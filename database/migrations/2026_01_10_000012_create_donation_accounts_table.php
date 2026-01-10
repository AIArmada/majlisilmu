<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('institution_id')->index();

            $table->string('label')->nullable();
            $table->string('recipient_name')->index();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('duitnow_id')->nullable();
            $table->uuid('qr_asset_id')->nullable()->index();

            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')->index();

            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('qr_asset_id')->references('id')->on('media_assets')->nullOnDelete();
            $table->index(['institution_id', 'recipient_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_accounts');
    }
};
