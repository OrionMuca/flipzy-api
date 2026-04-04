# Property Creation Flow

Full documentation for the property creation flow, covering the address lookup, building permits, and the final property creation request/response.

---

## Overview

The property creation flow has 3 steps:

1. **Address Lookup** - User searches an address, backend fetches property data + building permits from ATTOM
2. **Frontend Preparation** - Frontend collects user input (title, price, images, etc.) and holds the ATTOM data
3. **Property Creation** - Frontend sends everything to the backend in a single POST request

---

## Step 1: Address Lookup

**Endpoint:** `GET /api/v1/wholesaler/properties/search/address`

**Auth:** Bearer token required (wholesaler or admin role)

### Request

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `address` | string | Yes (or use separate fields) | Full address string, e.g. `"458 10TH AVE S, JACKSONVILLE BEACH, FL 32250"` |
| `street` | string | Yes (if no `address`) | Street address only |
| `city` | string | No | City name |
| `state` | string | No | 2-letter state code |
| `zip` | string | No | ZIP code |
| `force_fresh` | boolean | No | Skip cache, default `false` |

### Response

The backend calls two ATTOM endpoints:

- `/propertyapi/v1.0.0/property/expandedprofile` - property details
- `/propertyapi/v1.0.0/property/buildingpermits` - building permits

Returns a `PropertyPreviewResource`:

```json
{
  "success": true,
  "message": "Property data found",
  "data": {
    "id": null,
    "title": null,
    "description": null,
    "property_type": "house",
    "status": null,
    "address": "458 10TH AVE S",
    "city": "JACKSONVILLE BEACH",
    "state": "FL",
    "zip_code": "32250",
    "country": "US",
    "location": {
      "latitude": 30.278311,
      "longitude": -81.391615
    },
    "details": {
      "bedrooms": 3,
      "bathrooms": 2,
      "square_feet": 1329,
      "lot_size": 6250,
      "year_built": 1989,
      "condition": "fair"
    },
    "financial": {
      "asking_price": null,
      "arv": 657100,
      "repair_estimate": null,
      "potential_profit": null
    },
    "images": [],
    "primary_image": null,
    "wholesaler": null,
    "is_featured": false,
    "is_verified": false,
    "allow_inquiries": false,
    "attom_data": {
      "assessed_value": 46320,
      "market_value": 657100,
      "tax_amount": 3229.06,
      "tax_year": 2024,
      "attom_id": 184713191,
      "apn": "02192-04-018-000",
      "full_address": "4529 WINONA CT, DENVER, CO 80212",
      "property_class": "Single Family Residence / Townhouse",
      "subdivision": "BERKELEY",
      "county": "Denver",
      "country": "US",
      "zoning_type": null,
      "pool_type": null,
      "municipality": "DENVER",
      "legal1": "BERKELEY B36 L31 & S/2 OF L32 EXC REAR 8FT TO CITY",
      "cooling_type": null,
      "heating_type": "CENTRAL",
      "heating_fuel": "GAS",
      "living_size": 1147,
      "gross_size": 1147,
      "last_sale_date": "2023-10-23"
    },
    "building_permits": [
      {
        "effective_date": "2018-11-30",
        "permit_number": "2018-ELEC-0014039",
        "status": "final",
        "description": "200 amp service change and 50 amp sub-panel to garage",
        "type": "Electrical permit",
        "project_name": "Aca Electrical Permit",
        "job_value": 4000,
        "fees": 51,
        "business_name": "Positively Electric INC",
        "home_owner_name": "Mattice,michael Scott",
        "classifiers": ["Electrical Work"]
      },
      {
        "effective_date": "2017-09-22",
        "permit_number": "2017-ROOFSIDE-0013232",
        "status": "final",
        "description": "Tear off existing material house and garage...",
        "type": "Roofing and siding permit",
        "project_name": "Aca Roofing Permit",
        "job_value": 9447,
        "fees": 99,
        "business_name": "J&K Roofing INC",
        "home_owner_name": "Mattice,michael Scott",
        "classifiers": ["Roofing", "Siding"]
      }
    ],
    "created_at": null,
    "updated_at": null
  }
}
```

