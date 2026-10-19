<?php

/**
 * Baut das MODX-Paket zum Einspielen per FTP/SFTP: build/wm-consent-modx.zip mit
 *   core/components/wm-consent/   (bootstrap.php, elements/, src/Core/)
 *   assets/components/wm-consent/ (wm-consent.js, wm-consent.css)
 * Vorher `npm run build`, falls sich der Kern geändert hat. Aufruf: php tools/build-modx.php
 */

$root = dirname(__DIR__);
$target = $root.'/build/wm-consent-modx';

$copy = function (string $from, string $to) use (&$copy): void {
    if (is_dir($from)) {
        @mkdir($to, 0775, true);

        foreach (scandir($from) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $copy($from.'/'.$entry, $to.'/'.$entry);
            }
        }

        return;
    }

    @mkdir(dirname($to), 0775, true);
    copy($from, $to);
};

$remove = function (string $path) use (&$remove): void {
    if (is_dir($path)) {
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $remove($path.'/'.$entry);
            }
        }

        rmdir($path);
    } elseif (is_file($path)) {
        unlink($path);
    }
};

$remove($target);
$copy($root.'/modx/core', $target.'/core');
$copy($root.'/src/Core', $target.'/core/components/wm-consent/src/Core');
$copy($root.'/dist/wm-consent.js', $target.'/assets/components/wm-consent/wm-consent.js');
$copy($root.'/dist/wm-consent.css', $target.'/assets/components/wm-consent/wm-consent.css');
$copy($root.'/modx/wmConsentConfig.example.json', $target.'/wmConsentConfig.example.json');
$copy($root.'/modx/README.md', $target.'/README.md');
$copy($root.'/modx/wm-consent-site.php', $target.'/wm-consent-site.php');

$zipPath = $root.'/build/wm-consent-modx.zip';
@unlink($zipPath);
$zip = new ZipArchive;
$zip->open($zipPath, ZipArchive::CREATE);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    $zip->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), strlen($target) + 1)));
}

$zip->close();

echo "build/wm-consent-modx.zip gebaut.\n";
