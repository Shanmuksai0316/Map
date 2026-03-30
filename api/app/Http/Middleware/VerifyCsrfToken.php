<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * Login is not excluded – ensure SESSION_DOMAIN is unset (single domain)
     * and session config is correct so CSRF token is valid on login.
     * livewire/upload-file is excluded because the upload URL is signed (expires + signature)
     * and validated by Livewire; the signed URL provides request verification.
     */
    protected $except = [
        'livewire/upload-file',
    ];
}
