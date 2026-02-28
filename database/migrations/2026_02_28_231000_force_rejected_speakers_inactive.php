<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('speakers')
            ->where('status', 'rejected')
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
};
