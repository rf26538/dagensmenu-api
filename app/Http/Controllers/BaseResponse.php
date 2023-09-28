<?php
namespace App\Http\Controllers;


class BaseResponse
{
    public $isSuccess;
    public $messageForUser;
    public $response;

    function __construct(bool $isSuccess, String $messageForUser = null, $response)
    {
        $this->isSuccess = $isSuccess;
        $this->messageForUser = $messageForUser;
        $this->response = $response;
    }
}