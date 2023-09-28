<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = array('categoryName', 'position', 'status');
    protected $table = 'categories';

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "categoryId";
    protected $hidden = ['userId', 'createdOn', 'status', 'ip'];
    public $timestamps = false;


    public function categoriesRestaurants() {
        return $this->hasMany('App\Models\Order\CategoryRestaurant', 'categoryId');
    }

    public function menuItemsCategories() {
        return $this->hasMany('App\Models\Order\MenuItemCategory', 'categoryId');
    } 
}
