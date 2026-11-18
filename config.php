<?php

/**
 * Generator configuration. All dimensions in millimetres,
 * measured from the top-left corner of the page.
 */
return [
    'template'    => __DIR__ . '/wzor.pdf',
    'csv'         => __DIR__ . '/lista.csv',

    // CSV column separator (e.g. ';' for Excel exports, ',' for plain CSV)
    'csv_separator' => ',',

    /*
     * Columns required in the CSV regardless of templates (e.g. kept for the
     * mailing script). Columns referenced by {placeholders} in 'filename' and
     * field 'value' templates are always required automatically.
     */
    'required_columns' => [],

    /*
     * DEFAULT OUTPUT FILENAME template (without extension, .pdf is always
     * appended). {kolumna} placeholders use CSV values, exactly like in field
     * 'value' templates (case-sensitive, must match the CSV header).
     * Polish diacritics are transliterated (ń -> n) and unsafe characters
     * removed automatically, so names are always filesystem/e-mail safe.
     * Every column referenced here becomes a required CSV column.
     * The template edited in the web UI is saved to 'settings' and
     * overrides this default (also for generuj.php).
     * Examples: '{imie}_{nazwisko}_{numer}', 'voucher-{KOD}'
     */
    'filename'    => 'Certyfikat_diagnozy_NPWZ-{IMIĘ}_{NAZWISKO}-{PWZ}',
    'settings'    => __DIR__ . '/settings.json',

    'output_dir'  => __DIR__ . '/pdf',
    'report'      => __DIR__ . '/raport_generowania.csv',
    'grid_output' => __DIR__ . '/siatka_kalibracyjna.pdf',
    'test_output' => __DIR__ . '/test.pdf',
    'font_cache'  => __DIR__ . '/fonts/_tcpdf/',
    'log'         => __DIR__ . '/logs/generator.log',

    // Overwrite existing files in output_dir (--preview always overwrites)
    'overwrite'   => false,

    /*
     * FIELDS — every text stamped onto the template — are defined in
     * settings.json under "fields" (one object per field). Adding a field
     * there needs no code changes. A 'fields' array here is used only
     * when settings.json has no "fields" key.
     *
     *   "value"    - text template; {kolumna} is replaced with the CSV column
     *                value, e.g. "{imie} {nazwisko}", "Nr: {numer}".
     *                Every column referenced here automatically becomes a
     *                required CSV column (validated before generation).
     *   "page"     - template page the text is stamped on
     *   "x", "y"   - top-left corner of the text box in mm (calibrate with --grid)
     *   "width"    - text box width in mm (auto font shrinking fits text into it)
     *   "align"    - left / center / right
     *   "font"     - TTF file path, relative to settings.json (or absolute)
     *   "size"     - starting font size in pt
     *   "min_size" - minimum font size in pt (below this a warning is reported)
     *   "color"    - "#RRGGBB" or [R, G, B]
     *   "transform"- optional: none (default) / uppercase / lowercase,
     *                applied to the stamped text only (not filenames)
     */
];
