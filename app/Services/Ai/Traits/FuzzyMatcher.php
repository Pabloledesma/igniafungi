<?php

namespace App\Services\Ai\Traits;

use Illuminate\Support\Str;

trait FuzzyMatcher
{
    /**
     * Find best match for a string in a list of options using Levenshtein distance.
     */
    public function findBestMatch(string $input, $options, int $threshold = 3): ?string
    {
        $bestMatch = null;
        $shortestDistance = -1;

        $inputLower = Str::lower(trim($input));

        foreach ($options as $option) {
            $optionLower = Str::lower($option);

            // Exact match optimization
            if ($inputLower === $optionLower) {
                return $option;
            }

            // Calculate distance
            $lev = levenshtein($inputLower, $optionLower);

            if ($lev <= $threshold) {
                if ($shortestDistance < 0 || $lev < $shortestDistance) {
                    $shortestDistance = $lev;
                    $bestMatch = $option;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Infer location (City) from user content using common prepositions.
     */
    public function inferLocationFromContent(string $content, array $knownCities): ?array
    {
        $normalized = Str::lower($content);

        // Strategy 1: Look for "en [City]" or "a [City]"
        if (preg_match('/(en|a|para|hacia)\s+([a-zá-ú\s]+)/u', $normalized, $matches)) {
            $candidate = trim($matches[2]);
            $candidate = $this->resolveCityAlias($candidate);
            $match = $this->findBestMatch($candidate, $knownCities);
            if ($match) {
                return ['city' => $match];
            }
        }

        // Strategy 2: Direct match against DB cities in the content (Robust)
        // Order by length descending to catch multi-word cities first
        usort($knownCities, fn($a, $b) => strlen($b) - strlen($a));

        $normalizedAscii = Str::slug($content, ' '); // "precio envio bogota usme"

        foreach ($knownCities as $city) {
            // Check exact match first
            if (str_contains($normalized, Str::lower($city))) {
                return ['city' => $city];
            }

            // Check ASCII match (Bogotá -> bogota)
            $cityAscii = Str::slug($city, ' ');
            // Only strictly match full words to avoid false positives? 
            // "bogota" in "bogota usme" -> Yes.
            if (str_contains($normalizedAscii, $cityAscii)) {
                return ['city' => $city];
            }
        }

        return null; // No confident match
    }

    protected function resolveCityAlias(string $input): string
    {
        $aliases = [
            'villao' => 'Villavicencio',
            'medallo' => 'Medellín',
            'bog' => 'Bogotá',
            'bogota' => 'Bogotá',
            'medellin' => 'Medellín',
            'ibague' => 'Ibagué',
            'popayan' => 'Popayán',
            'cali' => 'Cali' // Identity but good for lower case
        ];

        $lower = Str::lower($input);
        return $aliases[$lower] ?? $input;
    }
}
