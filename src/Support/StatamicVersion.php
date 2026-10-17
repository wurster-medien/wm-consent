<?php

namespace WursterMedien\Consent\Support;

use Composer\InstalledVersions;
use Throwable;

/**
 * Hauptversion des installierten Statamic: Das Control Panel von Statamic 5 (Vue 2, Komponente <publish-form>) und
 * das von Statamic 6 (Inertia, Statamic\CP\PublishForm) brauchen je einen eigenen Weg zur Formular-Seite.
 */
class StatamicVersion
{
    public function major(): int
    {
        try {
            $version = InstalledVersions::getVersion('statamic/cms');
        } catch (Throwable) {
            $version = null;
        }

        if (is_string($version) && preg_match('/^v?(\d+)\./', $version, $matches)) {
            return (int) $matches[1];
        }

        return 6;
    }
}
