<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Rezervarehotel;
use App\Models\Trzdetfact;
use App\Models\Trznp;
use App\Models\Trzdetnp;
use App\Models\Trzfact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Message;
use App\Services\RezervareHotelService;

class OrderService
{
    public function saveOrder(array $orderInfo, RezervareHotelService $rezervarehotelService)
    {
        $orderBookingInfo = $orderInfo['custom_info'];
        $clientInfo = $orderInfo['billing'];
        $bookedRooms = [];
        $client = $this->findOrCreateClient($clientInfo);
        $invoiceNo ='FA'.date('Y').str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

        foreach ($orderInfo['items'] as $item) {
            $bookedRooms = $this->processOrderItem($item, $orderBookingInfo, $client, $bookedRooms, $rezervarehotelService, $invoiceNo);
        }
        $this->generateInvoice($orderInfo, $invoiceNo);

        return true;
    }

    private function findOrCreateClient($clientInfo)
    {
        $client = Client::where('email',  $clientInfo['email'])
            ->where('tel', $clientInfo['phone'])
            ->first();
            
        if(!$client){
            $client = new Client();
            $client->email = $clientInfo['email'];
            $client->tel = $clientInfo['phone'];
        }
        $client->den = $clientInfo['first_name'] ;
        $client->prenume = $clientInfo['last_name'];
        $client->adresa1 = $clientInfo['address_1'];
        $client->adresa2 = $clientInfo['address_2'];
        $client->oras = $clientInfo['city'];
        $client->save();
    
        return $client;
    }

