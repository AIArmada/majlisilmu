<?php

declare(strict_types=1);

use App\Support\Signals\ProductSignalsSurfaceResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('signals.database.tables.tracked_properties', 'signal_tracked_properties');

        if (! Schema::hasTable($table)) {
            return;
        }

        $surfaceResolver = app(ProductSignalsSurfaceResolver::class);
        $appName = (string) config('app.name', 'Application');
        $slug = $surfaceResolver->slugForSurface('public') ?? (Str::slug($appName) ?: 'application');
        $existing = DB::table($table)
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->where('slug', $slug)
            ->first();

        $payload = [
            'name' => $appName.' Website',
            'slug' => $slug,
            'domain' => $surfaceResolver->domainForSurface('public'),
            'type' => (string) config('signals.defaults.property_type', 'website'),
            'timezone' => (string) config('signals.defaults.timezone', config('app.timezone', 'UTC')),
            'currency' => (string) config('signals.defaults.currency', 'MYR'),
            'is_active' => true,
            'settings' => null,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table($table)
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        DB::table($table)->insert([
            ...$payload,
            'id' => (string) Str::uuid(),
            'write_key' => Str::random(40),
            'owner_type' => null,
            'owner_id' => null,
            'created_at' => now(),
        ]);
    }
};
