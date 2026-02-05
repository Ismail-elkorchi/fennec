ALTER TABLE jobs ADD COLUMN lease_expires_at TIMESTAMPTZ NULL;
ALTER TABLE jobs ADD COLUMN heartbeat_at TIMESTAMPTZ NULL;

CREATE INDEX jobs_lease_expired_idx ON jobs (lease_expires_at) WHERE status='running';
