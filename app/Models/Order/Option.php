<?php namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class Option extends Model {


    protected $fillable = array('optionName', 'suboptionType');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "optionId";
    protected $hidden = ['userId', 'createdOn', 'isDeleted', 'ip'];
    public $timestamps = false;

    public static $rules = [
        // Validation rules
    ];

    public function Items(){
        return $this->hasMany('App\Models\Order\OptionItem', 'optionId')->where('isDeleted', 0)->orderBy('optionItemPosition', 'asc');
    }
}
