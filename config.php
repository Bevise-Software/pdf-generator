<?php

/**
 * Generator configuration. All dimensions in millimetres,
 * measured from the top-left corner of the page.
 */
return [
    'template'    => __DIR__ . '/wzor.pdf',
    'csv'         => __DIR__ . '/odbiorcy.csv',
    'output_dir'  => __DIR__ . '/pdf',
    'report'      => __DIR__ . '/raport_generowania.csv',
    'grid_output' => __DIR__ . '/siatka_kalibracyjna.pdf',
    'font_cache'  => __DIR__ . '/fonts/_tcpdf/',
    'log'         => __DIR__ . '/logs/generator.log',

    // Overwrite existing files in output_dir (--preview always overwrites)
    'overwrite'   => false,

    'fields' => [
        'full_name' => [
            'page'     => 1,
            'x'        => 20.0,      // calibrate with --grid
            'y'        => 120.0,     // calibrate with --grid
            'width'    => 170.0,
            'align'    => 'center',  // left / center / right
            'font'     => __DIR__ . '/fonts/DejaVuSans.ttf',
            'size'     => 28.0,      // starting size in pt
            'min_size' => 14.0,      // minimum size in pt
            'color'    => '#1A1A1A', // '#RRGGBB' or [R, G, B]
        ],
        'number' => [
            'page'     => 1,
            'x'        => 20.0,
            'y'        => 150.0,
            'width'    => 170.0,
            'align'    => 'center',
            'font'     => __DIR__ . '/fonts/DejaVuSans.ttf',
            'size'     => 14.0,
            'min_size' => 8.0,
            'color'    => [80, 80, 80],
        ],
    ],
];
