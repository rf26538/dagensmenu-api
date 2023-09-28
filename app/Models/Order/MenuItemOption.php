<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class MenuItemOption extends Model
{
    protected $fillable = array('menuItemId', 'optionId', 'isMultipleChoice', 'maxItemsSelectable', 'isRequired', 'addPriceToMenuItem', 'optionPosition', 'oneItemMultipleSelectionAllowed', 'maxQuantityAllowedOfOneOptionItem');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $table = 'menu_items_options';
    protected $hidden = ['userId', 'createdOn', 'ip', 'isDeleted'];
    public $timestamps = false;

    public function option(){
        return $this->hasOne('App\Models\Order\Option', 'optionId', 'optionId');
    }

    public function optionItems()
    {
        return $this->hasMany('App\Models\Order\OptionItem', 'optionId', 'optionId');
    }
}
