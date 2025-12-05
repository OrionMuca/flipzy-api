# Project Requirements

This document contains important requirements and standards that must be followed throughout the project.

---

## API Response Consistency

**CRITICAL:** All API endpoints MUST follow consistent response formats based on their operation type. This ensures predictable frontend integration and better developer experience.

### CREATE Endpoints (POST)

**Standard Response Format:**
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": {
    // Created resource object
  }
}
```

**HTTP Status Code:** `201 Created`

**Examples:**
- `POST /api/v1/waiting-list/register`
- `POST /api/v1/admin/coupons`
- `POST /api/v1/properties`
- `POST /api/v1/conversations`

**Requirements:**
- Always include `success: true`
- Always include a descriptive `message` field
- Always include the created resource in `data` field
- Use HTTP status code 201
- Return the complete resource object (not just ID)

---

### INDEX Endpoints (GET - List)

**Standard Response Format:**
```json
{
  "data": [
    // Array of resource objects
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `GET /api/v1/admin/waiting-list`
- `GET /api/v1/admin/coupons`
- `GET /api/v1/properties`
- `GET /api/v1/conversations`

**Requirements:**
- Always include `data` array containing resource objects
- Always include `pagination` object with pagination metadata
- Use HTTP status code 200
- Pagination object must include: `current_page`, `last_page`, `per_page`, `total`
- For non-paginated lists, still include pagination object with all items counted

---

### SHOW Endpoints (GET - Single Resource)

**Standard Response Format:**
```json
{
  "success": true,
  "data": {
    // Resource object
  }
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `GET /api/v1/admin/waiting-list/{id}`
- `GET /api/v1/admin/coupons/{id}`
- `GET /api/v1/properties/{id}`
- `GET /api/v1/conversations/{id}`

**Requirements:**
- Always include `success: true`
- Always include the resource in `data` field
- Use HTTP status code 200
- Return complete resource object with all relationships loaded if needed

---

### UPDATE Endpoints (PUT/PATCH)

**Standard Response Format:**
```json
{
  "success": true,
  "message": "Resource updated successfully",
  "data": {
    // Updated resource object
  }
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `PUT /api/v1/admin/coupons/{id}`
- `PUT /api/v1/properties/{id}`
- `PATCH /api/v1/users/{id}`

**Requirements:**
- Always include `success: true`
- Always include a descriptive `message` field
- Always include the updated resource in `data` field
- Use HTTP status code 200
- Return the complete updated resource object

---

### DELETE Endpoints (DELETE)

**Standard Response Format:**
```json
{
  "success": true,
  "message": "Resource deleted successfully"
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `DELETE /api/v1/admin/coupons/{id}`
- `DELETE /api/v1/properties/{id}`
- `DELETE /api/v1/users/{id}`

**Requirements:**
- Always include `success: true`
- Always include a descriptive `message` field
- Do NOT include `data` field (resource is deleted)
- Use HTTP status code 200

---

## Error Response Consistency

**CRITICAL:** All error responses MUST follow consistent formats based on error type.

### Validation Errors (400/422)

**Standard Response Format:**
```json
{
  "success": false,
  "errors": {
    "field_name": ["Error message 1", "Error message 2"],
    "another_field": ["Error message"]
  }
}
```

**HTTP Status Code:** `400 Bad Request` or `422 Unprocessable Entity`

**Requirements:**
- Always include `success: false`
- Always include `errors` object with field names as keys
- Each field error is an array of strings (multiple validation rules can fail)
- Use 400 for general validation errors
- Use 422 for form validation errors (Laravel standard)

---

### Business Logic Errors (400)

**Standard Response Format:**
```json
{
  "success": false,
  "error": "Descriptive error message"
}
```

**HTTP Status Code:** `400 Bad Request`

**Examples:**
- "This email is already registered on the waiting list"
- "Coupon code not found"
- "Insufficient permissions"

**Requirements:**
- Always include `success: false`
- Always include `error` field with descriptive message
- Use HTTP status code 400
- Do NOT include `errors` field (use `error` singular)

---

### Authentication Errors (401)

**Standard Response Format:**
```json
{
  "message": "Unauthenticated."
}
```

**HTTP Status Code:** `401 Unauthorized`

**Requirements:**
- Use Laravel's default authentication error format
- Use HTTP status code 401
- Message should be clear and consistent

---

### Authorization Errors (403)

**Standard Response Format:**
```json
{
  "message": "This action is unauthorized."
}
```

**HTTP Status Code:** `403 Forbidden`

**Requirements:**
- Use Laravel's default authorization error format
- Use HTTP status code 403
- Message should be clear and consistent

---

### Not Found Errors (404)

**Standard Response Format:**
```json
{
  "message": "No query results for model [App\\Models\\ResourceName] {id}"
}
```

**HTTP Status Code:** `404 Not Found`

**Requirements:**
- Use Laravel's default model not found error format
- Use HTTP status code 404
- Message should include model name and identifier

---

## Additional Requirements

### Date/Time Format

**Standard:** All date and datetime fields MUST be returned in US format (MM-DD-YYYY).

**Format:**
- **Date only:** `MM-DD-YYYY` (e.g., `"01-15-2025"`)
- **DateTime:** `MM-DD-YYYY HH:MM:SS` (e.g., `"01-15-2025 10:30:00"`)

**Examples:**
- `"01-15-2025 10:30:00"` (datetime with time)
- `"01-15-2025"` (date only)

**Requirements:**
- Consistent format across all endpoints
- Use US date format (MM-DD-YYYY) for all date/datetime fields
- DateTime fields must include time in 24-hour format (HH:MM:SS)
- Date-only fields (without time) use MM-DD-YYYY format
- All timestamps are stored in UTC but displayed in MM-DD-YYYY format

---

### UUID Format

**Standard:** All IDs MUST be UUIDs (v4).

**Format:** `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`

**Requirements:**
- Use UUID v4 format
- Always return as string
- Validate UUID format in all endpoints accepting IDs

---

### Pagination

**Standard:** All paginated endpoints MUST include pagination metadata.

**Format:**
```json
{
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

**Requirements:**
- Always include pagination object
- Default `per_page`: 15
- Maximum `per_page`: 100
- Always include all four fields: `current_page`, `last_page`, `per_page`, `total`

---

### Nullable Fields

**Standard:** Nullable fields MUST be returned as `null` (not omitted).

**Requirements:**
- Always include nullable fields in response
- Use `null` value, not empty string or empty array
- Document nullable fields in API documentation

---

### Resource Relationships

**Standard:** Related resources SHOULD be loaded when needed, but not always.

**Requirements:**
- Use Laravel Resource classes for consistent formatting
- Load relationships explicitly when needed
- Document which relationships are included in responses
- Use `whenLoaded()` in Resource classes to conditionally include relationships

---

## Code Standards

### Controller Response Methods

**Standard:** Use consistent response methods in controllers.

**Examples:**
```php
// CREATE
return response()->json([
    'success' => true,
    'message' => 'Resource created successfully',
    'data' => new ResourceResource($resource),
], 201);

// INDEX
return ResourceResource::collection($resources)->additional([
    'pagination' => [
        'current_page' => $resources->currentPage(),
        'last_page' => $resources->lastPage(),
        'per_page' => $resources->perPage(),
        'total' => $resources->total(),
    ],
]);

// SHOW
return response()->json([
    'success' => true,
    'data' => new ResourceResource($resource),
]);

// UPDATE
return response()->json([
    'success' => true,
    'message' => 'Resource updated successfully',
    'data' => new ResourceResource($resource),
]);

// DELETE
return response()->json([
    'success' => true,
    'message' => 'Resource deleted successfully',
]);
```

---

## Testing Requirements

**Standard:** All endpoints MUST have tests that verify response format consistency.

**Requirements:**
- Test success responses match standard format
- Test error responses match standard format
- Test pagination structure
- Test nullable fields return null
- Test authentication/authorization errors

---

## Documentation Requirements

**Standard:** All endpoints MUST be documented with OpenAPI/Swagger annotations.

**Requirements:**
- Document request/response formats
- Include all possible error responses
- Document query parameters
- Document authentication requirements
- Keep documentation up to date

---

## Versioning

**Standard:** All API endpoints MUST be versioned.

**Format:** `/api/v1/...`

**Requirements:**
- Use version prefix in all routes
- Maintain backward compatibility within version
- Document breaking changes
- Plan for future versions

---

## Summary Checklist

When creating or updating endpoints, verify:

- [ ] Response format matches operation type (CREATE/INDEX/SHOW/UPDATE/DELETE)
- [ ] HTTP status code is correct
- [ ] Error responses follow standard format
- [ ] Pagination included for list endpoints
- [ ] Datetime fields use ISO format
- [ ] UUIDs are properly formatted
- [ ] Nullable fields return null (not omitted)
- [ ] Tests verify response format
- [ ] OpenAPI documentation is updated
- [ ] Version prefix is included in route

---

**Last Updated:** 2025-01-15

**Note:** This document should be updated as new requirements are identified or standards evolve.

