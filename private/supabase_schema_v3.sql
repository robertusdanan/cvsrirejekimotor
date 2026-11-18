-- v3: Move the last two local-SQLite-only tables (login throttle + audit log)
-- into Supabase, so the project no longer depends on any local/SQLite storage.
-- Run this once in the Supabase SQL editor.

CREATE TABLE IF NOT EXISTS login_throttle (
    ip           TEXT PRIMARY KEY,
    attempts     INTEGER NOT NULL DEFAULT 0,
    locked_until TIMESTAMPTZ DEFAULT NULL,
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id              BIGSERIAL PRIMARY KEY,
    admin_username  TEXT NOT NULL,
    action          TEXT NOT NULL,
    detail          TEXT DEFAULT '',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE login_throttle ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_audit_log ENABLE ROW LEVEL SECURITY;

-- Only the service_role key (used server-side by this app) may touch these
-- tables; there is no public/anon policy, so PostgREST rejects anon access.
CREATE POLICY "Service Role All Login Throttle" ON login_throttle FOR ALL USING (true);
CREATE POLICY "Service Role All Admin Audit Log" ON admin_audit_log FOR ALL USING (true);
