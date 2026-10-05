<?php

namespace App\Models;

use App\Enums\CloudStorageDisk;
use App\Enums\FileSource;
use App\Enums\StorageDriver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;


class File extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'storage_driver'  => StorageDriver::class,
        'synced_to_cloud' => 'boolean',
        'synced_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (File $file) {
            if (blank($file->uuid)) {
                $file->uuid = (string) Str::uuid();
            }
        });
    }

    public function resolveClaim(): ?Claim
    {
        $claimId = $this->resolveClaimId();

        return $claimId ? Claim::find($claimId) : null;
    }

    public function resolveClaimId(): ?int
    {
        return match (FileSource::tryFrom($this->source)) {
            FileSource::CLAIM_FILES => ClaimFile::where('file_id', $this->id)->value('claim_id'),
            FileSource::ADDITIONAL_FILES => AdditionalFile::where('file_id', $this->id)->value('claim_id'),
            FileSource::OBLIGATION_LETTERS => AdditionalFile::where('file_id', $this->id)->value('claim_id'),
            FileSource::DISCHARGE_FILES => Discharge::where('file_id', $this->id)->value('claim_id'),
            FileSource::UPLOADED_CLAIMS_FILES => optional(
                UploadedClaimsFile::where('original_file_id', $this->id)
                    ->orWhere('failed_file_id', $this->id)
                    ->first()
            )?->claims()->value('id'),
            FileSource::REJECTED_CLAIMS => RejectedClaim::whereHas(
                'attachments',
                fn($q) => $q->where('files.id', $this->id)
            )->value('claim_id'),
            FileSource::VOUCHERS => Voucher::where('file_id', $this->id)->first()?->link?->payment?->claim_id,
            default => null,
        };
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // -------------------------------------------------------------------------
    // Path helpers
    // -------------------------------------------------------------------------

    /**
     * Effective storage path (cloud_path for GCS, local_path otherwise).
     * Use resolveUrl() when you need a URL the frontend can actually hit.
     */
    public function path(): ?string
    {
        if ($this->storage_driver === StorageDriver::CLOUD) {
            return $this->cloud_path ?: $this->local_path;
        }

        return $this->local_path;
    }

    // -------------------------------------------------------------------------
    // URL resolution — single source of truth
    // -------------------------------------------------------------------------

    /**
     * Whether this file sits in a publicly-readable bucket.
     * Profile images are the only public source; everything else is private.
     */
    public function isPublic(): bool
    {

        return $this->bucket_config === CloudStorageDisk::GCS_PUBLIC->value;
    }

    /**
     * Expiry instant for a private-GCS signed URL, null for public / local files.
     */
    public function expiresAt(?int $ttl = null): ?Carbon
    {
        if ($this->storage_driver !== StorageDriver::CLOUD || $this->isPublic()) {
            return null;
        }

        $ttl ??= (int) config("filesystems.disks.{$this->bucket_config}.signed_url_ttl", 1800);

        return Carbon::now()->addSeconds($ttl);
    }

    /**
     * Resolve a URL the frontend can use to access this file.
     *
     * Behaviour per storage driver:
     *   - GCS + public bucket  → plain canonical URL (no expiry)
     *   - GCS + private bucket → V4 signed URL valid for $ttl seconds
     *   - local                → route('download') via file UUID
     *
     * Returns null when essential metadata (cloud_path / local_path / uuid) is missing.
     *
     * @param  int|null  $ttl  Signed-URL lifetime in seconds. Defaults to
     *                          config("filesystems.disks.{bucket}.signed_url_ttl") (1 800 s / 30 min).
     */
    public function resolveUrl(?int $ttl = null): ?string
    {
        $ttl ??= (int) config("filesystems.disks.{$this->bucket_config}.signed_url_ttl", 1800);

        if ($this->storage_driver === StorageDriver::CLOUD) {
            if (blank($this->cloud_path) || blank($this->bucket_config)) {
                return null;
            }

            try {
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk($this->bucket_config);

                if ($this->isPublic()) {
                    // Public bucket — return the stable object URL, no signing needed.
                    return $disk->url($this->cloud_path);
                }

                // Private bucket — mint a short-lived signed URL.
                return $disk->temporaryUrl(
                    $this->cloud_path,
                    Carbon::now()->addSeconds($ttl),
                );
            } catch (Throwable) {
                return null;
            }
        }

        // Local storage — serve through the show-file route using the file's
        // UUID only. Raw local_path is never exposed as a URL segment.
        if (blank($this->uuid)) {
            return null;
        }

        return $this->isPublic()
            ? route('public.file', ['key' => $this->uuid])
            : route('download', ['key' => $this->uuid]);
    }
}