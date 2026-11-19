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

            $this->applyDefaults($client);

            $now = now()->format('Y-m-d H:i:s.v');
            $client->den        = $clientInfo['last_name'];
            $client->prenume    = $clientInfo['first_name'];
            $client->adresa1    = $this->blankIfEmpty($clientInfo['address_1'] ?? null);
            $client->adresa2    = $this->blankIfEmpty($clientInfo['address_2'] ?? null);
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
            $client->oras       = $this->blankIfEmpty($clientInfo['city'] ?? null);
            $client->judet      = $this->blankIfEmpty(!empty($clientInfo['state']) ? Judet::getNameByCode($clientInfo['state']) : null);
            $client->tara       = $this->blankIfEmpty(!empty($clientInfo['country']) ? Country::getNameByCode($clientInfo['country']) : null);
            $client->valuta     = 'RON';
            $client->hotel      = 'Extra';
            $client->cnpcui     = '0000000000000';
            $client->save();
            $this->syncClhead($client);

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
     * Default values Acord expects on every web client (PF and PJ).
     */
    private function applyDefaults(Client $client): void
    {
        foreach ([
            'reg', 'cp', 'tel', 'fax', 'banca', 'iban', 'profesie', 'nrvoucher',
            'institutia', 'cardid', 'obs', 'pasaport', 'numevip', 'mobilvip',
            'emailvip', 'sursamkt', 'canalmkt', 'idpartener',
        ] as $field) {
            $client->{$field} = ' ';
        }

        $client->conthotel = 0;
        $client->origin    = 'Romania';
    }

    /**
     * Acord expects clhead = spaid (own id). spaid is only known after the
     * first insert, so a second update is needed for new clients.
     */
    private function syncClhead(Client $client): void
    {
        if ((int) $client->clhead !== (int) $client->spaid) {
            $client->clhead = $client->spaid;
            $client->save();
        }
    }

    /**
     * Acord expects a single space instead of NULL / empty string in text columns.
     */
    private function blankIfEmpty($value): string
    {
        return ($value === null || trim((string) $value) === '') ? ' ' : (string) $value;
    }

    /**
     * Company client (PJ), matched by CUI.
     */
    public function findOrCreateCompany(array $clientInfo): Client
    {
        $client = Client::where('cnpcui', $clientInfo['_billing_cui'])->first() ?? new Client();

        $this->applyDefaults($client);

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
        $client->nrc        = $clientInfo['_billing_reg_com'];
        $client->banca      = $this->blankIfEmpty($clientInfo['_billing_banca'] ?? null);
        $client->iban       = $this->blankIfEmpty($clientInfo['_billing_cont_iban'] ?? null);
        $client->oras       = $clientInfo['_billing_company_city'];
        $client->judet      = Judet::getNameByCode($clientInfo['_billing_company_state']);
        $client->tara       = Country::getNameByCode($clientInfo['_billing_company_country']);
        $client->compid     = 'WEBSITE';
        $client->atojki     = 'Firma';
        $client->pj         = 1;
        $client->save();
        $this->syncClhead($client);

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

        return $this->findOrCreateCompany($clientInfo);
    }

    public function assignHotel(Client $client, $hotelId): Client
    {
        Log::info('Updating hotel for client', ['client_id' => $client->spaid, 'hotel' => $hotelId]);
        $client->hotel = config('orders.hotels')[$hotelId] ?? config('orders.default_hotel');
        $client->save();
        $this->syncClhead($client);

        return $client;
    }
}
