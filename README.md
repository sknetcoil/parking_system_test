# Smart Parking Application

Full-stack implementation of the assignment with:
- JWT auth and pre-seeded users
- transactional booking with race-condition handling
- background stale reservation release
- real-time updates via WebSocket
- React parking-grid embedded into an existing Vanilla JS app

## Tech Stack
- Backend: PHP 8.3 (FPM), Ratchet WebSocket, ReactPHP loop
- Database: PostgreSQL 15
- Frontend: Vanilla JS app shell + embedded React 18 (Vite)
- Infra: Docker Compose (db, php, nginx, websocket, cronworker)

## Prerequisites
- Docker + Docker Compose
- Node.js 18+ and npm (for frontend dev server)

## Run

1. Copy environment files:
```bash
cp .env.example .env
cp frontend/.env.example frontend/.env
```

2. Start backend stack:
```bash
docker compose up --build
```

3. Start frontend (in another terminal):
```bash
cd frontend
npm install
npm run dev
```

4. Open the frontend URL from Vite (usually `http://localhost:5173`).

## Seed Users
- `driver1@parking.com` / `password123`
- `driver2@parking.com` / `password123`

## API
- `POST /login`
- `GET /spots`
- `GET /time-slots`
- `POST /reservations` with `{ spot_id, slot_id, date }`
  > Note: The assignment spec defines `{ spot_id, start_time, end_time }`. We accept `{ spot_id, slot_id, date }` instead — the backend resolves the slot's time window from the `time_slots` table and constructs the timestamps internally. This enforces that only valid, configured time windows can be booked, rather than accepting arbitrary timestamps from the client.
- `PUT /reservations/{id}/complete`
- `GET /me`

## Migrations

File-based SQL migrations are under:
- `backend/migrations/001_schema.sql`
- `backend/migrations/002_seed_data.sql`
- `backend/migrations/003_cleanup_legacy_spots.sql`
- `backend/migrations/004_time_slots.sql`

They are executed by:
- `backend/scripts/migrate.php`

Execution model:
- `composer install` runs at image build time (`docker compose build`).
- On `docker compose up`, only the `php` container runs `migrate.php` before starting php-fpm.
- `websocket` and `cronworker` wait for the `db` health check before starting — no migrations on their side.
- Applied migrations are tracked in `schema_migrations`.

## Architecture Decisions

### Concurrency / race-condition handling
- Booking is handled in a DB transaction.
- The target spot is locked via `SELECT ... FOR UPDATE`.
- Overlapping active reservations are checked with:
  - `status = 'Booked'`
  - `(start_time < requested_end) AND (end_time > requested_start)`
  - `FOR UPDATE`
- If overlap exists: HTTP `409` with user-friendly message.

### Locking strategy: SQL `FOR UPDATE` vs Redis

Two approaches were considered for preventing double-booking:

**Option A — Redis soft-lock**
- On booking attempt, acquire a Redis key (`SET spot:{id}:{slot} NX EX 10`) before writing to DB.
- Pros: sub-millisecond lock acquisition, enables "hold for N seconds" UX patterns.
- Cons:
  - Introduces a two-store consistency problem — Redis lock and DB row can fall out of sync (Redis crash, TTL mismatch, failed DB write after successful lock).
  - Requires a Redis container, client library, and cache-invalidation logic on every code path that touches reservations.
  - TTL-based expiry is probabilistic, not transactional — a slot could appear "held" in Redis while the DB has no record of it, or vice versa.

**Option B — SQL `SELECT ... FOR UPDATE` (chosen)**
- Lock the target `parking_spots` row and any overlapping `reservations` rows within a single DB transaction.
- Pros:
  - Single source of truth — the DB is the only authority, no sync required.
  - Atomicity is guaranteed by the transaction; if the INSERT fails for any reason, the lock is released automatically on rollback.
  - PostgreSQL handles deadlock detection natively.
  - No additional infrastructure or library.
- Cons:
  - Holds a DB connection for the duration of the lock (milliseconds in practice).
  - Does not support "soft reservation" UX (e.g. "spot held for 60 seconds while you decide").

**Decision:** For a parking booking system with discrete time-slot granularity and low concurrent write volume, `FOR UPDATE` is the right trade-off. The complexity and failure surface of a two-store approach outweigh any throughput gains Redis would provide at this scale. If the product ever needs a "hold" UX, Redis can be layered on top of the existing SQL guarantee rather than replacing it.

### REST vs WebSocket separation
- REST handles authoritative state changes (book/complete/list).
- WebSocket handles collaboration signals.
- A lightweight internal TCP channel (`websocket:8082`) receives push events from REST/worker and broadcasts to connected WS clients (`:8081`).

### Background processing
- `cronworker` runs every 60 seconds.
- It marks expired active reservations as `Completed`.
- It logs auto-release actions and publishes `slot_updated` events to WebSocket clients.

### Frontend integration (legacy + React)
- The legacy Vanilla router/auth/page shell remains intact.
- React mounts only inside `#parking-slots-view`.
- Date selection is controlled by Vanilla and passed to React using a custom browser event (`parking-date-change`).

## Assumptions
- Parking capacity is 5 spots (as requested in assignment frontend section).
- Allowed booking slots are configured in DB table `time_slots` and enforced by backend validation.
- Frontend consumes `/spots` as source of truth and uses WebSocket events as refresh triggers.

## Planned vs Actual

Initial estimate:
- DB schema + migrations + seed: 2h
- Auth + JWT: 1.5h
- Reservation concurrency: 2h
- WebSocket + background worker: 2h
- Frontend React embedding + booking grid: 3h
- Docker + README polish: 1.5h
- Total: 12h

Actual:
- DB schema + migrations + seed: 2.5h
- Auth + JWT: 1.5h
- Reservation concurrency: 2h
- WebSocket + background worker: 2.5h
- Frontend React embedding + booking grid: 3.5h
- Docker + README polish: 2h
- Total: 14h

Adjustments made:
- Added dynamic nginx upstream resolution to avoid stale fastcgi upstream IPs after container recreation.
- Reworked startup flow to explicit migration execution instead of runtime auto-install logic in request path.
