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
use App\Models\Trzfact;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Message;
use App\Services\RezervareHotelService;
use App\Helper\Judet;


class OrderService
{

    protected $nrGest = 10101;
    protected $vatRate = 11;

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
       
        $seria = $this->getSerie();
        $number = str_pad($this->getNrf(), 5, '0', STR_PAD_LEFT);
        $invoiceNo = 'FA1' . date('y') . $this->nrGest . $number;

        $rezervare = null;
        $trznp = null;
        $trzfact = null;
        foreach ($orderInfo['items'] as $item) {
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
            $this->updateHotelToClient($client, $hotelId);
            if (is_array($roomNumber) && !empty($roomNumber)) {
                $selectedRoom = reset($roomNumber);
            } else {
                throw new \Exception('No available room found for the given criteria.');
            }


            $rezervare = $this->createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $selectedRoom);

            // Only create trznp and trzfact for the first item (after first rezervare is created)
            if ($trznp === null && $rezervare) {
                $trznp = $this->createTrznp($client, $orderInfo['total'], $rezervare->idrezervarehotel);
                $trzfact = $this->createTrzfact($client, $orderInfo['total'], $rezervare->idrezervarehotel,  $trznp->nrnpint, $invoiceNo);
            }

            $bookedRooms = $this->processOrderItem($item,  $client, $bookedRooms,  $rezervare, $trznp, $tipCamera, $selectedRoom, $trzfact, $item['product_meta_input']['_hotel_room_type']);
        }

        $this->generateInvoice($orderInfo, $invoiceNo,$clientInfo, $this->getCompany());
        $this->updateNrf();
        return true;
    }

    public function getNrf()
    {
        $nrf = Gest::where('nrgest', $this->nrGest)->first()->nrf;
        return $nrf + 1;
    }

    public function updateNrf()
    {
        $nrf = Gest::where('nrgest', $this->nrGest)->first();
        $nrf->nrf = $nrf->nrf + 1;
        $nrf->save();
    }

    public function getSerie()
    {
        $company = Company::where('idfirma', 1)->first();
        return  $company->serie;
    }

    public function getCompany()
    {
      return Company::where('idfirma', 1)->first();
    }

    private function findOrCreateClient($clientInfo)
    {
        $client = Client::where('email',  $clientInfo['email'])
            ->where('mobilcontact', $clientInfo['phone'])
            ->first();
        if (!$client) {
            $client = new Client();
            $client->email = $clientInfo['email'];
            $client->mobilcontact = $clientInfo['phone'];
        }
        $isPj = false;
        if ($clientInfo['_billing_company_details'] == 1) {
            $isPj = true;
        }

        $client->den        = $clientInfo['first_name'];
        $client->prenume    = $clientInfo['last_name'];
        $client->adresa1    = $clientInfo['address_1'];
        $client->adresa2    = $clientInfo['address_2'];
        $client->pj         = $isPj;
        $client->modp       = 'Website';
        $client->obscui     = 'independent';
        $client->startper   = date('Y-m-d H:i:s');
        $client->endper     = date('Y-m-d H:i:s');
        $client->datan      = date('Y-m-d H:i:s');
        $client->camera     = 0;
        $client->datacreare = date('Y-m-d H:i:s');
        $client->compid     = 'Website';
        $client->tip        = 'Website';
        $client->oras       = $clientInfo['city'];
        $client->judet      = Judet::getNameByCode($clientInfo['state']);
        $client->tara       = Country::getNameByCode($clientInfo['country']);
        $client->valuta     = 'RON';
        $client->hotel      = 'Extra';
        if ($isPj) {
            $client->cnpcui     = $clientInfo['_billing_cui'];
            $client->den        = $clientInfo['_billing_company_name'];
            $client->prenume    = $clientInfo['first_name'].' '.$clientInfo['last_name'];
            $client->obscui     = $clientInfo['_billing_cui'];
            $client->nrc        = $clientInfo['_billing_reg_com'];
            $client->banca      = $clientInfo['_billing_banca'];
            $client->iban       = $clientInfo['_billing_cont_iban'];
        }
        $client->save();
        $client->refresh();
        $client->clhead = $client->spaid; // Self-referential
          
        $client->save();
        $client->refresh();
        return $client;
    }


    private function updateHotelToClient($client, $hotel)
    {
        Log::info('Updating hotel for client', ['client_id' => $client->spaid, 'hotel' => $hotel]);
        if ($hotel == 1) {
            $client->hotel = '1~Hotel Noblesse';
        } else {
            $client->hotel = '1~Hotel Royal';
        }
        $client->save();
        $client->refresh();
        return $client;
    }

    private function processOrderItem($item,  $client, $bookedRooms,  $rezervare, $trznp, $tipCamera, $selectedRoom, $trzfact, $roomType)
    {
        // Add parameters: $rezervare, $trznp, $tipCamera, $selectedRoom
        $this->createTrzdetnp($client, $item['subtotal'], $rezervare->idrezervarehotel, $trznp, $tipCamera, $item['quantity']);
        $this->createTrzdetfact($client, $item['subtotal'], $item['quantity'], $trzfact->nrfact, $roomType);
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
        $rezervare->refresh();
        return $rezervare;
    }

    private function createTrznp($client, $pret,  $idrezervarehotel)
    {
        $trznp = new Trznp();
        $trznp->spaid = $client->spaid;
        $trznp->totalron = $pret;
        $trznp->tva19 = $pret - $this->getVatFromPrice($pret);
        $trznp->data = date('Y-m-d H:i:s');
        $trznp->compid = 'Website';
        $trznp->obscui = 'independent';
        $trznp->modp = 'Bank Card Web';
        $trznp->tipnp = 'Inside Services';
        $trznp->idrezervarehotel = $idrezervarehotel;
        $trznp->tip = 'Website';
        $trznp->save();
        $trznp->refresh();
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
        $trzdetnp->compid = 'Website';
        $trzdetnp->pretfaradisc = $pret;
        $trzdetnp->valuta = 'RON';
        $trzdetnp->cursv = 1;
        $trzdetnp->datac = date('Y-m-d H:i:s');
        $trzdetnp->dataactiv = date('Y-m-d H:i:s');
        $trzdetnp->tipf = 'Hotel';
        $trzdetnp->idrezervarehotel = $idrezervarehotel;
        $trzdetnp->cardid = 0;
        $trzdetnp->idprog = 0;
        $trzdetnp->idtrz = 0;
        $trzdetnp->save();
        $trzdetnp->refresh();
        return $trzdetnp;
    }

    public function createTrzfact(client $client, $pret, $trznpid,$invoiceNo)
    {
        $trzfact = new Trzfact();
        $trzfact->idfirma = 1;
        $trzfact->nrfactfisc = ' ';
        $trzfact->nrdep = 1;
        $trzfact->nrgest = $this->nrGest;
        $trzfact->idcl = $client->spaid;
        $trzfact->stotalron = $this->getVatFromPrice($pret);
        $trzfact->tva =  $pret - $this->getVatFromPrice($pret);
        $trzfact->cotatva = -1;
        $trzfact->totalron = $pret;
        $trzfact->sold = $pret;
        $trzfact->tipv = 'RON';
        $trzfact->nume = $client->den . ' ' . $client->prenume;
        $trzfact->cnp = '000000000000';
        $trzfact->datafact = date('Y-m-d');
        $trzfact->datascad = date('Y-m-d');
        $trzfact->data = date('Y-m-d');
        $trzfact->compid = 'Website';
        $trzfact->tip = 'CP';
        $trzfact->nrfactspec = $invoiceNo;
        $trzfact->idpers = 0;
        $trzfact->curseur = 0.0000;
        $trzfact->cursusd = 0.0000;
        $trzfact->nrnp = $trznpid;
        $trzfact->save();
        $trzfact->refresh();
        return $trzfact;
    }

    public function createTrzdetfact($client, $pret,  $quantity, $nrFact, $roomType)
    {

        $price = Pret::where('tipcamera', $roomType)->first();

        $trzdetfact = new Trzdetfact();
        $trzdetfact->idfirma = 1;
        $trzdetfact->nrfact = $nrFact;
        $trzdetfact->idcl = $client->spaid;
        $trzdetfact->clasa = $price->clasa;
        $trzdetfact->grupa = $price->grupa;
        $trzdetfact->art = $price->art;
        $trzdetfact->cant = $quantity;
        $trzdetfact->cantf = $quantity;
        $trzdetfact->preturon = $pret / $quantity;
        $trzdetfact->valoare = $this->getVatFromPrice($pret); 
        $trzdetfact->tva = $pret - $this->getVatFromPrice($pret); // (pret fara tva);
        $trzdetfact->data = date('Y-m-d');
        $trzdetfact->compid = 'Website';
        $trzdetfact->idpers = '0';
        $trzdetfact->cotatva = $this->vatRate / 100;
        $trzdetfact->save();
        $trzdetfact->refresh();
        return $trzdetfact;
    }

    public function generateInvoice($orderBookingInfo, $invoiceNo, $clientInfo, $company)
    {
        $data = ['title' => 'Master Hotel'];
        $data['spaces'] = 14 - count($orderBookingInfo['items']);
        $data['nrfactura'] =  $invoiceNo;
        $data['data'] = date('Y-m-d');
        $data['data_scadenta'] = date('Y-m-d');
        $data['client'] = $clientInfo;
        $isPj = false;
        if ($clientInfo['_billing_company_details'] == 1) {
            $isPj = true;
        }
        if ($isPj) {
            $data['client']['cnpcui'] = $clientInfo['_billing_cui'];
            $data['client']['den'] = $clientInfo['_billing_company_name'];
            $data['client']['prenume'] = $clientInfo['first_name'].' '.$clientInfo['last_name'];
            $data['client']['obscui'] = $clientInfo['_billing_cui'];
            $data['client']['nrc'] = $clientInfo['_billing_reg_com'];
            $data['client']['banca'] = $clientInfo['_billing_banca'];
            $data['client']['iban'] = $clientInfo['_billing_cont_iban'];
        }
        $data['company'] = $company;
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
        $data['totalWithoutTax'] =  $data['total'] - $data['totalTax'];
        $pdf = Pdf::loadView('invoice', $data);

        $invoiceDir = storage_path('invoices' . '/' . date('y') . '/' . date('m'));
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0777, true);
        }
        $fileName = $invoiceDir . '/invoice' . $data['nrfactura'] . '.pdf';
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

    /**
     * Calculate the VAT value from a price, with VAT set to 11%.
     *
     * @param float|int $price
     * @return float
     */
    public function getVatFromPrice($priceWithVAT)
    {
        return  $priceWithVAT / (1 + ($this->vatRate / 100));
    }
}
