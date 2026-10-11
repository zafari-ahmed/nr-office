<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentLog extends Model
{
    use HasAttachments, SoftDeletes;

    public const TYPES = ['quotation' => 'Quotation', 'invoice' => 'Invoice'];

    public const STATUSES = [
        'sent' => 'Sent',
        'client_replied' => 'Client replied',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'sent_date' => 'date',
        'next_follow_up_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function followUp(): MorphOne
    {
        return $this->morphOne(ProjectFollowUp::class, 'source');
    }
}
