<?php

namespace App\Exceptions;

use Throwable;
use Inertia\Inertia;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function handleException(Throwable $exception)
    {
        // MAIL SENDING EXCEPTIONS\
        if ($exception instanceof \Swift_TransportException) {
            if (request()->ajax() && !request()->hasHeader('x-inertia')) {
                return response()->json(['exception' => 'Failed to process request. Please check SMTP authentication connection.']);
            } else {
                return redirect()->back()->with('exception', 'Failed to process request. Please check SMTP authentication connection.');
            }
        }

        if ($exception instanceof \Illuminate\Session\TokenMismatchException) {
            if (request()->ajax()) {
                return response()->json(['exception' => 'Sorry, your Page has been expired. Please try again']);
            } else {
                return redirect()->back()->with(['exception' => 'Sorry, your Page has been expired. Please try again']);
            }
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            if (request()->ajax()) {
                return response()->json(['exception' => $exception->getMessage()]);
            } else {
                return redirect()->back()->with(['exception' => $exception->getMessage()]);
            }
        }

        if ($exception instanceof AuthenticationException) {
            if (request()->expectsJson()) {
                return response()->json(['exception' => 'Unauthenticated.'], 401);
            }

            $guard = Arr::get($exception->guards(), 0);

            switch ($guard) {
                case 'admin':
                    $login = 'login';
                    break;

                default:
                    $login = 'login';
                    break;
            }

            request()->session()->flash('session_expired', 'Your session has expired. Please try again later');

            return redirect()->guest(route($login));
        }

        if ($exception instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
            if (request()->ajax() && !request()->hasHeader('x-inertia')) {
                return response()->json(['exception' => "The Upload Max Filesize is " . ini_get("upload_max_filesize") . "B on the server. Please increase Upload Max Filesize limit on the server."], 413);
            } else {
                return redirect()->back()->with(['exception' => "The Upload Max Filesize is " . ini_get("upload_max_filesize") . "B on the server. Please increase Upload Max Filesize limit on the server."]);
            }
        }

        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            if (request()->ajax()) {
                return response()->json(['exception' => 'Record not found.']);
            } else {
                return redirect()->back()->with(['exception' => 'Record not found.']);
            }
        }

        if ($exception instanceof \Illuminate\Contracts\Filesystem\FileNotFoundException) {
            if (request()->ajax()) {
                return response()->json(['exception' => 'File not found.']);
            } else {
                return redirect()->back()->with(['exception' => 'File not found.']);
            }
        }

        // SAML EXCEPTIONS
        if ($exception instanceof \OneLogin\Saml2\Error) {
            if (request()->ajax() && !request()->hasHeader('x-inertia')) {
                return response()->json(['exception' => 'Invalid SSO or SLO URL for SAML. Please enter valid URLs.']);
            } else {
                return redirect()->back()->with('exception', 'Invalid SSO or SLO URL for SAML. Please enter valid URLs.');
            }
        }
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
        });

        $this->renderable(function (Throwable $e) {
            return $this->handleException($e);
        });
    }

    /**
     * Prepare exception for rendering.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);
        $isAjaxRequest = $request->ajax();
        $defaultMessage = 'Something went wrong on the server.';

        if($e instanceof \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException){
            Log::info('Tried to access non existent tenant from url :'.$request->url());
            $view = View::make('errors.503',['exception' => $e]);
            return response($view);
        }

        if (env('APP_DEBUG') === true && !$e instanceof ValidationException) {
            return $response;
        }

        if ($e instanceof \Illuminate\Session\TokenMismatchException) {
            return back()->with(['error' => 'Sorry, your session has expired. Please try again!']);
        }

        if (!$e instanceof ValidationException && !$e instanceof AuthenticationException) {
            if(method_exists($e,'getStatusCode')){
                if ($e->getStatusCode() === 419) {
                    return back()->with([
                        'message' => 'The page expired, please try again.',
                    ]);
                }
                if ($isAjaxRequest) {
                    return Inertia::render('errors/ErrorPage', ['status' => $e->getStatusCode(), 'prev_url' => url()->previous()])
                        ->toResponse($request)
                        ->setStatusCode($e->getStatusCode());
                } else {
                    switch ($e->getStatusCode()) {
                        case '500':
                            $message=$defaultMessage;
                            break;
                        case '503':
                            $message='Under Maintainance. Will be back soon.';
                            break;
                        case '404':
                            $message='Sorry, the page you are looking for could not be found.';
                            break;
                        case '403':
                            $message='Sorry, you are forbidden from accessing this page.';
                            break;
                        default :
                            $message=$defaultMessage;
                            break;
                    }
                    return response()->make(view('errors.error', ['message'=>$message,'code'=>$e->getStatusCode()]), $e->getStatusCode());
                }
            }
            else{
                Log::info('Issue on server :'.$e->getMessage());
                if ($isAjaxRequest) {
                    return Inertia::render('errors/ErrorPage', ['status' => $defaultMessage, 'prev_url' => url()->previous()])
                            ->toResponse($request)
                            ->setStatusCode('500');
                } else {
                    return response()->make(view('errors.error', ['message'=>$defaultMessage,'code'=>'500']), 500);
                }
                
            }
        }

        return $response;
    }
}
