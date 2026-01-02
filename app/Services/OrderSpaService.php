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

class OrderSpaService
{
    use OrderServiceCommonTrait;

    public function saveOrder(array $orderInfo)
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
        if ($clientInfo['_billing_company_details'] == 1) {
            $clientPj = $this->findOrCreateClientPj($clientInfo, $client->spaid);
        }

        $invoiceNo = $this->invoiceNo;

        $rezervare = null;
        $trznp = null;
        $trzfact = null;
        Log::info('Creating rezervare for client', ['client_id' => $client->spaid]);
        foreach ($orderInfo['items'] as $item) {
            

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

            $bookedRooms = $this->processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp, $trzfact, $item['product_meta_input']['_hotel_room_type']);
        }

        $this->generateInvoice($orderInfo, $invoiceNo, $clientInfo, $this->getCompany(), $trznp ? $trznp->nrnpint : null);
       
        return true;
    }


    private function processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp,  $trzfact, $roomType)
    {

        // Add parameters: $rezervare, $trznp, $tipCamera, $selectedRoom
        $trzdetnp = $this->createTrzdetnp($client, $item['subtotal'], $rezervare->idrezervarehotel, $trznp, null, $item['quantity'], $rezervare->pachet);

        $this->createTrzdet($trzdetnp);
        if($clientPj) {
            $client = $clientPj;
        }
        $this->createTrzdetfact($client, $item['subtotal'], $item['quantity'], $trzfact->nrfact, $roomType, $item);
     
    }


    private function createTrznp($client, $pret,  $idrezervarehotel)
    {
        $trznp = new Trznp();
        $trznp->spaid = $client->spaid;
        $trznp->totalron = $pret;
        $trznp->tva19 = $pret - $this->getVatFromPrice($pret);
        $trznp->data = date('Y-m-d H:i:s.v');
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

    private function createTrzdetnp($client, $pret, $idrezervarehotel, $trznp, $tipCamera, $quantity, $roomType)
    {
        $trzdetnp = new Trzdetnp();
        $trzdetnp->nrnp = $trznp->nrnpint;
        $trzdetnp->spaid = $client->spaid;
        $trzdetnp->art = $roomType;
        $trzdetnp->cant = $quantity;
        $trzdetnp->preturon = $pret / $quantity;
        $trzdetnp->valoare = $pret;
        $trzdetnp->data = date('Y-m-d H:i:s.v');
        $trzdetnp->compid = 'Website';
        $trzdetnp->pretfaradisc = $pret;
        $trzdetnp->valuta = 'RON';
        $trzdetnp->cursv = 1;
        $trzdetnp->datac = date('Y-m-d H:i:s.v');
        $trzdetnp->dataactiv = date('Y-m-d H:i:s.v');
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
            ->orderByDesc('idtrzf')
            ->first();

        return $trzdetnp;
    }



    public function createTrzfact(client $client, $pret, $trznp, $invoiceNo)
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
        $trzfact->datafact = date('Y-m-d H:i:s.v'); //2025-10-12 00:00:00.000
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
        $trzdetfact->preturon = round($this->getVatFromPrice($pret / $quantity), 2);
        $trzdetfact->valoare = round($this->getVatFromPrice($pret), 2);
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
         $data['client']['cnpcui'] = '0000000000000';
        $data['nrnp'] = $nrnp;
        $data['data'] = date('d-m-Y');
        $data['data_scadenta'] = date('d-m-Y');
        $isPj = false;
        $data['data_start'] = new \DateTime($orderBookingInfo['custom_info']['start_date']);
        $data['data_end'] = new \DateTime($orderBookingInfo['custom_info']['end_date']);
        if ($clientInfo['_billing_company_details'] == 1) {
            $isPj = true;
        }
        if ($isPj) {
            $data['client']['cnpcui'] = $clientInfo['_billing_cui'];
            $data['client']['den'] = $clientInfo['_billing_company_name'];
            $data['client']['prenume'] = '.' ;
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
                'pret_unit_no_vat' => round($this->getVatFromPrice($item['subtotal'] / $item['quantity']), 2),
                'total' => $item['subtotal'],
                'tvaValue' => round($item['subtotal'] - $this->getVatFromPrice($item['subtotal']), 2),
                'total_no_vat' =>  round($this->getVatFromPrice($item['subtotal']), 2),
                'tva' => $this->vatRate, // Assuming a fixed tax rate of 19%
            ];
        }
        $data['vatRate'] = $this->vatRate;
        $data['total'] = $orderBookingInfo['total'];
        $data['totalTax'] = round($orderBookingInfo['total'] - $this->getVatFromPrice($data['total']), 2);
        $data['totalWithoutTax'] =  round($this->getVatFromPrice($data['total']), 2);
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
        $bccRecipients = ['codrut_befu@yahoo.com', 'support@masterspa.ro'];
        if (!$to || !file_exists($invoice)) {
            Log::error('Invoice email not sent: missing recipient or invoice file.');
            return false;
        }
        $subject = 'Rezervarea dumneavoastra de la Noblesse';
        try {
            Mail::send('emails.invoice', [], function (Message $message) use ($to, $subject, $invoice, $bccRecipients) {
                $message->to($to)
                    ->bcc($bccRecipients)
                    ->subject($subject)
                    ->attach($invoice);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email: ' . $e->getMessage());
            return false;
        }

        try {
            $bccSubject = 'Comanda noua - ' . ($orderBookingInfo['billing']['first_name'] ?? 'Client');
            $bccData = [
                'order' => $orderBookingInfo,
                'invoiceFile' => basename($invoice),
            ];

            Mail::send('emails.invoice_bcc', $bccData, function (Message $message) use ($bccRecipients, $bccSubject, $invoice) {
                $message->to($bccRecipients)
                    ->subject($bccSubject)
                    ->attach($invoice);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send BCC invoice email: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    // ...existing code...
}