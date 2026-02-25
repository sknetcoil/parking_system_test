import React from 'react';

export default function SlotGrid({ spots, selectedDate, timeSlots, onBookSlot }) {

    // helper to check if a specific time slot bounds is booked 
    const isSlotBooked = (spot, slot) => {
        const slotStart = new Date(`${selectedDate}T${slot.start}`).getTime();
        const slotEnd = new Date(`${selectedDate}T${slot.end}`).getTime();

        return spot.reservations.some(res => {
            const resStart = new Date(res.start_time.replace(' ', 'T')).getTime();
            const resEnd = new Date(res.end_time.replace(' ', 'T')).getTime();

            // Check for overlap
            return (resStart < slotEnd && resEnd > slotStart);
        });
    };

    return (
        <div className="slot-grid-container" style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {spots.map(spot => (
                <div key={spot.id} className="spot-row" style={{ display: 'flex', alignItems: 'center', gap: '1rem', background: '#f8f9fa', padding: '1rem', borderRadius: '8px' }}>
                    <div className="spot-label" style={{ width: '80px', fontWeight: 'bold' }}>
                        Spot #{spot.spot_number}
                    </div>
                    <div className="slots" style={{ display: 'flex', gap: '0.5rem', flex: 1 }}>
                        {timeSlots.map((slot, idx) => {
                            const booked = isSlotBooked(spot, slot);
                            return (
                                <button
                                    key={idx}
                                    onClick={() => !booked && onBookSlot(spot.id, slot)}
                                    disabled={booked}
                                    style={{
                                        flex: 1,
                                        padding: '0.5rem',
                                        border: 'none',
                                        borderRadius: '4px',
                                        background: booked ? '#dc3545' : '#28a745',
                                        color: 'white',
                                        cursor: booked ? 'not-allowed' : 'pointer',
                                        opacity: booked ? 0.7 : 1,
                                        transition: 'all 0.2s',
                                        fontWeight: 500
                                    }}
                                    title={booked ? 'Booked' : 'Available - Click to book'}
                                >
                                    {slot.label}
                                </button>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
