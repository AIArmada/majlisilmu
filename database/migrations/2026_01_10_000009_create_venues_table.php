<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('institution_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('slug')->unique();

            $table->unsignedSmallInteger('state_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 16)->nullable();
            $table->string('city')->nullable();

            $table->decimal('lat', 10, 7)->nullable()->index();
            $table->decimal('lng', 10, 7)->nullable()->index();

            $table->string('google_maps_place_id')->nullable()->index();
            $table->string('waze_place_url')->nullable();

            $table->json('facilities')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
