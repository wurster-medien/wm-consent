<?php

namespace WursterMedien\Consent\Http\Controllers;

use Composer\InstalledVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Addon;
use Statamic\Statamic;
use Throwable;
use WursterMedien\Consent\AgencyStyles;

/**
 * Versionen der Seite für den Kundenbereich (Simon, 22.10.2026: genaue Statamic-Version mit offenen Updates und
 * Sicherheitsupdates in der Prüfung der Websites). Nur POST mit Signatur: X-WM-Timestamp (Unix-Zeit, höchstens
 * WINDOW Sekunden alt) und X-WM-Signature = HMAC-SHA256 über "wm-consent-status:{timestamp}" mit dem Schlüssel
 * `wm-consent.status_key`, ohne Angabe dem Lizenzschlüssel von Statamic (kennt der Kundenbereich aus unserem Konto bei
 * statamic.com). Ohne Schlüssel oder mit falscher Signatur 404, damit niemand sonst sieht, welche Seite angreifbar ist.
 *
 * Außerhalb von statamic.web und web (siehe routes/web.php): kein statischer Cache, keine Sitzung, keine Cookies.
 */
class StatusController
{
    public const WINDOW = 300;

    public function __invoke(Request $request, AgencyStyles $styles): JsonResponse
    {
        $key = self::key();

        abort_if($key === null || ! self::verified($request, $key), 404);

        return response()->json([
            'statamic' => Statamic::version(),
            'edition' => Statamic::pro() ? 'pro' : 'solo',
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'wm_consent' => self::packageVersion('wurster-medien/wm-consent'),
            'addons' => Addon::all()
                ->mapWithKeys(fn ($addon): array => [(string) $addon->package() => $addon->version() !== null ? (string) $addon->version() : null])
                ->sortKeys()
                ->all(),
            'styles' => $styles->status(),
        ], 200, [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
            'X-Statamic-Uncacheable' => 'true',
        ]);
    }

    public static function key(): ?string
    {
        $key = config('wm-consent.status_key') ?: config('statamic.system.license_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public static function signature(string $key, int $timestamp): string
    {
        return hash_hmac('sha256', 'wm-consent-status:'.$timestamp, $key);
    }

    private static function verified(Request $request, string $key): bool
    {
        $timestamp = (string) $request->header('X-WM-Timestamp', '');
        $signature = (string) $request->header('X-WM-Signature', '');

        if (preg_match('/^\d{9,11}$/', $timestamp) !== 1 || abs(time() - (int) $timestamp) > self::WINDOW) {
            return false;
        }

        return hash_equals(self::signature($key, (int) $timestamp), strtolower($signature));
    }

    private static function packageVersion(string $package): ?string
    {
        try {
            return InstalledVersions::getPrettyVersion($package);
        } catch (Throwable) {
            return null;
        }
    }
}
