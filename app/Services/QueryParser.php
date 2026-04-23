<?php

namespace App\Services;

class QueryParser
{
    protected array $countryMap = [
        'nigeria' => 'NG',
        'angola' => 'AO',
        'kenya' => 'KE',
        'benin' => 'BJ',
        'ghana' => 'GH',
        'south africa' => 'ZA',
        'egypt' => 'EG',
    ];

    public function parse(string $query): ?array
    {
        $filters = [];
        $query = strtolower($query);

        // Gender parsing
        if (preg_match('/\b(male|males)\b/', $query)) {
            $filters['gender'] = 'male';
        }
        if (preg_match('/\b(female|females)\b/', $query)) {
            $filters['gender'] = 'female';
        }

        // Age group parsing
        foreach (['child', 'teenager', 'adult', 'senior'] as $group) {
            if (str_contains($query, $group)) {
                $filters['age_group'] = $group;
            }
        }

        // "young" mapping
        if (str_contains($query, 'young')) {
            $filters['min_age'] = 16;
            $filters['max_age'] = 24;
        }

        // "above X" or "X and above"
        if (preg_match('/\b(above|over)\s+(\d+)\b/', $query, $matches)) {
            $filters['min_age'] = (int) $matches[2];
        } elseif (preg_match('/\b(\d+)\s+and\s+above\b/', $query, $matches)) {
            $filters['min_age'] = (int) $matches[1];
        }

        // "below X" or "under X"
        if (preg_match('/\b(below|under|less than)\s+(\d+)\b/', $query, $matches)) {
            $filters['max_age'] = (int) $matches[2];
        }

        // Country parsing
        if (preg_match('/\bfrom\s+([a-z\s]+)\b/', $query, $matches)) {
            $countryName = trim($matches[1]);
            // Check if country name exists in our map
            foreach ($this->countryMap as $name => $code) {
                if (str_contains($countryName, $name)) {
                    $filters['country_id'] = $code;
                    break;
                }
            }
        }

        // If no filters were applied, it might be an uninterpretable query
        // However, the prompt says "male and female teenagers above 17" -> age_group=teenager + min_age=17
        // So we should check if we got ANYTHING.
        
        return empty($filters) ? null : $filters;
    }
}
