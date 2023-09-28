<?php

namespace App\Models\Review;
use Illuminate\Database\Eloquent\Model;

class ReviewModel extends Model 
{
    protected $fillable = array('reviewReply', 'replyTimeStamp', 'adId', 'reviewImages', 'redirectReviewId', 'typeReferenceId', 'typeId');
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "reviews";
    protected $primaryKey = "id";
    public $timestamps = false;

    public function reviewComments() {
        return $this->hasMany('App\Models\Review\ReviewCommentModel', 'reviewId');
    }

    public function reviewLikes() {
        return $this->hasMany('App\Models\Review\ReviewLikeModel', 'reviewId');
    }
}
