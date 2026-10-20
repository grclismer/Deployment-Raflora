<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Defines the proxy IP addresses to trust behind reverse proxies or load
    | balancers (e.g. Railway). Setting this to '*' trusts any calling proxy IP,
    | which accommodates dynamic container networking in hosted PaaS environments.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '*'),

];
