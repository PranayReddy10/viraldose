<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $site = 'ViralDose';
        $pages = [
            ['title' => 'About Us', 'slug' => 'about-us', 'sort_order' => 1, 'content' => "<p>{$site} is an independent digital news platform delivering breaking news, viral stories and trending updates across news, entertainment, sports, technology, business and lifestyle.</p><h2>Our mission</h2><p>To report fast, accurately and responsibly. Every story is reviewed by an editor before publishing, and corrections are issued transparently.</p><h2>Editorial team</h2><p>Meet the people behind the stories on our author pages. Each writer has a public profile with their beat and contact details.</p>"],
            ['title' => 'Editorial Policy', 'slug' => 'editorial-policy', 'sort_order' => 2, 'content' => "<p>This page explains how {$site} sources, verifies and publishes news.</p><h2>Sourcing</h2><p>We attribute information to primary sources wherever possible and link to them. Claims from social media are verified before publication.</p><h2>Corrections</h2><p>If we get something wrong we fix it quickly and note the update on the article. Send corrections through our contact page.</p><h2>Ownership &amp; funding</h2><p>{$site} is independently owned and funded through advertising. Advertisers have no influence over editorial decisions.</p>"],
            ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'sort_order' => 3, 'content' => "<p>This Privacy Policy explains how {$site} collects, uses and protects information when you use our website.</p><h2>Information we collect</h2><p>We collect information you provide directly (such as comments, newsletter sign-ups and contact messages) and information collected automatically through cookies and analytics tools such as Google Analytics.</p><h2>Advertising</h2><p>We use third-party advertising partners, including Google AdSense, which may use cookies to serve ads based on your prior visits. You can opt out of personalised advertising by visiting Google Ads Settings.</p><h2>Your choices</h2><p>You may unsubscribe from the newsletter at any time using the link in each email, and you can control cookies through your browser settings.</p><h2>Contact</h2><p>Questions about this policy can be sent via our contact page.</p>"],
            ['title' => 'Terms of Use', 'slug' => 'terms-of-use', 'sort_order' => 4, 'content' => "<p>By accessing {$site} you agree to these terms.</p><h2>Content</h2><p>All content is provided for general information. While we strive for accuracy, we make no warranties about completeness or reliability.</p><h2>Intellectual property</h2><p>Articles, images and branding are the property of {$site} or their respective owners and may not be reproduced without permission.</p><h2>User contributions</h2><p>Comments must be respectful and lawful. We may remove content at our discretion.</p>"],
            ['title' => 'Disclaimer', 'slug' => 'disclaimer', 'sort_order' => 5, 'content' => "<p>The information on {$site} is published in good faith for general information purposes only. Any action you take based on the information found on this website is strictly at your own risk.</p><p>External links are provided for convenience; we do not control and are not responsible for third-party content.</p>"],
        ];

        foreach ($pages as $page) {
            Page::firstOrCreate(['slug' => $page['slug']], $page + ['is_active' => true, 'show_in_footer' => true]);
        }
    }
}
