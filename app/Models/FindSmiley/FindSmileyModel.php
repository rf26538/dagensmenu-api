<?php

namespace App\Models\FindSmiley;
use Illuminate\Database\Eloquent\Model;

class FindSmileyModel extends Model
{
    protected $table = 'find_smiley';

    protected $fillable = [
        'comment',
        'commentBy',
        'commentOn',
        'googleDataFetchedOn', 
        'googleFetchIgnoredBy', 
        'googleFetchIgnoredOn', 
        'restaurantExistsInGoogle', 
        'navnelBnr',
        'navn1',
        'dagensmenuId',
        'isWrongSmiley',
        'isWrongSmileyFindAndUpdated',
        'isWrongSmileyIgnored'
    ];

    protected $connection = EAT_AUTOMATED_COLLECTION;
    public $timestamps = false;

    public function userDetail() 
    {
        return $this->hasOne('App\Models\User', 'uid', 'commentBy');
    }
}

?>