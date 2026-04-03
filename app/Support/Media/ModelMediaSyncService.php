<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

class ModelMediaSyncService
{
    public function clearCollection(HasMedia $model, string $collection): void
    {
        $model->clearMediaCollection($collection);
    }

    public function syncSingle(HasMedia $model, ?UploadedFile $file, string $collection): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $model->clearMediaCollection($collection);
        $model->addMedia($file)->toMediaCollection($collection);
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     */
    public function syncMultiple(HasMedia $model, ?array $files, string $collection, bool $replace = false): void
    {
        if ($replace) {
            $model->clearMediaCollection($collection);
        }

        foreach ($files ?? [] as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $model->addMedia($file)->toMediaCollection($collection);
        }
    }
}
