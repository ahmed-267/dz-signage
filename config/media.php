<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage disk
    |--------------------------------------------------------------------------
    |
    | Disk-agnostic: switch this to an S3/R2 disk later without rewriting the
    | MediaAsset domain. Local development uses the public disk.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Upload size limits (kilobytes — Laravel validation units)
    |--------------------------------------------------------------------------
    */

    'max_sizes' => [
        'image' => (int) env('MEDIA_MAX_IMAGE_KB', 10_240), // 10 MB
        'logo' => (int) env('MEDIA_MAX_LOGO_KB', 5_120), // 5 MB
        'video' => (int) env('MEDIA_MAX_VIDEO_KB', 512_000), // 500 MB
        'document' => (int) env('MEDIA_MAX_DOCUMENT_KB', 20_480), // 20 MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types / extensions
    |--------------------------------------------------------------------------
    */

    'allowed' => [
        'image' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        ],
        'logo' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
        ],
        'video' => [
            'mimes' => ['video/mp4', 'video/quicktime', 'video/webm'],
            'extensions' => ['mp4', 'mov', 'webm'],
        ],
        'document' => [
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'extensions' => ['pdf', 'doc', 'docx'],
        ],
    ],

];
