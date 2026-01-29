# Queue Worker & Docker

## Do I have to run `php artisan queue:work`?

Yes — for **broadcasting** (Pusher), **emails**, **enrichment jobs**, and any other queued work to run, a queue worker must be running. You have two options:

1. **`php artisan queue:work`** — simple worker (good for local dev).
2. **`php artisan horizon`** — recommended for production; runs multiple workers, has a dashboard, and is already wired in Docker.

---

## Customizing the queue worker

### Option A: Customize `queue:work` (when not using Horizon)

You can pass options to control which queue, timeouts, retries, etc.:

```bash
# Process only the default queue, 3 retries, 90s timeout, 3s sleep when empty
php artisan queue:work redis --queue=default --tries=3 --timeout=90 --sleep=3

# Process multiple queues (order = priority)
php artisan queue:work redis --queue=default,emails-waiting-list,emails-auth

# Max 100 jobs then restart (helps prevent memory leaks)
php artisan queue:work redis --max-jobs=100
```

Useful options:

| Option | Description |
|--------|-------------|
| `--queue=name` | Queue(s) to process (comma-separated) |
| `--tries=3` | Max attempts per job |
| `--timeout=90` | Max seconds per job (default 60) |
| `--sleep=3` | Seconds to sleep when queue is empty |
| `--max-jobs=100` | Restart worker after N jobs |
| `--max-time=3600` | Restart worker after N seconds |

### Option B: Customize via Horizon (recommended in production)

Horizon is already in the project. Configure it via **environment variables** (no code change):

In **`.env`**:

```env
# Horizon (queue worker)
HORIZON_BALANCE=auto
HORIZON_MAX_PROCESSES=10
HORIZON_MEMORY=128
HORIZON_TRIES=3
HORIZON_TIMEOUT=90
# Optional: restrict to specific queues (comma-separated). Leave empty for default set.
# HORIZON_QUEUES=default,emails-waiting-list,emails-auth,emails-admin
```

- **HORIZON_MAX_PROCESSES** — number of worker processes (increase for more throughput).
- **HORIZON_TIMEOUT** — max seconds per job (broadcast jobs are quick; 60–90 is fine).
- **HORIZON_QUEUES** — leave empty to use the default queues from `config/horizon.php`, or set your own list.

Then run:

```bash
php artisan horizon
```

For more control (e.g. different settings per environment), edit **`config/horizon.php`** (the `defaults` and `environments` sections).

---

## Running the worker for a specific queue only

### Broadcast jobs use the `broadcasts` queue

Message broadcast jobs (Pusher, etc.) are sent to the **`broadcasts`** queue. You can change this in `.env`:

```env
QUEUE_BROADCAST_QUEUE=broadcasts
```

### Option 1: `queue:work` for one queue

```bash
# Only process the broadcasts queue (real-time messages)
php artisan queue:work redis --queue=broadcasts

# Only process the default queue
php artisan queue:work redis --queue=default

# Multiple queues (order = priority): broadcasts first, then default
php artisan queue:work redis --queue=broadcasts,default
```

### Option 2: Horizon – process only specific queues

Set **`HORIZON_QUEUES`** in `.env` to a comma-separated list. Horizon will process **only** those queues:

```env
# Only the broadcast queue (real-time messages)
HORIZON_QUEUES=broadcasts

# Only default and broadcasts
HORIZON_QUEUES=default,broadcasts

# All app queues (default if HORIZON_QUEUES is empty)
# HORIZON_QUEUES=default,broadcasts,emails-waiting-list,emails-auth,emails-admin
```

Leave **`HORIZON_QUEUES`** empty to use the default list (includes `broadcasts`). After changing it, restart Horizon: `php artisan horizon:terminate` (or restart the container in Docker).

---

## Docker: do I need to configure anything?

**No extra Docker config is needed.** The queue worker is already run inside Docker.

### What’s already set up

1. **App container** (`flipzy-backend`) runs:
   - Nginx + PHP-FPM (web)
   - **Horizon** (queue worker), started by **Supervisor** when the container starts.

2. **Supervisor** config in the image:
   - `docker/supervisor/laravel-horizon.conf` — runs `php artisan horizon` as `www-data`.
   - So as soon as the app container is up, Horizon is processing the queue (including broadcast jobs to Pusher).

3. **Environment** in `docker-compose.yml`:
   - `QUEUE_CONNECTION=redis` is passed to the app container, so jobs go to Redis and Horizon processes them.

### What you need to do

1. **Use the same `.env` (or env vars) in Docker** so that inside the container you have:
   - `QUEUE_CONNECTION=redis` (already set in docker-compose).
   - `BROADCAST_CONNECTION=pusher` and your Pusher keys (from your `.env` file, which is mounted into the container).

2. **No new service** — you do **not** need a separate “queue” or “worker” service in `docker-compose.yml`. One app container runs both the web app and Horizon.

3. **Optional:** To tune Horizon in Docker, set the same Horizon vars in `.env` (e.g. `HORIZON_MAX_PROCESSES`, `HORIZON_TIMEOUT`). They are read by Horizon inside the container.

### Summary

| Where you run | What runs the queue |
|---------------|----------------------|
| **Local (no Docker)** | You run `php artisan queue:work` or `php artisan horizon` yourself. |
| **Docker (docker-compose up)** | Horizon runs automatically inside the app container via Supervisor. No extra container or config. |

So: you don’t have to “always run queue:work” yourself when using Docker — Horizon is already running there. When developing locally without Docker, run `php artisan queue:work` or `php artisan horizon` in a separate terminal.
