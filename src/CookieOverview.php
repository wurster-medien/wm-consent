<?php

namespace WursterMedien\Consent;

use WursterMedien\Consent\Core\Services;
use WursterMedien\Consent\Tags\WmConsent;

/**
 * Übersicht im Tab „Cookies“ (Feldtyp html, in Statamic 5 und 6 gleich): was der Banner von selbst in die Cookie-Liste
 * schreibt, nach Kategorien. Die Dienste kommen aus dem, was der Filter gefunden hat (Detected), aus „Dienste immer
 * anzeigen“ und der Mess-ID; ihre Cookies aus dist/cp.json (Catalog). Nur zum Lesen, als Tabellen mit der Klasse
 * data-table des Control Panels (Statamic 5 und 6, hell und dunkel).
 */
class CookieOverview
{
    private const MUTED = 'opacity:.65';

    public function __construct(private Settings $settings, private Detected $detected) {}

    public function html(): string
    {
        $groups = array_fill_keys(Services::CATEGORIES, []);
        $groups['necessary'][] = $this->bannerCookies();

        if ($this->settings->get('auto_cookies', true)) {
            $groups['necessary'][] = $this->laravelCookies();
        }

        foreach ($this->services() as $key => $source) {
            $service = Catalog::services()[$key] ?? ['label' => Services::LABELS[$key] ?? $key, 'category' => Services::category($key) ?? 'media', 'provider' => '', 'cookies' => []];
            $groups[$service['category']][] = [
                'title' => $service['label'],
                'meta' => array_filter([e($service['provider']), $source]),
                'cookies' => $service['cookies'],
            ];
        }

        $html = $this->notice();

        foreach ($groups as $category => $entries) {
            if ($entries === []) {
                continue;
            }

            $html .= '<div style="margin:24px 0 8px;font-weight:600">'.e(Catalog::categories()[$category] ?? $category).'</div>'
                .'<table class="data-table" style="table-layout:fixed"><colgroup><col style="width:24%"><col style="width:22%"><col style="width:14%"><col></colgroup>'
                .'<thead><tr><th>Dienst</th><th>Cookie</th><th>Laufzeit</th><th>Zweck</th></tr></thead><tbody>';

            foreach ($entries as $entry) {
                foreach ($entry['cookies'] ?: [['name' => '–', 'duration' => '–', 'description' => '']] as $index => $cookie) {
                    /* Weitere Cookies desselben Dienstes ohne Linie darüber, damit der Dienst als Block steht. */
                    $td = '<td style="vertical-align:top'.($index > 0 ? ';border-top-color:transparent' : '').'"';
                    $html .= '<tr>'.$td.'>'.($index === 0 ? '<strong>'.e($entry['title']).'</strong>'.implode('', array_map(fn (string $meta): string => '<div style="font-size:12px;'.self::MUTED.'">'.$meta.'</div>', $entry['meta'])) : '').'</td>'
                        .$td.'><code style="font-size:12px;word-break:break-word;background:none;padding:0">'.e($cookie['name']).'</code>'.(! empty($cookie['more']) ? ' <span style="'.self::MUTED.'">u. a.</span>' : '').'</td>'
                        .$td.'>'.e($cookie['duration']).'</td>'
                        .$td.'>'.e($cookie['description']).'</td></tr>';
                }
            }

            $html .= '</tbody></table>';
        }

        return '<div>'.$html.'</div>';
    }

    /**
     * Dienste im Banner mit Herkunft (Text, schon maskiert): gefunden, von Hand oder über die Mess-ID.
     *
     * @return array<string, string>
     */
    private function services(): array
    {
        $services = [];

        foreach ($this->detected->all() as $key => $found) {
            $path = (string) (parse_url($found['url'], PHP_URL_PATH) ?: '/');
            $services[$key] = 'gefunden auf <a href="'.e($found['url']).'" target="_blank" rel="noopener">'.e($path).'</a>, zuletzt '.e(date('d.m.Y', (int) strtotime($found['last'])));
        }

        foreach ((array) $this->settings->get('services', []) as $key) {
            $services[$key] ??= 'unter „Dienste immer anzeigen“';
        }

        if (filled($this->settings->get('google_analytics_id'))) {
            $services['google_analytics'] ??= 'über die Mess-ID';
        }

        return array_intersect_key($services, array_flip(Services::keys()));
    }

    /** Hinweis oben: Filter aus, noch nichts gefunden oder woher die Liste kommt. */
    private function notice(): string
    {
        $text = match (true) {
            ! $this->settings->get('filter', true) => 'Der Filter ist aus („Tracker und Einbettungen blockieren“), es wird nichts gefunden. Unten steht nur, was von Hand eingestellt ist.',
            $this->detected->all() === [] => 'Noch keine Dienste gefunden. Die Liste füllt sich, sobald Seiten der Website aufgerufen werden.',
            default => 'Gefunden wird beim Aufruf der Seiten. Seiten, die seit dem Update niemand geöffnet hat, fehlen noch.',
        };

        return '<div style="font-size:13px;'.self::MUTED.'">'.e($text).'</div>';
    }

    /**
     * @return array{title: string, meta: list<string>, cookies: list<array<string, mixed>>}
     */
    private function bannerCookies(): array
    {
        $days = max(1, min(365, (int) $this->settings->get('cookie_days', 182)));
        $cookies = [['name' => 'wm_consent', 'duration' => $days.' Tage', 'description' => 'Speichert die Auswahl zu diesem Banner.']];

        if (array_intersect(array_keys($this->services()), array_keys(Services::FRAMES)) !== []) {
            $cookies[] = ['name' => 'wm_consent_<dienst>', 'duration' => '6 Monate', 'description' => 'Merkt sich „Immer laden“ für Videos und Karten, je Dienst.'];
        }

        return ['title' => 'Cookie-Banner', 'meta' => ['diese Website'], 'cookies' => $cookies];
    }

    /**
     * @return array{title: string, meta: list<string>, cookies: list<array<string, mixed>>}
     */
    private function laravelCookies(): array
    {
        return [
            'title' => 'Session und XSRF',
            'meta' => ['diese Website', 'von selbst eingetragen'],
            'cookies' => array_map(fn (array $cookie): array => ['name' => $cookie['name'], 'duration' => $cookie['duration']['de'], 'description' => $cookie['description']['de']], WmConsent::laravelCookies()),
        ];
    }
}
