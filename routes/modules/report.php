<?php

Route::middleware(['role:Global Admin'])->group(function () {
    Route::namespace('Controls')->group(function () {
            Route::name('report.')->group(function () {
                Route::get('report', 'ReportController@view')->name('view');
                Route::get('regenrateUrl', 'ReportController@regenerateShareLink')->name('regenerateUrl');
                Route::post('update', 'ReportController@update')->name('update');
            });
    });
});