**Notes:**

- `building_permits` are sorted by `effective_date` descending (latest first)
- `building_permits` will be `null` if ATTOM has no permits on file for that property
- `arv` is populated from ATTOM's market value; `asking_price` is set by the wholesaler
- Fields like `title`, `description`, `id`, `status` are `null` because the property hasn't been created yet

---

## Step 2: Frontend Preparation

The frontend holds the data from Step 1 and collects additional input from the user:

- **User fills in:** title, description, asking_price, images, and optionally ARV override
- **Frontend keeps:** all ATTOM data (details, attom_data fields, building_permits)
- **Frontend may also call** the rehab estimate preview endpoint to get a repair cost estimate

---

## Step 3: Property Creation

**Endpoint:** `POST /api/v1/wholesaler/properties`

**Auth:** Bearer token required (wholesaler or admin role)

**Content-Type:** `multipart/form-data` (because of image uploads)

### Request Body

#### Required Fields

| Field | Type | Validation | Description |
|-------|------|------------|-------------|
| `title` | string | max:255 | Property title |
| `address` | string | max:255 | Full street address |
| `city` | string | max:100 | City name |
| `state` | string | exactly 2 chars | State code (e.g. `FL`) |
| `zip_code` | string | max:10 | ZIP code |
| `asking_price` | numeric | min:0 | Asking price set by wholesaler |

#### Optional - Property Details

These can be sent as flat top-level fields OR nested inside a `details` object. Top-level values take precedence.

| Field | Type | Validation | Description |
|-------|------|------------|-------------|
| `description` | string | - | Property description |
| `property_type` | string | `house`, `condo`, `townhouse`, `duplex`, `multi-family` | Property type |
| `country` | string | exactly 2 chars | Country code, defaults to `US` |
| `latitude` | numeric | -90 to 90 | Latitude |
| `longitude` | numeric | -180 to 180 | Longitude |
| `bedrooms` | integer | 0-20 | Number of bedrooms |
| `bathrooms` | numeric | 0-20 | Number of bathrooms |
| `square_feet` | integer | min:0 | Living area in sq ft |
| `lot_size` | integer | min:0 | Lot size in sq ft |
| `year_built` | integer | 1800-current year | Year built |
| `condition` | string | `excellent`, `good`, `fair`, `poor` | Property condition |

#### Optional - Extended Details (from ATTOM)

| Field | Type | Description |
|-------|------|-------------|
| `living_size` | integer | Living area size |
| `gross_size` | integer | Gross building size |
| `zoning_type` | string | Zoning classification |
| `pool_type` | string | Pool type |
| `municipality` | string | Municipality name |
| `legal1` | string | Legal description |
| `cooling_type` | string | Cooling system type |
| `heating_fuel` | string | Heating fuel type |
| `heating_type` | string | Heating system type |
| `last_sale_date` | date | Last sale date (YYYY-MM-DD) |
| `tax_amount` | numeric | Annual tax amount |
| `tax_year` | integer | Tax assessment year |

#### Optional - Financial

| Field | Type | Description |
|-------|------|-------------|
| `arv` | numeric | After Repair Value |
| `repair_estimate` | numeric | Estimated repair cost |

> `potential_profit` is auto-calculated: `arv - asking_price - repair_estimate`

#### Optional - Flags

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `is_featured` | boolean | false | Featured listing |
| `is_verified` | boolean | false | Verified property |
| `allow_inquiries` | boolean | false | Allow investor inquiries |

#### Optional - Images

| Field | Type | Description |
|-------|------|-------------|
| `images[]` | file array | Up to 10 images (jpeg/png/jpg/gif, max 5MB each) |
| `primary_image_index` | integer | Index of the primary image in the array (0-based) |

#### Optional - Rehab Estimate (from preview)

