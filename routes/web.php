<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$router->get('/', function () use ($router) {
    return 'pingaa';
});

$router->get('/favicon.ico', function () use ($router) {
    return 'pingaa';
});

$router->get('log-rvmequtepr', 'Log\LogReaderController@view');

$router->get('pinging', function() {
    return 'ponging';
});

$router->get('echo/{echoText}', function ($echoText) {
    return $echoText;
});

$router->get('/user/loggedInUserDetails', 'User\UserController@fetchLoggedInUserDetails');
$router->get('user/{id}', 'User\UserController@show');

$router->get('advrt/{id}', 'AdvertisementController@get');

$router->get('/restaurant-details/fetch/{id}', 'Restaurant\RestaurantController@fetch');

$router->post('/login', 'LoginController@postLogin');
$router->post('/log/noLocalStorage', 'Log\NoLocalStorageController@add');
$router->post('/loginWithToken', 'LoginController@loginWithAutoLoginToken');
$router->group(['middleware' => ["auth", "checkAdmin", "checkRestaurantBelongsToUser"]], function($router) {
    $router->post('/authenticationCheck', 'LoginController@authenticationCheck');
});
$router->post('/authentication', 'LoginController@loginCheck');

$router->group(['middleware' => ["auth", "checkAdmin"]], function($router) {
    $router->put('/advertisement/enableFutureOrder/{id}', 'AdvertisementController@enableFutureOrder');
});


$router->group(['prefix' => '/order', 'middleware' => ["auth", "checkAdmin"]], function($router) {
    $router->post('/authenticationCheck', 'LoginController@authenticationCheck');
});
$router->group(['prefix' => '/restaurant', 'middleware' => ["auth"]], function($router) {


    $router->group(['prefix' => '/restaurant-sub', 'middleware' => ["checkAdmin"]], function($router) {
        $router->get('/fetch', 'Restaurant\RestaurantSubscriptionController@fetch');
        $router->post('/advrt/trial/update', 'Restaurant\RestaurantSubscriptionController@updateAdvertisementTrialPeriod');
        $router->post('/advrt/payment/update', 'Restaurant\RestaurantSubscriptionController@updateAdvertisementPayment');
        $router->post('/tablebooking/trial/update', 'Restaurant\RestaurantSubscriptionController@updateTableBookingTrial');
        $router->post('/tablebooking/payment/update', 'Restaurant\RestaurantSubscriptionController@updateTableBookingPayment');
        $router->post('/paymentSubStatus/update', 'Restaurant\RestaurantSubscriptionController@updatePaymentSubscriptionStatus');
    });

    $router->group(['prefix' => '/restaurant-sub', 'middleware' => ["checkRestaurantBelongsToUser"] ], function($router){
        $router->put('/payment/updatePlan/{restaurantId}', 'Restaurant\RestaurantSubscriptionController@updateAdvertisementPlan');
        $router->get('/fetchPlan/{restaurantId}', 'Restaurant\RestaurantSubscriptionController@fetchAdvertisementSubscriptionPlan');
    });
    
    $router->get('/getMenuItems/{optionId}', 'Order\MenuItemController@getMenuItemNameByOptionId');

});

$router->group(['prefix' => '/restaurant', 'middleware' => ["auth", "checkRestaurantBelongsToUser"]], function($router) {
    $router->get('/fetchDueSubscriptionAmount/{restaurantId}', 'Restaurant\RestaurantSubscriptionController@fetchDueSubscriptionAmount');
});

$router->post('/restaurant/uploadImage', 'Restaurant\RestaurantImageController@uploadImage');
$router->post('/restaurant/uploadMultipleImage', 'Restaurant\RestaurantImageController@uploadMultipleImage');
$router->post('/restaurant/uploadMultiplePdf', 'Restaurant\RestaurantPdfController@uploadMultiplePdf');

$router->group(['prefix' => '/restaurant/detail', 'middleware' => ["auth"]], function($router) {
    $router->get('/fetchOrderOnlinePaymentDetails/{id}', 'Restaurant\RestaurantController@fetchOrderOnlinePaymentDetails');
    $router->get('/fetchMenuCardImages/{id}', 'Restaurant\RestaurantController@fetchMenuCardImage');
    $router->get('/fetchImages/{id}', 'Restaurant\RestaurantController@fetchRestaurantImages');
});

