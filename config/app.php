<?php
// App configuration (paths, runtime settings)
$app = [
    // Python executable to call (set as full path if Apache can't find 'python')
    // Updated to point to installed Python 3.11 in the project environment
    'python_path' => getenv('PYTHON_PATH') ?: __DIR__ . '/../py/python.exe',

    // Folder where planilhas will be written (must be writable by web server)
    'planilhas_folder' => __DIR__ . '/../uploads/planilhas',

    // Folder where generated PDFs will be written (must be writable by web server)
    'pdfs_folder' => __DIR__ . '/../uploads/pdfs',

    // Path to LibreOffice/soffice for PDF conversion (if not set, will attempt to use 'soffice' from PATH)
    'libreoffice_path' => getenv('LIBREOFFICE_PATH') ?: 'soffice',

    // Logs folder
    'logs_folder' => __DIR__ . '/../logs'
];

// App configuration (paths)
define('BASE_DIR', __DIR__ . '/..');
define('UPLOADS_DIR', BASE_DIR . '/uploads');
define('ASSINATURAS_DIR', UPLOADS_DIR . '/assinaturas');
define('PLANILHAS_DIR', UPLOADS_DIR . '/planilhas');
define('PDFS_DIR', UPLOADS_DIR . '/pdfs');
define('EVIDENCIAS_DIR', UPLOADS_DIR . '/evidencias');
define('TMP_DIR', BASE_DIR . '/tmp');
define('LOGS_DIR', BASE_DIR . '/logs');
// Default destination email for automatic sends
// Per project requirement, default to the provided address if env var is not set
define('DEFAULT_EMAIL', getenv('DEFAULT_TO') ?: '');

// Configure PHP error handling for the app (log to file, do not display to client)
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', LOGS_DIR . '/php_errors.log');

// Ensure directories exist
foreach ([UPLOADS_DIR, ASSINATURAS_DIR, PLANILHAS_DIR, PDFS_DIR, EVIDENCIAS_DIR, TMP_DIR, LOGS_DIR] as $d){
    if(!file_exists($d)) @mkdir($d, 0777, true);
}

// Use the bundled project Python binary (guaranteed absolute path). Do not fall back to a bare 'python' invocation.
$proj_py = realpath($app['python_path']);
if(!$proj_py || !file_exists($proj_py)){
    // If realpath failed, still prefer the expected bundled path and log a clear message for admins.
    $expected = $app['python_path'];
    file_put_contents(LOGS_DIR . '/python_detect.log', "[".date('c')."] Embedded python not found at expected path: {$expected}. Please ensure the bundled python exists. Using that path anyway.\n", FILE_APPEND);
    $proj_py = $expected;
}
// Final constant used throughout the app to run Python scripts
if(!defined('PYTHON_BIN')){
    define('PYTHON_BIN', $proj_py);
}

return $app;