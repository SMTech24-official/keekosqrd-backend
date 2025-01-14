<?php

namespace App\Traits;

use App\Models\User;
use App\Mail\OtpMail;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

trait AuthTrait
{
    use HandlesApiResponse;
    use AdminAuthGetTrait;

    public function profile()
    {
        return $this->safeCall(function () {
            $user = Auth::user();

            return $this->successResponse(
                'User profile data',
                ['user' => $user],
            );
        });
    }

    // Refresh Token API (GET) (Auth Token - Header)
    public function refreshToken()
    {
        return $this->safeCall(function () {
            $user = request()->user(); //user data
            $token = $user->createToken("newToken");

            $refreshToken = $token->accessToken;

            return $this->successResponse(
                'Token refreshed successfully',
                ['token' => $refreshToken],
            );
        });
    }

    public function forgotPassword(Request $request)
    {
        return $this->safeCall(function () use ($request) {

            $request->validate([
                'email' => 'required|email',
            ]);

            // Check if user exists
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->errorResponse('Email not found.', 404);
            }

            // Generate a numeric OTP (6 digits)
            $otp = new Otp();
            $generatedOtp = $otp->generate($request->email, 'numeric', 6); // Numeric OTP, 6 digits

            // Check if OTP generation was successful (the returned object should have 'status' key)
            if ($generatedOtp->status) {
                // Send OTP via email
                Mail::to($request->email)->send(new OtpMail($generatedOtp->token));

                return $this->successResponse('OTP sent to your email for password reset.');
            }
        });

        return $this->errorResponse('Failed to generate OTP.', 500);
    }

    /**
     * Verify OTP without requiring email in the request.
     */
    public function verifyOtp(Request $request)
    {
        return $this->safeCall(function () use ($request) {

            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|numeric|digits:6',
            ]);

            $otp = new Otp();
            $validation = $otp->validate($request->email, $request->otp);

            if (!$validation->status) {
                return $this->errorResponse($validation->message, 400);
            }

            // Store OTP verification status in cache/session
            cache()->put('otp_verified:' . $request->email, true, now()->addMinutes(10)); // Expires in 10 minutes

            return $this->successResponse('OTP is valid. You can now reset your password.');
        });
    }



    /**
     * Reset password without requiring email in the request.
     */
    public function resetPassword(Request $request)
    {
        return $this->safeCall(function () use ($request) {

            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            // Check if OTP was verified
            $otpVerified = cache()->get('otp_verified:' . $request->email);

            if (!$otpVerified) {
                return $this->errorResponse('OTP verification is required before resetting the password.', 400);
            }

            // Find the user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            // Reset the user's password
            $user->password = bcrypt($request->password);
            $user->save();

            // Invalidate the OTP verification status in cache
            cache()->forget('otp_verified:' . $request->email);

            return $this->successResponse(
                'Password reset successfully. OTP verification is now invalidated.',
                ['user' => $user],
            );
        });
    }

}
