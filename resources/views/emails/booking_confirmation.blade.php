<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Confirmare rezervare</title>
</head>
<body>
   <p>Bună ziua,</p>
   <p>Vă mulțumim pentru rezervare! Mai jos găsiți detaliile acesteia.</p>

   <p><strong>Perioada:</strong> {{ $order['custom_info']['start_date'] ?? '' }} - {{ $order['custom_info']['end_date'] ?? '' }}</p>
   @if(!empty($order['items'] ?? []))
       <ul>
           @foreach($order['items'] as $item)
               <li>{{ $item['name'] ?? 'Camera' }} - Cantitate: {{ $item['quantity'] ?? 1 }}, Subtotal: {{ $item['subtotal'] ?? 0 }} RON</li>
           @endforeach
       </ul>
   @endif
   <p><strong>Total:</strong> {{ $order['total'] ?? '' }} RON</p>

   <p>Pentru orice întrebări sau nelămuriri, nu ezitați să ne contactați.</p>
   <p>Părerea dumneavoastră este foarte importantă pentru noi și de aceea am aprecia foarte
    mult un feedback cu privire la experiența serviciilor noastre,
    prin intermediul adresei noastre de e-mail: noblesse@resortparadis.ro.</p>
   <p>Vă mulțumim,</p>
   <p>Echipa Noblesse</p>
   <p>Web : <a href="https://resortparadis.ro/">https://resortparadis.ro/</a></p>
   <p>Contact : +40 748 182 282</p>
</body>
</html>
