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

/*
 * Website = Kontext (sites.<kontext> im Chunk). Sprache: lang im Chunk, sonst der cultureKey, wenn der Kontext ihn
 * selbst setzt (Babel & Co.), sonst <html lang> der Seite (im Skript).
 */
$settings['site'] = $modx->context->get('key');

if (empty($settings['lang']) && ! empty($modx->context->config['cultureKey'])) {
    $settings['lang'] = $modx->context->config['cultureKey'];
}

/* Session-Cookie mit der Laufzeit aus MODX (session_cookie_lifetime in Sekunden, 0 = Ende der Sitzung). */
if (! isset($settings['auto_cookies']) || $settings['auto_cookies']) {
    $sessionName = (string) ($modx->getOption('session_name') ?: session_name() ?: 'PHPSESSID');
    $sessionMinutes = intdiv(max(0, (int) $modx->getOption('session_cookie_lifetime', null, 0)), 60);
    $settings['cookies'] = array_merge(
        [['name' => $sessionName, 'duration' => \WursterMedien\Consent\Core\Lang::minutes($sessionMinutes), 'description' => \WursterMedien\Consent\Core\Lang::all('session')]],
        (array) ($settings['cookies'] ?? [])
    );
}

$assetsUrl = $modx->getOption('assets_url').'components/wm-consent/';
$assetsPath = $modx->getOption('assets_path').'components/wm-consent/';

return \WursterMedien\Consent\Core\HeadTag::render($settings, $assetsUrl, wm_consent_version($assetsPath));
