<?php

namespace App\Forms;

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\Gender;
use App\Forms\Components\Select;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SpeakerFormSchema
{
    /**
     * Shared createOptionForm for Speaker selects.
     *
     * @return array<int, Component>
     */
    public static function createOptionForm(): array
    {
        $components = SpeakerContributionFormSchema::components(includeMedia: true);

        array_splice($components, 2, 0, [
            Section::make(__('Affiliated Institution'))
                ->schema([
                    Select::make('institution_id')
                        ->label(__('Affiliated Institution'))
                        ->options(fn () => Institution::query()
                            ->whereIn('status', ['verified', 'pending'])
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->closeOnSelect()
                        ->createOptionForm(InstitutionFormSchema::createOptionForm())
                        ->createOptionUsing(fn (array $data, Schema $schema): string => InstitutionFormSchema::createOptionUsing($data, $schema)),
                    TextInput::make('institution_position')
                        ->label(__('Position'))
                        ->maxLength(255)
                        ->placeholder(__('e.g., Imam, Mudir, Committee Member'))
                        ->visible(fn (Get $get): bool => filled($get('institution_id'))),
                ])
                ->columns(2),
        ]);

        return $components;
    }

    /**
     * Shared createOptionUsing callback for Speaker selects.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createOptionUsing(array $data, ?Schema $schema = null): string
    {
        $speaker = Speaker::create([
            'name' => $data['name'],
            'gender' => $data['gender'] ?? Gender::Male->value,
            'honorific' => empty($data['honorific']) ? null : $data['honorific'],
            'pre_nominal' => empty($data['pre_nominal']) ? null : $data['pre_nominal'],
            'post_nominal' => empty($data['post_nominal']) ? null : $data['post_nominal'],
            'job_title' => $data['job_title'] ?? null,
            'bio' => $data['bio'] ?? null,
            'qualifications' => self::normalizeQualificationEntries($data['qualifications'] ?? []),
            'is_freelance' => (bool) ($data['is_freelance'] ?? false),
            'slug' => Str::slug((string) $data['name']).'-'.Str::lower(Str::random(7)),
            'status' => 'pending',
        ]);

        $creator = auth()->user();

        if ($creator instanceof User) {
            app(AddMemberToSubject::class)->handle($speaker, $creator);
        }

        // Save media uploads (avatar/cover) via Filament's relationship-saving mechanism
        $schema?->model($speaker)->saveRelationships();

        if (array_key_exists('language_ids', $data)) {
            $speaker->languages()->sync(self::normalizeIntegerIds((array) $data['language_ids']));
        }

        SharedFormSchema::createAddressFromData($speaker, $data);
        SharedFormSchema::createContactsFromData($speaker, $data);
        SharedFormSchema::createSocialMediaFromData($speaker, $data);

        $institutionIds = [];

        if (filled($data['institution_id'] ?? null)) {
            $institutionIds[] = (string) $data['institution_id'];
        } elseif (! empty($data['institutions'])) {
            $institutionIds = array_filter(
                array_map(
                    fn (mixed $institutionId): ?string => filled($institutionId) ? (string) $institutionId : null,
                    (array) $data['institutions']
                )
            );
        }

        if ($institutionIds !== []) {
            $position = filled($data['institution_position'] ?? null) ? $data['institution_position'] : null;
            $institutionData = [];

            foreach ($institutionIds as $institutionId) {
                $institutionData[$institutionId] = [
                    'position' => $position,
                ];
            }

            $speaker->institutions()->attach($institutionData);
        }

        return (string) $speaker->getKey();
    }

    /**
     * @param  iterable<int, mixed>  $qualifications
     * @return list<array{institution: string, degree: string, field: string|null, year: string|null}>
     */
    private static function normalizeQualificationEntries(iterable $qualifications): array
    {
        return collect($qualifications)
            ->map(function (mixed $qualification): ?array {
                if (! is_array($qualification)) {
                    return null;
                }

                $institution = is_string($qualification['institution'] ?? null) ? trim($qualification['institution']) : null;
                $degree = is_string($qualification['degree'] ?? null) ? trim($qualification['degree']) : null;
                $field = is_string($qualification['field'] ?? null) ? trim($qualification['field']) : null;
                $year = is_string($qualification['year'] ?? null) ? trim($qualification['year']) : null;

                if (blank($institution) || blank($degree)) {
                    return null;
                }

                return [
                    'institution' => $institution,
                    'degree' => $degree,
                    'field' => filled($field) ? $field : null,
                    'year' => filled($year) ? $year : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $values
     * @return list<int>
     */
    private static function normalizeIntegerIds(iterable $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null)
            ->filter(fn (?int $value): bool => $value !== null)
            ->unique()
            ->values()
            ->all();
    }
}
