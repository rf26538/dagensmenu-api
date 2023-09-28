<?php

namespace App\Models\Url;

use Illuminate\Database\Eloquent\Model;
class UrlModel extends Model{
    protected $table = "url";
    protected $primaryKey = "urlId";
    public $timestamps = false;
    protected $fillable = ['urlId','url', 'redirectUrlId', 'redirectUrl','typeId','typeReferenceId', 'createdOn'];
    protected $connection = EAT_DB_CONNECTION_NAME;
    
}