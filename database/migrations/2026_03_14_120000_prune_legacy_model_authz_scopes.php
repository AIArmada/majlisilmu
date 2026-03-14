<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authz_scopes')) {
            return;
        }

        $scopeIds = DB::table('authz_scopes')
            ->whereIn('scopeable_type', [
                Institution::class,
                Speaker::class,
                Event::class,
                Reference::class,
            ])
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($scopeIds === []) {
            return;
        }

        $roleTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelHasPermissionsTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $teamsKey = config('permission.column_names.team_foreign_key', 'authz_scope_id');
        $rolePivotKey = config('permission.column_names.role_pivot_key', 'role_id') ?: 'role_id';

        $roleIds = Schema::hasTable($roleTable)
            ? DB::table($roleTable)
                ->whereIn($teamsKey, $scopeIds)
                ->pluck('id')
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values()
                ->all()
            : [];

        if ($roleIds !== []) {
            if (Schema::hasTable($modelHasRolesTable)) {
                DB::table($modelHasRolesTable)->whereIn($rolePivotKey, $roleIds)->delete();
            }

            if (Schema::hasTable($roleHasPermissionsTable)) {
                DB::table($roleHasPermissionsTable)->whereIn($rolePivotKey, $roleIds)->delete();
            }

            DB::table($roleTable)->whereIn('id', $roleIds)->delete();
        }

        if (Schema::hasTable($modelHasPermissionsTable)) {
            DB::table($modelHasPermissionsTable)->whereIn($teamsKey, $scopeIds)->delete();
        }

        DB::table('authz_scopes')->whereIn('id', $scopeIds)->delete();
    }
};
