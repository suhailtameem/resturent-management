# Customer App API

This document describes the customer-facing API implemented by this Laravel application. Manager and staff APIs are outside its scope.

## Base URL and conventions

- Base path: `/api/v1`
- Use JSON request and response bodies with `Content-Type: application/json`.
- Success resources use a `data` envelope. Validation failures use Laravel's standard `message` and `errors` response. Domain errors include `message`, `code`, and `errors`.
- IDs in API resources are strings. Prices are decimal numbers in SAR, rounded to two decimal places.
- Timestamps use ISO 8601 where returned.
- Public catalog endpoints do not require authentication. Protected endpoints require a Sanctum bearer token:

```http
Authorization: Bearer <access_token>
```

## Endpoint overview

| Method   | Path                     | Auth              | Purpose                                                     |
| -------- | ------------------------ | ----------------- | ----------------------------------------------------------- |
| `GET`    | `/startup-lookups`       | Public            | Store status, settings, currency, pause, and delivery zones |
| `GET`    | `/categories`            | Public            | List active categories                                      |
| `GET`    | `/products`              | Public            | Search and filter available products                        |
| `GET`    | `/products/{product}`    | Public            | Get product details and options                             |
| `POST`   | `/auth/login-phone`      | Public, throttled | Request a one-time code                                     |
| `POST`   | `/auth/verify-otp`       | Public, throttled | Verify a one-time code and issue a token                    |
| `POST`   | `/auth/logout`           | Bearer token      | Revoke the current token                                    |
| `GET`    | `/me`                    | Bearer token      | Get the customer profile and addresses                      |
| `PATCH`  | `/me`                    | Bearer token      | Update profile fields                                       |
| `GET`    | `/addresses`             | Bearer token      | List the customer's addresses                               |
| `POST`   | `/addresses`             | Bearer token      | Create an address                                           |
| `PATCH`  | `/addresses/{address}`   | Bearer token      | Update an owned address                                     |
| `DELETE` | `/addresses/{address}`   | Bearer token      | Delete an owned address                                     |
| `POST`   | `/checkout/quote`        | Bearer token      | Calculate authoritative totals for pickup or delivery       |
| `POST`   | `/orders`                | Bearer token      | Place an order (pickup/delivery, COD/online) from a quote   |
| `GET`    | `/orders`                | Bearer token      | List the customer's orders                                  |
| `GET`    | `/orders/{order}`        | Bearer token      | Load full order details screen with items and timeline      |
| `GET`    | `/orders/{order}/track`  | Bearer token      | Real-time order status tracking and status timeline         |
| `POST`   | `/orders/{order}/cancel` | Bearer token      | Cancel an eligible order before preparation                 |

The legacy `/customer/store/settings`, `/customer/auth/send-otp`, `/customer/auth/verify-otp`, `/customer/profile`, `/customer/menu`, and `/customer/products/{product}` routes are retained for compatibility. Prefer the versioned paths above for new integrations.

+## Startup lookups

- +### Get store startup lookups
- +`GET /startup-lookups`
- +Public endpoint called by the customer mobile or web application upon launch. It delivers essential bootstrap data required to initialize UI state, evaluate ordering availability, display store profile and currency details, and configure delivery location pickers.
- +`http
+GET /api/v1/startup-lookups
+`
- +Successful response: `200 OK`
- +```json
  +{
- "data": {
- "is_open": true,
- "paused_until": null,
- "estimated_prep_time_minutes": 30,
- "store_name_ar": "أرواق العنب",
- "store_name_en": "Arwaq Al-Eneab",
- "address": "تبوك، المملكة العربية السعودية",
- "support_phone": "+966500000000",
- "currency_code": "SAR",
- "currency_label_ar": "ر.س",
- "currency_label_en": "SAR",
- "payment_methods": {
-      "cash_on_delivery_enabled": true,
-      "online_enabled": true
- },
- "auto_accept_prepaid_orders": false,
- "delivery_cities": [
-      {
-        "id": "1",
-        "name": "تبوك",
-        "service_status": "active",
-        "neighborhoods": [
-          {
-            "id": "10",
-            "name": "حي المروج",
-            "delivery_fee": 12.0,
-            "minimum_order_amount": 35.0,
-            "is_active": true
-          }
-        ]
-      }
- ]
- }
  +}
  +```
