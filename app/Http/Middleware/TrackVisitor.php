<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitor
{
    /**
     * Increment today's unique visitor count if not already recorded in this session.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionKey = 'visited_'.now()->format('Y-m-d');

        if (! $request->session()->has($sessionKey)) {
            $today = now()->toDateString();

            $affected = DB::table('daily_visits')
                ->where('date', $today)
                ->update(['visitor_count' => DB::raw('visitor_count + 1'), 'updated_at' => now()]);

            if ($affected === 0) {
                DB::table('daily_visits')->insertOrIgnore([
                    'date' => $today,
                    'visitor_count' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $request->session()->put($sessionKey, true);
        }

        return $next($request);
    }
}
