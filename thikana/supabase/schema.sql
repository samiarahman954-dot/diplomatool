-- Thikana database. Run this in the Supabase SQL editor, then run districts.sql.
-- The anon key can only call the functions below; the tables themselves are closed.

create table if not exists public.districts (
  id   int primary key,
  name text not null,
  lat  double precision not null,
  lon  double precision not null
);

create table if not exists public.letters (
  id         uuid primary key default gen_random_uuid(),
  to_key     text not null,
  to_addr    jsonb not null,
  from_key   text not null,
  from_addr  jsonb not null,
  pen_name   text not null default '',
  body       text not null,
  stamp      text not null default 'shapla',
  sealed     boolean not null default false,
  seal_hint  text not null default '',
  reply_to   uuid references public.letters(id) on delete set null,
  reports    int not null default 0,
  created_at timestamptz not null default now(),
  deliver_at timestamptz not null
);

create index if not exists letters_to_key on public.letters (to_key, created_at desc);
create index if not exists letters_from_key on public.letters (from_key);
create index if not exists letters_deliver_at on public.letters (deliver_at desc);

create table if not exists public.letter_reports (
  letter_id  uuid not null references public.letters(id) on delete cascade,
  reporter   text not null,
  created_at timestamptz not null default now(),
  primary key (letter_id, reporter)
);

alter table public.districts enable row level security;
alter table public.letters enable row level security;
alter table public.letter_reports enable row level security;

-- Same formula as src/lib/delivery.ts: 5 minutes + 1 minute per km between district centres.
create or replace function public.delivery_minutes(p_from int, p_to int)
returns double precision language sql stable as $$
  select 5 + coalesce((
    select 2 * 6371 * asin(sqrt(
      power(sin(radians(b.lat - a.lat) / 2), 2) +
      cos(radians(a.lat)) * cos(radians(b.lat)) * power(sin(radians(b.lon - a.lon) / 2), 2)
    ))
    from public.districts a, public.districts b
    where a.id = p_from and b.id = p_to
  ), 0);
$$;

-- Mirrors src/lib/safety.ts for open (unsealed) letters.
create or replace function public.body_issue(p_body text)
returns text language plpgsql immutable as $$
declare
  t text := translate(btrim(p_body), '০১২৩৪৫৬৭৮৯', '0123456789');
begin
  if t = '' then return 'empty'; end if;
  if length(t) > 3000 then return 'too_long'; end if;
  if t ~ '(\+?88[\s-]*)?\y01[3-9]([\s-]*[0-9]){8}\y'
     or t ~ '\+[0-9]([\s-]*[0-9]){7,}'
     or t ~ '[0-9]{10,}' then return 'phone'; end if;
  if t ~* '[^\s@]+@[^\s@]+\.[a-z]{2,}' then return 'email'; end if;
  if t ~* '(https?://|www\.|\y[a-z0-9-]+\.(com|net|org|xyz|io|me|bd)\y)' then return 'link'; end if;
  return null;
end $$;

create or replace function public.send_letter(
  p_to_key text, p_to_addr jsonb, p_from_key text, p_from_addr jsonb,
  p_pen_name text, p_body text, p_stamp text, p_sealed boolean, p_seal_hint text, p_reply_to uuid
) returns table (id uuid, deliver_at timestamptz)
language plpgsql security definer set search_path = public as $$
declare
  key_re constant text := '^bd\.[0-9]+\.[0-9]+\.(u[0-9]+|x[^.]+)\.[^.]+$';
  issue text;
