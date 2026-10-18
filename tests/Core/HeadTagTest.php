<?php

namespace WursterMedien\Consent\Tests\Core;

use PHPUnit\Framework\TestCase;
use WursterMedien\Consent\Core\HeadTag;
use WursterMedien\Consent\Core\Lang;

class HeadTagTest extends TestCase
{
    public function test_it_outputs_assets_consent_mode_config_and_blocked_analytics(): void
    {
        $html = HeadTag::render(['google_analytics_id' => 'g-abc1234', 'privacy_url' => '/datenschutz'], 'https://kunde.test/wm-consent/', 'v1');

        $this->assertStringContainsString('<link rel="stylesheet" href="https://kunde.test/wm-consent/wm-consent.css?v=v1" data-wm-consent-ignore>', $html);
        $this->assertStringContainsString("gtag('consent','default',{ad_storage:'denied'", $html);
        $this->assertStringContainsString('id="wm-consent-config" data-wm-consent>', $html);
        $this->assertStringContainsString('<script type="text/plain" data-category="analytics" data-service="google_analytics" async src="https://www.googletagmanager.com/gtag/js?id=G-ABC1234"></script>', $html);
        $this->assertStringContainsString('<script src="https://kunde.test/wm-consent/wm-consent.js?v=v1" defer data-wm-consent-ignore></script>', $html);
        $this->assertSame(['google_analytics'], HeadTag::config(['google_analytics_id' => 'G-ABC1234'])['services']);
    }

    public function test_settings_are_checked_before_they_reach_the_page(): void
    {
        $config = HeadTag::config([
            'mode' => 'neon',
            'colors' => ['light' => ['background' => '#fff', 'text' => 'red;}</style><script>', 'highlight' => 'rgb(20, 184, 166)'], 'dark' => ['highlight' => '#2dd4bf']],
            'privacy_url' => 'javascript:alert(1)',
            'imprint_url' => '/impressum',
            'services' => ['youtube', 'erfunden'],
            'cookie_days' => 5000,
        ]);

        $this->assertSame('auto', $config['mode']);
        $this->assertSame(['background' => '#fff', 'highlight' => 'rgb(20, 184, 166)'], $config['theme']['light']);
        $this->assertSame(['highlight' => '#2dd4bf'], $config['theme']['dark']);
        $this->assertSame(['background' => '#1e293b'], HeadTag::config(['colors' => ['dark' => ['background' => '#1e293b', 'muted' => '#334155']]])['theme']['dark']);
        $this->assertNull($config['privacyUrl']);
        $this->assertSame('/impressum', $config['imprintUrl']);
        $this->assertSame(['youtube'], $config['services']);
        $this->assertSame(365, $config['cookieDays']);
        $this->assertFalse($config['loadOnce']);
        $this->assertTrue($config['blur']);
        $this->assertSame('bottom right', $config['position']);
        $this->assertSame('middle center', HeadTag::config(['position' => 'middle center'])['position']);
        $this->assertFalse(HeadTag::config(['blur' => false])['blur']);
        $this->assertSame(8, $config['blurStrength']);
        $this->assertSame(30, HeadTag::config(['blur_strength' => 99])['blurStrength']);
        $this->assertSame(50, $config['overlayOpacity']);
        $this->assertSame(90, HeadTag::config(['overlay_opacity' => 120])['overlayOpacity']);
        $this->assertTrue(HeadTag::config(['load_once' => true])['loadOnce']);
    }

    public function test_own_cookies_are_cleaned_up(): void
    {
        $config = HeadTag::config([
            'services' => 'youtube|vimeo',
            'cookies' => [
                ['name' => 'warenkorb', 'type' => 'local', 'duration' => 'bis zum Löschen', 'description' => 'Auswahl'],
                ['name' => 'warenkorb', 'description' => 'doppelt'],
                ['name' => '', 'description' => 'ohne Namen'],
                ['name' => 'tracker', 'category' => 'erfunden', 'type' => 'datenbank'],
                'kein Eintrag',
            ],
        ]);

        $this->assertSame(['youtube', 'vimeo'], $config['services']);
        $this->assertSame([
            ['name' => 'warenkorb', 'description' => 'Auswahl', 'duration' => 'bis zum Löschen', 'provider' => '', 'category' => 'necessary', 'type' => 'local'],
            ['name' => 'tracker', 'description' => '', 'duration' => '', 'provider' => '', 'category' => 'necessary', 'type' => 'cookie'],
        ], $config['cookies']);
    }

