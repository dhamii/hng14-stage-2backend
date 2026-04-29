<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OAuthState extends Model
{
    use HasUuids;

    protected $fillable = [
        'state',
        'code_challenge',
        'code_challenge_method',
        'redirect_uri',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
