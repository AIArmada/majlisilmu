<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'dawah_share_share_events',
            'dawah_share_outcomes',
            'dawah_share_visits',
            'dawah_share_attributions',
            'dawah_share_links',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }
};
