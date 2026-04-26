<?php

return [

    /*
    | Live HTTP provider imports are default. Set true to use fixtures for
    | offline debugging.
    */
    'import_use_stub' => env('HOLIDAYSAGE_IMPORT_USE_STUB', false),

    /*
    | Jet2 live smartsearch: keep these modest so a bad network fails fast. curl from a shell
    | often returns quickly; PHP should not sit for a minute on a single GET.
    */
    'jet2' => [
        'connect_timeout' => (float) env('HOLIDAYSAGE_JET2_CONNECT_TIMEOUT', 5.0),
        'api_timeout' => (float) env('HOLIDAYSAGE_JET2_API_TIMEOUT', 12.0),
        'html_timeout' => (float) env('HOLIDAYSAGE_JET2_HTML_TIMEOUT', 16.0),
        'max_5xx_attempts' => (int) env('HOLIDAYSAGE_JET2_5XX_ATTEMPTS', 2),
        'retry_5xx_sleep_ms' => (int) env('HOLIDAYSAGE_JET2_5XX_SLEEP_MS', 300),
        'import_job_timeout' => (int) env('HOLIDAYSAGE_JET2_IMPORT_JOB_TIMEOUT', 90),
    ],

];
