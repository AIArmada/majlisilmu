<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventMembersRelationManager extends RelationManager
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
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'organizer' => 'success',
                        'co-organizer' => 'info',
                        'moderator' => 'warning',
                        'volunteer' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('-', ' ', $state))),
                TextColumn::make('pivot.joined_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add Member')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->options(function () {
                                $existingMemberIds = $this->getOwnerRecord()->members()->pluck('users.id')->toArray();

                                return User::query()
                                    ->whereNotIn('id', $existingMemberIds)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'organizer' => 'Organizer',
                                'co-organizer' => 'Co-Organizer',
                                'moderator' => 'Moderator',
                                'volunteer' => 'Volunteer',
                                'member' => 'Member',
                            ])
                            ->default('member')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->members()->attach($data['user_id'], [
                            'role' => $data['role'],
                            'joined_at' => now(),
                        ]);
                    }),
            ])
            ->actions([
                Action::make('changeRole')
                    ->label('Change Role')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'organizer' => 'Organizer',
                                'co-organizer' => 'Co-Organizer',
                                'moderator' => 'Moderator',
                                'volunteer' => 'Volunteer',
                                'member' => 'Member',
                            ])
                            ->required(),
                    ])
                    ->mountUsing(function (Action $action, User $record): void {
                        $action->fillForm([
                            'role' => $record->pivot->role,
                        ]);
                    })
                    ->action(function (array $data, User $record): void {
                        $this->getOwnerRecord()->members()->updateExistingPivot($record->id, [
                            'role' => $data['role'],
                        ]);
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $this->getOwnerRecord()->members()->detach($record->id);
                    }),
            ]);
    }
}
