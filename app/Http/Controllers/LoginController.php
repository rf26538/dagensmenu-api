<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libs\Helpers\AuthenticatedUser;
use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;
use Auth;

class LoginController extends Controller
{
    /**
     * @var \Tymon\JWTAuth\JWTAuth
     */
    protected $jwt;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }

    public function postLogin(Request $request)
    {
        $this->validate($request, [
            'email'    => 'required|email|max:255',
            'password' => 'required',
        ]);

        try {

            if (! $token = $this->jwt->attempt($request->only('email', 'password'))) {
                return response()->json(['user_not_found'], 404);
            }


        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json(['token_expired'], 500);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json(['token_invalid'], 500);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json(['token_absent' => $e->getMessage()], 500);

        }

        return response()->json(compact('token'));
    }

    public function loginWithAutoLoginToken(Request $request)
    {
        $this->validate($request, [
            'token'    => 'required|string|max:32|min:32'
        ]);

        try {
            $user = User::where('auto_login_hash','=',$request->token)->first();

            if (empty($user) || !$token = $this->jwt->fromUser($user)) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json(['token_expired'], 500);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json(['token_invalid'], 500);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json(['token_absent' => $e->getMessage()], 500);

        }

        return response()->json(compact('token'));
    }

    public function authenticationCheck(Request $request)
    {
        Auth::user();

        $someJson = $request->post('someVariable');
        $arrayResult = (json_decode($someJson));
        return "user has been authenticated";
    }

    public function loginCheck()
    {
        $msg = "";
        $response = "";
        $userId = Auth::id();

        if($userId)
        {
            $msg = 'authorised';
            $response = true;
        }
        else
        {
            $msg = 'unauthorised';
            $response = false;
        }

		return response()->json(new BaseResponse(true, $msg, $response));

    }
}
