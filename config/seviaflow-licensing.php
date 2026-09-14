<?php

return [
    'enforce' => (bool) env('SEVIAFLOW_LICENSE_ENFORCE', true),
    'issuer' => (string) env('SEVIAFLOW_LICENSE_ISSUER', 'seviaflow-hub'),
    'audience' => (string) env('SEVIAFLOW_LICENSE_AUDIENCE', 'seviaflow-installation'),
    'installation_uuid' => env('SEVIAFLOW_INSTALLATION_UUID'),
    'environment' => (string) env('APP_ENV', 'production'),
    'trusted_keys' => [],
    'activation_path' => 'private/seviaflow-licenses.json',
    'clock_skew_seconds' => 300,
];
