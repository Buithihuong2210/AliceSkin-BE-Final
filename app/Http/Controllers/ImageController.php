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
        // Validate the uploaded file
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Allow only specific image types
        ]);

        // Check if the file is valid and uploaded
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Retrieve the uploaded file
            $file = $request->file('image');
            $filePath = $file->getRealPath();
            $fileName = $file->hashName(); // Generate a unique name for the file
            $bucketName = 'alice-skin'; // Replace with your bucket name

            // Upload the file to Google Cloud Storage
            $this->storageService->uploadFile($bucketName, $filePath, [
                'name' => $fileName,
                'predefinedAcl' => 'publicRead', // Make it public if needed
            ]);

            // Get the public URL of the uploaded file
            $url = $this->storageService->getPublicUrl($bucketName, $fileName);

            return response()->json([
                'message' => 'Image uploaded successfully!',
                'url' => $url, // Return the full URL of the uploaded image
            ], 200);
        }

        return response()->json([
            'error' => 'Failed to upload image.',
        ], 400);
    }
}