$router->group(['prefix' => 'userOrder', 'middleware' => ["auth"]], function($router) {
    $router->get('userDeliveryAddress', 'Order\UserDeliveryAddressController@get');
    $router->post('userDeliveryAddress', 'Order\UserDeliveryAddressController@add');
    $router->put('userDeliveryAddress/{id}', 'Order\UserDeliveryAddressController@update');
    $router->delete('userDeliveryAddress/{id}', 'Order\UserDeliveryAddressController@delete');

    $router->post('userDeliveryAddress/addTelephoneNumber', 'Order\UserDeliveryAddressController@addTelephoneNumber');
    $router->post('userDeliveryAddress/updatePhoneNumber/{addressId}/{countryCode}/{phoneNumber}', 'Order\UserDeliveryAddressController@updateOrderPhoneNumber');

    $router->post('foodOrder', 'Order\OrderController@placeOrder');
    $router->get('getStatus/{orderUniqueId}', 'Order\OrderStatusController@getStatusByUniqueId');
    $router->put('markAsAccepted/{orderUniqueId}', 'Order\OrderStatusController@markAsAccepted');
    $router->put('markFutureOrderAsAccepted/{orderUniqueId}', 'Order\OrderStatusController@markFutureOrderAsAccepted');
    $router->put('markAsRejected/{orderUniqueId}', 'Order\OrderStatusController@markAsRejected');
    $router->put('markAsReady/{orderUniqueId}', 'Order\OrderStatusController@markAsReady');
    $router->put('markAsSeenByRestaurant/{orderUniqueId}', 'Order\OrderStatusController@markAsSeenByRestaurant');

    $router->get('orderDetail/{orderUniqueId}', 'Order\OrderController@getOrderDetail');
    $router->post('stripeClientSecretKey', 'Order\OrderController@getStripeClientSecretKey');
});

$router->group(['prefix' => 'order', 'middleware' => ["auth", "checkAdmin"]], function($router) {

    $router->get('tag', 'Order\TagController@get');
    $router->get('tag/{id}', 'Order\TagController@getById');
    $router->post('tag', 'Order\TagController@add');
    $router->put('tag/{id}', 'Order\TagController@update');
    $router->delete('tag/{id}', 'Order\TagController@delete');
    $router->post('/restaurant/uploadImage', 'Restaurant\RestaurantImageController@uploadImage');

    $router->get('size', 'Order\SizeController@get');
    $router->post('menuItem/size/{sizeId}', 'Order\SizeController@update');
    $router->put('menuItem/size', 'Order\SizeController@add');

    $router->get('option/restaurantId/{restaurantId}[/includeItems/{includeItems}]', 'Order\OptionsController@getByRestaurantId');
    $router->get('option/{optionId}[/includeItems/{includeItems}]', 'Order\OptionsController@getbyid');
    $router->post('option', 'Order\OptionsController@add');
    $router->put('option/{id}', 'Order\OptionsController@update');
    $router->delete('option/{optionId}', 'Order\OptionsController@delete');

    $router->get('category', 'Order\CategoryController@get');
    $router->get('category/{categoryId}', 'Order\CategoryController@getById');
    $router->post('category', 'Order\CategoryController@add');
    $router->put('category/{categoryId}', 'Order\CategoryController@update');
    $router->delete('category/{categoryId}', 'Order\CategoryController@delete');
    $router->post('category/description/{categoryId}', 'Order\CategoryController@updateCategoryDescription');
    $router->post('category/positions', 'Order\CategoryController@updateCategoryPositions');

    $router->get('optionItem/optionId/{optionId}', 'Order\OptionItemController@getByOptionId');
    $router->get('optionItem/{optionItemId}', 'Order\OptionItemController@getById');
    $router->post('optionItem', 'Order\OptionItemController@add');
    $router->put('optionItem/{optionItemId}', 'Order\OptionItemController@update');
    $router->delete('optionItem/{optionItemId}', 'Order\OptionItemController@delete');

    $router->get('menuItem/menuItemId/{menuItemId}', 'Order\MenuItemController@getByMenuItemId');
    $router->get('menuItem/restaurantId/{restaurantId}', 'Order\MenuItemController@getByRestaurantId');
    $router->post('menuItem', 'Order\MenuItemController@add');
    $router->put('menuItem/{menuItemId}', 'Order\MenuItemController@update');
    $router->delete('menuItem/{menuItemId}', 'Order\MenuItemController@delete');
    $router->post('menuItem/positions/{categoryId}/{restaurantId}', 'Order\MenuItemController@updateMenuItemPositions');

    $router->get('latest', 'Order\OrderReportController@getRestaurantOrdersForAdmin');
});

