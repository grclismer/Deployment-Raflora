# Step 2C: Same-Image Client vs Guest Diagnostic Evidence

**Date:** 2026-09-08  
**Scope:** Read-only forensic evidence capture from the latest same-image Client vs Guest diagnostic test.  
**Note:** This report records the evidence currently available in the repository and the investigation log; it does not assume a raw Gemini response that was not persisted.

## 1. Executive summary

The latest same-image test evidence shows the client and guest artifacts are byte-identical, which means the uploaded image file itself was the same. The repository does not retain the exact live request payload or the final raw Gemini JSON for that specific same-image test, so the precise selected model and template-reuse branch cannot be proven for the run itself. The strongest verified fact is: the same image file was used, and the app's shared diagnostic contract would record the source as `client` or `guest`, plus flags for `fresh_gemini_analysis` and `completed_template_reused`.

## 2. Verified image identity evidence

The investigation captured the following same-image artifact pair:

| Portal | Artifact path | SHA1 | Match status |
|---|---|---|---|
| Client | `storage/app/public/bookings/temp-analysis/client-valid-analysis.png` | `2115A1D881432165B3BE8D5059CD4A2BA1C0F58E` | Matches Guest exactly |
| Guest | `storage/app/public/bookings/temp-analysis/guest-valid-analysis.png` | `2115A1D881432165B3BE8D5059CD4A2BA1C0F58E` | Matches Client exactly |

This is the key evidence: the same insight image was used in both portal tests. Therefore, any difference in later rendered outputs cannot be explained by a different uploaded file unless the same binary was later transformed differently after upload.

## 3. Request context and what was recorded

Both portals pass through the same shared service path:

- `BookingController::analyzeTempImage()` / `GuestBookingController::analyzeTempImage()`
- `GeminiVisionService::validateImage()`
- `GeminiVisionService::analyzeImageFromPath()`
- same prompt structure, same JSON contract, same response normalization

The runtime request context is built from these semantic values:

- `event_type`
- `event_time`
- `venue` (Guest normalizes `venue_address` into `venue` before calling the service)
- `special_requests`
- `guest_count` / `table_count` when applicable
- `scaleContext` derived from the scale values

The exact values from the latest same-image test are not stored in a persisted run record in the repo. The code confirms the same request model, but the live same-image payload values are not reconstructible from repository files alone.

## 4. Template reuse status

| Portal | Template reuse status | Evidence |
|---|---|---|
| Client | Unverified for this exact same-image run | No run-level persisted row proves a completed template match for the screenshot test |
| Guest | Unverified for this exact same-image run | Same as Client; the repo contains the code path but not the per-run DB/session proof |

The service diagnostic contract defines the branch explicitly:

```php
'fresh_gemini_analysis' => !$completedTemplateReused,
'completed_template_reused' => (bool) $completedTemplateReused,
```

That means the code is prepared to distinguish fresh Gemini generation from completed-template reuse, but the exact same-image run did not retain the final persisted flag in a repository-visible record.

## 5. Selected Gemini model

| Portal | Selected Gemini model | Status |
|---|---|---|
| Client | Not recorded in the same-image test evidence | The selected model is not stored in the persisted run payload for this exact test |
| Guest | Not recorded in the same-image test evidence | Same as Client |

The shared service uses the fallback list:

1. `gemini-3.5-flash`
2. `gemini-3.1-flash-lite`
3. `gemini-2.5-flash`
4. `gemini-2.5-flash-lite`
5. `gemini-1.5-flash`

The app logs show that later runs commonly selected `gemini-3.1-flash-lite`, but the exact same-image test record in this investigation does not contain a definitive selected model for the client and guest screenshots.

## 6. Analysis results for both runs

The code’s analysis diagnostic schema is defined as:

```php
'analysis_result' => [
  'suggested_materials' => $materials,
],
'raw_response' => $analysisResult['raw_response'] ?? null,
```

For the latest same-image runtime evidence, the repository does not retain the raw client and guest `suggested_materials` arrays for the exact uploaded image. In other words:

| Portal | Raw analysis result for same-image test | Status |
|---|---|---|
| Client | Not persisted in repository evidence | Missing |
| Guest | Not persisted in repository evidence | Missing |

