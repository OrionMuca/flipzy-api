# Migration Strategy

## Overview
This project uses a **consolidated migration approach** for cleaner deployments while maintaining backward compatibility.

## Strategy

### Initial Table Creation
- **Initial table creation migrations** include all current fields from the start
- Example: `create_waiting_list_entries_table` includes `phone_number`, `company_name`, and `selected_roles`
- Example: `create_users_table` includes `phone_number` and `company_name`
- This means **fresh deployments** get all fields in one go

### Incremental Migrations
- **Incremental migrations** (like `add_fields_to_*`) are kept for backward compatibility
- These migrations are **idempotent** - they check if columns exist before adding them
- This allows existing databases to migrate incrementally without errors
- Fresh databases can safely run these migrations (they'll detect existing columns and skip)

### Benefits
1. **Fresh deployments**: Get all fields in initial table creation (cleaner, faster)
2. **Existing databases**: Can migrate incrementally without conflicts
3. **No duplication**: Incremental migrations won't fail on fresh databases
4. **Easier maintenance**: Less migration files to manage for new deployments

## Best Practices

### When Adding New Fields
1. **Update the initial table creation migration** to include the new field
2. **Create an incremental migration** that checks if the column exists before adding
3. This ensures both fresh and existing databases work correctly

### Example Pattern
```php
// In incremental migration
public function up(): void
{
    Schema::table('table_name', function (Blueprint $table) {
        if (!Schema::hasColumn('table_name', 'new_column')) {
            $table->string('new_column')->nullable();
        }
    });
}
```

## Migration Order
Migrations run in chronological order (by timestamp). Foreign key constraints are handled in separate migrations that run after both tables exist.

