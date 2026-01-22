<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Role;
use App\Models\Institution;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles')
                    ->label('Roles')
                    ->getStateUsing(function (User $record): string {
                        $institution = $this->getOwnerRecord();

                        return Authz::withScope($institution, function () use ($record): string {
                            return $record->getRoleNames()->implode(', ');
                        }, $record) ?: '—';
                    }),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add member')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->options(User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('role_ids')
                            ->label('Roles')
                            ->options(fn () => $this->getScopedRoleOptions())
                            ->multiple()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $institution = $this->getOwnerRecord();
                        $user = User::findOrFail($data['user_id']);

                        $institution->members()->syncWithoutDetaching([$user->id]);
                        $this->syncMemberRoles($institution, $user, $data['role_ids'] ?? []);
                    }),
            ])
            ->actions([
                Action::make('manageRoles')
                    ->label('Roles')
                    ->form([
                        Forms\Components\Select::make('role_ids')
                            ->label('Roles')
                            ->options(fn () => $this->getScopedRoleOptions())
                            ->multiple()
                            ->required(),
                    ])
                    ->mountUsing(function (Action $action, User $record): void {
                        $action->fillForm([
                            'role_ids' => $this->getMemberRoleIds($record),
                        ]);
                    })
                    ->action(function (array $data, User $record): void {
                        $institution = $this->getOwnerRecord();

                        $this->syncMemberRoles($institution, $record, $data['role_ids'] ?? []);
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $institution = $this->getOwnerRecord();

                        $institution->members()->detach($record->id);
                        $this->syncMemberRoles($institution, $record, []);
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getScopedRoleOptions(): array
    {
        $institution = $this->getOwnerRecord();
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        return Authz::withScope($institution, function () use ($teamsKey): array {
            return Role::query()
                ->where($teamsKey, getPermissionsTeamId())
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        });
    }

    /**
     * @return list<string>
     */
    protected function getMemberRoleIds(User $user): array
    {
        $institution = $this->getOwnerRecord();

        return Authz::withScope($institution, function () use ($user): array {
            return $user->roles()->pluck('id')->all();
        }, $user);
    }

    /**
     * @param  list<string>  $roleIds
     */
    protected function syncMemberRoles(Institution $institution, User $user, array $roleIds): void
    {
        Authz::withScope($institution, function () use ($user, $roleIds): void {
            $user->syncRoles($roleIds);
        }, $user);
    }
}
