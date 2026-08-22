<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function actorFromJwt(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);

        foreach (['nontri_id', 'sub', 'name'] as $claim) {
            $actor = $claims[$claim] ?? null;

            if (is_scalar($actor) && trim((string) $actor) !== '') {
                return trim((string) $actor);
            }
        }

        return null;
    }
}
