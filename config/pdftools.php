<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rutas de herramientas PDF
    |--------------------------------------------------------------------------
    |
    | Configura las rutas a las herramientas externas necesarias para
    | la conversión y validación de PDFs. Si se deja vacío, el sistema
    | intentará autodetectar las herramientas.
    |
    | Windows ejemplos:
    |   - C:\Program Files\gs\gs10.06.0\bin\gswin64c.exe
    |   - C:\Poppler\Library\bin\pdfimages.exe
    |
    | Linux ejemplos:
    |   - /usr/bin/gs o simplemente: gs
    |   - /usr/bin/pdfimages o simplemente: pdfimages
    |
    */

    'ghostscript' => env('GHOSTSCRIPT_PATH', ''),
    'pdfimages' => env('PDFIMAGES_PATH', ''),
    'qpdf' => env('QPDF_PATH', ''),
    'imagemagick' => env('IMAGEMAGICK_PATH', ''),
];
