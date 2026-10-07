# Connecting Instagram (@viraldose_news) to ViralDose

You need two values for **Admin → Settings → Instagram**:

| Field | Looks like | Where it comes from |
|---|---|---|
| Instagram business account ID | `17841400000000000` (starts with 1784) | step 6 |
| Access token | `EAAG…` (about 200 characters) | step 6 – the **Page** token |

About 20 minutes. Do every step on a computer, logged in to the Facebook account that is an **admin of the Facebook Page**.

## 1. Instagram account type and Page link
1. Instagram app → Profile → ☰ → **Settings and privacy → Account type and tools → Switch to professional account**
   → choose **Business** (or Creator) → category *News & Media Website*.
2. Instagram app → Profile → **Edit profile → Page** (or *Public business information → Page*) → **Connect** → pick the
   ViralDose Facebook Page. If you have no Page yet, create one at facebook.com/pages/create first.
3. Check: facebook.com → your Page → **Settings → Linked accounts → Instagram** shows @viraldose_news.

## 2. Create the Meta app
4. https://developers.facebook.com → **My Apps → Create app** → name it (e.g. `viraldose`).
   When asked for use cases, pick **Manage messaging & content on Instagram** and
   **Manage everything on your Page** (Business type if asked). Create the app.
5. Add the permissions the site needs (Meta only lets the Explorer grant permissions that are on the app):
   - Left menu **Use cases** → *Manage messaging & content on Instagram* → **Customize** →
     open **API setup with Facebook login** (not "with Instagram login" – the site talks to graph.facebook.com)
     → under Permissions click **Add** next to `instagram_basic` and `instagram_content_publish`.
   - **Use cases** → *Manage everything on your Page* → **Customize** → **Add** `pages_show_list`,
     `pages_read_engagement`, `pages_manage_posts` (for the Facebook Page) and `business_management`.
   You can leave the app **Unpublished** (Development mode). App Review and "Publish" are not needed because
   you only post to your own account and you are the app's admin (App roles).

## 3. Get the tokens (Graph API Explorer)
6. Open https://developers.facebook.com/tools/explorer
   1. Right panel **Meta App** → select the app you created in step 4 (e.g. *viraldose*). **Not** your personal
      name or any other app – a token from another app cannot publish.
   2. **User or Page** → *Get User Access Token*.
   3. **Permissions** → remove anything else that is listed (e.g. `whatsapp_business_*`, click ✕) →
      *Add a permission* → add all of:
      `instagram_basic`, `instagram_content_publish`, `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `business_management`.
      If a permission is missing from the list, it is not on the app yet – go back to step 5.
      (If the panel only offers **Configurations**: Facebook Login for Business → Configurations → Create
      configuration → choose *User access token*, the five permissions and your Page + Instagram account →
      then select that configuration in the Explorer.)
   4. Click **Generate Access Token**. A Facebook window opens:
      Continue → **select the ViralDose Page** → **select @viraldose_news** → allow all permissions → Save → Got it.
      The *Access Token* box now holds a short-lived **user** token (expires in ~1 hour).
   5. Make it long-lived: open https://developers.facebook.com/tools/debug/accesstoken → paste the token → **Debug**
      → at the bottom click **Extend Access Token** → enter your Facebook password if asked → copy the new token
      that appears under the button (valid ~60 days).
   6. Back in the Graph API Explorer, **paste the long-lived token into the Access Token box**, set the method to
      **GET** and the query to:

      ```
      me/accounts?fields=name,access_token,instagram_business_account{id,username}
      ```

      Click **Submit**. The answer looks like:

      ```json
      {
        "data": [{
          "name": "ViralDose",
          "access_token": "EAAG...very long...",          <-- Page access token  -> "Access token"
          "instagram_business_account": {
            "id": "17841400000000000",                    <-- -> "Instagram business account ID"
            "username": "viraldose_news"
          },
          "id": "1234567890"                              <-- Facebook Page ID (not needed)
        }]
      }
      ```

      Because it came from a long-lived user token, this **Page token never expires**.
7. Optional check: paste the Page token into the Access Token Debugger → it should say **Type: Page** and
   **Expires: Never**, with `instagram_content_publish` in the scopes.

## 4. Paste into ViralDose
8. Admin → **Settings → Instagram**:
   - *Instagram business account ID* → the `instagram_business_account.id` value (starts with 1784; **not** the Page `id`).
   - *Access token* → the Page `access_token`.
   → **Save** → **Test connection**. Expected: *Instagram connected: @viraldose_news (N followers)*.
9. Open any story → Instagram box → **Post card to Instagram**. The post link appears under the buttons.

## Troubleshooting
| What you see | Cause / fix |
|---|---|
| `"data": []` in step 6 | The Page wasn't ticked in the login window. Explorer → *Get User Access Token* again → in the window click **Edit previous settings / Choose what you allow** → tick the Page and Instagram account. |
| No `instagram_business_account` in the answer | Instagram is still a personal account or not linked to *this* Page (steps 1–3), or the Instagram account wasn't ticked in the login window. |
| Token debugger shows another app name, or `whatsapp_*` scopes | Wrong *Meta App* selected in the Explorer. Pick your viraldose app and redo step 6. |
| Test: `(#10)` / `(#200) … permission` | `instagram_content_publish` or `instagram_basic` missing – regenerate the token with all five permissions (step 6.3), then redo 6.5–6.6. |
| Test: `Error validating access token` / code 190 | Token expired or invalidated (Facebook password changed, app removed, or you pasted the short-lived/user token). Redo 6.2–6.6 and paste the **Page** token. |
| Test: `Unsupported get request … does not exist` | The ID is the Facebook Page ID, not the Instagram business account ID. |
| Post fails: `The media could not be fetched from this URI` (9004) | Instagram can't download the card. Open the card's "Download card" link in a private browser window; it must load over **https** without login. Check `php artisan storage:link` or the DigitalOcean Space is public. |
| Post fails: `The aspect ratio is not supported` | Only from very old cards – click *Regenerate card* in the story's Instagram box. |
| `Application request limit reached` / `(#9) … limit` | Too many API posts in 24 h; wait and try later. |

Security: the token lets anyone post to your account. Paste it only into the admin; it is stored encrypted.
If it leaks, Facebook → Settings → **Business integrations / Apps and websites** → remove the app, then repeat step 6.
