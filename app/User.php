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

class User extends Authenticatable implements JWTSubject, Uploader
{
    use Notifiable, SoftDeletes, HasJWT, UploadsFiles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    protected $fillable = ['name', 'email', 'role_id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];


    /**
     * Get the directory for uploads.
     *
     * @return string
     */
    public function getDirectory() : string
    {
        return 'users/'.$this->getKey();
    }

    /**
     * Get the upload attributes
     *
     * @return array
     */
    public function getUploadAttributes() : array
    {
        return $this->uploadAttributes;
    }

      
    /**
     * Hash password
     * @param $input
     */
    public function setPasswordAttribute($input)
    {
        if ($input)
            $this->attributes['password'] = app('hash')->needsRehash($input) ? Hash::make($input) : $input;
    }
    

    /**
     * Set to null if empty
     * @param $input
     */
    public function setRoleIdAttribute($input)
    {
        $this->attributes['role_id'] = $input ? $input : null;
    }
    
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
