<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserInfoController extends Controller
{
    public function show($id)
    {
        $user = User::with([
            'role',
        ])->withTrashed()->findOrFail($id);

        return response()->json([
            "succes" => true,
            'message' => 'User information retrieved successfully',
            'data' =>  $user,
        ]);
    }

    public function delete($id, Request $request)
    {
        try {
            $user = User::findOrFail($id);
            $request->validate([
                'new_assined_user_id' => 'required|exists:users,id',
            ]);
            $userService = new UserService();

            $userService->delete($user, $request->new_assined_user_id);

            return response()->json([
                "succes" => true,
                'message' => 'User deleted successfully',
            ]);
        }catch(ModelNotFoundException $e) {
            return response()->json([
                "succes" => false,
                'message' => 'User not found to be deleted',
            ], 404);
        }catch (\Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ],400);
        }

    }

    public function restore($id)
    {
        try {
            $user = User::onlyTrashed()->findOrFail($id);
            $user->restore();


            return response()->json([
                "succes" => true,
                'message' => 'User restored successfully',
                'data' => $user,
            ]);
        }catch(ModelNotFoundException $e) {
            return response()->json([
                "succes" => false,
                'message' => 'User not found to be restored',
            ], 404);
        }catch (\Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ],400);
        }

    }

}
