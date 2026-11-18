<?php

namespace Tests\Unit\Orders;

use App\Mail\OrderMail;
use App\Services\Orders\OrderMailer;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * No database access. Voucher PDFs are written to storage/vouchers.
 */
class OrderMailerTest extends TestCase
{
    private function order(): array
    {
        return [
            'id' => 1,
            'total' => 500,
            'billing' => ['first_name' => 'Ana', 'email' => 'buyer@example.com'],
            'custom_info' => ['start_date' => '2026-12-01', 'end_date' => '2026-12-03'],
            'items' => [['name' => 'Dubla', 'quantity' => 1, 'subtotal' => 500]],
        ];
    }

    public function test_booking_confirmation_without_invoice_has_no_attachment(): void
    {
        Mail::fake();
        config(['orders.mail.hotel_staff' => ['staff@example.com'], 'orders.mail.queue' => false]);

        app(OrderMailer::class)->sendHotelBooking($this->order());

        Mail::assertSent(OrderMail::class, 2);
        Mail::assertSent(OrderMail::class, function (OrderMail $mail) {
            return $mail->hasTo('buyer@example.com')
                && $mail->hasBcc('staff@example.com')
                && $mail->attachments() === [];
        });
        Mail::assertSent(OrderMail::class, fn (OrderMail $mail) => $mail->hasTo('staff@example.com'));
    }

    public function test_queue_setting_queues_mails(): void
    {
        Mail::fake();
        config(['orders.mail.hotel_staff' => [], 'orders.mail.queue' => true]);

        app(OrderMailer::class)->sendHotelBooking($this->order());

        Mail::assertNothingSent();
        Mail::assertQueued(OrderMail::class, 1);
    }

    public function test_main_voucher_email_goes_to_buyer_not_last_beneficiary(): void
    {
        Mail::fake();
        config(['orders.mail.voucher_staff' => ['staff@example.com'], 'orders.mail.queue' => false]);

        $order = $this->order();
        $order['items'][0]['clients'] = [
            ['email' => 'ben1@example.com', 'send' => true, 'voucher' => 'T' . uniqid() . 'a'],
            ['email' => 'ben2@example.com', 'send' => false, 'voucher' => 'T' . uniqid() . 'b'],
        ];

        app(OrderMailer::class)->sendVouchers($order);

        // ben1 (send=Da) + main email to buyer; ben2 gets nothing on its own.
        Mail::assertSent(OrderMail::class, 2);
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->hasTo('ben1@example.com') && count($m->attachments()) === 1);
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->hasTo('buyer@example.com') && count($m->attachments()) === 2);
        Mail::assertNotSent(OrderMail::class, fn (OrderMail $m) => $m->hasTo('ben2@example.com'));

        foreach ($order['items'][0]['clients'] as $c) {
            @unlink(storage_path('vouchers/' . date('y') . '/' . date('m') . '/voucher_' . $c['voucher'] . '.pdf'));
        }
    }
}
