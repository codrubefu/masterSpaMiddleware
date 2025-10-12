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
use Illuminate\Mail\Message;
use App\Services\RezervareHotelService;
use App\Helper\Judet;
use App\Models\Camerehotel;

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
        $invoiceNo = 'FA' . date('y') . $this->nrGest . $number;

        $rezervare = null;
        $trznp = null;
        $trzfact = null;
        Log::info('Creating rezervare for client', ['client_id' => $client->spaid]);

        foreach ($orderInfo['items'] as $item) {
            //$roomsIds = array_map(fn($id) => (int)trim($id), explode(',', $item['product_meta_input']['_hotel_room_number'][0])); 
            $roomsIds = array_map(fn($id) => trim($id), explode(',', $item['product_meta_input']['_hotel_room_number'][0]));

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
            
            Log::info('Updating hotel for client', ['client_id' => $client->spaid, 'hotel' => $hotelId]);

            $this->updateHotelToClient($client, $hotelId);
            if (is_array($roomNumber) && !empty($roomNumber)) {
                $selectedRoom = reset($roomNumber);
            } else {
                throw new \Exception('No available room found for the given criteria.');
            }


            $rezervare = $this->createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $selectedRoom, $hotelId, strpos(strtolower($item['meta_data'][0]['value']), 'single') !== false);

            // Only create trznp and trzfact for the first item (after first rezervare is created)
            if ($trznp === null && $rezervare) {
                $trznp = $this->createTrznp($client, $orderInfo['total'], $rezervare->idrezervarehotel);
                $trzfact = $this->createTrzfact($client, $orderInfo['total'], $trznp, $invoiceNo);
            }

            $bookedRooms = $this->processOrderItem($item,  $client, $bookedRooms,  $rezervare, $trznp, $tipCamera, $selectedRoom, $trzfact, $item['product_meta_input']['_hotel_room_type']);
        }

        $this->generateInvoice($orderInfo, $invoiceNo, $clientInfo, $this->getCompany(), $trznp ? $trznp->nrnpint : null);
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
         $client->den = '';
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
       
        $client = Client::where('email',  $clientInfo['email'])
                ->where('mobilcontact', $clientInfo['phone'])
                ->first();
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
        $client->clhead = $client->spaid; // Self-referential
        $client->save();
        return $client;
    }

    private function processOrderItem($item,  $client, $bookedRooms,  $rezervare, $trznp, $tipCamera, $selectedRoom, $trzfact, $roomType)
    {
        // Add parameters: $rezervare, $trznp, $tipCamera, $selectedRoom
        $trzdetnp = $this->createTrzdetnp($client, $item['subtotal'], $rezervare->idrezervarehotel, $trznp, $tipCamera, $item['quantity']);
  
        $this->createTrzdet($trzdetnp);
        $this->createTrzdetfact($client, $item['subtotal'], $item['quantity'], $trzfact->nrfact, $roomType,$item);
        $bookedRooms[] = $selectedRoom;
        return $bookedRooms;
    }

    private function createRezervarehotel($client, $orderBookingInfo, $tipCamera, $numberOfNights, $pret, $roomNumber, $hotelId, $isSingle)
    {
        $camera  = Camerehotel::where('idhotel', $hotelId)
        ->where('nr', $roomNumber)
        ->select('adultmax', 'kidmax')
        ->first();
        $rezervare = new Rezervarehotel();
        $rezervare->idcl = $client->spaid;
        $rezervare->idclagentie1 = 0;
        $rezervare->idclagentie2 = 0;
        $rezervare->datas = $orderBookingInfo['start_date'];
        $rezervare->dataf = $orderBookingInfo['end_date'];
        $rezervare->camera = $roomNumber;
        $rezervare->tipcamera = $tipCamera;
        $rezervare->nrnopti = $numberOfNights;
        $rezervare->nradulti = $isSingle ? 1 : $camera->adultmax;
        $rezervare->nrcopii = $orderBookingInfo['kids'] != 0 ? $camera->kidmax : 0;
        $rezervare->tipmasa = 'Fara MD';
        $rezervare->prettipmasa = $pret;
        $rezervare->pachet  = $tipCamera;
        $rezervare->pretcamera = $pret;
        $rezervare->pretnoapte = $pret;
        $rezervare->total = $pret;
        $rezervare->idfirma = 1;
        $rezervare->utilizator = 'Web';
        $rezervare->idhotel = $hotelId;
        $rezervare->save();
        // Get the last rezervare for this client (by primary key desc)
        $rezervare = Rezervarehotel::where('idcl',  $client->spaid)
            ->orderByDesc('idrezervarehotel')
            ->first();
        return $rezervare;
    }

    private function createTrznp($client, $pret,  $idrezervarehotel)
    {
        $trznp = new Trznp();
        $trznp->spaid = $client->spaid;
        $trznp->totalron = $pret;
        $trznp->tva19 = $pret - $this->getVatFromPrice($pret);
        $trznp->data = date('Y-m-d H:i:s');
        $trznp->compid = 'WEBSITE';
        $trznp->obscui = 'independent';
        $trznp->modp = 'Bank Card Web';
        $trznp->tipnp = 'Inside Services';
        $trznp->tipcc = 'VISA';
        $trznp->nrtrzcc = '1234****3456';
        $trznp->cardid  = ' ';
        $trznp->idrec  = 0;
        $trznp->idlogin = 0;
        $trznp->prmcod  = ' ';
        $trznp->idrezervarehotel = $idrezervarehotel;
        $trznp->tip = 'WEBSITE';
        $trznp->obs = ' Sales Order from Website: ' . $idrezervarehotel;
        $trznp->descval = $this->numberToRomanianText($pret);

        $trznp->save();
        $trznp = Trznp::where('spaid',  $client->spaid)
            ->orderByDesc('nrnpint')
            ->first();
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
        $trzdetnp->idcldet = $client->spaid;
        $trzdetnp->pretueur = 0.00;
        $trzdetnp->nrvestiar = ' ';
        $trzdetnp->cotatva = $this->vatRate / 100;
        $trzdetnp->nrvestiar2 = ' ';
        $trzdetnp->save();
     
        $trzdetnp = Trzdetnp::where('spaid',  $client->spaid)
        ->orderByDesc('nrnp')
        ->first();
        return $trzdetnp;
    }


    private function numberToRomanianText($number)
    {
        $number = (int) $number;
        
        if ($number == 0) {
            return 'zero';
        }

        $units = ['', 'unu', 'doi', 'trei', 'patru', 'cinci', 'șase', 'șapte', 'opt', 'nouă'];
        $teens = ['zece', 'unsprezece', 'doisprezece', 'treisprezece', 'paisprezece', 'cincisprezece', 
                  'șaisprezece', 'șaptesprezece', 'optsprezece', 'nouăsprezece'];
        $tens = ['', '', 'douăzeci', 'treizeci', 'patruzeci', 'cincizeci', 'șaizeci', 'șaptezeci', 'optzeci', 'nouăzeci'];
        $hundreds = ['', 'una sută', 'două sute', 'trei sute', 'patru sute', 'cinci sute', 
                     'șase sute', 'șapte sute', 'opt sute', 'nouă sute'];

        $result = '';

        // Millions
        if ($number >= 1000000) {
            $millions = intval($number / 1000000);
            if ($millions == 1) {
                $result .= 'un milion ';
            } else {
                $result .= $this->convertHundreds($millions, $units, $teens, $tens, $hundreds) . ' milioane ';
            }
            $number %= 1000000;
        }

        // Thousands
        if ($number >= 1000) {
            $thousands = intval($number / 1000);
            if ($thousands == 1) {
                $result .= 'o mie ';
            } else {
                $result .= $this->convertHundreds($thousands, $units, $teens, $tens, $hundreds) . ' mii ';
            }
            $number %= 1000;
        }

        // Hundreds, tens, units
        if ($number > 0) {
            $result .= $this->convertHundreds($number, $units, $teens, $tens, $hundreds);
        }

        return trim($result);
    }

    private function convertHundreds($number, $units, $teens, $tens, $hundreds)
    {
        $result = '';

        // Hundreds
        if ($number >= 100) {
            $hundredsDigit = intval($number / 100);
            $result .= $hundreds[$hundredsDigit] . ' ';
            $number %= 100;
        }

        // Tens and units
        if ($number >= 20) {
            $tensDigit = intval($number / 10);
            $unitsDigit = $number % 10;
            $result .= $tens[$tensDigit];
            if ($unitsDigit > 0) {
                $result .= ' și ' . $units[$unitsDigit];
            }
        } elseif ($number >= 10) {
            $result .= $teens[$number - 10];
        } elseif ($number > 0) {
            $result .= $units[$number];
        }

        return trim($result);
    }

    public function createTrzfact(client $client, $pret, $trznp,$invoiceNo)
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
        $trzfact->datafact = date('Y-m-d H:i:s.v');//2025-10-12 00:00:00.000
        $trzfact->datascad = date('Y-m-d H:i:s.v');
        $trzfact->data = date('Y-m-d H:i:s.v');
        $trzfact->compid = 'Website';
        $trzfact->tip = 'CP';
        $trzfact->nrfactspec = $invoiceNo;
        $trzfact->idpers = 0;
        $trzfact->curseur = 0.0000;
        $trzfact->cursusd = 0.0000;
        $trzfact->nrnp = $trznp->nrnpint;
        $trzfact->ciserie = ' ';
        $trzfact->cinr = ' ';
        $trzfact->cipol = ' ';
        $trzfact->auto = ' ';
        $trzfact->nrauto = ' ';
        $trzfact->save();
        $trzfact = Trzfact::where('idcl',  $client->spaid)
            ->orderByDesc('nrfact')
            ->first();

        $trznp->nrfact = $trzfact->nrfact;
        $trznp->save();
        return $trzfact;
    }

    public function createTrzdetfact($client, $pret,  $quantity, $nrFact, $roomType, $item)
    {

        $price = Pret::where('tipcamera', $roomType)->first();
       
        $trzdetfact = new Trzdetfact();
        $trzdetfact->idfirma = 1;
        $trzdetfact->nrfact = $nrFact;
        $trzdetfact->idcl = $client->spaid;
        $trzdetfact->clasa = $price->clasa;
        $trzdetfact->grupa = $price->grupa;
        $trzdetfact->art = $item['meta_data'][0]['value'];
        $trzdetfact->cant = $quantity;
        $trzdetfact->cantf = $quantity;
        $trzdetfact->preturon = round($this->getVatFromPrice($pret / $quantity),2);
        $trzdetfact->valoare = round($this->getVatFromPrice($pret),2); 
        $trzdetfact->tva = $pret - $this->getVatFromPrice($pret); // (pret fara tva);
        $trzdetfact->data = date('Y-m-d H:i:s.v'); //2025-10-12 00:00:00.000
        $trzdetfact->compid = 'Website';
        $trzdetfact->idpers = '0';
        $trzdetfact->cotatva = $this->vatRate / 100;
        $trzdetfact->save();
        return $trzdetfact;
    }

    public function generateInvoice($orderBookingInfo, $invoiceNo, $clientInfo, $company, $nrnp)
    {
        $data = ['title' => 'Master Hotel'];
        $data['spaces'] = 14 - count($orderBookingInfo['items']);
        $data['nrfactura'] =  $invoiceNo;
        $data['data'] = date('Y-m-d');
        $data['data_scadenta'] = date('Y-m-d');
        $data['client'] = $clientInfo;
        $data['nrnp'] = $nrnp;
        $data['data'] = date('d-m-Y');
        $data['data_scadenta'] =date('d-m-Y');
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
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['subtotal'] / $item['quantity'],
                'pret_unit_no_vat' => round($this->getVatFromPrice($item['subtotal'] / $item['quantity']),2),
                'total' => $item['subtotal'],
                'tvaValue' => round($item['subtotal'] - $this->getVatFromPrice($item['subtotal']),2),
                'total_no_vat' =>  round($this->getVatFromPrice($item['subtotal']),2),
                'tva' => $this->vatRate, // Assuming a fixed tax rate of 19%
            ];
        }
         $data['vatRate'] = $this->vatRate;
        $data['total'] = $orderBookingInfo['total'];
        $data['totalTax'] = round($orderBookingInfo['total'] - $this->getVatFromPrice($data['total']),2);
        $data['totalWithoutTax'] =  round($this->getVatFromPrice($data['total']),2);
        $pdf = Pdf::loadView('invoice', $data);

        $invoiceDir = storage_path('invoices' . '/' . date('y') . '/' . date('m'));
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0777, true);
        }
        $fileName = $invoiceDir . '/invoice' . $data['nrfactura'] . '.pdf';
        $pdf->save($fileName);

        $this->sendEmail($fileName, $orderBookingInfo);
    }

    protected function createTrzdet($trzdetnp)
    {
        $trzdet = new Trzdet();
        $trzdet->idfirma = 1;
        $trzdet->nrdoc = $trzdetnp->nrnp;
        $trzdet->idcl = $trzdetnp->spaid;
        $trzdet->art = $trzdetnp->art;
        $trzdet->cant = $trzdetnp->cant;
        $trzdet->pretueur = 0.00;
        $trzdet->preturon = $trzdetnp->preturon;
        $trzdet->valoare = $trzdetnp->valoare;
        $trzdet->tva = null;
        $trzdet->tip = 'NP';
        $trzdet->data = date('Y-m-d H:i:s.v'); //2025-10-12 00:00:00.000
        $trzdet->compid = $trzdetnp->compid;
        $trzdet->idpers = $trzdetnp->idpers;
        $trzdet->redproc = $trzdetnp->redproc ?? null;
        $trzdet->cardid = $trzdetnp->cardid;
        $trzdet->redabn = $trzdetnp->redabn ?? null;
        $trzdet->pretfaradisc = $trzdetnp->pretfaradisc;
        $trzdet->modp = 'Bank Card Web';
        $trzdet->idprog = $trzdetnp->idprog;
        $trzdet->idtrz = $trzdetnp->idtrz;
        $trzdet->idrevc = 1;
        $trzdet->valuta = 'RON';
        $trzdet->cursv = 1.000;
        $trzdet->datac = date('Y-m-d H:i:s.v'); //2025-10-12 00:00:00.000
        $trzdet->cotatva = $this->vatRate / 100;
        $trzdet->reinnoire = false;
        $trzdet->cardb =  null;
        $trzdet->idcldet = $trzdetnp->spaid;
        $trzdet->cardb = 0;
        $trzdet->save();
        return $trzdet;
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
