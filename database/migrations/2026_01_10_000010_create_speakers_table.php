<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->index();
            $table->string('gender')->nullable()->default('male'); // male, female
            $table->string('honorific')->nullable()->index(); // Dato’, Datin, Tan Sri, Tun
            $table->string('pre_nominal')->nullable(); // Dr, Prof, Ir, Ustaz
            $table->string('post_nominal')->nullable(); // PhD, HONS, MSc
            $table->string('slug')->unique();
            $table->text('bio')->nullable();

            $table->jsonb('qualifications')->nullable();
            $table->boolean('is_freelance')->default(false);
            $table->string('job_title')->nullable();

            $table->string('status')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speakers');
    }
};
