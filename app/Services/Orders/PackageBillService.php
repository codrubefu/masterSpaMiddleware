<?php

namespace App\Services\Orders;

use App\Models\Client;
use App\Models\Detaliitrz;
use App\Models\Gencard;
use App\Models\Genprod;
use App\Models\Pret;
use App\Models\Trzbill;
use App\Models\Trzdetbill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Web packages (genprod with a configured clasa/grupa, e.g. WEB/PACK).
 *
 * A package order is written as an information/booking note:
 *   trzbill     header: client + total
 *   trzdetbill  one line per package component (from gencard)
 *   detaliitrz  same components, facturat = 0, linked by nrbill
 * Reception turns it into a payment note (facturat = 1) in their app.
 *
 * Must run inside the order's DB transaction.
 */
class PackageBillService
{
    /** Configured [['clasa' => .., 'grupa' => ..], ...] pairs. */
    public function filters(): array
    {
        return config('orders.packages.filters', []);
    }

    /** genprod query restricted to the configured package classes/groups. */
    public function packagesQuery(): Builder
    {
        $filters = $this->filters();

        return Genprod::query()->where(function (Builder $q) use ($filters) {
            if (!$filters) {
                $q->whereRaw('1 = 0');
            }
            foreach ($filters as $f) {
                $q->orWhere(function (Builder $q) use ($f) {
                    $q->whereRaw('RTRIM(LTRIM(clasa)) = ?', [$f['clasa']])
                        ->whereRaw('RTRIM(LTRIM(grupa)) = ?', [$f['grupa']]);
                });
            }
        });
    }

    /** The package matching a WooCommerce item (by name = genprod.art), or null. */
    public function findPackage(array $item): ?Genprod
    {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $package = $this->packagesQuery()
            ->whereRaw('RTRIM(LTRIM(art)) = ?', [$name])
            ->where('activ', 1)
            ->first();

        // Not a configured package: any genprod product with this name becomes
        // a package of itself (components() falls back to the product).
        return $package ?? Genprod::query()
            ->whereRaw('RTRIM(LTRIM(art)) = ?', [$name])
            ->orderByDesc('activ')
            ->orderByDesc('idprod')
            ->first();
    }

    /**
     * Package price from pret (clasa/grupa/art of the genprod package).
     * It has priority over the sum of the gencard components.
     */
    public function packagePrice(Genprod $package): ?float
    {
        $pret = $this->byProduct(Pret::query(), $package->clasa, $package->grupa, $package->art)
            ->orderByDesc('data')
            ->first();

        return $pret ? (float) $pret->pret : null;
    }

    /** gencard components of a package, each with its VAT rate from genprod. */
    public function components(Genprod $package): Collection
    {
        $rows = Gencard::query()
            ->where('idprod', $package->idprod)
            ->orderBy('idgencard')
            ->get();

        // No gencard components: the product itself is the package (single line).
        if ($rows->isEmpty()) {
            return collect([[
                'clasa' => trim($package->clasa),
                'grupa' => trim($package->grupa),
                'art' => trim($package->art),
                'cant' => 1.0,
                'val' => 0.0, // allocate() puts the whole package price on it
                'cotatva' => self::vatRate($package),
            ]]);
        }

        return $rows->map(function (Gencard $row) {
            $product = $this->byProduct(Genprod::query(), $row->clasac, $row->grupac, $row->artc)->first();
            if (!$product) {
                throw new \RuntimeException("Component '" . trim($row->artc) . "' not found in genprod.");
            }

            return [
                'clasa' => trim($row->clasac),
                'grupa' => trim($row->grupac),
                'art' => trim($row->artc),
                'cant' => (float) $row->cant ?: 1.0,
                'val' => (float) $row->val,
                'cotatva' => self::vatRate($product),
            ];
        });
    }

    /**
     * Writes trzbill + trzdetbill + detaliitrz for the package items of an order.
     *
     * @param array $packages [['package' => Genprod, 'item' => array], ...]
     */
    public function createBill(Client $client, array $packages, $orderId): Trzbill
    {
        $now = $this->now();
        $lines = [];
        foreach ($packages as $entry) {
            array_push($lines, ...$this->lines($entry['package'], $entry['item']));
        }

        $total = round(array_sum(array_column($lines, 'valoare')), 2);
        $vat = round(array_sum(array_map(
            fn ($l) => self::vatOf($l['valoare'], $l['cotatva']),
            $lines
        )), 2);

        // TODO: nrbill comes from the trzbill IDENTITY for now; confirm with Acord
        // whether web notes need their own counter/series (e.g. nrnpspec).
        $bill = new Trzbill();
        $bill->idfirma = (int) config('orders.idfirma', 1);
        $bill->idcl = $client->spaid;
        $bill->totalron = $total;
        $bill->tva19 = $vat;
        $bill->data = $now;
        $bill->compid = 'Website';
        $bill->idpers = 0;
        $bill->cardid = ' ';
        $bill->tip = 'WEBSITE';
        $bill->idrezervarehotel = 0;
        $bill->obs = 'Sales Order from Website: ' . $orderId;
        $bill->save();
        $bill->refresh();

        foreach ($lines as $line) {
            $this->createDetBill($client, $bill, $line, $now);
            $this->createDetaliiTrz($client, $bill, $line, $now);
        }

        Log::info('Created package bill', ['nrbill' => $bill->nrbill, 'order' => $orderId, 'total' => $total]);

        return $bill;
    }

