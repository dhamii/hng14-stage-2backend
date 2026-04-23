<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\QueryParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    protected $queryParser;

    public function __construct(QueryParser $queryParser)
    {
        $this->queryParser = $queryParser;
    }

    public function index(Request $request)
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
            'data' => $profiles->items(),
        ]);
    }

    public function search(Request $request)
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
}