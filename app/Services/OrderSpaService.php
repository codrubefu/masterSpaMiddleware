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
            if ($trznp === null) {
            
                $trznp = $this->createTrznp($client, $orderInfo['total'], 0);
                if($clientPj) {
                    $useClient = $clientPj;
                }else{
                    $useClient = $client;
                }
                $trzfact = $this->createTrzfact($useClient, $orderInfo['total'], $trznp, $invoiceNo);

            }
            
            $np = $trznp->nrnpint.'.00';

            $bookedRooms = $this->processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp, $trzfact, null);
        }

        $this->generateInvoice($orderInfo, $invoiceNo, $clientInfo, $this->getCompany(), $trznp ? $trznp->nrnpint : null);
       
        return true;
    }


    private function processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp,  $trzfact, $roomType)
    {

        // Add parameters: $rezervare, $trznp, $tipCamera, $selectedRoom
        $trzdetnp = $this->createTrzdetnp($client, $item['subtotal'], null, $trznp, null, $item['quantity'], null);

        $this->createTrzdet($trzdetnp);
        if($clientPj) {
            $client = $clientPj;
        }
        $this->createTrzdetfact($client, $item['subtotal'], $item['quantity'], $trzfact->nrfact, $roomType, $item);
     
    }










    // ...existing code...
}