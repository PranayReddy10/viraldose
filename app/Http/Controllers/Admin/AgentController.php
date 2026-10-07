<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\AgentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin → Content Agent: turn the agent API on/off, pick the author its drafts
 * are credited to, and create / revoke the API token.
 */
class AgentController extends Controller
{
    public function show()
    {
        return view('admin.agent', [
            'enabled' => (bool) setting('agent_enabled', 0),
            'hasToken' => (bool) setting('agent_token_hash'),
            'tokenCreatedAt' => setting('agent_token_created_at'),
            'authorId' => (int) setting('agent_user_id', 0),
            'authors' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'categories' => Category::active()->ordered()->get(['id', 'name', 'slug']),
            'quotas' => AgentPlan::quotas(),
            'topNews' => AgentPlan::topNews(),
            'maxPerRun' => AgentPlan::maxPerRun(),
            'progress' => AgentPlan::progress(),
            'drafts' => Post::where('created_via', 'agent')->with('category')->orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'agent_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        Setting::setMany([
            'agent_enabled' => $request->boolean('agent_enabled') ? 1 : 0,
            'agent_user_id' => (int) ($data['agent_user_id'] ?? 0),
        ]);

        return back()->with('status', 'Content agent settings saved.');
    }

    public function plan(Request $request)
    {
        $data = $request->validate([
            'agent_top_news' => ['required', 'integer', 'min:0', 'max:20'],
            'agent_max_per_run' => ['required', 'integer', 'min:1', 'max:10'],
            'quotas' => ['nullable', 'array'],
            'quotas.*' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        $valid = Category::pluck('id')->all();
        $quotas = collect($data['quotas'] ?? [])->filter(fn ($n, $id) => in_array((int) $id, $valid, true) && (int) $n > 0)
            ->mapWithKeys(fn ($n, $id) => [(int) $id => (int) $n])->all();

        Setting::setMany([
            'agent_top_news' => (int) $data['agent_top_news'],
            'agent_max_per_run' => (int) $data['agent_max_per_run'],
            'agent_category_quotas' => json_encode((object) $quotas),
        ]);

        return back()->with('status', 'Daily plan saved.');
    }

    public function token()
    {
        $token = 'vd_'.Str::random(48);
        Setting::setMany([
            'agent_token_hash' => hash('sha256', $token),
            'agent_token_created_at' => now()->toDateTimeString(),
            'agent_enabled' => 1,
        ]);

        return back()->with('agent_token', $token)->with('status', 'New API token created. Copy it now – it will not be shown again.');
    }

    public function revoke()
    {
        Setting::setMany(['agent_token_hash' => '', 'agent_token_created_at' => '', 'agent_enabled' => 0]);

        return back()->with('status', 'API token revoked. The agent can no longer create posts.');
    }
}
