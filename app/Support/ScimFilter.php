<?php

namespace App\Support;

/**
 * A minimal SCIM filter expression parser -- real SCIM filter grammar
 * (RFC 7644 §3.4.2.2) supports full boolean logic and multiple operators,
 * but the overwhelming majority of real provisioning traffic only ever
 * sends a single `attribute eq "value"` check (an IdP confirming "does
 * this user already exist" before creating one). That single case is what
 * this parses; anything more complex is treated as unparseable rather than
 * guessed at.
 */
class ScimFilter
{
    public static function parse(?string $filter): ?array
    {
        if (! $filter) {
            return null;
        }

        if (! preg_match('/^\s*([a-zA-Z0-9_.\[\]" =]+?)\s+eq\s+"?([^"]*)"?\s*$/i', trim($filter), $matches)) {
            return null;
        }

        return [
            'attribute' => strtolower(trim($matches[1])),
            'value' => $matches[2],
        ];
    }
}
