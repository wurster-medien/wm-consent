<?php

namespace WursterMedien\Consent\Tests\Core;

use PHPUnit\Framework\TestCase;
use WursterMedien\Consent\Core\HeadTag;

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
        $this->assertSame(
            ['background' => '#1e293b', 'muted' => '#334155', 'mutedText' => '#94a3b8', 'border' => '#334155'],
            HeadTag::config(['colors' => ['dark' => ['background' => '#1e293b', 'muted' => '#334155', 'muted_text' => '#94a3b8', 'border' => '#334155']]])['theme']['dark'],
        );
        $this->assertNull($config['privacyUrl']);
        $this->assertSame('/impressum', $config['imprintUrl']);
        $this->assertSame(['youtube'], $config['services']);
        $this->assertSame(365, $config['cookieDays']);
        $this->assertFalse($config['loadOnce']);
        $this->assertTrue($config['blur']);
        $this->assertSame('bottom right', $config['position']);
        $this->assertSame('middle center', HeadTag::config(['position' => 'middle center'])['position']);
        $this->assertFalse(HeadTag::config(['blur' => false])['blur']);
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
}
