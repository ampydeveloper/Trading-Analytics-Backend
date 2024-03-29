<?php

use Illuminate\Http\Request;

/*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
 */
/* Route::middleware('auth:api')->get('/user', function (Request $request) {
  return $request->user();
  }); */

//Route::post('create-new-item-from-admin1', 'Api\Ebay\EbayController@createEbayItemForAdmin');
//Route::post('get-cards-list-for-admin', 'Api\CardController@getCardListForAdmin');
//Route::post('get-ebay-list1/{days?}/{card_id?}', 'Api\CardController@getDashboardGraphData');
// Route::get('cronTest', 'Api\CardController@cronTest');
Route::get('cronTestt', 'Api\TestController@cronTestt');
Route::get('total-sx', 'Api\TestController@updateSxValueInTable');
Route::get('addRequestListingForAll', 'Api\TestController@addRequestListingForAll');
Route::get('cron-trender', 'Api\CardController@createTrenderCache');
Route::get('autoShiftUsers', 'Api\Auth\AuthController@autoShiftUsers');
Route::get('autoShiftUsersFromWordpress', 'Api\Auth\AuthController@autoShiftUsersFromWordpress');

Route::get('get-items-for-status', 'Api\Ebay\EbayController@getItemsForStatus');

Route::get('compareImages', 'Api\TestController@compareImages');
Route::get('compare-image-data', 'Api\CardController@compareImages');

Route::group([
    'namespace' => 'Api\Auth',
        ], function () {
    Route::get('redirect', 'AuthController@redirect');
});
Route::group([
    // Prefixed with /auth
    'namespace' => 'Api\Auth',
    'prefix' => 'auth'
        ], function () {
    Route::post('login', 'AuthController@login');
    Route::post('two-factor', 'AuthController@checkGoogleAuthCode');
    // Socialite Routes
    Route::post('login/{provider}', 'SocialLoginController@login');

    Route::post('register', 'AuthController@register');
    Route::get('register/activate/{token}', 'AuthController@activate');
    Route::get('email-confirmation/{token}', 'AuthController@emailConfirmation');

    Route::post('send-reset-link-email', 'AuthController@sendResetLinkEmail');
    Route::post('password-reset-request', 'AuthController@passwordResetRequest');
    Route::get('logout', 'AuthController@logout');

    // Requires Authorization
    Route::group([
        'middleware' => 'jwt.verify'
            ], function () {
        Route::get('getUser', 'AuthController@getUser');
        Route::patch('password/change', 'AuthController@changePassword');
    });
});

//Route::post('upload-slab-excel', 'CardController@uploadSlabForExcelImport');

