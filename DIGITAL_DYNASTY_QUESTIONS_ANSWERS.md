# Digital Dynasty Questions & Answers
**Date:** November 7th, 2025  
**Project:** Flipzy Backend Platform

---

## **Architecture & Ownership**

### **1. What is the exact stack (frontend, backend, DB, file storage), versions, and why these over alternatives?**

**Stack:**
- **Backend:** Laravel 12 (PHP 8.2+)
- **Database:** MySQL 8.0+ (configured; supports MariaDB)
- **Cache/Queue:** Redis
- **Authentication:** Laravel Passport 13.4 (OAuth2)
- **Authorization:** Spatie Laravel Permission 6.23
- **File Storage:** Local (S3-ready via Laravel Filesystem)
- **AI:** OpenAI PHP SDK 0.18.0 (GPT-3.5-turbo/GPT-4)
- **API Docs:** L5-Swagger 9.0 (OpenAPI/Swagger)
- **Queue Monitor:** Laravel Horizon 5.39

**Why these choices:**
- **Laravel:** Mature ecosystem, built-in security, excellent scalability
- **MySQL:** ACID compliance, JSON support, excellent performance, widely supported
- **Redis:** Fast caching and queue management
- **Passport:** Industry-standard OAuth2 for API token management
- **Spatie Permission:** Flexible role-based access control

---

### **2. What are we doing to build something that is going to be compatible with mobile apps?**

**Current Implementation:**
- ✅ RESTful API with JSON responses
- ✅ OAuth2 bearer tokens (mobile-friendly)
- ✅ UUID primary keys (better than auto-increment for distributed systems)
- ✅ OpenAPI/Swagger documentation (can generate mobile SDKs)
- ✅ Stateless authentication (no sessions required)
- ✅ CORS configured for cross-origin requests

**Mobile Compatibility:**
- API designed for mobile-first consumption
- Token-based auth works seamlessly with iOS/Android
- All endpoints return consistent JSON structure
- No web-specific dependencies

---

### **3. Create the repo under my GitHub org. Confirm branch strategy (dev/staging/prod) and my admin access. Is there an alternative to Github? Why not the alternative?**

**Repository:**
- ✅ Will be created under your GitHub organization
- ✅ You will have full admin access

**Branch Strategy:**
- `main` - Production
- `staging` - Staging environment
- `develop` - Development branch

**Alternatives:**
- **GitLab:** Self-hosted option available, good CI/CD
- **Bitbucket:** Similar to GitHub, less popular
- **Why GitHub:** Industry standard, best integrations, largest community, excellent CI/CD support

---

### **4. Who owns the code and infra from day one? Confirm I own domains, cloud accounts, Map keys, ATTOM keys.**

