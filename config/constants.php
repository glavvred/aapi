<?php

namespace App;

return [
    'distance' => [
        'interplanetary' => 1,
        'interstallar' => 100,
    ],
    'time' => [
        'interplanetary' => 300,
        'interstallar' => 3000,
    ],
    'money' => [
        'refund' => 0.25,
        'paid_refund' => 0.75,
    ],
    'registration' => [
        'planet_type' => 1,
        'resources' => [
            'metal' => 2500,
            'crystal' => 2500,
            'gas' => 1000,
            'dark_matter' => 10,
        ],
    ],
    'galaxy' => [
        'dimensions' => [
            'x' => 10,
            'y' => 10,
            'orbit' => [
                'max' => 30,
                'max_inhabited' => 25,
                'min_inhabited' => 5,
                'min' => 1,
            ],
            'user_per_solar_system' => 5,
            'planets_per_solar_system' => [
                'min' => 10,
                'max' => 20
            ],
        ],
        'planet' => [
            'slots' => [
                'min' => 50,
                'max' => 800,
            ],
            'temperature' => [
                'min' => -100,
                'max' => 100,
            ],
            'diameter' => [
                'min' => 3000,
                'max' => 200000,
            ],
            'density' => [
                'min' => 100,
                'max' => 1000,
            ],
            'capacity' => [
                'metal' => 100000,
                'crystal' => 100000,
                'gas' => 100000,
            ],
            'resources_overflow_divider' => 20,
        ]
    ]
];