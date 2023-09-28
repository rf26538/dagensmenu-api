<?php

namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Amazon\AmazonS3;
use App\Shared\EatCommon\Image\ImageHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Restaurant\Advertisement;
use Exception;

class RestaurantPdfController extends Controller
{
    private $amazonS3;
    private $imageHandler;

    function __construct(AmazonS3 $amazonS3, ImageHandler $imageHandler)
    {
        $this->amazonS3 = $amazonS3;
        $this->imageHandler = $imageHandler;
    }

    public function uploadMultiplePdf(Request $request)
    {
        try
        {
            $data = $request->all();
            $inputFileFieldName = $request->post("fileControlName");
            $files = $request->file($inputFileFieldName);
            $restaurantId = $request->post("restaurantId");
            $imageFolder = $request->post("imageFolderName");
            $isSuccess = false;
            $response = [];

            $validator = Validator::make(
                $data, [
                    sprintf("%s.*", $inputFileFieldName) => 'required|mimes:pdf',
                ],[
                    sprintf("%s.*.mimes", $inputFileFieldName) => "Only pdf are allowed"
                ]
            );

            if($validator->fails())
            {
                Log::critical(sprintf("Upload pdf file validation failed. %s ", $validator->errors()->first()));
                $response['error'] = $validator->errors()->first();
            }
            else
            {
                if(!empty($restaurantId))
                {
                    $advertisementDetails = Advertisement::select('imageFolder', 'status')->where('id', $restaurantId)->first();

                    if(!empty($advertisementDetails))
                    {
                        if($advertisementDetails['status'] == DELETED)
                        {
                            return response()->json(new BaseResponse(true, null, true));
                        }
                        else
                        {
                            $folderName = $advertisementDetails['imageFolder'];
                        }
                    }
                    else
                    {
                        throw new Exception(sprintf("Restaurant does not exist. %s ", $restaurantId));
                    }
                }
                elseif(!empty($imageFolder))
                {
                    $folderName = $imageFolder;
                }
                else
                {
                    $folderName = $this->imageHandler->CreateNewFolderForAdvertisementImage();
                }

                if(!empty($files))
                {
                    
                    foreach($files as $file)
                    {
                        if(method_exists($file, 'getClientOriginalName'))
                        {
                            $fileName = preg_replace('/[^a-zA-Z0-9_ -]/s','', str_replace(".pdf", "", $file->getClientOriginalName()));
                            $fileNameWithExtension = sprintf("%s.pdf",$fileName); 
                            $fileNameOnAmazon = sprintf("%s.pdf", str_replace ( '.' , '', uniqid('', true)));
                            $amazonWebPath = $this->amazonS3->UploadFile($file->getPathName(), env('AMAZON_IMAGES_BUCKET'), $folderName, env('AMAZON_ACL'), $fileNameOnAmazon);
                            $response['menucardPdfFiles'][] = ['pdfFileName' => $fileNameOnAmazon, 'pdfOriginalName' => $fileNameWithExtension, 'pdfWebPath' => $amazonWebPath, 'imageFolderName' => $folderName];
                        }
                        else
                        {               
                           Log::critical(sprintf("Error found in RestaurantPdfController@uploadMultiplePdf Message is restaurant PDF upload failed for restaurantId %s", $restaurantId));
                        }
                    }
                    $isSuccess = true;
                }
            }

        }
        catch(Exception $e)
        {
            Log::critical($e->getMessage());

        }

        return response()->json(new BaseResponse($isSuccess, true, $response));
    }
}