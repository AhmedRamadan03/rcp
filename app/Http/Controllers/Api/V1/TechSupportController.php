<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\TechSupport;
use Illuminate\Routing\Controller;

class TechSupportController extends Controller
{
    /**
     * Get dashboard data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {

        $data =  TechSupport::loginIfExists(auth()->user()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Login successfully, use the link below.',
            'data' => [
                'url' => techSupportUrl().$data['rcp_token']
            ]
        ]);
    }
}
