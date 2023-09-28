<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OptionItem extends Model
{
    protected $fillable = array('optionItemName', 'price', 'optionItemPosition', 'isDefault');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "optionItemId";
    protected $hidden = ['userId', 'createdOn', 'isDeleted', 'ip'];
    public $timestamps = false;

    public function Option(){
        return $this->belongsTo('App\Models\Order\Option', 'optionId', 'optionId');
    }

    public function sizes(){
        return $this->hasMany('App\Models\Order\OptionItemSize', 'optionItemId')->orderBy('createdOn', 'asc');
    }
}
