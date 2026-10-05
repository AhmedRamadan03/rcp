<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuthenticationLog as AuthLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search', '');

        $authLogs = AuthLogs::whereHas('authenticatable', function ($query) use ($searchTerm) {
            $query->where('name', 'like', '%' . $searchTerm . '%')
                ->orWhere('email', 'like', '%' . $searchTerm . '%');
        })->paginate($perPage);

        return response()->json([
            "succes" => true,
            'message' => 'Authentication logs retrieved successfully',
            'data' => $authLogs->items(),
            'pagination' => [
                'total' => $authLogs->total(),
                'per_page' => $authLogs->perPage(),
                'current_page' => $authLogs->currentPage(),
                'last_page' => $authLogs->lastPage(),
                'from' => $authLogs->firstItem(),
                'to' => $authLogs->lastItem()
            ]
        ]);
    }
}

