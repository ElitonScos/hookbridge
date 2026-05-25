CREATE EXTENSION IF NOT EXISTS "pgcrypto";

CREATE TABLE IF NOT EXISTS webhook_events (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source       VARCHAR(100) NOT NULL,
    event_type   VARCHAR(100) NOT NULL,
    payload      JSONB NOT NULL DEFAULT '{}',
    signature    TEXT,
    status       VARCHAR(50) NOT NULL DEFAULT 'pending',
    error_msg    TEXT,
    category     VARCHAR(100),
    received_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    processed_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_events_status     ON webhook_events (status);
CREATE INDEX IF NOT EXISTS idx_events_source     ON webhook_events (source);
CREATE INDEX IF NOT EXISTS idx_events_received   ON webhook_events (received_at DESC);
