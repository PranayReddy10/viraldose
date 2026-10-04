<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'website' => ['prohibited'],
        ]);

        Subscriber::firstOrCreate(
            ['email' => mb_strtolower($data['email'])],
            ['token' => Str::random(40), 'ip_address' => $request->ip()],
        );

        return back()->with('newsletter_status', 'You are subscribed. Thank you!');
    }

    public function unsubscribe(string $token)
    {
        Subscriber::where('token', $token)->update(['is_active' => false]);

        return redirect('/')->with('status', 'You have been unsubscribed.');
    }
}
