<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_payment_status_is_isolated_per_casa_for_same_payment(): void
    {
        $payloadCasa1 = ['payment_id' => 'order-1001', 'casa_id' => 'casa-1'];
        $payloadCasa2 = ['payment_id' => 'order-1001', 'casa_id' => 'casa-2'];

        $this->postJson('/api/payment', $payloadCasa1)->assertOk()->assertJson(['done' => true]);
        $this->postJson('/api/payment', $payloadCasa2)->assertOk()->assertJson(['done' => true]);

        $this->postJson('/api/is-payment-done', $payloadCasa1)
            ->assertOk()
            ->assertJson(['done' => true, 'casa_id' => 'casa-1']);

        $this->postJson('/api/is-payment-done', $payloadCasa2)
            ->assertOk()
            ->assertJson(['done' => true, 'casa_id' => 'casa-2']);
    }

    public function test_default_query_returns_true_if_any_casa_is_paid(): void
    {
        $this->postJson('/api/payment', ['payment_id' => 'order-1002', 'casa_id' => 'casa-1'])
            ->assertOk();

        $this->postJson('/api/is-payment-done', ['payment_id' => 'order-1002'])
            ->assertOk()
            ->assertJson(['done' => true, 'casa_id' => 'default']);
    }
}
