<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Factura MasterSPA</title>
</head>
<body>
   <p>Bună ziua,</p>
   <p>Vă mulțumim pentru interesul acordat serviciilor noastre!</p>
   <p>Pentru orice întrebări sau nelămuriri, nu ezitați să ne contactați.</p>
   <p>Parerea dumneavoastra este foarte importanta pentru noi si de aceea am aprecia foarte 
    mult un feedback cu privire la experiența serviciilor noastre, 
    prin intermediul adresei noastre de e-mail: {{ config('appconfig.hotel_email') }} </p>
   <p>Pentru informatii suplimentare va stam la dispozitie!</p>
   
   <h3>Comanda dumneavoastra </h3>
        <h3>Detalii camere</h3>
        <ul>
            @foreach($items as $item)
                <li>
                    Ati platit: {{ $item['name'] ?? 'Produs' }} &nbsp;
                    Avans: {{ $item['total'] ?? 0 }} RON &nbsp;
                    Rest de plata: {{ round((float) ($item['original_total'] ?? 0) - 200, 2) }} RON
                </li>
            @endforeach
        </ul>

   <p>Va multumim,</p>
   <p>Echipa {{ config('appconfig.hotel_1') }}</p>
   <p>Web : <a href="{{ config('appconfig.hotel_web') }}">{{ config('appconfig.hotel_web') }}</a></p>
   <p>Contact : {{ config('appconfig.hotel_phone') }}</p>
</body>
</html>