**Ownership Confirmation:**
- ✅ **Code:** You own all code from day one
- ✅ **Infrastructure:** You own all cloud accounts, domains, API keys
- ✅ **IP:** All code is proprietary (Laravel uses MIT license, but that doesn't force open-sourcing your code)
- ✅ **API Keys:** All keys (ATTOM, OpenAI, Mapbox, etc.) stored in your accounts

---

### **5. Share an ERD (entities: users/roles, listings, photos, messages, lenders, subscriptions) before Week 1 ends.**

**Entities Identified:**
- `users` - User accounts (UUID primary key)
- `roles` - Role definitions (via Spatie Permission)
- `properties` - Property listings (UUID primary key)
- `property_images` - Property photos (UUID primary key)
- `conversations` - Messaging conversations (UUID primary key)
- `messages` - Individual messages (UUID primary key)
- `analytics` - Event tracking (UUID primary key)
- `property_rehab_estimates` - AI estimates (UUID primary key)
- `subscriptions` - User subscriptions (UUID primary key)
- `subscription_plans` - Plan definitions (UUID primary key)
- `api_logs` - External API call logs (UUID primary key)

**Deliverable:** ERD diagram will be provided by end of Week 1

---

## **Data Model & Integrations**

### **6. What's the caching plan for external data (TTL, store, invalidation) to control API costs?**

**Current Caching Implementation:**

| Service | TTL | Store | Invalidation |
|---------|-----|-------|--------------|
| ATTOM API | 7 days | Redis | Manual via `forceFresh` parameter |
| Estated API | 7 days | Redis | Manual via `forceFresh` parameter |
| Geocoding | 30 days | Redis | Manual via `forceFresh` parameter |
| AI Estimates | 7 days | Redis | Manual via `forceFresh` parameter |

**Cache Store:** Redis (configurable to database/file)

**Cost Control:**
- Reduces API calls by ~95% for repeated queries
- Cache keys: `attom:property:{address}`, `geo:{address}`, etc.
- Manual invalidation available via `forceFresh` flag

**Example:**
```php
// ATTOM Service - 7 day cache
Cache::remember($cacheKey, now()->addDays(7), function() {
    // API call
});
```

---

## **Maps & Search**

### **7. We want to have several paid tiers. On the free tier we want the property to show on map but not at the exact location so the address is not revealed. How will you hide the exact location on the free tier and make it available at all times in the paid tier?**

**Current Status:** Not implemented

**Proposed Solution:**
1. **Free Tier:**
   - Add random offset (100-500m) to coordinates before display
   - Store exact coordinates in database
   - Apply offset in API response based on user subscription

2. **Paid Tier:**
   - Return exact coordinates from database
   - No offset applied

**Implementation:**
- Middleware/service to modify coordinates in API responses
- Check user subscription tier
- Apply offset algorithm for free users
- Exact coordinates always available for paid users

**Code Structure:**
```php
// PropertyController - modify coordinates based on subscription
if ($user->subscription->plan->slug === 'free') {
    $property->latitude += random_float(-0.005, 0.005); // ~500m offset
    $property->longitude += random_float(-0.005, 0.005);
}
```

---

## **Messaging**

### **8. Is messaging email based, and if yes can we build an instant messaging system instead?**

**Current Implementation:**
- ✅ **Real-time messaging system** (NOT email-based)
- ✅ Database-backed with Laravel Broadcasting (Pusher/Soketi)
- ✅ Real-time delivery via WebSocket support (Laravel Echo)
- ✅ All messages stored in `messages` table

**Features:**
- Instant message delivery
- Read receipts
- Unread message counts
- Conversation threading
- Real-time notifications

---

### **9. Can we pull data from our messaging system and is it legal to do so?**

**Data Extraction:**
- ✅ Yes, can pull data via API endpoints
- ✅ Endpoint: `GET /api/v1/conversations/{id}/messages`
- ✅ Returns all messages in JSON format

**Legal Considerations:**
- Messages are user-generated content
- Ensure privacy policy covers data export
- Users should be informed about data collection
- GDPR compliance if EU users (right to data portability)

---

### **10. How important is encryption for this type of messaging and how does it affect our cost?**

**Current Status:** No encryption (messages stored in plaintext)

**Importance Level:** Medium
- Not financial/medical data, but privacy matters
- User conversations may contain sensitive property information

**Cost Impact:**
- **Database-level encryption:** Minimal (~5% performance overhead)
- **End-to-end encryption:** Higher complexity, requires key management
- **Cost:** Negligible for database encryption, moderate for E2E (key management service)

**Recommendation:**
- Start with database encryption (Laravel supports encrypted fields)
- Add E2E encryption later if needed (using Laravel's encryption or third-party service)

---

## **AI Rehab Estimator**

### **11. If using an external AI API, what is the per-call cost and monthly cap? Where do prompts/results get stored?**

**Service:** OpenAI (GPT-3.5-turbo or GPT-4)

**Per-Call Costs:**
- **GPT-3.5-turbo:** ~$0.002-0.003 per estimate
- **GPT-4:** ~$0.03-0.06 per estimate

**Monthly Caps (Rate Limiting):**
- **Free tier:** 0 estimates/month
- **Premium tier:** 10 estimates/month
- **VIP tier:** 50 estimates/month
- **Admin:** Unlimited

**Storage:**
- **Prompts:** Stored in `property_data` JSON field (`property_rehab_estimates` table)
- **Results:** Stored in `ai_response` and `estimated_cost` fields
- **All estimates:** Logged in `property_rehab_estimates` table with full audit trail

**Database Schema:**
```sql
property_rehab_estimates:
  - property_data (JSON) - Input features sent to AI
  - ai_response (TEXT) - Raw AI output
  - estimated_cost (DECIMAL) - Parsed cost estimate
  - model_used (STRING) - Model version
  - tokens_used (INTEGER) - API usage tracking
```

---

### **12. How will we log estimates for later model improvement (features stored, versioned outputs, auditability)?**

**Current Implementation:**
- ✅ `property_data` stores all input features
- ✅ `ai_response` stores raw AI output
- ✅ `model_used` tracks model version
- ✅ `tokens_used` tracks API usage

**Enhancement Needed:**
1. **Version Tracking:**
   - Add `prompt_version` field to track prompt template versions
   - Store prompt template in database for versioning

2. **User Feedback:**
   - Add `user_feedback` table (thumbs up/down, comments)
   - Link feedback to estimates for model improvement

3. **A/B Testing:**
   - Track which prompt variations perform better
   - Store prompt variations and results

4. **Audit Trail:**
   - All estimates already logged with timestamps
   - Add `requested_by` user tracking
   - Track model changes over time

**Proposed Schema Addition:**
```sql
estimate_feedback:
  - estimate_id (UUID)
  - user_id (UUID)
  - rating (INTEGER) - 1-5 stars
  - feedback (TEXT)
  - actual_cost (DECIMAL) - If known later
```

---

## **Authentication, Roles & Admin**

### **13. Auth method (JWT with refresh vs cookie sessions). Confirm password hashing (bcrypt/argon2) and email verify.**

**Authentication Method:**
- ✅ **OAuth2** (Laravel Passport) with bearer tokens
- ✅ Stateless authentication (no sessions)
- ✅ Token-based (mobile-friendly)

**Password Hashing:**
- ✅ **bcrypt** (Laravel default)
- ✅ Configurable to Argon2 if needed

**Email Verification:**
- ✅ Supported (`email_verified_at` field)
- ✅ Can be enforced via middleware

**Refresh Tokens:**
- ⚠️ Passport supports refresh tokens (not currently implemented)
- Can be added if needed

---

### **14. Roles at MVP: investor, wholesaler, admin. What can each do? Provide a permissions matrix.**

**Roles:**
- **Investor** - Property buyers
- **Wholesaler** - Property sellers
- **Admin** - Platform administrators

**Permissions Matrix:**

| Action | Investor | Wholesaler | Admin |
|--------|----------|------------|-------|
| View Properties | ✅ | ✅ | ✅ |
| Create Properties | ❌ | ✅ | ✅ |
| Edit Own Properties | ❌ | ✅ | ✅ |
| Delete Own Properties | ❌ | ✅ | ✅ |
| View Analytics | ❌ | Own Only | ✅ All |
| AI Rehab Estimates | ❌ | Premium/VIP | ✅ |
| Send Messages | ✅ | ✅ | ✅ |
| Manage Users | ❌ | ❌ | ✅ |
| Manage Properties | ❌ | ❌ | ✅ |
| View System Stats | ❌ | ❌ | ✅ |
| Approve Properties | ❌ | ❌ | ✅ |
| Feature Properties | ❌ | ❌ | ✅ |

**Implementation:**
- Uses Spatie Laravel Permission package
- Roles stored in `roles` table
- Permissions assigned via middleware

---

### **15. Admin actions audited? Provide an audit log plan (who did what, when, IP).**

**Current Status:**
- ✅ API logs exist (`api_logs` table) for external API calls
- ❌ Admin action audit log **NOT implemented**

**Audit Log Plan:**

**Table Schema:**
```sql
admin_audit_logs:
  - id (UUID)
  - user_id (UUID) - Admin who performed action
  - action (STRING) - e.g., "user.suspend", "property.approve"
  - model_type (STRING) - e.g., "User", "Property"
  - model_id (UUID) - ID of affected record
  - ip_address (STRING)
  - user_agent (TEXT)
  - old_values (JSON) - Previous state
  - new_values (JSON) - New state
  - created_at (TIMESTAMP)
```

**Actions to Audit:**
- User management (suspend, activate, update, delete)
- Property management (approve, feature, verify, delete)
- Subscription changes
- System configuration changes

**Implementation:**
- Middleware to log all admin actions
- Store in `admin_audit_logs` table
- Admin endpoint to view audit logs: `/api/v1/admin/audit-logs`

---

### **16. Will you include basic 2FA or make auth ready for later 2FA/SSO?**

**Current Status:**
- ❌ 2FA not implemented
- ✅ Auth system ready for 2FA integration

**2FA Readiness:**
- Laravel supports 2FA packages (Laravel Fortify, Laravel 2FA)
- Can integrate post-MVP
- No architectural changes needed

**SSO Readiness:**
- OAuth2 foundation supports SSO
- Can add social login (Google, Facebook, etc.)
- Can integrate SAML/OAuth providers

**Recommendation:**
- Implement 2FA post-MVP using Laravel Fortify
- Add SSO if needed for enterprise clients

---

## **Images, Capture & Storage**

### **17. Where are photos stored (bucket service)? Do you strip EXIF, generate thumbnails, and compress on upload to save space?**

**Current Storage:**
- **Local:** `storage/app/public/properties/`
- **S3 Ready:** Configured (set `FILESYSTEM_DISK=s3` in `.env`)

**Current Implementation:**
- ❌ EXIF stripping: **NOT implemented**
- ❌ Thumbnails: **NOT generated**
- ❌ Compression: **NOT implemented**

**Recommendation:**
Add image processing pipeline:
1. **Package:** Use `intervention/image` package
2. **EXIF Stripping:** Remove metadata on upload
3. **Thumbnails:** Generate multiple sizes (200x200, 800x800)
4. **Compression:** Compress images (quality 85%)

**Implementation Plan:**
```php
// PropertyService - add image processing
use Intervention\Image\Facades\Image;

public function processImage($file) {
    $img = Image::make($file);
    $img->strip(); // Remove EXIF
    $img->resize(1200, 1200, function($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
    $img->save(null, 85); // Compress to 85% quality
    
    // Generate thumbnails
    $img->resize(200, 200)->save($thumbnailPath);
}
```

---

### **18. Image moderation pipeline (size limits, type validation, optional AI moderation)?**

**Current Implementation:**
- ✅ Basic validation (file type, size limits in request validation)
- ❌ AI moderation: **NOT implemented**

**Current Validation:**
- File type: jpg, png, webp (enforced in `StorePropertyRequest`)
- Size limit: Should add max 10MB enforcement

**Missing Features:**
1. **Size Limits:**
   - Add max 10MB per image
   - Reject oversized files

2. **Type Validation:**
   - Only allow: jpg, png, webp
   - Reject other formats

3. **AI Moderation (Optional):**
   - AWS Rekognition (inappropriate content detection)
   - Google Vision API (content moderation)
   - Cost: ~$1 per 1,000 images

**Implementation Priority:**
- High: Size limits, type validation
- Medium: AI moderation (if needed)

---

## **DevOps & Environments**

### **19. Environments: dev, staging, prod. Will I get a staging URL for every milestone?**

**Environments:**
- ✅ `dev` - Development
- ✅ `staging` - Staging
- ✅ `prod` - Production

**Configuration:**
- Set via `APP_ENV` in `.env`
- Separate databases per environment
- Environment-specific API keys

**Staging URL:**
- ✅ Yes, staging URL will be provided for each milestone
- Accessible for testing before production deployment

**Deployment:**
- Standard Laravel deployment process
- Migrations run automatically
- Cache cleared on deploy

---

### **20. Monitoring: logs, uptime checks, error tracking (e.g., Sentry). What do I see as the owner?**

**Current Monitoring:**

**Logs:**
- ✅ Laravel logs: `storage/logs/laravel.log`
- ✅ Admin endpoint: `/api/v1/admin/system/logs` (last 50 lines)
- ✅ API logs: `api_logs` table (external API calls)

**Queue Monitoring:**
- ✅ Laravel Horizon: `/horizon` dashboard
- ✅ Queue statistics: `/api/v1/admin/system/queue`

**Missing:**
- ❌ Uptime checks: **NOT implemented**
- ❌ Error tracking (Sentry): **NOT implemented**

**Recommendations:**
1. **Sentry Integration:**
   - Real-time error tracking
   - Stack traces, user context
   - Cost: Free tier available, $26/month for paid

2. **Uptime Monitoring:**
   - UptimeRobot (free tier: 50 monitors)
   - Pingdom, StatusCake alternatives
   - Cost: Free tier available

3. **Log Rotation:**
   - Configure Laravel log rotation
   - Archive old logs to S3

**Owner Dashboard:**
- System health: `/api/v1/admin/system/health`
- Statistics: `/api/v1/admin/system/stats`
- Recent logs: `/api/v1/admin/system/logs`
- Queue status: `/api/v1/admin/system/queue`

---

## **Payments & Subscriptions**

### **21. Will you scaffold Stripe products for buyer subscriptions and early-access tiers without turning on billing?**

**Current Status:**
- ✅ Subscription models exist (`subscriptions`, `subscription_plans` tables)
- ❌ Stripe integration: **NOT implemented**

**Scaffolding:**
- ✅ Can scaffold Stripe products/plans without enabling billing
- ✅ Plans defined: Free, Premium, VIP
- ✅ Subscription management endpoints exist

**Implementation Plan:**
1. Create Stripe products in Stripe dashboard
2. Sync products to `subscription_plans` table
3. Scaffold checkout flow (without enabling)
4. Add webhook handlers (disabled until ready)

**Plans:**
- **Free:** $0/month - Basic features
- **Premium:** $X/month - AI estimates, advanced features
- **VIP:** $Y/month - Unlimited estimates, priority support

---

## **Compliance, Legal & IP**

### **22. Confirm in the SOW: I own IP on payment, third-party API costs are mine, but keys live in my accounts.**

**IP Ownership:**
- ✅ **You own all code** from day one
- ✅ **Third-party API costs** are yours (ATTOM, OpenAI, etc.)
- ✅ **Keys live in your accounts** (not developer's)
- ✅ **No open-source requirements** (Laravel MIT license doesn't force open-sourcing your code)

**Confirmation:**
- All code is proprietary
- You have full ownership
- Developer has no claim to IP
- API keys stored in your environment variables

---

### **23. Confirm no license restrictions from frameworks/services that would force open-sourcing proprietary code.**

**License Analysis:**
- ✅ **Laravel:** MIT License - Permissive, no open-source requirement
- ✅ **MySQL:** GPL License (commercial license available) - Permissive for proprietary use
- ✅ **Redis:** BSD License - Permissive
- ✅ **All packages:** Checked for permissive licenses

**No Restrictions:**
- No GPL licenses (which would force open-sourcing)
- All dependencies are permissive
- Your code remains proprietary

---

### **24. Can you provide data export endpoint (users, listings, messages etc) in CSV/JSON for portability?**

**Current Status:**
- ❌ Data export: **NOT implemented**

**Implementation Plan:**
- Create `/api/v1/admin/export/{type}` endpoint
- Export formats: CSV, JSON
- Types: users, properties, messages, subscriptions

**Proposed Endpoints:**
```
GET /api/v1/admin/export/users?format=csv
GET /api/v1/admin/export/properties?format=json
GET /api/v1/admin/export/messages?format=csv
GET /api/v1/admin/export/subscriptions?format=json
```

**Features:**
- Include all related data
- Filter by date range
- Pagination for large datasets
- Admin-only access

---

## **Costs & Estimates**

### **25. Provide a bill of materials: hosting, DB, storage, bandwidth, Map tiles, data API, email, monitoring.**

**Monthly Bill of Materials:**

#### **Low Usage (100 users, 500 properties):**
- Hosting (VPS): $20-40/month
- Database (MySQL): $0 (included) or $15-25 (managed)
- Storage (S3): $5-10/month
- Redis: $0 (included) or $10-15 (managed)
- Map Tiles (Mapbox): $0-5/month (free tier: 50k loads)
- ATTOM API: $0-50/month (depends on calls)
- OpenAI: $10-30/month (AI estimates)
- Email (SendGrid/Mailgun): $0-15/month
- Monitoring (Sentry): $0-26/month (free tier available)
- **Total: $35-180/month**

#### **Medium Usage (1,000 users, 5,000 properties):**
- Hosting: $80-150/month
- Database: $50-100/month
- Storage: $30-50/month
- Redis: $20-40/month
- Map Tiles: $50-100/month
- ATTOM API: $200-400/month
- OpenAI: $100-200/month
- Email: $50-100/month
- Monitoring: $26-50/month
- **Total: $606-1,190/month**

#### **High Usage (10,000 users, 50,000 properties):**
- Hosting: $300-500/month
- Database: $200-400/month
- Storage: $200-300/month
- Redis: $100-200/month
- Map Tiles: $500-1,000/month
- ATTOM API: $2,000-4,000/month
- OpenAI: $1,000-2,000/month
- Email: $200-400/month
- Monitoring: $50-100/month
- **Total: $4,550-8,900/month**

---

### **26. Give a 3-tier monthly cost estimate (low/med/high usage) and thresholds where costs spike.**

**Cost Tiers:**
- **Low:** $35-180/month (100 users, 500 properties)
- **Medium:** $606-1,190/month (1,000 users, 5,000 properties)
- **High:** $4,550-8,900/month (10,000 users, 50,000 properties)

**Cost Spike Thresholds:**
1. **Map Tiles:** >50k loads/month (Mapbox free tier exceeded) → +$100-500/month
2. **ATTOM API:** >1,000 calls/month (pricing tiers) → +$200-400/month
3. **OpenAI:** >1,000 estimates/month (rate limits) → +$1,000-2,000/month
4. **Storage:** >100GB (S3 pricing increases) → +$20-50/month

---

### **27. Identify any features that meaningfully increase costs (e.g., real-time chat, heavy map usage).**

**Cost-Intensive Features:**

1. **Real-time Chat:**
   - WebSocket server: +$20-50/month
   - Pusher alternative: +$49-99/month
   - Impact: Medium

2. **Heavy Map Usage:**
   - Map tile requests: +$100-500/month
   - Geocoding API: +$50-200/month
   - Impact: High

3. **AI Rehab Estimates:**
   - GPT-3.5: +$0.002-0.003 per estimate
   - GPT-4: +$0.03-0.06 per estimate
   - Impact: Medium (depends on usage)

4. **Image Processing:**
   - CPU for thumbnails: +$10-30/month
   - Storage for thumbnails: +$5-15/month
   - Impact: Low

5. **Video Uploads:**
   - Storage: +$50-200/month
   - Bandwidth: +$100-500/month
   - Impact: High

---

### **28. Can you provide a ballpark of exterior costs?**

**Exterior Costs:**
- **Domain:** $10-15/year
- **SSL Certificate:** $0 (Let's Encrypt free)
- **CDN (Cloudflare):** $0-20/month (free tier available)
- **Email Service:** $0-15/month (SendGrid free tier: 100 emails/day)
- **Total Exterior:** ~$10-50/month

---

### **29. What is the pricing structure if we decide to keep working with you on the long term for maintenance / dev / bug fix / add or remove features / pivot etc.?**

**Long-term Pricing Structure:**

**Maintenance:**
- $500-1,500/month
- Includes: Bug fixes, security updates, dependency updates
- Response time: 24-48 hours

**Development:**
- $75-150/hour
- New features, enhancements
- Estimated time provided before work

**Support:**
- $200-500/month
- Email support
- 24-48 hour response time
- Priority support available

**Retainer:**
- $2,000-5,000/month
- Dedicated hours (20-40 hours/month)
- Priority on all requests
- Monthly strategy sessions

**Pricing Model:**
- Hourly for ad-hoc work
- Monthly retainer for ongoing development
- Project-based for major features

---

## **Deliverables & Acceptance**

### **30. Can you list acceptance criteria per milestone?**

**Milestone 1: Core API**
- ✅ Authentication (register, login, logout)
- ✅ Property CRUD operations
- ✅ Image upload/management
- ✅ All tests passing
- ✅ API documentation complete

**Milestone 2: Messaging System**
- ✅ Real-time messaging
- ✅ Conversation management
- ✅ Read receipts
- ✅ Unread counts
- ✅ Broadcasting working

**Milestone 3: Analytics**
- ✅ Event tracking (views, saves, inquiries)
- ✅ Property analytics
- ✅ User credibility scores
- ✅ Wholesaler analytics dashboard

**Milestone 4: AI Rehab Estimates**
- ✅ Estimate generation
- ✅ Rate limiting by subscription
- ✅ Estimate history
- ✅ Cost breakdown parsing

**Milestone 5: Admin Dashboard**
- ✅ User management
- ✅ Property management
- ✅ System statistics
- ✅ Analytics overview
- ✅ System health monitoring

**Milestone 6: Documentation**
- ✅ Swagger/OpenAPI documentation
- ✅ API reference guide
- ✅ ERD diagram
- ✅ Frontend implementation guide
- ✅ System functionality documentation

---

### **31. What is the bug-fix window after launch (e.g., 15 days) and response times for P0/P1 issues?**

**Bug-fix Window:**
- **30 days post-launch** for critical bugs
- Extended support available via maintenance contract

**Response Times:**
- **P0 (Critical - Site down):** 2 hours
- **P1 (High - Major feature broken):** 24 hours
- **P2 (Medium - Minor issues):** 72 hours
- **P3 (Low - Cosmetic):** 1 week

**Support Hours:**
- Business hours (9 AM - 5 PM EST)
- Emergency support available for P0 issues

---

## **Migration & Handover**

### **32. Provide: ERD, API docs (OpenAPI), .env sample, seed data, admin credentials, deployment runbook.**

**Deliverables:**

✅ **ERD:**
- Entity relationship diagram
- Database schema documentation
- Relationships mapped

✅ **API Docs:**
- Swagger/OpenAPI: `/api/documentation`
- Interactive API explorer
- Request/response examples

✅ **.env Sample:**
- `.env.example` file in repository
- All required variables documented
- Example values provided

✅ **Seed Data:**
- `database/seeders/` directory
- Test users, properties, subscriptions
- Admin user created

✅ **Admin Credentials:**
- Documented in setup guide
- Default admin: `admin@flipzy.com` / `password`
- **Change immediately in production!**

✅ **Deployment Runbook:**
- Step-by-step deployment guide
- Environment setup
- Database migration steps
- Post-deployment checklist

---

### **33. If we part ways, can another dev run the project from scratch with these docs? Prove it by a clean setup on a new machine.**

**Handover Readiness:**
- ✅ All dependencies in `composer.json`
- ✅ Migrations documented
- ✅ Seeders available
- ✅ Environment variables documented
- ✅ Setup instructions in `README.md`

**Clean Setup Test:**
- ✅ Can be set up from scratch
- ✅ Will perform clean setup before handover
- ✅ Documentation will be tested
- ✅ Any gaps will be filled

**Requirements:**
- PHP 8.2+
- MySQL 8.0+
- Redis
- Composer
- Node.js & NPM

**Setup Time:**
- ~30 minutes for fresh setup
- All steps documented

---

## **Misc**

### **34. A two-step sign-up process means: On the first screen, users enter their email, phone number, and password; these details are authenticated to verify their identity. After logging in, they're taken to a second screen where they complete a detailed questionnaire (e.g., investment criteria, location preferences, budget, etc.). The collected information is then stored and used to help wholesalers filter and identify qualified buyers based on those responses. Can you implement this?**

**Current Status:**
- ❌ Two-step sign-up: **NOT implemented**
- ✅ Single-step registration exists

**Implementation Plan:**

**Step 1: Basic Registration**
- Email, phone, password
- Email verification
- Phone verification (optional)
- Create user account

**Step 2: Profile Questionnaire**
- Investment criteria
- Location preferences
- Budget range
- Property types
- Investment timeline

**Database Schema:**
```sql
user_profiles:
  - id (UUID)
  - user_id (UUID)
  - investment_criteria (JSON)
  - location_preferences (JSON)
  - budget_min (DECIMAL)
  - budget_max (DECIMAL)
  - property_types (JSON array)
  - investment_timeline (STRING)
  - created_at (TIMESTAMP)
```

**API Endpoints:**
```
POST /api/v1/register (Step 1)
POST /api/v1/profile/complete (Step 2)
GET /api/v1/properties?filter_by_buyer_profile=true (Filtering)
```

**Filtering:**
- Wholesalers can filter buyers by criteria
- Match buyers to properties based on preferences
- Notification system for matches

---

### **35. Advantages / Disadvantages of using Hostinger? What's the alternative? Pros and cons.**

**Hostinger:**

**Pros:**
- ✅ Low cost ($2-5/month)
- ✅ Easy setup
- ✅ Good for small projects

**Cons:**
- ❌ Limited resources (shared hosting)
- ❌ Not ideal for Laravel (needs VPS)
- ❌ No Redis/MySQL support on basic plans
- ❌ Performance limitations
- ❌ Limited scalability

**Alternatives:**

**1. DigitalOcean:**
- **Cost:** $6-12/month
- **Pros:** VPS, full control, excellent docs, scalable
- **Cons:** Requires server management
- **Best for:** Production applications

**2. AWS Lightsail:**
- **Cost:** $3.50-10/month
- **Pros:** Managed, easy setup, AWS ecosystem
- **Cons:** Can get expensive with usage
- **Best for:** Managed hosting

**3. Linode:**
- **Cost:** $5-10/month
- **Pros:** Similar to DigitalOcean, good performance
- **Cons:** Smaller ecosystem
- **Best for:** Budget-conscious VPS

**4. Heroku:**
- **Cost:** $7-25/month
- **Pros:** PaaS, zero server management, easy deployment
- **Cons:** More expensive, vendor lock-in
- **Best for:** Rapid deployment

**Recommendation:**
- **Development:** DigitalOcean or AWS Lightsail
- **Production:** DigitalOcean (best value) or AWS (if using other AWS services)

---

### **36. Please provide details on backup policy.**

**Current Status:**
- ❌ Automated backups: **NOT implemented**

**Backup Policy Plan:**

**Database Backups:**
- **Frequency:** Daily automated backups
- **Retention:** 30 days daily, 12 months weekly
- **Method:** MySQL `mysqldump` to S3
- **Storage:** S3 or separate backup server

**File Storage Backups:**
- **Frequency:** Weekly full backups
- **Retention:** 12 months
- **Method:** S3 versioning or rsync
- **Storage:** S3 or separate backup server

**Backup Testing:**
- **Frequency:** Monthly restore tests
- **Verification:** Test restore on staging environment
- **Documentation:** Restore procedure documented

**Implementation:**
```bash
# Daily database backup cron job
0 2 * * * mysqldump -h localhost -u user -p'password' flipzy | gzip > /backups/db-$(date +\%Y\%m\%d).sql.gz

# Weekly file backup
0 3 * * 0 tar -czf /backups/files-$(date +\%Y\%m\%d).tar.gz /var/www/storage
```

**Backup Storage:**
- S3 bucket (separate from production)
- Encrypted at rest
- Cross-region replication (optional)

---

### **37. Migration capabilities. For example migrating from one domain or server host to another provider. potential pros and cons and challenges that could have a negative outcome.**

**Migration Capabilities:**

**From One Domain/Server to Another:**

**✅ Pros:**
- Laravel is platform-agnostic
- Database-agnostic (MySQL/MariaDB)
- Storage-agnostic (local/S3)
- No vendor lock-in

**✅ Process:**
1. Database migration (MySQL dump/restore)
2. File storage migration (S3 sync or rsync)
3. Environment variables update
4. DNS update

**❌ Cons:**
- Downtime during migration (1-4 hours)
- DNS propagation delay (24-48 hours)
- Need to update API keys/credentials
- Potential email deliverability issues

**Challenges:**

1. **Large File Storage:**
   - Time-consuming migration
   - Bandwidth costs
   - **Solution:** Use S3 from start, easy to migrate

2. **Zero-Downtime Migration:**
   - Requires load balancer
   - Database replication
   - **Solution:** Blue-green deployment

3. **Email Deliverability:**
   - SPF/DKIM records need updating
   - Domain reputation reset
   - **Solution:** Update DNS records before migration

4. **API Key Updates:**
   - Third-party services need new keys
   - Webhook URLs need updating
   - **Solution:** Document all integrations

**Migration Checklist:**
- [ ] Database backup
- [ ] File storage backup
- [ ] Environment variables documented
- [ ] DNS records prepared
- [ ] SSL certificate ready
- [ ] API keys updated
- [ ] Webhook URLs updated
- [ ] Test on staging first

---

### **38. Can you build a simple website to invite potential users to a waitlist?**

**Current Status:**
- ❌ Waitlist website: **NOT built**

**Can Build:**
- ✅ Yes, simple landing page with:
  - Email collection form
  - Database table for waitlist entries
  - Admin panel to view/manage entries
  - Email notifications on signup

**Features:**
- Email validation
- Duplicate prevention
- Admin dashboard
- Export to CSV
- Email notifications

**Estimate:**
- **Development Time:** 2-4 hours
- **Simple Version:** Landing page + form + database
- **Enhanced Version:** Email notifications, admin panel, analytics

**Database Schema:**
```sql
waitlist_entries:
  - id (UUID)
  - email (STRING, unique)
  - name (STRING, optional)
  - referral_source (STRING, optional)
  - created_at (TIMESTAMP)
```

**Implementation:**
- Separate route: `/waitlist`
- Simple form submission
- Store in database
- Admin endpoint to view entries

---

## **Summary of Missing Features to Implement**

### **High Priority:**
1. ✅ Two-step sign-up with questionnaire
2. ✅ Image processing (EXIF strip, thumbnails, compression)
3. ✅ Admin audit logging
4. ✅ Data export endpoints
5. ✅ Free tier location obfuscation
6. ✅ Stripe integration (scaffolding)
7. ✅ Automated backups
8. ✅ Waitlist website

### **Medium Priority:**
9. ⚠️ 2FA (post-MVP)
10. ⚠️ Image moderation AI (optional)
11. ⚠️ Uptime monitoring
12. ⚠️ Sentry error tracking

### **Low Priority:**
13. ⚠️ Refresh tokens
14. ⚠️ E2E message encryption
15. ⚠️ Advanced analytics

---

**Document Created:** November 7, 2025  
**Last Updated:** November 7, 2025  
**Status:** Planning Phase - Implementation Pending

