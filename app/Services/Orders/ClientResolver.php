<?php

namespace App\Services\Orders;

use App\Helper\Country;
use App\Helper\Judet;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

/**
 * Finds or creates the clients (PF / PJ) of a website order.
 */
class ClientResolver
{
    /**
     * Person client, matched by email + phone.
     */
    public function findOrCreate(array $clientInfo): Client
    {
        try {
            foreach (['last_name', 'first_name', 'phone', 'email'] as $field) {
                if (empty($clientInfo[$field])) {
                    throw new \InvalidArgumentException("Missing mandatory clientInfo field: $field");
                }
            }

            $client = Client::where('email', $clientInfo['email'])
                ->where('mobilcontact', $clientInfo['phone'])
                ->first();

            if (!$client) {
                $client = new Client();
                $client->email = $clientInfo['email'];
                $client->mobilcontact = $clientInfo['phone'];
            }

            $now = now()->format('Y-m-d H:i:s.v');
            $client->den        = $clientInfo['last_name'];
            $client->prenume    = $clientInfo['first_name'];
            $client->adresa1    = $clientInfo['address_1'] ?? null;
            $client->adresa2    = $clientInfo['address_2'] ?? null;
            $client->pj         = false;
            $client->modp       = 'Website';
            $client->obscui     = 'independent';
            $client->startper   = $now;
            $client->endper     = $now;
            $client->datan      = $now;
            $client->camera     = 0;
            $client->datacreare = $now;
            $client->compid     = 'Website';
            $client->tip        = 'Website';
            $client->oras       = $clientInfo['city'] ?? null;
            $client->judet      = isset($clientInfo['state']) ? Judet::getNameByCode($clientInfo['state']) : null;
            $client->tara       = isset($clientInfo['country']) ? Country::getNameByCode($clientInfo['country']) : null;
            $client->valuta     = 'RON';
            $client->hotel      = 'Extra';
            $client->cnpcui     = '0000000000000';
            $client->save();

            return $client->refresh();
        } catch (\Throwable $exception) {
            Log::error('findOrCreateClient failed', [
                'email' => $clientInfo['email'] ?? null,
                'phone' => $clientInfo['phone'] ?? null,
                'clientInfo' => $clientInfo,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * Company client (PJ), matched by CUI, linked to the person client $spaid.
     */
    public function findOrCreateCompany(array $clientInfo, $spaid): Client
    {
        $client = Client::where('cnpcui', $clientInfo['_billing_cui'])->first() ?? new Client();

        $now = now()->format('Y-m-d H:i:s.v');
        $client->cnpcui     = $clientInfo['_billing_cui'];
        $client->den        = $clientInfo['_billing_company_name'];
        $client->prenume    = '.';
        $client->obscui     = $clientInfo['_billing_cui'];
        $client->adresa1    = $clientInfo['_billing_company_address'];
        $client->datan      = $now;
        $client->modp       = 'Website';
        $client->valuta     = 'RON';
        $client->datacreare = $now;
        $client->tip        = 'Website';
        $client->clhead     = $spaid;
        $client->nrc        = $clientInfo['_billing_reg_com'];
        $client->banca      = $clientInfo['_billing_banca'];
        $client->iban       = $clientInfo['_billing_cont_iban'];
        $client->oras       = $clientInfo['_billing_company_city'];
        $client->judet      = Judet::getNameByCode($clientInfo['_billing_company_state']);
        $client->tara       = Country::getNameByCode($clientInfo['_billing_company_country']);
        $client->compid     = 'WEBSITE';
        $client->atojki     = 'Firma';
        $client->pj         = 1;
        $client->save();

        return $client->refresh();
    }

    /**
     * Returns the company client if the order is billed to a company, else null.
     */
    public function resolveCompany(array $clientInfo, Client $person): ?Client
    {
        if (($clientInfo['_billing_company_details'] ?? 0) != 1) {
            return null;
        }

        return $this->findOrCreateCompany($clientInfo, $person->spaid);
    }

    public function assignHotel(Client $client, $hotelId): Client
    {
        Log::info('Updating hotel for client', ['client_id' => $client->spaid, 'hotel' => $hotelId]);
        $client->hotel = config('orders.hotels')[$hotelId] ?? config('orders.default_hotel');
        $client->clhead = $client->spaid; // Self-referential
        $client->save();

        return $client;
    }
}
