-- Table for failed jobs

CREATE TABLE IF NOT EXISTS failed_jobs (
    id VARCHAR(64) PRIMARY KEY,
    job VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    original_queue VARCHAR(64) NOT NULL DEFAULT 'default',
    attempts INT NOT NULL DEFAULT 0,
    exception JSON NULL,
    failed_at DOUBLE NOT NULL,
    created_at DOUBLE NULL
);

-- Index for performance
CREATE INDEX idx_failed_jobs_failed_at ON failed_jobs(failed_at);
