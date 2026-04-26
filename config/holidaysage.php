<?php

return [

    /*
    | Number of ranked holiday cards per page on the saved search results view.
    */
    'search_results_per_page' => max(1, (int) env('HOLIDAYSAGE_SEARCH_RESULTS_PER_PAGE', 18)),

    /*
    | Live HTTP provider imports are default. Set true to use fixtures for
    | offline debugging.
    */
    'import_use_stub' => env('HOLIDAYSAGE_IMPORT_USE_STUB', false),

    /*
    | Jet2: API calls should fail fast; hotel detail HTML can be slow (TTFB, large pages).
    | `html_timeout` is Guzzle’s total request time for non-API GETs (detail pages).
    */
    'jet2' => [
        'connect_timeout' => (float) env('HOLIDAYSAGE_JET2_CONNECT_TIMEOUT', 5.0),
        'api_timeout' => (float) env('HOLIDAYSAGE_JET2_API_TIMEOUT', 12.0),
        'html_timeout' => (float) env('HOLIDAYSAGE_JET2_HTML_TIMEOUT', 45.0),
        'max_5xx_attempts' => (int) env('HOLIDAYSAGE_JET2_5XX_ATTEMPTS', 2),
        'retry_5xx_sleep_ms' => (int) env('HOLIDAYSAGE_JET2_5XX_SLEEP_MS', 300),
        'import_job_timeout' => (int) env('HOLIDAYSAGE_JET2_IMPORT_JOB_TIMEOUT', 90),
    ],

];
