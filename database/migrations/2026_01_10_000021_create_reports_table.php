<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('reporter_id')->nullable()->index();
            $table->uuid('handled_by')->nullable()->index();

            $table->string('entity_type')->index();
            $table->uuid('entity_id')->index();

            $table->enum('category', [
                'wrong_info',
                'cancelled_not_updated',
                'fake_speaker',
                'inappropriate_content',
                'donation_scam',
                'other',
            ])->index();

            $table->text('description')->nullable();

            $table->enum('status', ['open', 'triaged', 'resolved', 'dismissed'])->default('open')->index();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
