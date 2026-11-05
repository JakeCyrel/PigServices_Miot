create table if not exists public.profiles (
    id uuid primary key references auth.users (id) on delete cascade,
    full_name text not null default '',
    email text not null,
    phone text not null default '',
    address text not null default '',
    role text not null default 'female_owner' check (role in ('admin', 'male_owner', 'female_owner', 'veterinarian')),
    is_verified boolean not null default true,
    created_at timestamptz not null default now()
);

create table if not exists public.services (
    id uuid primary key default gen_random_uuid(),
    owner_id uuid not null references public.profiles (id) on delete cascade,
    service_type text not null default 'breeding' check (service_type in ('breeding', 'veterinary')),
    pig_name text not null,
    breed text not null,
    age_months smallint not null default 0 check (age_months >= 0),
    location text not null,
    description text not null,
    is_active boolean not null default true,
    created_at timestamptz not null default now()
);

create table if not exists public.requests (
    id uuid primary key default gen_random_uuid(),
    reference_no text not null unique default ('PL-' || upper(substr(replace(gen_random_uuid()::text, '-', ''), 1, 8))),
    service_id uuid not null references public.services (id) on delete cascade,
    requester_id uuid not null references public.profiles (id) on delete cascade,
    preferred_date date not null,
    notes text not null default '',
    status text not null default 'Pending' check (status in ('Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled')),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists public.messages (
    id uuid primary key default gen_random_uuid(),
    request_id uuid not null references public.requests (id) on delete cascade,
    sender_id uuid not null references public.profiles (id) on delete cascade,
    recipient_id uuid not null references public.profiles (id) on delete cascade,
    message text not null check (char_length(message) between 1 and 2000),
    read_at timestamptz,
    created_at timestamptz not null default now()
);

create index if not exists services_active_created_idx on public.services (is_active, created_at desc);
create index if not exists requests_requester_created_idx on public.requests (requester_id, created_at desc);
create index if not exists messages_request_created_idx on public.messages (request_id, created_at);
create index if not exists messages_recipient_read_idx on public.messages (recipient_id, read_at);

create or replace function public.current_user_role()
returns text
language sql
stable
security definer
set search_path = public
as $$
    select role from public.profiles where id = (select auth.uid())
$$;

create or replace function public.is_admin()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
    select coalesce(public.current_user_role() = 'admin', false)
$$;

create or replace function public.can_view_profile(target_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
    select
        target_id = (select auth.uid())
        or public.is_admin()
        or exists (
            select 1 from public.services s
            where s.owner_id = target_id and s.is_active
        )
        or exists (
            select 1
            from public.requests r
            join public.services s on s.id = r.service_id
            where (r.requester_id = (select auth.uid()) and s.owner_id = target_id)
               or (s.owner_id = (select auth.uid()) and r.requester_id = target_id)
        )
$$;

create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
    requested_role text := coalesce(new.raw_user_meta_data ->> 'role', 'female_owner');
begin
    if requested_role not in ('male_owner', 'female_owner', 'veterinarian') then
        requested_role := 'female_owner';
    end if;

    insert into public.profiles (id, full_name, email, phone, address, role)
    values (
        new.id,
        coalesce(new.raw_user_meta_data ->> 'full_name', ''),
        coalesce(new.email, ''),
        coalesce(new.raw_user_meta_data ->> 'phone', ''),
        coalesce(new.raw_user_meta_data ->> 'address', ''),
        requested_role
    );
    return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
    after insert on auth.users
    for each row execute function public.handle_new_user();

create or replace function public.set_request_updated_at()
returns trigger
language plpgsql
set search_path = public
as $$
begin
    new.updated_at := now();
    return new;
end;
$$;

drop trigger if exists requests_set_updated_at on public.requests;
create trigger requests_set_updated_at
    before update on public.requests
    for each row execute function public.set_request_updated_at();

alter table public.profiles enable row level security;
alter table public.services enable row level security;
alter table public.requests enable row level security;
alter table public.messages enable row level security;

revoke all on public.profiles, public.services, public.requests, public.messages from anon, authenticated;
grant select on public.profiles to authenticated;
grant update (full_name, phone, address) on public.profiles to authenticated;
grant select, insert, update, delete on public.services to authenticated;
grant select, insert on public.requests to authenticated;
grant update (status, preferred_date, notes) on public.requests to authenticated;
grant select, insert on public.messages to authenticated;
grant update (read_at) on public.messages to authenticated;

drop policy if exists profiles_select_related on public.profiles;
create policy profiles_select_related on public.profiles
    for select to authenticated using (public.can_view_profile(id));
drop policy if exists profiles_update_self on public.profiles;
create policy profiles_update_self on public.profiles
    for update to authenticated using (id = (select auth.uid()))
    with check (id = (select auth.uid()));

drop policy if exists services_select_active on public.services;
create policy services_select_active on public.services
    for select to authenticated using (is_active or owner_id = (select auth.uid()) or public.is_admin());
drop policy if exists services_insert_provider on public.services;
create policy services_insert_provider on public.services
    for insert to authenticated with check (
        owner_id = (select auth.uid())
        and public.current_user_role() in ('male_owner', 'veterinarian')
    );
drop policy if exists services_update_owner_or_admin on public.services;
create policy services_update_owner_or_admin on public.services
    for update to authenticated using (owner_id = (select auth.uid()) or public.is_admin())
    with check (owner_id = (select auth.uid()) or public.is_admin());
drop policy if exists services_delete_owner_or_admin on public.services;
create policy services_delete_owner_or_admin on public.services
    for delete to authenticated using (owner_id = (select auth.uid()) or public.is_admin());

drop policy if exists requests_select_participants on public.requests;
create policy requests_select_participants on public.requests
    for select to authenticated using (
        requester_id = (select auth.uid())
        or exists (select 1 from public.services s where s.id = service_id and s.owner_id = (select auth.uid()))
        or public.is_admin()
    );
drop policy if exists requests_insert_requester on public.requests;
create policy requests_insert_requester on public.requests
    for insert to authenticated with check (
        requester_id = (select auth.uid())
        and public.current_user_role() in ('male_owner', 'female_owner')
        and exists (select 1 from public.services s where s.id = service_id and s.is_active)
    );
drop policy if exists requests_update_participants on public.requests;
create policy requests_update_participants on public.requests
    for update to authenticated using (
        requester_id = (select auth.uid())
        or exists (select 1 from public.services s where s.id = service_id and s.owner_id = (select auth.uid()))
        or public.is_admin()
    ) with check (
        public.is_admin()
        or (requester_id = (select auth.uid()) and status in ('Pending', 'Cancelled'))
        or (status in ('Pending', 'Approved', 'Rejected', 'Completed')
            and exists (select 1 from public.services s where s.id = service_id and s.owner_id = (select auth.uid())))
    );

drop policy if exists messages_select_participants on public.messages;
create policy messages_select_participants on public.messages
    for select to authenticated using (
        exists (
            select 1 from public.requests r
            join public.services s on s.id = r.service_id
            where r.id = request_id
              and (r.requester_id = (select auth.uid()) or s.owner_id = (select auth.uid()) or public.is_admin())
        )
    );
drop policy if exists messages_insert_participant on public.messages;
create policy messages_insert_participant on public.messages
    for insert to authenticated with check (
        sender_id = (select auth.uid())
        and exists (
            select 1 from public.requests r
            join public.services s on s.id = r.service_id
            where r.id = request_id
              and ((r.requester_id = (select auth.uid()) and s.owner_id = recipient_id)
                or (s.owner_id = (select auth.uid()) and r.requester_id = recipient_id))
        )
    );
drop policy if exists messages_update_recipient on public.messages;
create policy messages_update_recipient on public.messages
    for update to authenticated using (recipient_id = (select auth.uid()))
    with check (recipient_id = (select auth.uid()));