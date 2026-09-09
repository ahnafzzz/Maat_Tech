<?php

return [
    'invitation_expiration_minutes' => (int) env('ADMIN_INVITATION_EXPIRATION_MINUTES', 60),
    'two_factor_expiration_minutes' => (int) env('ADMIN_TWO_FACTOR_EXPIRATION_MINUTES', 10),
    'two_factor_max_attempts' => (int) env('ADMIN_TWO_FACTOR_MAX_ATTEMPTS', 5),
    'two_factor_max_challenges_per_hour' => (int) env('ADMIN_TWO_FACTOR_MAX_CHALLENGES_PER_HOUR', 5),
];
