<?php

return [
    'admin_initial_password' => env('ADMIN_INITIAL_PASSWORD'),
    'admission_average' => (float) env('UIST_ADMISSION_AVERAGE', 10),
    'student_login_attempts' => (int) env('UIST_STUDENT_LOGIN_ATTEMPTS', 5),
    'student_login_decay_minutes' => (int) env('UIST_STUDENT_LOGIN_DECAY_MINUTES', 1),
    'social_links' => [
        'x' => env('UIST_SOCIAL_X', '#'),
        'google' => env('UIST_SOCIAL_GOOGLE', '#'),
        'pinterest' => env('UIST_SOCIAL_PINTEREST', '#'),
        'facebook' => env('UIST_SOCIAL_FACEBOOK', '#'),
    ],
];