$router->group(['prefix' => 'order', 'middleware' => ["auth", "checkRestaurantBelongsToUser"]], function($router) {
    $router->get('categoryRestaurant/{restaurantId}', 'Order\CategoryRestaurantController@get');
    $router->post('categoryRestaurant/{restaurantId}', 'Order\CategoryRestaurantController@updateOrCreate');
    $router->post('categoryRestaurant/positions/{restaurantId}', 'Order\CategoryRestaurantController@updateCategoryPositions');
    $router->get('report/restaurantOrderMonthlyReport/{restaurantId}', 'Order\OrderReportController@getRestaurantOrderMonthlyReport');
});

$router->group(['middleware' => ["auth"]], function($router) {
    $router->get('restaurant/orders/{restaurantId}', 'Restaurant\RestaurantOrdersController@fetchOrders');
    $router->get('order/history', 'Order\UserOrderController@history');
    $router->get('restaurant/details', 'Restaurant\RestaurantController@fetchRestaurantDetails');
    $router->put('restaurant/updateAutoPrintEnabledStatus/{restaurantId}/{autoPrintStatus}', 'Restaurant\RestaurantController@updateIsAutoPrintEnabledStatus');
    $router->get('order/restaurant/hasNewOrder', 'Order\OrderController@hasNewOrder');
    $router->put('restaurant/deliveryAllowedForAllPostcode/{restaurantId}', 'Restaurant\DeliveryAllowedForAllPostcodeController@save');
    $router->get('restaurant/user/details', 'Restaurant\RestaurantController@getLoggedInUserRestaurant');

    $router->group(['middleware' => ["auth", "checkAdmin"]], function($router) {
        $router->get('restaurant/promotional/{restaurantId}', 'AdvertisementController@getIsPromotionalRestaurant');
        $router->post('restaurant/promotional/{restaurantId}/{status}', 'AdvertisementController@changeIsPromotionalRestaurantStatus');
        $router->get('/failure/restaurant/fetchAll', 'FindSmiley\FindSmileyController@get');
        $router->put('/failure/restaurant/{navnelBnr}', 'FindSmiley\FindSmileyController@updateGoogleFetchInformation');
        $router->get('google/failure/restaurant/fetchAll', 'EatAutomatedCollection\GoogleRestaurantInformationController@get');
        $router->put('google/failure/restaurant/{googleRestaurantId}', 'EatAutomatedCollection\GoogleRestaurantInformationController@updateGoogleRestarantInformation');
        $router->get('/failure/organic/restaurant/fetchAll', 'EatAutomatedCollection\OrganicRestaurantInformationController@get');
        $router->put('/failure/organic/restaurant/{organicRestaurantInformationId}', 'EatAutomatedCollection\OrganicRestaurantInformationController@update');
    });
});

$router->group(['prefix' => 'tableBooking', 'middleware' => ["auth", "checkRestaurantBelongsToUser"]], function($router) {
    $router->get('/closedDates/{restaurantId}', 'TableBooking\TableBookingClosedDateController@fetchTableBookingClosedDates');
    $router->post('/closedDate/{restaurantId}', 'TableBooking\TableBookingClosedDateController@saveTableBookingClosedDate');
    $router->put('/closedDate/{tableBookingclosedDateId}', 'TableBooking\TableBookingClosedDateController@editTableBookingClosedDate');
    $router->delete('/closedDate/{tableBookingclosedDateId}', 'TableBooking\TableBookingClosedDateController@deleteTableBookingClosedDate');
    $router->get('report/restaurantTableBookingMonthlyReport/{restaurantId}', 'TableBooking\TableBookingReportController@getRestaurantTableBookingMonthlyReport');
    $router->get('fetchBookingsForRestaurant/{restaurantId}', 'TableBooking\TableBookingHistoryController@fetchBookingsForRestaurant');
});

