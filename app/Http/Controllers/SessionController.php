<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Session;
use Illuminate\Support\Facades\Auth;
use App\Models\GlobalSettings\GlobalSetting;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function ajaxCheck()
    {
        $globalSetting = GlobalSetting::first();

        if (Auth::guard('admin')->check() && !is_null($globalSetting->session_timeout)) {
            // Log out user if idle for too long
            if (time() - Session::get('lastActivityTime') > $globalSetting->session_timeout * 60) {
                Session::forget('lastActivityTime');

                $user = Auth::guard('admin')->user();

                Auth::guard('admin')->logout();

                request()->session()->invalidate();

                request()->session()->regenerateToken();

                return response()->json([
                    'success' => true,
                    'user' => $user,
                ]);
            }
        }

        return response()->json([
            'success' => false,
        ]);
    }

    public function showPagesLockScreen(Request $request)
    {
        $email = $request->email;
        $fullName = $request->fullName;
        $loggedInWithSSO = $request->loggedInWithSSO ? 'yes' : 'no'; // faced problems when sending boolean value in react component

        return inertia('auth/SessionOutPage', compact('email', 'fullName', 'loggedInWithSSO'));
        // return view('pages.pages-lock-screen', compact('email', 'full_name', 'loggedInWithSSO'));
    }

    public function getSAMLConfiguration()
    {
        try {
            $isSsoConfigured = checkForSAMLConfigurationStatus();

            return response()->json(
                $isSsoConfigured,
                200
            );
        } catch (\Exception $exception) {

            \Log::error($exception);
        }
    }
}
