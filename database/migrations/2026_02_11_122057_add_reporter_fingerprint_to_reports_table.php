<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports') || Schema::hasColumn('reports', 'reporter_fingerprint')) {
            return;
        }

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('reporter_fingerprint', 128)
                ->nullable()
                ->after('reporter_id')
                ->index();
        });
    }
};
