<?php

namespace App\Http\Controllers\Restaurant;
use App\Shared\EatCommon\Amazon\AmazonS3;
use App\Models\Restaurant\FooterLinkModel;
use App\Models\Url\UrlModel;
use App\Libs\Helpers\Authentication;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseResponse;
use Exception;
use Auth;

class FooterLinkController extends Controller {
    
    function __construct(DatetimeHelper $datetimeHelpers) 
    {   
        $this->datetimeHelpers = $datetimeHelpers;
    }

    public function getAll()
    {
        $condition = [
            ['url', '!=', NULL],
            ['status', STATUS_ACTIVE]
        ];
        $result = FooterLinkModel::select(['id','caption','url'])->where($condition)->get();
        return response()->json(new BaseResponse(true, null, $result));
    }

    public function getSingleFooterLink(int $footerLinkId)
    {
        $validator = Validator::make(['footerLinkId' => $footerLinkId], ['footerLinkId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("FooterLinkController getSingleFooterLink error %s", $validator->errors()->first()));
        }
        $condition = [
            ['id', $footerLinkId],
            ['status', STATUS_ACTIVE]
        ];
        $result = FooterLinkModel::select(['id','caption', 'url', 'content', 'images'])->where($condition)->first();
        return response()->json(new BaseResponse(true, null, $result));
    }

    public function save(request $request)
    {
        $rules = array( 
            'caption' => 'required|string',
            'location' => 'string',
            'url' => 'string',
            'content' => 'string',
            'uploadedImages' => 'array'
        );
        $validator = Validator::make($request->post(), $rules);
            
        if($validator->fails())
        {
            throw new Exception(sprintf("FooterLinkController.save error. %s ", $validator->errors()->first()));
        }

        $matchUrl = $request->post('url') ?  $request->post('url') : $request->post('location');
        
        $condition = [
            ['url', $matchUrl],
            ['status', STATUS_ACTIVE]
        ];
        $secondCondition = [
            ['location', $matchUrl],
            ['status', STATUS_ACTIVE]
        ];

        if($request->post('uploadedImages'))
        {
            $saveImages = implode(", ", $request->post('uploadedImages'));    
        }
        else
        {
            $saveImages = NULL;
        }

        $result = FooterLinkModel::select(['id'])->where($condition)->orWhere($secondCondition)->first();
        if($result)
        {
            $result = false;
            $response['message'] = 'URL already exist'; 
        }
        else
        {
            $footerLinkModel = new FooterLinkModel;
            $footerLinkModel->caption = $request->post('caption');
            $footerLinkModel->location = $request->post('location');
            $footerLinkModel->content = $request->post('content');
            $footerLinkModel->url = $request->post('url');
            $footerLinkModel->images = $saveImages;
            $footerLinkModel->status = STATUS_ACTIVE;
            $footerLinkModel->save();
            $footerLinkModel->id;
            
            $typeReferenceId = $footerLinkModel->id;
            if($typeReferenceId)
            {
                if($request->post('location'))
                {
                    $url = $request->post('location');
                }
                else{
                    $url = $request->post('url');
                }

                $urlModel = new UrlModel;
                $urlModel->url = $url;
                $urlModel->redirectUrlId = NULL;
                $urlModel->redirectUrl = NULL;
                $urlModel->typeId = FOOTER_LINKS_URL_TYPE_ID;
                $urlModel->typeReferenceId = $typeReferenceId;
                $urlModel->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $urlModel->save();

                $result = true;
                $response = null;
            }
            else
            {
                $result = false;
                $response = null;
            }
        }
        
        return response()->json(new BaseResponse($result, null, $response));
    }

    public function update(request $request)
    {
        $rules = array( 
            'id' =>  'required|integer|min:1',
            'caption' => 'required|string',
            'pageUrl' => 'required|string',
            'content' => 'required|string',
            'uploadedImages' => 'array'
        );
        $validator = Validator::make($request->post(), $rules);
            
        if($validator->fails())
        {
            throw new Exception(sprintf("FooterLinkController.update error. %s ", $validator->errors()->first()));
        }
        $id = $request->post('id');
        $matchUrl = $request->post('pageUrl');
        $condition = [
            ['url', $matchUrl],
            ['typeReferenceId', $id]
        ];
        $result = UrlModel::select(['redirectUrl', 'typeReferenceId'])->where($condition)->first();
        $url = $request->post('pageUrl');
          if($request->post('uploadedImages'))
        {
            $saveImages = implode(", ", $request->post('uploadedImages'));    
        }
        else
        {
            $saveImages = NULL;
        }
        $data = [ 
            'caption'=> $request->post('caption'),
            'url' => $url,
            'content' => $request->post('content'),
            'images' => $saveImages
        ];
        if($result)
        {
            if($result->typeReferenceId != $id)
            {
                $result = false;
                $response['message'] = 'URL already exist'; 
            }
            elseif($result->typeReferenceId == $id && $result->redirectUrl == null)
            {
                FooterLinkModel::where('id', $id)->update($data);
                $result = true;
            }
            else
            {
                $condition = [
                    ['url', $matchUrl],
                    ['redirectUrl', '!=', ''],
                    ['typeReferenceId', $id]
                ];

                UrlModel::where($condition)->delete();

                FooterLinkModel::where('id', $id)->update($data);

                $data = [ 
                    'redirectUrl' => $url
                ];
    
                UrlModel::where('typeReferenceId', $id)->update($data);
    
                $urlModel = new UrlModel;
                $urlModel->url = $url;
                $urlModel->redirectUrlId = NULL;
                $urlModel->redirectUrl = NULL;
                $urlModel->typeId = FOOTER_LINKS_URL_TYPE_ID;
                $urlModel->typeReferenceId = $id;
                $urlModel->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $urlModel->save();
                
                $result = true;
            }
        }
        else
        {
            FooterLinkModel::where('id', $id)->update($data);
            $dataUrl = [ 
                'redirectUrl' => $url
            ];

            UrlModel::where('typeReferenceId', $id)->update($dataUrl);

            $urlModel = new UrlModel;
            $urlModel->url = $url;
            $urlModel->redirectUrlId = NULL;
            $urlModel->redirectUrl = NULL;
            $urlModel->typeId = FOOTER_LINKS_URL_TYPE_ID;
            $urlModel->typeReferenceId = $id;
            $urlModel->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $urlModel->save();

            $result = true;
        }
        
        $response = null;
        return response()->json(new BaseResponse($result, null, $response));
        
    }

    public function delete(int $footerLinkId)
    {
        $validatorGet = Validator::make(['footerLinkId' => $footerLinkId], ['footerLinkId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("FooterLinkController delete error. %s ", $validatorGet->errors()->first()));
            return response()->json( $response);
        } 

        FooterLinkModel::where('id', $footerLinkId)->update(array('status' => DELETED));
        return response()->json(new BaseResponse(true, null, null));
    }

   
}