<?php
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:Global Admin|Risk Administrator'])->group(function () {
    Route::namespace('RisksManagement')->group(function () {
        // Controllers Within The "App\Http\Controllers\Admin" Namespace

        Route::group(['prefix' => 'risks'], function () {
            Route::name('risks.')->group(function () {
                //Projects
                Route::namespace('Projects')->group(function () {
                    Route::prefix('projects')->group(function () {
                        Route::name('projects')->group(function () {
                            Route::get('/', 'ProjectController@index')->name('.index');
                            Route::get('/list', 'ProjectController@getProjectList')->name('.list');
                            Route::get('/create', 'ProjectController@create')->name('.projects-create')->withoutMiddleware(['data_scope']);
                            Route::post('/store', 'ProjectController@store')->name('.projects-store');
                            Route::get('{project}/edit', 'ProjectController@edit')->name('.projects-edit')->withoutMiddleware(['data_scope']);
                            Route::post('{project}/update', 'ProjectController@update')->name('.projects-update');
                            Route::delete('{project}/delete', 'ProjectController@delete')->name('.projects-delete');
                            Route::get('{project}/show/{tab?}', 'ProjectController@show')->name('.project-show')->withoutMiddleware(['data_scope']);
                            Route::get('/check-project-name-taken/{project?}', 'ProjectController@checkProjectNameTaken')->name('.check-project-name-taken');
                            Route::get('/project-filter-data', 'ProjectController@projectFilterData')->name('.project-filter-data');
                            Route::get('{project}/check-project-risks', 'ProjectController@checkProjectRisks')->name('.check-project-risks')->withoutMiddleware(['data_scope']);

                            // Route::get('/get-top-risks', 'DashboardController@getTopRisks')->name('.get-top-risks');
                            // Route::post('generate-pdf-report', 'DashboardController@generatePdfReport')->name('.generate-pdf-report');
                            // Route::get('/dashboard-data', 'DashboardController@getDashboardDataJson')->name('.get-dasboard-data');
                            // Route::get('/dashboard-data/datatable-data', 'DashboardController@getTopRisksJson')->name('.get-dasboard-data-datatable');
                        });
                    });
                });

                // Dashboard
                Route::namespace('Dashboard')->group(function () {
                    Route::prefix('dashboard')->group(function () {
                        Route::name('dashboard')->group(function () {
                            Route::get('/', 'DashboardController@index')->name('.index');
                            Route::get('/get-top-risks', 'DashboardController@getTopRisks')->name('.get-top-risks');
                            Route::post('generate-pdf-report', 'DashboardController@generatePdfReport')->name('.generate-pdf-report');
                            Route::get('/dashboard-data', 'DashboardController@getDashboardDataJson')->name('.get-dasboard-data');
                            Route::get('/dashboard-data/datatable-data', 'DashboardController@getTopRisksJson')->name('.get-dasboard-data-datatable');
                        });
                    });
                });

                // risk setup
                Route::get('/setup', function () {
                    return Inertia::render('risk-management/risk-setup/RiskSetup');
                })->name('setup');

                Route::namespace('RiskSetup')->group(function () {
                    Route::namespace('Wizard')->group(function () {
                        Route::prefix('wizard')->group(function () {
                            Route::name('wizard.')->group(function () {
                                Route::get('setup', 'RiskSetupController@index')->name('setup');
                                Route::get('fetch-standards', 'RiskSetupController@fetchStandards')->name('fetch-risk-standards');
                                Route::get('get-projects-by-standard',
                                    'RiskSetupController@getProjectsByStandard')->name('get-projects-by-standard');
                                Route::get('check-compliance-projects-exists',
                                    'RiskSetupController@checkComplianceProjectsExists')->name('check-compliance-projects-exists');
                                Route::get('get-risk-import-setup-page',
                                    'RiskSetupController@getRiskImportSetupPage')->name('get-risk-import-setup-page');
                                Route::post('automated-risk-setup',
                                    'RiskSetupController@automatedRiskSetup')->name('automated-risk-setup');
                                Route::get('get-risk-import-risks-list-section',
                                    'RiskSetupController@getRiskImportRisksListSection')->name('get-risk-import-risks-list-section');
                                Route::post('yourself-risks-setup',
                                    'RiskSetupController@yourselfRisksSetup')->name('yourself-risks-setup');
                            });
                        });
                    });

                    // manual setup
                    Route::namespace('Manual')->group(function () {
                        Route::prefix('manual')->group(function () {
                            Route::name('manual.')->group(function () {
                                Route::get('setup', 'RiskSetupController@index')->name('setup');
                                Route::get('download-sample',
                                    'RiskSetupController@downloadTemplateFile')->name('download-sample');
                                Route::post('risks-import', 'RiskSetupController@risksImport')->name('risks-import');
                            });
                        });
                    });
                });

                Route::namespace('RiskRegister')->group(function () {
                    Route::name('register.')->group(function () {
                        Route::get('risks-register', 'RiskRegisterController@index')->name('index');
                        Route::get('risks-register/contributors', 'RiskRegisterController@getContributorsList')->name('risks-contributors');
                        Route::get('risks-register/create', 'RiskRegisterController@riskCreate')->name('risks-create');
                        Route::post('risks-register/store', 'RiskRegisterController@riskStore')->name('risks-store');
                        Route::post('risk-register/{id}/manual-assign', 'RiskRegisterController@manualAssign')->name('risks-manual-assign');
                        Route::get('risks-register/{id}/show', 'RiskRegisterController@riskShow')->name('risks-show');
                        Route::get('risks-register/{id}/edit', 'RiskRegisterController@riskEdit')->name('risks-edit');
                        Route::post('risks-register/{id}/delete', 'RiskRegisterController@riskDelete')->name('risks-delete');
                        Route::post('risks-register/{id}/update',
                            'RiskRegisterController@riskUpdate')->name('risks-update');
                        Route::get('{id}/get-risk-mapped-compliance-controls',
                            'RiskRegisterController@getMappedRiskComplianceControls')->name('get-risk-mapped-compliance-controls');
                        Route::get('{id}/get-risk-mapping-compliance-project-controls',
                            'RiskRegisterController@getRiskMappingComplianceProjectControls')->name('get-risk-mapping-compliance-project-controls');
                        Route::post('map-risk-controls',
                            'RiskRegisterController@mapRiskControls')->name('map-risk-controls');
                        Route::get('risks-export', 'RiskRegisterController@riskExport')->name('risks-export');

                        // react routes
                        Route::get('risks-register-react', 'RiskRegisterReactController@index')->name('react.index');
                        Route::get('risks-register-react/create', 'RiskRegisterReactController@create')->name('risks-create-react');
                        Route::post('risks-register-react/{id}/update','RiskRegisterReactController@riskUpdate')->name('risks-update-react');
                        Route::get('risks-register-react/{id}/show', 'RiskRegisterReactController@riskShow')->name('risks-show-react');
                        Route::get('get-filter-options', 'RiskRegisterReactController@getFilterOptions')->name('risks-show-react-filter');
                        Route::get('risks-register-react/{id}/registered-risks', 'RiskRegisterReactController@registeredRisks')->name('registered-risks');
                    });
                }); // END
            });
        });
    });
});