    /** trzdetbill/detaliitrz lines of one ordered package (quantity applied). */
    private function lines(Genprod $package, array $item): array
    {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $components = $this->components($package)->all();
        $price = $this->packagePrice($package);

        $webPrice = isset($item['subtotal']) ? (float) $item['subtotal'] / $quantity : null;

        if ($price === null) {
            $price = array_sum(array_column($components, 'val'));
            if ($price <= 0 && $webPrice !== null) {
                $price = $webPrice;
                Log::warning('No pret for package, using website price', ['art' => trim($package->art), 'price' => $price]);
            } else {
                Log::warning('No pret for package, using gencard sum', ['art' => trim($package->art), 'price' => $price]);
            }
        }

        if ($webPrice !== null && abs($webPrice - $price) > 0.009) {
            Log::warning('Website price differs from pret, using pret', [
                'art' => trim($package->art), 'pret' => $price, 'website' => $webPrice,
            ]);
        }

        $values = self::allocate($price, array_column($components, 'val'));

        $lines = [];
        foreach ($components as $i => $c) {
            $cant = $c['cant'] * $quantity;
            $valoare = round($values[$i] * $quantity, 2);
            $lines[] = $c + [
                'cant_total' => $cant,
                'pret' => round($valoare / $cant, 2),
                'valoare' => $valoare,
            ];
        }

        return $lines;
    }

    private function createDetBill(Client $client, Trzbill $bill, array $line, string $now): Trzdetbill
    {
        $det = new Trzdetbill();
        $det->idfirma = (int) config('orders.idfirma', 1);
        $det->nrbill = $bill->nrbill;
        $det->idcl = $client->spaid;
        $det->clasa = $line['clasa'];
        $det->grupa = $line['grupa'];
        $det->art = $line['art'];
        $det->cant = $line['cant_total'];
        $det->pretueur = 0;
        $det->preturon = $line['pret'];
        $det->valoare = $line['valoare'];
        $det->data = $now;
        $det->compid = 'Website';
        $det->idpers = 0;
        $det->inchidzi = false;
        $det->cardid = ' ';
        $det->cotatva = $line['cotatva'];
        $det->dataactiv = $now;
        $det->genconsum = false;
        $det->save();

        return $det;
    }

    private function createDetaliiTrz(Client $client, Trzbill $bill, array $line, string $now): Detaliitrz
    {
        $det = new Detaliitrz();
        $det->idfirma = (int) config('orders.idfirma', 1);
        $det->idcl = $client->spaid;
        $det->obscui = 'independent';
        $det->clasa = $line['clasa'];
        $det->grupa = $line['grupa'];
        $det->art = $line['art'];
        $det->canti = $line['cant_total'];
        $det->cantc = $line['cant_total'];
        $det->pret = $line['pret'];
        $det->preteur = 0;
        $det->cotatva = $line['cotatva'];
        // Same values reception writes for a manual note.
        $det->card = $line['clasa'];
        $det->facturat = false;
        $det->extra = true;
        $det->consultat = false;
        $det->predat = false;
        $det->idclientprog = 0;
        $det->nrbill = $bill->nrbill;
        $det->datac = $now;
        $det->data = $now;
        $det->dataactiv = $now;
        $det->compid = 'Website';
        $det->save();

        return $det;
    }

    /**
     * Splits $total over the component values proportionally (2 decimals);
     * the last component absorbs the rounding so the sum is exactly $total.
     *
     * @param float[] $values
     * @return float[]
     */
    public static function allocate(float $total, array $values): array
    {
        $values = array_values($values);
        $n = count($values);
        if ($n === 0) {
            return [];
        }

        $sum = array_sum($values);
        if (abs($sum - $total) < 0.005) {
            return array_map(fn ($v) => round($v, 2), $values);
        }

        $out = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $out[] = $sum > 0 ? round($total * $values[$i] / $sum, 2) : ($i === 0 ? round($total, 2) : 0.0);
        }
        $out[] = round($total - array_sum($out), 2);

        return $out;
    }

    /** VAT rate of a genprod product as a fraction (first non-zero tax1..tax5). */
    public static function vatRate($product): float
    {
        foreach (['tax1', 'tax2', 'tax3', 'tax4', 'tax5'] as $col) {
            $rate = (float) ($product->{$col} ?? 0);
            if ($rate > 0) {
                return $rate;
            }
        }

        return 0.0;
    }

    /** VAT part of a VAT-inclusive amount. */
    public static function vatOf(float $gross, float $rate): float
    {
        return $rate > 0 ? round($gross - $gross / (1 + $rate), 2) : 0.0;
    }

    private function byProduct(Builder $q, $clasa, $grupa, $art): Builder
    {
        return $q->whereRaw('RTRIM(LTRIM(clasa)) = ?', [trim((string) $clasa)])
            ->whereRaw('RTRIM(LTRIM(grupa)) = ?', [trim((string) $grupa)])
            ->whereRaw('RTRIM(LTRIM(art)) = ?', [trim((string) $art)]);
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s.v');
    }
}
