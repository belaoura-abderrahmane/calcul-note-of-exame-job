<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * في وضع سطح المكتب (مستخدم واحد على جهاز واحد) لا حاجة لشاشة الدخول.
 */
class DesktopAutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.desktop') && ! Auth::check() && ($user = User::query()->orderBy('id')->first())) {
            Auth::login($user, remember: true);
        }

        return $next($request);
    }
}
