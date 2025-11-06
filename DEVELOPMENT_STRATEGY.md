# Flipzy Backend Development Strategy

## Project Overview
**Flipzy** is a property management platform connecting investors and wholesalers for property flipping. This Laravel 12 backend will power the entire ecosystem.

---

## Architecture Principles

### 1. **Service-Oriented Architecture**
- All business logic in Service classes (e.g., `PropertyService`, `AttomService`)
- Controllers remain thin, delegating to services
- Services handle validation, API calls, and data transformation

### 2. **Database Strategy**
- **PostgreSQL** for primary data (production-ready, JSON support)
- **Redis** for caching, queues, and real-time features
- **File Storage** for images (S3 in production, local for development)

### 3. **API Design**
- RESTful endpoints with consistent response format
- API versioning (v1) for future compatibility
- Rate limiting and authentication on all endpoints
- Comprehensive error handling

### 4. **Real-time Communication**
- Laravel Broadcasting with Pusher/Soketi for real-time messaging
- WebSocket connections for live chat
- Event-driven architecture for notifications

---

## Refined Development Phases

### **PHASE 1: Foundation & Authentication** (Priority: CRITICAL)
**Goal:** Establish base infrastructure and secure authentication

**Tasks:**
1. ✅ Verify Laravel 12 installation
2. ⏳ Install Laravel Passport for OAuth2 API authentication
3. ⏳ Install Spatie Laravel Permission for roles & permissions
4. ⏳ Configure PostgreSQL connection
5. ⏳ Set up Redis for cache/queues
6. ⏳ Create roles and permissions (Investor, Wholesaler, Admin)
7. ⏳ Create API authentication endpoints (register, login, logout)
8. ⏳ Set up .env with service placeholders

**Deliverables:**
- Working OAuth2 authentication system
- Role & permission-based access control via Spatie
- API endpoints protected by Passport

---

### **PHASE 2: Core Database Schema** (Priority: CRITICAL)
**Goal:** Design and implement database structure

**Tasks:**
1. ⏳ Create migrations for:
   - `users` (extend existing)
   - `properties` (core table)
   - `property_images` (one-to-many)
   - `conversations` (messaging)
   - `messages` (messaging)
   - `analytics` (property views, saves, inquiries)
   - `subscriptions` (billing)
   - `subscription_plans` (plan definitions)
   - `property_rehab_estimates` (AI feature)
   - `api_logs` (external API tracking)
2. ⏳ Define Eloquent models with relationships
3. ⏳ Create factories and seeders for testing
4. ⏳ Set up database indexes for performance

**Key Relationships:**
- `User` hasMany `Property` (as wholesaler)
- `User` hasMany `Message` (as sender/receiver)
- `Property` belongsTo `User` (wholesaler)
- `Property` hasMany `PropertyImage`
- `Property` hasMany `Analytic`
- `User` hasOne `Subscription`

**Deliverables:**
- Complete database schema
- Working relationships
- Seed data for development

---

### **PHASE 3: Property CRUD Operations** (Priority: HIGH)
**Goal:** Core property management functionality

**Tasks:**
1. ⏳ Create `PropertyController` with full CRUD
2. ⏳ Build `PropertyService` for business logic
3. ⏳ Implement `PropertyRequest` validation classes
4. ⏳ Create `PropertyResource` for API responses
5. ⏳ Add image upload handling (S3/local)
6. ⏳ Implement filtering (city, price, type, status, beds, baths)
7. ⏳ Add pagination
8. ⏳ Create search functionality (basic, expand to Meilisearch later)

**Endpoints:**
- `POST /api/v1/properties` - Create property
- `GET /api/v1/properties` - List with filters
- `GET /api/v1/properties/{id}` - View single
- `PUT /api/v1/properties/{id}` - Update
- `DELETE /api/v1/properties/{id}` - Delete
- `POST /api/v1/properties/{id}/images` - Upload images

**Deliverables:**
- Full property CRUD
- Image upload working
- Filtered listing with pagination

---

### **PHASE 4: External API Integration** (Priority: HIGH)
**Goal:** Auto-enrich properties from external sources

**Tasks:**
1. ⏳ Create service classes:
   - `AttomService` - Primary property data
   - `EstatedService` - Backup property data
   - `GeoService` - Address geocoding (OpenStreetMap/Mapbox)
2. ⏳ Build `PropertyEnrichmentService` (orchestrator)
3. ⏳ Create jobs for async API calls
4. ⏳ Implement rate limiting and caching
5. ⏳ Add error handling and fallback logic
6. ⏳ Create `ApiLog` model for tracking calls
7. ⏳ Build endpoint: `POST /api/v1/properties/{id}/enrich`

**API Integration Strategy:**
- Try ATTOM first (most comprehensive)
- Fallback to Estated if ATTOM fails
- Always geocode address for coordinates
- Cache API responses to reduce calls
- Queue heavy operations

**Deliverables:**
- Auto-enrichment working
- Property data populated from APIs
- Error handling and logging

---

### **PHASE 5: Real-time Messaging** (Priority: MEDIUM)
**Goal:** Enable investor-wholesaler communication

**Tasks:**
1. ⏳ Set up Laravel Broadcasting (Pusher or Soketi)
2. ⏳ Create `Conversation` and `Message` models
3. ⏳ Build `MessageController` and `MessageService`
4. ⏳ Implement WebSocket events
5. ⏳ Add read/unread tracking
6. ⏳ Create notification system
7. ⏳ Build message endpoints

