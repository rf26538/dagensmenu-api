<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class PhoneNumberVerification extends Model
{
    protected $fillable = array('userId', 'phoneNumber', 'verificationCode', 'isVerified', 'createdOn', 'ip');
    protected $table = 'phone_number_verifications';

    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $primaryKey = "phoneNumberVerificationId";
    protected $hidden = ['userId', 'createdOn', 'ip'];
    public $timestamps = false;
    const CREATED_AT = 'createdOn';

}
