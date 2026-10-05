<?php

namespace Tests\Feature;

use App\Enums\CloudStorageDisk;
use App\Enums\StorageDriver;
use App\Models\File;
use App\Models\PaymentLink;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.memory_testing.database', ':memory:');
        DB::purge('memory_testing');

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('local_path')->nullable();
            $table->text('cloud_path')->nullable();
            $table->string('storage_driver')->default(StorageDriver::LOCAL->value);
            $table->string('bucket_config')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->boolean('synced_to_cloud')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    #[Test]
    public function cloud_file_resolution_returns_an_uncredentialed_signed_url(): void
    {
        $disk = Storage::fake(CloudStorageDisk::GCS_PRIVATE->value);
        $path = 'claimFiles/testing/cloud-file.txt';
        $signedUrl = 'https://storage.googleapis.com/private-bucket/'.$path.'?signature=test';

        $disk->put($path, 'cloud contents');
        $disk->buildTemporaryUrlsUsing(fn (): string => $signedUrl);

        $file = File::create([
            'original_filename' => 'cloud-file.txt',
            'mime_type' => 'text/plain',
            'cloud_path' => $path,
            'storage_driver' => StorageDriver::CLOUD->value,
            'bucket_config' => CloudStorageDisk::GCS_PRIVATE->value,
            'synced_to_cloud' => true,
        ]);

        Sanctum::actingAs(new User);

        $this->getJson("/api/v1/show-file/{$file->uuid}?resolve=1")
            ->assertOk()
            ->assertJsonPath('data.url', $signedUrl)
            ->assertJsonPath('data.requires_credentials', false)
            ->assertHeaderMissing('Location');
    }

    #[Test]
    public function cloud_file_requests_still_redirect_without_resolution_mode(): void
    {
        $disk = Storage::fake(CloudStorageDisk::GCS_PRIVATE->value);
        $path = 'claimFiles/testing/cloud-file.txt';
        $signedUrl = 'https://storage.googleapis.com/private-bucket/'.$path.'?signature=test';

        $disk->put($path, 'cloud contents');
        $disk->buildTemporaryUrlsUsing(fn (): string => $signedUrl);

        $file = File::create([
            'original_filename' => 'cloud-file.txt',
            'mime_type' => 'text/plain',
            'cloud_path' => $path,
            'storage_driver' => StorageDriver::CLOUD->value,
            'bucket_config' => CloudStorageDisk::GCS_PRIVATE->value,
            'synced_to_cloud' => true,
        ]);

        Sanctum::actingAs(new User);

        $this->get("/api/v1/show-file/{$file->uuid}")
            ->assertRedirect($signedUrl);
    }

    #[Test]
    public function authenticated_client_view_can_resolve_cloud_files(): void
    {
        $disk = Storage::fake(CloudStorageDisk::GCS_PRIVATE->value);
        $path = 'claimFiles/testing/client-file.txt';
        $signedUrl = 'https://storage.googleapis.com/private-bucket/'.$path.'?signature=test';

        $disk->put($path, 'client file contents');
        $disk->buildTemporaryUrlsUsing(fn (): string => $signedUrl);

        $file = File::create([
            'original_filename' => 'client-file.txt',
            'mime_type' => 'text/plain',
            'cloud_path' => $path,
            'storage_driver' => StorageDriver::CLOUD->value,
            'bucket_config' => CloudStorageDisk::GCS_PRIVATE->value,
            'synced_to_cloud' => true,
        ]);

        Auth::guard('client-view')->setUser(new PaymentLink);

        $this->getJson("/api/v1/show-file/{$file->uuid}?resolve=1")
            ->assertOk()
            ->assertJsonPath('data.url', $signedUrl)
            ->assertJsonPath('data.requires_credentials', false);
    }

    #[Test]
    public function local_file_resolution_keeps_using_the_authenticated_api_url(): void
    {
        $relativePath = 'testing/local-file.txt';
        Storage::disk('local')->put('public/'.$relativePath, 'local contents');

        try {
            $file = File::create([
                'original_filename' => 'local-file.txt',
                'mime_type' => 'text/plain',
                'local_path' => $relativePath,
                'storage_driver' => StorageDriver::LOCAL->value,
                'bucket_config' => CloudStorageDisk::GCS_PRIVATE->value,
                'synced_to_cloud' => false,
            ]);

            Sanctum::actingAs(new User);

            $this->getJson("/api/v1/show-file/{$file->uuid}?resolve=1")
                ->assertOk()
                ->assertJsonPath('data.url', route('download', ['key' => $file->uuid]))
                ->assertJsonPath('data.requires_credentials', true)
                ->assertHeaderMissing('Location');
        } finally {
            Storage::disk('local')->delete('public/'.$relativePath);
        }
    }

    #[Test]
    public function unauthenticated_requests_cannot_resolve_file_urls(): void
    {
        $this->getJson('/api/v1/show-file/'.Str::uuid().'?resolve=1')
            ->assertForbidden();
    }

    #[Test]
    public function raw_file_path_browsing_is_rejected(): void
    {
        $relativePath = 'testing/secret-file.txt';
        Storage::disk('local')->put('public/'.$relativePath, 'secret local contents');

        try {
            Sanctum::actingAs(new User);

            $this->get("/api/v1/show-file/{$relativePath}")
                ->assertNotFound();

            $this->getJson("/api/v1/show-file/{$relativePath}?resolve=1")
                ->assertNotFound();
        } finally {
            Storage::disk('local')->delete('public/'.$relativePath);
        }
    }

    #[Test]
    public function non_uuid_keys_return_not_found(): void
    {
        Sanctum::actingAs(new User);

        $this->getJson('/api/v1/show-file/not-a-valid-uuid')
            ->assertNotFound()
            ->assertJsonPath('message', 'File not found.');
    }
}
