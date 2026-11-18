-- ============================================================================
-- Statistik kunjungan (schema v4, 2026-11-18)
-- Jalankan sekali di Supabase Dashboard -> SQL Editor -> New query -> Run.
--
-- - Menghitung SEMUA pengunjung, anonim tanpa login.
-- - Dedup per IP per hari (WIB): 1 IP = 1 kunjungan/hari.
-- - IP ASLI TIDAK PERNAH DISIMPAN: hanya hash HMAC-SHA256 dari IP yang
--   dikirim dari server (lihat private/src/StatistikKunjungan.php).
-- - Setiap kunjungan baru = 1x tulis ke Supabase, jadi proyek selalu ada
--   aktivitas dan tidak di-pause oleh Supabase (free tier).
-- ============================================================================

create table if not exists public.statistik_kunjungan (
  tanggal date primary key,
  pengunjung integer not null default 0,
  updated_at timestamptz not null default now()
);

create table if not exists public.statistik_kunjungan_ip (
  tanggal date not null,
  ip_hash text not null,
  created_at timestamptz not null default now(),
  primary key (tanggal, ip_hash)
);

alter table public.statistik_kunjungan enable row level security;
alter table public.statistik_kunjungan_ip enable row level security;
-- Tanpa policy: akses tabel langsung hanya untuk service_role (bypass RLS).
-- Website hanya memakai dua fungsi di bawah ini.

-- Catat 1 kunjungan untuk hash IP ini (bila belum terhitung hari ini),
-- lalu kembalikan jumlah hari ini + total. Atomik: dua request bersamaan
-- dari IP yang sama tetap terhitung 1x.
create or replace function public.catat_kunjungan(p_ip_hash text)
returns table(hari_ini integer, total integer)
language plpgsql
security definer
set search_path = public
as $$
declare
  v_hari_ini integer;
  v_total integer;
  v_baru boolean;
  v_hari date := (now() at time zone 'Asia/Jakarta')::date;
begin
  -- Buang jejak dedup hari-hari lama (hanya hari ini yang dibutuhkan).
  delete from public.statistik_kunjungan_ip where tanggal < v_hari;

  insert into public.statistik_kunjungan_ip (tanggal, ip_hash)
  values (v_hari, p_ip_hash)
  on conflict do nothing;
  v_baru := found;

  if v_baru then
    insert into public.statistik_kunjungan (tanggal, pengunjung)
    values (v_hari, 1)
    on conflict (tanggal) do update
      set pengunjung = public.statistik_kunjungan.pengunjung + 1,
          updated_at = now();
  end if;

  select pengunjung into v_hari_ini
  from public.statistik_kunjungan where tanggal = v_hari;
  v_hari_ini := coalesce(v_hari_ini, 0);

  select coalesce(sum(pengunjung), 0) into v_total
  from public.statistik_kunjungan;

  return query select v_hari_ini, v_total;
end;
$$;

-- Baca angka untuk footer tanpa menulis apa pun.
create or replace function public.baca_statistik()
returns table(hari_ini integer, total integer)
language sql
security definer
set search_path = public
as $$
  select
    coalesce(
      (select pengunjung from public.statistik_kunjungan
       where tanggal = (now() at time zone 'Asia/Jakarta')::date),
      0
    ),
    coalesce((select sum(pengunjung) from public.statistik_kunjungan), 0);
$$;

revoke all on function public.catat_kunjungan(text) from public;
revoke all on function public.baca_statistik() from public;
grant execute on function public.catat_kunjungan(text) to service_role;
grant execute on function public.baca_statistik() to service_role;
