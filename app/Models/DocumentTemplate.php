<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A document type such as Quotation or Purchase order: its starting body, reference prefix and letterhead. */
class DocumentTemplate extends Model
{
    use SoftDeletes;

    /** Placeholders a template body can use; they are filled in when the document is shown or printed. */
    public const PLACEHOLDERS = [
        'ref' => 'Reference number',
        'date' => 'Document date',
        'title' => 'Subject / title',
        'recipient' => 'To (recipient)',
        'project' => 'Project name',
        'client' => 'Client name',
        'company' => 'Company name',
    ];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function letterhead(): BelongsTo
    {
        return $this->belongsTo(Letterhead::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_id');
    }
}