- +#### Response field reference
- +- **Order intake and store operations:**
-   - `is_open` (`boolean`): Indicates whether the restaurant is currently accepting customer orders. If `false`, the app should disable cart checkout and inform the customer.
-   - `paused_until` (`string` or `null`): ISO 8601 UTC timestamp if order intake is temporarily paused by a manager (e.g., `"2026-10-29T21:30:00Z"`). Returns `null` when operating normally. Expired pauses automatically reset to open when accessed.
-   - `estimated_prep_time_minutes` (`integer`): Estimated order preparation time in minutes, including any active rush buffer configured by the manager.
      +- **Store profile & contacts:**
-   - `store_name_ar` (`string`), `store_name_en` (`string`): Store display names in Arabic and English.
-   - `address` (`string` or `null`): Physical restaurant location address.
-   - `support_phone` (`string`): Customer support telephone number.
      +- **Currency & payments:**
-   - `currency_code` (`string`): Fixed ISO code (`SAR`).
-   - `currency_label_ar` (`string`), `currency_label_en` (`string`): Display labels (e.g., `"ر.س"` and `"SAR"`).
-   - `payment_methods`:
-   - `cash_on_delivery_enabled` (`boolean`): Indicates if COD is accepted for orders.
-   - `online_enabled` (`boolean`): Indicates if online payment is enabled for the store.
-   - `auto_accept_prepaid_orders` (`boolean`): Whether prepaid orders are automatically moved to preparation.
      +- **Delivery coverage:**
-   - `delivery_cities` (`array`): Available delivery cities and their served neighborhoods.
-   - `id` (`string`): City ID.
-   - `name` (`string`): City name.
-   - `service_status` (`string`): City coverage status (`"active"` or `"temporarily_disabled"`).
-   - `neighborhoods` (`array`): Active neighborhoods within the city. Each entry contains `id`, `name`, `delivery_fee`, `minimum_order_amount`, and `is_active`.
- ## Catalog

### List categories

`GET /categories`

Returns active categories ordered by `sort_order`, then ID.

```json
{
    "data": [
        {
            "id": "1",
            "name_ar": "وجبات",
            "name_en": "Meals",
            "icon_code": null,
            "display_order": 1
        }
    ]
}
```

### List products

`GET /products`

Optional query parameters:

| Parameter     | Type    | Rules                                                                |
| ------------- | ------- | -------------------------------------------------------------------- |
| `category_id` | integer | Must identify an existing category                                   |
| `search`      | string  | Up to 100 characters; searches Arabic/English names and descriptions |
| `featured`    | boolean | Filter by featured flag                                              |
| `best_seller` | boolean | Filter by best-seller flag                                           |
| `per_page`    | integer | 1–50; defaults to 20                                                 |

Only available products in active categories are returned. Results are ordered by category `sort_order` and category ID, then product `sort_order` and product ID. Results use Laravel pagination (`data`, `links`, and `meta`).

Product fields:

- `id`, `category_id`, `name_ar`, `name_en`, `description_ar`, `image_url`
- `is_featured`, `is_best_seller`, `badge_text_ar`, `is_available`, `price`
- `sizes`: `id`, names, effective `price`, `piece_count`, `subtitle_ar`, `is_recommended`
- `flavor_options` and `add_ons`: `id`, names, `description_ar`, and `price`

`price` is the effective base product price. Selectable variant prices are provided separately in `sizes`. Product discounts are applied server-side when calculating quotes.

When a product has an uploaded image, `image_url` is the absolute public URL generated from Laravel's public storage disk. Use it directly rather than constructing a storage URL in the app.

Example:

```http
GET /api/v1/products?category_id=1&featured=true&search=بيتزا&per_page=10
```

### Get product details

`GET /products/{product}`

Returns the product resource including its selectable sizes, flavors, and add-ons. An unavailable product or a product in an inactive category returns `404` with code `PRODUCT_UNAVAILABLE`.

## Authentication

There is no separate customer signup endpoint or signup screen. The customer enters a phone number, requests a code, and verifies it. After successful verification, the backend signs in an existing customer or creates an account for a new phone number. The app must not decide whether to create an account.

### Request an OTP

`POST /auth/login-phone`

```json
{
    "phone": "+966530697231"
}
```

Saudi phone numbers may be supplied as `05XXXXXXXX`, `5XXXXXXXX`, or `+9665XXXXXXXX`; they are normalized to `+9665XXXXXXXX`.

