<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class SocialAuthController extends Controller
{
    /**
     * POST /api/v1/auth/social/firebase
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $credentials = config('services.firebase.credentials');

            if (!$credentials || !is_file($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase authentication is unavailable',
                ], 503);
            }

            $auth = (new Factory())
                ->withServiceAccount($credentials)
                ->createAuth();

            $verifiedToken = $auth->verifyIdToken(
                $validated['id_token']
            );

            $claims = $verifiedToken->claims()->all();

            $firebaseUid = $claims['sub'] ?? null;

            $signInProvider =
                $claims['firebase']['sign_in_provider'] ?? null;

            $providers = [
                'google.com' => 'google',
                'facebook.com' => 'facebook',
                'apple.com' => 'apple',
            ];

            $provider = $providers[$signInProvider] ?? null;

            if (!$firebaseUid || !$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported social login provider',
                ], 403);
            }

            $email = $claims['email'] ?? null;
            $name = $claims['name'] ?? 'Open Beauty User';

            $user = DB::transaction(function () use (
                $firebaseUid,
                $provider,
                $email,
                $name
            ) {
                $socialAccount = SocialAccount::where(
                    'provider',
                    $provider
                )
                    ->where('firebase_uid', $firebaseUid)
                    ->first();

                if ($socialAccount) {
                    return $socialAccount->user;
                }

                // Do not automatically merge accounts by email.
                // Existing accounts require a separate verified linking flow.
                if (
                    $email &&
                    User::where('email', $email)->exists()
                ) {
                    throw new \DomainException(
                        'An account with this email already exists. Please sign in using your existing method.'
                    );
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::random(64),
                ]);

                // Never grant admin privileges through social login.
                $user->role = User::ROLE_CUSTOMER;
                $user->save();

                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => $firebaseUid,
                    'firebase_uid' => $firebaseUid,
                ]);

                return $user;
            });

            $token = $user
                ->createToken('open-beauty-social')
                ->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Social login successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                ],
            ]);
        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Firebase token',
            ], 401);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Throwable $e) {
            Log::error('Social login failed', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Social authentication failed',
            ], 500);
        }
    }
}