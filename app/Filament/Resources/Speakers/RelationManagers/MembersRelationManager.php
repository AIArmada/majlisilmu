<?php

namespace App\Filament\Resources\Speakers\RelationManagers;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Role;
use App\Models\Speaker;
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
                        $speaker = $this->getSpeakerOwner();

                        return Authz::withScope($speaker, fn (): string => $record->getRoleNames()->implode(', '), $record) ?: '—';
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
                        $speaker = $this->getSpeakerOwner();
                        $user = User::findOrFail($data['user_id']);

                        $speaker->members()->syncWithoutDetaching([$user->id]);
                        $this->syncMemberRoles($speaker, $user, $data['role_ids'] ?? []);
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
                        $speaker = $this->getSpeakerOwner();

                        $this->syncMemberRoles($speaker, $record, $data['role_ids'] ?? []);
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $speaker = $this->getSpeakerOwner();

                        $speaker->members()->detach($record->id);
                        $this->syncMemberRoles($speaker, $record, []);
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getScopedRoleOptions(): array
    {
        $speaker = $this->getSpeakerOwner();
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        return Authz::withScope($speaker, fn (): array => Role::query()
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
        $speaker = $this->getSpeakerOwner();

        return Authz::withScope($speaker, fn (): array => $user->roles()->pluck('id')->all(), $user);
    }

    /**
     * @param  list<string>  $roleIds
     */
    protected function syncMemberRoles(Speaker $speaker, User $user, array $roleIds): void
    {
        Authz::withScope($speaker, function () use ($user, $roleIds): void {
            $user->syncRoles($roleIds);
        }, $user);
    }

    private function getSpeakerOwner(): Speaker
    {
        /** @var Speaker $speaker */
        $speaker = $this->getOwnerRecord();

        return $speaker;
    }
}
