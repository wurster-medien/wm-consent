<?php

/**
 * Snippet „wmConsent“: in den <head> jedes Templates, unbedingt ungecacht: [[!wmConsent]]
 *
 * Gibt Stylesheet, Einstellungen und Skript des Cookie-Banners aus (HeadTag). Den Link „Cookie-Einstellungen“ im
 * Footer schreibt man als <a href="#cookie-einstellungen" data-wm-consent-open>Cookie-Einstellungen</a>.
 *
 * @var modX $modx
 */
require_once MODX_CORE_PATH.'components/wm-consent/bootstrap.php';

$settings = wm_consent_settings($modx);

if (isset($settings['enabled']) && ! $settings['enabled']) {
    return '';
}

if (! isset($settings['auto_cookies']) || $settings['auto_cookies']) {
    $sessionName = (string) ($modx->getOption('session_name') ?: session_name() ?: 'PHPSESSID');
    $settings['cookies'] = array_merge(
        [['name' => $sessionName, 'duration' => 'Ende der Sitzung', 'description' => 'Hält Ihre Sitzung auf dieser Website, z. B. für Formulare.']],
        (array) ($settings['cookies'] ?? [])
    );
}

$assetsUrl = $modx->getOption('assets_url').'components/wm-consent/';
$assetsPath = $modx->getOption('assets_path').'components/wm-consent/';

return \WursterMedien\Consent\Core\HeadTag::render($settings, $assetsUrl, wm_consent_version($assetsPath));