$router->group(['prefix' => 'tableBooking', 'middleware' => ["auth"]], function($router) {
    $router->get('/reason/{restaurantId}', 'TableBooking\TableBookingClosedDateController@getReason');
});

$router->group(['prefix' => 'tableBooking'], function($router) {
    $router->post('/save', 'TableBooking\TableBookingController@saveTableBooking');
    $router->get('/timings/{restaurantId}', 'TableBooking\TableBookingTimingController@fetchTableBookingTimingsByDate');
    $router->get('/fetch/notExpired/byTelephone/{telephone}', 'TableBooking\TableBookingController@fetchByTelephone');
    $router->get('/timingWithClosedDates/{restaurantId}', 'TableBooking\TableBookingTimingController@fetchTableBookingTimingsForRestaurant');
});


$router->group(['middleware' => ["auth", "checkRestaurantBelongsToUser"]], function($router) {

    $router->group(['prefix' => 'restaurant'], function($router){
        $router->get('timings/{workingType}/{restaurantId}', 'Restaurant\RestaurantTimingController@fetchTimings');
        $router->post('timings/{workingType}/{restaurantId}', 'Restaurant\RestaurantTimingController@saveTimings');
        $router->put('/updateInformation', 'Restaurant\RestaurantController@updateRestaurantData');
    });

    $router->group(['prefix' => 'review'], function($router){
        $router->put('restaurantReply/{reviewId}', 'Review\ReviewController@saveReply');
        $router->delete('restaurantReply/delete/{reviewId}', 'Review\ReviewController@deleteReply');
    });
});

$router->get('restaurant/checkTakeawayAndDelivery/{restaurantId}', 'Restaurant\RestaurantTimingController@getTakeawayAndDeliveryStatus');
$router->get('restaurant/deliveryAllowedForAllPostcode/{restaurantId}', 'Restaurant\DeliveryAllowedForAllPostcodeController@isAllowed');

$router->group(['prefix' => 'payment', 'middleware' => ['auth']], function($router){
    $router->post('manualInvoice/save', 'Payment\ManualInvoiceController@save');
    $router->get('invoices/{restaurantId}', 'Payment\InvoiceController@fetchInvoices');
    $router->get('invoice/{paymentInvoiceId}', 'Payment\InvoiceController@fetchInvoice');
});

$router->get('translations', 'TranslationController@getAllDanishTextForMobileApp');

$router->group(['prefix' => 'stats', 'middleware' => ["auth", "checkAdmin"]], function($router) {
    $router->get('/fetchAllMenuCardStats', 'Stats\AdvertisementMenuCardStatisticsController@fetchAllMenuCardStats');
    $router->get('/fetchMenuCardStatsOnPostCode', 'Stats\AdvertisementMenuCardStatisticsController@fetchMenuCardStatsOnPostCode');
});

$router->group(['middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->get('restaurant/getClosedRestaurantOnVirkSmileyAndGoogleInformations', 'AdvertisementController@getClosedRestaurantOnVirkSmileyAndGoogleInformations');
    $router->put('restaurant/updateOurRestaurantGoogleInformationIsVerified', 'AdvertisementController@updateOurRestaurantGoogleInformationIsVerified');
    $router->put('restaurant/changeRestaurantCloseStatus/{restaurantId}/{status}', 'AdvertisementController@changeRestaurantCloseStatus');
    $router->post('restaurant/optionItemPositions/{optionId}', 'Order\OptionItemController@updateOptionItemPositions');
    $router->put('advertisement/transferRestaurantData', 'AdvertisementRedirectController@transferRestaurantData');
    $router->put('advertisement/changeStatus/{restaurantId}/{status}', 'AdvertisementStatusController@changeStatus');
    $router->get('restaurant/fetchDuplicateRestaurants', 'AdvertisementDuplicateController@fetchDuplicateRestaurants');
    $router->post('restaurant/saveDuplicateRestaurants', 'AdvertisementDuplicateController@saveDuplicateRestaurants');
});