**Endpoints:**
- `GET /api/v1/conversations` - List conversations
- `GET /api/v1/conversations/{id}/messages` - Get messages
- `POST /api/v1/conversations/{id}/messages` - Send message
- `PUT /api/v1/messages/{id}/read` - Mark as read

**Deliverables:**
- Real-time chat working
- Messages persisted
- Read receipts functional

---

### **PHASE 6: Analytics & Credibility System** (Priority: MEDIUM)
**Goal:** Track engagement and build trust scores

**Tasks:**
1. ⏳ Create `AnalyticsService`
2. ⏳ Track events: views, saves, inquiries
3. ⏳ Build credibility scoring algorithm
4. ⏳ Create analytics endpoints
5. ⏳ Add dashboard aggregation queries
6. ⏳ Implement caching for analytics

**Credibility Formula:**
```
score = (views * 0.3) + (saves * 0.5) + (inquiries * 0.2)
normalized_score = (score / max_possible_score) * 100
```

**Endpoints:**
- `POST /api/v1/properties/{id}/view` - Track view
- `POST /api/v1/properties/{id}/save` - Track save
- `POST /api/v1/properties/{id}/inquiry` - Track inquiry
- `GET /api/v1/users/{id}/credibility` - Get credibility score

**Deliverables:**
- Analytics tracking working
- Credibility scores calculated
- Dashboard-ready data

---

### **PHASE 7: Subscription & Billing** (Priority: FUTURE)
**Goal:** Implement tiered access with Stripe (Deferred to future)

**Note:** Database structure will be prepared, but implementation deferred.

**Future Tasks:**
1. Install Laravel Cashier (Stripe)
2. Create subscription plans (Free, Premium, VIP)
3. Build `SubscriptionService`
4. Implement subscription endpoints
5. Create middleware for tier checks
6. Set up Stripe webhooks

---

### **PHASE 8: AI Rehab Estimation** (Priority: LOW)
**Goal:** Optional AI-powered cost estimation

**Tasks:**
1. ⏳ Create `RehabEstimateService` using OpenAI
2. ⏳ Build prompt engineering for property details
3. ⏳ Store estimates in database
4. ⏳ Add caching for repeated requests
5. ⏳ Implement rate limiting (premium feature)
6. ⏳ Create estimation endpoint

**Endpoint:**
- `POST /api/v1/properties/{id}/estimate` - Generate estimate

**Deliverables:**
- AI estimation working
- Estimates stored and cached
- Premium-tier access enforced

---

### **PHASE 9: Admin Dashboard** (Priority: MEDIUM)
**Goal:** Admin control panel and monitoring

**Tasks:**
1. ⏳ Create admin middleware and routes
2. ⏳ Build admin controllers:
   - `AdminUserController`
   - `AdminPropertyController`
   - `AdminSubscriptionController`
   - `AdminAnalyticsController`
3. ⏳ Install Laravel Horizon for queue monitoring
4. ⏳ Add error logging and performance tracking
5. ⏳ Create admin dashboard endpoints

**Endpoints:**
- `GET /api/v1/admin/users` - Manage users
- `GET /api/v1/admin/properties` - Manage properties
- `GET /api/v1/admin/analytics` - System analytics
- `GET /api/v1/admin/subscriptions` - Subscription overview

**Deliverables:**
- Admin panel functional
- Queue monitoring active
- System health tracking

---

### **PHASE 10: Testing & Optimization** (Priority: CRITICAL)
**Goal:** Production-ready backend

**Tasks:**
1. ⏳ Write unit tests for services
2. ⏳ Write feature tests for endpoints
3. ⏳ Set up CI/CD pipeline
4. ⏳ Configure production environment
5. ⏳ Optimize database queries
6. ⏳ Add caching strategies
7. ⏳ Performance testing
8. ⏳ Security audit

**Deliverables:**
- Test coverage > 80%
- CI/CD pipeline active
- Production deployment ready

---

## Additional Enhancements (Post-MVP)

1. **Advanced Search:** Meilisearch/Elasticsearch integration
2. **Scheduled Jobs:** Nightly data refresh from ATTOM
3. **Email Notifications:** Property alerts, message notifications
4. **API Documentation:** Laravel API Resources + Swagger/OpenAPI
5. **File Storage:** S3 integration for production
6. **Rate Limiting:** Advanced rate limiting per user tier
7. **Audit Logging:** Track all important actions
8. **Multi-tenancy:** Support for multiple markets/regions

---

## Technology Stack

### Core
- **Framework:** Laravel 12
- **Database:** PostgreSQL 15+
- **Cache/Queue:** Redis 7+
- **PHP:** 8.2+

### Services
- **Authentication:** Laravel Passport (OAuth2)
- **Roles & Permissions:** Spatie Laravel Permission
- **Real-time:** Laravel Broadcasting + Pusher/Soketi
- **Billing:** Laravel Cashier (Stripe) - Future implementation
- **AI:** OpenAI API
- **External APIs:** ATTOM, Estated, OpenStreetMap/Mapbox

### Development
- **Testing:** PHPUnit/Pest
- **Queue Monitor:** Laravel Horizon
- **Code Quality:** Laravel Pint
- **Logging:** Laravel Pail

---

## Development Workflow

1. **Start with Phase 1** - Foundation must be solid
2. **Complete each phase** before moving to next
3. **Test as you go** - Don't wait until Phase 10
4. **Commit frequently** - Small, logical commits
5. **Document APIs** - Keep API docs updated

---

## Next Steps

We'll start with **PHASE 1** immediately, setting up:
1. Authentication system (Sanctum)
2. Database configuration (PostgreSQL)
3. Redis setup
4. Role-based access control
5. Basic API structure

Let's begin! 🚀

