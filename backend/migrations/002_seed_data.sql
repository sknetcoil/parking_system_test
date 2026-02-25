INSERT INTO users (email, password)
VALUES
    ('driver1@parking.com', '$2y$12$x8HvTcf3xZCdR8T80pZBE.bBHbzYKm2lXvnpMR32wFTjC8JAlFUdS'),
    ('driver2@parking.com', '$2y$12$x8HvTcf3xZCdR8T80pZBE.bBHbzYKm2lXvnpMR32wFTjC8JAlFUdS')
ON CONFLICT (email) DO NOTHING;

INSERT INTO parking_spots (spot_number)
SELECT gs
FROM generate_series(1, 5) AS gs
ON CONFLICT (spot_number) DO NOTHING;
