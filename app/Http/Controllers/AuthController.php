<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Mail\OTPMail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWT;

#[AllowDynamicProperties] class AuthController extends Controller
{
    private static string $phone = '';

    public function __construct(JWT $jwt)
    {
        $this->jwt = $jwt;
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email:rfc,dns|unique:users,email|required_without:phone',
            'phone' => 'nullable|string|unique:users,phone|max:15|required_without:email',
            'password' => 'required|string|min:6'
        ]);

        if (!$this->validatePhone($validated['phone'])) {
            return sendErrorResponse('Invalid phone number', 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => self::$phone,
            'password' => bcrypt($validated['password']),
        ]);

        // Assign the role to the user
        $user->assignRole('customer');

        Auth::attempt($request->only('email', 'password'));

        // Generate refresh token (optional: store securely if needed)
        $token = $this->jwt->claims(['refresh' => true])->fromUser(auth()->user());

        $data = [
            'access_token' => $token ?? 'null',
        ];

        return sendSuccessResponse('Customer registered successfully', $data, 201);
    }

    /**
     * Login a user and issue tokens
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['email', 'password']);

        if (!Auth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Generate refresh token (optional: store securely if needed)
        $token = $this->jwt->fromUser(auth()->user());

        $data = [
            'access_token' => $token,
        ];

        return sendSuccessResponse('Login successful', $data);
    }

    /**
     * Forgot Password (Generate OTP)
     */
    public function forget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = rand(1000, 9999); // Generate a 4-digit OTP

        // Ideally store OTP in the database or cache
        $user = User::where('email', $validated['email'])->first();
        $user->password_reset_code = $otp; // Add an `otp` column in the `users` table
        $user->save();

        // Send OTP via email (simulated here)
        Mail::to($user->email)->send(new OTPMail($otp));

        return sendSuccessResponse('OTP sent successfully');
    }

    /**
     * Validate OTP
     */
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user->password_reset_code != $validated['otp']) {
            return response()->json(['error' => 'Invalid OTP'], 400);
        }

        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $validated['email']], // Condition to check if the record already exists
            [
                'token' => $token,               // New token to insert or update
                'created_at' => now(),           // Update the timestamp to current time
            ]
        );


        $user->password_reset_code = null;
        $user->save();

        return sendSuccessResponse('OTP validated successfully', ['token' => $token]);
    }

    /**
     * Reset Password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'newPassword' => 'required|string|min:6',
        ]);
        $user = User::where('email', $validated['email'])->first();
        $token = DB::table('password_reset_tokens')->
        where('email', $validated['email'])->where('token', $validated['token']);

        if (!$user && $token->doesntExist()) {
            return sendErrorResponse('Unauthorized', 401);
        }

        if (Hash::check($validated['newPassword'], $user->password)) {
            return sendErrorResponse('New password cannot be the same as the old password', 400);
        }

        if (Carbon::parse($token->first()->created_at)->diffInMinutes(now()) > 30) {
            return sendErrorResponse('Token expired', 401);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->password = bcrypt($validated['newPassword']);
        $user->save();

        return sendSuccessResponse('Password reset successfully');
    }

    /**
     * Refresh Token
     */
    public function refresh(): JsonResponse
    {
        $newAccessToken = JWTAuth::refresh();
        $data = [
            'access_token' => $newAccessToken,
            'expires_in' => config('jwt.ttl'),
        ];

        return sendSuccessResponse('Token refreshed successfully', $data);
    }

    /**
     * Logout and invalidate tokens
     */
    public function logout(): JsonResponse
    {
        try {
            auth()->logout();
            return sendSuccessResponse('User logged out successfully');
        } catch (JWTException $e) {
            return sendErrorResponse('Unable to logout', 401);
        }
    }

    /**
     * Get the authenticated user
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();
        $data = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'profile_photo_url' => $user->profile_photo_url,
                'role' => $user->getRoleNames()->first(), // Fetches a single role name
            ],
        ];
        return sendSuccessResponse('User details', $data);
    }

    private function validatePhone($phone): false|string
    {
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse($phone, "BD"); // BD = Bangladesh
            if ($phoneUtil->isValidNumber($numberProto)) {
                self::$phone = $phoneUtil->format($numberProto, PhoneNumberFormat::E164); // +8801XXXXXXXXX

                return true;
            }
            return false;
        } catch (NumberParseException $e) {
            return false;
        }
    }
}
