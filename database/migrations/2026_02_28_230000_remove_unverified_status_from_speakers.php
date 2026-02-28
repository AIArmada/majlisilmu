<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('speakers')
            ->where('status', 'unverified')
            ->update(['status' => 'pending']);
    }
};
