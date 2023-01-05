<?php

Route::middleware(['role:Global Admin'])->group(function () {
    Route::namespace('AssetManagement')->group(function () {

        Route::group(['prefix' => 'asset-management'], function () {

            Route::name('asset-management.')->group(function () {
                Route::get('/', 'AssetManagementController@index')->name('index');
                Route::get('/get-json-data', 'AssetManagementController@getJsonData')->name('get-json-data');
                Route::get('/export', 'AssetManagementController@export')->name('export');
            });
        });
    });
});