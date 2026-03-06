<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutions')) {
            Schema::table('institutions', function (Blueprint $table): void {
                if (! Schema::hasColumn('institutions', 'allow_public_event_submission')) {
                    $table->boolean('allow_public_event_submission')->default(true)->index();
                }

                if (! Schema::hasColumn('institutions', 'public_submission_locked_at')) {
                    $table->timestamp('public_submission_locked_at')->nullable()->index();
                }

                if (! Schema::hasColumn('institutions', 'public_submission_locked_by')) {
                    $table->foreignUuid('public_submission_locked_by')->nullable()->index();
                }

                if (! Schema::hasColumn('institutions', 'public_submission_lock_reason')) {
                    $table->text('public_submission_lock_reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('speakers')) {
            Schema::table('speakers', function (Blueprint $table): void {
                if (! Schema::hasColumn('speakers', 'allow_public_event_submission')) {
                    $table->boolean('allow_public_event_submission')->default(true)->index();
                }

                if (! Schema::hasColumn('speakers', 'public_submission_locked_at')) {
                    $table->timestamp('public_submission_locked_at')->nullable()->index();
                }

                if (! Schema::hasColumn('speakers', 'public_submission_locked_by')) {
                    $table->foreignUuid('public_submission_locked_by')->nullable()->index();
                }

                if (! Schema::hasColumn('speakers', 'public_submission_lock_reason')) {
                    $table->text('public_submission_lock_reason')->nullable();
                }
            });
        }
    }
};