The project therefore proves the image identity and the service contract, but not the exact material list that each portal produced in this same-image run. The strongest supported conclusion is that both runs share the same source image, while the exact analysis output is not recoverable from the codebase alone.

## 7. Conclusion

The best-supported finding from the latest same-image diagnostic evidence is:

- The client and guest test images are the same file by SHA1.
- The same image file was used in both flows.
- The app is designed to record `fresh_gemini_analysis` vs `completed_template_reused` and the selected Gemini model, but this exact run did not preserve those values in a repository-visible record.
- Because the exact payload and raw response were not retained, the underlying cause of any client-vs-guest difference in the screenshot remains unproven but is still consistent with either different runtime context or independent Gemini generation.

In short: the image identity is proven; the per-run model/template/analysis outputs are not fully preserved in the repo evidence.
# Client vs Guest AI Analysis Inspection Report

**Date:** 2026-09-08  
**Scope:** Read-only forensic inspection. No files were modified during the inspection.

## 1. Current Status

| Area | Status | Finding |
|---|---|---|
| Client AI analysis | Working, independent | Uses the shared Gemini service but can produce a separate analysis. |
| Guest AI analysis | Working, independent | Uses the shared Gemini service but can produce a separate analysis. |
| Shared Gemini pipeline | Partial | Same service and prompt, but no canonical cross-portal analysis. |
| Area classification | Partial | Gemini-generated and normalized for display. |
| Detection vs Recommendation | Partial | Controlled by Gemini booleans; not independently verified. |
| Quantity estimation | AI-estimated | Context-dependent and not deterministic. |
| `visual_location` generation | Partial | Gemini-generated and range-validated. |
| Visual annotation rendering | Working technically | Uses normalized coordinates, but marker accuracy depends on Gemini. |
| Client/Guest consistency | Not guaranteed | Separate requests can return different interpretations. |

## 2. Exact Pipeline

### Client

`client/booking-create.blade.php`  
-> `POST /bookings/analyze-temp-image`  
-> `BookingController::analyzeTempImage()`  
-> `GeminiVisionService::validateImage()`  
-> optional completed-booking template lookup  
-> `GeminiVisionService::analyzeImageFromPath()`  
-> session-stored analysis token  
-> client form submits token  
-> `BookingController::store()` reuses stored analysis  
-> `Booking::ai_analysis_data` and `AiAnalysisResult` persistence  
-> `BookingController::analysis()`  
-> `client/booking-analysis.blade.php`

Relevant files:

- `app/Http/Controllers/BookingController.php`
- `app/Services/GeminiVisionService.php`
- `resources/views/client/booking-analysis.blade.php`

### Guest

`guest/booking-create.blade.php`  
-> `POST /guest/bookings/analyze-temp-image`  
-> `GuestBookingController::analyzeTempImage()`  
-> `GeminiVisionService::validateImage()`  
-> optional completed-booking template lookup  
-> `GeminiVisionService::analyzeImageFromPath()`  
-> session-stored analysis token  
-> guest form submits token  
-> `GuestBookingController::store()` reuses stored analysis  
-> `Booking::ai_analysis_data` and `AiAnalysisResult` persistence  
-> `GuestBookingController::show()`  
-> `guest/booking-analysis.blade.php`

Relevant files:

- `app/Http/Controllers/GuestBookingController.php`
- `app/Services/GeminiVisionService.php`
- `resources/views/guest/booking-analysis.blade.php`

## 3. Client vs Guest Differences

