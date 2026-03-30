<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function presign(Request $request)
    {
        $request->validate([
            'filename' => 'required|string|max:140',
            'mime'     => 'required|string|max:80',
            'max'      => 'nullable|integer|max:10485760', // 10MB
        ]);

        if (config('filesystems.default') !== 's3') {
            return response()->json([
                'configured' => false,
                'message' => 'Use POST /attachments with multipart form field "file" to upload directly (stored on server).',
            ]);
        }

        $path = 'attachments/'.auth()->user()->tenant_id.'/'.now()->format('Y/m/d/').uniqid().'_'.$request->filename;
        $client = Storage::disk('s3')->getClient();
        $cmd = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key'    => $path,
            'ContentType' => $request->mime,
            'ACL' => 'private',
        ]);
        $presigned = $client->createPresignedRequest($cmd, '+10 minutes');

        return response()->json([
            'configured' => true,
            'url'        => (string) $presigned->getUri(),
            'key'        => $path,
        ]);
    }
}