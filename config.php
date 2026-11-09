<?php

/**
 * Generator configuration. All dimensions in millimetres,
 * measured from the top-left corner of the page.
 */
return [
    'template'    => __DIR__ . '/Certyfikat_diagnozy_npwz.pdf',
    'csv'         => __DIR__ . '/odbiorcy.csv',

    /*
     * OUTPUT FILENAME template. {kolumna} placeholders use CSV values,
     * exactly like in field 'value' templates. Must end with .pdf.
     * Polish diacritics are transliterated (ń -> n) and unsafe characters
     * removed automatically, so names are always filesystem/e-mail safe.
     * Every column referenced here becomes a required CSV column.
     * Examples: '{imie}_{nazwisko}_{numer}.pdf', 'certyfikat_{numer}.pdf', '{plik}'
     */
    'filename'    => 'Certyfikat_diagnozy_NPWZ-{imie}_{nazwisko}.pdf',

    'output_dir'  => __DIR__ . '/pdf',
    'report'      => __DIR__ . '/raport_generowania.csv',
    'grid_output' => __DIR__ . '/siatka_kalibracyjna.pdf',
    'font_cache'  => __DIR__ . '/fonts/_tcpdf/',
    'log'         => __DIR__ . '/logs/generator.log',

    // Overwrite existing files in output_dir (--preview always overwrites)
    'overwrite'   => false,

    /*
     * FIELDS — every text stamped onto the template.
     *
     * TO ADD A NEW FIELD: add one entry below. Nothing else needs changing.
     *
     *   'value'    - text template; {kolumna} is replaced with the CSV column
     *                value, e.g. '{imie} {nazwisko}', '{numer}', 'Nr: {numer}'.
     *                Every column referenced here automatically becomes a
     *                required CSV column (validated before generation).
     *   'page'     - template page the text is stamped on
     *   'x', 'y'   - top-left corner of the text box in mm (calibrate with --grid)
     *   'width'    - text box width in mm (auto font shrinking fits text into it)
     *   'align'    - left / center / right
     *   'font'     - path to a TTF file (UTF-8 / Polish characters supported)
     *   'size'     - starting font size in pt
     *   'min_size' - minimum font size in pt (below this a warning is reported)
     *   'color'    - '#RRGGBB' or [R, G, B]
     */
    'fields' => [
        'full_name' => [
            'value'    => '{imie} {nazwisko}',
            'page'     => 1,
            'x'        => 150.0,
            'y'        => 170.0,
            'width'    => 300.0,
            'align'    => 'center',
            'font'     => __DIR__ . '/fonts/DejaVuSans.ttf',
            'size'     => 28.0,
            'min_size' => 14.0,
            'color'    => '#1A1A1A',
        ],
        'number' => [
            'value'    => '{numer}',
            'page'     => 1,
            'x'        => 150.0,
            'y'        => 85.0,
            'width'    => 170.0,
            'align'    => 'center',
            'font'     => __DIR__ . '/fonts/DejaVuSans.ttf',
            'size'     => 14.0,
            'min_size' => 8.0,
            'color'    => [80, 80, 80],
        ],
    ],
];
