# Endpoint Analysis Report

## Summary
This document compares the required endpoints from the frontend team with the existing API endpoints in the backend.

---

## ✅ **PUBLIC ENDPOINTS**

### 1. Get All properties (with filters)
**Status:** ✅ **EXISTS**
- **Route:** `GET /api/v1/properties`
- **Controller:** `PropertyController@index`
- **Filters Supported:** city, state, property_type, status, min_price, max_price, bedrooms, bathrooms, featured, verified, search, sort_by, sort_order
- **Location:** `routes/api.php:30`

### 2. Background verification ID
**Status:** ❌ **MISSING**
- **Required:** Endpoint to handle background verification ID submission/checking
- **Action Needed:** Create new endpoint

### 3. Newsletter subscription (email string param)
**Status:** ❌ **MISSING**
- **Required:** Public endpoint to subscribe to newsletter with email parameter
- **Note:** Found email campaign functionality in admin, but no public subscription endpoint
- **Action Needed:** Create new endpoint

---

## 👤 **INVESTOR ENDPOINTS**

### 1. Add to wishlist
**Status:** ❌ **MISSING**
- **Required:** Endpoint for investors to add properties to wishlist
- **Action Needed:** 
  - Create wishlist migration/model
  - Create wishlist controller
  - Add route: `POST /api/v1/properties/{property}/wishlist` (or similar)

### 2. Similar properties to the one clicked
**Status:** ❌ **MISSING**
- **Required:** Endpoint to get similar properties based on a property ID
- **Action Needed:** 
  - Create endpoint: `GET /api/v1/properties/{property}/similar`
  - Implement similarity algorithm (location, price range, property type, etc.)

### 3. Buy box matches (compare investor buy box with existing properties in system)
**Status:** ❌ **MISSING**
- **Required:** Endpoint to find properties that match investor's buy box criteria
- **Note:** Buy box model and controller exist, but no matching endpoint
- **Action Needed:** 
  - Create endpoint: `GET /api/v1/properties/matches` or `GET /api/v1/buy-box/matches`
  - Implement matching algorithm comparing buy box criteria with properties

### 4. Update profile data (add photo, change name, phone number)
**Status:** ❌ **MISSING**
- **Required:** Endpoint for investors to update their own profile
- **Note:** Only admin can update users currently (`AdminUserController@update`)
- **Action Needed:** 
  - Create endpoint: `PUT /api/v1/profile` or `PUT /api/v1/user/profile`
  - Support: name, phone_number, photo upload
  - Add to `AuthController` or create `ProfileController`

---

## 🏢 **WHOLESALER ENDPOINTS**

### 1. Dropdown to suggest addresses when adding a property (using google maps or another provider)
**Status:** ⚠️ **PARTIAL**
- **Current:** `GET /api/v1/properties/search/address` exists for address lookup
- **Issue:** This is a full address lookup, not an autocomplete/suggestion endpoint
- **Action Needed:** 
  - Create new endpoint: `GET /api/v1/properties/address/autocomplete?query=...`
  - Integrate Google Maps Places API or similar for autocomplete suggestions
  - Current `lookup` endpoint returns full property data, not suggestions

### 2. AI Rehab to be calculated before property is being submitted
**Status:** ⚠️ **PARTIAL**
- **Current:** `POST /api/v1/properties/{property}/estimate` exists for rehab estimates
- **Issue:** This requires a property to already exist (needs property ID)
- **Action Needed:** 
  - Create endpoint: `POST /api/v1/properties/preview/rehab-estimate` or modify existing preview endpoint
  - Allow rehab calculation before property creation
  - May need to use `POST /api/v1/properties/search/preview` and enhance it

### 3. Add investor manually (not with role, only for the crm) with their buy box and contact form
**Status:** ❌ **MISSING**
- **Required:** Endpoint for wholesalers/admins to add investors to CRM without creating full user accounts
- **Action Needed:** 
  - Create CRM investor model (separate from User model, or use a flag)
  - Create endpoint: `POST /api/v1/crm/investors`
  - Support: contact info + buy box data
  - May need new table: `crm_investors` or add `is_crm_only` flag to users

### 4. Property Investor matches (check the added properties with investors on the system and see the match based on investor buy box)
**Status:** ❌ **MISSING**
- **Required:** Endpoint for wholesalers to see which investors match their properties
- **Action Needed:** 
  - Create endpoint: `GET /api/v1/properties/{property}/investor-matches`
  - Implement matching algorithm (similar to buy box matches but from property perspective)
  - Return list of matching investors with match scores

### 5. Fields coming from ATTOM api should match fields in the Property entity
**Status:** ⚠️ **DATA MAPPING ISSUE** (Not an endpoint)
- **Current:** ATTOM service exists (`AttomService`, `AttomDataMapper`)
- **Action Needed:** 
  - Review `AttomDataMapper` class
  - Ensure all ATTOM fields are properly mapped to Property model fields
  - This is a data integrity issue, not a missing endpoint

### 6. Update profile data (photo, change phone number)
**Status:** ❌ **MISSING**
- **Required:** Same as investor profile update
- **Action Needed:** Same as Investor endpoint #4 (can be shared endpoint)

---

## 📋 **RECOMMENDATIONS**

### High Priority (Core Functionality)
1. **Profile Update Endpoint** - Needed by both investors and wholesalers
2. **Buy Box Matches** - Core feature for investors
3. **Property Investor Matches** - Core feature for wholesalers
4. **Wishlist Functionality** - Core feature for investors

### Medium Priority
5. **Similar Properties** - Enhances user experience
6. **Address Autocomplete** - Improves property creation UX
7. **Pre-submission Rehab Estimate** - Improves workflow

### Lower Priority
8. **Newsletter Subscription** - Marketing feature
9. **Background Verification** - May depend on third-party service
10. **CRM Investor Management** - Internal tool

### Data Integrity
11. **ATTOM Field Mapping Review** - Ensure data consistency

---

## 📝 **NEXT STEPS**

1. Create missing endpoints in priority order
2. Add proper role-based middleware (investor/wholesaler specific routes)
3. Implement matching algorithms for buy box and property-investor matching
4. Set up wishlist database structure
5. Integrate address autocomplete service (Google Maps/Mapbox)
6. Review and fix ATTOM data mapping

---

## 🔍 **EXISTING RELATED ENDPOINTS**

### Buy Box
- `GET /api/v1/buy-box` - Get user's buy box
- `PUT /api/v1/buy-box` - Update user's buy box

### Properties
- `GET /api/v1/properties` - List with filters ✅
- `GET /api/v1/properties/{property}` - Show property
- `POST /api/v1/properties` - Create property
- `GET /api/v1/properties/search/address` - Address lookup (not autocomplete)

### Rehab Estimates
- `POST /api/v1/properties/{property}/estimate` - Generate estimate (requires existing property)
- `GET /api/v1/properties/{property}/estimates` - Get estimate history

### User Management
- `GET /api/v1/user` - Get authenticated user
- `PUT /api/v1/admin/users/{user}` - Admin only user update
