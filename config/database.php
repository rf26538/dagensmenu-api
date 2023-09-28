<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => env('DB_PREFIX', ''),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_EAT', '127.0.0.1'),
            'port' => env('DB_PORT_EAT', 3306),
            'database' => env('DB_DATABASE_EAT', 'forge'),
            'username' => env('DB_USERNAME_EAT', 'forge'),
            'password' => env('DB_PASSWORD_EAT', ''),
            'unix_socket' => env('DB_SOCKET_EAT', ''),
            'charset' => env('DB_CHARSET_EAT', 'utf8mb4'),
            'collation' => env('DB_COLLATION_EAT', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX_EAT', ''),
            'strict' => env('DB_STRICT_MODE_EAT', true),
            'engine' => env('DB_ENGINE_EAT', null),
            'timezone' => env('DB_TIMEZONE_EAT', '+00:00'),
        ],
        'mysql_eat_order' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_EAT_ORDER', '127.0.0.1'),
            'port' => env('DB_PORT_EAT_ORDER', 3306),
            'database' => env('DB_DATABASE_EAT_ORDER', 'forge'),
            'username' => env('DB_USERNAME_EAT_ORDER', 'forge'),
            'password' => env('DB_PASSWORD_EAT_ORDER', ''),
            'unix_socket' => env('DB_SOCKET_EAT_ORDER', ''),
            'charset' => env('DB_CHARSET_EAT_ORDER', 'utf8mb4'),
            'collation' => env('DB_COLLATION_EAT_ORDER', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX_EAT_ORDER', ''),
            'strict' => env('DB_STRICT_MODE_EAT_ORDER', true),
            'engine' => env('DB_ENGINE_EAT_ORDER', null),
            'timezone' => env('DB_TIMEZONE_EAT_ORDER', '+00:00'),
        ],
        'mysql_eat_log' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_EAT_LOG', '127.0.0.1'),
            'port' => env('DB_PORT_EAT_LOG', 3306),
            'database' => env('DB_DATABASE_EAT_LOG', 'forge'),
            'username' => env('DB_USERNAME_EAT_LOG', 'forge'),
            'password' => env('DB_PASSWORD_EAT_LOG', ''),
            'unix_socket' => env('DB_SOCKET_EAT_LOG', ''),
            'charset' => env('DB_CHARSET_EAT_LOG', 'utf8mb4'),
            'collation' => env('DB_COLLATION_EAT_LOG', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX_EAT_LOG', ''),
            'strict' => env('DB_STRICT_MODE_EAT_LOG', false),
            'engine' => env('DB_ENGINE_EAT_LOG', null),
            'timezone' => env('DB_TIMEZONE_EAT_LOG', '+00:00'),
        ],

        'mysql_eat_automated_collection' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_EAT_AUTOMATED_COLLECTION', '127.0.0.1'),
            'port' => env('DB_PORT_EAT_AUTOMATED_COLLECTION', 3306),
            'database' => env('DB_DATABASE_EAT_AUTOMATED_COLLECTION', 'forge'),
            'username' => env('DB_USERNAME_EAT_AUTOMATED_COLLECTION', 'forge'),
            'password' => env('DB_PASSWORD_EAT_AUTOMATED_COLLECTION', ''),
            'unix_socket' => env('DB_SOCKET_EAT_AUTOMATED_COLLECTION', ''),
            'charset' => env('DB_CHARSET_EAT_AUTOMATED_COLLECTION', 'utf8mb4'),
            'collation' => env('DB_COLLATION_EAT_AUTOMATED_COLLECTION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX_EAT_AUTOMATED_COLLECTION', ''),
            'strict' => env('DB_STRICT_MODE_EAT_AUTOMATED_COLLECTION', true),
            'engine' => env('DB_ENGINE_EAT_AUTOMATED_COLLECTION', null),
            'timezone' => env('DB_TIMEZONE_EAT_AUTOMATED_COLLECTION', '+00:00'),
        ],

        'mysql_eat_cache' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_EAT_CACHE', '127.0.0.1'),
            'port' => env('DB_PORT_EAT_CACHE', 3306),
            'database' => env('DB_DATABASE_EAT_CACHE', 'forge'),
            'username' => env('DB_USERNAME_EAT_CACHE', 'forge'),
            'password' => env('DB_PASSWORD_EAT_CACHE', ''),
            'unix_socket' => env('DB_SOCKET_EAT_CACHE', ''),
            'charset' => env('DB_CHARSET_EAT_CACHE', 'utf8mb4'),
            'collation' => env('DB_COLLATION_EAT_CACHE', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX_EAT_CACHE', ''),
            'strict' => env('DB_STRICT_MODE_EAT_CACHE', true),
            'engine' => env('DB_ENGINE_EAT_CACHE', null),
            'timezone' => env('DB_TIMEZONE_EAT_CACHE', '+00:00'),
        ],

        'mysql_db_location' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_LOCATION_LOG', '127.0.0.1'),
            'port' => env('DB_PORT_LOCATION_LOG', 3306),
            'database' => env('DB_DATABASE_LOCATION_LOG', 'forge'),
            'username' => env('DB_USERNAME_LOCATION_LOG', 'forge'),
            'password' => env('DB_PASSWORD_LOCATION_LOG', ''),
            'unix_socket' => env('DB_SOCKET_LOCATION_LOG', ''),
            'charset' => env('DB_CHARSET_LOCATION_LOG', 'utf8mb4'),
            'collation' => env('DB_COLLATION_LOCATION_LOG', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX_LOCATION_LOG', ''),
            'strict' => env('DB_STRICT_MODE_LOCATION_LOG', true),
            'engine' => env('DB_ENGINE_LOCATION_LOG', null),
            'timezone' => env('DB_TIMEZONE_LOCATION_LOG', '+00:00'),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => env('DB_PREFIX', ''),
            'schema' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSL_MODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 1433),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => env('DB_PREFIX', ''),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => 'predis',

        'cluster' => env('REDIS_CLUSTER', false),

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

];
