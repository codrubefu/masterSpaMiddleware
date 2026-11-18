<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Only invalid payloads: validation fails before the controller runs,
 * so nothing reaches the database.
 */
class OrderValidationTest extends TestCase
{
    private function headers(): array
    {
        return ['X-API-Secret' => config('app.api_secret', 'your-default-secret-key')];
    }

    public function test_spa_order_without_billing_returns_json_422_even_without_accept_header(): void
    {
        $response = $this->post('/api/order/spa', ['id' => 1, 'total' => 10, 'items' => []], $this->headers());

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['billing', 'items']);
    }

    public function test_hotel_order_requires_booking_dates_and_room_meta(): void
    {
        $response = $this->postJson('/api/order', [
            'total' => 100,
            'billing' => ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'phone' => '07'],
            'custom_info' => ['start_date' => '2026-12-05', 'end_date' => '2026-12-01'],
            'items' => [['name' => 'Dubla', 'quantity' => 1, 'subtotal' => 100]],
        ], $this->headers());

        $response->assertStatus(422)->assertJsonValidationErrors([
            'custom_info.end_date',
            'items.0.product_meta_input._hotel_id.0',
            'items.0.meta_data.0.value',
        ]);
    }
}
