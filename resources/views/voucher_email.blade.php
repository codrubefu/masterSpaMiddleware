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
        <div class="info">Thank you for the interest you have shown towards Mirage MedSPA Hotel from Eforie Nord!</div>
        <div class="info">We would like to inform you that the payment has been confirmed. Please find the invoice attached and your voucher.</div>
        <div class="info">Your opinion is very important to us, and we would greatly appreciate your feedback regarding your experience with our services, either through the online form or by replying to our e-mail address: rezervari@miragemedspahotel.ro</div>
        <div class="info">For appointments and any other additional information we are at your disposal!</div>
        <div class="info">Best regards,</div>
        <div class="info">Mirage MedSPA Hotel Team</div>
        <div class="info">Web: <a href="https://miragemedspahotel.ro/">https://miragemedspahotel.ro/</a></div>
        <div class="info">Contact: +4 0241 74 24 01</div>
        @else
        <div class="title">Mirage MedSPA Hotel</div>
        <div class="info">Bună ziua,</div>
        <div class="info">Vă mulțumim pentru interesul manifestat față de serviciile Mirage MedSPA Hotel din stațiunea Eforie Nord!</div>
        <div class="info">Vă informăm că plata este confirmată. Vă rugăm să regăsiți anexat acestui e-mail factura fiscală și voucher-ul dumneavoastră.</div>
        <div class="info">Părerea dumneavoastră este foarte importantă pentru noi și de aceea am aprecia foarte mult un feedback cu privire la experiența avută cu serviciile noastre, prin intermediul formularului web sau printr-un reply la adresa de e-mail: rezervari@miragemedspahotel.ro</div>
        <div class="info">Pentru programări și orice alte informații suplimentare vă stăm la dispoziție!</div>
        <div class="info">Vă mulțumim,</div>
        <div class="info">Echipa Mirage MedSPA Hotel</div>
        <div class="info">Web: <a href="https://miragemedspahotel.ro/">https://miragemedspahotel.ro/</a></div>
        <div class="info">Contact: +4 0241 74 24 01</div>
        @endif
    </div>
</body>
</html>
