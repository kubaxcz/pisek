<?php

declare(strict_types=1);

namespace Piskari\Auth;

use Piskari\Config\Config;
use Piskari\Http\Request;

final class AdminAuth
{
    /**
     * Returns true when the request carries the correct admin secret.
     * The secret is sent in the X-Admin-Password header.
     */
    public static function check(Request $request): bool
    {
        $expected = Config::getString('ADMIN_PASSWORD');
        if ($expected === null || $expected === '') {
            // No password configured -> admin endpoints are locked down.
            return false;
        }

        $provided = $request->getHeader('x-admin-password');
        if ($provided === null) {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
