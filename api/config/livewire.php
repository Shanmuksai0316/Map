<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Livewire Temporary File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Use "local" disk for temporary uploads so storage/app/private is used
    | (writable in Docker). Final files (e.g. tenant logos) still go to the
    | FileUpload's disk (public). Avoids 500 when storage/app/public isn't writable.
    |
    */

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],

        'max_upload_time' => 5, // minutes
    ],
];


