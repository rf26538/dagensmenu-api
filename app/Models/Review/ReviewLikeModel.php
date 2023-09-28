<?php

namespace App\Models\Review;
use Illuminate\Database\Eloquent\Model;

class ReviewLikeModel extends Model 
{
    protected $fillable = array('reviewId', 'status', 'userId', 'timestamp', 'ip');
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "review_likes";
    protected $primaryKey = "reviewLikeId";
    public $timestamps = false;

}
