<?php

Route::prefix('compliance')->group(function () {
    Route::name('compliance')->group(function () {
        // TASK MONITOR ROUTES
        Route::middleware(['role:Global Admin|Compliance Administrator|Contributor'])->group(function () {
            include 'tasks.php';
        });
    });

    Route::namespace('Compliance')->group(function () {
        // Implemented Controls || Global Admin || Compliance Admin || Auditor || Contributor
        Route::middleware(['role:Global Admin|Compliance Administrator|Auditor|Contributor'])->group(function () {
            Route::namespace('ImplementedControls')->group(function () {
                Route::name('compliance')->group(function () {
                    Route::get('/implemented-controls', 'ImplementedControlsController@index')->name('.implemented-controls');
                    Route::get('/implemented-controls/data', 'ImplementedControlsController@getImplementedControlsData')->name('.implemented-controls.data');
                    Route::get('/implemented-controls/{id}/download-evidences', 'ImplementedControlsController@downloadEvidences')->name('.implemented-controls.download-evidences');
                    Route::get('/implemented-controls/{id}/{evidenceId}/download-evidences', 'ImplementedControlsController@downloadEvidences')->name('.implemented-controls.download-individual-evidences');
                    Route::get('/implemented-controls/control/evidence', 'ImplementedControlsController@getControlEvidences')->name('.implemented-controls.control.evidence');

                    // React changing
                    Route::get('/implemented-controls-data', 'ImplementedControlsController@getControlsData')->name('.implemented-controls-data');
                });
            });
        });

        Route::middleware(['role:Global Admin|Compliance Administrator|Contributor'])->group(function () {
            Route::get('/dashboard/data', 'ComplianceDashboardController@getDashboardData')->name('compliance-dashboard-data');
            Route::post('/dashboard/export-to-pdf', 'ComplianceDashboardController@exportToPDF')->name('compliance.dashboard.export-to-pdf');
            Route::get('/dashboard/calendar-data', 'ComplianceDashboardController@getCalendarTask')->name('compliance-dashboard-calendar');
            Route::get('/dashboard/get-calendar-more-popover-data', 'ComplianceDashboardController@getCalendarMorePopoverData')->name('get-calendar-more-popover-data');
            Route::get('/dashboard', 'ComplianceDashboardController@dashboard')->name('compliance-dashboard');


            /* Get compliance standards*/
            Route::get('/project-controls/{id}/get-all-implemented-controls', 'ComplianceController@getAllComplianceControls')->name('compliance.project-controls.get-all-implemented-controls');


            // get all implemented controls
            //Route::get('/project-controls/{id}/get-all-implemented-controls', 'ComplianceController@getAllComplianceControls')->name('compliance.project-controls.get-all-implemented-controls');

            // PROJECTS ROUTE
            Route::middleware(['data_scope'])->group(function () {
                Route::namespace('Project')->group(function () {
                    Route::group(['prefix' => 'projects'], function () {
                        Route::get('/view', 'ProjectController@view')->name('compliance-projects-view')->withoutMiddleware(['data_scope']);

                        Route::get('/list', 'ProjectController@getProjectList')->name('compliance.projects.list');

                        Route::get('/list-for-options', 'ProjectController@getProjectDataForOption')->name('compliance.projects.data-for-options');

                        Route::get('project/export/{id}', 'ProjectController@projectExport')->name('compliance.projects.export');

                        Route::get('/check-project-name-taken/{project?}', 'ProjectController@checkProjectNameTaken')->name('compliance.projects.check-project-name-taken');

                        // ROUTES ACCESSIBLE TO GLOBAL AND COMPLIANCE ADMIN
                        Route::middleware(['role:Global Admin|Compliance Administrator'])->group(function () {
                            Route::get('/create', 'ProjectController@create')->name('compliance-projects-create')->withoutMiddleware(['data_scope']);

                            Route::post('/store', 'ProjectController@store')->name('compliance-projects-store');

                            Route::get('{project}/edit/data', 'ProjectController@getEditData')->name('compliance-projects-edit-data');

                            Route::get('{project}/edit', 'ProjectController@edit')->name('compliance-projects-edit')->withoutMiddleware(['data_scope']);

                            Route::post('{project}/update', 'ProjectController@update')->name('compliance-projects-update');

                            Route::delete('{project}/delete', 'ProjectController@delete')->name('compliance-projects-delete');
                        });

                        /* Project controls */
                        Route::post('{project}/override-to-manual', 'ProjectControlController@overrideToManual')->name('compliance-project-override-to-manual');
                        Route::post('{project}/automate-controls', 'ProjectControlController@automate')->name('compliance-project-automate-controls');
                        Route::get('{project}/show/{tab?}', 'ProjectControlController@index')->name('compliance-project-show')->withoutMiddleware(['data_scope']);
                        Route::get('/{project}/controls', 'ProjectControlController@Controls')->name('compliance-project-controls');

                        Route::get('/{project}/controls-json', 'ProjectControlController@ControlsJson')->name('compliance-project-controls-json');
                        Route::get('/campaign-data-id', 'ProjectControlController@getCampaignDataId')->name('compliance.project.control.campaign-data-id')->withoutMiddleware(['data_scope']);

                        Route::prefix('{project}/controls')->group(function () {
                            Route::post('/updateAllJson', 'ProjectControlController@updateAllJson')->name('compliance-project-controls-update-all-json');
                            Route::get('/getProjectStatus', 'ProjectControlController@getProjectStat')->name('compliance-project-controls-stat');

                            Route::prefix('{projectControl}')->group(function () {
                                $controller = 'ProjectControlController';
                                Route::get('/show/{tabs?}', $controller . '@show')->name('compliance-project-control-show')->withoutMiddleware(['data_scope']);
                                Route::get('/merged-evidences', $controller . '@getMergedEvidences')->name('compliance.project.control.merged-evidences')->withoutMiddleware(['data_scope']);
                                Route::post('/update', $controller . '@update')->name('compliance.project.controls.update')->withoutMiddleware(['data_scope']);
                                Route::post('/evidences/upload', $controller . '@uploadEvidences')->name('compliance-project-control-evidences-upload')->withoutMiddleware(['data_scope']);
                                Route::post('/evidences/upload/additional', $controller . '@uploadAdditionalEvidences')->name('compliance-project-control-additional-evidences-upload')->withoutMiddleware(['data_scope']);

                                Route::get('/evidences', $controller . '@evidences')->name('compliance-project-control-evidences')->withoutMiddleware(['data_scope']);

                                Route::delete('/evidence/{id}/delete', $controller . '@deleteEvidences')->name('compliance-project-control-evidences-delete')->withoutMiddleware(['data_scope']);

                                Route::post('/comments', $controller . '@storeComment')->name('compliance.project-controls-comments')->withoutMiddleware(['data_scope']);

                                Route::post('/review-submit', $controller . '@submitForReview')->name('compliance.project-controls-review-submit')->withoutMiddleware(['data_scope']);
                                Route::post('/review-approve', $controller . '@controlReviewApprove')->name('compliance.project-controls-review-approve')->withoutMiddleware(['data_scope']);
                                Route::post('/review-reject', $controller . '@controlReviewReject')->name('compliance.project-controls-review-reject')->withoutMiddleware(['data_scope']);
                                Route::post('/request-evidence-amendment', $controller . '@requestEvidenceAmendment')->name('compliance.project-controls-request-amendment');
                                Route::post('/amendment-request-decision', $controller . '@amendRequestDecision')->name('compliance.project-controls-amend-request-decision');

                                Route::get('/remove-linked-controls', $controller . '@removeLinkedControls')->name('project-remove-control-linked-controls')->withoutMiddleware(['data_scope']);

                                Route::post('/update-project-control-automation', $controller . '@updateProjectControlAutomation')->name('update-project-control-automation')->withoutMiddleware(['data_scope']);

                            });
                        });
                    });
                });
            });
        });

        Route::group(['middleware' => ['role:Global Admin|Compliance Administrator|Auditor|Contributor'], 'prefix' => 'projects/{project}/controls/{projectControl}', 'namespace' => 'Project'],function (){  
            $controller = 'ProjectControlController';                             
            Route::get('/evidences/{id}/download/{linkedToControlId?}', $controller . '@downloadEvidences')->name('compliance-project-control-evidences-download')->withoutMiddleware(['data_scope']);
            Route::get('linked-controls/{linkedToControlId}/get-json-data', $controller . '@linkedControlEvidences')->name('project-control-linked-controls-evidences')->withoutMiddleware(['data_scope']);
            Route::get('linked-controls-view/{linkedToControlId}', $controller . '@linkedControlEvidencesView')->name('project-control-linked-controls-evidences-view')->withoutMiddleware(['data_scope']);
        });
    });
});

