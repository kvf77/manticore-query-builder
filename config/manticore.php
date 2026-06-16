<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Which low-level driver to use to talk to Manticore/Sphinx.
    | Supported: "pdo", "mysqli".
    |
    */
    'driver' => env('MANTICORE_DRIVER', 'pdo'),

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    |
    | Manticore listens on the MySQL protocol port (default 9306). Set a unix
    | socket instead of host/port if you prefer.
    |
    */
    'host'   => env('MANTICORE_HOST', '127.0.0.1'),
    'port'   => (int) env('MANTICORE_PORT', 9306),
    'socket' => env('MANTICORE_SOCKET'),

];
