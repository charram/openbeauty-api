<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class ProfilePhoneController extends Controller
{
    /**
     * POST /api/v1/profile/phone/verify
     *
     * Link a Firebase-verified phone number to
     * the currently authenticated Laravel user.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase ID Token is required.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentUser = $request->user();

        if (!$currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $credentials = config('services.firebase.credentials');

            if (!$credentials || !is_file($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase authentication is unavailable.',
                ], 503);
            }

            $auth = (new Factory())
                ->withServiceAccount($credentials)
                ->createAuth();

            // Verify Firebase ID Token
            $verifiedToken = $auth->verifyIdToken(
                $request->input('id_token')
            );

            $claims = $verifiedToken->claims()->all();

            $firebaseUid = $claims['sub'] ?? null;

            if (!$firebaseUid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase UID is missing.',
                ], 401);
            }

            // Confirm Firebase identity belongs to this Laravel user.
            $linkedAccount = SocialAccount::where(
                'firebase_uid',
                $firebaseUid
            )
                ->where('user_id', $currentUser->id)
                ->whereIn('provider', [
                    'google',
                    'facebook',
                    'apple',
                ])
                ->exists();

            if (!$linkedAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase account does not match the logged-in user.',
                ], 403);
            }

            // Read the verified phone from Firebase user record.
            $firebaseUser = $auth->getUser($firebaseUid);

            $phone = $firebaseUser->phoneNumber ?? null;

            if (
                !is_string($phone) ||
                !preg_match('/^\+[1-9]\d{7,14}$/', $phone)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'No verified phone number found on Firebase account.',
                ], 422);
            }

            // Require a recently authenticated Firebase session.
            $authTime = $claims['auth_time'] ?? null;

            if (
                !is_numeric($authTime) ||
                (time() - (int) $authTime) > 600 ||
                (int) $authTime > time() + 60
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please reauthenticate before linking a phone number.',
                ], 403);
            }

            // Update the existing user only.
            $result = DB::transaction(function () use (
                $currentUser,
                $phone
            ) {
                $user = User::whereKey($currentUser->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $phoneOwner = User::where('phone', $phone)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($phoneOwner) {
                    throw new \DomainException(
                        'This phone number is already used by another account.'
                    );
                }

         // Check whether the phone number has changed.
$phoneChanged = $user->phone !== $phone;

// Update the phone number.
$user->phone = $phone;

// Update verification time for a new or changed phone number.
if ($phoneChanged || !$user->phone_verified_at) {
    $user->phone_verified_at = now();
}

$user->save();

                return $user;
            });

            return response()->json([
                'success' => true,
                'message' => 'Phone number verified and linked successfully.',
                'data' => [
                    'user' => [
                        'id' => $result->id,
                        'name' => $result->name,
                        'email' => $result->email,
                        'phone' => $result->phone,
                        'phone_verified_at' => $result->phone_verified_at,
                        'role' => $result->role,
                    ],
                ],
            ]);

        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Firebase token.',
            ], 401);

        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);

        } catch (\Throwable $e) {
            Log::error('Profile phone verification failed', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify phone number.',
            ], 500);
        }
    }
}