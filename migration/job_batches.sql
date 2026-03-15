CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids {JSON_TYPE} NULL,
    options {JSON_TYPE} NULL,
    cancelled_at {FLOAT_TYPE} NULL,
    created_at {FLOAT_TYPE} NOT NULL,
    finished_at {FLOAT_TYPE} NULL
);
