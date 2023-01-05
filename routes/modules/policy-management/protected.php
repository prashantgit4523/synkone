<?php

Route::middleware(['role:Global Admin|Policy Administrator'])->group(function () {
Route::namespace('PolicyManagement')->group(function () {
    Route::prefix('policy-management')->group(function () {
        Route::name('policy-management')->group(function () {
            /****
            * Policies routes
            */
            Route::middleware(['data_scope'])->group(function () {
                /*** Campaigns routes ***/
                Route::namespace('Campaign')->group(function () {
                    Route::prefix('campaigns')->group(function () {
                        Route::name('.campaigns')->group(function () {
                            Route::get('/', 'CampaignController@index')->withoutMiddleware(['data_scope']);
                            Route::get('/get-create-data', 'CampaignController@getCampaignCreateData')->name('.get-create-data');
                            Route::get('{id}/show', 'CampaignController@show')->name('.show')->withoutMiddleware(['data_scope']);
                            Route::get('{id}/get-campaign-data', 'CampaignController@getCampaignData')->name('.get-campaign-data');
                            Route::get('{id}/export-pdf', 'CampaignController@exportPdf')->name('.export-pdf');
                            Route::get('{id}/export-awareness-pdf', 'CampaignController@exportPdf')->name('.export-awareness-pdf')->withoutMiddleware(['role:Global Admin|Policy Administrator'])->withoutMiddleware(['data_scope']);
                            Route::get('{id}/export-csv', 'CampaignController@exportCsv')->name('.export-csv');
                            Route::post('{id}/send-users-reminder', 'CampaignController@sendUsersReminder')->name('.send-users-reminder')->withoutMiddleware(['data_scope']);
                            Route::get('{id}/render-users', 'CampaignController@renderCampaignUsers')->name('.render-users');
                            Route::get('{id}/get-users-activities/{user_id}', 'CampaignController@getUserActivities')->name('.get-users-activities');
                            Route::delete('{id}/delete', 'CampaignController@delete')->name('.delete');
                            Route::post('{id}/duplicate', 'CampaignController@duplicateCampaign')->name('.duplicate');
                            Route::post('{id}/complete', 'CampaignController@completeCampaign')->name('.complete');
                            Route::post('/store', 'CampaignController@store')->name('.store');
                            Route::get('/list', 'CampaignController@campaignList')->name('.list');
                        });
                    });
                });

                Route::namespace('Policy')->group(function () {
                    Route::prefix('policies')->group(function () {
                        Route::name('.policies')->group(function () {
                            Route::get('/', 'PolicyController@index')->withoutMiddleware(['data_scope']);
                            Route::get('/list', 'PolicyController@policyList')->name('.list');
                            Route::get('/get-json-data', 'PolicyController@getJsonData')->name('.get-json-data');
                            Route::post('/store-link-policies', 'PolicyController@storeLinkPolicies')->name('.store-link-policies');
                            Route::post('/upload-policies', 'PolicyController@uploadPolicies')->name('.upload-policies');
                            Route::post('/upload-policies-file', 'PolicyController@uploadFile')->name('.upload-policies-files');
                            Route::get('{id}/download-policies', 'PolicyController@downloadPolicies')->name('.download-policies');
                            Route::delete('{id}/delete-policies', 'PolicyController@deletPolicies')->name('.delete-policies');
                            Route::post('{id}/update-policies', 'PolicyController@updatePolicies')->name('.update-policies');
                            Route::get('{id}/get-policy-data', 'PolicyController@getPolicyData')->name('.get-policy-data');
                        });
                    });
                });


                /**
                 * Users & Groups
                 */
                Route::namespace('UserAndGroup')->group(function () {
                    Route::prefix('users-and-groups')->group(function () {
                        Route::name('.users-and-groups')->group(function () {
                            Route::get('/', 'UserAndGroupController@index')->withoutMiddleware(['data_scope']);

                            // Group routes
                            Route::prefix('groups')->group(function () {
                                Route::name('.groups')->group(function () {
                                    Route::get('/list', 'UserAndGroupController@getGroupList')->name('.list');
                                    Route::post('/store', 'UserAndGroupController@addGroup')->name('.store');
                                    Route::get('{id}/edit', 'UserAndGroupController@getGroupEditData')->name('.edit');
                                    Route::post('{id}/update', 'UserAndGroupController@updateGroup')->name('.update');
                                    Route::delete('{id}/delete', 'UserAndGroupController@deleteGroup')->name('.delete');
                                    Route::get('/check-name-taken/{id?}', 'UserAndGroupController@checkGroupNameTaken')->name('.check-name-taken');
                                    Route::get('/get-json-data', 'UserAndGroupController@getGroupsJsonData')->name('.get-json-data');
                                    Route::get('/get-group-name-list', 'UserAndGroupController@getGroupNameList')->name('.get-group-name-list');
                                });
                            });

                            // LDAP USERS
                            Route::get('/get-ldap-users', 'UserController@getLdapUsers')->name('.get-ldap-users');

                            //SSO Users and Groups
                            Route::get('sync-sso-users', 'UserAndGroupController@syncSSOUsersAndGroups')->name('.sync-sso-users');
                            Route::get('import-system-users', 'UserAndGroupController@importSystemUsers')->name('.import-system-users');

                            // Users routes
                            Route::prefix('users')->group(function () {
                                Route::name('.users')->group(function () {
                                    Route::get('/download-csv-template', 'UserController@downloadCsvTemplate')->name('.download-csv-template');
                                    Route::get('/create', 'UserController@create')->name('.create');
                                    Route::post('/store', 'UserController@store')->name('.store');
                                    Route::delete('{id}/delete', 'UserController@delete')->name('.delete-user');
                                    Route::get('{id}/edit', 'UserController@edit')->name('.edit');
                                    Route::post('{id}/update', 'UserController@update')->name('.update');
                                    Route::get('{id}/disable', 'UserController@disable')->name('.disable');
                                    Route::get('{id}/activate', 'UserController@activate')->name('.activate');
                                    Route::get('/get-data', 'UserController@getUsers')->name('.get-data');
                                    Route::get('/import-user-data', 'UserController@importUserData')->name('.import-user-data');
                                    Route::get('/check-user-by-email', 'UserController@checkUserByEmail')->name('.check-user-by-email');
                                });
                            });
                        });
                    });
                });
            });
        });
    });
});
});
