<?php

return [
    // Máximo de adaptaciones de CV gratuitas para visitantes no suscritos.
    'tailor_limit' => (int) env('DEMO_TAILOR_LIMIT', 3),

    // Días que dura el bloqueo por IP una vez alcanzado el límite.
    'tailor_decay_days' => (int) env('DEMO_TAILOR_DECAY_DAYS', 14),
];
