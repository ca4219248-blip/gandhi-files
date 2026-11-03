-- ---------------------------------------------------------------------------
-- Gandhi Files - schema, row level security and storage setup
-- Run this once in the Supabase SQL editor, or apply it as a migration.
-- Table names are prefixed with pra_ so they never collide with other apps
-- that share the same database.
-- ---------------------------------------------------------------------------

create extension if not exists "pgcrypto";

-- ---------------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------------
create table if not exists public.pra_categories (
    id          uuid primary key default gen_random_uuid(),
    name        text not null unique,
    slug        text not null unique,
    description text,
    created_at  timestamptz not null default now()
);

-- ---------------------------------------------------------------------------
-- Documents: every record must carry a source and an attached file or link
-- ---------------------------------------------------------------------------
create table if not exists public.pra_documents (
    id           uuid primary key default gen_random_uuid(),
    title        text not null check (length(trim(title)) between 4 and 300),
    slug         text not null unique,
    summary      text not null check (length(trim(summary)) between 20 and 2000),
    body         text,
    category_id  uuid references public.pra_categories (id) on delete set null,

    doc_date     date,

    source_type      text not null check (source_type in (
                         'court_order', 'cag_report', 'parliament_record',
                         'government_notification', 'official_report',
                         'news_report', 'filing', 'other')),
    source_title     text not null check (length(trim(source_title)) >= 3),
    source_publisher text,
    source_url       text,
    source_date      date,

    file_path    text,
    file_name    text,
    file_size    bigint,
    file_mime    text check (file_mime is null or file_mime = 'application/pdf'),
    external_url text,

    status       text not null default 'draft' check (status in ('draft', 'published', 'unpublished')),
    published_at timestamptz,
    created_at   timestamptz not null default now(),
    updated_at   timestamptz not null default now(),

    -- a record is only valid if it points at a verifiable source
    constraint pra_documents_source_required check (
        coalesce(
            nullif(trim(source_url), ''),
            nullif(trim(file_path), ''),
            nullif(trim(external_url), '')
        ) is not null
    ),
    -- and if it can actually be read
    constraint pra_documents_file_required check (
        file_path is not null or nullif(trim(external_url), '') is not null
    )
);

create index if not exists pra_documents_status_idx on public.pra_documents (status, published_at desc);
create index if not exists pra_documents_category_idx on public.pra_documents (category_id);
create index if not exists pra_documents_search_idx on public.pra_documents using gin (to_tsvector('english', title || ' ' || summary));

-- ---------------------------------------------------------------------------
-- Admin allow list: an account can sign in only if its email is listed here
-- ---------------------------------------------------------------------------
create table if not exists public.pra_admins (
    email        text primary key,
    display_name text,
    is_active    boolean not null default true,
    created_at   timestamptz not null default now()
);

-- ---------------------------------------------------------------------------
-- Right of reply submissions (public form, admin moderation)
-- ---------------------------------------------------------------------------
create table if not exists public.pra_reply_requests (
    id           uuid primary key default gen_random_uuid(),
    document_id  uuid references public.pra_documents (id) on delete set null,
    name         text not null check (length(trim(name)) between 2 and 120),
    email        text not null check (position('@' in email) > 1),
    organisation text,
    message      text not null check (length(trim(message)) between 20 and 5000),
    status       text not null default 'received' check (status in ('received', 'published', 'rejected')),
    admin_note   text,
    created_at   timestamptz not null default now()
);

create index if not exists pra_reply_requests_status_idx on public.pra_reply_requests (status, created_at desc);

-- ---------------------------------------------------------------------------
-- updated_at / published_at maintenance
-- ---------------------------------------------------------------------------
create or replace function public.pra_touch_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at := now();
    if new.status = 'published' and (old.status is distinct from 'published' or old.published_at is null) then
        new.published_at := now();
    end if;
    if new.status <> 'published' then
        new.published_at := old.published_at;
    end if;
    return new;
