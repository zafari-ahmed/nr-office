<?php

namespace App\Models;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** A document written on the letterhead from a template. Every print is kept in document_prints. */
class Document extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['doc_date' => 'date', 'last_printed_at' => 'datetime'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id')->withTrashed();
    }

    public function letterhead(): BelongsTo
    {
        return $this->belongsTo(Letterhead::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prints(): HasMany
    {
        return $this->hasMany(DocumentPrint::class)->latest('printed_at');
    }

    /** The letterhead to print on: the one chosen, else the template's, else the default. */
    public function resolvedLetterhead(): ?Letterhead
    {
        return $this->letterhead ?? $this->template?->letterhead ?? Letterhead::default();
    }

    /** The body with {{placeholders}} filled in, cleaned for output. */
    public function renderedBody(): string
    {
        $values = [
            'ref' => $this->ref_no,
            'date' => $this->doc_date?->format('d M Y'),
            'title' => $this->title,
            'recipient' => $this->recipient,
            'project' => $this->project?->name,
            'client' => $this->project?->client?->name,
            'company' => Setting::get('company.name'),
        ];

        return self::fillPlaceholders(RichText::clean($this->body, true), $values);
    }

    /** @param array<string, ?string> $values */
    public static function fillPlaceholders(string $html, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($values) {
            $key = Str::lower($m[1]);

            return array_key_exists($key, $values) ? e((string) $values[$key]) : $m[0];
        }, $html);
    }
}
