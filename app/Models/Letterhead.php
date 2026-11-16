<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A letterhead: the header and footer images cut from an uploaded PDF or Word file (done in the browser, see
 * resources/js/letterhead.js), with how much space they take so the content fits between them on every page.
 */
class Letterhead extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['is_default' => 'boolean', 'header_mm' => 'float', 'footer_mm' => 'float', 'side_mm' => 'float'];

    public static function default(): ?self
    {
        return static::orderByDesc('is_default')->orderBy('id')->first();
    }

    public function imageUrl(string $part): ?string
    {
        $path = $part === 'header' ? $this->header_path : $this->footer_path;

        return $path ? route('letterheads.image', [$this, $part]).'?v='.$this->updated_at?->timestamp : null;
    }
}
