<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * The content agent's daily plan, set in Admin → Content Agent:
 * - "top news": the biggest stories of the day in any category (saved as featured drafts),
 * - per-category quotas: how many extra articles each category should get per day,
 * - max per run: how many articles one scheduled run may write (the task runs several times a day).
 * Progress counts agent posts created since midnight in the site's display time zone.
 */
class AgentPlan
{
    public static function topNews(): int
    {
        return (int) Setting::get('agent_top_news', 3);
    }

    public static function maxPerRun(): int
    {
        return (int) Setting::get('agent_max_per_run', 6);
    }

    /** @return array<int, int> category id => articles per day */
    public static function quotas(): array
    {
        $raw = Setting::get('agent_category_quotas', '{}');
        $quotas = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return collect($quotas)->mapWithKeys(fn ($n, $id) => [(int) $id => max(0, min(10, (int) $n))])->filter()->all();
    }

    public static function dayStart(): Carbon
    {
        $tz = (string) Setting::get('timezone_display', 'Asia/Kolkata');

        return Carbon::now($tz)->startOfDay()->setTimezone(config('app.timezone'));
    }

    /**
     * @return array{date: string, max_per_run: int, top_news: array{target: int, created_today: int, remaining: int}, categories: array<int, array<string, mixed>>, remaining_total: int}
     */
    public static function progress(): array
    {
        $since = static::dayStart();
        $today = Post::withTrashed()->where('created_via', 'agent')->where('created_at', '>=', $since);

        $topDone = (clone $today)->where('is_featured', true)->count();
        $perCategory = (clone $today)->where('is_featured', false)->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        $quotas = static::quotas();
        $categories = Category::active()->ordered()->whereIn('id', array_keys($quotas))->get(['id', 'name', 'slug'])
            ->map(function (Category $c) use ($quotas, $perCategory) {
                $done = (int) ($perCategory[$c->id] ?? 0);

                return ['slug' => $c->slug, 'name' => $c->name, 'target' => $quotas[$c->id], 'created_today' => $done, 'remaining' => max(0, $quotas[$c->id] - $done)];
            })->values()->all();

        $top = ['target' => static::topNews(), 'created_today' => $topDone, 'remaining' => max(0, static::topNews() - $topDone)];

        return [
            'date' => Carbon::now((string) Setting::get('timezone_display', 'Asia/Kolkata'))->toDateString(),
            'max_per_run' => static::maxPerRun(),
            'top_news' => $top,
            'categories' => $categories,
            'remaining_total' => $top['remaining'] + array_sum(array_column($categories, 'remaining')),
        ];
    }
}
