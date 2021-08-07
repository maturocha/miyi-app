<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $date }}</title>
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

h3, h4 {
  text-align: center;
}

table.details {
    width: auto;
    border-collapse: collapse;
    border-spacing: 0;
    margin: 0 auto;
    font-size: 7px;
}

table.details tr:nth-child(2n-1) td {
  background: #F5F5F5;
}

table.details th,
table.details td {
  text-align: left;
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
  font-size: 1.2em;
}

footer {
  color: #5D6975;
  width: 100%;
  height: 30px;
  position: absolute;
  bottom: 0;
  border-top: 1px solid #C1CED9;
  padding: 8px 0;
  text-align: center;
}
</style>
  </head>
  <body>
    <header class="clearfix">
      <div id="logo">
        <img src="img/logo-byn.png">
      </div>
    <main>
      <h3>{{ $title }}: {{ $title_zone }}</h3>
      <h4> Fecha: {{ $date }}</h4>
      @php 
        $count = 0;
        $final = count($details);
              
      @endphp
      @if ($final > 0)
          @while ($count < $final)
              @php 
                  $category_ant = $details[$count]->category;
              @endphp
              <h4>{{ $category_ant }}</h3>
              <table class="details">
                <thead>
                  <tr>
                    <th class="service">Producto</th>
                    <th class="cant">Cant.</th>
                  </tr>
                </thead>
                <tbody>
                @while ( ($count < $final) && ($category_ant === $details[$count]->category) )
                  <tr>
                    <td class="service">{{ $details[$count]->name }}</td>
                    <td class="cant">{{ $details[$count]->cant }}</td>
                  </tr>

                  @php
                    $count++;  
                  @endphp

                @endwhile
                </tbody>
          </table>
        @endwhile
      @endif
    </main>
  </body>
</html>