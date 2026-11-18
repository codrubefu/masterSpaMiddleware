<?php

namespace App\Services\Orders;

use App\Helper\MoneyToWords;
use App\Helper\Vat;
use App\Models\Client;
use App\Models\Genprod;
use App\Models\Gest;
use App\Models\Pret;
use App\Models\Trzdet;
use App\Models\Trzdetfact;
use App\Models\Trzdetnp;
use App\Models\Trzfact;
use App\Models\Trznp;
use Illuminate\Support\Facades\DB;

/**
 * Creates the payment note (trznp + trzdetnp + trzdet) and the invoice
 * (trzfact + trzdetfact) of a website order. Must run inside the order's
 * DB transaction.
 */
class NpInvoiceService
{
    /** Whether NP + invoice must be created at all (CREATE_NP_AND_INVOICE). */
    public function enabled(): bool
    {
        return (bool) config('orders.np_invoice.enabled', false);
    }

    /**
     * Reserves the next invoice number from gest.nrf, e.g. FA25100042.
     * The row lock is held until the surrounding transaction commits, so
     * concurrent orders can't get the same number.
     */
    public function reserveInvoiceNo(): string
    {
        $nrGest = (int) config('orders.nr_gest', 1);

        $next = DB::transaction(function () use ($nrGest) {
            $gest = Gest::where('nrgest', $nrGest)->lockForUpdate()->first();
            if (!$gest) {
                return 1;
            }
            $gest->nrf = $gest->nrf + 1;
            $gest->save();

            return $gest->nrf;
        });

        return 'FA' . date('y') . $nrGest . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /** Payment note header (trznp). */
    public function createNp(Client $client, float $total, $idrezervarehotel): Trznp
    {
        $trznp = new Trznp();
        $trznp->spaid = $client->spaid;
        $trznp->totalron = $total;
        $trznp->tva19 = Vat::vat($total);
        $trznp->data = $this->now();
        $trznp->compid = 'WEBSITE';
        $trznp->obscui = 'independent';
        $trznp->modp = 'Bank Card Web';
        $trznp->tipnp = 'Inside Services';
        $trznp->tipcc = 'VISA';
        $trznp->nrtrzcc = '1234****3456';
        $trznp->cardid  = ' ';
        $trznp->idrec  = 0;
        $trznp->idlogin = 0;
        $trznp->prmcod  = ' ';
        $trznp->idrezervarehotel = $idrezervarehotel;
        $trznp->tip = 'WEBSITE';
        $trznp->obs = ' Sales Order from Website: ' . $idrezervarehotel;
        $trznp->descval = MoneyToWords::toWords($total);
        $trznp->save();

        return $trznp->refresh();
    }

    /** Payment note line (trzdetnp) + its transaction detail (trzdet). */
    public function createNpLine(Client $client, Trznp $trznp, float $subtotal, $quantity, $idrezervarehotel, $art): Trzdetnp
    {
        $now = $this->now();

        $trzdetnp = new Trzdetnp();
        $trzdetnp->nrnp = $trznp->nrnpint;
        $trzdetnp->spaid = $client->spaid;
        $trzdetnp->art = $art;
        $trzdetnp->cant = $quantity;
        $trzdetnp->preturon = $subtotal / $quantity;
        $trzdetnp->valoare = $subtotal;
        $trzdetnp->data = $now;
        $trzdetnp->compid = 'Website';
        $trzdetnp->pretfaradisc = $subtotal;
        $trzdetnp->valuta = 'RON';
        $trzdetnp->cursv = 1;
        $trzdetnp->datac = $now;
        $trzdetnp->dataactiv = $now;
        $trzdetnp->tipf = 'Hotel';
        $trzdetnp->idrezervarehotel = $idrezervarehotel;
        $trzdetnp->cardid = 0;
        $trzdetnp->idprog = 0;
        $trzdetnp->idtrz = 0;
        $trzdetnp->idcldet = $client->spaid;
        $trzdetnp->pretueur = 0.00;
        $trzdetnp->nrvestiar = ' ';
        $trzdetnp->cotatva = Vat::fraction();
        $trzdetnp->nrvestiar2 = ' ';
        $trzdetnp->save();
        $trzdetnp->refresh();

        $this->createTrzdet($trzdetnp);

        return $trzdetnp;
    }

    /** Invoice header (trzfact); reserves the invoice number and links it to the NP. */
    public function createInvoice(Client $client, float $total, Trznp $trznp): Trzfact
    {
        $now = $this->now();

        $trzfact = new Trzfact();
        $trzfact->idfirma = (int) config('orders.idfirma', 1);
        $trzfact->nrfactfisc = ' ';
        $trzfact->nrdep = 1;
        $trzfact->nrgest = (int) config('orders.nr_gest', 1);
        $trzfact->idcl = $client->spaid;
        $trzfact->stotalron = Vat::net($total);
        $trzfact->tva = Vat::vat($total);
        $trzfact->cotatva = -1;
        $trzfact->totalron = $total;
        $trzfact->sold = $total;
        $trzfact->tipv = 'RON';
        $trzfact->nume = $client->den . ' ' . $client->prenume;
        $trzfact->cnp = '000000000000';
        $trzfact->datafact = $now;
        $trzfact->datascad = $now;
        $trzfact->data = $now;
        $trzfact->compid = 'Website';
        $trzfact->tip = 'CP';
        $trzfact->nrfactspec = $this->reserveInvoiceNo();
        $trzfact->idpers = 0;
        $trzfact->curseur = 0.0000;
        $trzfact->cursusd = 0.0000;
        $trzfact->nrnp = $trznp->nrnpint;
        $trzfact->ciserie = ' ';
        $trzfact->cinr = ' ';
        $trzfact->cipol = ' ';
        $trzfact->auto = ' ';
        $trzfact->nrauto = ' ';
        $trzfact->save();
        $trzfact->refresh();

        $trznp->nrfact = $trzfact->nrfact;
        $trznp->save();

        return $trzfact;
    }

    /**
     * Invoice line (trzdetfact). Class/group come from genprod (spa, by
     * product name) or from pret (hotel, by room type).
     */
    public function createInvoiceLine(Client $client, Trzfact $trzfact, float $subtotal, $quantity, array $item, $roomType = null, bool $spa = false): Trzdetfact
    {
        if ($spa) {
            // Legacy tables often store padded CHAR values (trailing spaces).
            $price = Genprod::query()
                ->whereRaw('RTRIM(LTRIM(art)) = ?', [$item['name'] ?? null])
                ->first();
        } else {
            $price = Pret::where('tipcamera', $roomType)->first();
        }

        if (!$price) {
            throw new \RuntimeException('Price source not found for item.');
        }

        $trzdetfact = new Trzdetfact();
        $trzdetfact->idfirma = (int) config('orders.idfirma', 1);
        $trzdetfact->nrfact = $trzfact->nrfact;
        $trzdetfact->idcl = $client->spaid;
        $trzdetfact->clasa = $price->clasa;
        $trzdetfact->grupa = $price->grupa;
        $trzdetfact->art = $item['meta_data'][0]['value'] ?? null;
        $trzdetfact->cant = $quantity;
        $trzdetfact->cantf = $quantity;
        $trzdetfact->preturon = Vat::net($subtotal / $quantity);
        $trzdetfact->valoare = Vat::net($subtotal);
        $trzdetfact->tva = Vat::vat($subtotal);
        $trzdetfact->data = $this->now();
        $trzdetfact->compid = 'Website';
        $trzdetfact->idpers = '0';
        $trzdetfact->cotatva = Vat::fraction();
        $trzdetfact->save();

        return $trzdetfact;
    }

    private function createTrzdet(Trzdetnp $trzdetnp): Trzdet
    {
        $trzdet = new Trzdet();
        $trzdet->idfirma = (int) config('orders.idfirma', 1);
        $trzdet->nrdoc = $trzdetnp->nrnp;
        $trzdet->idcl = $trzdetnp->spaid;
        $trzdet->art = $trzdetnp->art;
        $trzdet->cant = $trzdetnp->cant;
        $trzdet->pretueur = 0.00;
        $trzdet->preturon = $trzdetnp->preturon;
        $trzdet->valoare = $trzdetnp->valoare;
        $trzdet->tva = null;
        $trzdet->tip = 'NP';
        $trzdet->data = $this->now();
        $trzdet->compid = $trzdetnp->compid;
        $trzdet->idpers = $trzdetnp->idpers;
        $trzdet->redproc = $trzdetnp->redproc ?? null;
        $trzdet->cardid = $trzdetnp->cardid;
        $trzdet->redabn = $trzdetnp->redabn ?? null;
        $trzdet->pretfaradisc = $trzdetnp->pretfaradisc;
        $trzdet->modp = 'Bank Card Web';
        $trzdet->idprog = $trzdetnp->idprog;
        $trzdet->idtrz = $trzdetnp->idtrz;
        $trzdet->idrevc = 1;
        $trzdet->valuta = 'RON';
        $trzdet->cursv = 1.000;
        $trzdet->datac = $this->now();
        $trzdet->cotatva = Vat::fraction();
        $trzdet->reinnoire = false;
        $trzdet->cardb = 0;
        $trzdet->idcldet = $trzdetnp->spaid;
        $trzdet->save();

        return $trzdet;
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s.v');
    }
}
