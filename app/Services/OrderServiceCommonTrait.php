<?php


namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

trait OrderServiceCommonTrait
{
    protected $nrGest = 10101;
    protected $vatRate = 11;
    protected $number;
    protected $invoiceNo;

    public function __construct()
    {
        $this->number = $this->generateNextInvoiceSequence();
        $this->invoiceNo = 'FA' . date('y') . $this->nrGest . $this->number;
    }

    private function generateNextInvoiceSequence(): string
    {
        $nextNrf = DB::transaction(function () {
            $gest = \App\Models\Gest::where('nrgest', $this->nrGest)->lockForUpdate()->firstOrFail();
            $gest->nrf = $gest->nrf + 1;
            $gest->save();
            return $gest->nrf;
        });
        return str_pad($nextNrf, 5, '0', STR_PAD_LEFT);
    }

    public function getSerie()
    {
        $company = \App\Models\Company::where('idfirma', 1)->first();
        return  $company->serie;
    }

    public function getCompany()
    {
        return \App\Models\Company::where('idfirma', 1)->first();
    }

    private function findOrCreateClient($clientInfo)
    {
        // Validate mandatory fields
        foreach (['last_name', 'first_name', 'phone', 'email'] as $field) {
            if (empty($clientInfo[$field])) {
                throw new \InvalidArgumentException("Missing mandatory clientInfo field: $field");
            }
        }

        $client = \App\Models\Client::where('email', $clientInfo['email'])
            ->where('mobilcontact', $clientInfo['phone'])
            ->first();

        if (!$client) {
            $client = new \App\Models\Client();
            $client->email = $clientInfo['email'];
            $client->mobilcontact = $clientInfo['phone'];
        }

        $isPj = false;
        $client->den        = $clientInfo['last_name'];
        $client->prenume    = $clientInfo['first_name'];
        $client->adresa1    = $clientInfo['address_1'] ?? null;
        $client->adresa2    = $clientInfo['address_2'] ?? null;
        $client->pj         = $isPj;
        $client->modp       = 'Website';
        $client->obscui     = 'independent';
        $client->startper   = date('Y-m-d H:i:s.v');
        $client->endper     = date('Y-m-d H:i:s.v');
        $client->datan      = date('Y-m-d H:i:s.v');
        $client->camera     = 0;
        $client->datacreare = date('Y-m-d H:i:s.v');
        $client->compid     = 'Website';
        $client->tip        = 'Website';
        $client->oras       = $clientInfo['city'] ?? null;
        $client->judet      = isset($clientInfo['state']) ? \App\Helper\Judet::getNameByCode($clientInfo['state']) : null;
        $client->tara       = isset($clientInfo['country']) ? \App\Helper\Country::getNameByCode($clientInfo['country']) : null;
        $client->valuta     = 'RON';
        $client->hotel      = 'Extra';
        $client->cnpcui     = '0000000000000';
        $client->save();

        $client = \App\Models\Client::where('email', $clientInfo['email'])
            ->where('mobilcontact', $clientInfo['phone'])
            ->first();
        return $client;
    }

