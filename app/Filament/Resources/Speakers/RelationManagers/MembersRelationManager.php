<?php

namespace App\Filament\Resources\Speakers\RelationManagers;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Role;
use App\Filament\Resources\Authz\UserResource as AuthzUserResource;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use App\Support\Submission\PublicSubmissionLockService;
use App\Support\Submission\PublicSubmissionUiEvents;
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
                    ->sortable()
                    ->url(fn (User $record): ?string => AuthzUserResource::canEdit($record)
                        ? AuthzUserResource::getUrl('edit', ['record' => $record], panel: 'admin')
                        : null),
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
                        $speaker = $this->getSpeakerOwner();
                        $user = User::findOrFail($data['user_id']);

                        $speaker->members()->syncWithoutDetaching([$user->id]);
                        $this->syncMemberRoles($user, $data['role_ids'] ?? []);
                        app(PublicSubmissionLockService::class)->ensureSpeakerUnlockedIfIneligible($speaker->fresh());
                        $this->notifyOwnerEditPage();
                    }),
            ])
            ->actions([
                Action::make('manageRoles')
                    ->label('Roles')
                    ->form([
                        $this->makeRoleSelect(),
                    ])
                    ->fillForm(function (User $record): array {
                        return [
                            'role_ids' => $this->getMemberRoleIds($record),
                        ];
                    })
                    ->action(function (array $data, User $record): void {
                        $this->syncMemberRoles($record, $data['role_ids'] ?? []);
                        app(PublicSubmissionLockService::class)->syncForUser($record);
                        $this->notifyOwnerEditPage();
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $speaker = $this->getSpeakerOwner();

                        $speaker->members()->detach($record->id);
                        $this->syncMemberRoles($record, []);
                        app(PublicSubmissionLockService::class)->ensureSpeakerUnlockedIfIneligible($speaker->fresh());
                        $this->notifyOwnerEditPage();
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getScopedRoleOptions(): array
    {
        app(ScopedMemberRoleSeeder::class)->ensureForSpeaker();
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

    private function getSpeakerOwner(): Speaker
    {
        /** @var Speaker $speaker */
        $speaker = $this->getOwnerRecord();

        return $speaker;
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
        return app(MemberRoleScopes::class)->speaker();
    }

    private function notifyOwnerEditPage(): void
    {
        $this->dispatch(PublicSubmissionUiEvents::REFRESH_TOGGLE)->to($this->getPageClass());
    }
}
