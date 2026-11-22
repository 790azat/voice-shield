<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Processing backend
    |--------------------------------------------------------------------------
    |
    | "http" – sends audio to the GPU worker (worker/ folder) over HTTP.
    | "fake" – demo mode without a GPU: simulates processing and returns the
    |          original file. Useful for local development and previews.
    |
    */

    'driver' => env('PROTECTION_DRIVER', 'fake'),

    'worker' => [
        'url' => rtrim((string) env('PROTECTION_WORKER_URL', ''), '/'),
        'token' => env('PROTECTION_WORKER_TOKEN'),
        'timeout' => (int) env('PROTECTION_WORKER_TIMEOUT', 60),
    ],

    'fake' => [
        // Where the demo backend keeps files. /tmp is the only writable path on Vercel.
        'path' => env('PROTECTION_FAKE_PATH', sys_get_temp_dir().'/voice-shield'),
        'duration' => (int) env('PROTECTION_FAKE_DURATION', 12),
    ],

    // Vercel functions accept request bodies up to 4.5 MB.
    'max_upload_kb' => (int) env('PROTECTION_MAX_UPLOAD_KB', 4096),

    'models' => ['large-v3', 'large-v3,medium', 'large-v3,medium,small'],

    'languages' => [
        'ru' => 'Русский',
        'en' => 'English',
        'uk' => 'Українська',
        'kk' => 'Қазақша',
        'hy' => 'Հայերեն',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
    ],

    'presets' => [
        'light' => ['label' => 'Мягкая', 'hint' => 'почти без следов, слабее защита', 'eps' => 0.02, 'steps' => 1500, 'lr' => 0.0007],
        'standard' => ['label' => 'Стандартная', 'hint' => 'баланс качества и защиты', 'eps' => 0.03, 'steps' => 2000, 'lr' => 0.0007],
        'strong' => ['label' => 'Сильная', 'hint' => 'надёжнее, лёгкий шум может быть слышен', 'eps' => 0.05, 'steps' => 3000, 'lr' => 0.001],
    ],
];
