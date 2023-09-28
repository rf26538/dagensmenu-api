<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class MenuItemCategory extends Model
{
    protected $fillable = array('menuItemId', 'categoryId');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $table = 'menu_items_categories';
    protected $hidden = ['userId', 'createdOn', 'ip', 'isDeleted'];
    public $timestamps = false;

    public function category(){
        return $this->hasOne('App\Models\Order\Category', 'categoryId', 'categoryId')->where('status', STATUS_ACTIVE)->orderBy('categoryId', 'asc');
    }

    public function categoryRestaurant() {
        return $this->hasOne('App\Models\Order\CategoryRestaurant',  'categoryId', 'categoryId');
    }

    public function menuItem() {
        return $this->belongsTo('App\Models\Order\MenuItem', 'menuItemId');
    }
}