OTP requests have route throttling (5 requests per minute), a 60-second resend cooldown, and a limit of 3 requests per 10-minute window.

**SMS provider is not configured.** For app development, `local` and `testing` environments simulate a successful send without sending a message or returning a code. In these environments, any four-digit code can complete login. This bypass is intentionally limited to these environments and must not be enabled in production. In other environments the endpoint returns `503 OTP_DELIVERY_UNAVAILABLE` until a provider is integrated.

Development response:

```json
{
    "data": {
        "message": "Verification code sent successfully.",
        "retry_in_seconds": 60
    }
}
```

Treat `200 OK` as confirmation that the app can show the code-entry screen; it does not include a code. Do not proceed to a signed-in state until OTP verification succeeds.

### Verify an OTP

`POST /auth/verify-otp`

```json
{
    "phone": "+966530697231",
    "code": "1234"
}
```

`code` must be a four-digit string. The legacy `otp` request key is accepted as an alias. In `local` and `testing`, the value is not checked; any four digits allow login so the app can be tested without an SMS provider. Outside these environments, the code must match the issued OTP, expires after 10 minutes, is single-use, and allows at most five verification attempts.

Successful response:

```json
{
    "data": {
        "access_token": "<plain-text-token>",
        "token_type": "Bearer",
        "customer": {
            "id": "1",
            "name": "Customer",
            "phone": "+966530697231",
            "email": "customer.530697231@example.invalid",
            "saved_addresses": []
        }
    }
}
```

No refresh-token endpoint or token-expiry value is currently provided.

### Login flow status handling

| Status                         | Meaning                                                                                                          | App behavior                                                                                                                                  |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `200` from `/auth/login-phone` | The OTP request was accepted (or simulated in local/testing).                                                    | Show the code-entry screen. Never infer or display an OTP from this response.                                                                 |
| `200` from `/auth/verify-otp`  | Verification succeeded; an existing customer was authenticated or a new account was created.                     | Securely store `data.access_token` and send it in the `Authorization` header for protected API requests. Continue into the authenticated app. |
| `403` from `/auth/verify-otp`  | The customer account is blocked (`CUSTOMER_BLOCKED`).                                                            | Do not sign in; show an appropriate account-unavailable message.                                                                              |
| `422`                          | Invalid request fields or invalid/expired OTP (`INVALID_OTP` outside local/testing).                             | Show the backend message and field errors when present; allow correction or re-entry.                                                         |
| `429`                          | OTP request throttling/cooldown was reached (`OTP_RATE_LIMITED` or route throttling).                            | Do not retry immediately; show a wait message and honor retry timing if provided.                                                             |
| `503` from `/auth/login-phone` | OTP delivery is unavailable (`OTP_DELIVERY_UNAVAILABLE`) because no provider is configured for this environment. | Show that login is temporarily unavailable. Do not show the OTP-entry screen as though the request succeeded.                                 |
| Any other non-2xx response     | The operation failed.                                                                                            | Do not claim success or enter the authenticated app. Parse available error fields defensively and avoid showing raw server exceptions.        |

Validation errors commonly include `message` and field-level `errors`. Domain errors include `message`, `code`, and `errors`; optional fields may vary, so the app should not assume every error response has every field.

### Logout

`POST /auth/logout`

Requires a bearer token. Deletes the current Sanctum personal access token and returns `204 No Content`.

## Customer profile and addresses

### Get profile

`GET /me`

Example response:

```json
{
    "data": {
        "id": "1",
        "name": "Customer",
        "phone": "+966530697231",
        "email": "customer@example.com",
        "preferred_language": "ar",
        "is_vip": false,
        "saved_addresses": []
    }
}
```

### Update profile

`PATCH /me`

Requires a Bearer token. To update only the customer's name, send `full_name`:

```http
PATCH /api/v1/me
Authorization: Bearer <access_token>
Content-Type: application/json
```

```json
{
    "full_name": "New Customer Name"
}
```

The backend updates the customer's `full_name` and keeps the legacy `name` field synchronized. On success, it returns `200 OK` with the updated profile fields:

```json
{
    "data": {
        "id": "1",
        "name": "New Customer Name",
        "phone": "+966530697231",
        "email": "customer@example.com",
        "preferred_language": "ar"
    }
}
```

