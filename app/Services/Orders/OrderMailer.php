<?php

namespace App\Services\Orders;

use App\Helper\Vat;
use App\Mail\OrderMail;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * PDFs (invoice, vouchers) and emails for website orders.
 *
 * Called only after the order is committed. Mail failures are logged and
 * never thrown: the order is already saved, and an error response would
 * make WooCommerce resend it.
 */
class OrderMailer
{
    /**
     * Hotel: invoice PDF + email to client and staff.
     */
    public function sendHotelInvoice(array $order, array $clientInfo, string $invoiceNo, $nrnp): void
    {
        $file = $this->generateInvoicePdf($order, $clientInfo, $invoiceNo, $nrnp);
        $this->sendHotelBooking($order, $file);
    }

    /**
     * Hotel: email to client (+ summary to staff). Without $invoiceFile a
     * plain booking confirmation is sent (invoicing disabled).
     */
    public function sendHotelBooking(array $order, ?string $invoiceFile = null): void
    {
        $to = $order['billing']['email'] ?? null;
        if (!$to || ($invoiceFile !== null && !file_exists($invoiceFile))) {
            Log::error('Booking email not sent: missing recipient or invoice file.');
            return;
        }

        $staff = config('orders.mail.hotel_staff', []);
        $files = $invoiceFile !== null ? [$invoiceFile] : [];

        $this->deliver(
            [$to],
            new OrderMail(
                config('orders.mail.hotel_subject'),
                $invoiceFile !== null ? 'emails.invoice' : 'emails.booking_confirmation',
                ['order' => $order],
                $files
            ),
            $staff
        );

        if ($staff) {
            $this->deliver(
                $staff,
                new OrderMail(
                    'Comanda noua - ' . ($order['billing']['first_name'] ?? 'Client'),
                    'emails.invoice_bcc',
                    ['order' => $order, 'invoiceFile' => $invoiceFile !== null ? basename($invoiceFile) : null],
                    $files
                )
            );
        }
    }

    /**
     * Spa: one voucher PDF per beneficiary; each beneficiary with
     * "Trimite email = Da" gets their own, the buyer gets all of them.
     */
    public function sendVouchers(array $order): void
    {
        $subject = 'Voucherul dumneavoastră de la ' . config('orders.mail.client_name');
        $staff = config('orders.mail.voucher_staff', []);
        $buyer = $order['billing']['email'] ?? null;

        $vouchers = [];
        foreach ($order['items'] as $item) {
            foreach ($item['clients'] ?? [] as $clientData) {
                $data = [
                    'client' => $clientData['email'] ?? $buyer,
                    'voucher_no' => $clientData['voucher'],
                    'date' => date('d-m-Y'),
                    'send' => $clientData['send'] ?? false,
                ];
                $data['file'] = $this->generateVoucherPdf($data);
                $vouchers[] = $data;
            }
        }

        $sentTo = [];
        foreach ($vouchers as $data) {
            if (!$data['send']) {
                continue;
            }
            if (!$data['client']) {
                Log::error('Voucher email not sent: missing recipient for voucher.', ['voucher' => $data['voucher_no']]);
                continue;
            }
            $this->deliver(
                array_merge($staff, [$data['client']]),
                new OrderMail($subject, 'emails.voucher_email', $data, [$data['file']])
            );
            $sentTo[] = $data['client'];
        }

        if (!$buyer || !$vouchers) {
            Log::error('Voucher email not sent: missing recipient or vouchers for main client.');
            return;
        }

        $mainData = end($vouchers);
        $mainData['sentTo'] = $sentTo;
        $this->deliver(
            array_merge($staff, [$buyer]),
            new OrderMail($subject, 'emails.voucher_email_main', $mainData, array_column($vouchers, 'file'))
        );
    }

    private function generateInvoicePdf(array $order, array $clientInfo, string $invoiceNo, $nrnp): string
    {
        $data = [
            'title' => 'Master Hotel',
            'spaces' => 14 - count($order['items']),
            'nrfactura' => $invoiceNo,
            'nrnp' => $nrnp,
            'data' => date('d-m-Y'),
            'data_scadenta' => date('d-m-Y'),
            'data_start' => new \DateTime($order['custom_info']['start_date']),
            'data_end' => new \DateTime($order['custom_info']['end_date']),
            'client' => $clientInfo,
            'company' => Company::where('idfirma', (int) config('orders.idfirma', 1))->first(),
        ];
        $data['client']['cnpcui'] = '0000000000000';

        if (($clientInfo['_billing_company_details'] ?? 0) == 1) {
            $data['client']['cnpcui'] = $clientInfo['_billing_cui'];
            $data['client']['den'] = $clientInfo['_billing_company_name'];
            $data['client']['prenume'] = '.';
            $data['client']['obscui'] = $clientInfo['_billing_cui'];
            $data['client']['nrc'] = $clientInfo['_billing_reg_com'];
            $data['client']['banca'] = $clientInfo['_billing_banca'];
            $data['client']['iban'] = $clientInfo['_billing_cont_iban'];
        }

        foreach ($order['items'] as $item) {
            $unit = $item['subtotal'] / $item['quantity'];
            $data['items'][] = [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $unit,
                'pret_unit_no_vat' => Vat::net($unit),
                'total' => $item['subtotal'],
                'tvaValue' => Vat::vat($item['subtotal']),
                'total_no_vat' => Vat::net($item['subtotal']),
                'tva' => Vat::rate(),
            ];
        }

        $data['vatRate'] = Vat::rate();
        $data['total'] = $order['total'];
        $data['totalTax'] = Vat::vat($order['total']);
        $data['totalWithoutTax'] = Vat::net($order['total']);

        $file = $this->storageDir('invoices') . '/invoice' . $invoiceNo . '.pdf';
        Pdf::loadView('invoice', $data)->save($file);

        return $file;
    }

    private function generateVoucherPdf(array $data): string
    {
        $file = $this->storageDir('vouchers') . '/voucher_' . $data['voucher_no'] . '.pdf';
        Pdf::loadView('emails.voucher_pdf', $data)->setPaper('a4', 'landscape')->save($file);

        return $file;
    }

    /** storage/<type>/<yy>/<mm>, created if missing. */
    private function storageDir(string $type): string
    {
        $dir = storage_path($type . '/' . date('y') . '/' . date('m'));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Sends (or queues, see orders.mail.queue) and logs failures.
     */
    private function deliver(array $to, OrderMail $mail, array $bcc = []): void
    {
        try {
            $pending = Mail::to($to);
            if ($bcc) {
                $pending->bcc($bcc);
            }
            config('orders.mail.queue') ? $pending->queue($mail) : $pending->send($mail);
        } catch (\Throwable $e) {
            Log::error('Order email failed: ' . $e->getMessage(), ['to' => $to]);
        }
    }
}
