<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Admin panel is working!',
            'status' => 'success',
            'data' => [
                'users_count' => \App\Models\User::count(),
                'tenants_count' => \App\Models\Tenant::count(),
                'timestamp' => now()
            ]
        ]);
    }
}