All fields are optional:

| Field                | Type           | Rules                                        |
| -------------------- | -------------- | -------------------------------------------- |
| `full_name`          | string or null | Maximum 100 characters                       |
| `email`              | email or null  | Maximum 120 characters; unique when supplied |
| `preferred_language` | string         | `ar` or `en`                                 |

Only the supplied fields are updated. `phone`, VIP status, and order totals cannot be updated through this endpoint.

### Address representation

Address resources expose:

```json
{
    "id": "1",
    "title_ar": "المنزل",
    "city_ar": "تبوك",
    "district_ar": "الورود",
    "street_details": "شارع 1",
    "is_default": true
}
```

### List addresses

`GET /addresses`

Returns only the authenticated customer's addresses, with the default address first.

### Create an address

`POST /addresses`

Required fields: `title_ar` (max 50), `city_ar` (max 50), and `district_ar` (max 100).

Optional fields:

| Field                                    | Type           | Rules                                                         |
| ---------------------------------------- | -------------- | ------------------------------------------------------------- |
| `title_en`                               | string or null | Maximum 50 characters                                         |
| `city_en`                                | string or null | Maximum 50 characters                                         |
| `district_en`                            | string or null | Maximum 100 characters                                        |
| `street_details_ar`, `street_details_en` | string or null | Maximum 255 characters                                        |
| `latitude`                               | number or null | -90 to 90                                                     |
| `longitude`                              | number or null | -180 to 180                                                   |
| `notes`                                  | string or null | Maximum 1,000 characters                                      |
| `is_default`                             | boolean        | Defaults to false; a customer's first address is made default |

If `is_default` is true, the customer's previous default is unset.

### Update an address

`PATCH /addresses/{address}`

Accepts the same fields as address creation, all optional. The address must belong to the authenticated customer; otherwise the API returns `404`.

### Delete an address

`DELETE /addresses/{address}`

Deletes an owned address and returns `204 No Content`. Deleting an address does not automatically designate another address as default.

## Checkout and orders

### Checkout overview and policy

- **Authentication required**: Guest checkout is not supported. All checkout requests require an active customer bearer token (`Authorization: Bearer <token>`).
- **Fulfillment modes**:
    - `pickup`: Customer picks up the order at the store. Delivery fee is `0.00 SAR`. Store address and support contact are returned in the response for customer guidance.
    - `delivery`: Home delivery to customer's saved address (`address_id`). Delivery fee and minimum order requirements are calculated dynamically from the selected delivery neighborhood.
- **Payment methods**:
    - `cod`: Cash on delivery / الدفع عند الاستلام (available when `cash_on_delivery_enabled` is true in store settings).
    - `online`: Electronic payment (available when `online_enabled` is true in store settings).
- **Authoritative pricing**: The client must never calculate totals locally. Prices, discounts, delivery fees, and final totals are calculated and verified authoritatively on the backend via the quote endpoint.
- **Store operations & capacity**: Orders and quotes are automatically rejected if order intake is paused or closed (`STORE_CLOSED`), or if hourly store order intake limits are reached (`ORDER_CAPACITY_REACHED`).

---

### Step 1: Request a checkout quote

`POST /checkout/quote`

Calculates authoritative totals, verifies catalog product availability, validates options, verifies delivery coverage and fees, and reserves a quote UUID valid for **10 minutes**.

#### Request fields

| Field                          | Type    | Rules                                                                                                                                                                       |
| ------------------------------ | ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `delivery_mode`                | string  | **Required**. `pickup` or `delivery`                                                                                                                                        |
| `address_id`                   | integer | **Required if `delivery_mode` is `delivery`**. Must be an existing address owned by the authenticated customer                                                              |
| `neighborhood_id`              | integer | Optional. Specific delivery neighborhood ID from `startup-lookups`. If omitted, the server automatically matches the address `district_ar` to active delivery neighborhoods |
| `coupon_code`                  | string  | Optional. Promo coupon code (max 30 characters)                                                                                                                             |
| `items`                        | array   | **Required**. 1–30 item entries                                                                                                                                             |
| `items.*.product_id`           | integer | **Required**. Must exist, be available, and belong to an active category                                                                                                    |
| `items.*.size_id`              | integer | Optional / required if product has size variants                                                                                                                            |
| `items.*.flavor_id`            | integer | Optional. Selected single flavor option                                                                                                                                     |
| `items.*.addon_ids`            | array   | Optional. Array of distinct add-on option IDs (max 20)                                                                                                                      |
| `items.*.quantity`             | integer | **Required**. 1–50 per item                                                                                                                                                 |
| `items.*.special_instructions` | string  | Optional special kitchen note (max 255 characters)                                                                                                                          |

