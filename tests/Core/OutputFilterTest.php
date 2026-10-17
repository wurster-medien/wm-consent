<?php

namespace WursterMedien\Consent\Tests\Core;

use PHPUnit\Framework\TestCase;
use WursterMedien\Consent\Core\HeadTag;
use WursterMedien\Consent\Core\OutputFilter;

class OutputFilterTest extends TestCase
{
    public function test_known_trackers_wait_for_consent_and_their_category(): void
    {
        $html = '<head><script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC1234"></script>'
            ."<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('config','G-ABC1234');</script>"
            ."<script type=\"text/javascript\">!function(f,b,e,v){}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','1');</script>"
            .'<script src="/js/site.js"></script></head>';

        $result = OutputFilter::process($html);

        $this->assertStringContainsString('<script type="text/plain" data-category="analytics" data-service="google_analytics" async src="https://www.googletagmanager.com/gtag/js?id=G-ABC1234"></script>', $result['html']);
        $this->assertStringContainsString("<script type=\"text/plain\" data-category=\"analytics\" data-service=\"google_analytics\">window.dataLayer", $result['html']);
        $this->assertStringContainsString('<script type="text/plain" data-category="marketing" data-service="meta_pixel">!function', $result['html']);
        $this->assertStringContainsString('<script src="/js/site.js"></script>', $result['html']);
        $this->assertSame(['google_analytics', 'meta_pixel'], $result['detected']);
    }

    public function test_marked_ignored_and_data_scripts_stay_as_they_are(): void
    {
        $html = '<script data-wm-consent-ignore>gtag("consent","default",{});</script>'
            .'<script type="text/plain" data-category="analytics">gtag("config","G-X")</script>'
            .'<script type="application/ld+json">{"url":"https://www.google-analytics.com"}</script>';

        $this->assertSame($html, OutputFilter::filter($html));
    }

    public function test_embeds_become_placeholders_and_the_tag_manager_noscript_goes(): void
    {
        $html = '<p>Video:</p><iframe width="560" height="315" src="https://www.youtube.com/embed/abc123?start=10" title="Unser Film" allowfullscreen></iframe>'
            .'<iframe src="https://www.google.com/maps/embed?pb=!1m18&amp;x=1" height="450" class="map"></iframe>'
            .'<iframe src="/eigene-seite.html"></iframe>'
            .'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-AB12"></iframe></noscript><noscript>Bitte JavaScript</noscript>';

        $result = OutputFilter::process($html);

        $this->assertStringContainsString('<div data-service="youtube" data-id="https://www.youtube-nocookie.com/embed/abc123?start=10" data-title="Unser Film" data-autoscale></div>', $result['html']);
        $this->assertStringContainsString('<div data-service="google_maps" data-id="https://www.google.com/maps/embed?pb=!1m18&amp;x=1" class="map" style="height:450px"></div>', $result['html']);
        $this->assertStringContainsString('<iframe src="/eigene-seite.html"></iframe>', $result['html']);
        $this->assertStringNotContainsString('ns.html', $result['html']);
        $this->assertStringContainsString('<noscript>Bitte JavaScript</noscript>', $result['html']);
        $this->assertSame(['youtube', 'google_maps'], $result['detected']);
    }

    public function test_found_services_are_written_into_the_head_config(): void
    {
        $html = HeadTag::render(['services' => ['vimeo']], '/wm-consent', '1')
            .'<iframe src="https://player.vimeo.com/video/1"></iframe>';

        $filtered = OutputFilter::filter($html);

        preg_match('#id="wm-consent-config"[^>]*>(.*?)</script>#s', $filtered, $match);
        $config = json_decode($match[1], true);
        $this->assertSame(['vimeo'], $config['services']);
        $this->assertSame(['vimeo'], $config['detected']);
    }

    public function test_pages_without_scripts_or_frames_are_untouched(): void
    {
        $this->assertSame(['html' => '<p>Hallo</p>', 'detected' => []], OutputFilter::process('<p>Hallo</p>'));
    }
}
