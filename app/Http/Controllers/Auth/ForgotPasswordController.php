<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Admin;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function forgetPasswordForm()
    {
        return inertia('auth/ForgetPassword');
    }

    public function broker()
    {
        return Password::broker('admins');
    }

    public function forgetPasswordVerifyEmail(Request $request)
    {
        if ($request->email) {
            $admin = Admin::where('email', $request->email)->first();

            if ($admin) {
                return 'true';
            } else {
                return 'false';
            }
        } else {
            return 'false';
        }
    }

    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);

        $admin = Admin::where('email', $request->email)->first();
        $eligibleForResetPassword = true;
        if ($admin) {
            if ($admin->status != 'active' || $admin->auth_method != 'Manual') {
                $eligibleForResetPassword = false;
            }
        } else {
            $eligibleForResetPassword = false;
        }

        if ($eligibleForResetPassword) {
            $response = $this->broker()->sendResetLink(
                $this->credentials($request)
            );
        } else {
            $response = "passwords.sent";
        }

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($request, $response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    protected function sendResetLinkResponse(Request $request, $response)
    {
        $data = [
            'pageTitle' => 'Forgot Password Success',
            'title' => 'Success!',
            'message' => 'If the provided email is a valid registered email, you will receive a password reset link in your inbox.',
            'actionLink' => route('login'),
            'actionTitle' => 'Back To Home',
        ];

        return inertia('auth/StatusPage', compact('data'));
    }
}
