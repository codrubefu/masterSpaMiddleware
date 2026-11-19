<?php

namespace Tests\Unit\Orders;

use App\Services\Orders\PackageBillService;
use Tests\TestCase;

/**
 * No database access: price allocation, VAT and config parsing only.
 */
class PackageBillTest extends TestCase
{
    public function test_allocate_keeps_component_values_when_they_match(): void
    {
        $this->assertSame([2380.0, 3320.0], PackageBillService::allocate(5700, [2380, 3320]));
    }

    public function test_allocate_uses_package_price_when_components_differ(): void
    {
        $out = PackageBillService::allocate(5000, [2380, 3320]);
        $this->assertEqualsWithDelta(5000, array_sum($out), 0.0001);
        $this->assertSame(2087.72, $out[0]);
        $this->assertSame(2912.28, $out[1]);
    }

    public function test_allocate_rounding_goes_to_last_line(): void
    {
        $out = PackageBillService::allocate(100, [1, 1, 1]);
        $this->assertSame([33.33, 33.33, 33.34], $out);
    }

    public function test_allocate_zero_components_puts_price_on_first(): void
    {
        $this->assertSame([250.0, 0.0], PackageBillService::allocate(250, [0, 0]));
        $this->assertSame([], PackageBillService::allocate(250, []));
    }

    public function test_vat_rate_is_first_non_zero_tax(): void
    {
        $this->assertSame(0.11, PackageBillService::vatRate((object) ['tax1' => '.0000', 'tax2' => '.1100']));
        $this->assertSame(0.21, PackageBillService::vatRate((object) ['tax1' => '.2100', 'tax2' => '0']));
        $this->assertSame(0.0, PackageBillService::vatRate((object) ['tax1' => '.0000']));
    }

    public function test_vat_of_vat_inclusive_amount(): void
    {
        $this->assertSame(329.01, PackageBillService::vatOf(3320, 0.11));
        $this->assertSame(0.0, PackageBillService::vatOf(2380, 0));
    }

    public function test_filters_config_parsing(): void
    {
        $this->assertSame([['clasa' => 'WEB', 'grupa' => 'PACK']], config('orders.packages.filters'));
    }
}
