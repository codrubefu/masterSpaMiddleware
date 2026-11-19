<?php

namespace App\Services;

use App\Models\Camerehotel;
use App\Models\Client;
use App\Models\Rezervarehotel;
use App\Services\Orders\ClientResolver;
use App\Services\Orders\NpInvoiceService;
use App\Services\Orders\OrderMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderHotelService
{
    public function __construct(
        private ClientResolver $clients,
        private NpInvoiceService $npInvoice,
        private OrderMailer $mailer,
    ) {
    }

    public function saveOrder(array $orderInfo, RezervareHotelService $rezervarehotelService)
    {
        $orderBookingInfo = $orderInfo['custom_info'];
        $clientInfo = $orderInfo['billing'];
        foreach ($orderInfo['meta_data'] ?? [] as $value) {
            if (strpos($value['key'], '_billing_') !== false) {
                $clientInfo[$value['key']] = $value['value'];
            }
        }

        // All DB writes are atomic: e.g. "no available room" on the 2nd item
        // rolls back the 1st item's rezervare, trznp and trzfact.
        [$trznp, $trzfact] = DB::transaction(function () use ($orderInfo, $orderBookingInfo, $clientInfo, $rezervarehotelService) {
            $client = $this->clients->findOrCreate($clientInfo);
            $clientPj = $this->clients->resolveCompany($clientInfo, $client);

            $start = new \DateTime($orderBookingInfo['start_date']);
            $end = new \DateTime($orderBookingInfo['end_date']);
            $numberOfNights = $start->diff($end)->days;

            $bookedRooms = [];
            $trznp = null;
            $trzfact = null;
            Log::info('Creating rezervare for client', ['client_id' => $client->spaid]);

            foreach ($orderInfo['items'] as $item) {
                $meta = $item['product_meta_input'];
                $roomsIds = array_map('trim', explode(',', $meta['_hotel_room_number'][0]));
                $hotelId = $meta['_hotel_id'][0];
                $tipCamera = $meta['_hotel_room_type_long'][0];
                $pret = $item['subtotal'] / $item['quantity'];

                $roomNumber = $rezervarehotelService->getRoomNumber(
                    array_values(array_diff($roomsIds, $bookedRooms)),
                    $orderBookingInfo['start_date'],
                    $orderBookingInfo['end_date'],
                    $hotelId
                );
                if (!is_array($roomNumber) || empty($roomNumber)) {
                    throw new \Exception('No available room found for the given criteria.');
                }
                $selectedRoom = reset($roomNumber);
                $bookedRooms[] = $selectedRoom;

                $this->clients->assignHotel($client, $hotelId);

                $rezervare = $this->createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $selectedRoom, $hotelId, $item['meta_data'][0]['value']);

                if (!$this->npInvoice->enabled()) {
                    continue;
                }

                // One trznp + trzfact per order, linked to the first rezervare.
                if ($trznp === null) {
                    $trznp = $this->npInvoice->createNp($client, $orderInfo['total'], $rezervare->idrezervarehotel);
                    $trzfact = $this->npInvoice->createInvoice($clientPj ?? $client, $orderInfo['total'], $trznp);
                }

                $rezervare->nrnp = $trznp->nrnpint . '.00';
                $rezervare->save();

                $this->npInvoice->createNpLine($client, $trznp, $item['subtotal'], $item['quantity'], $rezervare->idrezervarehotel, $rezervare->pachet);
                $this->npInvoice->createInvoiceLine($clientPj ?? $client, $trzfact, $item['subtotal'], $item['quantity'], $item, $meta['_hotel_room_type']);
            }

            return [$trznp, $trzfact];
        });

        // PDF + emails only after the order is committed; a failure here must
        // not return an error (WooCommerce would retry and duplicate the order).
        try {
            if ($trzfact) {
                $this->mailer->sendHotelInvoice($orderInfo, $clientInfo, trim($trzfact->nrfactspec), $trznp->nrnpint);
            } else {
                // No invoice in DB -> no invoice PDF; just a booking confirmation.
                $this->mailer->sendHotelBooking($orderInfo);
            }
        } catch (\Throwable $e) {
            Log::error('Hotel order saved, but emails failed', ['order_id' => $orderInfo['id'] ?? null, 'message' => $e->getMessage(), 'exception' => $e]);
        }

        return true;
    }

    private function createRezervarehotel(Client $client, array $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $roomNumber, $hotelId, $pachet): Rezervarehotel
    {
        $camera = Camerehotel::where('idhotel', $hotelId)
            ->where('nr', $roomNumber)
            ->select('adultmax', 'kidmax')
            ->first();
        if (!$camera) {
            throw new \RuntimeException("Room {$roomNumber} not found in hotel {$hotelId}.");
        }
        $isSingle = str_contains(strtolower($pachet), 'single');
        $isMicDejun = str_contains(strtolower($pachet), 'dejun');

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
        $rezervare->nrcopii = ($orderBookingInfo['kids'] ?? 0) != 0 ? $camera->kidmax : 0;
        $rezervare->tipmasa = $isMicDejun ? 'MD inclus' : 'Fara MD';
        $rezervare->prettipmasa = $pret;
        $rezervare->pachet = $pachet;
        $rezervare->pretcamera = $pret;
        $rezervare->pretnoapte = $pret;
        $rezervare->total = $pret;
        $rezervare->idfirma = (int) config('orders.idfirma', 1);
        $rezervare->utilizator = 'Web';
        $rezervare->idhotel = $hotelId;
        $rezervare->status = ' ';
        $rezervare->agent = ' ';
        $rezervare->platit = 1;
        $rezervare->save();

        return $rezervare->refresh();
    }
}