end;
$$;

drop trigger if exists pra_documents_touch on public.pra_documents;
create trigger pra_documents_touch
    before update on public.pra_documents
    for each row execute function public.pra_touch_updated_at();

-- ---------------------------------------------------------------------------
-- Row level security
-- ---------------------------------------------------------------------------
alter table public.pra_categories     enable row level security;
alter table public.pra_documents      enable row level security;
alter table public.pra_admins         enable row level security;
alter table public.pra_reply_requests enable row level security;

-- Anonymous visitors: read published records and the category list only.
drop policy if exists pra_documents_public_read on public.pra_documents;
create policy pra_documents_public_read on public.pra_documents
    for select to anon, authenticated
    using (status = 'published');

drop policy if exists pra_categories_public_read on public.pra_categories;
create policy pra_categories_public_read on public.pra_categories
    for select to anon, authenticated
    using (true);

-- Anonymous visitors: may submit a right of reply, nothing else.
drop policy if exists pra_reply_requests_public_insert on public.pra_reply_requests;
create policy pra_reply_requests_public_insert on public.pra_reply_requests
    for insert to anon, authenticated
    with check (status = 'received' and admin_note is null);

-- pra_admins has no policy at all: only the service role key can read it.
-- Writes to documents and reply moderation also go through the service role
-- key, which is used server side only (see app/Supabase.php).

-- ---------------------------------------------------------------------------
-- Published replies, exposed through a view so the public never sees emails
-- ---------------------------------------------------------------------------
create or replace view public.pra_published_replies as
    select id, document_id, name, organisation, message, created_at
    from public.pra_reply_requests
    where status = 'published';

-- ---------------------------------------------------------------------------
-- Grants (Supabase normally sets these defaults; repeated here for clarity)
-- ---------------------------------------------------------------------------
grant usage on schema public to anon, authenticated;
grant select on public.pra_documents, public.pra_categories, public.pra_published_replies to anon, authenticated;
grant insert on public.pra_reply_requests to anon, authenticated;
grant all on public.pra_documents, public.pra_categories, public.pra_admins,
    public.pra_reply_requests to service_role;
grant select on public.pra_published_replies to service_role;

-- ---------------------------------------------------------------------------
-- Private storage bucket for the PDFs
-- ---------------------------------------------------------------------------
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('pra-documents', 'pra-documents', false, 26214400, array['application/pdf'])
on conflict (id) do update
    set public = false,
        file_size_limit = 26214400,
        allowed_mime_types = array['application/pdf'];

-- No storage policies for anon/authenticated: files are served through short
-- lived signed URLs generated server side with the service role key.
drop policy if exists pra_storage_admin_all on storage.objects;
create policy pra_storage_admin_all on storage.objects
    for all to service_role
    using (bucket_id = 'pra-documents')
    with check (bucket_id = 'pra-documents');

-- ---------------------------------------------------------------------------
-- Starter categories
-- ---------------------------------------------------------------------------
insert into public.pra_categories (name, slug, description) values
    ('Court orders',           'court-orders',           'Judgments, orders and filings from courts and tribunals.'),
    ('Audit reports',          'audit-reports',          'CAG and other audit findings tabled in legislatures.'),
    ('Parliament records',     'parliament-records',     'Questions, debates, committee reports and answers.'),
    ('Government notifications','government-notifications','Gazette notifications, circulars and official orders.'),
    ('News reports',           'news-reports',           'Reported journalism, archived with the original link.'),
    ('Other documents',        'other-documents',        'RTI responses, public filings and miscellaneous records.')
on conflict (slug) do nothing;

-- ---------------------------------------------------------------------------
-- First admin: add the email you will sign in with, then create that user in
-- Authentication > Users in the Supabase dashboard.
-- ---------------------------------------------------------------------------
-- insert into public.pra_admins (email, display_name)
-- values ('you@example.com', 'Archive editor')
-- on conflict (email) do nothing;
