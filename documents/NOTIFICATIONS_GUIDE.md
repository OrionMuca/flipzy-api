# Admin Notifications System Guide

This guide explains how to use the admin notification system to send emails to users from the admin panel.

---

## Overview

The notification system uses Laravel Notifications, which stores notifications in the database and sends emails. This allows for:
- **In-app notifications** - Users can see notifications in their account
- **Email notifications** - Users receive email notifications
- **Future extensions** - Easy to add Firebase push notifications, SMS, etc.

Notifications are stored in the `notifications` table and can be retrieved via API endpoints.

---

## Architecture

### Components

1. **AdminNotification** (`app/Notifications/AdminNotification.php`)
   - Laravel Notification class
   - Handles notification delivery channels
   - Stores in database AND sends email
   - Can be extended to Firebase/SMS

2. **AdminNotificationMail** (`app/Mail/AdminNotificationMail.php`)
   - Mailable class for email notifications
   - Uses the same email template system as other emails
   - Queued for performance

3. **AdminNotificationService** (`app/Services/AdminNotificationService.php`)
   - Business logic for sending notifications
   - Handles different recipient types (all, selected, filtered, single)

4. **AdminNotificationController** (`app/Http/Controllers/Admin/AdminNotificationController.php`)
   - API endpoint for sending notifications
   - Admin-only access

5. **SendAdminNotificationRequest** (`app/Http/Requests/SendAdminNotificationRequest.php`)
   - Request validation
   - Ensures proper data format and authorization

---

## API Endpoint

### Send Notification

**Endpoint:** `POST /api/v1/admin/notifications/send`

**Authentication:** Bearer token with admin role required

**Request Body:**

```json
{
  "subject": "Important Update",
  "message": "We have an important update for you...",
  "action_url": "https://yourapp.com/update",  // Optional
  "action_text": "View Update",  // Optional
  "recipient_type": "all",  // "all" | "selected" | "filtered" | "single"
  "user_ids": ["uuid1", "uuid2"],  // Required if recipient_type is "selected"
  "user_id": "uuid",  // Required if recipient_type is "single"
  "filters": {  // Required if recipient_type is "filtered"
    "role": "investor",  // Optional: "investor" | "wholesaler" | "admin"
    "search": "john"  // Optional: search by name or email
  }
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "total_users": 150,
    "sent": 148,
    "failed": 2
  }
}
```

**Error Response (400):**

```json
{
  "success": false,
  "error": "Validation error message"
}
```

---

## Recipient Types

### 1. All Users (`recipient_type: "all"`)

Sends notification to all verified users.

**Request:**
```json
{
  "subject": "Platform Update",
  "message": "We're excited to announce new features!",
  "recipient_type": "all"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "total_users": 150,
    "sent": 148,
    "failed": 2
  }
}
```

---

### 2. Selected Users (`recipient_type: "selected"`)

Sends notification to specific users by their IDs.

**Request:**
```json
{
  "subject": "Welcome Back",
  "message": "We noticed you haven't been active lately...",
  "recipient_type": "selected",
  "user_ids": [
    "550e8400-e29b-41d4-a716-446655440000",
    "660e8400-e29b-41d4-a716-446655440001"
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "total_requested": 2,
    "total_found": 2,
    "not_found": 0,
    "sent": 2,
    "failed": 0
  }
}
```

---

### 3. Filtered Users (`recipient_type: "filtered"`)

Sends notification to users matching filter criteria.

**Request:**
```json
{
  "subject": "New Investment Opportunities",
  "message": "Check out our latest properties...",
  "recipient_type": "filtered",
  "filters": {
    "role": "investor",
    "search": "john"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "total_users": 45,
    "sent": 44,
    "failed": 1,
    "filters_applied": {
      "role": "investor",
      "search": "john"
    }
  }
}
```

**Available Filters:**
- `role`: Filter by user role (`investor`, `wholesaler`, `admin`)
- `search`: Search by name or email (partial match)

---

### 4. Single User (`recipient_type: "single"`)

Sends notification to a single user.

**Request:**
```json
{
  "subject": "Account Verification Required",
  "message": "Please verify your email address to continue using our platform.",
  "recipient_type": "single",
  "user_id": "550e8400-e29b-41d4-a716-446655440000",
  "action_url": "https://yourapp.com/verify-email",
  "action_text": "Verify Email"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "user_id": "550e8400-e29b-41d4-a716-446655440000",
    "user_email": "user@example.com",
    "sent": true
  }
}
```

---

## Email Template

The notification emails use the same professional template system as other emails:

