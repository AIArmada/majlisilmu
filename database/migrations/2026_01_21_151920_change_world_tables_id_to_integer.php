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
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true)->change();
        });

        Schema::table('states', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true)->change();
            $table->unsignedMediumInteger('country_id')->change();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true)->change();
            $table->unsignedMediumInteger('country_id')->change();
            $table->unsignedMediumInteger('state_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true)->change();
        });

        Schema::table('states', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true)->change();
            $table->unsignedBigInteger('country_id')->change();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true)->change();
            $table->unsignedBigInteger('country_id')->change();
            $table->unsignedBigInteger('state_id')->change();
        });
    }
};
