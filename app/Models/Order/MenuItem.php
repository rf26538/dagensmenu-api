<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = array('menuItemName', 'description', 'price', 'images', 'restaurantId', 'status');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "menuItemId";
    protected $hidden = ['userId', 'createdOn', 'ip', 'status'];
    public $timestamps = false;

    public function categories(){
        return $this->hasMany('App\Models\Order\MenuItemCategory', 'menuItemId');
    }

    public function options(){
        return $this->hasMany('App\Models\Order\MenuItemOption', 'menuItemId')->orderBy('optionPosition', 'asc');
    }
    public function tags(){
        return $this->hasMany('App\Models\Order\MenuItemTag', 'menuItemId')->orderBy('createdOn', 'asc');
    }
    public function sizes(){
        return $this->hasMany('App\Models\Order\MenuItemSize', 'menuItemId')->orderBy('createdOn', 'asc');
    }
}
