<?php

namespace App\Http\Controllers\Api\V1\Claims;

use App\Http\Controllers\Controller;
use App\Services\ClaimsUploadUsingFileService;
use Illuminate\Http\Request;

class ClaimUploadController extends Controller
{
    public function __construct(
        protected ClaimsUploadUsingFileService $uploadService
    ) {}

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:10240',
        ]);

        try {
            $result = $this->uploadService->upload($request->file('file'));

            return response()->json([
                "succes" => true,
                'message' => 'File uploaded and processed successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function template()
    {
        $path = storage_path('app/public/templates/claims_import_template.xlsx');

        if (!file_exists($path)) {
            return response()->json([
                "succes" => false,
                'message' => 'Template file not found',
            ], 404);
        }

        return response()->download($path, 'claims_import_template.xlsx');
    }
}
