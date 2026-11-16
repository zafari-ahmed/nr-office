<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One print of a document, with a copy of exactly what was printed. */
class DocumentPrint extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['doc_date' => 'date', 'printed_at' => 'datetime', 'with_letterhead' => 'boolean'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function letterhead(): BelongsTo
    {
        return $this->belongsTo(Letterhead::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
