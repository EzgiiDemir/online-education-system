<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ScimToken extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = ['last_used_at' => 'datetime'];

    /**
     * Creates a new token and returns [model, plaintext] -- the plaintext
     * is never stored anywhere and can't be recovered later, only the hash
     * used to verify a presented bearer token against.
     */
    public static function generate(string $name): array
    {
        $plaintext = 'scim_'.Str::random(40);
        $token = static::create(['name' => $name, 'token_hash' => Hash::make($plaintext)]);

        return [$token, $plaintext];
    }

    public static function findByPlaintext(string $plaintext): ?self
    {
        // Bcrypt hashes aren't lookup-able by value, so this checks every
        // token -- fine at the scale a handful of IdP integrations implies,
        // the same tradeoff Laravel's own default password check makes.
        foreach (static::all() as $token) {
            if (Hash::check($plaintext, $token->token_hash)) {
                return $token;
            }
        }

        return null;
    }
}
