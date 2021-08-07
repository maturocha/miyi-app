<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $date }}</title>
    <style type="text/css">
    @page  {
      margin: 0.55cm 0.8cm;
      size: letter; /*or width x height 150mm 50mm*/
      size: landscape
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
  margin-bottom: 6px;
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

.title_header {
  margin-bottom: 15px;
}

.title_header > span {
    
    display: inline-block;
    
}

h3, h4 {
  text-align: center;
  margin: 0;
}

table.details {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    margin: 0 auto;
    font-size: 10px;
    border-bottom: 1px solid #C1CED9;
    border-right: 1px solid #C1CED9;
}
table.details tr,
table.details th,
table.details td {
  text-align: center;
  white-space: nowrap;
  border: 1px solid #C1CED9;
  width: auto;
}

table.details th {
  padding: 5px 20px;
  color: #5D6975;
  border: 1px solid #C1CED9;
  white-space: nowrap;        
  font-weight: normal;
  width: auto;
}

table.details tfoot {
  padding: 5px 20px;
  color: #5D6975;
  border: 1px solid #C1CED9;
  white-space: nowrap;        
  font-weight: normal;
}

table.details .service,
table.details .desc {
  text-align: left;
}

table.details td {
  padding: 5px 20px;
  white-space:nowrap;
}

table.details td.grand {
  font-size: 13px;
  font-weight: bold;
  text-align: right;
  color: black;
  border-bottom: 1px solid #C1CED9;
  border-right: 1px solid #C1CED9;
  white-space:nowrap;
  
}

table.details td.dev {
  font-size: 13px;
  font-weight: bold;
  text-align: left;
  color: black;
  border-bottom: 1px solid #FFF;
  border-right: 1px solid #C1CED9;
  white-space:nowrap;
  
}

.dev {
  border-bottom: 1px solid #FFF;
}

</style>
  </head>
  <body>
    <header class="clearfix">
      <div id="logo">
        <img src="img/logo-byn.png">
      </div>
    </header>
    <main>
    <h4>{{ $title }}: {{ $title_zone }}</h4>
    <div class="title_header">
      
      <span> Fecha: {{ $date }}</span>
      <span style="float: right;">Vehículo: ________________________</span>
    </div>
      <table class="details">
        <thead>
          <tr>
            <th>Vendedor</th>
            <th>Barrio</th>
            <th>ID Boleta</th>
            <th>Cliente</th>
            <th>Saldo Ant</th>
            <th>Boleta Actual</th>
            <th>Pago</th>
            <th>Resto</th>
            <th>Observaciones</th>
            
          </tr>
        </thead>
        <tbody>
        @php
          $total = 0;
        @endphp
        @foreach ($details as $detail)
        @php
          $total += $detail->total;
        @endphp
          <tr>
            <td class="cliente">{{ $detail->user }}</td>
            <td class="barrio">{{ $detail->neighborhood }}</td>
            <td class="id">{{ $detail->id_order }}</td>
            <td class="cliente">{{ $detail->customer }}</td>
            <td class="ant"></td>
            <td class="act">${{ $detail->total }} </td>
            <td class="pago"></td>
            <td class="resto"></td>
            <td class="obs" style="width: 50%"></td>
          </tr>
            
        @endforeach
          <tr>
            <td colspan="9" style="height: 12px;"></td>
          </tr>
          <tr>
            <td colspan="9" style="height: 12px;"></td>
          </tr>
          <tr>
            <td colspan="9" style="height: 12px;"></td>
          </tr>
          <tr>
            <td colspan="9" style="height: 12px;"></td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="1"></td>
            <td colspan="1" class="grand total">COMBUSTIBLE</td>
            <td colspan="1" class="grand total"></td>
            <td colspan="1" class="grand total"></td>
            <td colspan="1" class="grand total">S-TOTAL</td>
            <td colspan="1" class="grand total">$ {{ $total }}</td>
            <td colspan="1" class=""></td>
            <td colspan="2" class="grand dev">DEVOLUCIONES</td>
          </tr>
          <tr>
            <td colspan="1"></td>
            <td colspan="1" class="grand total">OTROS</td>
            <td colspan="1" class="grand total"></td>
            <td colspan="1" class="grand total"></td>
            <td colspan="1" class="grand total">GASTOS</td>
            <td colspan="1" class="grand"></td>
            <td colspan="3" class="grand dev"></td>
            
          </tr>
          <tr>
            <td colspan="1"></td>
            <td colspan="1" class="grand total">TOTAL GASTOS</td>
            <td colspan="1" class="grand total"></td>
            <td colspan="1" class="grand total"></td>
            <td colspan="1" class="grand total">TOTAL</td>
            <td colspan="1" class="grand"></td>
            <td colspan="3" class="grand dev"></td>
            
          </tr>
        </tfoot>
      </table>
    </main>
  </body>
</html>