<?php

namespace App\Http\Resources\Api\V1;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File as FileFacade;
use Throwable;

/**
 * Standard API shape for file metadata.
 *
 * Wraps a registered File row or, via from(), a legacy storage path when no
 * File row exists yet. URLs always point at the download route.
 */
class FileResource extends JsonResource
{
    /**
     * Build a resource from a File row and/or a legacy path fallback.
     *
     * - File present          → wraps the File model.
     * - No File, path present → wraps the legacy path string.
     * - Neither               → returns null.
     */
    public static function from(?File $file, ?string $legacyPath = null): ?self
    {
        if ($file !== null) {
            return new static($file);
        }

        if (filled($legacyPath)) {
            return new static($legacyPath);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        
        if ($this->resource instanceof File) {
            return [
                'file_name' => $this->resource->original_filename,
                'mime_type' => $this->resource->mime_type,
                'url'       => self::downloadUrl(
                    filled($this->resource->uuid)
                        ? $this->resource->uuid
                        : ltrim((string) $this->resource->local_path, '/')
                ),
            ];
        }

        $path = ltrim((string) $this->resource, '/');

        return [
            'file_name' => basename($path),
            'mime_type' => pathinfo($path, PATHINFO_EXTENSION) ?? null,
            'url'       => self::downloadUrl($path),
        ];
    }

    private static function downloadUrl(?string $key): ?string
    {
        if (blank($key)) {
            return null;
        }

        return route('download', ['key' => $key]);
    }
}
