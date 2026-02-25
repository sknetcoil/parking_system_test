import React, { useEffect, useRef, useState } from 'react';
import SlotGrid from './SlotGrid';
import AuthService from '../services/AuthService';

export default function ParkingApp({ initialDate }) {
    const [selectedDate, setSelectedDate] = useState(initialDate);
    const [spots, setSpots] = useState([]);
    const [timeSlots, setTimeSlots] = useState([]);
    const [loading, setLoading] = useState(true);
    const spotsRequestSeq = useRef(0);

    const fetchSpots = async () => {
        const requestId = ++spotsRequestSeq.current;
        try {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/spots`, { cache: 'no-store' });
            const data = await response.json();
            // Keep only the latest response to avoid WS race flicker/stale state.
            if (requestId === spotsRequestSeq.current) {
                setSpots(data);
            }
        } catch (err) {
            console.error('Failed to fetch spots', err);
        }
    };

    const fetchTimeSlots = async () => {
        try {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/time-slots`, { cache: 'no-store' });
            const data = await response.json();
            setTimeSlots(data.map(slot => ({
                id: slot.id,
                label: slot.label,
                start: slot.start_time,
                end: slot.end_time
            })));
        } catch (err) {
            console.error('Failed to fetch time slots', err);
        }
    };

    useEffect(() => {
        Promise.all([fetchSpots(), fetchTimeSlots()]).finally(() => setLoading(false));

        const handleDateChange = (e) => setSelectedDate(e.detail);
        window.addEventListener('parking-date-change', handleDateChange);

        const wsUrl = import.meta.env.VITE_WS_URL;
        let ws;
        let reconnectTimer;
        let destroyed = false;

        const connectWebSocket = () => {
            ws = new WebSocket(wsUrl);

            ws.onmessage = (event) => {
                try {
                    const payload = JSON.parse(event.data);
                    if (payload.event === 'slot_updated') {
                        fetchSpots();
                    }
                } catch (err) { }
            };

            ws.onerror = () => {
                ws.close();
            };

            ws.onclose = () => {
                if (!destroyed) {
                    reconnectTimer = setTimeout(connectWebSocket, 2000);
                }
            };
        };

        connectWebSocket();

        // Polling fallback: refetch spots every 30s in case a WebSocket push was missed
        const pollTimer = setInterval(fetchSpots, 30000);

        return () => {
            destroyed = true;
            if (reconnectTimer) clearTimeout(reconnectTimer);
            clearInterval(pollTimer);
            window.removeEventListener('parking-date-change', handleDateChange);
            if (ws) ws.close();
        };
    }, []);

    const handleBookSlot = async (spotId, slot) => {
        try {
            const token = AuthService.getCurrentToken();
            if (!token) {
                AuthService.logout();
                window.location.href = '/login';
                return;
            }

            const res = await fetch(`${import.meta.env.VITE_API_URL}/reservations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    spot_id: spotId,
                    slot_id: slot.id,
                    date: selectedDate
                })
            });

            if (res.status === 401) {
                AuthService.logout();
                alert('Session expired. Please sign in again.');
                window.location.href = '/login';
                return;
            }

            if (!res.ok) {
                const err = await res.json();
                alert(err.error || 'Failed to book slot');
            } else {
                fetchSpots(); // optimistic update or exact sync with API
            }
        } catch (err) {
            alert('Network error when booking slot.');
        }
    };

    if (loading) return <div>Loading grid...</div>;

    return (
        <div className="parking-app">
            <h2>Availability for {selectedDate}</h2>
            <SlotGrid
                spots={spots}
                selectedDate={selectedDate}
                timeSlots={timeSlots}
                onBookSlot={handleBookSlot}
            />
        </div>
    );
}
