<?php

namespace App\Filament\Resources\Events\RelationManagers;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Role;
use App\Models\Event;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;

class EventUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Team Members';

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
                    ->getStateUsing(fn (User $record): string => Authz::withScope($this->getRoleScope(), fn (): string => $record->getRoleNames()->implode(', '), $record) ?: '—'),
                TextColumn::make('pivot.joined_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add Member')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->options(function () {
                                $existingMemberIds = $this->getEventOwner()->members()->pluck('users.id')->toArray();

                                return User::query()
                                    ->whereNotIn('id', $existingMemberIds)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                        $this->makeRoleSelect(),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getEventOwner();
                        $user = User::findOrFail($data['user_id']);

                        $event->members()->syncWithoutDetaching([$user->id => [
                            'joined_at' => now(),
                        ]]);
                        $this->syncMemberRoles($user, $data['role_ids'] ?? []);
                    }),
            ])
            ->actions([
                Action::make('manageRoles')
                    ->label('Roles')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        $this->makeRoleSelect(),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'role_ids' => $this->getMemberRoleIds($record),
                    ])
                    ->action(function (array $data, User $record): void {
                        $this->syncMemberRoles($record, $data['role_ids'] ?? []);
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $event = $this->getEventOwner();

                        $event->members()->detach($record->id);
                        $this->syncMemberRoles($record, []);
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getScopedRoleOptions(): array
    {
        app(ScopedMemberRoleSeeder::class)->ensureForEvent();
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $scope = $this->getRoleScope();

        return Authz::withScope($scope, fn (): array => Role::query()
            ->where($teamsKey, getPermissionsTeamId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all());
    }

    /**
     * @return list<string>
     */
    protected function getMemberRoleIds(User $user): array
    {
        return Authz::withScope($this->getRoleScope(), fn (): array => $user->roles()->pluck('id')->all(), $user);
    }

    /**
     * @param  list<string>  $roleIds
     */
    protected function syncMemberRoles(User $user, array $roleIds): void
    {
        Authz::withScope($this->getRoleScope(), function () use ($user, $roleIds): void {
            $user->syncRoles($roleIds);
        }, $user);
    }

    private function getEventOwner(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    private function makeRoleSelect(): Select
    {
        return Select::make('role_ids')
            ->label('Roles')
            ->options(fn () => $this->getScopedRoleOptions())
            ->multiple()
            ->required();
    }

    private function getRoleScope(): AuthzScope
    {
        return app(MemberRoleScopes::class)->event();
    }
}
