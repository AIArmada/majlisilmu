<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            if (! Schema::hasColumn('spaces', 'institution_id')) {
                $table->foreignUuid('institution_id')->nullable()->after('id')->index();
            }

            if (Schema::hasColumn('spaces', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