Route::group([
    'namespace' => 'Api',
//    'middleware' => 'jwt.verify'
        ], function () {
    Route::get('get-all-ebay-card-ids', 'Ebay\EbayController@getItemsIds');
    Route::post('get-items-for-listing', 'Ebay\EbayController@getItemsForRelatedListing');

    Route::post('get-item-details', 'Ebay\EbayController@getItemsDetails');
    Route::get('get-advance-search-attributes', 'Ebay\EbayController@getAdanceSearchData');

    Route::post('advance-search-admin', 'Ebay\EbayController@getAdanceSearchDataForAdmin');
    Route::post('advance-search-change-status', 'Ebay\EbayController@updateAdanceSearchOptionStatus');
    Route::get('get-card-data/{id}', 'CardController@getCardDetails');
    Route::get('get-card-graph/{card_id}/{days?}', 'CardController@getCardGraphData');
    Route::get('get-single-card-graph/{card_id}/{days?}', 'CardController@getSingleCardGraphData');
    Route::get('get-card-all-graph/{card_id}', 'CardController@getCardAllGraph');
    Route::get('get-stoxticker-data', 'CardController@getStoxtickerData');
    Route::get('get-dashboard-graph/{days?}/{card_id?}', 'CardController@getDashboardGraphData');
    Route::get('get-sx-dashboard-graph/{days?}', 'StoxtickerController@getStoxtickerAllData');
    Route::get('get-edit-card/{card_id}', 'CardController@getEditCard');
    Route::post('card-edit', 'CardController@editCard');

    Route::get('get-cards-list', 'CardController@getCardList');
    Route::post('get-cards-list-for-admin', 'CardController@getCardListForAdmin');
    Route::post('fetch-card-by-item-id-for-admin', 'CardController@getFetchItemForAdmin');
    Route::post('scrap-item-for-admin', 'CardController@getItemScarpForAdmin');
    Route::post('set-featured-card', 'CardController@setFeatured');
    Route::post('set-card-status', 'CardController@setStatus');
    Route::post('set-card-sx', 'CardController@setSx');
    Route::post('card-create', 'CardController@create');
    Route::post('inactive-slab', 'CardController@inactiveSlab');
    Route::post('get-ebay-list', 'Ebay\EbayController@getItemsListForAdmin');
    Route::post('get-ebay-other-list', 'Ebay\EbayController@getItemsOtherListForAdmin');
    Route::post('get-ebay-list-for-sport', 'Ebay\EbayController@getItemsListForAdminForSport');
    Route::post('get-ebay-list-sold', 'Ebay\EbayController@getItemsListSoldAdmin');
    Route::post('get-ebay-specific-list', 'Ebay\EbayController@getSpecificListForAdmin');
    Route::post('change-ebay-status', 'Ebay\EbayController@changeEbayStatusAdmin');
    Route::post('change-card-status', 'Ebay\EbayController@changeCardStatusAdmin');
    Route::post('change-sales-status', 'Ebay\EbayController@changeSalesStatusAdmin');

    Route::post('get-ebay-list-sold-sorting', 'Ebay\EbayController@getEbayListSoldSorting');
   
    Route::post('generate-image', 'UserController@generateImageUsingBase');
    Route::post('generate-graph-image', 'UserController@generateImageUsingBase');
//    Route::post('add-see-problem', 'Ebay\EbayController@addSeeProblem');
    Route::post('get-see-problem', 'Ebay\EbayController@getSeeProblemForAdmin');
    Route::post('sales-create', 'CardController@createSales');
    Route::post('get-sales-list', 'CardController@getSalesList');
    Route::get('get-edit-sales/{sale_id}', 'CardController@getSalesEdit');
    Route::post('edit-sales-data', 'CardController@editSalesData');
    Route::get('get-edit-listing/{listing_id}', 'Ebay\EbayController@getListingEdit');
    Route::post('upload-slab-excel', 'CardController@uploadSlabForExcelImport');
    Route::post('create-new-item-from-admin', 'Ebay\EbayController@createEbayItemForAdmin');
    Route::get('get-listing-categories', 'Ebay\EbayController@getListingCategories');
    Route::post('searched-cards', 'Ebay\EbayController@searchedCardsByUserForAdmin');
    Route::post('csv-uploads', 'CardController@csvUploads');
    Route::get('delete-upload/{excel_id}', 'CardController@deleteUploads');
});

