<?php

namespace App\Services;

use App\Models\Client;

class ClientService
{
    public function createOrUpdateClient($den, $prenume)
    {
        $newClient = new Client();

        $newClient->den = $den;
        $newClient->prenume = $prenume;
        $newClient->save();

        return $newClient;
    }
}
