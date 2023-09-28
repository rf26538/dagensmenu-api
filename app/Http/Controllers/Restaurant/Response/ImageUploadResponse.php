<?php
namespace App\Http\Controllers\Restaurant\Response;
use App\Http\Controllers\BaseResponse;

class ImageUploadResponse extends BaseResponse
{
    public $folderName;
    public $imageWebPath;
    public $imageName;
    public $imageHeight;
    public $imageWidth;
}