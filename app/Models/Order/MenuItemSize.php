<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class MenuItemSize extends Model
{
    protected $fillable = array('menuItemId', 'sizeId');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $table = 'menu_item_sizes';
    protected $hidden = ['userId', 'createdOn', 'ip', 'isDeleted'];
    public $timestamps = false;

    public function size(){
        return $this->hasOne('App\Models\Order\Size', 'sizeId', 'sizeId')->orderBy('createdOn', 'asc');
    }
}
