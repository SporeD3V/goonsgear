<?php

namespace App\Http\Middleware;

use App\Models\UrlRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class HandleLegacyRedirects
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        if ($request->is('admin/*') || $request->is('api/*') || $request->is('up')) {
            return $next($request);
        }

        if (! $this->redirectTableExists()) {
            return $next($request);
        }

        $currentPath = UrlRedirect::normalizePath($request->getPathInfo());

        $redirectRule = UrlRedirect::query()
            ->where('is_active', true)
            ->where('from_path', $currentPath)
            ->first();

        if ($redirectRule !== null) {
            $destination = trim((string) $redirectRule->to_url);

            if ($destination !== '' && $destination !== $request->fullUrl() && $destination !== $currentPath) {
                return redirect($destination, (int) $redirectRule->status_code);
            }
        }

        return $next($request);
    }

    private function redirectTableExists(): bool
    {
        return Schema::hasTable('url_redirects');
    }
}
