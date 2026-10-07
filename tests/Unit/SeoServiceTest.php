<?php

namespace Tests\Unit;

use App\Services\Seo;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    public function test_full_title_appends_site_name_and_truncates(): void
    {
        $seo = (new Seo)->title('Short');
        $this->assertStringEndsWith(' - ViralDose', $seo->fullTitle());

        $long = str_repeat('word ', 30);
        $this->assertLessThanOrEqual(72, strlen((new Seo)->title($long)->fullTitle()));

        // A 50–70 character headline is kept whole instead of being cut to fit the site name.
        $headline = 'Chamoli Earthquake: 4.9 Quake Jolts Uttarakhand, Felt in Delhi NCR';
        $this->assertSame($headline, (new Seo)->title($headline)->fullTitle());
        $this->assertStringEndsNotWith(' ', (new Seo)->title($long)->fullTitle());
    }

    public function test_description_is_truncated_to_160(): void
    {
        $seo = (new Seo)->description(str_repeat('a', 300));
        $this->assertSame(160, strlen($seo->resolvedDescription()));
    }

    public function test_breadcrumbs_generate_json_ld(): void
    {
        $seo = (new Seo)->breadcrumbs([
            ['name' => 'Home', 'url' => 'http://localhost'],
            ['name' => 'Sports', 'url' => 'http://localhost/category/sports'],
        ]);
        $this->assertSame('BreadcrumbList', $seo->jsonLd[0]['@type']);
        $this->assertCount(2, $seo->jsonLd[0]['itemListElement']);
    }
}
