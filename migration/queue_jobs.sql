-- Table for normal jobs

CREATE TABLE IF NOT EXISTS jobs (
    id VARCHAR(64) PRIMARY KEY,
    queue VARCHAR(64) NOT NULL DEFAULT 'default',
    job VARCHAR(255) NOT NULL,
    payload {JSON_TYPE} NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    created_at {FLOAT_TYPE} NOT NULL,
    available_at {FLOAT_TYPE} NULL,
    reserved_at {FLOAT_TYPE} NULL,
    failed_at {FLOAT_TYPE} NULL
);

-- Indexes for performance
CREATE INDEX {IF_NOT_EXISTS} idx_jobs_queue ON jobs(queue);
CREATE INDEX {IF_NOT_EXISTS} idx_jobs_available_at ON jobs(available_at);
CREATE INDEX {IF_NOT_EXISTS} idx_jobs_reserved_at ON jobs(reserved_at);
CREATE INDEX {IF_NOT_EXISTS} idx_jobs_failed_at ON jobs(failed_at);
