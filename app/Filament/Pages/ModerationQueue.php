<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ModerationQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Moderation Queue';

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.moderation-queue';

    public string $activeTab = 'pending';

    public function mount(): void
    {
        $this->activeTab = request()->query('tab', 'pending');
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getTabs(): array
    {
        return [
            'pending' => [
                'label' => 'Pending',
                'icon' => 'heroicon-o-clock',
                'count' => Event::whereState('status', \App\States\EventStatus\Pending::class)->count(),
                'badgeColor' => 'warning',
            ],
            'needs_changes' => [
                'label' => 'Needs Changes',
                'icon' => 'heroicon-o-pencil-square',
                'count' => Event::whereHas('moderationReviews', fn ($q) => $q->where('decision', 'needs_changes')->latest()->limit(1))->whereState('status', \App\States\EventStatus\Pending::class)->count(),
                'badgeColor' => 'info',
            ],
            'reports' => [
                'label' => 'Reports',
                'icon' => 'heroicon-o-flag',
                'count' => \App\Models\Report::where('status', 'open')->count(),
                'badgeColor' => 'danger',
            ],
            'recently_rejected' => [
                'label' => 'Recently Rejected',
                'icon' => 'heroicon-o-x-circle',
                'count' => Event::whereState('status', \App\States\EventStatus\Rejected::class)->where('updated_at', '>=', now()->subDays(7))->count(),
                'badgeColor' => 'gray',
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title),
                TextColumn::make('institution.name')
                    ->label('Institution')
                    ->limit(20),
                TextColumn::make('starts_at')
                    ->label('Event Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state_id')
                    ->label('State')
                    ->relationship('state', 'name'),
                SelectFilter::make('institution_id')
                    ->label('Institution')
                    ->relationship('institution', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Event $record, \App\Services\ModerationService $service): void {
                        $service->approve($record, auth()->user());
                    })
                    ->visible(fn (Event $record) => $record->status instanceof \App\States\EventStatus\Pending),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Select::make('reason_code')
                            ->label('Reason')
                            ->options([
                                'incomplete_info' => 'Incomplete Information',
                                'duplicate' => 'Duplicate Event',
                                'inappropriate' => 'Inappropriate Content',
                                'spam' => 'Spam',
                                'wrong_category' => 'Wrong Category',
                                'other' => 'Other',
                            ])
                            ->required(),
                        Textarea::make('note')
                            ->label('Note to Submitter')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Event $record, array $data, \App\Services\ModerationService $service): void {
                        $service->reject($record, auth()->user(), $data['reason_code'], $data['note']);
                    })
                    ->visible(fn (Event $record) => $record->status instanceof \App\States\EventStatus\Pending),

                Action::make('needs_changes')
                    ->label('Request Changes')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Textarea::make('note')
                            ->label('What needs to be changed?')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Event $record, array $data, \App\Services\ModerationService $service): void {
                        $service->requestChanges($record, auth()->user(), 'needs_changes', $data['note']);
                    })
                    ->visible(fn (Event $record) => $record->status instanceof \App\States\EventStatus\Pending),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Event $record) => EventResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    protected function getTableQuery(): Builder
    {
        $query = Event::query()->with(['institution', 'state']);

        return match ($this->activeTab) {
            'pending' => $query->whereState('status', \App\States\EventStatus\Pending::class),
            'needs_changes' => $query->whereHas('moderationReviews', fn ($q) => $q->where('decision', 'needs_changes'))->whereState('status', \App\States\EventStatus\Pending::class),
            'recently_rejected' => $query->whereState('status', \App\States\EventStatus\Rejected::class)->where('updated_at', '>=', now()->subDays(7)),
            default => $query->whereState('status', \App\States\EventStatus\Pending::class),
        };
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'moderator']) ?? false;
    }
}
