<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StorageDriver;
use App\Models\File;
use App\Services\FilesUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File delivery via GET /api/show-file/{key}.
 *
 * URL resolution for models/resources lives in File::resolveUrl().
 */
class FileController extends Controller
{
    /**
     * Public download/redirect endpoint backing GET /api/show-file/{key?}.
     *
     *  - $key is a File UUID  → cloud files redirect to a signed or public URL;
     *                           local files are streamed directly.
     */
    public function serve(Request $request, ?string $key = null): JsonResponse|RedirectResponse|StreamedResponse
    {
        if (blank($key) || ! Str::isUuid($key)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $file = File::where('uuid', $key)->first();
        if ($file !== null) {
            return $this->resolveFile($file, $request->boolean('resolve'));
        }

        return response()->json(['message' => 'File not found.'], 404);
    }

    private function resolveFile(File $file, bool $resolveOnly = false): JsonResponse|RedirectResponse|StreamedResponse
    {
        $uploader = app(FilesUploader::class);

        if (! $uploader->exists($file)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        if ($file->storage_driver === StorageDriver::CLOUD) {
            $url = $file->resolveUrl();
            if ($url === null) {
                return response()->json(['message' => 'File is not accessible.'], 404);
            }

            if ($resolveOnly) {
                return $this->resolvedAccessResponse($url, false);
            }

            return redirect()->away($url);
        }

        if ($resolveOnly) {
            $public = request()->routeIs('public.file');

            return $this->resolvedAccessResponse(
                route($public ? 'public.file' : 'download', ['key' => $file->uuid]),
                ! $public,
            );
        }

        return $uploader->download($file);
    }

    private function resolvedAccessResponse(string $url, bool $requiresCredentials): JsonResponse
    {
        return response()->json([
            'data' => [
                'url' => $url,
                'requires_credentials' => $requiresCredentials,
            ],
        ]);
    }
}