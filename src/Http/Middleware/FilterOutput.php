<?php

namespace WursterMedien\Consent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WursterMedien\Consent\Core\OutputFilter;
use WursterMedien\Consent\Settings;

/**
 * Blockt bekannte Tracker und Einbettungen im ausgelieferten HTML (OutputFilter), nur für HTML-Antworten der Seite
 * (Gruppe statamic.web, nicht im Control Panel).
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

        $filtered = OutputFilter::filter($content);

        if ($filtered !== $content) {
            $response->setContent($filtered);
            $response->headers->remove('Content-Length');
        }

        return $response;
    }
}
