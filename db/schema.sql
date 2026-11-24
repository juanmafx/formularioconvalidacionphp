CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL UNIQUE COLLATE NOCASE CHECK (length(username) BETWEEN 3 AND 64),
    password_hash TEXT NOT NULL CHECK (password_hash LIKE '$argon2id$%'),
    totp_secret_encrypted TEXT NOT NULL,
    totp_last_step INTEGER NOT NULL DEFAULT 0,
    failed_attempts INTEGER NOT NULL DEFAULT 0 CHECK (failed_attempts >= 0),
    locked_until INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
) STRICT;

CREATE TABLE IF NOT EXISTS admin_recovery_codes (
    id INTEGER PRIMARY KEY,
    admin_user_id INTEGER NOT NULL REFERENCES admin_users (id) ON DELETE CASCADE,
    code_hash TEXT NOT NULL CHECK (length(code_hash) = 64),
    used_at INTEGER,
    UNIQUE (admin_user_id, code_hash)
) STRICT;

CREATE TABLE IF NOT EXISTS submissions (
    id TEXT PRIMARY KEY CHECK (length(id) = 32),
    payload_encrypted TEXT NOT NULL,
    key_id TEXT NOT NULL,
    notification_status TEXT NOT NULL DEFAULT 'pending'
        CHECK (notification_status IN ('pending', 'sent', 'failed', 'disabled')),
    created_at INTEGER NOT NULL
) STRICT, WITHOUT ROWID;

CREATE INDEX IF NOT EXISTS idx_submissions_created_at ON submissions (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_submissions_key_id ON submissions (key_id);

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    bucket_key TEXT PRIMARY KEY CHECK (length(bucket_key) = 64),
    hits INTEGER NOT NULL CHECK (hits >= 0),
    window_started_at INTEGER NOT NULL
) STRICT, WITHOUT ROWID;

CREATE INDEX IF NOT EXISTS idx_rate_limit_window ON rate_limit_buckets (window_started_at);

CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY,
    admin_user_id INTEGER REFERENCES admin_users (id) ON DELETE SET NULL,
    event_type TEXT NOT NULL CHECK (event_type IN (
        'login_password_failed',
        'login_second_factor_failed',
        'login_succeeded',
        'recovery_code_used',
        'logout',
        'submission_viewed'
    )),
    subject_id TEXT,
    client_fingerprint TEXT NOT NULL CHECK (length(client_fingerprint) = 64),
    created_at INTEGER NOT NULL
) STRICT;

CREATE INDEX IF NOT EXISTS idx_audit_events_created_at ON audit_events (created_at);