$router->group(['prefix' => 'payment', 'middleware' => ['auth' ,'checkRestaurantBelongsToUser']], function($router){
    $router->get('stripe/createPaymentIntent/{restaurantId}', 'Payment\StripePaymentIntentController@createPaymentIntent');
    $router->post('stripe/cardRegisterSuccessfulResponse/{restaurantId}', 'Payment\StripePaymentIntentController@cardRegisterSuccessfulResponse');
    $router->post('stripe/cardRegisterFailureResponse/{restaurantId}', 'Payment\StripePaymentIntentController@cardRegisterFailureResponse');
    $router->get('stripe/fetchDetailsToAuthenticateFailedPayment/{restaurantId}', 'Payment\StripePaymentIntentController@fetchDetailsToAuthenticateFailedPayment');
    $router->post('stripe/saveSuccessAuthenticatePaymentDetails/{restaurantId}', 'Payment\StripePaymentIntentController@saveSuccessAuthenticatePaymentDetails');
    $router->post('stripe/saveFailedAuthenticatePaymentDetails/{restaurantId}', 'Payment\StripePaymentIntentController@saveFailedAuthenticatePaymentDetails');
    $router->post('stripe/addInstantPayment/{restaurantId}', 'Payment\StripePaymentIntentController@addInstantPayment');

});

$router->group(['middleware' => ['App\Http\Middleware\CorsMiddleware::class']], function($router) {
    $router->post('/feedback/save', 'Restaurant\FeedbackController@saveFeedback');
});

$router->group(['middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->get('feedback/getFeedback', 'Restaurant\FeedbackController@fetchFeedbacks');
    $router->put('feedback/delete/{feedbackId}', 'Restaurant\FeedbackController@delete');
    $router->put('feedback/markAsRead/{feedbackId}', 'Restaurant\FeedbackController@markAsRead');
    $router->put('feedback/reply/{feedbackId}', 'Restaurant\FeedbackController@updateFeedbackReply');
    $router->put('feedback/approve/{feedbackId}', 'Restaurant\FeedbackController@approveFeedback');
});

$router->group(['prefix' => '/user'], function($router) {
    $router->post('/forgotPassword', 'User\ChangePasswordRequestController@saveDetailsForChangePassword');
    $router->post('/changePassword', 'User\ChangePasswordRequestController@changePassword');
    $router->get('/name/fetch', 'User\ChangePasswordRequestController@getUserDetails');
});

$router->group(['prefix' => '/user', 'middleware' => ['auth']], function($router) {
    $router->post('/changeUserEmail', 'User\SettingController@changeEmail');
    $router->post('/deleteUserAccount', 'User\SettingController@deleteAccount');
    $router->post('/changeProfileInfo', 'User\SettingController@changeProfileInfo');
});

$router->group(['middleware' => ["auth", "checkRestaurantBelongsToUser"]], function($router) {
    $router->put('/orderOnline/temporaryClose/{restaurantId}', 'Order\OrderOnlineTemporaryCloseTimingController@save');
    $router->delete('/orderOnline/temporaryClose/{restaurantId}', 'Order\OrderOnlineTemporaryCloseTimingController@update');
});

$router->group(['prefix' => '/orderOnline', 'middleware' => ['auth']], function($router) {
    $router->group(['middleware' => ['checkAdmin']], function($router) {
        $router->get('/deliveryRejectedOrder/fetchAll', 'Log\DeliveryRejectedOrderController@fetchAll');
        $router->get('/order/stats', 'Order\OrderStatsController@getOrderStatistics');
    });

    $router->group(['middleware' => ['checkRestaurantBelongsToUser']], function($router) {
        $router->get('/order/stats/{restaurantId}', 'Order\OrderPerRestaurantStatsController@getRestaurantOrderStatistics');
    });


    $router->post('/deliveryRejectedOrder', 'Log\DeliveryRejectedOrderController@save');
    $router->get('/deliveryPrice/fetchAll/{restaurantId}', 'Order\DeliveryPriceController@fetchAll');
    $router->post('/deliveryPrice', 'Order\DeliveryPriceController@save');
    $router->put('/deliveryPrice/{deliveryPriceId}', 'Order\DeliveryPriceController@update');
    $router->delete('/deliveryPrice/{restaurantId}/{deliveryPriceId}', 'Order\DeliveryPriceController@delete');
    $router->get('/isPhoneNumberVerified/{phoneNumber}', 'Order\PhoneNumberVerificationController@getPhoneNumberVerificationStatus');
    $router->post('/phoneNumberVerification/sendCode/{countryCode}/{phoneNumber}', 'Order\PhoneNumberVerificationController@sendPhoneNumberVerificationSmsToUser');
    $router->post('/phoneNumberVerification/verifyCode/{phoneNumber}/{verificationCode}', 'Order\PhoneNumberVerificationController@verifyPhoneNumberVerificationCode');
    $router->put('/payment/markAsFailed', 'Order\OrderPaymentDetailController@markAsFailed');
    $router->put('/payment/updatePaymentRequest', 'Order\OrderPaymentDetailController@updatePaymentRequestedOn');
});

