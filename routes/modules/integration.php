<?php

$authNamespace = '\App\Http\Controllers\Integration\Auth';
$middleware = ['role:Global Admin'];

Route::group(['namespace' => $authNamespace, 'middleware' => $middleware, 'name' => 'integration.auth.'],function (){
    Route::get('auth/{provider}/redirect', 'IntegrationAuthController@loginUrl')->name('redirect');
    Route::get('auth/{provider}/callback', 'IntegrationAuthController@loginCallback')->name('callback');
});

Route::get('integrate/service', '\App\Http\Controllers\Integration\Auth\IntegrationAuthController@integrateService')->name('integrate.service');

Route::namespace('\App\Http\Controllers\Integration')->group(function () use ($middleware){
    Route::group(['prefix' => 'integrations', 'middleware' => $middleware], function () {
        Route::get('/', 'IntegrationController@index')->name('integrations.index');
        Route::get('Error', 'IntegrationController@checkErrorCode');
        //the below route is only used for custom auth methods
        Route::post('integration/authenticate/{id}', 'IntegrationController@authenticate')->name('integrations.authenticate');
        Route::post('integration/disconnect', 'IntegrationController@disconnect')->name('integrations.disconnect');
    }); // route prefix group ends here
});
