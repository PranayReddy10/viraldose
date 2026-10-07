# Daily content agent

A scheduled Claude task writes 2–3 original articles a day and sends them to the site as **drafts**.
An editor reviews each draft in the admin panel and presses **Publish**. Publishing pings IndexNow and the
article appears in the sitemap automatically.

## One-time setup

1. Deploy the code (hPanel → Git → Deploy), then over SSH:
   `php artisan migrate --force && php artisan optimize:clear && php artisan optimize`
   The migration also switches off the RSS feeds and the Google Indexing API ping (see "Why" below).
2. Admin → **Content Agent** → *Create API token*. Copy the token (it is shown once).
3. Pick the author the drafts are credited to and Save.
4. Give the token to the scheduled task. Revoke it from the same page at any time.

## API

All calls need `Authorization: Bearer <token>`.

| Method | URL | Purpose |
|---|---|---|
| GET | `/api/agent/context` | Categories (slugs), latest 100 headlines + URLs for internal links, drafts awaiting review |
| GET | `/api/agent/posts` | Posts the agent created and their status |
| POST | `/api/agent/posts` | Create a draft |

POST body (JSON):

```json
{
  "title": "50–70 characters",
  "category": "india-news",
  "content": "<h2>…</h2><p>…</p>",
  "excerpt": "optional",
  "tags": ["Tag one", "Tag two"],
  "meta_title": "50–70 characters",
  "meta_description": "150–160 characters",
  "meta_keywords": "focus keyword, other keyword",
  "image_base64": "optional JPG/PNG/WebP, ≤ 5 MB (1200×675 recommended)",
  "image_alt": "describe the image",
  "image_kind": "photo or card (default card)",
  "image_caption": "Photo credit, e.g. Photo: Jane Doe / Wikimedia Commons, CC BY-SA 4.0"
}
```

Rules enforced by the server: always saved as draft; at least 400 words; HTML is sanitised; links to other
websites are turned into plain text; the same title/slug within 30 days returns `409`.

## Why the feeds and the Indexing API ping were turned off

* RSS import copied other sites' articles. Google does not index copies, and a site full of them gets
  crawled less — this is what filled "Crawled / Discovered – currently not indexed".
* Google's Indexing API is only supported for job-posting and livestream pages; for news it has no effect.
  IndexNow (Bing, Yandex) and the sitemap remain on.
* Deleted (trashed) and archived posts now return **410 Gone** so Google drops them faster. A redirect
  added under Admin → Redirects still takes priority.
