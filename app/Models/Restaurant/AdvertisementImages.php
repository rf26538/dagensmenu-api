<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class AdvertisementImages extends Model
{
    protected $fillable = array('adv_id', 'image_folder', 'width', 'height', 'creation_date', 'is_Primary_Image', 'image_name');
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "advertisement_images";
    protected $primaryKey = "id";
    public $timestamps = false;
}
