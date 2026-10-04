<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Services\Seo;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function show(Seo $seo, string $slug)
    {
        $page = Page::active()->where('slug', $slug)->firstOrFail();

        return $this->render($seo, $page);
    }

    public function render(Seo $seo, Page $page)
    {
        $seo->title($page->meta_title ?: $page->title)
            ->description($page->meta_description ?: $page->content)
            ->canonical($page->url())
            ->breadcrumbs([
                ['name' => 'Home', 'url' => url('/')],
                ['name' => $page->title, 'url' => $page->url()],
            ]);
        if ($page->noindex) {
            $seo->noindex();
        }

        return view('front.page', compact('page'));
    }

    public function contact(Seo $seo)
    {
        $seo->title('Contact Us')
            ->description('Get in touch with the '.site_name().' editorial team.')
            ->canonical(route('contact'))
            ->breadcrumbs([
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Contact', 'url' => route('contact')],
            ]);

        return view('front.contact');
    }

    public function sendContact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'], // honeypot
        ]);
        unset($data['website']);

        ContactMessage::create($data + ['ip_address' => $request->ip()]);

        return back()->with('status', 'Thanks! Your message has been sent.');
    }
}
