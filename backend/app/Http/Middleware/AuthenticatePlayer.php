<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlayer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $player = Player::where('token', $token)->first();
        if (! $player) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $request->attributes->set('player', $player);

        return $next($request);
    }
}
