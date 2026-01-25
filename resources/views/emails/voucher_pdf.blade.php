
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
    </style>
    <title>Gift Voucher - Mirage MedSpa Hotel</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #a6b6a2;
            background-image: url('file://{{ public_path('src/voucher_bg.jpg') }}');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;    
            max-height: 100vh;
        }
        .main-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #a6b6a2;
            z-index: 0;
        }
        .circle-bg {
            position: absolute;
            top: 0; left: 0;
            width: 60vw; height: 60vw;
            background: #e6ecd7;
            border-radius: 50%;
            z-index: 1;
        }
        .voucher-container {
            position: relative;
            z-index: 2;
            max-width: 30%;
            margin: 40px;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: row;
            gap: 40px;
        }
        .voucher-left {
            flex: 2;
        }
        .voucher-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 30px;
        }
        .voucher-subtitle {
            
            margin-top: 140px;
            font-size: 22px;
            color: #444;
            margin-bottom: 10px;
            text-align: center
        }

        .voucher-period {
            font-size: 14px;
            color: #444;
            margin-bottom: 10px;
            text-align: center
        }
        .voucher-list {
            font-size: 12px;
            color: #222;
            margin-bottom: 20px;
        }
        .voucher-indications {
            font-size: 12px;
            color: #222;
            margin-bottom: 20px;
        }

        ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .voucher-code{
            position: absolute;
            bottom: 10px;
            width: 100%;
            text-align: center;
        }
        .voucher-code > div {
            display: block;
            padding: 10px 20px;
            background: #ffffff;
            opacity: 0.8;
            border-radius: 16px;
            margin: 0 auto;
            max-width: 400px;
        }

        .voucher-barcode-img {
            display: block;
            margin: 0 auto 6px auto;
            height: 60px;
            opacity: 1;
        }

        .voucher-barcode-code {
            display: block;
            font-size: 14px;
            color: #444;
            margin-top: 2px;
            text-align: center;
            font-weight: bold;
            letter-spacing: 2px;
            opacity: 1;
            background: none;
        }

       

    </style>
</head>
<body>
    <div class="voucher-container">
        <div class="voucher-left">
            <div class="voucher-subtitle">Mirage Vital Spa 7 nopti<br>cazare gratuita 6 zile spa</div>
            <div class="voucher-period">Perioada: <strong>16.11-23.11.2025, 7 nopti</strong></div>
            <div class="voucher-list">
                <ul>
                    <li>Pachetul include: cazare gratuită pentru 2 adulți în camera dublă, mic dejun, cină, pachetul Mirage Vital Spa 6 zile - 4 terapii/zi, acces gratuit în zona Aqua Mirage + Fitness 1 oră/persoană/zi (în urma unei programări efectuate la Recepție Spa-ului începând cu ziua sosirii).</li>
                    <li>Conținut: consultație medicală, 4 terapii/zi, program personalizat în funcție de diagnostic (terapii cu nămol, masaj, hidroterapie, kinetoterapie, electroterapie, etc), recomandări.</li>
                </ul>
            </div>
            <div class="voucher-indications">
                <strong>Indicații:</strong> Afecțiuni reumatismale degenerative și inflamatorii, lumbago cronic, hernie de disc, afecțiuni neurologice periferice și centrale, recuperare posttraumatică și post chirurgicală, osteoporoză, hipertensiune arterială, afecțiuni ale sânului, tulburări circulatorii periferice, afecțiuni ginecologice, sterilitate secundară, afecțiuni cronice, afecțiuni respiratorii, afecțiuni ORL, afecțiuni respiratorii, acnee.
            </div>
           
        </div>
           
    </div>
         <div class="voucher-code">
            <div>
                @php
                    $dns1d = new \Milon\Barcode\DNS1D();
                    $barcode = $dns1d->getBarcodePNG($voucher_no ?? '1234567890', 'C128', 2, 60);
                @endphp
                <img src="data:image/png;base64,{{ $barcode }}" alt="Voucher Barcode" class="voucher-barcode-img" />
                <div class="voucher-barcode-code">{{ $voucher_no ?? '1234567890' }}</div>
            </div>
        </div>
      
  
</body>
</html>
