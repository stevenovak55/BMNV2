# BMN Boston V2 REST API Contract

**Version:** 1.0.0
**Base URL:** `/wp-json/bmn/v1/` (or `?rest_route=/bmn/v1/` for non-pretty-permalinks)
**Authentication:** Public endpoints (no auth required for read operations)
**Date:** 2026-02-18 (Session 25)

---

## Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/properties` | Search/list properties with filters |
| GET | `/properties/{listing_id}` | Single property detail |
| GET | `/properties/autocomplete` | Type-ahead suggestions |
| GET | `/health` | Basic health check |
| GET | `/health/full` | Full service health check |
| POST | `/extractions/trigger` | Trigger extraction (admin only) |
| GET | `/extractions/status` | Extraction status |
| GET | `/extractions/history` | Extraction run history |

---

## 1. Property Search

**`GET /bmn/v1/properties`**

### Request Parameters

#### Pagination & Sort

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number (1-based) |
| `per_page` | int | 25 | Items per page (1-250) |
| `sort` | string | `list_date_desc` | Sort order |

**Sort options:** `price_asc`, `price_desc`, `list_date_asc`, `list_date_desc`, `beds_desc`, `sqft_desc`, `dom_asc`, `dom_desc`

#### Filters

| Param | Type | Description |
|-------|------|-------------|
| **Status** | | |
| `status` | string | `Active` (default), `Pending`, `Under Agreement`, `Sold` (comma-separated) |
| **Direct Lookup** | | (bypasses other filters) |
| `mls_number` | string | Exact MLS listing ID |
| `address` | string | Partial address match |
| **Location** | | |
| `city` | string | City name(s), comma-separated |
| `zip` | string | ZIP code(s), comma-separated |
| `neighborhood` | string | Subdivision or MLS area |
| `street_name` | string | Partial street name |
| **Geographic** | | |
| `bounds` | string | `south,west,north,east` (lat/lon bounding box) |
| `polygon` | string | GeoJSON polygon coordinates |
| **Property Type** | | |
| `property_type` | string | e.g., `Residential`, `Commercial Sale`, `Land` |
| `property_sub_type` | string | Comma-separated subtypes |
| **Price** | | |
| `min_price` | int | Minimum list price |
| `max_price` | int | Maximum list price |
| `price_reduced` | bool | Only price-reduced listings |
| **Rooms** | | |
| `beds` | int | Minimum bedrooms (>=) |
| `baths` | int | Minimum bathrooms (>=) |
| **Size** | | |
| `sqft_min` | int | Minimum living area (sq ft) |
| `sqft_max` | int | Maximum living area (sq ft) |
| `lot_size_min` | float | Minimum lot size (acres) |
| `lot_size_max` | float | Maximum lot size (acres) |
| **Time** | | |
| `year_built_min` | int | Minimum year built |
| `year_built_max` | int | Maximum year built |
| `max_dom` | int | Maximum days on market |
| `min_dom` | int | Minimum days on market |
| `new_listing_days` | int | Listed within last N days |
| **Parking** | | |
| `garage_spaces_min` | int | Minimum garage spaces |
| `parking_total_min` | int | Minimum total parking |
| **Amenities** | | |
| `has_virtual_tour` | bool | Has virtual tour |
| `has_garage` | bool | Has garage |
| `has_fireplace` | bool | Has fireplace |
| **Special** | | |
| `open_house_only` | bool | Only with upcoming open houses |
| `exclusive_only` | bool | Only exclusive listings |

### Response

```json
{
  "success": true,
  "data": [
    {
      "listing_id": "73478192",
      "listing_key": "0a09194c75ef41419a8d...",
      "address": "49 Main St Unit D, Topsfield MA 01983",
      "street_number": "49",
      "street_name": "Main St",
      "unit_number": "D",
      "city": "Topsfield",
      "state": "MA",
      "zip": "01983",
      "price": 650000.0,
      "original_price": 675000.0,
      "beds": 3,
      "baths": 2,
      "baths_full": 2,
      "baths_half": 0,
      "sqft": 2100,
      "property_type": "Residential",
      "property_sub_type": "Single Family Residence",
      "status": "Active",
      "latitude": 42.639978,
      "longitude": -70.950083,
      "list_date": "2026-02-01",
      "dom": 17,
      "main_photo_url": "https://dvvjkgh94f2v6.cloudfront.net/...",
      "photos": [
        "https://dvvjkgh94f2v6.cloudfront.net/.../photo1.jpeg",
        "https://dvvjkgh94f2v6.cloudfront.net/.../photo2.jpeg"
      ],
      "year_built": 1998,
      "lot_size": 0.25,
      "garage_spaces": 2,
      "has_open_house": false,
      "next_open_house": null,
      "is_exclusive": false,
      "grouping_address": "49 Main St"
    }
  ],
  "meta": {
    "total": 4040,
    "page": 1,
    "per_page": 25,
    "total_pages": 162
  }
}
```

