<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),
    'session_domain' => env('SESSION_DOMAIN', '.taheiya.sa'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONT_END_URL', 'https://rcp-siyaq.taheiya.sa'),
    'admin_email' => env('ADMIN_MAIL','faisaln@taheiya.sa'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'Asia/Riyadh',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Rate Limit
    |--------------------------------------------------------------------------
    |
    | This value controls the number of requests per minute that can be made
    | to the application by a single user or IP address.
    |
    */

    'rate_limit' => env('RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store' => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        Maatwebsite\Excel\ExcelServiceProvider::class,

    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
        'Excel' => Maatwebsite\Excel\Facades\Excel::class,
    ])->toArray(),

    'client_name' => env('CLIENT_NAME', 'GGI'),
    'has_otp_verification' => env('HAS_OTP_VERIFICATION', false),
    'company_name_ar' => env('COMPANY_NAME_AR', 'إسم الشركة'),
    'company_name_en' => env('COMPANY_NAME_EN', 'Company Name'),

    'have_ivr' => env('HAVE_IVR', false),

    'ivrs' => [
        'IVR_APPROVE'=>env('IVR_APPROVE', '684ac591-e1a9-4b2b-9a25-7f507156d3af'),
        'IVR_DELAY_ACCEPT'=>env('IVR_DELAY_ACCEPT', '836b6d3c-5b8a-459a-9817-f98847b4ba21'),
        'IVR_PARTIAL_ACCEPT'=>env('IVR_PARTIAL_ACCEPT', '9d6e5c2c-13a0-4e36-b578-7d1fe246289a'),
        'IVR_DELAY_TIME'=>env('IVR_DELAY_TIME', '4bd76823-133d-459c-83d5-b8486ae15c8b'),
        'IVR_INSTALLMENT_TIME'=>env('IVR_INSTALLMENT_TIME', 'c5178b16-3a8a-4a5c-a44f-6157de734de0'),
        'IVR_DEBTOR_REQUEST_ACCEPT'=>env('IVR_DEBTOR_REQUEST_ACCEPT', '6736006b-27c8-49e4-a2ba-a2b6dd88163a'),
        'IVR_DEBTOR_REQUEST_REJECT'=>env('IVR_DEBTOR_REQUEST_REJECT', '436c7ae8-e90e-4cab-8959-069746fee7a8'),
    ],

    'elm' => [
        'enabled' => env('HAVE_ELM', false),
        'url' => env('ELM_URL', 'https://api.absher.sa/AbsherSmsNotification'),
        'wsdl' => env('ELM_WSDL_URL', 'https://api.absher.sa/AbsherSmsNotification?wsdl'),
        'client_id' => env('ELM_CLIENT_ID', ''),
        'authorization' => env('ELM_CLIENT_AUTHORIZATION', ''),
        'certificate_path' => env('ELM_CERTIFICATE_PATH', ''),
        'password' => env('ELM_PASSWORD', ''),
        'ELM_TEMPLATE_SUBMIT_REQUEST' => env('ELM_TEMPLATE_SUBMIT_REQUEST', ''),
    ],

    'art_sms_provider' => [
        'base_url' => env('ALRAJHI_TAKAFUL_SMS_BASE_URL', 'https://apioceuat.alrajhitakaful.com'),
        'redirect_uri' => env('ALRAJHI_TAKAFUL_SMS_REDIRECT_URI', 'https://www.alrajhitakaful.com'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Puppeteer Configuration
    |--------------------------------------------------------------------------
    */
    'puppeteer' => [
        'puppeteer_chrome_path' => env('PUPPTEER_CHROME_PATH', '/usr/bin/google-chrome'),
        'puppeteer_npm_binary'  => env('PUPPTEER_NPM_BINARY', '/home/init0x1/.nvm/versions/node/v22.20.0/bin/npm'),
        'puppeteer_node_binary' => env('PUPPTEER_NODE_BINARY', '/home/init0x1/.nvm/versions/node/v22.20.0/bin/node'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maqsam Configuration
    |--------------------------------------------------------------------------
    */
    'maqsam' => [
        'maqsam_access_key_id'          => env('MAQSAM_ACCESS_KEY_ID', 'FXNBuNGRA3nxFVeju4L1'),
        'maqsam_access_secret'          => env('MAQSAM_ACCESS_SECRET', 'LQM5Hh4KUOgwr3OpiVUt'),
        'maqsam_active'                 => env('MAQSAM_ACTIVE', null),
        'maqsam_inquiry_url'            => env('MAQSAM_INQUIRY_URL', null),
        'maqsam_max_trials'             => env('MAQSAM_MAX_TRIALS', 1),
        'maqsam_retry_delay'            => env('MAQSAM_RETRY_DELAY', 0),
        'maqsam_callback_url'           => env('MAQSAM_CALLBACK_URL', 'http://localhost:8000/api/maqsam/callback'),
        'maqsam_valid_entries'          => env('MAQSAM_VALID_ENTRIES', '0,1,2,3,4,5,6,7,8,9,*,#'),
        'maqsam_valid_entries_urls'     => env('MAQSAM_VALID_ENTRIES_URLS', null),
        'maqsam_invalid_entry_url'      => env('MAQSAM_INVALID_ENTRY_URL', null),
        'maqsam_timeout_period'         => env('MAQSAM_TIMEOUT_PERIOD', 600),
        'maqsam_timeout_url'            => env('MAQSAM_TIMEOUT_URL', null),
        'maqsam_record_message_entries' => env('MAQSAM_RECORD_MESSAGE_ENTRIES', null),
        'maqsam_max_recording_length'   => env('MAQSAM_MAX_RECORDING_LENGTH', 60),
    ],


    'taheiya_demo_mode' => env('TAHEIYA_DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Client Access Configuration
    |--------------------------------------------------------------------------
    */
    'client_view' => [
        'ttl_minutes' => env('CLIENT_VIEW_TTL_MINUTES', 20),
    ],

];
