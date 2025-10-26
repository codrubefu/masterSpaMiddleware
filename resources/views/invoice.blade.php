<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FACTURA {{ $nrfactura }}</title>
  <style>
  
    body {
      font-family: 'DejaVu Sans', DejaVuSans, sans-serif;
      font-size: 10px;
      margin: 20px;
      color: #000;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    th, td {
      border: 1px solid #000;
      padding: 6px;
      text-align: left;
    }
    th {
      background: #f0f0f0;
    }
    .right { text-align: right; }
    .center { text-align: center; }
    .no-border td, .no-border th { border: none; }

    .header-info { 
       font-size: 10px;
     }

     .invoice-info {
        text-align: center
     }
     .footer-table td {
        border-bottom:0px;
     }



  </style>
</head>
<body>
  <table class="no-border">
    <tr class="header-info">
      <td style="width: 30%">
        <strong>{{ $company->den }}</strong><br>
        {{ $company->adresa1 }}<br>
        C.U.I.: {{ $company->cnpcui }}<br>
        Nr. Reg. Com.: {{ $company->nrc }}<br>
        Cont: {{ $company->iban }}<br>
        Banca: {{ $company->banca }}
      </td>
           <td style="width: 7%">
      </td>
      <td class="invoice-info" >
        <h2>FACTURA</h2>
         <p style="text-align: left">
            SERIE:NBLS<br>
            Număr: {{ $nrfactura }}<br>
            Data: {{ $data }}<br>
         </p>
      </td>
      <td style="width: 7%">
      </td>
      <td style="width: 30%">
            <strong>Client:</strong> {{ $client['first_name'] ?? '' }} {{ $client['last_name'] ?? '' }}<br>
            @if (isset($client['den']) && $client['den'] != '' && $client['den'] != null)
              <strong>Denumire:</strong> {{ $client['den'] ?? '' }}<br>
            @endif
            @if (isset($client['cnpcui']) && $client['cnpcui'] === '0000000000000')
             <strong>CNP:</strong> {{ $client['cnpcui'] }}<br>
            @else
            <strong>C.U.I.:</strong> {{ $client['cnpcui'] }}<br>
            @endif
            <strong>E-mail:</strong> {{ $client['email'] ?? '' }}<br>
            <strong>Adresă:</strong> {{ $client['address_1'] ?? '' }} {{ $client['address_2'] ?? '' }} {{ $client['city'] ?? '' }}<br>
            @if(isset($client['iban']) && $client['iban'])
            <strong>Cont</strong>: {{ $client['iban'] ?? '' }}<br>
            @endif
            @if(isset($client['banca']) && $client['banca']) 
            <strong>Banca:</strong> {{ $client['banca'] ?? '' }}
            @endif
       </td>
    </tr>
  </table>
  <p style="text-align: right; font-weight: bold;"> Scadent la: {{ $data }}</p>
  <table>
    <thead>
      <tr>
        <th class="center">Nr. crt</th>
        <th>Denumirea produselor sau a serviciilor</th>
        <th class="center">U.M.</th>
        <th class="right">Cantitate</th>
        <th class="right">Preț unitar fără TVA (RON)</th>
        <th class="center">Cota TVA</th>
        <th class="right">Valoare (RON)</th>
        <th class="right">Valoare TVA (RON)</th>

      </tr>
      <tr>
        <td class="center">0</td>
        <td>1</td>
        <td class="center">2</td>
        <td class="right">3</td>
        <td class="right">4</td>
        <td class="right">5</td>
        <td class="right">6</td>
        <td class="right">7</td>
      </tr>
    </thead>
    <tbody>
   @foreach ($items as $key => $item)
    <tr>
      <td class="center" style="border-bottom: 0px;border-top: 0px;">{{ $key+1 }}</td>
      <td style="border-bottom: 0px;border-top: 0px;">{{ $item['name'] ?? '' }}<br>De la {{ $data_start->format('d.m.Y') }} pana la {{ $data_end->format('d.m.Y') }}</td>
      <td style="border-bottom: 0px;border-top: 0px;" class="center">{{ $item['unit'] ?? 'BUC' }}</td>
      <td style="border-bottom: 0px;border-top: 0px;" class="right">{{ $item['quantity'] ?? '' }}</td>
      <td style="border-bottom: 0px;border-top: 0px;" class="right">{{ $item['pret_unit_no_vat'] ?? '' }}</td>
      <td style="border-bottom: 0px;border-top: 0px;" class="center">{{ $item['tva'] ?? '' }}</td>

      <td style="border-bottom: 0px;border-top: 0px;" class="right">{{ $item['total_no_vat'] ?? '' }}</td>
            <td style="border-bottom: 0px;border-top: 0px;" class="right">{{ $item['tvaValue'] ?? '' }}</td>
    </tr>
   @endforeach

      @while ($spaces > 0)
      <tr>
        <td class="center" style="border-bottom: 0px;border-top: 0px;">&nbsp;</td>
        <td style="border-bottom: 0px;border-top: 0px;"></td>
        <td style="border-bottom: 0px;border-top: 0px;" class="center">&nbsp;</td>
        <td style="border-bottom: 0px;border-top: 0px;" class="right">&nbsp;</td>
        <td style="border-bottom: 0px;border-top: 0px;" class="right">&nbsp;</td>
        <td style="border-bottom: 0px;border-top: 0px;" class="right">&nbsp;</td>
        <td style="border-bottom: 0px;border-top: 0px;" class="right">&nbsp;</td>
        <td style="border-bottom: 0px;border-top: 0px;" class="center">&nbsp;</td>
      </tr>
    @php $spaces--; @endphp
    @endwhile
       <tr>
        <td style="border-top: 0px;"></td>
        <td colspan="7"> Achitat prin: Card Bancar <br>
             WEBSITE 
             Nr. Nota {{ $nrnp }}
        </td>
      </tr>
      <tr>
        <td style="border-bottom:0" colspan="8"> Aceasta factura circula fara stampila si semnatura conform Cod Fiscal 2018, art. 319 alin 29.</td>
      </tr>
      <tr >
        <td style="padding: 0;border-top:0;margin:0;" colspan="8" cellspacing=0 cellpadding=0>
            <table style="width: 100%;margin: 0;" class="footer-table">
                <tr>
                    <td style="border-left:0; height: 50px;text-align:center;width:20%">L.S</td>
                    <td>
                        Date privind expeditia:<br>
                        Nume Delegat: <b>{{ $client['first_name'] ?? '' }} {{ $client['last_name'] ?? '' }}</b><br>
                        CNP: {{ $client['cnpcui'] ?? '' }}<br>
                        Expedierea s-a efectuat prin prezenta noastra la: {{ $data }}<br>
                        Semnaturile:.....................................
                    </td>
                    <td  style="border-right:0; padding:0;">
                        <table style="margin: 0;">
                            <tr>
                                <th style="border-left:0;border-top:0;">TOTAL</th>
                                <th style="border-top:0;">{{ $totalWithoutTax }}</th>
                                <th style="border-top:0;border-right:0">{{ $totalTax }}</th>
                            </tr>
                            <tr>
                                <td style="border-left:0;">TVA 21%</td>
                                <td>0.00</td>
                                <td style="border-right:0;">0</td>
                            </tr>
                            
                            <tr>
                                <td style="border-left:0;">TVA 11%</td>
                                 <th style="border-top:0;">{{ $totalWithoutTax }}</th>
                                <th style="border-top:0;border-right:0">{{ $totalTax }}</th>
                            </tr>
                               
                            <tr>
                                <td style="border-left:0;">Sematura de primire</td>
                                <td >
                                    <b>Total de plata</b>
                                    (Col.6+Col.7)       
                                </td>
                                <td style="text-align: right;border-left:0;border-right:0;">
                                    <b>{{ $total }}</b>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
      </tr>
    </tbody>
  </table>

 

  <p>Software dezvoltat de MasterSPA • www.masterspa.ro • versiune 20231101</p>
  <p>Page 1 of 1</p>
</body>
</html>