| Field | Type | Description |
|-------|------|-------------|
| `rehab_estimate` | object/JSON string | Full rehab estimate from the preview endpoint |
| `rehab_estimate.estimated_cost` | numeric | Total estimated repair cost |
| `rehab_estimate.breakdown` | object | Cost breakdown by category |
| `rehab_estimate.model_used` | string | Model/method used for estimate |
| `rehab_estimate.property_data` | object | Property data used for estimate |
| `rehab_estimate.notes` | string | Estimate notes |
| `rehab_estimate.tokens_used` | integer | AI tokens used (if applicable) |

#### Optional - Building Permits (from lookup)

Pass through the `building_permits` array received from the address lookup response.

| Field | Type | Description |
|-------|------|-------------|
| `building_permits` | array/JSON string | Array of permit objects from ATTOM |
| `building_permits[].effective_date` | string | Permit effective date |
| `building_permits[].permit_number` | string | Permit number |
| `building_permits[].status` | string | Permit status (e.g. `final`, `issued`, `withdrawn`) |
| `building_permits[].description` | string | Work description |
| `building_permits[].type` | string | Permit type (e.g. `Electrical permit`, `Roofing and siding permit`) |
| `building_permits[].project_name` | string | Project name |
| `building_permits[].job_value` | numeric | Job value in dollars |
| `building_permits[].fees` | numeric | Permit fees |
| `building_permits[].business_name` | string | Contractor/business name |
| `building_permits[].home_owner_name` | string | Home owner name on permit |
| `building_permits[].classifiers` | array | Work categories (e.g. `["Electrical Work", "Plumbing"]`) |

#### Auto-Set by Backend (do NOT send)

| Field | Value | Description |
|-------|-------|-------------|
| `status` | `"draft"` | Always starts as draft |
| `payment_status` | `"unpaid"` | Unpaid until publish payment |
| `wholesaler_id` | from auth token | Authenticated user's ID |
| `potential_profit` | calculated | `arv - asking_price - repair_estimate` |

### Example Request Body

```json
{
  "title": "458 10TH AVE S",
  "description": "Beautiful house located at 458 10TH AVE S...",
  "property_type": "house",
  "address": "458 10TH AVE S, JACKSONVILLE BEACH, FL 32250",
  "city": "JACKSONVILLE BEACH",
  "state": "FL",
  "zip_code": "32250",
  "latitude": 30.278311,
  "longitude": -81.391615,
  "bedrooms": 3,
  "bathrooms": 2,
  "square_feet": 1329,
  "lot_size": 6250,
  "year_built": 1989,
  "asking_price": 500000,
  "arv": 657100,
  "details": {
    "tax_amount": 3632.92,
    "tax_year": 2024,
    "zoning_type": "Residential",
    "municipality": "DUVAL",
    "cooling_type": "CENTRAL",
    "heating_type": "FORCED AIR",
    "heating_fuel": "ELECTRIC",
    "living_size": 1329,
    "gross_size": 1861,
    "last_sale_date": "2000-09-22",
    "legal1": "8-13 04-3S-29E OCEANSIDE PARK LOT 4 BLK 105"
  },
  "rehab_estimate": {
    "estimated_cost": 82450,
    "breakdown": {
      "kitchen": 20612.5,
      "bathrooms": 16490,
      "flooring": 12367.5,
      "paint": 8245,
      "electrical": 8245,
      "plumbing": 6596,
      "hvac": 5771.5,
      "roofing": 4122.5
    },
    "labor_percentage": 40,
    "materials_percentage": 60,
    "timeline_weeks": 16,
    "risk_factors": [],
    "notes": "Calculation-based estimate for Jacksonville Beach, FL.",
    "confidence": "medium",
    "model_used": "calculation-fallback",
    "tokens_used": null,
    "property_data": {
      "location": {
        "address": "458 10TH AVE S, JACKSONVILLE BEACH, FL 32250",
        "city": "JACKSONVILLE BEACH",
        "state": "FL",
        "zip": "32250"
      },
      "property": {
        "type": "house",
        "square_feet": 1329,
        "bedrooms": 3,
        "bathrooms": 2,
        "year_built": 1989,
        "condition": null,
        "lot_size": 6250
      },
      "financial": {
        "asking_price": 500000,
        "arv": 657100
      }
    }
  },
  "building_permits": [
    {
      "effective_date": "2018-11-30",
      "permit_number": "2018-ELEC-0014039",
      "status": "final",
      "description": "200 amp service change and 50 amp sub-panel to garage",
      "type": "Electrical permit",
      "project_name": "Aca Electrical Permit",
      "job_value": 4000,
      "fees": 51,
      "business_name": "Positively Electric INC",
      "home_owner_name": "Mattice,michael Scott",
      "classifiers": ["Electrical Work"]
    },
    {
      "effective_date": "2017-09-22",
      "permit_number": "2017-ROOFSIDE-0013232",
      "status": "final",
      "description": "Tear off existing material house and garage",
      "type": "Roofing and siding permit",
      "project_name": "Aca Roofing Permit",
      "job_value": 9447,
      "fees": 99,
      "business_name": "J&K Roofing INC",
      "home_owner_name": "Mattice,michael Scott",
      "classifiers": ["Roofing", "Siding"]
    }
  ],
  "primary_image_index": 0
}
```