/*
* Compliance Template
*/
Route::namespace('Compliance\Standard')->group(function () {
    Route::group(['prefix' => 'administration', 'middleware' => ['role:Global Admin|Compliance Administrator']], function () {
        Route::group(['prefix' => 'compliance-template'], function () {
            Route::get('/view', [
                'uses' => 'ComplianceTemplateReactController@view',
                // 'uses' => 'ComplianceTemplateController@view',
                'as' => 'compliance-template-view',
            ]);

            Route::get('/get-json-data', [
                'uses' => 'ComplianceTemplateReactController@getJsonData',
                // 'uses' => 'ComplianceTemplateController@getJsonData',
                'as' => 'compliance-template-get-json-data',
            ]);

            Route::get('/list', 'ComplianceTemplateReactController@getStandardList')->name('compliance-standard-list');

            Route::get('/create', [
                'uses' => 'ComplianceTemplateReactController@create',
                // 'uses' => 'ComplianceTemplateController@create',
                'as' => 'compliance-template-create',
            ]);

            Route::get('/dublicate/{standard}/', 'ComplianceTemplateReactController@dublicate')->name('compliance-template-dublicate');

            Route::post('/store', [
                'uses' => 'ComplianceTemplateReactController@store',
                'as' => 'compliance-template-store',
            ]);

            Route::get('/edit/{standard}', [
                'uses' => 'ComplianceTemplateReactController@edit',
                // 'uses' => 'ComplianceTemplateController@edit',
                'as' => 'compliance-template-edit',
            ]);

            Route::post('/update/{standard}', [
                'uses' => 'ComplianceTemplateReactController@update',
                'as' => 'compliance-template-update',
            ]);

            Route::delete('{standard}/delete', [
                'uses' => 'ComplianceTemplateReactController@delete',
                'as' => 'compliance-template-delete',
            ]);

            Route::group(['prefix' => '{standard}/controls'], function () {
                Route::get('/view', [
                    'uses' => 'TemplateControlsReactController@view',
                    'as' => 'compliance-template-view-controls',
                ]);

                Route::get('/create', [
                    'uses' => 'TemplateControlsReactController@create',
                    'as' => 'compliance-template-create-controls',
                ]);

                Route::post('/store', [
                    'uses' => 'TemplateControlsReactController@store',
                    'as' => 'compliance-template-store-controls',
                ]);

                Route::get('/edit/{control}', [
                    'uses' => 'TemplateControlsReactController@edit',
                    'as' => 'compliance-template-edit-controls',
                ]);

                Route::post('/update/{control}', [
                    'uses' => 'TemplateControlsReactController@update',
                    'as' => 'compliance-template-update-controls',
                ]);

                Route::delete('{control}/delete', [
                    'uses' => 'TemplateControlsReactController@delete',
                    'as' => 'compliance-template-delete-controls',
                ]);

                Route::get('/upload/csv', [
                    'uses' => 'TemplateControlsReactController@uploadCsv',
                    'as' => 'compliance-template-upload-csv-controls',
                ]);

                Route::get('/download/template', [
                    'uses' => 'TemplateControlsReactController@downloadTemplate',
                    'as' => 'compliance-template-download-template-controls',
                ]);

                Route::post('/upload/csv/store', [
                    'uses' => 'TemplateControlsReactController@uploadCsvStore',
                    'as' => 'compliance-template-upload-csv-store-controls',
                ]);

                Route::get('/get-json-data', [
                    'uses' => 'TemplateControlsReactController@getJsonData',
                    'as' => 'compliance-template-controls-get-json-data',
                ]);
            });
        });
    });
});
