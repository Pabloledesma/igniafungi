<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    protected $apiKey;
    protected $placeId;

    public function __construct()
    {
        $this->apiKey = config('services.google.places_api_key', env('GOOGLE_PLACES_API_KEY'));
        $this->placeId = config('services.google.place_id', env('GOOGLE_PLACE_ID'));
    }

    public function getReviews()
    {
        if (empty($this->apiKey) || empty($this->placeId)) {
            return [];
        }

        return Cache::remember('google_reviews', 86400, function () {
            try {
                $response = Http::get("https://maps.googleapis.com/maps/api/place/details/json", [
                    'place_id' => $this->placeId,
                    'fields' => 'reviews',
                    'key' => $this->apiKey,
                    'language' => 'es'
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['result']['reviews'])) {
                        // Take top 5 reviews
                        return collect($data['result']['reviews'])
                            ->take(5)
                            ->map(function ($review) {
                                return (object) [
                                    'author_name' => $review['author_name'],
                                    'profile_photo_url' => $review['profile_photo_url'],
                                    'rating' => $review['rating'],
                                    'text' => $review['text'],
                                    'relative_time_description' => $review['relative_time_description']
                                ];
                            });
                    }
                }

                Log::error('Google Places API Error: ' . $response->body());
                return [];

            } catch (\Exception $e) {
                Log::error('Google Places Service Exception: ' . $e->getMessage());
                return [];
            }
        });
    }
}
