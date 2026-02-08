<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // Status: pending (user-created, awaiting verification), verified (approved by moderator)
            $table->string('status')->default('verified')->after('type');
            $table->index(['type', 'status', 'order_column'], 'tags_type_status_order');
        });

        // Set all existing tags to 'verified' (they're pre-seeded admin tags)
        DB::table('tags')->update(['status' => 'verified']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex('tags_type_status_order');
            $table->dropColumn('status');
        });
    }
};
