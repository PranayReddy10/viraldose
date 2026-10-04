<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_strips_scripts_and_event_handlers(): void
    {
        $out = $this->sanitizer->clean('<p onclick="x()">Hi<script>alert(1)</script></p><style>p{}</style>');
        $this->assertSame('<p>Hi</p>', $out);
    }

    public function test_unwraps_unknown_tags_but_keeps_text(): void
    {
        $this->assertSame('<p>Hello <strong>world</strong></p>', $this->sanitizer->clean('<p>Hello <font color="red"><strong>world</strong></font></p>'));
    }

    public function test_only_whitelisted_iframes_survive(): void
    {
        $yt = '<iframe src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>';
        $this->assertStringContainsString('youtube.com/embed/abc', $this->sanitizer->clean($yt));
        $this->assertSame('', $this->sanitizer->clean('<iframe src="https://evil.example/x"></iframe>'));
    }

    public function test_images_get_lazy_loading_and_alt(): void
    {
        $out = $this->sanitizer->clean('<img src="/storage/a.jpg">');
        $this->assertStringContainsString('loading="lazy"', $out);
        $this->assertStringContainsString('alt=""', $out);
    }

    public function test_handles_unicode_content(): void
    {
        $out = $this->sanitizer->clean('<p>भारत ने जीता फाइनल – ಕನ್ನಡ</p>');
        $this->assertSame('<p>भारत ने जीता फाइनल – ಕನ್ನಡ</p>', $out);
    }
}
