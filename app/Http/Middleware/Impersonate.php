<?php

namespace App\Http\Middleware;

use App\Models\Impersonation;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a "Log in as" tab in as the employee for this request only. The key must be active, belong to a Super admin
 * who is still active, and come with the encrypted cookie set when that admin started it (so a copied link
 * is useless in another browser). The admin's own tabs never send the key, so they stay signed in as the admin.
 */
class Impersonate
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->attributes->get('impersonation_key');
        View::share('impersonation', null);
        if (! $key) {
            return $next($request);
        }
        if ($request->routeIs('impersonation.ended')) {
            return $next($request);
        }

        $impersonation = Impersonation::with('impersonator', 'user')->where('key', $key)->first();
        $reason = match (true) {
            ! $impersonation, $impersonation->ended_at !== null => 'ended',
            $request->cookie(Impersonation::cookieName($key)) !== $impersonation->key => 'other_browser',
            ! $impersonation->impersonator?->is_active || ! $impersonation->impersonator->isAdmin() => 'revoked',
            ! $impersonation->user?->is_active || $impersonation->user->isAdmin() => 'revoked',
            $impersonation->isIdle() => 'expired',
            default => null,
        };
        if ($reason) {
            Auth::guard('web')->forgetUser();
            if (in_array($reason, ['revoked', 'expired'], true)) {
                $impersonation->end($reason);
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'This "log in as" tab has ended.'], 401)
                : redirect()->route('impersonation.ended', ['reason' => $reason]);
        }

        // Signed out from inside the tab: leave the employee's account rather than signing anyone out.
        if ($request->routeIs('logout')) {
            return redirect()->route('impersonation.end', ['_as' => $key], 307);
        }

        // Opened without the key (it was found from the page it came from): put it in the address so later links carry it.
        if ($request->isMethod('GET') && ! $request->query('_as') && ! $request->ajax() && ! $request->expectsJson()) {
            return redirect(self::withKey($request->fullUrl(), $key, $request));
        }

        Auth::guard('web')->setUser($impersonation->user);
        $request->attributes->set('impersonation', $impersonation);
        View::share('impersonation', $impersonation);
        if (! $impersonation->last_seen_at || $impersonation->last_seen_at->lt(now()->subMinute())) {
            $impersonation->forceFill(['last_seen_at' => now()])->save();
        }

        $response = $next($request);

        // Keep redirects inside the tab's account.
        if ($response instanceof RedirectResponse && ! $request->routeIs('impersonation.end')) {
            $response->setTargetUrl(self::withKey($response->getTargetUrl(), $key, $request));
        }
        return $response;
    }

    public static function withKey(string $url, string $key, Request $request): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (($host && $host !== $request->getHost()) || str_contains($url, '_as=')) {
            return $url;
        }
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        return $base.(str_contains($base, '?') ? '&' : '?').'_as='.$key.($fragment !== null ? '#'.$fragment : '');
    }
}