    private function processOrderItem($item, $orderBookingInfo, $client, $bookedRooms, RezervareHotelService $rezervarehotelService, $invoiceNo)
    {
        $roomsIds = array_map(fn($id) => (int)trim($id), explode(',', $item['product_meta_input']['_hotel_room_number'][0]));
        $hotelId = $item['product_meta_input']['_hotel_id'][0];
        $tipCamera = $item['product_meta_input']['_hotel_room_type_long'][0];
        $start = new \DateTime($orderBookingInfo['start_date']);
        $end = new \DateTime($orderBookingInfo['end_date']);
        $pret = $item['subtotal'] / $item['quantity'];
        $numberOfNights = $start->diff($end)->days;
        $freeRoomsIds = array_values(array_diff($roomsIds, $bookedRooms));
    
        $roomNumber = $rezervarehotelService->getRoomNumber(
            $freeRoomsIds,
            $orderBookingInfo['start_date'],
            $orderBookingInfo['end_date'],
            $hotelId
        );
        if (is_array($roomNumber) && !empty($roomNumber)) {
            $selectedRoom = reset($roomNumber);
        } else {
            // Handle the case where no room is available
            throw new \Exception('No available room found for the given criteria.');
        }
        $rezervare = $this->createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $selectedRoom);
        $trznp = $this->createTrznp($client, $item['subtotal'], $rezervare->idrezervarehotel);
        $this->createTrzdetnp($client, $item['subtotal'], $rezervare->idrezervarehotel,$trznp,$tipCamera, $item['quantity']);
        $this->createTrzfact($client, $item['subtotal'], $rezervare->idrezervarehotel, $invoiceNo);
        $this->createTrzdetfact($client, $item['subtotal'], $rezervare->idrezervarehotel, $trznp, $tipCamera, $item['quantity']);
        $bookedRooms[] = $selectedRoom;
        return $bookedRooms;
    }

    private function createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $roomNumber)
    {
        $rezervare = new Rezervarehotel();
        $rezervare->idcl = $client->spaid;
        $rezervare->idclagentie1 = 0;
        $rezervare->idclagentie2 = 0;
        $rezervare->datas = $orderBookingInfo['start_date'];
        $rezervare->dataf = $orderBookingInfo['end_date'];
        $rezervare->camera = $roomNumber;
        $rezervare->tipcamera = $tipCamera;
        $rezervare->nrnopti = $numberOfNights;
        $rezervare->nradulti = 2;
        $rezervare->nrcopii = 0;
        $rezervare->tipmasa = 'Fara MD';
        $rezervare->prettipmasa = $pret;
        $rezervare->pachet  = $tipCamera;
        $rezervare->pretcamera = $pret;
        $rezervare->pretnoapte = $pret;
        $rezervare->total = $pret;
        $rezervare->idfirma = 1;
        $rezervare->utilizator = 'Web';
        $rezervare->save();
        return $rezervare;
    }

    private function createTrznp($client, $pret,  $idrezervarehotel)
    {
        $trznp = new Trznp();
        $trznp->spaid = $client->spaid;
        $trznp->totalron = $pret;
        $trznp->tva19 = $pret * 0.19;
        $trznp->data = date('Y-m-d H:i:s');
        $trznp->compid = 'website';
        $trznp->obscui = 'intern';
        $trznp->modp = 'Credit Card';
        $trznp->tipnp = 'Inside Services';
        $trznp->idrezervarehotel = $idrezervarehotel;
        $trznp->save();
        return $trznp;
    }

    private function createTrzdetnp($client, $pret, $idrezervarehotel, $trznp, $tipCamera, $quantity)
    {
        $trzdetnp = new Trzdetnp();
        $trzdetnp->nrnp = $trznp->nrnpint;
        $trzdetnp->spaid = $client->spaid;
        $trzdetnp->art = $tipCamera;
        $trzdetnp->cant = $quantity;
        $trzdetnp->preturon = $pret / $quantity;
        $trzdetnp->valoare = $pret;
        $trzdetnp->data = date('Y-m-d H:i:s');
        $trzdetnp->compid = 'website';
        $trzdetnp->pretfaradisc = $pret;
        $trzdetnp->valuta = 'RON';
        $trzdetnp->cursv = 1;
        $trzdetnp->datac = date('Y-m-d H:i:s');
        $trzdetnp->dataactiv = date('Y-m-d H:i:s');
        $trzdetnp->tipf = 'Hotel';
        $trzdetnp->idrezervarehotel = $idrezervarehotel;
        $trzdetnp->save();
        return $trzdetnp;
    }

    public function createTrzfact(client $client, $pret,  $invoiceNumber){
        $trzfact = new Trzfact();
        $trzfact->idfirma = 1;
        $trzfact->nrfactfisc = ' ';
        $trzfact->nrdep = 1;
        $trzfact->nrgest = 1;
        $trzfact->idcl = $client->spaid;
        $trzfact->stotalron = $pret-( $pret * 0.19);
        $trzfact->tva = $pret * 0.19;
        $trzfact->cotatva = -1;
        $trzfact->totalron = $pret;
        $trzfact->sold = $pret;
        $trzfact->tipv = 'RON';
        $trzfact->nume = $client->den . ' ' . $client->prenume;
        $trzfact->cnp = $client->cnp;
        $trzfact->datafact = date('Y-m-d');
        $trzfact->datascad = date('Y-m-d');
        $trzfact->data = date('Y-m-d');
        $trzfact->compid = 'Website';
        $trzfact->tip='CP';
        $trzfact->nrfactspec = $invoiceNumber;
        $trzfact->costtot = $pret;
        $trzfact->save();
        return $trzfact;
    }

    public function createTrzdetfact ($client, $pret, $idrezervarehotel, $trznp, $tipCamera, $quantity){
        $trzdetfact = new Trzdetfact();
        $trzdetfact->idfirma = 1;
        $trzdetfact->save();
        return $trzdetfact;
    }

    public function generateInvoice($orderBookingInfo, $invoiceNo)
    {
        $data = ['title' => 'Welcome to Laravel PDF'];
        $data['spaces'] = 14 - count($orderBookingInfo['items']);
        $data['nrfactura'] =  $invoiceNo;
        $data['data'] = date('Y-m-d');
        $data['data_scadenta'] = date('Y-m-d');
        $data['client'] = $orderBookingInfo['billing'];
        foreach ($orderBookingInfo['items'] as $item) {
            $data['items'][] = [
                'name' => $item['product_meta_input']['_hotel_room_type_long'][0],
                'quantity' => $item['quantity'],
                'price' => $item['subtotal'] / $item['quantity'],
                'total' => $item['subtotal'],
                'tvaValue' => $item['subtotal'] * 0.19,
                'tva' => 19, // Assuming a fixed tax rate of 19%
            ];
        }
        $data['total'] = $orderBookingInfo['total'];
        $data['totalTax'] = $orderBookingInfo['total'] * 0.19;
        $data['totalWithoutTax'] =  $data['total'] -$data['totalTax'];
        $pdf = Pdf::loadView('invoice', $data);

        $invoiceDir = storage_path('invoices'. '/'.date('Y').'/'.date('m'));
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0777, true);
        }
        $fileName = $invoiceDir . '/invoice'.$data['nrfactura'].'.pdf';
        $pdf->save($fileName);

        $this->sendEmail($fileName, $orderBookingInfo);
    }

    protected function sendEmail($invoice, $orderBookingInfo)
    {
        $to = $orderBookingInfo['billing']['email'] ?? null;
        if (!$to || !file_exists($invoice)) {
            Log::error('Invoice email not sent: missing recipient or invoice file.');
            return false;
        }
        $subject = 'Factura ta de la MasterSPA';
        try {
            Mail::send('emails.invoice', [], function (Message $message) use ($to, $subject, $invoice) {
                $message->to($to)
                    ->subject($subject)
                    ->attach($invoice);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email: ' . $e->getMessage());
            return false;
        }
        return true;
    }
}
