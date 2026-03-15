-- Table for failed jobs

CREATE TABLE IF NOT EXISTS failed_jobs (
    id VARCHAR(64) PRIMARY KEY,
    job VARCHAR(255) NOT NULL,
    payload {JSON_TYPE} NOT NULL,
    original_queue VARCHAR(64) NOT NULL DEFAULT 'default',
    attempts INT NOT NULL DEFAULT 0,
    exception {JSON_TYPE} NULL,
    failed_at {FLOAT_TYPE} NOT NULL,
    created_at {FLOAT_TYPE} NULL
);

-- Index for performance
CREATE INDEX {IF_NOT_EXISTS} idx_failed_jobs_failed_at ON failed_jobs(failed_at);
