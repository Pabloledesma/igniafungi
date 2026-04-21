<?php

return [
    'client_id' => env('SALESFORCE_CLIENT_ID'),
    'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
    'login_url' => env('SALESFORCE_LOGIN_URL', 'https://login.salesforce.com'),
];
