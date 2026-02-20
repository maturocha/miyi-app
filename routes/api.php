<?php

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

Route::namespace('Api')->name('api.')->group(function () {
    Route::namespace('V1')->name('v1.')->prefix('v1')->group(function () {
        Route::namespace('Auth')->name('auth.')->prefix('auth')->group(function () {

            Route::group(['middleware' => 'cors'], function() {
                Route::post('identify', 'SessionsController@identify')->name('identify');
                Route::post('signin', 'SessionsController@signin')->name('signin');

                Route::middleware('auth:api')->group(function () {
                    Route::post('signout', 'SessionsController@signout')->name('signout');
                    Route::post('refresh', 'SessionsController@refresh')->name('refresh');
                    Route::post('user', 'SessionsController@user')->name('user');
                });
    
                Route::name('password.')->prefix('password')->group(function () {
                    Route::post('request', 'ForgotPasswordController@sendResetLinkEmail')->name('request');
                    Route::patch('reset/{token}', 'ResetPasswordController@reset')->name('reset');
                });

                
            });
            

          
        });

        Route::middleware('auth:api')->group(function () {

            Route::group(['middleware' => 'cors'], function() {
                Route::namespace('Settings')->prefix('settings')->name('settings.')->group(function () {
                    Route::patch('profile', 'ProfileController@update')->name('profile');

                    Route::prefix('account')->name('account.')->group(function () {
                        Route::patch('password', 'AccountController@updatePassword')->name('password');
                        Route::patch('credentials', 'AccountController@updateCredentials')->name('credentials');
                    });
                });

                Route::resource('roles', 'RolesController');
                
                Route::resource('users', 'UsersController', ['except' => ['edit', 'create']]);
                Route::prefix('users')->name('users.')->group(function () {
                    Route::patch('{user}/restore', 'UsersController@restore')->name('restore');

                    Route::patch('{user}/change-password', 'Auth\ChangePasswordController@changePassword');

                    Route::prefix('{user}/avatar')->name('avatar.')->group(function () {
                        Route::post('/', 'UsersController@storeAvatar')->name('store');
                        Route::delete('/', 'UsersController@destroyAvatar')->name('destroy');
                    });
                });

                Route::get('images/{id}', 'ImageController@showImage');

                Route::resource('orders', 'OrdersController');
                // Cambio de estado individual y masivo de pedidos
                Route::put('orders/{order}/status', 'OrdersController@updateStatus');
                Route::put('orders/bulk-status', 'OrdersController@bulkUpdateStatus');

                Route::get('orders/{id}/print',  'OrdersController@print');

                Route::resource('details', 'OrdersDetailsController');

                Route::resource('customers', 'CustomersController');

                Route::resource('providers', 'ProvidersController');

                Route::resource('zones', 'ZonesController');

                Route::resource('neighborhoods', 'NeighborhoodController');

                Route::resource('categories', 'CategoriesController');

                Route::resource('products', 'ProductsController');

                Route::resource('promotions', 'PromotionController');

                Route::resource('stock', 'StockController');

                Route::get('raises',  'SummaryController@raises');

                Route::get('statistics',  'SummaryController@statistics');

                Route::resource('notifications', 'NotificationsController', ['except' => ['edit', 'create']]);

                Route::resource('deliveries', 'DeliveriesController');
                Route::get('deliveries/{delivery}/cargas', 'DeliveriesController@cargas');
                Route::post('deliveries/{delivery}/add-pending-orders', 'DeliveriesController@addPendingOrders');
                Route::post('deliveries/{delivery}/orders', 'DeliveriesController@addOrder');
                Route::put('deliveries/{delivery}/orders/{order}', 'DeliveriesController@updateOrder');
                Route::put('deliveries/{delivery}/expenses', 'DeliveriesController@updateExpenses');
                Route::post('deliveries/{delivery}/start', 'DeliveriesController@start');
                Route::post('deliveries/{delivery}/finish', 'DeliveriesController@finish');
                Route::post('deliveries/{delivery}/close', 'DeliveriesController@close');

            });
        });
    });
});
