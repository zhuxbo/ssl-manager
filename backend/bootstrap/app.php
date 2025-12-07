<?php

use App\Bootstrap\ApiExceptions;
use App\Bootstrap\ApiMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        (new ApiMiddleware)->handle($middleware);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        (new ApiExceptions)->handle($exceptions);
    })
    ->create();
