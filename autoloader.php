<?php
/**
 * Autoloader for Antavo Loyalty API PHP SDK.
 */
spl_autoload_register(function(/*string*/ $class) {
    $pattern = '/^\\\?Antavo\\\/';
    if (preg_match($pattern, $class)) {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
            . strtr(preg_replace($pattern, '', $class), '\\', DIRECTORY_SEPARATOR) . '.php';
        if (is_file($file)) {
            include $file;
        }
    }
});
