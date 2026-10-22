<?php

/*
 * Only the keys that differ from Livewire's defaults. Laravel merges package
 * config one level deep, so 'temporary_file_upload' is repeated in full.
 */
return [

    /*
     * Every admin-panel (Filament) file field uploads through Livewire's
     * temporary upload first. Livewire's default cap of 12 MB made it
     * impossible to add a lecture video or a large slide deck from /admin
     * even though the rest of the app accepts them. 500 MB matches the
     * largest file the app accepts anywhere (live-class recordings) and the
     * Nginx/PHP limits in deploy/ (600 MB with request overhead).
     */
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:512000'],
        'directory' => null,
        'middleware' => 'throttle:60,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        // A 500 MB file over an ordinary office uplink needs more than the default 5 minutes.
        'max_upload_time' => 30,
        'cleanup' => true,
    ],

];