#### Delivery quote example

```http
POST /api/v1/checkout/quote
Authorization: Bearer <access_token>
Content-Type: application/json
```

```json
{
    "delivery_mode": "delivery",
    "address_id": 5,
    "neighborhood_id": 10,
    "coupon_code": "WELCOME20",
    "items": [
        {
            "product_id": 1,
            "size_id": 2,
            "flavor_id": 3,
            "addon_ids": [4, 7],
            "quantity": 2,
            "special_instructions": "زيادة ليمون ودبس رمان"
        }
    ]
}
```

#### Pickup quote example

```json
{
    "delivery_mode": "pickup",
    "items": [
        {
            "product_id": 1,
            "size_id": 2,
            "quantity": 1
        }
    ]
}
```

#### Successful quote response: `200 OK`

```json
{
    "data": {
        "quote_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
        "expires_at": "2026-10-29T21:40:00+00:00",
        "delivery_mode": "delivery",
        "delivery_address": {
            "id": "5",
            "title_ar": "المنزل",
            "title_en": "Home",
            "city_ar": "تبوك",
            "city_en": null,
            "district_ar": "حي المروج",
            "district_en": null,
            "street_details": "شارع الأمير فهد",
            "latitude": 28.3835,
            "longitude": 36.5662,
            "notes": "الشقة 4 الدور الثاني"
        },
        "delivery_neighborhood": {
            "id": "10",
            "name": "حي المروج",
            "delivery_fee": 15.0,
            "minimum_order_amount": 30.0
        },
        "items": [
            {
                "product_id": 1,
                "size_id": 2,
                "product_name_ar": "ورق عنب محشي",
                "product_name_en": "Stuffed Grape Leaves",
                "size_name_ar": "كبير",
                "size_name_en": "Large",
                "piece_count": 24,
                "image_url": "https://restaurant.example/storage/products/grape-leaves.jpg",
                "quantity": 2,
                "unit_price": 35.0,
                "line_total": 70.0,
                "special_instructions": "زيادة ليمون ودبس رمان",
                "options": [
                    {
                        "option_id": 4,
                        "option_name_ar_snapshot": "دبس رمان إضافي",
                        "option_name_en_snapshot": "Extra Pomegranate Molasses",
                        "option_price_snapshot": "3.00"
                    }
                ]
            }
        ],
        "subtotal": 70.0,
        "product_discount": 0.0,
        "coupon_discount": 10.0,
        "delivery_fee": 15.0,
        "tax_amount": 0.0,
        "total": 75.0,
        "currency": "SAR",
        "currency_code": "SAR",
        "currency_label_ar": "ر.س",
        "currency_label_en": "SAR",
        "estimated_prep_time_minutes": 30,
        "coupon": {
            "code": "WELCOME20",
            "is_valid": true
        }
    }
}
```

---

### Step 2: Place an order

`POST /orders`

Atomically places the order from an unexpired quote. Rechecks store status, hourly capacity, pricing, payment settings, item availability, and delivery area availability within an atomic database transaction.

#### Required header

All order submissions **must** include an `Idempotency-Key` header with a unique client-generated string (1–128 characters). If network connectivity drops or the request times out, retrying with the same key safely returns the created order (`200 OK`) without double-charging or duplicating the order.

```http
Idempotency-Key: ord-chk-20261029-982341
```

#### Request body

```json
{
    "quote_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "payment_method": "cod",
    "special_notes": "يرجى رن الجرس وعدم الاتصال بالهاتف عند الوصول",
    "contact_phone": "+966551234567",
    "scheduled_day": "tomorrow",
    "scheduled_start_time": "19:30",
    "scheduled_end_time": "20:00"
}
```

