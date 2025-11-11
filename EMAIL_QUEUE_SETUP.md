# Email Queue Setup

This document explains the email queuing system implemented in the application.

## Overview

All emails in the application are sent via queues to improve performance and user experience. Emails are automatically queued when sent, and processed by queue workers in the background.

## Queue Names

Emails are organized into different queues for better readability and management:

### `emails-auth`
Authentication-related emails:
- **PasswordResetMail** - Password reset requests
- **EmailVerificationMail** - Email verification links

### `emails-waiting-list`
Waiting list related emails:
- **WaitingListWelcomeMail** - Welcome email when joining waiting list
- **PaymentConfirmationMail** - Payment confirmation after subscription purchase
- **AccountCreatedMail** - Account creation notification with credentials
- **WaitingListUpdateMail** - Periodic updates to waiting list users

## Configuration

### Queue Connection

The default queue connection is configured in `.env`:

```env
QUEUE_CONNECTION=database
```

Available options:
- `sync` - Synchronous (for development, processes immediately)
- `database` - Database queue (recommended for most setups)
- `redis` - Redis queue (for high-performance setups)
- `sqs` - Amazon SQS (for AWS deployments)

### Queue Table

If using `database` driver, ensure the jobs table exists:

```bash
php artisan queue:table
php artisan migrate
```

## Running Queue Workers

### Development

```bash
# Process all queues
php artisan queue:work

# Process specific queue
php artisan queue:work --queue=emails-auth
php artisan queue:work --queue=emails-waiting-list

# Process multiple queues (priority order)
php artisan queue:work --queue=emails-auth,emails-waiting-list
```

### Production

For production, use a process manager like Supervisor or systemd to keep workers running:

**Supervisor Configuration Example:**

```ini
[program:flipzy-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### Horizon (Recommended)

If using Laravel Horizon for queue management:

```bash
php artisan horizon
```

Horizon provides a dashboard at `/horizon` for monitoring queues.

## Retry Configuration

All email jobs are configured with:
- **Max Attempts**: 3 tries
- **Backoff**: 60 seconds between retries

This means if an email fails to send, it will retry up to 3 times with a 60-second delay between attempts.

## Monitoring

### Check Queue Status

```bash
# List all jobs in queue
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Failed Jobs

Failed jobs are stored in the `failed_jobs` table. To retry:

```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry {job-id}
```

## Testing

### Sync Mode (Development)

For testing, you can use sync mode to process emails immediately:

```env
QUEUE_CONNECTION=sync
```

### Testing Queued Emails

In tests, you can use Laravel's queue fakes:

```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

// Your code that sends emails

Queue::assertPushed(PasswordResetMail::class);
```

## Best Practices

1. **Always use queues for emails** - Never send emails synchronously in production
2. **Monitor queue workers** - Ensure workers are running and processing jobs
3. **Set up alerts** - Monitor failed jobs and set up alerts for failures
4. **Use Horizon** - For production, consider using Laravel Horizon for better queue management
5. **Separate queues** - Use different queue names for different email types for better organization

## Troubleshooting

### Emails Not Sending

1. Check if queue workers are running:
   ```bash
   ps aux | grep queue:work
   ```

2. Check for failed jobs:
   ```bash
   php artisan queue:failed
   ```

3. Check queue logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Queue Jobs Stuck

1. Restart queue workers:
   ```bash
   php artisan queue:restart
   ```

2. Clear failed jobs:
   ```bash
   php artisan queue:flush
   ```

### High Queue Load

1. Increase number of workers:
   ```bash
   # Run multiple workers
   php artisan queue:work --queue=emails-auth &
   php artisan queue:work --queue=emails-waiting-list &
   ```

2. Use Redis for better performance:
   ```env
   QUEUE_CONNECTION=redis
   ```

## Email Sending

All emails are automatically queued when using `Mail::to()->send()`. The `ShouldQueue` interface ensures emails are queued rather than sent immediately.

Example:
```php
// This will automatically queue the email
Mail::to($user->email)->send(new PasswordResetMail($user, $token, $url));
```

No changes needed in existing code - emails will be queued automatically!

