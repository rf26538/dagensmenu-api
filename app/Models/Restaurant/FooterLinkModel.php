<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;
class FooterLinkModel extends Model{
    protected $table = "footer_links";
    protected $primaryKey = "id";
    public $timestamps = false;
    protected $fillable = ['id','caption', 'location', 'content','url','images', 'status'];
    protected $connection = EAT_DB_CONNECTION_NAME;
    
}