<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\ModerationReview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
                'count' => Event::where('status', 'pending')->count(),
                'badgeColor' => 'warning',
            ],
            'needs_changes' => [
                'label' => 'Needs Changes',
                'icon' => 'heroicon-o-pencil-square',
                'count' => Event::whereHas('moderationReviews', fn ($q) => $q->where('decision', 'needs_changes')->latest()->limit(1))->where('status', 'pending')->count(),
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
                'count' => Event::where('status', 'rejected')->where('updated_at', '>=', now()->subDays(7))->count(),
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
                    ->action(function (Event $record): void {
                        $this->approveEvent($record);
                    })
                    ->visible(fn (Event $record) => $record->status === 'pending'),

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
                    ->action(function (Event $record, array $data): void {
                        $this->rejectEvent($record, $data['reason_code'], $data['note']);
                    })
                    ->visible(fn (Event $record) => $record->status === 'pending'),

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
                    ->action(function (Event $record, array $data): void {
                        $this->requestChanges($record, $data['note']);
                    })
                    ->visible(fn (Event $record) => $record->status === 'pending'),

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
            'pending' => $query->where('status', 'pending'),
            'needs_changes' => $query->whereHas('moderationReviews', fn ($q) => $q->where('decision', 'needs_changes'))->where('status', 'pending'),
            'recently_rejected' => $query->where('status', 'rejected')->where('updated_at', '>=', now()->subDays(7)),
            default => $query->where('status', 'pending'),
        };
    }

    protected function approveEvent(Event $event): void
    {
        $event->update([
            'status' => 'approved',
            'published_at' => now(),
        ]);

        ModerationReview::create([
            'event_id' => $event->id,
            'reviewer_id' => auth()->id(),
            'decision' => 'approved',
        ]);

        Notification::make()
            ->title('Event Approved')
            ->success()
            ->send();
    }

    protected function rejectEvent(Event $event, string $reasonCode, string $note): void
    {
        $event->update(['status' => 'rejected']);

        ModerationReview::create([
            'event_id' => $event->id,
            'reviewer_id' => auth()->id(),
            'decision' => 'rejected',
            'reason_code' => $reasonCode,
            'note' => $note,
        ]);

        Notification::make()
            ->title('Event Rejected')
            ->warning()
            ->send();
    }

    protected function requestChanges(Event $event, string $note): void
    {
        ModerationReview::create([
            'event_id' => $event->id,
            'reviewer_id' => auth()->id(),
            'decision' => 'needs_changes',
            'note' => $note,
        ]);

        Notification::make()
            ->title('Changes Requested')
            ->info()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'moderator']) ?? false;
    }
}
