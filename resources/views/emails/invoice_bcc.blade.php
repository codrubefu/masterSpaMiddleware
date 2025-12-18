<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Comanda noua</title>
</head>
<body>
    <h2>Comanda noua inregistrata pe site</h2>
    <p><strong>Client:</strong> {{ ($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '') }}</p>
    <p><strong>Email:</strong> {{ $order['billing']['email'] ?? '' }}</p>
    <p><strong>Telefon:</strong> {{ $order['billing']['phone'] ?? '' }}</p>
    <p><strong>Perioada:</strong> {{ $order['custom_info']['start_date'] ?? '' }} - {{ $order['custom_info']['end_date'] ?? '' }}</p>
    <p><strong>Total:</strong> {{ $order['total'] ?? '' }} RON</p>

    @if(!empty($order['items'] ?? []))
        <h3>Detalii camere</h3>
        <ul>
            @foreach($order['items'] ?? [] as $item)
                <li>
                    {{ $item['name'] ?? 'Produs' }} -
                    Cantitate: {{ $item['quantity'] ?? 1 }},
                    Subtotal: {{ $item['subtotal'] ?? 0 }} RON
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($invoiceFile))
        <p>Factura atasata: {{ $invoiceFile }}</p>
    @endif
</body>
</html>
