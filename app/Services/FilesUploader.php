<?php

namespace App\Services;

use App\Enums\CloudStorageDisk;
use App\Enums\FileSource;
use App\Enums\StorageDriver;
use App\Jobs\SyncFileToCloudJob;
use App\Models\File as FileModel;
use App\Models\UploadedClaimsFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Central file writer.
 *
 * Every domain that needs to persist a file calls upload() (for an
 * UploadedFile coming from a request) or registerLocalFile() (for a file
 * already written to local disk — typically by Pdf::save() / Excel::store()).
 * The active storage backend is resolved from filesystems.default (FILESYSTEM_DISK).
 * The target bucket/disk is passed explicitly via options['disk'] so callers
 * own that decision without any config-file lookup.
 */
class FilesUploader
{
    private string $activeDisk;

    public function __construct()
    {
        $this->activeDisk = config('filesystems.default', 'local');
    }

    /**
     * Whether the active filesystem disk is a cloud backend (non-local).
     */
    private function isCloudDisk(): bool
    {
        return ! in_array($this->activeDisk, ['local', 'public'], true);
    }

    /**
     * Resolve the target cloud disk from the caller-supplied options.
     * Callers that need the public bucket must pass options['disk'] = 'gcs_public'.
     * Anything else defaults to gcs_private.
     */
    private function resolveCloudDisk(array $options): CloudStorageDisk
    {
        $disk = $options['disk'] ?? null;

        if ($disk === CloudStorageDisk::GCS_PUBLIC
            || $disk === CloudStorageDisk::GCS_PUBLIC->value) {
            return CloudStorageDisk::GCS_PUBLIC;
        }

        return CloudStorageDisk::GCS_PRIVATE;
    }

    /**
     * Upload one or many UploadedFiles. Returns a single File model for a
     * single upload, or a Collection<File> for an array.
     *
     * @param  UploadedFile|UploadedFile[]  $files
     * @return FileModel|Collection<int, FileModel>
     */
    public function upload(UploadedFile|array $files, array $options): FileModel|Collection
    {
        $this->assertSource($options);

        if (is_array($files)) {
            $models = collect();
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    throw new InvalidArgumentException('Every element passed to FilesUploader::upload() must be an UploadedFile.');
                }
                $models->push($this->uploadOne($file, $options));
            }

