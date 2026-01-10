<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type')->default('masjid')->index();
            $table->string('name')->index();
            $table->string('slug')->unique();

            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('website_url')->nullable();

            $table->unsignedSmallInteger('state_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 16)->nullable();
            $table->string('city')->nullable();

            $table->decimal('lat', 10, 7)->nullable()->index();
            $table->decimal('lng', 10, 7)->nullable()->index();

            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')->index();
            $table->unsignedSmallInteger('trust_score')->default(0)->index();

            $table->timestamps();

            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
