<?php
namespace RahatulRabbi\TalkBridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $field = config('talkbridge.user_fields.last_seen', 'last_seen_at');
            if ($field) {
                $user->updateQuietly([$field => now()]);
            }
        }
        return $next($request);
    }
}
