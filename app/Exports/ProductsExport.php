<?php

namespace App\Exports;

use App\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;

class ProductsExport implements FromQuery, WithHeadings
{
    use Exportable;


    public function headings(): array
    {

        $columns = array(
        'ID',
        'Nombre',
        'ID Categoria',
        'Codigo',
        'Stock Actual',
        'Precio de compra',
        '% May',
        '% Min',
        'Precio Min',
        'Precio May',
        'Venta por',
        'Producto propio',
        'Bulto'

        );

        return $columns;
    }

    public function query()
    {
        return Product::query()
                        ->select('products.id as id', 
                                'products.name as name', 
                                'id_category',
                                'products.code_miyi as code', 
                                'products.stock',
                                'price_purchase',
                                'percentage_may',
                                'percentage_min' ,
                                'products.price_unit', 
                                'products.price_min', 
                                'products.interval_quantity', 
                                'products.own_product', 
                                'products.bulto');
    }

}