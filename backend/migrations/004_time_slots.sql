CREATE TABLE IF NOT EXISTS time_slots (
    id SERIAL PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CHECK (start_time < end_time),
    UNIQUE (start_time, end_time)
);

INSERT INTO time_slots (label, start_time, end_time)
VALUES
    ('08:00-12:00', '08:00:00', '12:00:00'),
    ('12:00-16:00', '12:00:00', '16:00:00'),
    ('16:00-20:00', '16:00:00', '20:00:00')
ON CONFLICT (start_time, end_time) DO NOTHING;
