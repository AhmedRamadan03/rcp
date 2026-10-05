<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Liveness endpoint for uptime checks and the "deployment is down" alert.
 * Unauthenticated on purpose. Kept cheap: one trivial query.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => 'ok'];
        $healthy = true;

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $checks['database'] = 'error';
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