| Field                  | Type          | Rules                                                                                                         |
| ---------------------- | ------------- | ------------------------------------------------------------------------------------------------------------- |
| `quote_id`             | string (UUID) | **Required**. Must match an active, unexpired quote for the authenticated customer                            |
| `payment_method`       | string        | **Required**. `cod` (Cash on delivery) or `online` (when enabled in store settings)                           |
| `special_notes`        | string        | Optional delivery or chef instructions (max 1,000 characters)                                                 |
| `contact_phone`        | string        | Optional alternative contact phone for this order (max 20 characters); defaults to the customer account phone |
| `scheduled_day`        | string        | Optional. `today` or `tomorrow`; required together with both schedule times                                   |
| `scheduled_start_time` | string        | Optional local store time in `HH:mm` format; must be later than now when `scheduled_day` is `today`           |
| `scheduled_end_time`   | string        | Optional local store time in `HH:mm` format; must be later than the start time                                |

When the schedule fields are omitted, the order is placed immediately. Scheduled orders return `scheduled_start_at` and `scheduled_end_at` as ISO 8601 timestamps in customer and manager order resources; both values are `null` for unscheduled orders.

#### Successful response: `201 Created` (or `200 OK` on idempotent retry)

Returns the full canonical Order Resource (documented in the Order Details Screen API section below).

---

### Step 3: Order Details Screen API

`GET /orders/{order}`

Fetches the complete, comprehensive data structure required to render the **Order Details Screen** in the customer mobile or web application.

- The order must belong to the authenticated customer; accessing another user's order returns `404 Not Found`.
- All prices, product names, sizes, and options are preserved from the historical purchase-time snapshot.
- Includes pre-formatted bilingual labels for statuses, payment methods, delivery modes, and currency, allowing immediate UI presentation without hardcoding logic on the client.

#### Order Details response: `200 OK`

```json
{
    "data": {
        "id": "14",
        "order_number": "ORD-B8X7K9LP2M01",
        "created_at": "2026-10-29T18:25:30+00:00",
        "updated_at": "2026-10-29T18:25:30+00:00",
        "status": "received",
        "status_label_ar": "تم استلام الطلب",
        "status_label_en": "Order Received",
        "can_cancel": true,
        "delivery_mode": "delivery",
        "delivery_mode_label_ar": "توصيل",
        "delivery_mode_label_en": "Home Delivery",
        "payment_method": "cod",
        "payment_method_label_ar": "الدفع عند الاستلام",
        "payment_method_label_en": "Cash on Delivery",
        "payment_status": "pending",
        "payment_status_label_ar": "معلق",
        "payment_status_label_en": "Pending",
        "delivery_address": {
            "id": "5",
            "title_ar": "المنزل",
            "title_en": "Home",
            "city_ar": "تبوك",
            "city_en": null,
            "district_ar": "حي المروج",
            "district_en": null,
            "street_details": "شارع الأمير فهد",
            "latitude": 28.3835,
            "longitude": 36.5662,
            "notes": "الشقة 4 الدور الثاني"
        },
        "pickup_store_details": null,
        "items": [
            {
                "id": "22",
                "product_id": "1",
                "product_name_ar": "ورق عنب محشي",
                "product_name_en": "Stuffed Grape Leaves",
                "image_url": "https://restaurant.example/storage/products/grape-leaves.jpg",
                "size_name_ar": "كبير",
                "size_name_en": "Large",
                "piece_count": 24,
                "quantity": 2,
                "unit_price": 35.0,
                "line_total": 70.0,
                "special_instructions": "زيادة ليمون ودبس رمان",
                "options": [
                    {
                        "id": "4",
                        "name_ar": "دبس رمان إضافي",
                        "name_en": "Extra Pomegranate Molasses",
                        "price": 3.0
                    }
                ]
            }
        ],
        "subtotal": 70.0,
        "product_discount": 0.0,
        "coupon_discount": 0.0,
        "delivery_fee": 15.0,
        "tax_amount": 0.0,
        "total": 85.0,
        "currency": "SAR",
        "currency_label_ar": "ر.س",
        "currency_label_en": "SAR",
        "special_notes": "يرجى رن الجرس وعدم الاتصال بالهاتف عند الوصول",
        "estimated_prep_time_minutes": 30,
        "status_timeline": [
            {
                "status": "received",
                "status_label_ar": "تم استلام الطلب",
                "status_label_en": "Order Received",
                "notes": "Order placed by customer.",
                "created_at": "2026-10-29T18:25:30+00:00"
            }
        ]
    }
}
```

#### Pickup order differences in details screen

When `delivery_mode === "pickup"`:

