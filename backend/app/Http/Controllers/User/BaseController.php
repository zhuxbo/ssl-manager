<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\JWTGuard;

class BaseController extends Controller
{
    protected JWTGuard $guard;

    public function __construct()
    {
        // @phpstan-ignore assign.propertyType
        $this->guard = Auth::guard('user');
    }
}
