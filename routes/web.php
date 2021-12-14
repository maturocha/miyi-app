<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('/{locale?}')->where(['locale' => 'en|fil'])->group(function () {
    Route::name('backoffice.')->group(function () {
        Route::get('/', function () {
            return view('__backoffice.welcome');
        })->name('welcome');
    });
});

Route::namespace('Api')->name('api.')->group(function () {
    Route::namespace('V1')->name('v1.')->group(function () {
        Route::get('comprobante/{id}',  'OrdersController@viewVoucher');
        Route::get('listado/exportar', 'SummaryController@export');

        Route::get('products/export', 'ProductsController@export');
    });
    
});
