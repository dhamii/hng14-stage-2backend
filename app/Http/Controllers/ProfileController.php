<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileIngestionService;
use App\Services\QueryParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(protected QueryParser $queryParser, protected ProfileIngestionService $profileIngestionService) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gender' => 'nullable|string|in:male,female',
            'age_group' => 'nullable|string|in:child,teenager,adult,senior',
            'country_id' => 'nullable|string|size:2',
            'min_age' => 'nullable|integer|min:0',
            'max_age' => 'nullable|integer|min:0',
            'min_gender_probability' => 'nullable|numeric|between:0,1',
            'min_country_probability' => 'nullable|numeric|between:0,1',
            'sort_by' => 'nullable|string|in:age,created_at,gender_probability',
            'order' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid query parameters'
            ], 422);
        }

        $query = Profile::query();

        // Filtering
        if ($request->has('gender') && $request->gender !== null) {
            $query->where('gender', '=', $request->gender);
        }
        if ($request->has('age_group') && $request->age_group !== null) {
            $query->where('age_group', '=', $request->age_group);
        }
        if ($request->has('country_id') && $request->country_id !== null) {
            $query->where('country_id', '=', strtoupper($request->country_id));
        }
        if ($request->has('min_age') && $request->min_age !== null) {
            $query->where('age', '>=', $request->min_age);
        }
        if ($request->has('max_age') && $request->max_age !== null) {
            $query->where('age', '<=', $request->max_age);
        }
        if ($request->has('min_gender_probability') && $request->min_gender_probability !== null) {
            $query->where('gender_probability', '>=', $request->min_gender_probability);
        }
        if ($request->has('min_country_probability') && $request->min_country_probability !== null) {
            $query->where('country_probability', '>=', $request->min_country_probability);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $order = $request->get('order', 'asc');
        $query->orderBy($sortBy, $order);

        // Pagination
        $limit = $request->get('limit', 10);
        $profiles = $query->paginate($limit);

        return response()->json([
            'status' => 'success',
            'page' => $profiles->currentPage(),
            'limit' => $profiles->perPage(),
            'total' => $profiles->total(),
            'total_pages' => $profiles->lastPage(),
            'links' => [
                'self' => $profiles->url($profiles->currentPage()),
                'next' => $profiles->nextPageUrl(),
                'prev' => $profiles->previousPageUrl(),
            ],
            'data' => $profiles->items(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if (!$request->has('q') || empty($request->q)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing or empty parameter'
            ], 400);
        }

        $filters = $this->queryParser->parse($request->q);

        if (!$filters) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to interpret query'
            ], 400);
        }

        // Reuse index logic by merging filters into request
        $request->merge($filters);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|min:2|max:255']);

        $profile = $this->profileIngestionService->createFromName($request->name);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile created successfully',
            'data' => $profile,
        ], 201);
    }

    public function destroy(Profile $profile): JsonResponse
    {
        $profile->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile deleted successfully',
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if ($request->query('format') !== 'csv') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unsupported export format',
            ], 400);
        }

        $query = Profile::query();

        foreach (['gender', 'age_group', 'country_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $field === 'country_id' ? strtoupper($request->$field) : $request->$field);
            }
        }

        if ($request->filled('min_age')) {
            $query->where('age', '>=', $request->min_age);
        }
        if ($request->filled('max_age')) {
            $query->where('age', '<=', $request->max_age);
        }
        if ($request->filled('min_gender_probability')) {
            $query->where('gender_probability', '>=', $request->min_gender_probability);
        }
        if ($request->filled('min_country_probability')) {
            $query->where('country_probability', '>=', $request->min_country_probability);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $order = $request->get('order', 'asc');
        $query->orderBy($sortBy, $order);

        $filename = 'profiles_export_'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['id', 'name', 'gender', 'gender_probability', 'age', 'age_group', 'country_id', 'country_name', 'country_probability', 'created_at', 'updated_at']);

            $query->chunk(200, function ($profiles) use ($stream): void {
                foreach ($profiles as $profile) {
                    fputcsv($stream, [
                        $profile->id,
                        $profile->name,
                        $profile->gender,
                        $profile->gender_probability,
                        $profile->age,
                        $profile->age_group,
                        $profile->country_id,
                        $profile->country_name,
                        $profile->country_probability,
                        $profile->created_at,
                        $profile->updated_at,
                    ]);
                }
            });

            fclose($stream);
        }, 200, $headers);
    }
}