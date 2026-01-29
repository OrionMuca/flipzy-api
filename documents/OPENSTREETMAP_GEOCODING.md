# OpenStreetMap (Nominatim) Geocoding

## Are we okay with OpenStreetMap?

**Yes, with important limits.**

- **Single geocode / reverse geocode** (one full address or coordinates): Allowed. We use a valid **User-Agent**, **cache** results, and stay under **1 request per second** when using Nominatim for address **suggestions** (see below).
- **Address suggestions (autocomplete)**: The **public** Nominatim service [does not allow autocomplete](https://operations.osmfoundation.org/policies/nominatim/). We still use it when Mapbox is not configured, but we:
  - **Throttle** to at most **1 request per second** to Nominatim (app-wide lock).
  - **Cache** suggestions for 24 hours so repeat/similar queries don’t hit the API.
  - Rely on you to add **Mapbox** (or self-hosted Nominatim) for production address suggestions when you’re ready.

So: using OpenStreetMap for geocoding is fine; for **suggestions**, prefer Mapbox or a self-hosted Nominatim instance when you can.

---

## Why is some OpenStreetMap data null?

**OpenStreetMap data is often incomplete or inconsistent.** Many fields can be missing depending on:

- **Result type**: A postcode result has no `house_number` or `road`; a place has no street.
- **Region**: OSM uses different address keys by country/region (`city`, `town`, `village`, `municipality`, `hamlet`, `locality`, `county`, etc.).
- **Coverage**: Not every place has full address tags in OSM.

So **nullable fields are expected**. We handle them by:

1. **Broader fallbacks**  
   For “city”, we try in order: `city`, `town`, `village`, `municipality`, `hamlet`, `locality`, `county`, `state_district`, so we get a value whenever OSM provides one of these.

2. **Null-safe output**  
   Empty or missing values are returned as `null` in `address_components` (no empty strings), so the API response is consistent.

3. **Formatted address**  
   We build `formatted_address` only from non-empty parts (e.g. `"SMYRNA, DE 19977"` when there’s no street). If everything is missing, we fall back to a short slice of Nominatim’s `display_name` so the user still sees something.

So: **yes, nulls are because OpenStreetMap data is often nullable or missing**; the backend is written to handle that and still return a usable, ATTOM-style formatted address when possible.

---

## Does OpenStreetMap return street data?

**Yes – but only when the result is an address, not a place.**

Nominatim returns different **result types**:

| Query type | What Nominatim returns | Has `road` / `house_number`? |
|------------|-------------------------|------------------------------|
| **City / place** (e.g. "Smyrna", "Denver") | The **place** (city, town, village) | **No** – the result is the place itself, not a street address. |
| **Street address** (e.g. "468 Sequoia Dr Smyrna DE", "Sequoia Dr Smyrna") | **Address** (building, way, or interpolated address) | **Yes** – when OSM has that address in the database. |

So **searching for a city and only getting results without a street is expected**: those results are the city/place, and places don’t have a street line. Street data (`road`, `house_number`) appears when:

1. The query matches a **street-level** result (full or partial address), and  
2. OpenStreetMap has that address in its data (US coverage is uneven; many areas have cities/postcodes but not every street).

To get suggestions that include a street, the user must type something address-like (e.g. street name + city, or full address). We do not filter or drop street data – we pass through whatever Nominatim returns; for city-only queries it simply doesn’t include street fields.
