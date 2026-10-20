<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemData\DemoDatasetGenerator;
use App\Services\SystemData\SystemDataExporter;
use App\Services\SystemData\SystemDataImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemDataController extends Controller
{
    /**
     * Export sanitized system business data as a portable ZIP archive.
     */
    public function export(SystemDataExporter $exporter): BinaryFileResponse
    {
        $this->authorizeAdmin();

        $zipPath = $exporter->export();
        $fileName = 'raflora-system-data-' . now()->format('Y-m-d-His') . '.zip';

        return response()->download($zipPath, $fileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Generate and download a synthetic, workflow-spanning demo dataset ZIP archive.
     */
    public function demo(DemoDatasetGenerator $generator): BinaryFileResponse
    {
        $this->authorizeAdmin();

        $zipPath = $generator->generate();
        $fileName = 'raflora-demo-dataset-' . now()->format('Y-m-d-His') . '.zip';

        return response()->download($zipPath, $fileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Inspect uploaded ZIP archive, validate schemas and relationships, and return preview without database mutations.
     */
    public function preview(Request $request, SystemDataImporter $importer): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'dataset_file' => 'required|file|mimes:zip|max:30720', // Up to 30MB
        ]);

        $file = $request->file('dataset_file');
        $token = (string) Str::uuid();
        $tempDir = storage_path('app/tmp_system_data');
        File::ensureDirectoryExists($tempDir);

        $pendingZipPath = $tempDir . '/pending_' . $token . '.zip';
        $file->move($tempDir, 'pending_' . $token . '.zip');

        try {
            $previewResult = $importer->preview($pendingZipPath);

            if (!$previewResult['isValid']) {
                @unlink($pendingZipPath);

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'preview' => $previewResult,
                        'errors' => $previewResult['errors'],
                    ], 422);
                }

                return back()->with('import_preview', $previewResult)
                    ->withErrors(['dataset_file' => $previewResult['errors'][0] ?? 'Validation failed.']);
            }

            $previewResult['preview_token'] = $token;

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'preview' => $previewResult,
                ]);
            }

            return back()->with('import_preview', $previewResult);
        } catch (\Throwable $e) {
            @unlink($pendingZipPath);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [$e->getMessage()],
                ], 422);
            }

            return back()->withErrors(['dataset_file' => 'Preview inspection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Atomically commit the validated dataset into the database.
     */
    public function import(Request $request, SystemDataImporter $importer): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'preview_token' => 'required|string',
        ]);

        $token = $request->input('preview_token');
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $token)) {
            abort(400, 'Invalid preview token format.');
        }

        $pendingZipPath = storage_path('app/tmp_system_data/pending_' . $token . '.zip');
        if (!file_exists($pendingZipPath)) {
            $msg = 'Import preview session expired or not found. Please re-upload your archive to preview.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 404);
            }
            return back()->withErrors(['preview_token' => $msg]);
        }

        try {
            $importResult = $importer->import($pendingZipPath);
            @unlink($pendingZipPath);

            $totalImported = array_sum($importResult['record_counts'] ?? []);
            $successMsg = "System data imported successfully ({$totalImported} total records across entities).";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMsg,
                    'result' => $importResult,
                ]);
            }

            return back()->with('success', $successMsg);
        } catch (\Throwable $e) {
            @unlink($pendingZipPath);

            $errorMsg = 'Import failed. No changes were made: ' . $e->getMessage();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                ], 422);
            }

            return back()->withErrors(['import_error' => $errorMsg]);
        }
    }

    /**
     * Authorize that the current authenticated user has an administrator role.
     */
    protected function authorizeAdmin(): void
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403, 'Unauthorized. Administrator access required.');
        }
    }
}
