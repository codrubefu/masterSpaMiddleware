<?php

/*
|--------------------------------------------------------------------------
| Website orders (WooCommerce -> SPA database)
|--------------------------------------------------------------------------
*/

return [

    // Cota TVA in procente (ex. 11 => 11%).
    'vat_rate' => (float) env('ORDER_VAT_RATE', 11),

    // Firma (company.idfirma) si gestiunea (gest.nrgest) pe care se emit documentele.
    'idfirma' => (int) env('ORDER_IDFIRMA', 1),
    'nr_gest' => (int) env('ORDER_NR_GEST', 1),

    /*
    | Daca e false, nu se creeaza nota de plata (trznp) si factura (trzfact),
    | impreuna cu detaliile lor (trzdetnp, trzdet, trzdetfact). La hotel se
    | trimite atunci doar un email de confirmare, fara factura PDF.
    */
    'np_invoice' => [
        'enabled' => (bool) env('CREATE_NP_AND_INVOICE', false),
    ],

    // Valoarea scrisa in client.hotel, dupa idhotel din WooCommerce.
    'hotels' => [
        1 => '1~Hotel Noblesse',
    ],
    'default_hotel' => '1~Hotel Royal',

    'mail' => [
        // true => emailurile se pun pe coada (necesita `php artisan queue:work`
        // daca QUEUE_CONNECTION nu e `sync`).
        'queue' => (bool) env('ORDER_MAIL_QUEUE', false),

        'client_name' => env('CLIENT_NAME', 'Mirage MedSPA Hotel'),

        // Hotel: subiect email client + destinatari copie/sumar comanda.
        'hotel_subject' => env('ORDER_HOTEL_MAIL_SUBJECT', 'Rezervarea dumneavoastra de la Noblesse'),
        'hotel_staff' => array_filter(explode(',', env('ORDER_HOTEL_MAIL_STAFF', 'codrut_befu@yahoo.com,support@masterspa.ro'))),

        // Spa: destinatari in copie la emailurile cu vouchere.
        'voucher_staff' => array_filter(explode(',', env('ORDER_VOUCHER_MAIL_STAFF', 'codrut_befu@yahoo.com,mgrus@acordnet.ro'))),
    ],
];
