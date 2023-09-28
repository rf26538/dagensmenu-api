<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class Working extends Model
{
    public $timestamps = false;
    protected $table = 'working';
    protected $primaryKey = "working_id";
    protected $fillable = [
        'adv_id',
        'monday',
        'monday_time',
        'tuesday',
        'tuesday_time',
        'wednesday',
        'wednesday_time',
        'thursday',
        'thursday_time',
        'friday',
        'friday_time',
        'saturday',
        'saturday_time',
        'sunday',
        'sunday_time',
        'monday_e',
        'monday_e_time',
        'tuesday_e',
        'tuesday_e_time',
        'wednesday_e',
        'wednesday_e_time',
        'thursday_e',
        'thursday_e_time',
        'friday_e',
        'friday_e_time',
        'saturday_e',
        'saturday_e_time',
        'sunday_e',
        'sunday_e_time',
        'today',
        'today_time',
        'today_e',
        'today_e_time',
        'c_today',
        'c_today_time',
        'c_today_e',
        'c_today_e_time',
        'c_monday',
        'c_monday_time',
        'c_tuesday',
        'c_tuesday_time',
        'c_wednesday',
        'c_wednesday_time',
        'c_thursday',
        'c_thursday_time',
        'c_friday',
        'c_friday_time',
        'c_saturday',
        'c_saturday_time',
        'c_sunday',
        'c_sunday_time',
        'c_monday_e',
        'c_monday_e_time',
        'c_tuesday_e',
        'c_tuesday_e_time',
        'c_wednesday_e',
        'c_wednesday_e_time',
        'c_thursday_e',
        'c_thursday_e_time',
        'c_friday_e',
        'c_friday_e_time',
        'c_saturday_e',
        'c_saturday_e_time',
        'c_sunday_e',
        'c_sunday_e_time',
        'workingt',
        'working_type'
    ];
}


?>