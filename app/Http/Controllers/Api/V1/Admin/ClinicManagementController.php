<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClinicManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->isSuperAdmin() && !$user->isClinicAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage clinics.',
            ], 403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Clinic::query();

        // Clinic admins can only see their active memberships.
        if (!$user->isSuperAdmin()) {
            $query->whereHas('users', function ($members) use ($user) {
                $members->where('users.id', $user->getKey())
                    ->where('clinic_user.is_active', true);
            });
        }

        $search = trim($validated['q'] ?? '');

        if ($search !== '') {
            $query->where(function ($clinics) use ($search) {
                $pattern = '%' . $search . '%';

                $clinics->where('name', 'ilike', $pattern)
                    ->orWhere('province', 'ilike', $pattern)
                    ->orWhere('district', 'ilike', $pattern);
            });
        }

        $clinics = $query
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'logo',
                'cover_image',
                'province',
                'district',
                'is_active',
                'is_verified',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'message' => 'Managed clinics retrieved successfully',
            'data' => [
                'clinics' => $clinics->items(),
                'pagination' => [
                    'current_page' => $clinics->currentPage(),
                    'last_page' => $clinics->lastPage(),
                    'per_page' => $clinics->perPage(),
                    'total' => $clinics->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->exists) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->isClinicAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create clinics.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'subdistrict' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:5000'],

            // These fields are controlled by the server.
            'id' => ['prohibited'],
            'slug' => ['prohibited'],
            'user_id' => ['prohibited'],
            'clinic_id' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'is_verified' => ['prohibited'],
        ]);

        $name = trim($validated['name']);

        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'name' => ['The clinic name is required.'],
                ],
            ], 422);
        }

        $clinic = DB::transaction(function () use ($user, $validated, $name) {
            $clinic = new Clinic();

            $clinic->name = $name;

            // UUID keeps the slug unique, including for Thai clinic names.
            $clinic->slug = 'clinic-' . Str::uuid()->toString();

            foreach ([
                'description',
                'phone',
                'email',
                'website',
                'province',
                'district',
                'subdistrict',
                'postcode',
                'address',
            ] as $field) {
                $value = $validated[$field] ?? null;

                $clinic->{$field} = is_string($value)
                    ? (trim($value) === '' ? null : trim($value))
                    : null;
            }

            $country = trim($validated['country'] ?? '');
            $clinic->country = $country !== '' ? $country : 'Thailand';

            // New clinics are not published or verified automatically.
            $clinic->is_active = false;
            $clinic->is_verified = false;

            $clinic->save();

            // The creator can manage this clinic.
            // Membership activation does not publish the clinic.
            $clinic->users()->attach($user->getKey(), [
                'is_active' => true,
            ]);

            return $clinic;
        });

        return response()->json([
            'success' => true,
            'message' => 'Clinic created successfully',
            'data' => [
                'clinic' => $clinic->fresh()->only([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'phone',
                    'email',
                    'website',
                    'country',
                    'province',
                    'district',
                    'subdistrict',
                    'postcode',
                    'address',
                    'is_active',
                    'is_verified',
                ]),
            ],
        ], 201);
    }
}