    public function findOrCreateClientPj($clientInfo, $spaid)
    {
        $client = \App\Models\Client::where('cnpcui',  $clientInfo['_billing_cui'])
            ->first();
        if (!$client) {
            $client = new \App\Models\Client();
        }
        $client->cnpcui     = $clientInfo['_billing_cui'];
        $client->den        = $clientInfo['_billing_company_name'];
        $client->prenume    = '.';
        $client->obscui     = $clientInfo['_billing_cui'];
        $client->adresa1    = $clientInfo['_billing_company_address'];
        $client->datan      = date('Y-m-d H:i:s.v');
        $client->modp       = 'Website';
        $client->valuta     = 'RON';
        $client->datacreare = date('Y-m-d H:i:s.v');
        $client->tip       = 'Website';
        $client->clhead  = $spaid;
        $client->nrc        = $clientInfo['_billing_reg_com'];
        $client->banca      = $clientInfo['_billing_banca'];
        $client->iban       = $clientInfo['_billing_cont_iban'];
        $client->oras       = $clientInfo['_billing_company_city'];
        $client->judet      = \App\Helper\Judet::getNameByCode($clientInfo['_billing_company_state']);
        $client->tara       = \App\Helper\Country::getNameByCode($clientInfo['_billing_company_country']);
        $client->compid     = 'WEBSITE';
        $client->atojki     = 'Firma';
        $client->pj        = 1;
        $client->save();
        $client = \App\Models\Client::where('cnpcui',  $clientInfo['_billing_cui'])
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

    public function getVatFromPrice($priceWithVAT)
    {
        return  $priceWithVAT / (1 + ($this->vatRate / 100));
    }

    private function numberToRomanianText($number)
    {
        $number = (int) $number;
        if ($number == 0) {
            return 'zero';
        }
        $units = ['', 'unu', 'doi', 'trei', 'patru', 'cinci', 'șase', 'șapte', 'opt', 'nouă'];
        $teens = [
            'zece',
            'unsprezece',
            'doisprezece',
            'treisprezece',
            'paisprezece',
            'cincisprezece',
            'șaisprezece',
            'șaptesprezece',
            'optsprezece',
            'nouăsprezece'
        ];
        $tens = ['', '', 'douăzeci', 'treizeci', 'patruzeci', 'cincizeci', 'șaizeci', 'șaptezeci', 'optzeci', 'nouăzeci'];
        $hundreds = [
            '',
            'una sută',
            'două sute',
            'trei sute',
            'patru sute',
            'cinci sute',
            'șase sute',
            'șapte sute',
            'opt sute',
            'nouă sute'
        ];
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
    private function createTrznp($client, $pret,  $idrezervarehotel)
    {
        $trznp = new \App\Models\Trznp();
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
        $trznp = \App\Models\Trznp::where('spaid',  $client->spaid)
            ->orderByDesc('nrnpint')
            ->first();
        return $trznp;
    }

    private function createTrzdetnp($client, $pret, $idrezervarehotel, $trznp, $tipCamera, $quantity, $roomType)
    {
        $trzdetnp = new \App\Models\Trzdetnp();
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

        $trzdetnp = \App\Models\Trzdetnp::where('spaid',  $client->spaid)
            ->orderByDesc('idtrzf')
            ->first();

        return $trzdetnp;
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
            Mail::send('emails.invoice', [], function (\Illuminate\Mail\Message $message) use ($to, $subject, $invoice, $bccRecipients) {
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

            Mail::send('emails.invoice_bcc', $bccData, function (\Illuminate\Mail\Message $message) use ($bccRecipients, $bccSubject, $invoice) {
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
    protected function createTrzdet($trzdetnp)
    {
        $trzdet = new \App\Models\Trzdet();
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
        $trzdet->data = date('Y-m-d H:i:s.v');
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
        $trzdet->datac = date('Y-m-d H:i:s.v');
        $trzdet->cotatva = $this->vatRate / 100;
        $trzdet->reinnoire = false;
        $trzdet->cardb =  null;
        $trzdet->idcldet = $trzdetnp->spaid;
        $trzdet->cardb = 0;
        $trzdet->save();
        return $trzdet;
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
            $data['client']['prenume'] = '.';
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
                'tva' => $this->vatRate,
            ];
        }
        $data['vatRate'] = $this->vatRate;
        $data['total'] = $orderBookingInfo['total'];
        $data['totalTax'] = round($orderBookingInfo['total'] - $this->getVatFromPrice($data['total']), 2);
        $data['totalWithoutTax'] =  round($this->getVatFromPrice($data['total']), 2);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice', $data);
        
        $invoiceDir = storage_path('invoices' . '/' . date('y') . '/' . date('m'));
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0777, true);
        }
        $fileName = $invoiceDir . '/invoice' . $data['nrfactura'] . '.pdf';
        $pdf->save($fileName);

        $this->sendEmail($fileName, $orderBookingInfo);
    }
    public function createTrzdetfact($client, $pret,  $quantity, $nrFact, $roomType, $item)
    {
        $price = \App\Models\Pret::where('tipcamera', $roomType)->first();
        $trzdetfact = new \App\Models\Trzdetfact();
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
        $trzdetfact->tva = $pret - $this->getVatFromPrice($pret);
        $trzdetfact->data = date('Y-m-d H:i:s.v');
        $trzdetfact->compid = 'Website';
        $trzdetfact->idpers = '0';
        $trzdetfact->cotatva = $this->vatRate / 100;
        $trzdetfact->save();
        return $trzdetfact;
    }
    public function createTrzfact($client, $pret, $trznp, $invoiceNo)
    {
        $trzfact = new \App\Models\Trzfact();
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
        $trzfact->datafact = date('Y-m-d H:i:s.v');
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
        $trzfact = \App\Models\Trzfact::where('idcl',  $client->spaid)
            ->orderByDesc('nrfact')
            ->first();

        $trznp->nrfact = $trzfact->nrfact;
        $trznp->save();
        return $trzfact;
    }
}
