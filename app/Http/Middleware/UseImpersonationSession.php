<?php

namespace App\Http\Middleware;

use App\Models\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before the session starts. A request from a "Log in as" tab carries `_as={key}` (in the URL, a form field,
 * or the page it came from), and gets its own session cookie for that key, so the tab never shares the
 * Super admin's session. Whether the key is genuine is checked afterwards by App\Http\Middleware\Impersonate.
 */
class UseImpersonationSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = self::keyFrom($request);
        $request->attributes->set('impersonation_key', $key);
        // The session store is shared within a long-running process (and in tests), so always set the name.
        app('session')->driver()->setName($key ? Impersonation::sessionCookieName($key) : config('session.cookie'));

        return $next($request);
    }

    public static function keyFrom(Request $request): ?string
    {
        $key = $request->query('_as', $request->request->get('_as'));
        if (Impersonation::validKey($key)) {
            return $key;
        }
        // A page in the tab that was opened without the key (e.g. by script): the page it came from still has it.
        $referer = (string) $request->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
            if (Impersonation::validKey($query['_as'] ?? null) && ! str_starts_with((string) parse_url($referer, PHP_URL_PATH), '/impersonation/ended')) {
                return $query['_as'];
            }
        }

        return null;
    }
}
