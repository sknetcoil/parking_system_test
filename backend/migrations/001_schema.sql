CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS parking_spots (
    id SERIAL PRIMARY KEY,
    spot_number INT UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS reservations (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id),
    spot_id INT NOT NULL REFERENCES parking_spots(id),
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Booked',
    CHECK (status IN ('Booked', 'Completed'))
);

CREATE INDEX IF NOT EXISTS idx_reservations_spot_status_time
    ON reservations (spot_id, status, start_time, end_time);
