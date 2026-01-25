<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Response;

class VoucherPreviewController extends Controller
{
    /**
     * Show a preview of the voucher as a PDF in the browser.
     * Example usage: /voucher-preview?first_name=John&last_name=Doe&voucher_no=VCH-123&date=2026-01-18
     */
    public function show(Request $request)
    {
        $client = [
            'first_name' => $request->input('first_name', 'John'),
            'last_name' => $request->input('last_name', 'Doe'),
        ];
        $order = [
            'items' => [
                ['name' => 'Masaj relaxare', 'quantity' => 1],
                ['name' => 'Acces Spa', 'quantity' => 2],
            ]
        ];
        $voucher_no = $request->input('voucher_no', 'VCH-123');
        $date = $request->input('date', date('d-m-Y'));

        $data = [
            'client' => $client,
            'order' => $order,
            'voucher_no' => $voucher_no,
            'date' => $date,
        ];


        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('emails.voucher_pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        return $pdf->stream('voucher_preview.pdf');
    }
}
