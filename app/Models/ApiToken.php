<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = ['last_used_at' => 'datetime'];

    public static function generate(string $name): array
    {
        $plaintext = 'api_'.Str::random(40);
        $token = static::create(['name' => $name, 'token_hash' => Hash::make($plaintext)]);

        return [$token, $plaintext];
    }

    public static function findByPlaintext(string $plaintext): ?self
    {
        foreach (static::all() as $token) {
            if (Hash::check($plaintext, $token->token_hash)) {
                return $token;
            }
        }

        return null;
    }
}
