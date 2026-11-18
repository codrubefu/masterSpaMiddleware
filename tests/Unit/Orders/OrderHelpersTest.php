<?php

namespace Tests\Unit\Orders;

use App\Helper\MoneyToWords;
use App\Helper\Vat;
use App\Services\OrderSpaService;
use Tests\TestCase;

/**
 * No database access: pure helpers and payload parsing only.
 */
class OrderHelpersTest extends TestCase
{
    public function test_vat_split_adds_up_to_total(): void
    {
        config(['orders.vat_rate' => 11]);

        foreach ([0, 1, 99.99, 111, 500, 1234.56, 3333.33] as $gross) {
            $this->assertEqualsWithDelta($gross, Vat::net($gross) + Vat::vat($gross), 0.0001, "gross {$gross}");
        }
        $this->assertSame(450.45, Vat::net(500));
        $this->assertSame(49.55, Vat::vat(500));
        $this->assertSame(0.11, Vat::fraction());
    }

    public function test_money_to_words(): void
    {
        $this->assertSame('zero', MoneyToWords::toWords(0));
        $this->assertSame('douăzeci și unu', MoneyToWords::toWords(21));
        $this->assertSame('o mie', MoneyToWords::toWords(1000));
        $this->assertSame('un milion', MoneyToWords::toWords(1000000));
    }

    public function test_parse_order_info_extracts_beneficiaries(): void
    {
        $order = [
            'id' => 555,
            'items' => [
                ['name' => 'Abonament', 'meta_data' => [
                    ['key' => 'Abonat - Nume [1]', 'value' => 'Popescu'],
                    ['key' => 'Abonat - Prenume [1]', 'value' => 'Ion'],
                    ['key' => 'Abonat - Email [1]', 'value' => 'ion@example.com'],
                    ['key' => 'Abonat - Trimite email [1]', 'value' => 'Da'],
                    ['key' => 'Abonat - Nume [2]', 'value' => 'Ionescu'],
                    ['key' => 'Abonat - Trimite email [2]', 'value' => 'Nu'],
                    ['key' => 'altceva', 'value' => 'x'],
                ]],
                ['name' => 'Masaj', 'meta_data' => []],
            ],
        ];

        $service = app(OrderSpaService::class);
        $parsed = (fn ($o) => $this->parseOrderInfo($o))->call($service, $order);

        $clients = $parsed['items'][0]['clients'];
        $this->assertCount(2, $clients);
        $this->assertSame('Popescu', $clients[0]['last_name']);
        $this->assertSame('Ion', $clients[0]['first_name']);
        $this->assertSame('ion@example.com', $clients[0]['email']);
        $this->assertTrue($clients[0]['send']);
        $this->assertSame('Ionescu', $clients[1]['last_name']);
        $this->assertFalse($clients[1]['send']);

        // Item without meta_data gets one empty beneficiary.
        $this->assertCount(1, $parsed['items'][1]['clients']);
        $this->assertSame('', $parsed['items'][1]['clients'][0]['email']);

        // Every beneficiary gets a voucher number starting with the order id.
        foreach ([$clients[0], $clients[1], $parsed['items'][1]['clients'][0]] as $c) {
            $this->assertStringStartsWith('555', $c['voucher']);
        }
    }
}
