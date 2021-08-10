<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Comprobante #{{$order['id']}} - {{ $order['customer'] }} | {{ $order['date'] }}</title>
    <style type="text/css">
    @page  {
      margin: 0.15cm 1cm;
      size: letter; /*or width x height 150mm 50mm*/
    }


a {
  color: #5D6975;
  text-decoration: underline;
}

body {
  position: relative;
  margin: 0 auto; 
  color: #001028;
  background: #FFFFFF; 
  font-family: "Gill Sans Extrabold", Helvetica, sans-serif 
  font-size: 10px; 
}

header {
  padding: 10px 0;
  margin-bottom: 30px;
}

#logo {
  text-align: center;
  margin-bottom: 10px;
}

#logo img {
  width: 90px;
}

#info h3 {
  font-size: 1.4em;
}

#info .left {
  text-align: left;
  font-size: 0.8em;
  line-height: 20px;
}

#info .right {
  text-align: right;
  font-size: 0.8em;
  line-height: 18px;
}
#info span {
  color: #5D6975;
  text-align: left;
  
}

table.details {
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
  margin: 20px 0;
  font-size: 8px;
}

table.details tr:nth-child(2n-1) td {
  background: #F5F5F5;
}

table.details th,
table.details td {
  text-align: center;
  white-space: nowrap;
}

table.details th {
  padding: 5px 20px;
  color: #5D6975;
  border-bottom: 1px solid #C1CED9;
  white-space: nowrap;        
  font-weight: normal;
}

table.details .service,
table.details .desc {
  text-align: left;
}

table.details td {
  padding: 5px 20px;
  text-align: right;
}

table.details td.service,
table.details td.desc {
  vertical-align: top;
}

table.details td.unit,
table.details td.qty,
table.details td.total {
  font-size: 1.2em;
}

table.details td.grand {
  border-top: 1px solid #5D6975;
  font-size: 12px;
  font-weight: bold;
}

#notices .notice {
  color: #5D6975;
  font-size: 1.1em;
}

footer {
  color: #5D6975;
  width: 100%;
  height: 30px;
  position: absolute;
  bottom: 0;
  padding: 8px 0;
  text-align: center;
  font-size: 0.75em;
}

footer hr {
  width: 75%;
  border-top: 1px solid #C1CED9;
}
</style>
  </head>
  <body>
    <header class="clearfix">
      <div id="logo">
        <img src="img/logo-byn.png">
      </div>
    <main>
    <table border=0 cellspacing="1" cellpadding="4" width="100%" style="border: hidden" id="info">
        <tbody>
            <tr>
              <td colspan="5" class="left">
                <div><span>Fecha:</span> {{ $order['date'] }}</div>
                <div><span>Cliente:</span> {{ $order['customer'] }}</div>
                <div><span>Dirección</span> {{ $order['address'] }} - {{ strtoupper($order['neighborhood']) }}</div>
                <div><span>Zona:</span> {{ $order['zone'] }}</div>
                <div><span>Tel:</span> {{ $order['cellphone'] }}</div>
                <div><span>Vendido por:</span> {{ $order['name'] }}</div>
              </td>
             
              <td colspan="5" class="right">
                <h3>MIYI-0000{{ $order['id'] }}</h3>
                <div>Distribuidora Los Miyi</div>
                <div>Luján, Buenos Aires, AR</div>
                <div>(2323) 553513</div>
                <div>distribuidoramiyi.com.ar</div>
              </td>
            </tr>
        </tbody>
    </table>
      <table class="details">
        <thead>
          <tr>
            <th class="service">Cant</th>
            <th class="desc">Producto</th>
            <th>Precio unidad</th>
            <th>Peso</th>
            <th>Descuento</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
        @foreach ($details as $detail)
            
          <tr>
            <td class="service">{{ $detail->quantity }}</td>
            <td class="desc">{{ $detail->name }}</td>
            <td class="unit">${{ $detail->price_unit }} {{($detail->type_product == 'w') ? '/kg' : '' }}</td>
            <td class="unit">{{($detail->type_product == 'w') ? $detail->weight .' kg' : '-' }}</td>
            <td class="qty">{{ $detail->discount }} %</td>
            <td class="total">{{  number_format((float)$detail->price_final, 2) }}</td>
          </tr>
            
        @endforeach
          <tr>
            <td colspan="5">SUBTOTAL</td>
            
            <td class="total">${{ number_format((float)$order['total_bruto'], 2) }}</td>
          </tr>
          <tr>
            <td colspan="5">DTO GRAL</td>
            
            <td class="total">{{ number_format((float)$order['discount']) }}%</td>
          </tr>
          <tr>
            <td colspan="5">ENVIO</td>
            
            <td class="total">${{ number_format((float)$order['delivery_cost'], 2) }}</td>
          </tr>
          <tr>
            <td colspan="5" class="grand total">TOTAL</td>
            <td class="grand total">${{ number_format((float)$order['total'], 2) }}</td>
          </tr>
        </tbody>
      </table>
      <div id="notices">
        <div>Notas:</div>
        <div class="notice">{!! trim(nl2br($order['notes'])) !!}</div>
      </div>
    </main>
    <footer>
        Estimado cliente: Controle su mercadería al momento de recibirla, no aceptan reclamos posteriormente.
        <hr>
        Este comprobante no tiene validez fiscal.
    </footer>
  </body>
</html>