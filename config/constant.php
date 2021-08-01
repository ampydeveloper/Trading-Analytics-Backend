<?php

return [
    "days" => [
        1 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'daysForSx' => 0
        ],
        2 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-8 day')),
            'daysForSx' => 7
        ],
        3 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-30 day')),
            'daysForSx' => 30
        ],
        4 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-90 day')),
            'daysForSx' => 90
        ],
        5 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-180 day')),
            'daysForSx' => 180
        ],
        6 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-365 day')),
            'daysForSx' => 365
        ],
        7 => [
            'from' => date('Y-m-d H:i:s'),
            'to' => date('Y-m-d H:i:s', strtotime('-1825 day')),
            'daysForSx' => 1825
        ],
    ],
    'sports' => [
        0 => 'basketball',
        1 => 'soccer',
        2 => 'baseball',
        3 => 'football',
        4 => 'hockey',
        5 => 'pokemon',
    ],
];
