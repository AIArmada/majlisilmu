<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Role;
use App\Support\Authz\MemberRoleScopes;
use App\Models\Institution;
use App\Models\User;
use App\Support\Authz\ScopedMemberRoleSeeder;
use App\Support\Submission\PublicSubmissionLockService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
                        return Authz::withScope($this->getRoleScope(), fn (): string => $record->getRoleNames()->implode(', '), $record) ?: '—';
                    }),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add member')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->options(User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        $this->makeRoleSelect(),
                    ])
                    ->action(function (array $data): void {
                        $institution = $this->getInstitutionOwner();
                        $user = User::findOrFail($data['user_id']);

                        $institution->members()->syncWithoutDetaching([$user->id]);
                        $this->syncMemberRoles($user, $data['role_ids'] ?? []);
                        app(PublicSubmissionLockService::class)->ensureInstitutionUnlockedIfIneligible($institution->fresh());
                    }),
            ])
            ->actions([
                Action::make('manageRoles')
                    ->label('Roles')
                    ->form([
                        $this->makeRoleSelect(),
                    ])
                    ->mountUsing(function (Action $action, User $record): void {
                        $action->fillForm([
                            'role_ids' => $this->getMemberRoleIds($record),
                        ]);
                    })
                    ->action(function (array $data, User $record): void {
                        $this->syncMemberRoles($record, $data['role_ids'] ?? []);
                        app(PublicSubmissionLockService::class)->syncForUser($record);
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $institution = $this->getInstitutionOwner();

                        $institution->members()->detach($record->id);
                        $this->syncMemberRoles($record, []);
                        app(PublicSubmissionLockService::class)->ensureInstitutionUnlockedIfIneligible($institution->fresh());
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getScopedRoleOptions(): array
    {
        app(ScopedMemberRoleSeeder::class)->ensureForInstitution();
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

    private function getInstitutionOwner(): Institution
    {
        /** @var Institution $institution */
        $institution = $this->getOwnerRecord();

        return $institution;
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
        return app(MemberRoleScopes::class)->institution();
    }
}
