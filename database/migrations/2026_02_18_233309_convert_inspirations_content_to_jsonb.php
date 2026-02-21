<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inspirations', 'content') || Schema::hasColumn('inspirations', 'content_jsonb')) {
            return;
        }

        Schema::table('inspirations', function (Blueprint $table) {
            $table->jsonb('content_jsonb')->nullable()->after('title');
        });

        DB::table('inspirations')
            ->select(['id', 'content'])
            ->orderBy('id')
            ->eachById(function (object $row): void {
                DB::table('inspirations')
                    ->where('id', $row->id)
                    ->update([
                        'content_jsonb' => json_encode([
                            'type' => 'doc',
                            'content' => [[
                                'type' => 'paragraph',
                                'content' => [[
                                    'type' => 'text',
                                    'text' => (string) $row->content,
                                ]],
                            ]],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            }, 'id');

        Schema::table('inspirations', function (Blueprint $table) {
            $table->dropColumn('content');
        });

        Schema::table('inspirations', function (Blueprint $table) {
            $table->renameColumn('content_jsonb', 'content');
        });
    }
};