| Aspect | Client | Guest | Same/Different | Impact |
|---|---|---|---|---|
| Controller | `BookingController` | `GuestBookingController` | Different | Separate request and persistence paths. |
| Analysis service | `GeminiVisionService::analyzeImageFromPath()` | Same method | Same | Shared prompt and model behavior. |
| Image preprocessing | `prepareImageForGemini()` | Same | Same | Same resize/compression behavior. |
| Gemini models | Same fallback list | Same | Same | Same possible models. |
| Analysis temperature | `0.2` | `0.2` | Same | Gemini remains nondeterministic. |
| Image validation | Client pre-analysis validates image | Guest pre-analysis validates image; guest store also has an extra validation fallback | Mostly same | Guest has an additional validation path. |
| Venue field | Sends `venue` | Sends `venue_address`, then normalizes it | Equivalent after normalization | Minor request-shape difference. |
| Event context | Event type/time/venue/special requests | Same values, different venue field name | Mostly same | Minor context difference. |
| Pax/Table context | Controller supports it, but booking-form AJAX does not append these fields | Same | Same omission | Pre-analysis requests generally do not receive scale context. |
| Prompt | Shared service prompt | Shared service prompt | Same | No portal-specific prompt. |
| JSON parsing | Shared `extractFirstJson()` | Shared method | Same | Same parser. |
| Normalization | Shared `normalizeAreaAnalysis()` | Shared method | Same | Same display transformation. |
| Session token | Client session | Guest session | Different | Results are not shared between portals. |
| Completed-template lookup | Duplicated helper | Duplicated helper | Similar, separate implementations | Can bypass Gemini entirely. |
| Final persistence | `ai_analysis_data`, `AiAnalysisResult` | Same | Same | Raw analysis is retained. |
| Summary renderer | Client Blade view | Guest Blade view | Different files, same logic | Same coordinate approach. |

## 4. AI Response Structure

The shared prompt requests `suggested_materials` entries containing:

- `item_name`
- `category`
- `quantity`
- `unit_type`
- `unit_cost_php`
- `area`
- `is_detected`
- `is_recommendation`
- `is_custom_item`
- `note`
- Optional `visual_location`

The visual location structure is:

```json
{
  "visual_location": {
    "x": 0.42,
    "y": 0.27,
    "width": 0.15,
    "height": 0.10
  }
}
```

`width` and `height` are optional. Missing or invalid locations become `null`.

The schema is defined once in `GeminiVisionService`; Client and Guest do not maintain separate Gemini schemas.

## 5. Visual Grounding Audit

### Gemini prompt

Gemini is instructed to:

- Return normalized `x` and `y` values from `0.0` to `1.0`.
- Measure from the original image's left and top edges.
- Add `width` and `height` only when reliable.
- Return `null` when an item cannot be visually grounded.
- Never derive coordinates from Area, item order, or a generic region.

Evidence: `app/Services/GeminiVisionService.php`, analysis prompt and JSON schema.

### Normalization

`normalizeAreaAnalysis()` passes `material['visual_location']` to `normalizeVisualLocation()`.

`normalizeVisualLocation()`:

- Requires numeric `x` and `y`.
- Rejects values outside `0.0` to `1.0`.
- Preserves optional valid `width` and `height`.
- Returns `null` for missing or invalid data.

No coordinates are inferred from Area, category, item order, array position, or fixed percentages.

### Rendering

Both analysis views calculate:

```php
$left = $location['x'] * 100;
$top = $location['y'] * 100;
```

Markers are rendered only when `visual_location` is present.

Items without location data remain in the Design breakdown but receive no marker.

The image stage derives the uploaded image dimensions using `getimagesize()` and applies the image aspect ratio to the annotation stage. This keeps normalized coordinates attached to the image rather than the browser viewport.

### Remaining rendering limitations

- `width` and `height` are preserved but are not currently rendered as bounding boxes.
- Callout labels always extend toward the right.
- There is no collision avoidance or edge-aware label placement.
- A label can cover part of the detected object.
- Marker accuracy depends entirely on Gemini's returned point.
- Dynamic coordinates do not prove visual accuracy.

The full-image modal clones the annotated image stage, so the same image-relative markers are preserved in the modal.

## 6. Root Causes of Different Results

### Confirmed

1. Client and Guest can call Gemini independently.
2. Gemini analysis uses temperature `0.2`, so identical images can produce different outputs.
3. Initial AJAX analysis requests omit Pax and Table values.
4. Completed-booking template reuse can bypass Gemini.
5. Template-generated items do not contain Area or `visual_location` metadata.
6. Detection status is entirely model-controlled.

### Observed

The two portals can produce different:

- Item names
- Quantities
- Categories
- Areas
- AI Detected versus AI Recommendation states
- Visual locations

These differences are consistent with separate Gemini calls or different template/context paths.

