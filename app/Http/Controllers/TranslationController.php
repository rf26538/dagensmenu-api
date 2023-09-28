<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Language\DanishForMobileApp;
use Illuminate\Http\Request;

class TranslationController extends Controller  
{
    private $danishForMobileApp;

    public function __construct(DanishForMobileApp $danishForMobileApp)
    {
        $this->danishForMobileApp = $danishForMobileApp;
    }

    public function getAllDanishTextForMobileApp(Request $request)
    {
        $response = [];

        if ($request->get('lang') == 'da')
        {
            $response = $this->danishForMobileApp->getAllTranslationMessages();
        }

        return response()->json(new BaseResponse(true, null, $response));
    }
}

?>