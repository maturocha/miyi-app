<?php

namespace App\Http\Controllers\Admin;

use App\Image;
use App\Http\Controllers\Controller;
use App\Traits\Controllers\ImageTraitController;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    use ImageTraitController;
    //

    public function uploadImage(UploadedFile $image, $name, $id)
    {

        // Define folder path
        $folder = '/img/products/';
        // Make a file path where image will be stored [ folder path + file name + file extension]
        $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
        // Upload image
        $this->uploadOne($image, $folder, 'public', $name);

        $resource = new Image;
        $resource->id_product = $id;
        $resource->filename = $name;
        $resource->path = $filePath;
        $resource->save();


        // Return user back and show a flash message
        return true;
    }

    public function updateImage(UploadedFile $image, $name, $id)
    {

        // Define folder path
        $folder = '/img/products/';
        // Make a file path where image will be stored [ folder path + file name + file extension]
        $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
        // Upload image
        $this->uploadOne($image, $folder, 'public', $name);
        $resource = Image::getByProductID($id);

        if (isset($resource)) {
            $this->deleteOne($resource->path);
            $resource->filename = $name;
            $resource->path = $filePath;
            $resource->update();
        } else {
            $resource = new Image;
            $resource->id_product = $id;
            $resource->filename = $name;
            $resource->path = $filePath;
            $resource->save();

        }



        // Return user back and show a flash message
        return true;
    }
}
