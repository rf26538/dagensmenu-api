<?php

namespace App\Models\Review;
use Illuminate\Database\Eloquent\Model;

class ReviewCommentModel extends Model 
{
    protected $fillable = array('reviewId', 'comment', 'userId', 'timestamp', 'status', 'ip', 'modifiedOn');
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "review_comments";
    protected $primaryKey = "reviewCommentId";
    public $timestamps = false;
}
