<?php

/**
 * Plugin „wmConsentFilter“, Ereignis OnWebPagePrerender: blockt bekannte Tracker und Einbettungen (YouTube, Maps …)
 * im fertigen HTML jeder Seite (OutputFilter), bevor MODX es ausliefert. Nichts im Inhalt markieren nötig.
 *
 * @var modX $modx
 */
if ($modx->event->name !== 'OnWebPagePrerender' || ! isset($modx->resource) || $modx->context->get('key') === 'mgr') {
    return;
}

require_once MODX_CORE_PATH.'components/wm-consent/bootstrap.php';

$settings = wm_consent_settings($modx);

if ((isset($settings['enabled']) && ! $settings['enabled']) || (isset($settings['filter']) && ! $settings['filter'])) {
    return;
}

$contentType = $modx->resource->getOne('ContentType');

if ($contentType && stripos((string) $contentType->get('mime_type'), 'html') === false) {
    return;
}

$modx->resource->_output = \WursterMedien\Consent\Core\OutputFilter::filter((string) $modx->resource->_output);
