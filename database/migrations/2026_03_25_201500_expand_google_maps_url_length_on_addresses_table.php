<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addresses') || ! Schema::hasColumn('addresses', 'google_maps_url')) {
            return;
        }

        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('google_maps_url', 500)->nullable()->change();
        });
    }
};