### Inferred

If both tests were separate booking attempts, independent Gemini generation is the most likely cause. If one attempt matched a completed booking template and the other did not, that would also explain major differences.

### Unclear

The screenshots alone cannot establish:

- Whether either run used a completed-booking template.
- Whether both requests used byte-identical image files.
- Which fallback model generated each response.
- Whether the raw `visual_location` values were accurate for the specific image.

## 7. Detection vs Recommendation

`normalizeAreaAnalysis()` maps:

- `is_detected = true` and `is_recommendation = false` -> `AI Detected`
- Otherwise -> `AI Recommendation`

The application trusts Gemini's flags and does not independently verify them.

Therefore, an item such as Floral Foam Blocks can be labeled `AI Detected` if Gemini returns incorrect flags. There is no separate computer-vision verification layer.

## 8. Area Data

Area is read from:

```php
$material['area'] ?? $material['location'] ?? 'overall'
```

It is primarily Gemini-generated. The application:

- Normalizes it to lowercase.
- Maps known keys such as `ceiling`, `stage`, `tables`, and `backdrop` to display labels.
- Uses `overall` when both fields are missing.

Area is suitable for grouping but is not a visual coordinate and is not used as one by the current renderer.

## 9. Quantity Accuracy

Quantities originate from Gemini through `quantity` or `estimated_quantity`.

The application:

- Converts values to floats.
- Uses them for pricing and persistence.
- Does not perform deterministic flower-count calculations.
- Passes Pax/Table context into Gemini only when those values are present in the request.

Both controllers construct scale context similarly, but the initial form AJAX requests do not include those fields. Quantities should be treated as AI estimates, not verified measurements.

## 10. Consistency Classification

**Client/Guest consistency: PARTIAL**

They share:

- Gemini service
- Prompt
- Model fallback list
- Image preprocessing
- JSON parsing
- Location normalization
- Rendering approach

They do not share:

- One canonical analysis per image
- One cross-portal session or token
- One deterministic Gemini response
- Guaranteed identical event and scale context
- Guaranteed identical completed-template execution state

The observed differences are expected under the current architecture.

## 11. Accuracy Gaps

- No canonical analysis cache keyed globally by image hash.
- Gemini temperature remains nonzero.
- Pax/Table values are omitted from the initial AJAX analysis request.
- Completed-template reuse strips Area and visual-location metadata.
- Detection/recommendation flags are not independently verified.
- Bounding boxes are not rendered even when supplied.
- Labels can overlap detected objects.
- No confidence threshold controls whether a marker is displayed.
- No raw-response comparison tool exists for Client versus Guest runs.
- Marker accuracy cannot be established without comparing Gemini's raw `visual_location` values against the uploaded image.

## 12. Recommended Next Step

Do not change the UI first.

Capture and compare the following for the same image:

1. Client raw Gemini response.
2. Guest raw Gemini response.
3. Event context.
4. Pax/Table scale context.
5. Whether `findCompletedBookingTemplate()` was used.
6. Model selected from the fallback list.
7. Final persisted `suggested_materials`.

That comparison will identify whether the primary cause is nondeterministic Gemini output, different context, completed-template reuse, or a persistence/rendering issue.

## 13. Files Inspected

- `app/Http/Controllers/BookingController.php` - client analysis, storage, template reuse, persistence, and summary retrieval.
- `app/Http/Controllers/GuestBookingController.php` - guest analysis, validation, storage, template reuse, persistence, and summary retrieval.
- `app/Services/GeminiVisionService.php` - prompt, model fallback, preprocessing, JSON parsing, location normalization, and validation.
- `app/Http/Requests/BookingRequest.php` - client booking validation and scale fields.
- `app/Models/Booking.php` - JSON casting for persisted analysis data.
- `app/Models/AiAnalysisResult.php` - JSON casting and retrieval of suggested materials.
- `routes/web.php` - Client and Guest analysis endpoints.
- `resources/views/client/booking-analysis.blade.php` - client annotation rendering and full-image modal.
- `resources/views/guest/booking-analysis.blade.php` - guest annotation rendering and full-image modal.

## 14. Files Not Changed

No files were modified during the forensic inspection.
