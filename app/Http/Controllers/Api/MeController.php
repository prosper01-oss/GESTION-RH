<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Utilisateur connecté, avec ses rôles et permissions.
     */
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('roles.permissions'));
    }
}
