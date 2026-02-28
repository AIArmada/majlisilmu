<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media') || ! Schema::hasColumn('social_media', 'url')) {
            return;
        }

        Schema::table('social_media', function (Blueprint $table): void {
            $table->string('url')->nullable()->change();
        });
    }
};