begin
  if p_to_key !~ key_re or p_from_key !~ key_re then
    raise exception 'bad_address';
  end if;
  if split_part(p_to_key, '.', 2) <> p_to_addr->>'districtId'
     or split_part(p_from_key, '.', 2) <> p_from_addr->>'districtId' then
    raise exception 'bad_address';
  end if;
  if length(coalesce(p_pen_name, '')) > 40 or length(coalesce(p_seal_hint, '')) > 80 then
    raise exception 'too_long';
  end if;
  if p_sealed then
    -- Ciphertext of a 3000-character letter, base64-encoded.
    if p_body !~ '^seal1\.' or length(p_body) > 20000 then raise exception 'bad_seal'; end if;
  else
    issue := public.body_issue(p_body);
    if issue is not null then raise exception '%', issue; end if;
  end if;

  return query
  insert into public.letters as l
    (to_key, to_addr, from_key, from_addr, pen_name, body, stamp, sealed, seal_hint, reply_to, deliver_at)
  values (
    p_to_key, p_to_addr, p_from_key, p_from_addr, btrim(coalesce(p_pen_name, '')), p_body,
    coalesce(nullif(p_stamp, ''), 'shapla'), p_sealed, btrim(coalesce(p_seal_hint, '')), p_reply_to,
    now() + make_interval(mins => round(public.delivery_minutes(
      split_part(p_from_key, '.', 2)::int, split_part(p_to_key, '.', 2)::int))::int)
  )
  returning l.id, l.deliver_at;
end $$;

-- Letters still travelling are listed without their body.
create or replace function public.get_mailbox(p_key text)
returns table (
  id uuid, to_key text, to_addr jsonb, from_addr jsonb, pen_name text, body text, stamp text,
  sealed boolean, seal_hint text, created_at timestamptz, deliver_at timestamptz, reply_to uuid
)
language sql stable security definer set search_path = public as $$
  select l.id, l.to_key, l.to_addr, l.from_addr, l.pen_name,
         case when l.deliver_at <= now() then l.body end,
         l.stamp, l.sealed, l.seal_hint, l.created_at, l.deliver_at, l.reply_to
  from public.letters l
  where l.to_key = p_key and l.reports < 3
  order by l.created_at desc
  limit 200;
$$;

create or replace function public.recent_letters(p_limit int)
returns table (
  id uuid, to_key text, to_addr jsonb, from_addr jsonb, pen_name text, body text, stamp text,
  sealed boolean, seal_hint text, created_at timestamptz, deliver_at timestamptz, reply_to uuid
)
language sql stable security definer set search_path = public as $$
  select l.id, l.to_key, l.to_addr, l.from_addr, l.pen_name, l.body, l.stamp, l.sealed,
         l.seal_hint, l.created_at, l.deliver_at, l.reply_to
  from public.letters l
  where l.deliver_at <= now() and not l.sealed and l.reports < 3
  order by l.deliver_at desc
  limit least(greatest(p_limit, 1), 50);
$$;

create or replace function public.known_villages(p_union_key text)
returns table (key text, addr jsonb, letters bigint)
language sql stable security definer set search_path = public as $$
  with places as (
    select to_key as key, to_addr as addr from public.letters where reports < 3
    union all
    select from_key, from_addr from public.letters where reports < 3
  )
  select key, (array_agg(addr))[1], count(*)
  from places
  where starts_with(key, p_union_key || '.')
  group by key
  order by count(*) desc
  limit 100;
$$;

create or replace function public.random_mailbox()
returns table (addr jsonb)
language sql volatile security definer set search_path = public as $$
  select to_addr from public.letters
  where deliver_at <= now() and reports < 3
  order by random()
  limit 1;
$$;

create or replace function public.report_letter(p_id uuid, p_reporter text)
returns void language plpgsql security definer set search_path = public as $$
begin
  insert into public.letter_reports (letter_id, reporter)
  values (p_id, left(p_reporter, 64))
  on conflict do nothing;
  update public.letters
  set reports = (select count(*) from public.letter_reports r where r.letter_id = p_id)
  where id = p_id;
end $$;

revoke all on function public.delivery_minutes(int, int) from public, anon, authenticated;
revoke all on function public.body_issue(text) from public, anon, authenticated;
grant execute on function public.send_letter(text, jsonb, text, jsonb, text, text, text, boolean, text, uuid) to anon, authenticated;
grant execute on function public.get_mailbox(text) to anon, authenticated;
grant execute on function public.recent_letters(int) to anon, authenticated;
grant execute on function public.known_villages(text) to anon, authenticated;
grant execute on function public.random_mailbox() to anon, authenticated;
grant execute on function public.report_letter(uuid, text) to anon, authenticated;
