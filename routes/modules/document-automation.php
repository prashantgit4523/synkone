<?php

use Illuminate\Support\Facades\Route;

Route::post('documents/{id}/draft', 'DocumentAutomation\ControlDocumentController@draft')->name('documents.draft');
Route::post('documents/{id}/publish', 'DocumentAutomation\ControlDocumentController@publish')->name('documents.publish');
Route::get('documents/{id}/get-json-data', 'DocumentAutomation\ControlDocumentController@getJsonData')->name('documents.get-json-data');

Route::resource('documents', 'DocumentAutomation\ControlDocumentController')->only([
    'index', 'show', 'store', 'destroy'
]);

Route::post('froala-autosave', 'DocumentAutomation\ControlDocumentController@autoSave')->name('froala.autosave');
Route::post('froala-remove-autosaved-content', 'DocumentAutomation\ControlDocumentController@removeAutoSavedContent')->name('froala.remove-autosaved-content');

Route::post('frola-image-upload','DocumentAutomation\ControlDocumentController@uploadImage')->name('froala.image-upload');