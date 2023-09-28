<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OptionItemSize extends Model
{
    protected $fillable = array('optionItemId', 'sizeId');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $table = 'option_items_sizes';
    protected $hidden = ['userId', 'createdOn', 'ip', 'isDeleted'];
    public $timestamps = false;

    public function size(){
        return $this->hasOne('App\Models\Order\Size', 'sizeId', 'sizeId')->orderBy('createdOn', 'asc');
    }
}