    public function test_texts_cannot_close_the_config_script(): void
    {
        $html = HeadTag::render(['texts' => ['title' => '</script><script>alert(1)</script>']], '/wm-consent');

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function test_each_site_overrides_links_analytics_language_and_texts(): void
    {
        $settings = [
            'privacy_url' => '/datenschutz',
            'google_analytics_id' => 'G-ALLE1234',
            'texts' => ['title' => 'Für alle', 'de' => ['title' => 'Deutsch', 'description' => 'Text'], 'en' => ['title' => 'English']],
            'sites' => [
                'site2_en' => ['lang' => 'en_GB', 'privacy_url' => '/en/privacy', 'google_analytics_id' => 'G-SITE2222', 'imprint_url' => '', 'texts' => ['acceptAll' => 'OK', 'en' => ['title' => 'Site 2']]],
            ],
        ];

        $config = HeadTag::config($settings + ['site' => 'site2_en']);
        $this->assertSame('en', $config['lang']);
        $this->assertSame('/en/privacy', $config['privacyUrl']);
        $this->assertNull($config['imprintUrl']);
        $this->assertSame(['*' => ['title' => 'Für alle', 'acceptAll' => 'OK'], 'de' => ['title' => 'Deutsch', 'description' => 'Text', 'acceptAll' => 'OK'], 'en' => ['title' => 'Site 2', 'acceptAll' => 'OK']], (array) $config['texts']);
        $this->assertStringContainsString('gtag/js?id=G-SITE2222', HeadTag::render($settings + ['site' => 'site2_en'], '/wm-consent'));

        $other = HeadTag::config($settings + ['site' => 'site1_de', 'lang' => 'de-AT']);
        $this->assertSame('de', $other['lang']);
        $this->assertSame('/datenschutz', $other['privacyUrl']);
        $this->assertSame(['*' => ['title' => 'Für alle'], 'de' => ['title' => 'Deutsch', 'description' => 'Text'], 'en' => ['title' => 'English']], (array) $other['texts']);
        $this->assertStringContainsString('gtag/js?id=G-ALLE1234', HeadTag::render($settings + ['site' => 'site1_de'], '/wm-consent'));

        $this->assertNull(HeadTag::config([])['lang']);
        $this->assertStringContainsString('"texts":{}', HeadTag::render([], '/wm-consent'));
    }

    public function test_own_cookies_may_be_described_per_language(): void
    {
        $cookie = HeadTag::config(['cookies' => [['name' => 'warenkorb', 'duration' => ['de' => '1 Tag', 'en' => '1 day', 'xx-evil' => 'x'], 'description' => ['de' => 'Merkt sich den Warenkorb.', 'fr' => 'Mémorise le panier.']]]])['cookies'][0];

        $this->assertSame(['de' => '1 Tag', 'en' => '1 day'], $cookie['duration']);
        $this->assertSame(['de' => 'Merkt sich den Warenkorb.', 'fr' => 'Mémorise le panier.'], $cookie['description']);
    }

    public function test_language_codes_and_php_texts(): void
    {
        $this->assertSame('de', Lang::code('de_DE'));
        $this->assertSame('fr', Lang::code('FR-ch'));
        $this->assertNull(Lang::code('deutsch'));
        $this->assertNull(Lang::code(null));
        $this->assertSame('Cookie settings', Lang::get('link', 'it'));
        $this->assertSame('Cookie-Einstellungen', Lang::get('link', null));
        $this->assertSame(['de' => '90 Minuten', 'en' => '90 minutes', 'fr' => '90 minutes'], Lang::minutes(90));
        $this->assertSame('1 Stunde', Lang::minutes(60)['de']);
        $this->assertSame('Fin de la session', Lang::minutes(0)['fr']);
    }
}
