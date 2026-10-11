<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveTypeController extends Controller
{
    /**
     * Types de congés actifs et leurs règles en vigueur, pour les formulaires.
     */
    public function index(): AnonymousResourceCollection
    {
        return LeaveTypeResource::collection(
            LeaveType::query()->active()->with('currentPolicy')->orderBy('name')->get()
        );
    }
}
