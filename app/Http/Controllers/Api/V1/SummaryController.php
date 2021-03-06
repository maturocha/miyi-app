<?php

namespace App\Http\Controllers\Api\V1;

use App\Zone;
use App\Order;
use App\Product;
use App\Category;
use App\Customer;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use PDF;

class SummaryController extends Controller
{


    public function export(Request $request)  
    {
        $data = [];
        $zone = $request->input('zone_id', '');
        $type_export = $request->input('type', '');
        $own_product = $request->input('own_product', '');
        $date = Carbon::parse($request->input('date', ''))->format('Y-m-d');

        switch ($type_export) {
            case 'cargas':
                $data['date'] = Carbon::parse($request->input('date', ''))->format('d/m/Y');
                $data['title'] = ($own_product) ? 'Listado de cargas propio' : 'Listado de cargas Erramuspe';

                $data['title_zone'] = Zone::find($zone)->name;
                $data['details'] = Order::getProductsByDate($date, $zone, $own_product);
                $template = 'templates.listados.cargas';
                $name = 'listado_zona-';
                $horizontal = false;
                break;

            case 'diario':
                $data['date'] =  Carbon::parse($request->input('date', ''))->addDays(1)->format('d/m/Y');
                $data['title'] = 'Planilla Diaria';
                $data['title_zone'] = Zone::find($zone)->name;
                $data['details'] = Order::getCustomerByDate($date, $zone);
                $template = 'templates.listados.diario';
                $name = 'listado_diario-';
                $horizontal = true;
                break;
            
            default:
                # code...
                break;
        }
        $pdf = PDF::loadView($template, $data);
        if ($horizontal) {
            
            $pdf->setPaper('A4', 'landscape');
        }
        
        return $pdf->stream($name .$zone.'_'.$data['date'].'.pdf');    
        //return view($template, $data);    

        return $list;
    }

    public function raises(Request $request)  : JsonResponse  {

        $user = Auth::user();

        $date = Carbon::parse($request->input('date', ''))->format('Y-m-d');
        $list = Order::getOrderByDate($date, $date, $user);

        return response()->json($list);


    }

    public function statistics(Request $request)  : JsonResponse  {

        $user = Auth::user();
        $start_date = Carbon::parse($request->input('start_date', ''))->format('Y-m-d');
        $end_date = Carbon::parse($request->input('end_date', ''))->format('Y-m-d');
        $pmq = Product::getRankQuantity([$start_date, $end_date], 20);
        $pmkg = Product::getRankKg([$start_date, $end_date], 20);
        $pms = Product::getRankPurchase([$start_date, $end_date], 20);
        $pmr = Product::getRentableProducts([$start_date, $end_date], 20);
        $cxc = Category::getComisionByCategory([$start_date, $end_date], 20);
        $cmc = Customer::getRankPurchase([$start_date, $end_date], 20);

        $data = [
            'pmq' => $pmq,
            'pmkg' => $pmkg,
            'pms' => $pms,
            'pmr' => $pmr,
            'cxc'   => $cxc,
            'cmc'   => $cmc
        ];

        return response()->json($data);


    }

    
}