- **Logo:** Automatically includes `flipzy_logo.jpg`
- **Design:** Modern blue gradient theme
- **Action Button:** Optional, if `action_url` and `action_text` are provided
- **Responsive:** Mobile-friendly design

**Template Location:** `resources/views/emails/admin/notification.blade.php`

---

## Frontend Implementation Example

### Send to All Users

```javascript
async function sendNotificationToAll(subject, message) {
  const response = await fetch('/api/v1/admin/notifications/send', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      subject,
      message,
      recipient_type: 'all',
    }),
  });
  
  return await response.json();
}
```

### Send to Selected Users

```javascript
async function sendNotificationToSelected(userIds, subject, message, actionUrl, actionText) {
  const response = await fetch('/api/v1/admin/notifications/send', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      subject,
      message,
      recipient_type: 'selected',
      user_ids: userIds,
      action_url: actionUrl,
      action_text: actionText,
    }),
  });
  
  return await response.json();
}
```

### Send to Filtered Users

```javascript
async function sendNotificationToFiltered(filters, subject, message) {
  const response = await fetch('/api/v1/admin/notifications/send', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      subject,
      message,
      recipient_type: 'filtered',
      filters: {
        role: filters.role, // 'investor', 'wholesaler', 'admin'
        search: filters.search, // Search term
      },
    }),
  });
  
  return await response.json();
}
```

### Send to Single User

```javascript
async function sendNotificationToSingle(userId, subject, message, actionUrl, actionText) {
  const response = await fetch('/api/v1/admin/notifications/send', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      subject,
      message,
      recipient_type: 'single',
      user_id: userId,
      action_url: actionUrl,
      action_text: actionText,
    }),
  });
  
  return await response.json();
}
```

---

## User Notification Endpoints

Users can retrieve and manage their notifications:

### Get Notifications
**Endpoint:** `GET /api/v1/notifications`

**Query Parameters:**
- `per_page` (optional): Items per page (default: 15, max: 100)
- `unread_only` (optional): Show only unread notifications (default: false)

**Success Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "App\\Notifications\\AdminNotification",
      "subject": "Important Update",
      "message": "We have an important update for you...",
      "action_url": "https://yourapp.com/update",
      "action_text": "View Update",
      "read": false,
      "read_at": null,
      "created_at": "01-15-2025 10:30:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

### Get Unread Count
**Endpoint:** `GET /api/v1/notifications/unread-count`

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "unread_count": 5
  }
}
```

### Mark Notification as Read
**Endpoint:** `PUT /api/v1/notifications/{id}/read`

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

### Mark All as Read
**Endpoint:** `PUT /api/v1/notifications/read-all`

**Success Response (200):**
```json
{
  "success": true,
  "message": "All notifications marked as read",
  "data": {
    "marked_count": 5
  }
}
```

### Delete Notification
**Endpoint:** `DELETE /api/v1/notifications/{id}`

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notification deleted successfully"
}
```

---

## Queue Configuration

Notifications are queued for better performance. Make sure your queue worker is running:

```bash
# Start queue worker
php artisan queue:work

# Or use Horizon
php artisan horizon
```

**Queue Name:** `emails-admin`

**Queue Connection:** Uses default queue connection from config

---

## Error Handling

The system handles errors gracefully:

- **Invalid User IDs:** Returns count of not found users
- **Failed Sends:** Logs errors and continues with other users
- **Validation Errors:** Returns detailed validation messages

**Error Logging:**
- Failed notifications are logged to `storage/logs/laravel.log`
- Includes user ID, email, and error message

---

## Future Extensions

The notification system is designed to be easily extended:

### Database Notifications

✅ **Already Implemented!** Notifications are automatically stored in the database.

The `notifications` table stores:
- Notification ID (UUID)
- Type (notification class name)
- Notifiable (user) reference
- Data (JSON with subject, message, etc.)
- Read status and timestamps

**Migration:** Run `php artisan migrate` to create the table.

### Add Firebase Notifications

1. Install Firebase package
2. Create `FirebaseChannel` class
3. Add to `via()` method:
```php
public function via(object $notifiable): array
{
    return ['mail', 'firebase'];
}
```

### In-App Notifications

✅ **Already Implemented!** Users can retrieve their notifications via API:

- `GET /api/v1/notifications` - List notifications
- `GET /api/v1/notifications/unread-count` - Get unread count
- `PUT /api/v1/notifications/{id}/read` - Mark as read
- `PUT /api/v1/notifications/read-all` - Mark all as read
- `DELETE /api/v1/notifications/{id}` - Delete notification

Notifications are automatically stored in the database when sent.

---

## Security

