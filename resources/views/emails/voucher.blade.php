<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voucher</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .voucher-box {
            border: 2px dashed #888;
            padding: 30px;
            margin: 20px auto;
            max-width: 600px;
            background: #f9f9f9;
        }
        .voucher-title {
            font-size: 24px;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 20px;
        }
        .voucher-info {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .voucher-code {
            font-size: 20px;
            font-weight: bold;
            color: #e94e77;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="voucher-box">
        <div class="voucher-title">Voucher Cadou Noblesse Spa</div>
        <div class="voucher-info">Nume client: <strong>{{ $client['first_name'] }} {{ $client['last_name'] }}</strong></div>
        <div class="voucher-info">Data: <strong>{{ $date }}</strong></div>
        <div class="voucher-info">Număr voucher: <strong>{{ $voucher_no }}</strong></div>
        <div class="voucher-info">Servicii achiziționate:</div>
        <ul>
            @foreach($order['items'] as $item)
                <li>{{ $item['name'] }} &times; {{ $item['quantity'] }}</li>
            @endforeach
        </ul>
        <div class="voucher-code">Voucher: {{ $voucher_no }}</div>
        <div class="voucher-info">Prezentati acest voucher la recepție pentru a beneficia de serviciile achiziționate.</div>
    </div>
</body>
</html>
