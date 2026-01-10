<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_members', function (Blueprint $table) {
            $table->uuid('institution_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'admin', 'editor'])->default('editor')->index();
            $table->timestamps();

            $table->primary(['institution_id', 'user_id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_members');
    }
};
