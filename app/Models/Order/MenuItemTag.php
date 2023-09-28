<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class MenuItemTag extends Model
{
    protected $fillable = array('menuItemId', 'tagId');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $table = 'menu_items_tags';
    protected $hidden = ['userId', 'createdOn', 'ip', 'isDeleted'];
    public $timestamps = false;

    public function tag(){
        return $this->hasOne('App\Models\Order\Tag', 'tagId', 'tagId')->orderBy('createdOn', 'asc');
    }
}
