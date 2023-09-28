<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
class ChangePasswordRequestModel extends Model{
    protected $table = "change_password_requests";
    public $timestamps = false;
    protected $fillable = ['changePasswordRequestId','userId', 'name', 'email', 'token', 'ip', 'createdOn', 'status', 'isEmailSent'];
    protected $connection = EAT_DB_CONNECTION_NAME;
    
}