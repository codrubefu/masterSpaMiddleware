<?php

namespace App\Services;

use App\Models\Trzcard;
use App\Services\Orders\ClientResolver;
use App\Services\Orders\NpInvoiceService;
use App\Services\Orders\OrderMailer;
use App\Services\Orders\PackageBillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderSpaService
{
    public function __construct(
        private ClientResolver $clients,
        private NpInvoiceService $npInvoice,
        private OrderMailer $mailer,
        private PackageBillService $packages,
    ) {
    }

    public function saveOrder(array $orderInfo)
    {
        $orderInfo = $this->parseOrderInfo($orderInfo);
        $clientInfo = $orderInfo['billing'];
        foreach ($orderInfo['meta_data'] ?? [] as $value) {
            if (strpos($value['key'], '_billing_') !== false) {
                $clientInfo[$value['key']] = $value['value'];
            }
        }

        // All DB writes are atomic: a failure on any item rolls back the whole order.
        $orderInfo = DB::transaction(function () use ($orderInfo, $clientInfo) {
            $client = $this->clients->findOrCreate($clientInfo);
            $clientPj = $this->clients->resolveCompany($clientInfo, $client);

            // Create a client for each beneficiary that has an email
            foreach ($orderInfo['items'] as $key => $item) {
                foreach ($item['clients'] ?? [] as $clientData) {
                    if (!empty($clientData['email'])) {
                        $beneficiar = $this->clients->findOrCreate($clientData);
                        $orderInfo['items'][$key]['beneficiar_id'] = $beneficiar->spaid;
                    }
                }
            }

            // Web packages (e.g. WEB/PACK) go to an information note
            // (trzbill/trzdetbill/detaliitrz); reception makes the payment note.
            $packages = [];
            $others = [];
            foreach ($orderInfo['items'] as $item) {
                $package = $this->packages->findPackage($item);
                if ($package) {
                    $packages[] = ['package' => $package, 'item' => $item];
                } else {
                    $others[] = $item;
                }
            }

            if ($packages) {
                $bill = $this->packages->createBill($client, $packages, $orderInfo['id'] ?? null);
                $orderInfo['nrbill'] = $bill->nrbill;
            }

            $trznp = null;
            $trzfact = null;
            if ($others && $this->npInvoice->enabled()) {
                $othersTotal = $packages
                    ? array_sum(array_map(fn ($i) => (float) ($i['subtotal'] ?? 0), $others))
                    : $orderInfo['total'];
                Log::info('Creating trznp/trzfact for client', ['client_id' => $client->spaid]);
                $trznp = $this->npInvoice->createNp($client, $othersTotal, 0);
                $trzfact = $this->npInvoice->createInvoice($clientPj ?? $client, $othersTotal, $trznp);
            }

            foreach ($others as $item) {
                // TODO: trzcard is filled with WooCommerce item keys (not fillable) -> empty row; needs a field mapping.
                $this->createTrzcard($item);

                if ($trznp && $trzfact) {
                    $this->npInvoice->createNpLine($client, $trznp, $item['subtotal'], $item['quantity'], null, null);
                    $this->npInvoice->createInvoiceLine($clientPj ?? $client, $trzfact, $item['subtotal'], $item['quantity'], $item, null, true);
                }
            }

            return $orderInfo;
        });

        // Emails only after the order is committed. The order is already saved,
        // so a PDF/mail failure must not return an error (WooCommerce would retry
        // and duplicate the order).
        try {
            $this->mailer->sendVouchers($orderInfo);
        } catch (\Throwable $e) {
            Log::error('Spa order saved, but vouchers failed', ['order_id' => $orderInfo['id'] ?? null, 'message' => $e->getMessage(), 'exception' => $e]);
        }

        return true;
    }

    /**
     * Extracts the beneficiaries of each item from its meta_data
     * ("Abonat - Nume [1]", "Abonat - Email [1]", ...) into
     * $item['clients'] = [['last_name', 'first_name', 'email', 'send', 'phone', 'voucher'], ...]
     */
    private function parseOrderInfo(array $orderInfo): array
    {
        $emptyClient = ['last_name' => '', 'first_name' => '', 'email' => '', 'send' => false, 'phone' => ''];
        $fields = [
            'nume' => 'last_name',
            'prenume' => 'first_name',
            'email' => 'email',
            'trimite_email' => 'send',
            'telefon' => 'phone',
        ];

        foreach ($orderInfo['items'] as $key => $item) {
            if (empty($item['meta_data'])) {
                $orderInfo['items'][$key]['clients'] = [$emptyClient];
                continue;
            }

            foreach ($item['meta_data'] as $meta) {
                if (!preg_match('/Abonat - (Nume|Prenume|Email|Trimite email|Telefon) \\[(\d+)\\]/', $meta['key'], $matches)) {
                    continue;
                }
                $field = $fields[strtolower(str_replace(' ', '_', $matches[1]))];
                $index = (int) $matches[2] - 1;
                $orderInfo['items'][$key]['clients'][$index] ??= $emptyClient;
                $orderInfo['items'][$key]['clients'][$index][$field] = $field === 'send'
                    ? strtolower($meta['value']) === 'da'
                    : $meta['value'];
            }
        }

        $x = 0;
        foreach ($orderInfo['items'] as $key => $item) {
            foreach ($item['clients'] ?? [] as $index => $clientData) {
                $orderInfo['items'][$key]['clients'][$index]['voucher'] = $orderInfo['id'] . rand(1000, 9999) . $x;
                $x++;
            }
        }

        return $orderInfo;
    }

    protected function createTrzcard(array $data): Trzcard
    {
        $trzcard = new Trzcard();
        $trzcard->fill($data);
        $trzcard->save();

        return $trzcard;
    }
}
