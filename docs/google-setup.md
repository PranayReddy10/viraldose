# Connecting Google (Search Console, Indexing API, GA4) to ViralDose

One service-account JSON key gives the admin panel: search performance, live index status per article,
automatic submission of new posts, sitemap submission/status, and GA4 traffic. About 15 minutes.

## 1. Google Cloud project and APIs
1. https://console.cloud.google.com → sign in with the account that owns Search Console / Analytics.
2. Project dropdown → **New project** → name `ViralDose` → Create → make sure it is selected.
3. **APIs & Services → Library** → enable, one by one:
   - Google Search Console API
   - Web Search Indexing API
   - Google Analytics Data API

## 2. Service account and key
4. **IAM & Admin → Service Accounts → Create service account** → name `viraldose-site` → Create and continue
   → skip the optional role screens → Done.
5. Copy the email shown (`viraldose-site@<project>.iam.gserviceaccount.com`).
6. Click the account's email → tab **Keys** → **Add key → Create new key** → type **JSON** → Create.
   A file like `viraldose-123456-ab12cd34ef56.json` downloads; keep it private.
   Direct link to the list: https://console.cloud.google.com/iam-admin/serviceaccounts
7. Admin → **Settings → Google & Indexing** → upload that JSON → Save. It now shows "Connected as …".

> **Not this:** *APIs & Services → Credentials → Create credentials → API key* makes a plain API key
> (the page with "API restrictions" and "Application restrictions"). ViralDose cannot use an API key –
> only the service-account JSON from step 6 works. If that page is open, close it without saving.

## 3. Search Console access
8. https://search.google.com/search-console → select the viraldose.in property (or add a Domain property
   verified via DNS at the host, or a URL-prefix property `https://viraldose.in/` verified with the HTML tag
   pasted into Settings → SEO).
9. **Settings → Users and permissions → Add user** → paste the service-account email → permission **Owner**.
   (Owner is required for the Indexing API and sitemap submission; "Full" is not enough.)
10. Admin → Settings → Google → *Search Console property*: `sc-domain:viraldose.in` for a Domain property,
    `https://viraldose.in/` for URL-prefix → Save.

## 4. Analytics (GA4)
11. https://analytics.google.com → Admin → Property column → **Property access management** → + → Add users
    → paste the service-account email → role **Viewer** → Add.
12. Admin → **Property details** → copy the numeric **Property ID** (e.g. 123456789; not the `G-…` code).
13. Admin → Settings → Google → *GA4 property ID* → paste → Save. The `G-…` measurement ID goes in
    Settings → SEO (visitor tracking).

## 5. Test
14. Admin → **Google Search & Analytics → Test connection** → expect "Search Console OK – properties visible:
    sc-domain:viraldose.in" and "Analytics OK".
15. Sitemaps tab → **Submit sitemaps now**. Open any post → **Inspect URL** for live index status.

## Troubleshooting
| Message | Fix |
|---|---|
| properties visible: none | Step 9 missing, or you added your Gmail instead of the service-account email. |
| Permission denied (indexing) | The account is "Full user"; it must be **Owner**. |
| insufficient permissions for this property (Analytics) | Access was granted on the Account, not the Property, or the ID is a `G-` code. |
| 403 … API has not been used in project | Step 3: enable the API in the same project as the key. |
| Uploaded file is rejected / "not a service account key" | You uploaded or pasted an API key. Create the JSON key under IAM & Admin → Service Accounts → Keys (step 6). |
| "Service account key creation is disabled" | Workspace organisation policy `iam.disableServiceAccountKeyCreation`; an org admin must allow it, or use a personal Gmail project. |
| Quota exceeded (Indexing API) | 200 URLs/day per project; sitemaps + IndexNow still submit everything. |

Note: Google documents the Indexing API for job postings and live videos; news sites use it widely and it often
speeds up crawling, but Google may ignore some notifications. Sitemaps, the news sitemap and IndexNow remain
the official paths and are all submitted automatically.
