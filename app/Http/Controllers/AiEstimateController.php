<?php

namespace App\Http\Controllers;

use App\Services\AiEstimateService;
use App\Support\DecorAreaBreakdown;
use App\Support\EventDesignBrief;
use App\Support\InspirationPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-area AI ranges and combined material quantities for the photos analyzed in the caller's
 * own session, for the review step of the guest and client booking forms. Built from the
 * server-side analysis payloads only; the browser sends nothing but the photo tokens and the
 * current design brief, and gets back display ranges and quantities, never prices.
 */
class AiEstimateController extends Controller
{
    public function preview(Request $request, AiEstimateService $estimates): JsonResponse
    {
        $request->validate(['analysis_tokens' => ['nullable']] + EventDesignBrief::rules(), EventDesignBrief::messages());

        $brief = EventDesignBrief::fromInput($request->all());
        $payloads = [];
        foreach (InspirationPhotos::tokensFrom($request->input('analysis_tokens')) as $token) {
            $payload = BookingController::getStoredAnalysisPayload($token);
            if (is_array($payload) && is_array($payload['analysis'] ?? null)) {
                $payloads[] = $payload + ['token' => $token];
            }
        }

        $rows = $payloads === [] ? [] : InspirationPhotos::analysisData($payloads, $brief, storeFiles: false)['suggested_materials'];

        return response()->json([
            'photo_count' => count($payloads),
            'material_count' => count($rows),
            'estimate' => $estimates->estimate($rows, $brief),
            'area_breakdown' => DecorAreaBreakdown::build($rows, $brief),
        ]);
    }
}
