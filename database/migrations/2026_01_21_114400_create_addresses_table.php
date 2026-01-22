<?php

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
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('addressable');

            $table->string('type')->default('main')->index(); // e.g. main, billing, shipping

            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('postcode', 16)->nullable();

            $table->unsignedInteger('country_id')->nullable()->index();
            $table->unsignedInteger('state_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();
            $table->unsignedInteger('city_id')->nullable()->index();

            $table->decimal('lat', 10, 7)->nullable()->index();
            $table->decimal('lng', 10, 7)->nullable()->index();

            $table->string('google_place_id')->nullable()->index();
            $table->string('waze_url')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
