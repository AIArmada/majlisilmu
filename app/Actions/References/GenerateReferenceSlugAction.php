<?php

namespace App\Actions\References;

use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Models\Reference;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateReferenceSlugAction
{
    use AsAction;

    public function __construct(
        private readonly SyncSlugRedirectAction $syncSlugRedirectAction,
    ) {}

    public function syncReferenceSlugsForTitle(string $title): bool
    {
        $normalizedTitle = trim($title);

        if ($normalizedTitle === '') {
            return false;
        }

        $references = Reference::query()
            ->where('references.title', $normalizedTitle)
            ->get();

        $didChange = false;

        foreach ($this->orderedReferences($references) as $reference) {
            $didChange = $this->syncReferenceSlug($reference) || $didChange;
        }

        return $didChange;
    }

    public function syncReferenceSlug(Reference $reference): bool
    {
        $slug = $this->forReference($reference);

        if ($reference->slug === $slug) {
            return false;
        }

        $previousSlug = is_string($reference->slug) ? $reference->slug : null;

        Reference::withoutTimestamps(function () use ($reference, $slug): void {
            $reference->forceFill([
                'slug' => $slug,
            ])->saveQuietly();
        });

        $this->syncSlugRedirectAction->handle($reference, $previousSlug);

        return true;
    }

    public function handle(?string $title, ?string $ignoreReferenceId = null): string
    {
        $normalizedTitle = trim((string) $title);
        $titleSlug = Str::slug($normalizedTitle);

        if ($titleSlug === '') {
            $titleSlug = 'rujukan';
        }

        $sequence = $this->nextSequenceForExactTitle($normalizedTitle, $ignoreReferenceId);

        do {
            $candidate = $titleSlug;

            if ($sequence > 1) {
                $candidate .= '-'.$sequence;
            }

            $sequence++;
        } while ($this->slugExists($candidate, $ignoreReferenceId));

        return $candidate;
    }

    public function forReference(Reference $reference): string
    {
        return $this->handle($reference->title, (string) $reference->getKey());
    }

    private function nextSequenceForExactTitle(string $title, ?string $ignoreReferenceId): int
    {
        $matchingReferences = Reference::query()
            ->where('references.title', $title)
            ->get();

        if ($ignoreReferenceId !== null && $ignoreReferenceId !== '') {
            $existingSequence = $this->existingReferenceSequence($matchingReferences, $ignoreReferenceId);

            if ($existingSequence !== null) {
                return $existingSequence;
            }

            $matchingReferences = $matchingReferences
                ->reject(fn (Reference $reference): bool => (string) $reference->getKey() === $ignoreReferenceId)
                ->values();
        }

        $matchingCount = $matchingReferences->count();

        return $matchingCount > 0 ? $matchingCount + 1 : 1;
    }

    /**
     * @param  Collection<int, Reference>  $matchingReferences
     */
    private function existingReferenceSequence(Collection $matchingReferences, string $referenceId): ?int
    {
        $orderedReferences = $this->orderedReferences($matchingReferences);

        $existingIndex = $orderedReferences->search(
            fn (Reference $reference): bool => (string) $reference->getKey() === $referenceId,
        );

        if (! is_int($existingIndex)) {
            return null;
        }

        return $existingIndex + 1;
    }

    /**
     * @param  Collection<int, Reference>  $references
     * @return Collection<int, Reference>
     */
    private function orderedReferences(Collection $references): Collection
    {
        return $references
            ->sort(function (Reference $left, Reference $right): int {
                $leftCreatedAt = $left->created_at?->getTimestamp() ?? 0;
                $rightCreatedAt = $right->created_at?->getTimestamp() ?? 0;

                if ($leftCreatedAt !== $rightCreatedAt) {
                    return $leftCreatedAt <=> $rightCreatedAt;
                }

                return strcmp((string) $left->getKey(), (string) $right->getKey());
            })
            ->values();
    }

    private function slugExists(string $slug, ?string $ignoreReferenceId): bool
    {
        return Reference::query()
            ->where('slug', $slug)
            ->when(
                $ignoreReferenceId !== null && $ignoreReferenceId !== '',
                fn ($query) => $query->where('references.id', '!=', $ignoreReferenceId),
            )
            ->exists();
    }
}
