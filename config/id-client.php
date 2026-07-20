<?php

return [
    /*
     | The guard used to log the user in after a successful SSO callback.
     */
    'guard' => env('THIJSSENSOFTWARE_ID_GUARD', 'web'),

    /*
     | The Eloquent user model to provision and authenticate.
     */
    'user_model' => env('THIJSSENSOFTWARE_ID_USER_MODEL', 'App\\Models\\User'),

    /*
     | Where to send the user after a successful sign-in (fallback for
     | redirect()->intended()).
     */
    'home' => env('THIJSSENSOFTWARE_ID_HOME', '/dashboard'),

    /*
     | Just-in-time provisioning. When true, a user who authenticates at the
     | IdP (and is authorised for this app) but has no local account yet is
     | created automatically. Finance keeps this off: accounts are created
     | deliberately, and an unknown SSO user is denied rather than provisioned.
     */
    'provision' => env('THIJSSENSOFTWARE_ID_PROVISION', false),
];
