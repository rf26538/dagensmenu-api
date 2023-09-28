<?php

// Derived constants from EatCommon
require_once __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../app/Shared/EatCommon/PageNames.php');

define ("USER_ADMIN",1);
define ("USER_NOT_ADMIN",0);
define ("USER_NEW_RESTAURANT",3);
define ("USER_SUPER_ADMIN", 4);
define ("USER_FOODIE", 5);


$basePath = dirname(__FILE__, 2);
$tempImagePath = sprintf("%s%s%s%s", $basePath, DIRECTORY_SEPARATOR, "imagesTemp", DIRECTORY_SEPARATOR);

define('TEMP_IMAGES_PATH', $tempImagePath);
define('MOVE_PHOTOS_IMAGES_PATH', sprintf("MovePhotos%s", DIRECTORY_SEPARATOR));

define ("MIN_IMAGE_SIZE_IN_KB", 2000);

define ('MIN_IMAGE_WIDTH',1);
define ('MIN_IMAGE_HEIGHT',1);

define('SUBOPTION_TYPE_OPTION', 1);
define('SUBOPTION_TYPE_SUBOPTION', 2);

define('STATUS_ACTIVE', 1);
define('STATUS_DELETED', 2);

define('ORDER_ONLINE_TYPE_DELIVERY', 1);
define('ORDER_ONLINE_TYPE_TAKE_AWAY', 2);

define('ORDER_ONLINE_STATUS_ADDED', 1);
define('ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT', 5);
define('ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT', 10);
define('ORDER_ONLINE_STATUS_REJECTED_BY_RESTAURANT', 15);
define('ORDER_ONLINE_STATUS_FOOD_READY', 20);


define('ORDER_ONLINE_REJECTION_REASON_CLOSING_SOON', 1);
define('ORDER_ONLINE_REJECTION_REASON_ITEM_UNAVAILABLE', 2);
define('ORDER_ONLINE_REJECTION_REASON_PROBLEM_WITH_COMMENT', 3);
define('ORDER_ONLINE_REJECTION_REASON_OTHER_REASON', 10);

define('ORDER_ONLINE_PAYMENT_TYPE_MOBILE_PAY', 1);
define('ORDER_ONLINE_PAYMENT_TYPE_CASH_CARD_TO_RESTAURANT', 2);
define('ORDER_ONLINE_PAYMENT_TYPE_CARD_PAY', 3);

define('CURRENCY_MULTIPLIER', 100);

define('TABLE_BOOKING_STATUS_CREATED', 1);
define('TABLE_BOOKING_STATUS_ACCEPTED_BY_RESTAURANT', 2);
define('TABLE_BOOKING_STATUS_REJECTED_BY_RESTAURANT', 3);
define('TABLE_BOOKING_STATUS_EXPIRED', 4);
define('TABLE_BOOKING_STATUS_CANCELLED_BY_GUEST', 5);
define('TABLE_BOOKING_STATUS_CANCELLED_BY_RESTAURANT', 6);

define('QUERY_LOGIN_ACTION_TYPE', 'at'); //Login register page action type
define('QUERY_LOGIN_ACTION_ID', 'aid'); //Login register page action id

define('LOGIN_ACTION_TYPE_TABLE_BOOKING', 1);
define('TOTAL_FOOD_PRICE_DIFFERENCE', 5);
define('MAXIMUM_ORDER_ALLOWED_FROM_ONE_IP', 5);

define('MAXIMUM_USER_IMAGE_UPLOAD_COUNT', 20);

define('FEEDBACK_STATUS_ACTIVE', 1);
define('FEEDBACK_MAXIMUM_ALLOWED_FROM_ONE_IP', 5);
define('FEEDBACK_MARK_AS_READ', 1);
define('FEEDBACK_USER_TYPE_RESTAURANT_OWNER', 1);

define('FETCH_RESTAURANT_ORDERS_FOR_LAST_HOUR', 18);
define('FETCH_RESTAURANT_FUTURE_ORDERS_FOR_LAST_HOUR', 48);

define('FETCH_RESTAURANT_LAST_NEW_ORDERS_TIME_IN_SECONDS', 3580);

define('ORDER_ONLINE_TEMPORARY_CLOSE_STATUS_ACTIVE', 1);
define('ORDER_ONLINE_TEMPORARY_CLOSE_STATUS_DELETED', 0);
define('CHANGE_PASSWORD_MAXIMUM_ALLOWED_FROM_ONE_IP', 5);

define('RESTAURANT_ORDER_ONLINE_FULL_CLOSED', 4);
define('RESTAURANT_ORDER_ONLINE_DELIVERY_CLOSED', 3);

define('ORDER_ONLINE_DELIVERY_PRICE_STATUS_DELETED', 0);
define('ORDER_ONLINE_DELIVERY_PRICES_ARE_PRESENT', 1);
define('FOOTER_LINKS_URL_TYPE_ID', 11);


define('BENCHMARK_ENABLE', 0);

define('MINIMUM_ORDER_PRICE_FOR_PHONE_VERIFICATION', 500);
define('MAXIMUM_PHONE_VERIFICATION_SMS_SEND_FROM_ONE_IP', 10);
define('MAXIMUM_PHONE_VERIFICATION_SMS_SEND_FROM_ONE_PHONE_NUMBER', 5);
define('PHONE_VERIFIED', 1);

define('MAXIMUM_DAYS_ALLOWED_TO_STATS_CHART', 60);

define('ORDER_ONLINE_PAYMENT_STATUS_STARTED', 1);
define('ORDER_ONLINE_PAYMENT_STATUS_SENT', 5);
define('ORDER_ONLINE_PAYMENT_STATUS_SUCCESSFUL', 10);
define('ORDER_ONLINE_PAYMENT_STATUS_FAILED', 15);
define('ORDER_ONLINE_PAYMENT_STATUS_FAILED_FROM_PROVIDER', 4);
define('ORDER_ONLINE_PAYMENT_STATUS_REFUNDED', 20);
define('ORDER_ONLINE_ORDER_REJECTED_BECAUSE_OF_PRICE_MISMATCH', 25);

define('ORDER_ONLINE_ORDERS_STATISTICS_DAILY', 'daily');
define('ORDER_ONLINE_ORDERS_STATISTICS_MONTHLY', 'monthly');
define('ORDER_ONLINE_ORDERS_STATISTICS_WEEKLY', 'weekly');

define('MINIMUM_REVIEWS_STAR_COUNT', 4);
define('MAXIMUM_REVIEW_ALLOWED', 11);

define('FEEDBACK_REPLY_TYPE_EMAIL', 1);
define('FEEDBACK_REPLY_TYPE_PHONE_NUMBER', 2);

define('RESTAURANT_CREATION_FIRST_TAB', 1);
define('RESTAURANT_CREATION_SECOND_TAB', 2);
define('RESTAURANT_CREATION_THIRD_TAB', 3);

define('QUICK_CHANGE_REQUEST_MAXIMUM_ALLOWED_FROM_ONE_IP', 10);
define('RESTAURANT_CHANGE_REQUEST_STATUS_ADDED', 1);
define('RESTAURANT_CHANGE_REQUEST_TYPE_MENU_CARD', 2);
define('RESTAURANT_CHANGE_REQUEST_TYPE_TIMINGS', 1);

define('USER_REGISTRATION_SOURCE_TYPE_FEEDBACK', 5);
