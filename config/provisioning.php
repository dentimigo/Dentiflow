<?php

return [
    'superadmin' => [
        'name' => 'Administrador de DentiFlow',
        'email' => env('DENTIFLOW_SUPERADMIN_EMAIL'),
        'password' => env('DENTIFLOW_SUPERADMIN_PASSWORD'),
    ],
    'admin' => [
        'name' => 'Administrador operativo',
        'email' => env('DENTIFLOW_ADMIN_EMAIL'),
        'password' => env('DENTIFLOW_ADMIN_PASSWORD'),
    ],
];
