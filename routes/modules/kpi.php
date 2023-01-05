<?php

Route::middleware(['role:Global Admin'])->group(function () {
    Route::namespace('Controls')->group(function () {
            Route::name('kpi.')->group(function () {
                Route::get('/kpi-dashboard', 'KpiDashboardController@index')->name('index');
                Route::get('/kpi-dashboard-data', 'KpiDashboardController@getDashboardData')->name('index-dashboard-data');
                Route::post('/kpi-dashboard-data-export', 'KpiDashboardController@generatePdfReport')->name('index-dashboard-data-export');
                Route::get('/kpi/standard-filter-data', 'KpiDashboardController@getStandardsFilterData')->name('standard-filter-data');
                Route::post('/submit-target','KpiDashboardController@submitTarget')->name('target.submit');
            });
    });
});