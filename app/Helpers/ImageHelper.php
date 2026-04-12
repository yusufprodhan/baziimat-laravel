<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class ImageHelper
{
    public static function handleUploadedImage($file, $path, $delete = null)
    {
        if ($file) {

            if ($delete) {
                Storage::delete($path . '/' . $delete);
            }

            $name = Str::random(4) . $file->getClientOriginalName();
            Storage::putFileAs($path, $file, $name);

            return $name;
        }
    }


    public static function uploadSummernoteImage($file, $path)
    {

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if ($file) {

            $name = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();
            Storage::putFileAs($path, $file, $name);

            return $name;
        }
    }



    public static function ItemhandleUploadedImage($file, $path, $delete = null)
    {
        if ($file) {

            if ($delete) {
                Storage::delete($path . '/' . $delete);
            }

            $photoName = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();
            $thumbnailName = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();

            Storage::putFileAs($path, $file, $photoName);


            $image = \Image::make($file)->resize(230, 230);


            $thumbnailPath = $path . '/' . $thumbnailName;
            Storage::put($thumbnailPath, (string) $image->encode());


            return [$photoName, $thumbnailName];
        }
    }

    public static function handleUpdatedUploadedImage($file, $path, $data, $delete_path, $field)
    {

        $name = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();

        Storage::putFileAs($path, $file, $name);


        if ($data[$field] != null) {
            Storage::delete($delete_path . '/' . $data[$field]);
        }

        return $name;
    }


    public static function ItemhandleUpdatedUploadedImage($file, $path, $data, $delete_path, $field)
    {

        $photoName = 'OM_' . time() .  Str::random(8) . '.' . $file->getClientOriginalExtension();
        $thumbnailName = 'OM_' . time() . Str::random(8) . '.' . $file->getClientOriginalExtension();

        $directory = public_path($path);
        // Create the directory if it doesn't exist
        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
        }
        $file->move($directory, $photoName);
        $thumbnailPath = $directory . '/' . $thumbnailName;
        Image::make($directory . '/' . $photoName)
            ->resize(230, 230, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->save($thumbnailPath);

        if (!empty($data['thumbnail'])) {
            File::delete($directory . '/' . $data['thumbnail']);
        }

        if (!empty($data[$field])) {
            File::delete($directory . '/' . $data[$field]);
        }

        return [$photoName, $thumbnailName];
    }


    public static function handleDeletedImage($data, $field, $delete_path)
    {
        if (!empty($data[$field])) {
            File::delete($delete_path . '/' . $data[$field]);
        }
    }
}