- `delivery_address` is `null`.
- `pickup_store_details` is populated with the store's physical location and contact phone so the app can render store navigation and pickup instructions:

```json
{
    "pickup_store_details": {
        "store_name_ar": "أرواق العنب",
        "store_name_en": "Arwaq Al-Eneab",
        "address": "تبوك، المملكة العربية السعودية",
        "support_phone": "+966500000000"
    }
}
```

---

### Step 4: Real-time status tracking API

`GET /orders/{order}/track`

A lightweight endpoint designed specifically for live order tracking polling in customer mobile and web applications without reloading heavy item details.

#### Tracking response: `200 OK`

```json
{
    "data": {
        "id": "14",
        "order_number": "ORD-B8X7K9LP2M01",
        "status": "preparing",
        "status_label_ar": "قيد التحضير",
        "status_label_en": "Preparing",
        "can_cancel": false,
        "delivery_mode": "delivery",
        "payment_status": "pending",
        "estimated_prep_time_minutes": 30,
        "dispatched_at": null,
        "delivered_at": null,
        "updated_at": "2026-10-29T18:28:10+00:00",
        "status_timeline": [
            {
                "status": "received",
                "status_label_ar": "تم استلام الطلب",
                "status_label_en": "Order Received",
                "notes": "Order placed by customer.",
                "created_at": "2026-10-29T18:25:30+00:00"
            },
            {
                "status": "preparing",
                "status_label_ar": "قيد التحضير",
                "status_label_en": "Preparing",
                "notes": "جاري طهي الطلب في المطبخ",
                "created_at": "2026-10-29T18:28:10+00:00"
            }
        ]
    }
}
```

---

### Order status lifecycle and state transitions

An order progresses through standard lifecycle stages recorded chronologically in `status_timeline`:

```
                 ┌─────────────┐
                 │  received   │◄── Order placed by customer
                 └──────┬──────┘
                        │ (Manager/kitchen accepts)
                        ▼
                 ┌─────────────┐
                 │  preparing  │
                 └──────┬──────┘
            ┌───────────┴───────────┐
            │ (pickup)              │ (delivery)
            ▼                       ▼
   ┌──────────────────┐    ┌───────────────────┐
   │ ready_for_pickup │    │ out_for_delivery  │
   └────────┬─────────┘    └─────────┬─────────┘
            │                        │
            └───────────┬────────────┘
                        │ (Handed over / paid)
                        ▼
                 ┌─────────────┐
                 │  delivered  │
                 └─────────────┘

   * Cancellation / Rejection:
     - `cancelled`: Customer can cancel only while status is `received` (or `pending_confirmation`).
     - `rejected`: Store rejects order before preparation.
```

#### Status reference table

| Status Key             | Arabic Label    | English Label        | Meaning & App Display Guidance                                                         |
| ---------------------- | --------------- | -------------------- | -------------------------------------------------------------------------------------- |
| `received`             | تم استلام الطلب | Order Received       | Order created, awaiting confirmation. Customer **can cancel** (`can_cancel: true`).    |
| `pending_confirmation` | بانتظار التأكيد | Pending Confirmation | Legacy alias for received; customer can cancel.                                        |
| `preparing`            | قيد التحضير     | Preparing            | Kitchen is cooking/assembling items. Customer cannot cancel.                           |
| `ready_for_pickup`     | جاهز للاستلام   | Ready for Pickup     | _(Pickup mode)_ Order packed at counter ready for customer pickup. Show store address. |
| `out_for_delivery`     | في الطريق إليك  | Out for Delivery     | _(Delivery mode)_ Courier is en route to customer address (`dispatched_at` is set).    |
| `delivered`            | تم التوصيل      | Delivered            | Order successfully received by customer (`delivered_at` is set; COD marked `paid`).    |
| `cancelled`            | ملغي            | Cancelled            | Order cancelled by customer or store.                                                  |
| `rejected`             | مرفوض           | Rejected             | Store unable to fulfill order.                                                         |

---

### Step 5: Cancel an order

`POST /orders/{order}/cancel`

Allows the authenticated customer to cancel their order **prior to preparation**.

- Cancellation is allowed **only** when `status` is `received` or `pending_confirmation`.
- Once the store begins `preparing` (or subsequent stages), the API rejects cancellation with `409 Conflict` and error code `ORDER_CANNOT_BE_CANCELLED`.
- Cancelling an order automatically releases any used coupon redemption so the customer may apply the coupon to a future order.
- Writes a status transition entry to `order_status_logs`.