- **Authorization:** Only admins can send notifications
- **Validation:** All inputs are validated
- **Rate Limiting:** Consider adding rate limiting for bulk sends
- **User Privacy:** Only sends to verified users (email_verified_at is not null)

---

## Best Practices

1. **Test First:** Always test with a single user before sending to all
2. **Use Filters:** Use filtered sends instead of selecting all users when possible
3. **Monitor Queue:** Ensure queue workers are running for bulk sends
4. **Check Logs:** Review logs after bulk sends to identify any issues
5. **Personalize:** Use user's name in messages when possible (already included in greeting)

---

## Example Use Cases

### 1. Platform Update Announcement
```json
{
  "subject": "New Features Available!",
  "message": "We're excited to announce new features including...",
  "recipient_type": "all",
  "action_url": "https://yourapp.com/features",
  "action_text": "Learn More"
}
```

### 2. Role-Specific Announcement
```json
{
  "subject": "New Investment Opportunities",
  "message": "Check out our latest properties available for investment.",
  "recipient_type": "filtered",
  "filters": {
    "role": "investor"
  },
  "action_url": "https://yourapp.com/properties",
  "action_text": "View Properties"
}
```

### 3. User-Specific Notification
```json
{
  "subject": "Account Verification Required",
  "message": "Please verify your email to continue using our platform.",
  "recipient_type": "single",
  "user_id": "user-uuid",
  "action_url": "https://yourapp.com/verify",
  "action_text": "Verify Email"
}
```

---

## Troubleshooting

### Notifications Not Sending

1. **Check Queue Worker:**
   ```bash
   php artisan queue:work
   ```

2. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Check Email Configuration:**
   - Verify `.env` email settings
   - Test email sending: `php artisan tinker` → `Mail::raw('Test', fn($m) => $m->to('test@example.com')->subject('Test'))`

### Validation Errors

- Ensure all required fields are provided
- Check UUID format for user IDs
- Verify recipient_type is one of: `all`, `selected`, `filtered`, `single`

### Performance Issues

- Use queue workers for bulk sends
- Consider chunking very large user lists
- Monitor queue backlog in Horizon

---

## API Documentation

Full API documentation is available via Swagger UI at `/api/documentation`

---

**Last Updated:** 2025-01-15


---

# Implementation Summary


### 1. Core Components Created

#### Notification Class
- **File:** `app/Notifications/AdminNotification.php`
- **Purpose:** Laravel Notification class for admin notifications
- **Features:**
  - Queued for performance
  - Extensible to multiple channels (currently email, ready for Firebase/database)
  - Handles subject, message, and optional action button

#### Mailable Class
- **File:** `app/Mail/AdminNotificationMail.php`
- **Purpose:** Email template wrapper for notifications
- **Features:**
  - Uses professional email template system
  - Includes logo and branding
  - Queued for async processing

#### Service Layer
- **File:** `app/Services/AdminNotificationService.php`
- **Purpose:** Business logic for sending notifications
- **Methods:**
  - `sendToAll()` - Send to all verified users
  - `sendToSelected()` - Send to specific user IDs
  - `sendToSingle()` - Send to one user
  - `sendToFiltered()` - Send to users matching filters

#### Controller
- **File:** `app/Http/Controllers/Admin/AdminNotificationController.php`
- **Purpose:** API endpoint for admin notifications
- **Endpoint:** `POST /api/v1/admin/notifications/send`
- **Features:**
  - Admin-only access
  - Comprehensive validation
  - OpenAPI documentation

#### Request Validation
- **File:** `app/Http/Requests/SendAdminNotificationRequest.php`
- **Purpose:** Validates notification requests
- **Validations:**
  - Subject (required, max 255 chars)
  - Message (required, max 5000 chars)
  - Recipient type validation
  - User ID validation (UUID format)
  - Filter validation

#### Email Template
- **File:** `resources/views/emails/admin/notification.blade.php`
- **Purpose:** Email template for admin notifications
- **Features:**
  - Professional design matching other emails
  - Logo integration
  - Optional action button
  - Responsive design

#### Routes
- **File:** `routes/api.php`
- **Admin Route:** `POST /api/v1/admin/notifications/send`
- **User Routes:**
  - `GET /api/v1/notifications` - List notifications
  - `GET /api/v1/notifications/unread-count` - Unread count
  - `PUT /api/v1/notifications/{id}/read` - Mark as read
  - `PUT /api/v1/notifications/read-all` - Mark all as read
  - `DELETE /api/v1/notifications/{id}` - Delete notification
- **Middleware:** Authentication required

#### Database Migration
- **File:** `database/migrations/2025_12_05_135836_create_notifications_table.php`
- **Purpose:** Stores notifications in database for in-app access
- **Features:**
  - UUID primary key
  - Polymorphic relationship to users
  - Read status tracking
  - JSON data storage

