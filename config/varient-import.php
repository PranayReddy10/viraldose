<?php

/*
|--------------------------------------------------------------------------
| Varient → ViralDose import defaults
|--------------------------------------------------------------------------
| Legacy category ids from the old viraldose.in database mapped to category
| slugs on the new site (created automatically when missing). Override per
| run with --category-map="1:news,6:politics".
*/

return [
    'category_map' => [
        1 => 'india',          // national news
        3 => 'entertainment',
        4 => 'world',
        5 => 'news',
        6 => 'politics',
        7 => 'news',
        8 => 'business',
        9 => 'sports',
        10 => 'cricket',
        12 => 'education',
        13 => 'politics',
        14 => 'politics',
    ],
];