$router->group(['prefix' => 'footerLinks', 'middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->get('/getAll', 'Restaurant\FooterLinkController@getAll');
    $router->put('/save', 'Restaurant\FooterLinkController@save');
    $router->put('/update', 'Restaurant\FooterLinkController@update');
    $router->post('/delete/{footerLinkId}', 'Restaurant\FooterLinkController@delete');
    $router->get('/getSingleFooterLink/{footerLinkId}', 'Restaurant\FooterLinkController@getSingleFooterLink');
});

$router->group(['middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->get('websiteRegistrationStats/fetchAll', 'Log\WebsiteRegistrationStatController@fetchAll');
});

$router->group(['middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->get('websiteLoginStats/fetchAll', 'Log\WebsiteLoginStatController@fetchAll');
});

$router->group(['prefix' => 'payment', 'middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->post('/oneTime/save', 'Payment\OneTimePaymentController@save');
    $router->post('/oneTime/sendSms/{restaurantId}', 'Payment\OneTimePaymentController@sendSms');
});

$router->group(['prefix' => 'payment'], function($router){
    $router->post('createPaymentIntent/{uniqueId}', 'Payment\OneTimePaymentController@paymentIntent');
    $router->post('fetchRestaurantName/{uniqueId}', 'Payment\OneTimePaymentController@fetchRestaurantName');
    $router->post('paymentIntentResponse/{uniqueId}', 'Payment\OneTimePaymentController@transaction');
});

$router->group(['prefix' => 'review', 'middleware' => ['auth']], function($router){
    $router->get('/user/likes', 'Review\ReviewLikeController@getUserLikedReview');
});

$router->post('logs/client', 'Log\ClientLogController@log');
$router->post('logs/app', 'Log\AppLogController@log');

$router->group(['prefix' => 'review'], function($router){
    $router->post('save', 'Review\ReviewController@save');
    $router->put('update/{reviewUniqueId}', 'Review\ReviewController@update');
});

$router->group(['prefix' => 'restaurant', 'middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->get('/fetchWrongSmileyRestaurants', 'FindSmiley\FindSmileyController@fetchWrongSmileyRestaurants');
    $router->put('/update/wrongSmileyFoundAndIgnored/{restaurantId}', 'FindSmiley\FindSmileyController@updateWrongSmileyFoundAndMarkAsIgnored');
    $router->get('/stripeDetails', 'RestaurantStripeDetailsController@getRestaurantStripeDetails');
    $router->put('/removeStripeCard', 'RestaurantStripeDetailsController@removeStripeCard');
    $router->get('/fetchRestaurants', 'AdvertisementController@getRestaurantsByKeyword');
});

$router->group(['prefix' => 'review', 'middleware' => ['auth', 'checkAdmin']], function($router) {
    $router->put('/user/deleteIndividualReviewComment/{reviewCommentId}', 'Review\ReviewCommentController@deleteIndividualReviewComment');
});

$router->group(['prefix' => 'restaurant'], function($router){
    $router->post('/saveInformation', 'Restaurant\RestaurantController@saveRestaurantData');
    $router->get('/fetch', 'AdvertisementController@fetchRestaurantByInformation');
    $router->post('/email/verify', 'Restaurant\RestaurantController@verifyEmailForRestaurantCreation');
    $router->post('/quickChangeMenucard/saveMenucard', 'Restaurant\RestaurantChangeRequestController@saveMenucard');
    $router->post('/quickChangeTimings/saveTimings', 'Restaurant\RestaurantChangeRequestController@saveTimings');
});