Route::group([
    'namespace' => 'Api',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('add-see-problem', 'Ebay\EbayController@addSeeProblem');
    Route::get('see-problem-reject/{id}', 'Ebay\EbayController@seeProblemReject');
    Route::get('settings', 'UserController@settings');
    Route::post('settings', 'UserController@settings');
     Route::post('save-sold-price', 'Ebay\EbayController@saveSoldPriceAdmin');
});

Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'card',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('get-card-list-using-card-id/{id}', 'Ebay\EbayController@getItemsListForCard');
    Route::post('add-request-slab', 'CardController@addRequestSlab');
    Route::post('add-request-listing', 'CardController@addRequestListing');
    Route::post('get-request-slab-list-for-admin', 'CardController@getRequestSlabListForAdmin');
    Route::post('get-request-listing-list-for-admin', 'CardController@getRequestListingListForAdmin');
    Route::post('requested-listing-action-for-admin', 'CardController@markRequestedListingForAdmin');
    Route::post('requested-slab-action-for-admin', 'CardController@markRequestedSlabForAdmin');
    Route::post('requested-slab-action-reject', 'CardController@requestedSlabReject');
    Route::get('get-single-requested-slab/{card_id}', 'CardController@getSingleRequestedSlab');
});
Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'card',
        ], function () {
    Route::post('get-card-list-using-card-id/{id}', 'Ebay\EbayController@getItemsListForCard');
});

Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'users',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('get-list-for-admin', 'UserController@getAllUsersForAdmin');
    Route::post('save-user-by-admin', 'UserController@saveUserForAdmin');
    Route::post('create-user', 'UserController@createUser');
    Route::post('update-user-by-admin/{user}/{type}', 'UserController@updateUserAttributeForAdmin');
    Route::post('change-user-password-by-admin/{user}', 'UserController@changeUSerPasswordForAdmin');
    Route::get('get-users-for-activity-logs', 'UserController@getUsersForActivityLogs');
    Route::get('get-activity-logs-for-admin', 'UserController@getActivityLogs');
    Route::get('get-activity-logs-csv-for-admin/{user}', 'UserController@exportActivityLogs');
    Route::get('get-approved-count-for-logged-user/{user}', 'UserController@getApprovedCountForLoggedUser');
});

Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'watchlist',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::get('id-list', 'WatchListController@getUserWatchListIds');
    Route::post('add', 'WatchListController@addToWatchList');
    Route::post('remove', 'WatchListController@removeToWatchList');
    Route::post('search', 'WatchListController@getEbayList');
});
//Route::group([
//    // Prefixed with /auth
//    'namespace' => 'Api',
//    'prefix' => 'watchlist',
////    'middleware' => 'jwt.verify'
//], function () {
//    Route::post('search', 'WatchListController@getEbayList');
//});

Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'search',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('get-card-list-user', 'Ebay\EbayController@getItemsListForUser');
});
Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'search',
        // 'middleware' => 'jwt.verify'
        ], function () {
//    Route::post('get-card-list', 'Ebay\EbayController@getItemsList');
//    Route::post('get-recent-auction-list', 'Ebay\EbayController@getRecentAuctionList');
    Route::post('get-internal-card-list', 'Ebay\EbayController@getInternalItemsList');
    Route::post('head-to-head', 'Ebay\EbayController@getItemsList');
//    Route::post('recent-listing', 'Ebay\EbayController@getRecentList');
//    Route::post('ending-soon-listing', 'Ebay\EbayController@getEndingSoonList');
//    Route::post('sample-my-listing', 'Ebay\EbayController@sampleMyListing');
//    Route::post('ended-listing', 'Ebay\EbayController@getEndedList');
//    Route::post('featured-listing', 'CardController@getFeaturedList');
//    Route::post('slab-listing', 'CardController@getRecentList');
//    Route::post('get-smart-keyword', 'CardController@getSmartKeyword');
    Route::post('get-smart-keyword-with-data', 'CardController@getSmartKeywordWithData');
    Route::post('popular-pick-cards', 'CardController@getPopularPickCards');
    Route::post('get-smart-ebay-keyword', 'Ebay\EbayController@getSmartKeyword');
    Route::post('get-smart-keyword-onlyname', 'CardController@getSmartKeywordOnlyName');
});
Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'search',
//    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('get-card-list', 'Ebay\EbayController@getItemsList');
    Route::post('get-recent-auction-list', 'Ebay\EbayController@getRecentAuctionList');
    Route::post('recent-listing', 'Ebay\EbayController@getRecentList');
    Route::post('ended-listing', 'Ebay\EbayController@getEndedList');
    Route::post('ending-soon-listing', 'Ebay\EbayController@getEndingSoonList');

    Route::post('featured-listing', 'CardController@getFeaturedList');
    Route::post('slab-listing', 'CardController@getRecentListRedis');
    Route::post('get-smart-keyword', 'CardController@getSmartKeyword');
});

Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'portfolio',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('add', 'MyPortfolioController@add');
    Route::post('delete', 'MyPortfolioController@delete');
    Route::post('listing', 'MyPortfolioController@getList');
    Route::post('dashboard-listing', 'MyPortfolioController@getDashboardList');
    Route::get('filters', 'MyPortfolioController@getFiltersData');
    Route::post('search', 'MyPortfolioController@search');
    Route::post('portfolio-value', 'MyPortfolioController@portfolioValue');
    Route::get('get-portfolio-graph/{days?}', 'MyPortfolioController@getPortfolioGraphData');
    Route::post('gradeCard', 'MyPortfolioController@gradeCard');
});

Route::group([
    // Prefixed with /auth
    'namespace' => 'Api',
    'prefix' => 'user',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::post('profile-data/update', 'UserController@profileUpdate');
    Route::post('notification/update', 'UserController@notificationSettingsUpdate');
    Route::get('get-social-accounts', 'UserController@getSocialAccounts');
    Route::post('add-social-accounts/{provider}', 'UserController@addSocialAccounts');
    Route::post('remove-social-accounts', 'UserController@removeSocialAccounts');
    Route::post('update-profile', 'UserController@updateProfileImage');
});
Route::group([
    'namespace' => 'Api',
    'prefix' => 'stoxticker',
        ], function () {
    Route::post('slab-search', 'StoxtickerController@slabSearch');
    Route::post('search-board', 'StoxtickerController@searchBoard');
    Route::get('single-graph-board/{days}/{board}', 'StoxtickerController@singleBoards');
    Route::get('sold-listings', 'StoxtickerController@getSoldListings');
});
Route::group([
    'namespace' => 'Api',
    'prefix' => 'stoxticker',
    'middleware' => 'jwt.verify'
        ], function () {
    Route::get('all-boards/{days}', 'StoxtickerController@allBoards');
    Route::get('featured-boards/{days}', 'StoxtickerController@featuredBoards');
    Route::post('create-board', 'StoxtickerController@createBoard');
    Route::get('board-details/{board}/{days}', 'StoxtickerController@boardDetails');
    Route::post('follow-board', 'StoxtickerController@followBoard');
    Route::post('delete-board', 'StoxtickerController@deleteBoard');
    Route::post('add-feature', 'StoxtickerController@addFeature');
});
