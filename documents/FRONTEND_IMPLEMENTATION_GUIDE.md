# Frontend Implementation Guide - Waiting List Admin Panel

This guide provides everything a frontend developer needs to implement the new waiting list admin panel features.

## Table of Contents

1. [Authentication](#authentication)
2. [Daily Signups Dashboard](#daily-signups-dashboard)
3. [Geographic Distribution](#geographic-distribution)
4. [Enhanced Filtering & Reports](#enhanced-filtering--reports)
5. [Data Export](#data-export)
6. [Email Campaign Management](#email-campaign-management)
7. [TypeScript Interfaces](#typescript-interfaces)
8. [Error Handling](#error-handling)

---

## Authentication

All endpoints require admin authentication using Bearer token.

```typescript
const headers = {
  'Authorization': `Bearer ${accessToken}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};
```

---

## Daily Signups Dashboard

### Endpoint
```
GET /api/v1/admin/waiting-list/daily-signups
```

### Request
```typescript
// No query parameters required
const response = await fetch('/api/v1/admin/waiting-list/daily-signups', {
  method: 'GET',
  headers: headers
});
```

### Response Structure
```typescript
interface DailySignupsResponse {
  success: boolean;
  data: {
    total_last_30_days: number;        // Total signups in last 30 days
    average_daily: number;              // Average signups per day (rounded to 2 decimals)
    today_count: number;                // Signups today
    daily_breakdown: DailyBreakdown[];  // Array of daily data
  };
}

interface DailyBreakdown {
  date: string;              // Format: "YYYY-MM-DD"
  formatted_date: string;    // Format: "Dec 14, 2024"
  signups: number;           // Number of signups on this date
  change: number | null;     // Change from previous day (null for first day)
  change_type: 'up' | 'down' | 'same' | null;  // Change indicator
}
```

### Example Response
```json
{
  "success": true,
  "data": {
    "total_last_30_days": 138,
    "average_daily": 4.6,
    "today_count": 30,
    "daily_breakdown": [
      {
        "date": "2024-11-15",
        "formatted_date": "Nov 15, 2024",
        "signups": 5,
        "change": null,
        "change_type": null
      },
      {
        "date": "2024-11-16",
        "formatted_date": "Nov 16, 2024",
        "signups": 16,
        "change": 11,
        "change_type": "up"
      },
      {
        "date": "2024-12-14",
        "formatted_date": "Dec 14, 2024",
        "signups": 30,
        "change": 19,
        "change_type": "up"
      }
    ]
  }
}
```

### Frontend Implementation Example
```typescript
async function getDailySignups(): Promise<DailySignupsResponse> {
  const response = await fetch('/api/v1/admin/waiting-list/daily-signups', {
    method: 'GET',
    headers: headers
  });
  
  if (!response.ok) {
    throw new Error('Failed to fetch daily signups');
  }
  
  return response.json();
}

// Usage
const data = await getDailySignups();
console.log(`Total last 30 days: ${data.data.total_last_30_days}`);
console.log(`Average daily: ${data.data.average_daily}`);
console.log(`Today: ${data.data.today_count}`);
```

---

## Geographic Distribution

### Endpoint
```
GET /api/v1/admin/waiting-list/geographic-distribution
```

### Request
```typescript
const response = await fetch('/api/v1/admin/waiting-list/geographic-distribution', {
  method: 'GET',
  headers: headers
});
```

### Response Structure
```typescript
interface GeographicDistributionResponse {
  success: boolean;
  data: {
    total_signups: number;           // Total waiting list entries
    unique_states_count: number;      // Number of unique states
    top_state: string | null;         // State code with most signups (e.g., "CA")
    states: Record<string, number>;   // Object mapping state codes to counts
    top_states: TopState[];           // Ranked array of states
  };
}

interface TopState {
  state: string;        // State code (2 letters, e.g., "CA", "NY")
  count: number;       // Number of signups in this state
  percentage: number;  // Percentage of total (rounded to 2 decimals)
}
```

### Example Response
```json
{
  "success": true,
  "data": {
    "total_signups": 3508,
    "unique_states_count": 50,
    "top_state": "WY",
    "states": {
      "WY": 131,
      "WA": 126,
      "CT": 124,
      "MN": 124,
      "IN": 122,
      "KS": 122,
      "MS": 118,
      "NC": 115,
      "AR": 113,
      "TX": 110
    },
    "top_states": [
      {
        "state": "WY",
        "count": 131,
        "percentage": 3.73
      },
      {
        "state": "WA",
        "count": 126,
        "percentage": 3.59
      },
      {
        "state": "CT",
        "count": 124,
        "percentage": 3.53
      }
    ]
  }
}
```

### Frontend Implementation Example
```typescript
async function getGeographicDistribution(): Promise<GeographicDistributionResponse> {
  const response = await fetch('/api/v1/admin/waiting-list/geographic-distribution', {
    method: 'GET',
    headers: headers
  });
  
  if (!response.ok) {
    throw new Error('Failed to fetch geographic distribution');
  }
  
  return response.json();
}

// Usage
const data = await getGeographicDistribution();
console.log(`Total signups: ${data.data.total_signups}`);
console.log(`States: ${data.data.unique_states_count}`);
console.log(`Top state: ${data.data.top_state}`);

// Display state grid
Object.entries(data.data.states).forEach(([state, count]) => {
  console.log(`${state}: ${count} signups`);
});
```

---

## Enhanced Filtering & Reports

### Endpoint
```
GET /api/v1/admin/waiting-list
```

### Query Parameters
```typescript
interface WaitingListFilters {
  user_type?: 'all' | 'investor' | 'wholesaler' | 'both';
  sign_up_month?: number | 'all';  // 1-12 or 'all'
  sign_up_year?: number | 'all';    // e.g., 2024 or 'all'
  status?: 'pending' | 'account_created' | 'cancelled';
  search?: string;                   // Search by email, name, phone, company
  sort_by?: string;                  // Default: 'created_at'
  sort_order?: 'asc' | 'desc';      // Default: 'desc'
  per_page?: number;                 // Default: 15, max: 100
  page?: number;                     // Default: 1
}
```

### Request Example
```typescript
const params = new URLSearchParams({
  user_type: 'investor',
  sign_up_month: '12',
  sign_up_year: '2024',
  per_page: '20',
  page: '1'
});

const response = await fetch(`/api/v1/admin/waiting-list?${params}`, {
  method: 'GET',
  headers: headers
});
```

### Response Structure
```typescript
interface WaitingListResponse {
  success: boolean;
  data: WaitingListEntry[];          // Array of entries
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  summary: {
    total_records: number;           // Total matching filter criteria
    investors: number;                // Count of investors only
    wholesalers: number;              // Count of wholesalers only
    both: number;                     // Count with both roles
  };
}

interface WaitingListEntry {
  id: string;                         // UUID
  email: string;
  name: string;
  phone_number: string | null;
  company_name: string | null;
  selected_roles: string[];           // ['wholesaler', 'investor'] or one of them
  status: 'pending' | 'account_created' | 'cancelled';
  coupon_code: string | null;
  email_verified: boolean;
  email_verified_at: string | null;   // Format: "MM-DD-YYYY HH:MM:SS"
  account_created: boolean;
  account_created_at: string | null;  // Format: "MM-DD-YYYY HH:MM:SS"
  created_at: string;                 // Format: "MM-DD-YYYY HH:MM:SS"
  updated_at: string;                 // Format: "MM-DD-YYYY HH:MM:SS"
  state: string | null;               // US state code (2 letters)
  ip_address: string | null;          // IP address (admin only)
}
```

### Example Response
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "email": "john@example.com",
      "name": "John Doe",
      "phone_number": "+1234567890",
      "company_name": "Example Corp",
      "selected_roles": ["investor"],
      "status": "pending",
      "coupon_code": null,
      "email_verified": true,
      "email_verified_at": "12-10-2024 10:30:00",
      "account_created": false,
      "account_created_at": null,
      "created_at": "12-10-2024 10:00:00",
      "updated_at": "12-10-2024 10:30:00",
      "state": "CA",
      "ip_address": null
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 96
  },
  "summary": {
    "total_records": 96,
    "investors": 39,
    "wholesalers": 27,
    "both": 30
  }
}
```

### Frontend Implementation Example
```typescript
interface FilterState {
  userType: string;
  month: string;
  year: string;
  search: string;
  page: number;
}

async function getWaitingList(filters: FilterState): Promise<WaitingListResponse> {
  const params = new URLSearchParams();
  
  if (filters.userType !== 'all') params.append('user_type', filters.userType);
  if (filters.month !== 'all') params.append('sign_up_month', filters.month);
  if (filters.year !== 'all') params.append('sign_up_year', filters.year);
  if (filters.search) params.append('search', filters.search);
  params.append('page', filters.page.toString());
  params.append('per_page', '20');
  
  const response = await fetch(`/api/v1/admin/waiting-list?${params}`, {
    method: 'GET',
    headers: headers
  });
  
  if (!response.ok) {
    throw new Error('Failed to fetch waiting list');
  }
  
  return response.json();
}

// Usage
const filters = {
  userType: 'investor',
  month: '12',
  year: '2024',
  search: '',
  page: 1
};

const result = await getWaitingList(filters);
console.log(`Total: ${result.summary.total_records}`);
console.log(`Investors: ${result.summary.investors}`);
console.log(`Entries: ${result.data.length}`);
```

---

## Data Export

### CSV Export
```
GET /api/v1/admin/waiting-list/export/csv
```

### Excel Export
```
GET /api/v1/admin/waiting-list/export/excel
```

### PDF Export
```
GET /api/v1/admin/waiting-list/export/pdf
```

### Query Parameters
All export endpoints accept the same filter parameters as the main list endpoint:
- `user_type`
- `sign_up_month`
- `sign_up_year`
- `status`
- `search`

### Request Example
```typescript
// Export CSV with filters
const params = new URLSearchParams({
  user_type: 'investor',
  sign_up_month: '12',
  sign_up_year: '2024'
});

const response = await fetch(`/api/v1/admin/waiting-list/export/csv?${params}`, {
  method: 'GET',
  headers: headers
});

// Handle file download
const blob = await response.blob();
const url = window.URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = `waiting-list-${new Date().toISOString().split('T')[0]}.csv`;
document.body.appendChild(a);
a.click();
document.body.removeChild(a);
window.URL.revokeObjectURL(url);
```

### Response
- **Content-Type:** 
  - CSV: `text/csv`
  - Excel: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
  - PDF: `application/pdf`
- **Content-Disposition:** `attachment; filename="waiting-list-YYYY-MM-DD-HHMMSS.{ext}"`

### Frontend Implementation Example
```typescript
async function exportWaitingList(
  format: 'csv' | 'excel' | 'pdf',
  filters: FilterState
): Promise<void> {
  const params = new URLSearchParams();
  
  if (filters.userType !== 'all') params.append('user_type', filters.userType);
  if (filters.month !== 'all') params.append('sign_up_month', filters.month);
  if (filters.year !== 'all') params.append('sign_up_year', filters.year);
  if (filters.search) params.append('search', filters.search);
  
  const response = await fetch(`/api/v1/admin/waiting-list/export/${format}?${params}`, {
    method: 'GET',
    headers: headers
  });
  
  if (!response.ok) {
    throw new Error(`Failed to export ${format}`);
  }
  
  const blob = await response.blob();
  const contentType = response.headers.get('Content-Type') || '';
  const contentDisposition = response.headers.get('Content-Disposition') || '';
  
  // Extract filename from Content-Disposition header
  const filenameMatch = contentDisposition.match(/filename="(.+)"/);
  const filename = filenameMatch 
    ? filenameMatch[1] 
    : `waiting-list-${new Date().toISOString().split('T')[0]}.${format === 'excel' ? 'xlsx' : format}`;
  
  // Download file
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}

// Usage
await exportWaitingList('csv', filters);
await exportWaitingList('excel', filters);
await exportWaitingList('pdf', filters);
```

---

## Email Campaign Management

### List Campaigns
```
GET /api/v1/admin/email-campaigns
```

#### Query Parameters
```typescript
interface CampaignListParams {
  status?: 'draft' | 'sent';
  sort_by?: string;        // Default: 'created_at'
  sort_order?: 'asc' | 'desc';  // Default: 'desc'
  per_page?: number;       // Default: 15, max: 100
  page?: number;           // Default: 1
}
```

#### Response Structure
```typescript
interface CampaignListResponse {
  success: boolean;
  data: CampaignListItem[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface CampaignListItem {
  id: string;                    // UUID
  name: string;
  subject: string;
  status: 'draft' | 'sent';
  recipients_count: number;
  sent_count: number;
  scheduled_at: string | null;   // Format: "MM/DD/YYYY, g:i A" or null
  sent_at: string | null;        // Format: "MM/DD/YYYY, g:i A" or null
  created_at: string;            // Format: "MM/DD/YYYY, g:i A"
  open_rate: number;             // Percentage (0-100)
}
```

### Create Campaign
```
POST /api/v1/admin/email-campaigns
```

#### Request Body
```typescript
interface CreateCampaignRequest {
  name: string;                  // Required, max 255 chars
  subject: string;               // Required, max 255 chars
  content: string;               // Required
  scheduled_at?: string;         // Optional, ISO 8601 date string
}
```

#### Response
```typescript
interface CreateCampaignResponse {
  success: boolean;
  message: string;
  data: {
    id: string;
    name: string;
    subject: string;
    content: string;
    status: 'draft';
    recipients_count: 0;
    sent_count: 0;
    scheduled_at: string | null;
    sent_at: null;
    created_at: string;
    updated_at: string;
  };
}
```

### Get Campaign Details
```
GET /api/v1/admin/email-campaigns/{id}
```

#### Response Structure
```typescript
interface CampaignDetailResponse {
  success: boolean;
  data: {
    id: string;
    name: string;
    subject: string;
    content: string;
    status: 'draft' | 'sent';
    recipients_count: number;
    sent_count: number;
    scheduled_at: string | null;
    sent_at: string | null;
    created_at: string;
    stats: {
      total_recipients: number;
      sent: number;
      opened: number;
      open_rate: number;         // Percentage (0-100)
    };
  };
}
```

### Update Campaign
```
PUT /api/v1/admin/email-campaigns/{id}
```

#### Request Body
```typescript
interface UpdateCampaignRequest {
  name?: string;
  subject?: string;
  content?: string;
  scheduled_at?: string | null;
}
```

**Note:** Cannot update campaigns that have already been sent.

### Delete Campaign
```
DELETE /api/v1/admin/email-campaigns/{id}
```

#### Response
```json
{
  "success": true,
  "message": "Campaign deleted successfully"
}
```

### Send Campaign
```
POST /api/v1/admin/email-campaigns/{id}/send
```

#### Request Body (Optional)
```typescript
interface SendCampaignRequest {
  status?: 'pending' | 'account_created' | 'cancelled';  // Filter by entry status
  user_type?: 'all' | 'investor' | 'wholesaler' | 'both';  // Filter by user type
}
```

If no filters provided, sends to all waiting list entries.

#### Response
```typescript
interface SendCampaignResponse {
  success: boolean;
  message: string;
  data: {
    total_recipients: number;
    sent: number;
    failed: number;
  };
}
```

### Get Campaign Statistics
```
GET /api/v1/admin/email-campaigns/stats
```

#### Response Structure
```typescript
interface CampaignStatsResponse {
  success: boolean;
  data: {
    total_emails_sent: number;      // Total emails sent across all campaigns
    total_recipients: number;        // Total waiting list entries
    email_templates: number;          // Number of email templates (currently 2)
    average_open_rate: number;        // Average open rate percentage (0-100)
  };
}
```

### Frontend Implementation Examples

```typescript
// List campaigns
async function getCampaigns(params?: CampaignListParams): Promise<CampaignListResponse> {
  const queryParams = new URLSearchParams();
  if (params?.status) queryParams.append('status', params.status);
  if (params?.sort_by) queryParams.append('sort_by', params.sort_by);
  if (params?.sort_order) queryParams.append('sort_order', params.sort_order);
  if (params?.per_page) queryParams.append('per_page', params.per_page.toString());
  if (params?.page) queryParams.append('page', params.page.toString());
  
  const response = await fetch(`/api/v1/admin/email-campaigns?${queryParams}`, {
    method: 'GET',
    headers: headers
  });
  
  if (!response.ok) throw new Error('Failed to fetch campaigns');
  return response.json();
}

// Create campaign
async function createCampaign(data: CreateCampaignRequest): Promise<CreateCampaignResponse> {
  const response = await fetch('/api/v1/admin/email-campaigns', {
    method: 'POST',
    headers: headers,
    body: JSON.stringify(data)
  });
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to create campaign');
  }
  
  return response.json();
}

// Send campaign
async function sendCampaign(
  campaignId: string,
  filters?: SendCampaignRequest
): Promise<SendCampaignResponse> {
  const response = await fetch(`/api/v1/admin/email-campaigns/${campaignId}/send`, {
    method: 'POST',
    headers: headers,
    body: JSON.stringify(filters || {})
  });
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to send campaign');
  }
  
  return response.json();
}

// Get campaign stats
async function getCampaignStats(): Promise<CampaignStatsResponse> {
  const response = await fetch('/api/v1/admin/email-campaigns/stats', {
    method: 'GET',
    headers: headers
  });
  
  if (!response.ok) throw new Error('Failed to fetch campaign stats');
  return response.json();
}
```

---

## TypeScript Interfaces

Complete TypeScript definitions for all endpoints:

```typescript
// ============================================
// Daily Signups
// ============================================

interface DailySignupsResponse {
  success: boolean;
  data: {
    total_last_30_days: number;
    average_daily: number;
    today_count: number;
    daily_breakdown: DailyBreakdown[];
  };
}

interface DailyBreakdown {
  date: string;
  formatted_date: string;
  signups: number;
  change: number | null;
  change_type: 'up' | 'down' | 'same' | null;
}

// ============================================
// Geographic Distribution
// ============================================

interface GeographicDistributionResponse {
  success: boolean;
  data: {
    total_signups: number;
    unique_states_count: number;
    top_state: string | null;
    states: Record<string, number>;
    top_states: TopState[];
  };
}

interface TopState {
  state: string;
  count: number;
  percentage: number;
}

// ============================================
// Waiting List
// ============================================

interface WaitingListResponse {
  success: boolean;
  data: WaitingListEntry[];
  pagination: Pagination;
  summary: WaitingListSummary;
}

interface WaitingListEntry {
  id: string;
  email: string;
  name: string;
  phone_number: string | null;
  company_name: string | null;
  selected_roles: string[];
  status: 'pending' | 'account_created' | 'cancelled';
  coupon_code: string | null;
  email_verified: boolean;
  email_verified_at: string | null;
  account_created: boolean;
  account_created_at: string | null;
  created_at: string;
  updated_at: string;
  state: string | null;
  ip_address?: string | null;  // Admin only
}

interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface WaitingListSummary {
  total_records: number;
  investors: number;
  wholesalers: number;
  both: number;
}

// ============================================
// Email Campaigns
// ============================================

interface CampaignListResponse {
  success: boolean;
  data: CampaignListItem[];
  pagination: Pagination;
}

interface CampaignListItem {
  id: string;
  name: string;
  subject: string;
  status: 'draft' | 'sent';
  recipients_count: number;
  sent_count: number;
  scheduled_at: string | null;
  sent_at: string | null;
  created_at: string;
  open_rate: number;
}

interface CampaignDetailResponse {
  success: boolean;
  data: {
    id: string;
    name: string;
    subject: string;
    content: string;
    status: 'draft' | 'sent';
    recipients_count: number;
    sent_count: number;
    scheduled_at: string | null;
    sent_at: string | null;
    created_at: string;
    stats: CampaignStats;
  };
}

interface CampaignStats {
  total_recipients: number;
  sent: number;
  opened: number;
  open_rate: number;
}

interface CampaignStatsResponse {
  success: boolean;
  data: {
    total_emails_sent: number;
    total_recipients: number;
    email_templates: number;
    average_open_rate: number;
  };
}

// ============================================
// Request Types
// ============================================

interface CreateCampaignRequest {
  name: string;
  subject: string;
  content: string;
  scheduled_at?: string;
}

interface UpdateCampaignRequest {
  name?: string;
  subject?: string;
  content?: string;
  scheduled_at?: string | null;
}

interface SendCampaignRequest {
  status?: 'pending' | 'account_created' | 'cancelled';
  user_type?: 'all' | 'investor' | 'wholesaler' | 'both';
}
```

---

## Error Handling

### Standard Error Response
```typescript
interface ErrorResponse {
  success: false;
  error?: string;           // Error message
  errors?: Record<string, string[]>;  // Validation errors
  message?: string;         // Alternative error message
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created (campaign created)
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (missing/invalid token)
- `403` - Forbidden (not admin)
- `404` - Not Found (resource doesn't exist)
- `500` - Server Error

### Error Handling Example
```typescript
async function handleApiCall<T>(
  url: string,
  options: RequestInit = {}
): Promise<T> {
  const response = await fetch(url, {
    ...options,
    headers: {
      ...headers,
      ...options.headers,
    },
  });
  
  const data = await response.json();
  
  if (!response.ok) {
    if (response.status === 401) {
      // Handle unauthorized - redirect to login
      window.location.href = '/login';
      throw new Error('Unauthorized');
    }
    
    if (response.status === 403) {
      throw new Error('Access denied. Admin privileges required.');
    }
    
    if (data.errors) {
      // Validation errors
      const errorMessages = Object.values(data.errors).flat().join(', ');
      throw new Error(errorMessages);
    }
    
    throw new Error(data.error || data.message || 'An error occurred');
  }
  
  return data;
}
```

---

## Complete API Client Example

```typescript
class WaitingListAdminAPI {
  private baseUrl: string;
  private accessToken: string;
  
  constructor(baseUrl: string, accessToken: string) {
    this.baseUrl = baseUrl;
    this.accessToken = accessToken;
  }
  
  private getHeaders(): HeadersInit {
    return {
      'Authorization': `Bearer ${this.accessToken}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
  }
  
  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      ...options,
      headers: {
        ...this.getHeaders(),
        ...options.headers,
      },
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.error || error.message || 'Request failed');
    }
    
    return response.json();
  }
  
  // Daily Signups
  async getDailySignups(): Promise<DailySignupsResponse> {
    return this.request('/api/v1/admin/waiting-list/daily-signups');
  }
  
  // Geographic Distribution
  async getGeographicDistribution(): Promise<GeographicDistributionResponse> {
    return this.request('/api/v1/admin/waiting-list/geographic-distribution');
  }
  
  // Waiting List
  async getWaitingList(filters: FilterState): Promise<WaitingListResponse> {
    const params = new URLSearchParams();
    if (filters.userType !== 'all') params.append('user_type', filters.userType);
    if (filters.month !== 'all') params.append('sign_up_month', filters.month);
    if (filters.year !== 'all') params.append('sign_up_year', filters.year);
    if (filters.search) params.append('search', filters.search);
    params.append('page', filters.page.toString());
    
    return this.request(`/api/v1/admin/waiting-list?${params}`);
  }
  
  // Export
  async exportWaitingList(
    format: 'csv' | 'excel' | 'pdf',
    filters: FilterState
  ): Promise<Blob> {
    const params = new URLSearchParams();
    if (filters.userType !== 'all') params.append('user_type', filters.userType);
    if (filters.month !== 'all') params.append('sign_up_month', filters.month);
    if (filters.year !== 'all') params.append('sign_up_year', filters.year);
    
    const response = await fetch(
      `${this.baseUrl}/api/v1/admin/waiting-list/export/${format}?${params}`,
      {
        headers: this.getHeaders(),
      }
    );
    
    if (!response.ok) throw new Error('Export failed');
    return response.blob();
  }
  
  // Email Campaigns
  async getCampaigns(params?: CampaignListParams): Promise<CampaignListResponse> {
    const queryParams = new URLSearchParams();
    if (params?.status) queryParams.append('status', params.status);
    if (params?.page) queryParams.append('page', params.page.toString());
    
    return this.request(`/api/v1/admin/email-campaigns?${queryParams}`);
  }
  
  async createCampaign(data: CreateCampaignRequest): Promise<CreateCampaignResponse> {
    return this.request('/api/v1/admin/email-campaigns', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }
  
  async getCampaign(id: string): Promise<CampaignDetailResponse> {
    return this.request(`/api/v1/admin/email-campaigns/${id}`);
  }
  
  async updateCampaign(
    id: string,
    data: UpdateCampaignRequest
  ): Promise<CreateCampaignResponse> {
    return this.request(`/api/v1/admin/email-campaigns/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }
  
  async deleteCampaign(id: string): Promise<{ success: boolean; message: string }> {
    return this.request(`/api/v1/admin/email-campaigns/${id}`, {
      method: 'DELETE',
    });
  }
  
  async sendCampaign(
    id: string,
    filters?: SendCampaignRequest
  ): Promise<SendCampaignResponse> {
    return this.request(`/api/v1/admin/email-campaigns/${id}/send`, {
      method: 'POST',
      body: JSON.stringify(filters || {}),
    });
  }
  
  async getCampaignStats(): Promise<CampaignStatsResponse> {
    return this.request('/api/v1/admin/email-campaigns/stats');
  }
}

// Usage
const api = new WaitingListAdminAPI('https://api.example.com', accessToken);

// Get daily signups
const dailySignups = await api.getDailySignups();

// Get geographic distribution
const geoData = await api.getGeographicDistribution();

// Get filtered waiting list
const entries = await api.getWaitingList({
  userType: 'investor',
  month: '12',
  year: '2024',
  search: '',
  page: 1,
});

// Export data
const csvBlob = await api.exportWaitingList('csv', filters);
```

---

## React/Next.js Integration Examples

### React Hook Example
```typescript
import { useState, useEffect } from 'react';

function useDailySignups() {
  const [data, setData] = useState<DailySignupsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  useEffect(() => {
    async function fetchData() {
      try {
        setLoading(true);
        const response = await fetch('/api/v1/admin/waiting-list/daily-signups', {
          headers: {
            'Authorization': `Bearer ${accessToken}`,
            'Accept': 'application/json',
          },
        });
        
        if (!response.ok) throw new Error('Failed to fetch');
        
        const result = await response.json();
        setData(result);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    }
    
    fetchData();
  }, []);
  
  return { data, loading, error };
}

// Usage in component
function DailySignupsDashboard() {
  const { data, loading, error } = useDailySignups();
  
  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!data) return null;
  
  return (
    <div>
      <h2>Daily Signups</h2>
      <div>Total (30 days): {data.data.total_last_30_days}</div>
      <div>Average Daily: {data.data.average_daily}</div>
      <div>Today: {data.data.today_count}</div>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Signups</th>
            <th>Change</th>
          </tr>
        </thead>
        <tbody>
          {data.data.daily_breakdown.map((day) => (
            <tr key={day.date}>
              <td>{day.formatted_date}</td>
              <td>{day.signups}</td>
              <td>
                {day.change !== null && (
                  <span className={day.change_type}>
                    {day.change > 0 ? '↑' : day.change < 0 ? '↓' : '='} {Math.abs(day.change)}
                  </span>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

---

## State Management Example (Redux/Zustand)

```typescript
// Using Zustand
import create from 'zustand';

interface WaitingListStore {
  filters: FilterState;
  entries: WaitingListEntry[];
  summary: WaitingListSummary | null;
  pagination: Pagination | null;
  loading: boolean;
  error: string | null;
  
  setFilters: (filters: Partial<FilterState>) => void;
  fetchEntries: () => Promise<void>;
  resetFilters: () => void;
}

const useWaitingListStore = create<WaitingListStore>((set, get) => ({
  filters: {
    userType: 'all',
    month: 'all',
    year: 'all',
    search: '',
    page: 1,
  },
  entries: [],
  summary: null,
  pagination: null,
  loading: false,
  error: null,
  
  setFilters: (newFilters) => {
    set((state) => ({
      filters: { ...state.filters, ...newFilters },
    }));
    get().fetchEntries();
  },
  
  fetchEntries: async () => {
    set({ loading: true, error: null });
    
    try {
      const { filters } = get();
      const params = new URLSearchParams();
      if (filters.userType !== 'all') params.append('user_type', filters.userType);
      if (filters.month !== 'all') params.append('sign_up_month', filters.month);
      if (filters.year !== 'all') params.append('sign_up_year', filters.year);
      if (filters.search) params.append('search', filters.search);
      params.append('page', filters.page.toString());
      
      const response = await fetch(`/api/v1/admin/waiting-list?${params}`, {
        headers: {
          'Authorization': `Bearer ${accessToken}`,
          'Accept': 'application/json',
        },
      });
      
      if (!response.ok) throw new Error('Failed to fetch');
      
      const data: WaitingListResponse = await response.json();
      
      set({
        entries: data.data,
        summary: data.summary,
        pagination: data.pagination,
        loading: false,
      });
    } catch (err) {
      set({
        error: err instanceof Error ? err.message : 'Unknown error',
        loading: false,
      });
    }
  },
  
  resetFilters: () => {
    set({
      filters: {
        userType: 'all',
        month: 'all',
        year: 'all',
        search: '',
        page: 1,
      },
    });
    get().fetchEntries();
  },
}));
```

---

## Notes for Frontend Developer

1. **Date Formats:**
   - API returns dates in format: `"MM-DD-YYYY HH:MM:SS"`
   - Use a date parsing library (e.g., `date-fns`, `dayjs`) to format for display

2. **Pagination:**
   - All list endpoints support pagination
   - Use `pagination` object to build pagination controls

3. **Filtering:**
   - Filters are cumulative (AND logic)
   - Empty string for search means no search filter
   - `'all'` for user_type/month/year means no filter

4. **State Codes:**
   - US state codes are 2-letter abbreviations (e.g., "CA", "NY", "TX")
   - Use a state code mapping library for full state names

5. **Export Files:**
   - Files are streamed, handle as Blob
   - Filename is in `Content-Disposition` header
   - All exports respect current filters

6. **Email Campaigns:**
   - Campaigns can only be edited when status is `'draft'`
   - Once sent, campaigns cannot be modified
   - Open rate is calculated from notification read status

7. **Caching:**
   - Daily signups cached for 10 minutes
   - Geographic distribution cached for 1 hour
   - Consider implementing client-side caching

---

## Quick Reference

### Base URL
```
https://your-api-domain.com
```

### All Endpoints
```
GET    /api/v1/admin/waiting-list
GET    /api/v1/admin/waiting-list/stats
GET    /api/v1/admin/waiting-list/daily-signups
GET    /api/v1/admin/waiting-list/geographic-distribution
GET    /api/v1/admin/waiting-list/export/csv
GET    /api/v1/admin/waiting-list/export/excel
GET    /api/v1/admin/waiting-list/export/pdf
GET    /api/v1/admin/waiting-list/{id}

GET    /api/v1/admin/email-campaigns
POST   /api/v1/admin/email-campaigns
GET    /api/v1/admin/email-campaigns/stats
GET    /api/v1/admin/email-campaigns/{id}
PUT    /api/v1/admin/email-campaigns/{id}
DELETE /api/v1/admin/email-campaigns/{id}
POST   /api/v1/admin/email-campaigns/{id}/send
```

---

**Last Updated:** December 14, 2025  
**API Version:** v1  
**Authentication:** Bearer Token (Admin role required)
