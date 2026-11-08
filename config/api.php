<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versioned campaign API
    |--------------------------------------------------------------------------
    |
    | The public API is versioned in the URI (`/api/v1/...`) so a breaking
    | change is a new route group rather than a silent change to an integration
    | that is already live. `current` is reported in the health payload and the
    | `X-Omnicast-Api-Version` response header.
    |
    */

    'version' => env('API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | `per_page` is the page size used when a caller does not ask, and `max`
    | caps what a caller may request so a single request cannot pull a whole
    | tenant out of the database.
    |
    */

    'pagination' => [
        'per_page' => (int) env('API_PER_PAGE', 25),
        'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute per token. `read` and `write` are resolved from the
    | token abilities, so a reporting integration can be given a higher read
    | ceiling than a script that only files drafts.
    |
    */

    'rate_limits' => [
        'read' => (int) env('API_RATE_LIMIT_READ', 120),
        'write' => (int) env('API_RATE_LIMIT_WRITE', 30),
    ],

];
