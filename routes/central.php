<?php

use App\Http\Controllers\Administration\SMTPSettingsController;
use App\Http\Middleware\IpAccessHandler;
use App\Http\Controllers\Auth\SocialiteLoginController;

/*
  |--------------------------------------------------------------------------
  | Amin Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register admin routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */
Route::middleware([
    'web'
    // IpAccessHandler::class
])->group(function () {

    Route::get('/',function(){
        return inertia('central/Index');
    });
    Route::get('/register_domain','\App\Nova\Controller\RegisterDomain@showForm')->name('register.domain.show_form');

    Route::post('/register_domain','\App\Nova\Controller\RegisterDomain@submit')->name('register.domain.create');


    //integrate services
    Route::get('auth/{provider}/callback', '\App\Http\Controllers\Integration\Auth\IntegrationAuthController@loginCallback')->name('integration.auth.callback');

    //socialite login
    Route::get('auth/sso/{provider}/callback', [SocialiteLoginController::class, 'callback']);

    //smtp oauth login
    Route::get('smtp/auth/{provider}/callback', [SMTPSettingsController::class, 'loginCallback']);
});