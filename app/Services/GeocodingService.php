<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Address lookup for the venue location finder, backed by OpenStreetMap Nominatim.
 *
 * Requests go through the server (not the browser) so they carry an identifying User-Agent,
 * stay limited to the configured countries, and are cached — Nominatim's usage policy asks
 * for all three. Every result is normalized to the shape the booking forms fill in:
 * a specific venue/street line, a city/municipality, and the pin coordinates.
 */
class GeocodingService
{
    private const CACHE_TTL_HOURS = 24;

    /**
     * Search for places matching free text.
     *
     * @return array<int, array<string, mixed>>|null Matching places, or null when the lookup service failed.
     */
    public function search(string $query, int $limit = 5): ?array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));
        $cacheKey = 'geocode:search:' . md5(mb_strtolower($query) . '|' . $limit . '|' . $this->countryCodes());

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $places = $this->request('search', array_filter([
            'q' => $query,
            'limit' => $limit,
            'countrycodes' => $this->countryCodes(),
        ]));

        if ($places === null) {
            return null;
        }

        // OSM often maps one venue twice (building outline + entrance point) with the same label.
        $results = collect($places)
            ->map(fn ($place) => is_array($place) ? $this->normalize($place) : null)
            ->filter()
            ->unique('label')
            ->values()
            ->all();

        Cache::put($cacheKey, $results, now()->addHours(self::CACHE_TTL_HOURS));

        return $results;
    }

    /**
     * Describe the place at a map coordinate.
     *
     * @return array<string, mixed>|null The place, or null when nothing is there or the lookup failed.
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        $cacheKey = sprintf('geocode:reverse:%.5f,%.5f', $latitude, $longitude);

        // "No place here" is cached as false so it is not looked up again.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $place = $this->request('reverse', [
            'lat' => $latitude,
            'lon' => $longitude,
            'zoom' => 18,
        ]);

        if ($place === null) {
            return null;
        }

        // Nominatim answers {"error": "Unable to geocode"} for points with no address (e.g. open water).
        $result = isset($place['error']) ? null : $this->normalize($place);

        // Keep the exact pin the user dropped rather than the snapped address point.
        if ($result !== null) {
            $result['latitude'] = round($latitude, 7);
            $result['longitude'] = round($longitude, 7);
        }

        Cache::put($cacheKey, $result ?? false, now()->addHours(self::CACHE_TTL_HOURS));

        return $result;
    }

    /**
     * @return array<mixed>|null Decoded JSON body, or null on any transport or HTTP failure.
     */
    private function request(string $endpoint, array $params): ?array
    {
        $config = config('services.geocoding');

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->acceptJson()
                ->timeout((float) ($config['timeout'] ?? 8))
                ->get(rtrim($config['url'], '/') . '/' . $endpoint, $params + array_filter([
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'accept-language' => 'en',
                    'email' => $config['email'] ?? null,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Geocoding request failed: ' . $e->getMessage(), ['endpoint' => $endpoint]);
            return null;
        }

        if (!$response->successful() || !is_array($response->json())) {
            Log::warning('Geocoding request returned an error.', ['endpoint' => $endpoint, 'status' => $response->status()]);
            return null;
        }

        return $response->json();
    }

    /**
     * Turn a Nominatim place into the fields the booking forms use.
     */
    private function normalize(array $place): ?array
    {
        if (!isset($place['lat'], $place['lon']) || !is_numeric($place['lat']) || !is_numeric($place['lon'])) {
            return null;
        }

        $address = is_array($place['address'] ?? null) ? $place['address'] : [];
        $label = trim((string) ($place['display_name'] ?? ''));

        $city = $this->firstFilled($address, ['city', 'town', 'municipality', 'village', 'county', 'state_district', 'state', 'region']);
        $street = trim(($address['house_number'] ?? '') . ' ' . ($address['road'] ?? ''));
        $area = $this->firstFilled($address, ['suburb', 'quarter', 'neighbourhood', 'city_district', 'hamlet']);
        $name = trim((string) ($place['name'] ?? ''));

        // "Grand Hyatt Manila, 8th Avenue, Bonifacio Global City" — without repeating the city.
        $specificParts = [];
        foreach ([$name, $street, $area] as $part) {
            if ($part === '' || strcasecmp($part, (string) $city) === 0) {
                continue;
            }
            if (!in_array(mb_strtolower($part), array_map('mb_strtolower', $specificParts), true)) {
                $specificParts[] = $part;
            }
        }
        $specific = implode(', ', $specificParts) ?: trim(explode(',', $label)[0] ?? '');

        return [
            'label' => $label !== '' ? $label : $specific,
            'name' => $name !== '' ? $name : $specific,
            'specific' => mb_substr($specific, 0, 400),
            'city' => mb_substr((string) $city, 0, 255),
            'latitude' => round((float) $place['lat'], 7),
            'longitude' => round((float) $place['lon'], 7),
        ];
    }

    private function firstFilled(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($values[$key]) && is_string($values[$key])) {
                return trim($values[$key]);
            }
        }

        return null;
    }

    private function countryCodes(): string
    {
        return (string) config('services.geocoding.country_codes', '');
    }

    private function userAgent(): string
    {
        return config('services.geocoding.user_agent')
            ?: config('app.name', 'Raflora Enterprises') . ' venue location finder (' . config('app.url') . ')';
    }
}
