<?php

namespace App\Services;

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
        $nextNrf = \DB::transaction(function () {
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
        $client = \App\Models\Client::where('email',  $clientInfo['email'])
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
        $client->adresa1    = $clientInfo['address_1'];
        $client->adresa2    = $clientInfo['address_2'];
        $client->pj         =  $isPj;
        $client->modp       = 'Website';
        $client->obscui     = 'independent';
        $client->startper   = date('Y-m-d H:i:s.v');
        $client->endper     = date('Y-m-d H:i:s.v');
        $client->datan      = date('Y-m-d H:i:s.v');
        $client->camera     = 0;
        $client->datacreare = date('Y-m-d H:i:s.v');
        $client->compid     = 'Website';
        $client->tip        = 'Website';
        $client->oras       = $clientInfo['city'];
        $client->judet      = \App\Helper\Judet::getNameByCode($clientInfo['state']);
        $client->tara       = \App\Helper\Country::getNameByCode($clientInfo['country']);
        $client->valuta     = 'RON';
        $client->hotel      = 'Extra';
        $client->cnpcui     = '0000000000000';
        $client->save();
        $client = \App\Models\Client::where('email',  $clientInfo['email'])
            ->where('mobilcontact', $clientInfo['phone'])
            ->first();
        return $client;
    }

    public function findOrCreateClientPj($clientInfo,$spaid)
    {
        $client = \App\Models\Client::where('cnpcui',  $clientInfo['_billing_cui'])
            ->first();
        if (!$client) {
            $client = new \App\Models\Client();
        }
        $client->cnpcui     = $clientInfo['_billing_cui'];
        $client->den        = $clientInfo['_billing_company_name'];
        $client->prenume    = '.' ;
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
        \Log::info('Updating hotel for client', ['client_id' => $client->spaid, 'hotel' => $hotel]);
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
}
