<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_invitations')) {
            return;
        }

        Schema::create('member_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->foreignUuid('subject_id');
            $table->string('email');
            $table->string('role_slug');
            $table->string('token');
            $table->foreignUuid('invited_by');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUuid('accepted_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'member_invitations_subject_index');
            $table->index('email');
            $table->index('token');
        });
    }
};
