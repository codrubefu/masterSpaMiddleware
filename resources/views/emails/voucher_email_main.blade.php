<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voucher Email</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 30px; border-radius: 8px; }
        .title { font-size: 22px; color: #4a90e2; font-weight: bold; margin-bottom: 20px; }
        .info { font-size: 16px; margin-bottom: 10px; }
        .voucher-code { font-size: 20px; color: #e94e77; font-weight: bold; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        @php $lang = $lang ?? 'ro'; @endphp
        @if($lang === 'en')
        <div class="title">Mirage MedSPA Hotel</div>
        <div class="info">Good day,</div>
        <div class="info">Thank you for your order at Mirage MedSPA Hotel, Eforie Nord!</div>
        <div class="info">Your payment has been confirmed. Please find attached your invoice and all the vouchers for your order.</div>
        <div class="info">Below you will find a summary of the voucher delivery status for each recipient.</div>
        <div class="info">Your opinion is very important to us, and we would greatly appreciate your feedback regarding your experience with our services, either through the online form or by replying to our e-mail address: rezervari@miragemedspahotel.ro</div>
        <div class="info">For appointments and any other additional information we are at your disposal!</div>
        <div class="info">Best regards,</div>
        <div class="info">Mirage MedSPA Hotel Team</div>
        <div class="info">Web: <a href="https://miragemedspahotel.ro/">https://miragemedspahotel.ro/</a></div>
        <div class="info">Contact: +4 0241 74 24 01</div>
        @else
        <div class="title">Mirage MedSPA Hotel</div>
        <div class="info">Bună ziua,</div>
        <div class="info">Vă mulțumim pentru comanda dumneavoastră la Mirage MedSPA Hotel din Eforie Nord!</div>
        <div class="info">Plata a fost confirmată. Găsiți atașat factura fiscală și toate voucherele pentru comanda dumneavoastră.</div>
        <div class="info">Mai jos aveți un sumar cu statusul trimiterii voucherelor pentru fiecare destinatar.</div>
        <div class="info">Părerea dumneavoastră este foarte importantă pentru noi și de aceea am aprecia foarte mult un feedback cu privire la experiența avută cu serviciile noastre, prin intermediul formularului web sau printr-un reply la adresa de e-mail: rezervari@miragemedspahotel.ro</div>
        <div class="info">Pentru programări și orice alte informații suplimentare vă stăm la dispoziție!</div>
        <div class="info">Vă mulțumim,</div>
        <div class="info">Echipa Mirage MedSPA Hotel</div>
        <div class="info">Web: <a href="https://miragemedspahotel.ro/">https://miragemedspahotel.ro/</a></div>
        <div class="info">Contact: +4 0241 74 24 01</div>
        @endif
    </div>
    <div style="margin: 40px;">
        <h3>Raport trimitere vouchere</h3>
        <table border="1" cellpadding="8" cellspacing="0" style="background: #fff;">
            <thead>
                <tr>
                    <th>Nume</th>
                    <th>Email</th>
                    <th>Voucher</th>
                    <th>Status</th>
                    <th>Eroare</th>
                </tr>
            </thead>
            <tbody>
                @foreach($emailStatus ?? [] as $status)
                    <tr>
                        <td>{{ $status['name'] ?? '' }}</td>
                        <td>{{ $status['email'] ?? '' }}</td>
                        <td>{{ $status['voucher_no'] ?? '' }}</td>
                        <td style="color:{{ $status['sent'] ? 'green' : 'red' }};font-weight:bold;">
                            {{ $status['sent'] ? 'Trimis' : 'Ne-trimis' }}
                        </td>
                        <td style="color:red;">{{ $status['error'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
