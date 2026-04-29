<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Facades\Http;

class ProfileIngestionService
{
    public function createFromName(string $name): Profile
    {
        $normalizedName = trim($name);

        $genderData = Http::get('https://api.genderize.io', ['name' => $normalizedName])->json();
        $ageData = Http::get('https://api.agify.io', ['name' => $normalizedName])->json();
        $countryData = Http::get('https://api.nationalize.io', ['name' => $normalizedName])->json();

        $country = collect($countryData['country'] ?? [])->sortByDesc('probability')->first();
        $countryId = strtoupper($country['country_id'] ?? 'US');

        $profile = Profile::updateOrCreate(
            ['name' => $normalizedName],
            [
                'gender' => $genderData['gender'] ?? 'unknown',
                'gender_probability' => $genderData['probability'] ?? 0,
                'age' => $ageData['age'] ?? 0,
                'age_group' => $this->resolveAgeGroup((int) ($ageData['age'] ?? 0)),
                'country_id' => $countryId,
                'country_name' => $countryId,
                'country_probability' => $country['probability'] ?? 0,
            ]
        );

        return $profile;
    }

    private function resolveAgeGroup(int $age): string
    {
        return match (true) {
            $age < 13 => 'child',
            $age < 20 => 'teenager',
            $age < 60 => 'adult',
            default => 'senior',
        };
    }
}
