<?php
$phar = new Phar(
    __DIR__ . '/build/antavo-sdk.phar',
    FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
    'antavo-sdk.phar'
);

foreach (glob(__DIR__ . '/src/*') as $file) {
    $phar->addFile($file, preg_replace('#^' . preg_quote(__DIR__, '#') . '/#', '', $file));
}

$phar->addFile('autoloader.php');
$phar->setStub($phar->createDefaultStub('autoloader.php'));
