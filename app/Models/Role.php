<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Role
 *
 * @package App
 * @property string $name
 * @property string $key
*/
class Role extends Model
{
    protected $fillable = ['name', 'key'];
    
    
    
}
