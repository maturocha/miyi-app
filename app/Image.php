<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{

    protected $table = 'images';
    protected $primaryKey = 'id';
    protected $fillable = [
        'filename', 'path', 'url', 'id_product'
    ];

 
    public function getRecordTitle()
    {
        return $this->filename;
    }
  
    public function getAll()
    {
        return $this->name;
    }

    public static function getByProductID($id) {
        return self::where('id_product', '=', $id)->first();
    
      }
}