---

## 📋 Features

### Recipient Types

1. **All Users**
   - Sends to all verified users
   - Useful for platform-wide announcements

2. **Selected Users**
   - Send to specific user IDs (array)
   - Useful for targeted campaigns

3. **Filtered Users**
   - Filter by role (investor, wholesaler, admin)
   - Filter by search (name or email)
   - Useful for role-specific announcements

4. **Single User**
   - Send to one user by ID
   - Useful for individual notifications

### Notification Features

- ✅ **Database Storage** - Notifications stored in database for in-app access
- ✅ **Email Delivery** - Professional email template with logo
- ✅ **Custom Content** - Custom subject and message
- ✅ **Action Buttons** - Optional action button (URL + text)
- ✅ **Read Status** - Track read/unread status
- ✅ **User API** - Users can retrieve, mark as read, and delete notifications
- ✅ **Responsive Design** - Mobile-friendly email templates
- ✅ **Queued Processing** - Async processing for performance
- ✅ **Error Handling** - Comprehensive error handling and logging

---

## 🔧 Usage Examples

### Send to All Users
```bash
POST /api/v1/admin/notifications/send
{
  "subject": "Platform Update",
  "message": "We have exciting news!",
  "recipient_type": "all"
}
```

### Send to Selected Users
```bash
POST /api/v1/admin/notifications/send
{
  "subject": "Welcome Back",
  "message": "We missed you!",
  "recipient_type": "selected",
  "user_ids": ["uuid1", "uuid2"]
}
```

### Send to Filtered Users
```bash
POST /api/v1/admin/notifications/send
{
  "subject": "New Properties",
  "message": "Check out our latest listings",
  "recipient_type": "filtered",
  "filters": {
    "role": "investor"
  }
}
```

### Send to Single User
```bash
POST /api/v1/admin/notifications/send
{
  "subject": "Account Verification",
  "message": "Please verify your email",
  "recipient_type": "single",
  "user_id": "uuid",
  "action_url": "https://yourapp.com/verify",
  "action_text": "Verify Email"
}
```

---

## 🚀 Future Extensions

The system is designed to easily extend to:

1. **Database Notifications**
   - Add `toDatabase()` method
   - Update `via()` to include `'database'`

2. **Firebase Push Notifications**
   - Create Firebase channel
   - Add to `via()` method

3. **SMS Notifications**
   - Add SMS channel
   - Configure SMS provider

4. **In-App Notifications**
   - Use database notifications
   - Create frontend notification center

---

## 📊 Response Format

### Success Response
```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "total_users": 150,
    "sent": 148,
    "failed": 2
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": "Validation error message"
}
```

---

## 🔐 Security

- ✅ Admin-only access (middleware)
- ✅ Request validation
- ✅ Only sends to verified users
- ✅ Error logging for failed sends
- ✅ UUID validation for user IDs

---

## 📝 Files Created/Modified

### New Files
1. `app/Notifications/AdminNotification.php`
2. `app/Mail/AdminNotificationMail.php`
3. `app/Services/AdminNotificationService.php`
4. `app/Http/Controllers/Admin/AdminNotificationController.php`
5. `app/Http/Controllers/NotificationController.php` - User notification endpoints
6. `app/Http/Requests/SendAdminNotificationRequest.php`
7. `app/Http/Resources/NotificationResource.php`
8. `resources/views/emails/admin/notification.blade.php`
9. `database/migrations/2025_12_05_135836_create_notifications_table.php`
10. `ADMIN_NOTIFICATIONS_GUIDE.md`
11. `NOTIFICATION_SYSTEM_SUMMARY.md`

### Modified Files
1. `routes/api.php` - Added admin and user notification routes

---

## ✅ Testing Checklist

- [ ] Test sending to all users
- [ ] Test sending to selected users
- [ ] Test sending to filtered users
- [ ] Test sending to single user
- [ ] Test with action button
- [ ] Test without action button
- [ ] Test validation errors
- [ ] Test admin authorization
- [ ] Test email delivery
- [ ] Test queue processing

---

## 📚 Documentation

- **API Guide:** See `ADMIN_NOTIFICATIONS_GUIDE.md`
- **API Docs:** Available at `/api/documentation` (Swagger UI)
- **Email Templates:** See `resources/views/emails/admin/`

---

**Status:** ✅ Complete and Ready for Use

**Next Steps:**
1. Test the endpoint with Postman/API client
2. Integrate into admin frontend panel
3. Monitor queue workers for bulk sends
4. Consider adding rate limiting for production

