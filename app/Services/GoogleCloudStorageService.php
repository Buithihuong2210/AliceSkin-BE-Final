<?php

namespace App\Services;

use Google\Cloud\Storage\StorageClient;

class GoogleCloudStorageService
{
    protected $storage;

    public function __construct()
    {
        $this->storage = new StorageClient([
            'keyFilePath' => storage_path('gc-storage-key.json'), // Path to your service account file
        ]);
    }

    public function uploadFile($bucketName, $filePath, $options = [])
    {
        $bucket = $this->storage->bucket($bucketName);
        $bucket->upload(
            fopen($filePath, 'r'),
            $options
        );
    }

    public function downloadFile($bucketName, $objectName, $destinationPath)
    {
        $bucket = $this->storage->bucket($bucketName);
        $object = $bucket->object($objectName);
        $object->downloadToFile($destinationPath);
    }

    public function getPublicUrl($bucketName, $objectName)
    {
        // Generates the public URL to access the object
        return sprintf('https://storage.googleapis.com/%s/%s', $bucketName, $objectName);
    }

    public function makeObjectPublic($bucketName, $objectName)
    {
        // Makes the object publicly accessible
        $bucket = $this->storage->bucket($bucketName);
        $object = $bucket->object($objectName);
        $object->update(['acl' => []], ['predefinedAcl' => 'PUBLICREAD']);
    }
}
