<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Super admin using the app as another person in one browser tab ("Log in as").
 * The tab carries `_as={key}` on every request; see App\Http\Middleware\Impersonate.
 */
class Impersonation extends Model
{
    /** A tab left alone this long stops working. */
    public const IDLE_MINUTES = 120;

    public const END_REASONS = ['ended' => 'Ended', 'expired' => 'Timed out', 'signed_out' => 'Admin signed out', 'revoked' => 'Access changed'];

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'ended_at' => 'datetime'];

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /** Name of the encrypted cookie that ties this key to the browser that started it. */
    public static function cookieName(string $key): string
    {
        return 'nr_as_ok_'.$key;
    }

    /** Name of the separate session cookie the impersonating tab uses, so the admin's own session is never touched. */
    public static function sessionCookieName(string $key): string
    {
        return 'nr_as_'.$key;
    }

    public static function validKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^[A-Za-z0-9]{40}$/', $key) === 1;
    }

    public function isIdle(): bool
    {
        return ($this->last_seen_at ?? $this->started_at)->lt(now()->subMinutes(self::IDLE_MINUTES));
    }

    public function end(string $reason = 'ended'): void
    {
        if ($this->ended_at) {
            return;
        }
        $this->update(['ended_at' => now(), 'end_reason' => $reason]);
        $this->loadMissing('impersonator', 'user');
        ActivityLog::create([
            'user_id' => $this->impersonator_id,
            'subject_type' => $this->user?->employee?->getMorphClass(),
            'subject_id' => $this->user?->employee_id,
            'action' => 'impersonation_ended',
            'description' => "{$this->impersonator?->name} stopped logging in as {$this->user?->name} (".strtolower(self::END_REASONS[$reason] ?? $reason).')',
            'properties' => ['impersonation_id' => $this->id, 'reason' => $reason],
        ]);
    }
}