> Note: `images[]` are sent as file uploads in the multipart form, not shown in JSON.

### Response (201 Created)

```json
{
  "success": true,
  "message": "Property created successfully",
  "data": {
    "id": "019d55a4-cae6-71bc-b089-e4b9965f2c32",
    "title": "458 10TH AVE S",
    "description": "Beautiful house located at 458 10TH AVE S...",
    "property_type": "house",
    "status": "draft",
    "address": "458 10TH AVE S, JACKSONVILLE BEACH, FL 32250",
    "city": "JACKSONVILLE BEACH",
    "state": "FL",
    "zip_code": "32250",
    "country": "US",
    "location": {
      "latitude": "30.27831100",
      "longitude": "-81.39161500"
    },
    "details": {
      "bedrooms": 3,
      "bathrooms": 2,
      "square_feet": 1329,
      "lot_size": 6250,
      "year_built": 1989,
      "condition": null,
      "living_size": 1329,
      "gross_size": 1861,
      "zoning_type": "Residential",
      "pool_type": null,
      "municipality": "DUVAL",
      "legal1": "8-13 04-3S-29E OCEANSIDE PARK LOT 4 BLK 105",
      "cooling_type": "CENTRAL",
      "heating_fuel": "ELECTRIC",
      "heating_type": "FORCED AIR",
      "last_sale_date": "2000-09-22",
      "tax_amount": 3632.92,
      "tax_year": 2024
    },
    "financial": {
      "asking_price": "500000.00",
      "arv": "657100.00",
      "repair_estimate": "82450.00",
      "potential_profit": "74650.00"
    },
    "rehab_estimate": {
      "id": "019d55a4-caf7-71d8-ab4a-9a14103e27d2",
      "property_id": "019d55a4-cae6-71bc-b089-e4b9965f2c32",
      "estimated_cost": 82450,
      "total_cost": 82450,
      "breakdown": {
        "kitchen": 20612.5,
        "bathrooms": 16490,
        "flooring": 12367.5,
        "paint": 8245,
        "electrical": 8245,
        "plumbing": 6596,
        "hvac": 5771.5,
        "roofing": 4122.5
      },
      "labor_percentage": 40,
      "materials_percentage": 60,
      "timeline_weeks": 16,
      "risk_factors": [],
      "notes": "Calculation-based estimate for Jacksonville Beach, FL.",
      "confidence": "medium",
      "model_used": "calculation-fallback",
      "tokens_used": null,
      "created_at": "04-04-2026 12:00:00",
      "updated_at": "04-04-2026 12:00:00"
    },
    "images": [
      {
        "id": "019d55a4-caf1-727c-9270-d3a5e5c1a4ba",
        "url": "https://api.goflipzy.com/storage/properties/019d55a4.../image.png",
        "path": "properties/019d55a4.../image.png",
        "type": "image",
        "order": 0,
        "is_primary": true,
        "alt_text": "458 10TH AVE S - Image 1"
      }
    ],
    "primary_image": {
      "id": "019d55a4-caf1-727c-9270-d3a5e5c1a4ba",
      "url": "https://api.goflipzy.com/storage/properties/019d55a4.../image.png",
      "path": "properties/019d55a4.../image.png",
      "type": "image",
      "order": 0,
      "is_primary": true,
      "alt_text": "458 10TH AVE S - Image 1"
    },
    "wholesaler": {
      "id": "019bc392-b29a-7148-a532-35e2b8fb5c18",
      "name": "Wholesaler Test",
      "email": "wholesaler-flipzy@gmail.com",
      "phone_number": "0684444135",
      "company_name": null,
      "photo": "https://api.goflipzy.com/storage/profiles/.../photo.png",
      "email_verified_at": null,
      "id_verification_status": "unverified",
      "id_verified_at": null,
      "roles": ["wholesaler"],
      "created_at": "2026-01-15T21:32:09.000000Z",
      "updated_at": "2026-03-16T20:47:40.000000Z"
    },
    "is_featured": false,
    "is_verified": false,
    "allow_inquiries": false,
    "attom_details": null,
    "sale_history": null,
    "building_permits": [
      {
        "effective_date": "2018-11-30",
        "permit_number": "2018-ELEC-0014039",
        "status": "final",
        "description": "200 amp service change and 50 amp sub-panel to garage",
        "type": "Electrical permit",
        "project_name": "Aca Electrical Permit",
        "job_value": 4000,
        "fees": 51,
        "business_name": "Positively Electric INC",
        "home_owner_name": "Mattice,michael Scott",
        "classifiers": ["Electrical Work"]
      },
      {
        "effective_date": "2017-09-22",
        "permit_number": "2017-ROOFSIDE-0013232",
        "status": "final",
        "description": "Tear off existing material house and garage",
        "type": "Roofing and siding permit",
        "project_name": "Aca Roofing Permit",
        "job_value": 9447,
        "fees": 99,
        "business_name": "J&K Roofing INC",
        "home_owner_name": "Mattice,michael Scott",
        "classifiers": ["Roofing", "Siding"]
      }
    ],
    "enriched_at": null,
    "created_at": "04-04-2026 12:00:00",
    "updated_at": "04-04-2026 12:00:00"
  }
}
```

