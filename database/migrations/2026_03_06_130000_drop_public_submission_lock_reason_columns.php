<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'public_submission_lock_reason')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->dropColumn('public_submission_lock_reason');
            });
        }

        if (Schema::hasTable('speakers') && Schema::hasColumn('speakers', 'public_submission_lock_reason')) {
            Schema::table('speakers', function (Blueprint $table): void {
                $table->dropColumn('public_submission_lock_reason');
            });
        }
    }
};
