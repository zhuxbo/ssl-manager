<?php

namespace App\Http\Middleware;

class AdminAuthenticate extends Authenticate
{
    protected function guardName(): string
    {
        return 'admin';
    }
}