---

## Key Notes for Frontend

1. **Details can be sent flat or nested.** The backend accepts `bedrooms` at the top level or inside `details: { bedrooms: 3 }`. Top-level values win if both are sent. Fields like `tax_amount`, `zoning_type`, `municipality`, etc. should be sent this way too.

2. **Building permits are pass-through.** The frontend receives them from the lookup response and sends them back unchanged during creation. The backend stores them in the database so they appear in all future property responses.

3. **Building permits may be null.** Not every property has permits in ATTOM's database. The frontend should handle `building_permits: null` gracefully.

4. **Status is always `"draft"`.** Even if the frontend sends `status: "active"`, the backend overrides it to `"draft"`. Properties become active after the publish payment flow.

5. **`potential_profit` is auto-calculated.** Do not send it. The backend computes it as `arv - asking_price - repair_estimate`. If `arv` or `repair_estimate` is missing, it will be `null`.

6. **Rehab estimate syncs `repair_estimate`.** When `rehab_estimate.estimated_cost` is provided, the backend sets `repair_estimate` on the property to that value and recalculates `potential_profit`.

7. **Images require multipart/form-data.** The request must be sent as `multipart/form-data`, not `application/json`. The `rehab_estimate` and `building_permits` fields can be sent as JSON strings in this case - the backend handles JSON decoding.

8. **Wholesaler response includes verification status.** The `wholesaler` object in the response includes `id_verification_status` and `id_verified_at` for identity verification state.
