<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File; 
use Illuminate\Support\Facades\Storage;

trait ImageTraitController
{
    public function uploadOne(UploadedFile $uploadedFile, $folder = null, $disk = 'public', $filename = null)
    {
        $name = !is_null($filename) ? $filename : Str::random(25);

        $file = $uploadedFile->storeAs($folder, $name.'.'.$uploadedFile->getClientOriginalExtension(), $disk);

        return $file;
    }

    public function deleteOne($filename = null)
    {
        File::delete(public_path() . $filename);
        return $filename;
    }
}