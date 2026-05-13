<?php

namespace App\Services;

use App\Helper\Country;
use App\Models\Client;
use App\Models\Gest;
use App\Models\Pret;
use App\Models\Rezervarehotel;
use App\Models\Trzdetfact;
use App\Models\Trznp;
use App\Models\Trzdetnp;
use App\Models\Trzdet;
use App\Models\Trzfact;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Mail\Message;
use App\Services\RezervareHotelService;
use App\Helper\Judet;
use App\Models\Camerehotel;
use Throwable;

class OrderHotelService
{
    use OrderServiceCommonTrait;

    public function saveOrder(array $orderInfo, RezervareHotelService $rezervarehotelService)
    {
       
        $orderBookingInfo = $orderInfo['custom_info'];
        $clientInfo = $orderInfo['billing'];
        foreach ($orderInfo['meta_data'] as $key => $value) {
            if (strpos($value['key'], '_billing_') !== false) {
                $clientInfo[$value['key']] = $value['value'];
            }
        }
        $bookedRooms = [];
        $client = $this->findOrCreateClient($clientInfo);
        $clientPj = null;
        if (isset($clientInfo['_billing_company_details']) && $clientInfo['_billing_company_details'] == 1) {
            $clientPj = $this->findOrCreateClientPj($clientInfo, $client->spaid);
        }

        $invoiceNo = $this->invoiceNo;

        $rezervare = null;
        $trznp = null;
        $trzfact = null;
        Log::info('Creating rezervare for client', ['client_id' => $client->spaid]);
        foreach ($orderInfo['items'] as $item) {
            //$roomsIds = array_map(fn($id) => (int)trim($id), explode(',', $item['product_meta_input']['_hotel_room_number'][0])); 
            $roomsIds = array_values(array_filter(array_map(fn($id) => trim((string) $id), explode(',', $item['product_meta_input']['_hotel_room_number'][0]))));
            $normalizedBookedRooms = array_map(fn($room) => ltrim((string) $room, '0'), $bookedRooms);

            $freeRoomsIds = array_values(array_filter($roomsIds, function ($roomId) use ($normalizedBookedRooms) {
                $normalizedRoomId = ltrim((string) $roomId, '0');
                return !in_array($normalizedRoomId, $normalizedBookedRooms, true);
            }));
            $hotelId = $item['product_meta_input']['_hotel_id'][0];
            $tipCamera = $item['product_meta_input']['_hotel_room_type_long'][0];
            $start = new \DateTime($orderBookingInfo['start_date']);
            $end = new \DateTime($orderBookingInfo['end_date']);
            $pret = $item['subtotal'] / $item['quantity'];
            $numberOfNights = $start->diff($end)->days;

            $roomNumber = $rezervarehotelService->getRoomNumber(
                $freeRoomsIds,
                $orderBookingInfo['start_date'],
                $orderBookingInfo['end_date'],
                $hotelId
            );

            Log::info('Updating hotel for client', ['client_id' => $client->spaid, 'hotel' => $hotelId]);

            $this->updateHotelToClient($client, $hotelId);
            if (is_array($roomNumber) && !empty($roomNumber)) {
                $selectedRoom = reset($roomNumber);
            } else {
                $this->sendNoAvailableRoomAlert($orderInfo, [
                    'client_id' => $client->spaid,
                    'hotel_id' => $hotelId,
                    'requested_room_ids' => $roomsIds,
                    'remaining_room_ids' => $freeRoomsIds,
                    'start_date' => $orderBookingInfo['start_date'],
                    'end_date' => $orderBookingInfo['end_date'],
                    'item' => $item,
                ]);
                throw new \Exception('No available room found for the given criteria.');
            }
            $packageName = $item['meta_data'][0]['value'] ?? $item['name'] ?? '';
            $rezervare = $this->createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $selectedRoom, $hotelId, $packageName);

            // Only create trznp and trzfact for the first item (after first rezervare is created)
            if ($trznp === null && $rezervare) {
            
                $trznp = $this->createTrznp($client, $orderInfo['total'], $rezervare->idrezervarehotel);
                if($clientPj) {
                    $useClient = $clientPj;
                }else{
                    $useClient = $client;
                }
                $trzfact = $this->createTrzfact($useClient, $orderInfo['total'], $trznp, $invoiceNo);

            }
            
            $np = $trznp->nrnpint.'.00';
            $rezervare->nrnp = $np;
            $rezervare->save();

            $bookedRooms = $this->processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp, $tipCamera, $selectedRoom, $trzfact, $item['product_meta_input']['_hotel_room_type']);
        }

        $this->generateInvoice($orderInfo, $invoiceNo, $clientInfo, $this->getCompany(), $trznp ? $trznp->nrnpint : null);
       
        return true;
    }


    private function processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp, $tipCamera, $selectedRoom, $trzfact, $roomType)
    {


        // Add parameters: $rezervare, $trznp, $tipCamera, $selectedRoom
        $trzdetnp = $this->createTrzdetnp($client, $item['subtotal'], $rezervare->idrezervarehotel, $trznp, $tipCamera, $item['quantity'], $rezervare->pachet);

        $this->createTrzdet($trzdetnp);
        if($clientPj) {
            $client = $clientPj;
        }
        $this->createTrzdetfact($client, $item['subtotal'], $item['quantity'], $trzfact->nrfact, $roomType, $item);
        $bookedRooms[] = ltrim((string) $selectedRoom, '0');
        return $bookedRooms;
    }

    private function createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $roomNumber, $hotelId, $pachet)
    {
        $camera  = Camerehotel::where('idhotel', $hotelId)
            ->where('nr', $roomNumber)
            ->select('adultmax', 'kidmax')
            ->first();
        $isSingle = strpos(strtolower($pachet), 'single') !== false;
        $isMicDejun = strpos(strtolower($pachet), 'dejun') !== false;


        $rezervare = new Rezervarehotel();
        $rezervare->idcl = $client->spaid;
        $rezervare->idclagentie1 = 0;
        $rezervare->idclagentie2 = 0;
        $rezervare->datas = date('Y-m-d 14:00:00', strtotime($orderBookingInfo['start_date']));
        $rezervare->dataf = date('Y-m-d 11:00:00', strtotime($orderBookingInfo['end_date']));
        $rezervare->camera = $roomNumber;
        $rezervare->tipcamera = $tipCamera;
        $rezervare->nrnopti = $numberOfNights;
        $rezervare->nradulti = $isSingle ? 1 : $camera->adultmax;
        $rezervare->nrcopii = $orderBookingInfo['kids'] != 0 ? $camera->kidmax : 0;
        $isMicDejun = strpos(strtolower($pachet), 'dejun') !== false;
        $rezervare->tipmasa = $isMicDejun ? 'MD inclus' : 'Fara MD';
        $rezervare->prettipmasa = $pret;
        $rezervare->pachet  = $pachet;
        $rezervare->pretcamera = $pret;
        $rezervare->pretnoapte = $pret;
        $rezervare->total = $pret;
        $rezervare->idfirma = 1;
        $rezervare->utilizator = 'Web';
        $rezervare->idhotel = $hotelId;
        $rezervare->status = ' ';
        $rezervare->agent = ' ';
        $rezervare->platit = 1;
        $rezervare->save();
        // Get the last rezervare for this client (by primary key desc)
        $rezervare = Rezervarehotel::where('idcl',  $client->spaid)
            ->orderByDesc('idrezervarehotel')
            ->first();
        return $rezervare;
    }

    private function sendNoAvailableRoomAlert(array $orderInfo, array $context = []): void
    {
        $recipients = config('appconfig.invoice_bcc_recipients', []);
        if (empty($recipients)) {
            Log::warning('No recipients configured for no-room-available alert.', [
                'order_id' => $orderInfo['id'] ?? null,
                'context' => $context,
            ]);
            return;
        }

        $payload = [
            'order' => $orderInfo,
            'context' => $context,
        ];

        $body = "A hotel order failed because no available room was found.\n\n"
            . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            Mail::raw($body, function ($message) use ($recipients, $orderInfo) {
                $message->to($recipients)
                    ->subject('Eroare rezervare hotel - fara camera disponibila - comanda ' . ($orderInfo['id'] ?? 'necunoscuta'));
            });
        } catch (Throwable $exception) {
            Log::error('Failed to send no-room-available alert email.', [
                'order_id' => $orderInfo['id'] ?? null,
                'recipients' => $recipients,
                'context' => $context,
                'message' => $exception->getMessage(),
            ]);
        }
    }









    // ...existing code...
}
