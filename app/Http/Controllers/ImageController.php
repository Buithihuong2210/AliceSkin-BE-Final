<?php

namespace App\Http\Controllers;

use App\Services\GoogleCloudStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    protected $storageService;

    public function __construct(GoogleCloudStorageService $storageService)
    {
        $this->storageService = $storageService;
    }
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Retrieve the uploaded file
            $file = $request->file('image');
            $filePath = $file->getRealPath();
            $fileName = $file->hashName();
            $bucketName = 'alice-skin';

            // Upload the file to Google Cloud Storage
            $this->storageService->uploadFile($bucketName, $filePath, [
                'name' => $fileName,
                'predefinedAcl' => 'publicRead',
            ]);

            // Get the public URL of the uploaded file
            $url = $this->storageService->getPublicUrl($bucketName, $fileName);

            return response()->json([
                'message' => 'Image uploaded successfully!',
                'url' => $url,
            ], 200);
        }

        return response()->json([
            'error' => 'Failed to upload image.',
        ], 400);
    }
}