### List Item Fields

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `listing_id` | string | No | MLS number (use in URLs) |
| `listing_key` | string | No | Internal unique hash |
| `address` | string | Yes | Full unparsed address |
| `street_number` | string | Yes | Parsed street number |
| `street_name` | string | Yes | Parsed street name |
| `unit_number` | string | Yes | Unit/apt number |
| `city` | string | Yes | City name |
| `state` | string | Yes | State code (e.g., "MA") |
| `zip` | string | Yes | Postal code |
| `price` | float | Yes | Current list price |
| `original_price` | float | Yes | Original list price (if reduced) |
| `beds` | int | Yes | Total bedrooms |
| `baths` | int | Yes | Total bathrooms |
| `baths_full` | int | Yes | Full bathrooms |
| `baths_half` | int | Yes | Half bathrooms |
| `sqft` | int | Yes | Living area (square feet) |
| `property_type` | string | Yes | Type (Residential, Commercial, Land) |
| `property_sub_type` | string | Yes | Subtype (Single Family, Condo, etc.) |
| `status` | string | Yes | MLS status (Active, Pending, Closed) |
| `latitude` | float | Yes | Latitude coordinate |
| `longitude` | float | Yes | Longitude coordinate |
| `list_date` | string | Yes | Listing date (YYYY-MM-DD) |
| `dom` | int | Yes | Days on market |
| `main_photo_url` | string | Yes | Primary photo URL |
| `photos` | string[] | No | Photo URLs (up to 5) |
| `year_built` | int | Yes | Year built |
| `lot_size` | float | Yes | Lot size in acres |
| `garage_spaces` | int | Yes | Number of garage spaces |
| `has_open_house` | bool | No | Has upcoming open house |
| `next_open_house` | object | Yes | `{date, start_time, end_time}` |
| `is_exclusive` | bool | No | Is exclusive listing |
| `grouping_address` | string | Yes | Address without unit (for grouping) |

---

## 2. Property Detail

**`GET /bmn/v1/properties/{listing_id}`**

Returns the complete property data including agent, office, photos, open houses, and price history.

### Path Parameters

| Param | Type | Description |
|-------|------|-------------|
| `listing_id` | string | MLS number (NOT listing_key) |

### Response

Includes all list item fields plus:

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `county` | string | Yes | County/parish name |
| `subdivision` | string | Yes | Subdivision name |
| `close_price` | float | Yes | Closing price |
| `price_per_sqft` | float | Yes | Price per square foot |
| `rooms_total` | int | Yes | Total rooms |
| `parking_total` | int | Yes | Total parking spaces |
| `fireplaces_total` | int | Yes | Total fireplaces |
| `is_archived` | bool | No | Is off-market |
| `close_date` | string | Yes | Close date (YYYY-MM-DD) |
| `public_remarks` | string | Yes | Property description |
| `showing_instructions` | string | Yes | Showing instructions |
| `virtual_tour_url` | string | Yes | Virtual tour URL |
| `photos` | object[] | No | `[{url, category, order}]` (all photos) |
| `photo_count` | int | No | Total photo count |
| `tax_annual_amount` | float | Yes | Annual tax amount |
| `tax_year` | int | Yes | Tax year |
| `association_fee` | float | Yes | HOA/association fee |
| `association_yn` | bool | Yes | Has association |
| `elementary_school` | string | Yes | Elementary school |
| `middle_school` | string | Yes | Middle school |
| `high_school` | string | Yes | High school |
| `school_district` | string | Yes | School district |
| `agent` | object | Yes | `{name, email, phone, mls_id}` |
| `office` | object | Yes | `{name, phone, address, city, state, zip}` |
| `open_houses` | object[] | No | `[{date, start_time, end_time, type, remarks}]` |
| `price_history` | object[] | No | `[{change_type, field, old_value, new_value, changed_at}]` |

---

## 3. Autocomplete

**`GET /bmn/v1/properties/autocomplete`**

### Request Parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `term` | string | Yes | Search term (min 2 chars) |

### Response

```json
{
  "success": true,
  "data": [
    {"value": "Boston", "type": "city", "count": 441},
    {"value": "02101", "type": "zip", "count": 45},
    {"value": "Back Bay", "type": "neighborhood", "count": 67},
    {"value": "Main Street", "type": "street", "count": 12},
    {"value": "123 Main St, Boston MA 02101", "type": "address"},
    {"value": "73478192", "type": "mls"}
  ]
}
```

### Suggestion Types (priority order)

| Type | Has Count | Scope | Description |
|------|-----------|-------|-------------|
| `mls` | No | All | MLS number match |
| `city` | Yes | Active | City names |
| `zip` | Yes | Active | ZIP codes |
| `neighborhood` | Yes | Active | Subdivisions/MLS areas |
| `street` | Yes | Active | Street names |
| `address` | No | All | Full addresses |

Maximum 10 suggestions returned, deduplicated by value.

---

## Response Conventions

### Success Response
```json
{"success": true, "data": ..., "meta": ...}
```

### Error Response
```json
{"success": false, "message": "Error description", "code": 404}
```

### Null Handling
- Empty strings are returned as `null`
- Zero dates (`0000-00-00`) are returned as `null`
- Zero prices on non-sold listings are returned as `null`
- Missing optional fields are `null`, never omitted

### Photo URLs
All photos use the CloudFront CDN:
`https://dvvjkgh94f2v6.cloudfront.net/{dataset_id}/{listing_id}/{photo_hash}.jpeg`

### Status Mapping
| Request Status | DB Status(es) |
|---------------|---------------|
| `Active` | Active (non-archived) |
| `Pending` | Pending, Active Under Contract |
| `Under Agreement` | Pending, Active Under Contract |
| `Sold` | Closed (archived) |

---

## V1 to V2 Migration Guide

For iOS app developers migrating from V1 API:

| V1 Field | V2 Equivalent | Notes |
|----------|---------------|-------|
| `id` | `listing_key` | Internal hash |
| `mls_number` | `listing_id` | MLS number |
| `photo_url` | `main_photo_url` | Primary photo |
| `property_subtype` | `property_sub_type` | With underscore |
| `neighborhood` | (not in list) | Available in detail as `subdivision` |
| `district_grade` | (school plugin) | Separate integration |
| `exclusive_tag` | (derive from `is_exclusive`) | |
| `data.listings` | `data` | Direct array |
| `data.total` | `meta.total` | In meta object |
| `data.page` | `meta.page` | In meta object |

---

*Generated by Claude Code, Session 25, 2026-02-18*
