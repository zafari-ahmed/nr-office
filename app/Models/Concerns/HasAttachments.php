<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /**
     * @param  array<UploadedFile>|UploadedFile|null  $files
     */
    public function attachFiles(array|UploadedFile|null $files, string $collection = 'file'): void
    {
        foreach (array_filter(is_array($files) ? $files : [$files]) as $file) {
            $folder = str($this->getTable())->replace('_', '-').'/'.$this->getKey();
            $this->attachments()->create([
                'collection' => $collection,
                'disk' => 'public',
                'path' => $file->store($folder, 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
