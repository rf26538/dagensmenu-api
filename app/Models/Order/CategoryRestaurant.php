<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class CategoryRestaurant extends Model {

    protected $fillable = ['categoryId', 'categoryDescription', 'restaurantId', 'position', 'userId', 'ip', 'createdOn'];
    protected $table = 'categories_restaurants';
    protected $hidden = ['ip', 'createdOn'];
    public $timestamps = false;
    public $primaryKey = 'categoryRestaurantId';

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
}
?>