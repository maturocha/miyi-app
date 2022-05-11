<?php

namespace App;

use App\Traits\HasJWT;
use App\Contracts\Uploader;
use App\Traits\UploadsFiles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use DB;

class Category extends Authenticatable
{
  use Notifiable, SoftDeletes, HasJWT, UploadsFiles;

  protected $table = 'categories';
  protected $primaryKey = 'id';
  protected $fillable = [
      'name', 'description', 'slug'
  ];

  public function getRecordTitle()
  {
      return $this->name;
  }

  public function getAll()
  {
      return $this->name;
  }

  public static function getAllCategories() 
  {
    return Category::select('categories.id', 'categories.name', 'categories.slug', DB::raw('count(*) as itemCount'))
            ->join('products','products.id_category','=','categories.id')
            ->groupBy('categories.id')
            ->orderBy('categories.name', 'ASC')
            ->where('categories.name','<>', 'MEDICAMENTOS')
            ->where('show_store','=', 1)
            ->where(function($queryContainer){
                $queryContainer->where(function($q){
                    $q->where('stock','>',0)
                        ->where('own_product','=',1);
                    })
                    ->orwhere(function($q){
                        $q->where('stock','<>',0)
                        ->where('own_product','=', 0);
                        });    
                })
            ->get();
  }

  public static function BySlug($slug) 
  {

    return self::select('categories.name as category','products.id as id', 
                        'products.name as name', 'products.code_miyi as code', 
                        'products.stock', 'products.price_min as price', 'products.own_product', 
                        'products.interval_quantity', 'products.bulto',
                        DB::raw("CONCAT('https://admin.distribuidoramiyi.com.ar', images.path) AS img") )
                    ->join('products','products.id_category','=','categories.id')
                    ->leftjoin('images','products.id','=','images.id_product')
                    ->where('categories.slug','=', $slug)
                    ->where('products.deleted_at','=', null)
                    ->where('show_store','=', 1)
                    ->where(function($queryContainer){
                    $queryContainer->where(function($q){
                    $q->where('stock','>',0)
                        ->where('own_product','=',1);
                    })
                    ->orwhere(function($q){
                        $q->where('stock','<>',0)
                        ->where('own_product','=', 0);
                        });    
                    })
                    ->orderBy('products.name')
                    ->get();
  }

  public static function getComisionByCategory($dates, $topList) {

    return self::join('products','products.id_category','=','categories.id')
                ->join('order_details','products.id','=','order_details.id_product')
                ->join('orders','order_details.id_order','=','orders.id')
                ->select('categories.name', DB::raw('ROUND(SUM(order_details.quantity *  (order_details.price_unit - products.price_purchase) * ((100 - order_details.discount)/100) * ((100 - orders.discount)/100) ), 2) as total'))
                ->whereBetween('orders.date', [$dates[0], $dates[1]])
                ->orderByRaw('total DESC')
                ->groupBy('categories.id')
                ->get();

  }
}
