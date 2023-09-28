<?php

namespace App\Libs\Helpers;
use App\Models\Restaurant\Advertisement;
use Auth;
use Mockery\CountValidator\Exception;

class Authentication
{
    function isUserAdmin()
    {
        if(Auth::user()->type == USER_SUPER_ADMIN || Auth::user()->type == USER_ADMIN)
        {
            return true;
        }
        return false;
    }

    function doesEntityBelongsToUser(int $entityUserId)
    {
        if(Auth::user()->type == USER_SUPER_ADMIN || Auth::user()->type == USER_ADMIN)
        {
            return true;
        }

        if($entityUserId == Auth::id())
        {
            return true;
        }
        return false;
    }

    function doesRestaurantBelongsToUser(int $restaurantId)
    {
        $advertisementIdFromDb = Advertisement::select('id', 'author_id')->where('id', $restaurantId)->where('status', STATUS_ACTIVE)->first();

        if (empty($advertisementIdFromDb)) {
            throw new Exception(sprintf("Restaurant %s does not exist", $restaurantId));
        }

        if(!$this->doesEntityBelongsToUser($advertisementIdFromDb['author_id']))
        {
            throw new Exception(sprintf("Restaurant %s does not belong to user %s", $restaurantId, Auth::id()));
        }
    }
}