#### Successful response: `200 OK`

Returns the updated Order Resource with `status: "cancelled"`, `can_cancel: false`, and the cancellation appended to `status_timeline`.

---

### Step 6: List customer orders

`GET /orders`

Returns a paginated list of all orders belonging to the authenticated customer, ordered newest first (`20` per page), utilizing standard Laravel pagination (`data`, `links`, and `meta`).

```http
GET /api/v1/orders?page=1
Authorization: Bearer <access_token>
```

---

## Errors and validation

### Validation error format (`422 Unprocessable Content`)

Standard Laravel validation errors containing `message` and field-level `errors`:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "address_id": ["Delivery address is required."],
        "items.0.quantity": ["The items.0.quantity field must be at least 1."]
    }
}
```

### Domain error format

Business logic failures return a structured domain error envelope:

```json
{
    "message": "The minimum order amount for delivery to حي المروج is 30.00 SAR.",
    "code": "MINIMUM_ORDER_NOT_MET",
    "errors": {
        "subtotal": [
            "The minimum order amount for delivery to حي المروج is 30.00 SAR."
        ]
    }
}
```

### Domain error codes reference

| Code                         | HTTP Status | Trigger & Meaning                                                                                 |
| ---------------------------- | ----------- | ------------------------------------------------------------------------------------------------- |
| `ADDRESS_REQUIRED`           | `422`       | `address_id` is required when `delivery_mode` is `delivery`.                                      |
| `ADDRESS_NOT_FOUND`          | `422`       | The specified `address_id` does not exist or does not belong to the authenticated customer.       |
| `DELIVERY_ZONE_UNAVAILABLE`  | `422`       | The address neighborhood is not served by store delivery or is temporarily disabled.              |
| `MINIMUM_ORDER_NOT_MET`      | `422`       | Order subtotal does not meet the minimum delivery amount for the chosen neighborhood.             |
| `STORE_CLOSED`               | `409`       | Restaurant is closed or order intake is temporarily paused by the manager.                        |
| `ORDER_CAPACITY_REACHED`     | `409`       | Store has reached maximum orders per hour capacity limit.                                         |
| `PAYMENT_METHOD_UNAVAILABLE` | `409`       | Selected payment method (COD or Online) is disabled in store settings.                            |
| `QUOTE_EXPIRED`              | `409`       | Quote has expired (10 minutes) or current catalog prices/items changed.                           |
| `ORDER_CANNOT_BE_CANCELLED`  | `409`       | Order has already moved to preparation and can no longer be cancelled by customer.                |
| `PRODUCT_UNAVAILABLE`        | `422`       | Product is disabled or belongs to an inactive category.                                           |
| `INVALID_SELECTION`          | `422`       | Selected size, flavor, or add-on does not belong to the product or violates required group rules. |
| `COUPON_INVALID`             | `422`       | Coupon is expired, below minimum order value, uncombinable, or exceeds usage limits.              |
| `CUSTOMER_BLOCKED`           | `403`       | Customer account has been suspended or blocked.                                                   |
| `INVALID_OTP`                | `422`       | Submitted OTP verification code is incorrect or expired.                                          |
| `OTP_RATE_LIMITED`           | `429`       | Exceeded OTP request throttling.                                                                  |
| `OTP_DELIVERY_UNAVAILABLE`   | `503`       | SMS provider not configured in non-local environments.                                            |

---

## Push notification open tracking

`POST /api/v1/notifications/{notification}/opened` records that the authenticated customer opened a notification. Send the customer Sanctum token in the `Authorization: Bearer ...` header. The event is accepted only when the notification has a recipient record for that customer; repeated requests are safe. Success response: `{"data":{"opened":true}}`. Unknown notifications return `404`.

Push data keys include `notification_id`, optional `destination` (`/home` or `/orders`), and optional `coupon_code`. Coupon codes are informational and are not applied automatically.

## Not currently implemented

- SMS provider integration for production OTP delivery (local/testing environments simulate OTP delivery).
- Card payment gateways, external payment-provider handoffs, webhooks, or automated card refunds (COD is fully supported; online toggle is supported).
- Guest checkout without authentication.
- Refresh tokens.
