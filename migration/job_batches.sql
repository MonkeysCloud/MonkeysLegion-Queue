CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids JSON NULL,
    options JSON NULL,
    cancelled_at DOUBLE NULL,
    created_at DOUBLE NOT NULL,
    finished_at DOUBLE NULL
);
