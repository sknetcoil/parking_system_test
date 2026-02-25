DELETE FROM reservations
WHERE spot_id IN (
    SELECT id FROM parking_spots WHERE spot_number > 5
);

DELETE FROM parking_spots
WHERE spot_number > 5;
