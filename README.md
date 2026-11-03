# Public Records Archive

A small, self-contained PHP archive for **public documents with their sources attached**.
Plain PHP 8.1+ (no Composer, no framework) on the front end, Supabase for the database and
PDF storage, and a separate admin panel for publishing.

The point of the design is provenance: a record cannot be saved without a source and without
a readable document. That constraint lives in the database (`CHECK` constraints), not only in
the PHP, so nothing can slip in through an API call either.

---

## What is inside

```
index.php               Archive listing, search and category filter
document.php            Single record: summary, source block, PDF viewer, replies
about.php               About and editorial policy
right-of-reply.php      Public form for replies, corrections and clarifications
partials/               Public header and footer
assets/                 style.css (site) and admin.css (admin panel)
admin/                  Admin panel: sign in, records, editor, categories, reply queue
app/                    Config, bootstrap, Supabase client, auth, helpers
sql/001_schema.sql      Tables, RLS policies, storage bucket, starter categories
.github/workflows/      php -l syntax check on every push
```

## Features

- Search across titles, summaries and source titles; filter by category; pagination.
- Every record shows a structured source block: type, exact title, publisher, date, original link.
- PDFs are stored in a **private** Supabase Storage bucket and served through short lived
  signed URLs, so drafts never leak.
- Right of reply: the public can submit a reply, the admin can publish or reject it. Published
  replies appear on the record page. Email addresses are never exposed publicly — the public
  reads a view that does not include them.
- Row level security: anonymous visitors can read published records only; all writes go through
  the service role key, which stays on the server.
- CSRF tokens on every form, honeypot on the public form, PDF validated by file signature and
  MIME type, 25 MB upload cap.

---

## 1. Set up Supabase

1. Create a project at [supabase.com](https://supabase.com) (or reuse one).
2. Open **SQL Editor**, paste `sql/001_schema.sql` and run it. It creates:
   - `pra_categories`, `pra_documents`, `pra_admins`, `pra_reply_requests`
   - the `pra_published_replies` view
   - RLS policies and grants
   - the private `pra-documents` storage bucket
   - six starter categories
3. Go to **Authentication → Users → Add user** and create the account you will sign in with
   (email + password, "Auto confirm" on).
4. Add that same email to the admin allow list — in the SQL editor:

   ```sql
   insert into public.pra_admins (email, display_name)
   values ('you@example.com', 'Archive editor')
   on conflict (email) do nothing;
   ```

   Sign in is two step: Supabase Auth checks the password, then the app checks `pra_admins`.
   An account that is not on the list cannot get in, even with valid credentials.

## 2. Configure the app

```bash
cp .env.example .env
```

Fill in from **Project Settings → API**:

| Variable | Where to find it |
| --- | --- |
| `SUPABASE_URL` | Project URL |
| `SUPABASE_ANON_KEY` | `anon` / publishable key |
| `SUPABASE_SERVICE_KEY` | `service_role` secret key — server side only |
| `SUPABASE_BUCKET` | leave as `pra-documents` |

`.env` is git-ignored and blocked by `.htaccess`. Never paste the service key into a browser,
a commit, or a chat message.

## 3. Run it

On a host with PHP 8.1+ (Apache or nginx + PHP-FPM):

```bash
# local check without Apache
php -S localhost:8000
```

Then open <http://localhost:8000/>. Admin panel: <http://localhost:8000/admin/>.

Upload the project so that **this folder is the document root**. `app/` and `sql/` are blocked
by `.htaccess`; on nginx add:

```nginx
location ~ ^/(app|sql)/ { deny all; }
location ~ /\. { deny all; }
```

## 4. Deploying

**GitHub Pages cannot host this.** Pages serves static files only — no PHP, so no admin panel,
no Supabase calls, no login. Use GitHub as the code home (it is), and deploy the PHP to a host:

| Host | Notes |
| --- | --- |
| Render / Railway | Web service from this repo, start command `php -S 0.0.0.0:$PORT`; set the `.env` values as environment variables (the app reads `getenv()` too) |
| Hostinger / InfinityFree / cPanel | Upload the folder to `public_html`, create `.env` in the file manager |
| Any VPS | Apache or nginx + PHP-FPM, document root = this folder |

`.env` values also work as real environment variables, which is what PaaS hosts expect.

## 5. Adding a record

`/admin/ → New record`. The form asks for the title, a neutral summary, the category, and then
the source: type, exact document title, publisher, date, original link. Attach the PDF or point
at an external copy. Save as **draft** first, check the preview, then set **Published**.

A record cannot be saved if it has no source, or if it has neither a PDF nor a document link.
That is enforced by `pra_documents_source_required` and `pra_documents_file_required`.

## Editorial rules this build assumes

- Summaries describe what the document says. No conclusion about any person.
- No private information: addresses, phone numbers, health, minors, family details.
- Anyone named gets a reply route, and their reply is published.
- A withdrawn, superseded or court-restrained document gets unpublished.
- The site states plainly that being named in a public document is not a finding of guilt.

These are in `about.php` as the published policy; keep the code and the policy in step.

## Security notes

- The service role key bypasses RLS. It is used only in `app/Supabase.php`, server side.
- Files are in a private bucket; the public sees signed URLs that expire (default 15 minutes).
- Admin sessions are PHP sessions with `HttpOnly`, `SameSite=Lax`, and `Secure` under HTTPS.
- All state-changing requests are POST + CSRF token.
- Uploads are checked by `%PDF-` signature and MIME type, capped at 25 MB.

## License

MIT for the code. The documents you publish remain the responsibility of whoever publishes them.
