<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "tagId";
    protected $fillable = array('tagName' );
    protected $hidden = ['userId', 'createdOn', 'isDeleted', 'ip'];
    public $timestamps = false;

    public static $rules = [
    ];
}
