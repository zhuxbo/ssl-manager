<?php

namespace App\Http\Middleware;

class UserAuthenticate extends Authenticate
{
    protected function guardName(): string
    {
        return 'user';
    }
}
