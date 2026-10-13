<?php

namespace App\Http\Controllers;

use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints behind the venue location finder (<x-location-finder> and <x-venue-map>).
 * Public because guests use the finder on the guest booking form; throttled in routes/web.php.
 */
class LocationController extends Controller
{
    private const UNAVAILABLE_MESSAGE = 'Location search is unavailable right now. You can still type the venue address manually.';

    public function search(Request $request, GeocodingService $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'q.required' => 'Type a venue name or address to search.',
            'q.min' => 'Type at least 3 characters to search.',
        ]);

        $results = $geocoder->search($validated['q']);

        if ($results === null) {
            return response()->json(['message' => self::UNAVAILABLE_MESSAGE, 'results' => []], 503);
        }

        return response()->json(['results' => $results]);
    }

    public function reverse(Request $request, GeocodingService $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'result' => $geocoder->reverse((float) $validated['lat'], (float) $validated['lng']),
        ]);
    }
}
