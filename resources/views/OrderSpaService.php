<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Trzcard;
use App\Services\OrderServiceCommonTrait;

class OrderSpaService
{
    use OrderServiceCommonTrait;

    public function saveOrder(array $orderInfo)
    {
        $orderInfo = $this->parseOrderInfo($orderInfo);
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

        //create client for each $client form $intem['clients'] if send is true

        foreach ($orderInfo['items'] as  $key => $item) {
            if (isset($item['clients'])) {
                foreach ($item['clients'] as $clientData) {
                    if (isset($clientData['email']) && $clientData['email'] != '') {
                        $beneficiar = $this->findOrCreateClient($clientData);
                        $orderInfo['items'][$key]['beneficiar_id'] = $beneficiar->spaid;
                    }
                }
            }
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
                if ($clientPj) {
                    $useClient = $clientPj;
                } else {
                    $useClient = $client;
                }
                $trzfact = $this->createTrzfact($useClient, $orderInfo['total'], $trznp, $invoiceNo);
            }

            $np = $trznp->nrnpint . '.00';

            $bookedRooms = $this->processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp, $trzfact, null);
        }

        $this->sendVoucher($orderInfo);
        return true;
    }


    /**
     * Extracts clients array from meta_data array in the format:
     * [ ['name' => ..., 'email' => ..., 'send' => true/false], ... ]
     */
    private function parseOrderInfo($orderInfo)
    {
        $clients = [];
        $x = 0;
        foreach ($orderInfo['items'] as $key => $item) {
            foreach ($item['meta_data'] as $meta) {
                if (preg_match('/Abonat - (Nume|Prenume|Email|Trimite email|Telefon) \\[(\d+)\\]/', $meta['key'], $matches)) {
                    $field = strtolower(str_replace(' ', '_', $matches[1]));
                    $index = (int)$matches[2] - 1;
                    if (!isset($clients[$index])) {
                        $clients[$index] = ['last_name' => '', 'first_name' => '', 'email' => '', 'send' => false, 'phone' => ''];
                    }
                    if ($field === 'nume') {
                        $orderInfo['items'][$key]['clients'][$index]['last_name'] = $meta['value'];
                    } elseif ($field === 'prenume') {
                        $orderInfo['items'][$key]['clients'][$index]['first_name'] = $meta['value'];
                    } elseif ($field === 'email') {
                        $orderInfo['items'][$key]['clients'][$index]['email'] = $meta['value'];
                    } elseif ($field === 'trimite_email') {
                        $orderInfo['items'][$key]['clients'][$index]['send'] = (strtolower($meta['value']) === 'da');
                    } elseif ($field === 'telefon') {
                        $orderInfo['items'][$key]['clients'][$index]['phone'] = $meta['value'];
                    }
                }
            }
        }

        foreach ($orderInfo['items'] as $key => $item) {
            if(isset($item['clients']) && count($item['clients']) > 0) {
                foreach ($item['clients'] as $index => $clientData) {
                    $orderInfo['items'][$key]['clients'][$index]['voucher'] =  $orderInfo['id'] . rand(1000, 9999) . $x;
                    $x++;
                }
            }
        }

        // Re-index to remove gaps if any
        return $orderInfo;
    }


    private function processOrderItem($item,  $client, $clientPj, $bookedRooms,  $rezervare, $trznp,  $trzfact, $roomType)
    {

        // Add parameters: $rezervare, $trznp, $tipCamera, $selectedRoom
        $trzdetnp = $this->createTrzdetnp($client, $item['subtotal'], null, $trznp, null, $item['quantity'], null);

        $this->createTrzdet($trzdetnp);
        $this->createTrzcard($item, $client);
        if ($clientPj) {
            $client = $clientPj;
        }
        $this->createTrzdetfact($client, $item['subtotal'], $item['quantity'], $trzfact->nrfact, $roomType, $item);
    }

    /**
     * Generate a voucher PDF and send it via email to the client.
     */
    protected function sendVoucher($orderInfo)
    {

        foreach ($orderInfo['items'] as $key => $item) {
            if (isset($item['clients'])) {
                foreach ($item['clients'] as $clientData) {
                    $data =  [
                        'client' => $clientData['email'] ?? $orderInfo['billing']['email'],
                        'voucher_no' => $clientData['voucher'],
                        'date' => date('d-m-Y'),
                        'send' => $clientData['send'] ?? false,
                    ];

                    // Generate PDF from Blade template (landscape, with background)
                    $pdf = Pdf::loadView('emails.voucher_pdf', $data);
                    $pdf->setPaper('a4', 'landscape');
                    $voucherDir = storage_path('vouchers/' . date('y') . '/' . date('m'));

                    if (!file_exists($voucherDir)) {
                        mkdir($voucherDir, 0777, true);
                    }

                    $fileName = $voucherDir . '/voucher_' . $data['voucher_no'] . '.pdf';
                    $pdf->save($fileName);
                    $data['file'] = $fileName;
                    $voucherData[] = $data;
                }
            }
        }

        $emailSentTo = [];
        foreach ($voucherData as $key => $data) {
            $name = env('CLIENT_NAME', 'Mirage MedSPA Hotel');
            $to = $data['client'] ?? null;
            $subject = 'Voucherul dumneavoastră de la ' . $name;

            if ($data['send']) {
                // Other keys: send only their own voucher
                $fileName = $data['file'];
                if ($to && file_exists($fileName)) {
                    Mail::send('emails.voucher_email', $data, function ($message) use ($to, $subject, $fileName) {
                        $message->to(["codrut_befu@yahoo.com", $to])
                            ->subject($subject)
                            ->attach($fileName);
                    });
                    $emailSentTo[] = $to;
                } else {
                    Log::error('Voucher email not sent: missing recipient or voucher file for client.');
                }
            }
        }

        // Key 0: send all vouchers as attachments to the main client
        $allFiles = array_column($voucherData, 'file');
        $allFiles = array_filter($allFiles, 'file_exists');
        if ($to && count($allFiles) > 0) {
            $data['sentTo'] = $emailSentTo;
            Mail::send('emails.voucher_email_for_main', $data, function ($message) use ($to, $subject, $allFiles) {
                $message->to(["codrut_befu@yahoo.com", $to])
                    ->subject($subject);
                foreach ($allFiles as $file) {
                    $message->attach($file);
                }
            });
        } else {
            Log::error('Voucher email not sent: missing recipient or voucher files for main client.');
        }
    }

    protected function createTrzcard($data)
    {
        $trzcard = new Trzcard();
        $trzcard->fill($data);
        $trzcard->save();
        return $trzcard;
    }
}
