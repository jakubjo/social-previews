<?php

return [
    'route_prefix' => 'social-previews',

    'route_middleware' => ['web'],

    'ensure_gitignore_exists' => true,

    'disable_image_cache' => false,

    'font_cache_path' => storage_path('social-previews-fonts'),

    'renderer' => [
        'padding' => [
            'left' => 50,
            'top' => 50,
            'right' => 50,
            'bottom' => 50,
        ],

        'background' => [
            'type' => 'image',

            'color' => [195,130,250],

            'gradient' => [
                'from' => [195,130,250],
                'to' => [240,70,70],
                'angle' => 45,
            ],

            'image' => [
                'path' => public_path('/imgs/social-preview.png'),
            ],
        ],

        'image' => [
            'public_path' => '/assets/hannes-johnson-mRgffV3Hc6c-unsplash.jpg',
            'position' => 'right',
            'max_width' => 400,
            'max_height' => 630,
            'border' => 20,
            'border_color' => [0,0,0],
        ],

        'font' => [
            'family' => 'Roboto',
            'weight' => '400',
            'color' => [0,0,0],
            'size' => 50,
        ],

        'text' => 'Share this!',
    ],
];