            return $models;
        }

        return $this->uploadOne($files, $options);
    }

    /**
     * Register a file that already exists on local disk (no upload). Used by
     * Puppeteer/Pdf::save() and Excel::store() flows. If the active disk is
     * cloud, a SyncFileToCloudJob is dispatched so the file is mirrored to
     * the bucket asynchronously.
     *
     * Pass options['disk'] = 'gcs_public' for the public bucket; omit for private.
     */
    public function registerLocalFile(string $relativePath, array $options): FileModel
    {
        $this->assertSource($options);

        $relativePath = $this->normalizeRelativePath($relativePath);
        $originalName = $options['original_filename'] ?? basename($relativePath);

        $file = FileModel::create([
            'original_filename' => $originalName,
            'mime_type'         => $this->resolveMimeType($relativePath),
            'local_path'        => $relativePath,
            'cloud_path'        => null,
            'storage_driver'    => StorageDriver::LOCAL->value,
            'bucket_config'     => $this->resolveBucketConfig($options),
            'source'            => $options['source']->value,
            'uploaded_by'       => $this->resolveUploadedBy($options),
            'synced_to_cloud'   => false,
        ]);

        if ($this->isCloudDisk()) {
            SyncFileToCloudJob::dispatch($file->id);
        }

        return $file;
    }

    /**
     * Register a failed-import Excel file already stored on local disk.
     * Accepts a relative path or one prefixed with public/.
     */
    public function registerFailedFile(?string $path, ?int $uploadedBy = null): ?FileModel
    {
        if (blank($path)) {
            return null;
        }

        $relative = $this->normalizeRelativePath(preg_replace('#^public/#', '', $path));

        return $this->registerLocalFile($relative, [
            'source'      => FileSource::UPLOADED_CLAIMS_FILES,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Wire a registered failed file back to its parent UploadedClaimsFile row.
     */
    public function linkFailedFileToUpload(UploadedClaimsFile $upload, FileModel $file): void
    {
        $upload->update([
            'failed_file_path' => $file->local_path ?? $file->cloud_path,
            'failed_file_id'   => $file->id,
        ]);
    }

    /**
     * Register a failed-import file and link it to the parent upload record.
     */
    public function registerAndLinkFailedFile(
        UploadedClaimsFile $upload,
        ?string $path,
        ?int $uploadedBy = null
    ): ?FileModel {
        $file = $this->registerFailedFile($path, $uploadedBy);
        if ($file !== null) {
            $this->linkFailedFileToUpload($upload, $file);
        }

        return $file;
    }

    /**
     * Idempotently push a local-stored File to its target bucket. Determines
     * the destination disk from the file's bucket_config so no config lookup
     * is required. Verifies the upload and then flips storage_driver, cloud_path,
     * bucket_config, synced_at, and synced_to_cloud on the row.
     */
    public function syncToCloud(FileModel $file, bool $deleteLocal = false): FileModel
    {
        if ($file->synced_to_cloud && $file->storage_driver === StorageDriver::CLOUD) {
            return $file;
        }

        if (blank($file->local_path)) {
            throw new \RuntimeException("File #{$file->id} has no local_path; cannot sync to cloud.");
        }

        $localCandidate = $this->resolveLocalPath($file->local_path);
        if ($localCandidate === null) {
            throw new \RuntimeException("File #{$file->id} local_path '{$file->local_path}' does not exist on disk.");
        }

        $disk = CloudStorageDisk::tryFrom($file->bucket_config ?? '')
            ?? CloudStorageDisk::GCS_PRIVATE;

        $objectPath = FilesService::buildObjectPath(
            dirname($file->local_path),
            basename($file->local_path),
        );

        $stream = fopen($localCandidate, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("Could not open local file for File #{$file->id}.");
        }

        try {
            Storage::disk($disk->value)->put($objectPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! Storage::disk($disk->value)->exists($objectPath)) {
            throw new \RuntimeException("Verification failed: object {$objectPath} not present on {$disk->value} after upload.");
        }

        $file->forceFill([
            'cloud_path'      => $objectPath,
            'bucket_config'   => $disk->value,
            'storage_driver'  => StorageDriver::CLOUD->value,
            'synced_to_cloud' => true,
            'synced_at'       => now(),
        ])->save();

        if ($deleteLocal) {
            try {
                Storage::disk('local')->delete('public/'.ltrim($file->local_path, '/'));
            } catch (Throwable $e) {
                Log::warning('Failed to delete local copy after cloud sync', [
                    'file_id' => $file->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $file->fresh();
    }

    /**
     * Check whether a file exists on its storage backend.
     */
    public function exists(FileModel $file): bool
    {
        if ($file->storage_driver === StorageDriver::CLOUD && filled($file->cloud_path)) {
            return Storage::disk($file->bucket_config)->exists($file->cloud_path);
        }

        return filled($file->local_path)
            && is_file($this->resolveLocalPath($file->local_path) ?? '');
    }

    /**
     * Stream a file inline regardless of storage backend.
     * For cloud files the GCS adapter streams directly; for local files
     * the file is streamed from the local disk with Content-Disposition: inline.
     */
    public function download(FileModel $file): StreamedResponse
    {
        if ($file->storage_driver === StorageDriver::CLOUD && filled($file->cloud_path)) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($file->bucket_config);

            return $disk->response($file->cloud_path, $file->original_filename);
        }

        $relative = ltrim($file->local_path ?? '', '/');
        abort_if($relative === '', 404);

        $localDisk = Storage::disk('local');
        $storagePath = null;

        foreach (['public/'.$relative, $relative] as $candidate) {
            if ($localDisk->exists($candidate)) {
                $storagePath = $candidate;
                break;
            }
        }

        abort_if($storagePath === null, 404);

        return $localDisk->response($storagePath, $file->original_filename);
    }

    /**
     * Return an absolute local filesystem path that OpenSpout / Excel can read.
     * For cloud files the object is downloaded to a temp file first; callers are
     * responsible for unlinking the temp file when done.
     */
    public function resolveProcessingPath(FileModel $file): string
    {
        if ($file->storage_driver === StorageDriver::CLOUD && filled($file->cloud_path)) {
            $tmp    = tempnam(sys_get_temp_dir(), 'rcp_') . '.xlsx';
            $stream = Storage::disk($file->bucket_config)->readStream($file->cloud_path);
            file_put_contents($tmp, $stream);

            return $tmp;
        }

        $path = $this->resolveLocalPath($file->local_path ?? '');
        if ($path === null) {
            throw new \RuntimeException("File #{$file->id} not found on disk.");
        }

        return $path;
    }

    /**
     * Delete a file from its storage backend and soft-delete the row.
     * Accepts a File model or a raw legacy path string.
     */
    public function delete(FileModel|string|null $fileOrPath): bool
    {

        if ($fileOrPath === null) {
            return false;
        }

        if (is_string($fileOrPath)) {
            $relative = 'public/'.ltrim($fileOrPath, '/');

            if (Storage::disk('local')->exists($relative)) {

                return Storage::disk('local')->delete($relative);
            }

            return false;
        }

        $file = $fileOrPath;

        if ($file->storage_driver === StorageDriver::CLOUD && filled($file->cloud_path) && filled($file->bucket_config)) {
            try {

                Storage::disk($file->bucket_config)->delete($file->cloud_path);

            } catch (Throwable $e) {
                Log::warning('Failed to delete cloud object', [
                    'file_id' => $file->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if (filled($file->local_path)) {
            $local = 'public/'.ltrim($file->local_path, '/');
            if (Storage::disk('local')->exists($local)) {
                Storage::disk('local')->delete($local);
            }
        }

        $file->delete();

        return true;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function uploadOne(UploadedFile $file, array $options): FileModel
    {
        $directory = $this->requireDirectory($options);
        $fileName  = $this->buildFileName($file, $options);
        $source    = $options['source'];

        return $this->isCloudDisk()
            ? $this->uploadToCloud($file, $directory, $fileName, $source, $options)
            : $this->uploadToLocal($file, $directory, $fileName, $source, $options);
    }

    private function uploadToLocal(
        UploadedFile $file,
        string $directory,
        string $fileName,
        FileSource $source,
        array $options
    ): FileModel {
        $file->storeAs('public/'.$directory, $fileName);

        $relativePath = $directory.'/'.$fileName;

        return FileModel::create([
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType() ?: $this->resolveMimeType($relativePath),
            'local_path'        => $relativePath,
            'cloud_path'        => null,
            'storage_driver'    => StorageDriver::LOCAL->value,
            'bucket_config'     => $this->resolveBucketConfig($options),
            'source'            => $source->value,
            'uploaded_by'       => $this->resolveUploadedBy($options),
            'synced_to_cloud'   => false,
        ]);
    }

    private function uploadToCloud(
        UploadedFile $file,
        string $directory,
        string $fileName,
        FileSource $source,
        array $options
    ): FileModel {
        $disk       = $this->resolveCloudDisk($options);
        $objectPath = FilesService::buildObjectPath($directory, $fileName);

        Storage::disk($disk->value)->put(
            $objectPath,
            file_get_contents($file->getRealPath()),
        );

        if (! Storage::disk($disk->value)->exists($objectPath)) {
            throw new \RuntimeException("Verification failed: object {$objectPath} not present on {$disk->value} after upload.");
        }

        return FileModel::create([
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'local_path'        => null,
            'cloud_path'        => $objectPath,
            'storage_driver'    => StorageDriver::CLOUD->value,
            'bucket_config'     => $disk->value,
            'source'            => $source->value,
            'uploaded_by'       => $this->resolveUploadedBy($options),
            'synced_to_cloud'   => true,
            'synced_at'         => now(),
        ]);
    }

    private function assertSource(array $options): void
    {
        if (! isset($options['source']) || ! $options['source'] instanceof FileSource) {
            throw new InvalidArgumentException("FilesUploader options must include a 'source' of type App\\Enums\\FileSource.");
        }
    }

    private function requireDirectory(array $options): string
    {
        $directory = $options['directory'] ?? null;
        if (! is_string($directory) || $directory === '') {
            throw new InvalidArgumentException("FilesUploader options must include a non-empty 'directory'.");
        }

        return $this->normalizeRelativePath($directory);
    }

    private function buildFileName(UploadedFile $file, array $options): string
    {
        $name = $options['original_filename'] ?? $file->getClientOriginalName();

        return time().'-'.$name;
    }

    private function resolveUploadedBy(array $options): ?int
    {
        if (array_key_exists('uploaded_by', $options)) {
            return $options['uploaded_by'];
        }

        return auth()->id();
    }

    private function resolveBucketConfig(array $options): string
    {
        return $this->resolveCloudDisk($options)->value;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = preg_replace('#[/\\\\]+#', '/', $path);

        return trim($path, '/');
    }

    private function resolveMimeType(string $relativePath): ?string
    {
        $candidate = $this->resolveLocalPath($relativePath);
        if ($candidate === null) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\File::mimeType($candidate) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveLocalPath(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        $candidates = [
            storage_path('app/public/'.$relative),
            storage_path('app/'.$relative),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
