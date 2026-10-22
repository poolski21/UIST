<?php

return [
    'student_second_factor' => env('UIST_STUDENT_SECOND_FACTOR', false),
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
