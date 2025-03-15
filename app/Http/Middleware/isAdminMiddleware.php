<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class isAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->user()->hasRole('admin')) {
            return $next($request);
        }

        return response()->json([
            'status' => 'false',
            'message' => 'Permission denied',
        ], 403);
    }
}
