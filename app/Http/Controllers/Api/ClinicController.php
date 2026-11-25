<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    /**
     * Display a listing of active clinics.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim($validated['q'] ?? '');

        $query = Clinic::query()
            ->where('is_active', true)
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'logo',
                'cover_image',
                'province',
                'district',
                'rating',
                'review_count',
                'is_verified',
            ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $pattern = '%' . $search . '%';

                $builder->where('name', 'ilike', $pattern)
                    ->orWhere('province', 'ilike', $pattern)
                    ->orWhere('district', 'ilike', $pattern);
            });
        }

        $clinics = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'message' => 'Clinics retrieved successfully',
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}