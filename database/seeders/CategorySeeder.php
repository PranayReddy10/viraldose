<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'News', 'color' => '#dc2626', 'description' => 'Breaking news and top headlines from India and around the world.', 'children' => ['India', 'World', 'Politics']],
            ['name' => 'Entertainment', 'color' => '#db2777', 'description' => 'Bollywood, Tollywood, OTT releases, celebrity news and reviews.', 'children' => ['Bollywood', 'Tollywood', 'OTT', 'Television']],
            ['name' => 'Sports', 'color' => '#16a34a', 'description' => 'Cricket, football, IPL and all the latest sports updates.', 'children' => ['Cricket', 'Football']],
            ['name' => 'Technology', 'color' => '#2563eb', 'description' => 'Gadgets, smartphones, apps, AI and tech news.', 'children' => ['Mobiles', 'Apps', 'AI']],
            ['name' => 'Business', 'color' => '#ca8a04', 'description' => 'Markets, startups, economy and personal finance.', 'children' => []],
            ['name' => 'Lifestyle', 'color' => '#9333ea', 'description' => 'Health, food, travel, fashion and relationships.', 'children' => ['Health', 'Travel', 'Food']],
            ['name' => 'Viral', 'color' => '#ea580c', 'description' => 'Trending stories, viral videos and social media buzz.', 'children' => []],
            ['name' => 'Auto', 'color' => '#0d9488', 'description' => 'Cars, bikes, EVs and launches.', 'children' => []],
        ];

        foreach ($categories as $i => $data) {
            $children = $data['children'];
            unset($data['children']);
            $parent = Category::firstOrCreate(['slug' => Str::slug($data['name'])], $data + ['sort_order' => $i + 1]);
            foreach ($children as $j => $childName) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'color' => $parent->color, 'sort_order' => $j + 1, 'show_on_home' => false],
                );
            }
        }
    }
}
