<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements JWTSubject, AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "user";
    protected $primaryKey = "uid";
    protected $hidden = ['ip'];
    public $timestamps = false;

    protected $fillable = [
        'email',
        'password',
        'phone',
        'age',
        'address',
        'secondary_phone',
        'website',
        'status',
        'type',
        'nick_name',
        'company_name',
        'source_type',
        'source_response',
        'gender',
        'first_name',
        'last_name',
        'name',
        'image_name',
        'image_folder',
        'created_on',
        'modified_on',
        'auto_login_hash',
        'ip'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
