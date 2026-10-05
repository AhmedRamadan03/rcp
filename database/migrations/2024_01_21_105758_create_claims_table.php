<?php

use App\Enums\ClaimInsertionType;
use App\Enums\ClaimStatus;
use App\Enums\ClaimType;
use App\Enums\DebtorType;
use App\Enums\ReturnReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade')
                ->comment('created by');

            $table->string('status')->default(ClaimStatus::REGISTERED->value);

            $table->enum('insertion_type', [
                'form',
                'exports file',
                'api',
            ])->default(ClaimInsertionType::FORM);

            $table->double('amount', 8, 2);
            $table->enum('type', [
                'individual',
                'corporate',
                'leasing',
                'government',
                'municipality',
            ])->default(ClaimType::INDIVIDUAL);

            $table->foreignId('city_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('file_id')
                ->nullable()
                ->references('id')
                ->on('uploaded_claims_files')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->enum('debtor_type', [
                'insured',
                'third party',
            ])->default(DebtorType::INSURED);

            $table->integer('return_reason')->default(ReturnReason::USAGE_OUT_POLICY->value)->nullable();

            $table->string('policy_number');
            $table->boolean('is_transformed_to_lawyer')->default(false);
            $table->boolean('is_transformed_to_naviz')->default(false);
            $table->string('claim_number');
            $table->text('debtor_name');
            $table->string('debtor_nid'); // KSA iqama ID
            $table->string('debtor_phone')->nullable();
            $table->dateTime('accident_date');

            // there are more columns
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliams');
    }
};
