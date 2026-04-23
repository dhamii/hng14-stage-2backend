<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Profile extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'name',
        'gender',
        'gender_probability',
        'age',
        'age_group',
        'country_id',
        'country_name',
        'country_probability',
    ];

    /**
     * Generate a new UUID v7 for the model.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
