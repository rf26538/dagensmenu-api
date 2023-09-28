<?php

namespace App\Http\Controllers\User;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Auth;


class UserController extends Controller
{
    /**
     * Retrieve the user for the given ID.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return sprintf("some user %s", $id);
    }

    /**
     * Check If Owner Logged in
     */
    public function fetchLoggedInUserDetails()
    {
        $user = [];

        if(Auth::id())
        {
           $user = User::select(['email', 'phone','name','first_name','last_name','nick_name','type', 'countryCode'])->where('uid', Auth::id())->first();
        }

        return response()->json(new BaseResponse(true, null, $user));
    }
}
