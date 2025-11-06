# Property Enrichment Command Guide

## Command: `properties:enrich-all`

This command enriches all properties with data from external APIs (ATTOM). It provides flexible options to control which properties are enriched and how the enrichment is processed.

## Usage Examples

### 1. Enrich Only Un-Enriched Properties (Recommended for First Run)
```bash
php artisan properties:enrich-all --un-enriched
```
- Only processes properties that have never been enriched (`enriched_at` is NULL)
- Queues jobs for async processing (recommended for large datasets)

### 2. Refresh Properties Enriched More Than 30 Days Ago (Default)
```bash
php artisan properties:enrich-all
```
- Processes properties that:
  - Have never been enriched, OR
  - Were enriched more than 30 days ago
- Uses cached data (if available)

### 3. Refresh Properties Enriched More Than X Days Ago
```bash
php artisan properties:enrich-all --days=60
```
- Only processes properties enriched more than 60 days ago
- Adjust the number of days as needed

### 4. Force Fresh Data (Bypass Cache)
```bash
php artisan properties:enrich-all --fresh
```
- Forces fresh API calls, bypassing cache
- Useful when you know data has changed
- More expensive (more API calls)

### 5. Run Synchronously (For Testing/Small Datasets)
```bash
php artisan properties:enrich-all --sync --un-enriched
```
- Processes properties immediately (not queued)
- Use for testing or small datasets (< 10 properties)
- Slower but shows immediate results

### 6. Limit Number of Properties
```bash
php artisan properties:enrich-all --un-enriched --limit=10
```
- Only processes first 10 matching properties
- Useful for testing or gradual processing

### 7. Combine Options
```bash
# Refresh properties enriched more than 60 days ago, force fresh data, sync mode
php artisan properties:enrich-all --days=60 --fresh --sync

# Enrich only un-enriched properties, limit to 50, force fresh
php artisan properties:enrich-all --un-enriched --limit=50 --fresh
```

## Options Summary

| Option | Description | Default |
|--------|-------------|---------|
| `--fresh` | Force fresh data (bypass cache) | Uses cache |
| `--days=X` | Only enrich properties enriched > X days ago | 30 days |
| `--un-enriched` | Only enrich never-enriched properties | All matching |
| `--sync` | Run synchronously (not queued) | Queued |
| `--limit=X` | Limit number of properties | No limit |
| `--no-interaction` | Skip confirmation prompts | Asks for confirmation |

## Typical Workflows

### Initial Setup (First Time)
```bash
# Enrich all properties that haven't been enriched yet
php artisan properties:enrich-all --un-enriched
```

### Daily/Weekly Refresh
```bash
# Refresh properties enriched more than 7 days ago
php artisan properties:enrich-all --days=7
```

### Monthly Deep Refresh
```bash
# Force fresh data for all properties enriched more than 30 days ago
php artisan properties:enrich-all --days=30 --fresh
```

### Scheduled Task (Cron)
Add to `app/Console/Kernel.php`:
```php
$schedule->command('properties:enrich-all --days=30')
    ->daily()
    ->at('02:00'); // Run at 2 AM daily
```

## Processing Modes

### Async Mode (Default - Recommended)
- Properties are queued for background processing
- Non-blocking, fast execution
- Requires queue worker: `php artisan queue:work`
- Best for large datasets

### Sync Mode (`--sync`)
- Properties are processed immediately
- Shows progress bar and results
- Slower but immediate feedback
- Best for testing or small datasets

## Monitoring

### Check Enrichment Status
```bash
# View properties in database
php artisan tinker
>>> \App\Models\Property::whereNull('enriched_at')->count(); // Un-enriched
>>> \App\Models\Property::where('enriched_at', '<', now()->subDays(30))->count(); // Stale
```

### Check Queue Jobs
```bash
# View queued jobs
php artisan queue:work --verbose

# View failed jobs
php artisan queue:failed
```

### Check API Logs
```bash
# View recent API calls
php artisan tinker
>>> \App\Models\ApiLog::latest()->take(10)->get(['service', 'endpoint', 'success', 'created_at']);
```

## Troubleshooting

### Command Not Found
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear
```

### Jobs Not Processing
```bash
# Make sure queue worker is running
php artisan queue:work

# Or use supervisor/systemd for production
```

### Too Many API Calls
- Remove `--fresh` flag to use cache
- Use `--limit` to process in batches
- Increase `--days` value to reduce frequency

## Best Practices

1. **First Run**: Use `--un-enriched` to enrich all properties
2. **Regular Updates**: Schedule daily with `--days=30`
3. **Force Refresh**: Only use `--fresh` when necessary (costs more)
4. **Large Datasets**: Always use async mode (default)
5. **Testing**: Use `--sync --limit=5` for quick testing

## Example Output

```
Starting property enrichment process...

Mode: Enriching only un-enriched properties
Found 25 property/properties to enrich.

+-------------------+-------+
| Status            | Count |
+-------------------+-------+
| Total Properties  | 50    |
| Already Enriched  | 25    |
| Never Enriched    | 25    |
| To Process        | 25    |
+-------------------+-------+

Do you want to proceed? (Properties will be queued for async processing) (yes/no) [no]:
> yes

Processing: 25/25 [████████████████████████████] 100%

Enrichment process completed!

+--------------------------+-------+
| Status                   | Count |
+--------------------------+-------+
| Queued for Processing    | 25    |
| Errors                   | 0     |
+--------------------------+-------+

Note: Properties are queued. Run "php artisan queue:work" to process them.
```

