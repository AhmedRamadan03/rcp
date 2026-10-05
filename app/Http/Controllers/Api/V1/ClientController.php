<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dashboard\ClientInfoResource;
use Illuminate\Http\Request;


class ClientController extends Controller
{
    
    public function clientInfo()
    {

        return response()->json([
            'status'       => true,
            'client_info' => new ClientInfoResource($this)
        ]);
    }

}
