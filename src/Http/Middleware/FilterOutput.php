<?php

namespace WursterMedien\Consent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WursterMedien\Consent\Core\OutputFilter;
use WursterMedien\Consent\Core\Services;
use WursterMedien\Consent\Detected;
use WursterMedien\Consent\Settings;

/**
 * Blockt bekannte Tracker und Einbettungen im ausgelieferten HTML (OutputFilter), nur für HTML-Antworten der Seite
 * (Gruppe statamic.web, nicht im Control Panel). Was er findet, merkt sich Detected für die Übersicht im Control Panel.
 */
class FilterOutput
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app(Settings::class)->get('enabled', true) || ! app(Settings::class)->get('filter', true)) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        $content = $response->getContent();

        if (! is_string($content) || $content === '' || ($type !== '' && ! str_contains($type, 'text/html'))) {
            return $response;
        }

        ['html' => $filtered, 'detected' => $detected] = OutputFilter::process($content);

        /* Für die Übersicht auch, was schon markiert war ({{ wm_consent:script service="…" }}, GA über die Mess-ID). */
        preg_match_all('/\sdata-service="([a-z_]+)"/', $filtered, $marked);
        $found = array_values(array_intersect(array_unique([...$detected, ...$marked[1]]), Services::keys()));

        if ($found !== [] && $response->isSuccessful()) {
            try {
                app(Detected::class)->record($found, $request->url());
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($filtered !== $content) {
            $response->setContent($filtered);
            $response->headers->remove('Content-Length');
        }

        return $response;
    }
}
