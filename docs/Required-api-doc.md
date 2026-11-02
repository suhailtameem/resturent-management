# Customer Management API Requirements

This document specifies the manager APIs needed to replace the mock data in `lib/screens/customers/customers_management_screen.dart` and provide useful customer account management.

## Scope

- Use the manager API base path `/api/v1/manager`.
- All endpoints require the manager Sanctum bearer token through `auth:staff_api` and the `staff.role:super_admin` middleware.
- Managers can create customer profiles and edit their contact information. A manager-created account must remain unverified until the customer completes the normal phone/OTP verification flow; the manager API must not issue customer tokens or bypass OTP.
- Track staff account review separately from phone ownership verification. A manager verification mark is an administrative attestation, not proof that the customer controls the phone number.
- Customer management needs list/search/segments, customer summary and addresses, order history, profile updates, VIP status, account block/unblock, statistics, and CSV export.
- Wallet balances, wallet credits, loyalty point adjustments, coupon grants, and coupon sending are out of scope. Remove those actions and displays from the customer management screen.
- Do not hard-delete customers. `orders.user_id` is a cascading foreign key, so deleting a customer would also delete order history. Block the account instead.
- Call and WhatsApp buttons are client-side actions using the customer's phone number; they do not need backend endpoints.

## Existing backend support and gaps (verified against this project)

- Manager routes are under `/api/v1/manager` and protected by `auth:staff_api` plus `staff.role:super_admin`. The spec's role and authentication description now matches this middleware.
- The `users` table has name, phone, full name, email, VIP flag, status, and legacy order aggregate columns. Phone is nullable and has no unique index. Email is currently `NOT NULL UNIQUE`, which conflicts with manager-created profiles that may omit email; make it nullable before implementing that contract.
- `phone_verified_at`, `manager_verified_at`, and `manager_verified_by_staff_user_id` do not exist. OTP success currently creates/updates a user but does not record when that phone was verified. Add these fields and persist OTP verification time in the OTP flow; keep staff review fields distinct.
- `User` already relates to `CustomerAddress` and `Order`; order items store product name snapshots and quantities, so lifetime favorite items can be computed without depending on products that may later change.
- `total_orders_count`, `total_spent_amount`, and `last_order_at` are not maintained by checkout or order status changes. Compute manager-facing aggregates from completed/delivered orders; don't trust the stored defaults.
- Existing order states include `delivered` and `completed`; some historic rows may lack `delivered_at`, so use `COALESCE(delivered_at, updated_at)` as the completion timestamp for date-window metrics.
- `orders.user_id` is declared cascade-on-delete in migrations, but the inspected live database reports MyISAM, which does not enforce foreign keys. Still avoid hard deletion to preserve history and because other configured engines do cascade.
- At inspection time there were no manager customer routes or manager customer resource. The implementation adds them and reuses `ManagerOrderSummaryResource` for customer order history.

Implementation choices: use the existing Manager API controller/request/resource layout. Normalize Saudi phone numbers to the OTP flow's `+9665XXXXXXXX` canonical form and check duplicates across common legacy formats; persist canonical values for new/edited numbers. Since existing phone data may be legacy-formatted, do not add a blind unique index on `users.phone` until existing values have been normalized and collision-checked. Use database-derived aggregates and a shared helper so list, profile, stats, and CSV agree.

The normalized-phone duplicate check is application-level until legacy records can be safely normalized and a database unique constraint can be added. The stats comparison returns `null` for the active-customer percentage when the preceding period has no active customers, because a percentage change from zero is undefined.

## Customer segments and metric definitions

- `vip`: `is_vip` is true.
- `frequent`: at least 10 completed/delivered orders. Keep this threshold in one backend constant so it can be changed consistently.
- `new`: customer account created in the last 30 days.
- `inactive`: no completed/delivered order in the last 30 days, including customers who have never completed an order.
- Segments are independent of account status; a blocked customer can also be VIP or inactive. Return the account `status` separately.
- KPI `active_customers` means customers with a completed/delivered order in the selected stats period (the trailing 30 days by default). This is different from account `status=active`.
- `total_orders_count` and `total_spent_amount` use completed/delivered orders only; cancelled and rejected orders must not count. `last_order_at` is the most recent customer order timestamp, and `favorite_item` is the most frequently purchased item by quantity from completed/delivered orders.
- `average_order_value` is completed/delivered revenue divided by completed/delivered order count for the selected period. Return zero when the period has no completed orders.

## Endpoint summary

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/manager/customers/stats` | Customer KPIs and comparison values |
| `POST` | `/api/v1/manager/customers` | Create a customer profile with phone verification pending |
| `GET` | `/api/v1/manager/customers` | Searchable, filterable, paginated customer list |
| `GET` | `/api/v1/manager/customers/{customer}` | Customer profile, addresses, and lifetime summary |
| `GET` | `/api/v1/manager/customers/{customer}/orders` | Paginated order history for one customer |
| `PATCH` | `/api/v1/manager/customers/{customer}` | Update customer name, phone, email, and VIP flag |
| `PATCH` | `/api/v1/manager/customers/{customer}/verification` | Mark or clear manager account verification |
| `PATCH` | `/api/v1/manager/customers/{customer}/status` | Block or reactivate a customer account |
| `GET` | `/api/v1/manager/customers/export` | Download filtered customer records as CSV |

Register `/customers/export`, `/customers/stats`, and the collection `POST /customers` before `/customers/{customer}` so literal routes are not treated as IDs.

## 1. Create a customer profile

`POST /api/v1/manager/customers`

Create a customer profile for the customer to claim through the existing OTP sign-in flow. Require `full_name` and `phone`; accept optional `email`. Normalize phone numbers consistently with customer OTP authentication and enforce uniqueness on the normalized phone. Return `201` with the customer resource. Set account status to `active`, `phone_verified_at` to `null`, and manager verification fields to `null`. Do not accept a password, OTP, verification flag, or token in this request, and do not issue a customer token. The customer must complete OTP verification before their phone is considered verified.

Example request:

```json
{"full_name":"نورة السبيعي","phone":"0559981240","email":null}
```

## 2. Customer statistics

`GET /api/v1/manager/customers/stats`

Optional query parameters: `from` and `to` in `YYYY-MM-DD`. With no dates, use the current 30-day period and compare it with the preceding 30-day period.

Return:

```json
{
  "data": {
    "total_customers": 1420,
    "active_customers": 890,
    "vip_customers": 245,
    "average_order_value": "84.00",
    "currency": "SAR",
    "period": {"from": "2026-10-01", "to": "2026-10-31"},
    "comparison": {
      "active_customers_change_percent": 12.0,
      "average_order_value_change": "5.00"
    }
  }
}
```

`total_customers` includes active and blocked accounts. If the client keeps the growth labels shown in the mockup, return comparison values against the immediately preceding period with the same duration; otherwise remove those labels instead of hardcoding them.

## 3. Customer list, search, and segment filters

`GET /api/v1/manager/customers`

Query parameters:

- `search`: match customer name, phone, email, or saved address city/district/street.
- `segment`: `all` (default), `vip`, `frequent`, `new`, or `inactive`.
- `status`: `all` (default), `active`, or `blocked` account status.
- `sort_by`: `last_order_at` (default), `total_orders_count`, `total_spent_amount`, or `created_at`.
- `sort_order`: `desc` (default) or `asc`.
- `page`: one-based page number.
- `per_page`: default 15, maximum 50.

Return a Laravel-style paginated `data` collection and `links`/`meta` object. Each list row must include:

```json
{
  "id": 101,
  "full_name": "خالد الشمري",
  "phone": "0530697231",
  "email": null,
  "status": "active",
  "is_vip": true,
  "segments": ["vip", "frequent"],
  "primary_address": {
    "city": "تبوك",
    "district": "حي المروج",
    "street_details": "شارع الملك فهد"
  },
  "total_orders_count": 24,
  "total_spent_amount": "1420.00",
  "currency": "SAR",
  "favorite_item": "بوكس ميكس ورق عنب وملفوف (كبير)",
  "last_order_at": "2026-10-31T10:48:00+03:00"
}
```

Return `primary_address` and `last_order_at` as `null` when unavailable. Do not return a fabricated customer rating; ratings are not currently stored in the backend.

## 4. Customer profile

`GET /api/v1/manager/customers/{customer}`

Return the customer identity and account status, VIP flag, phone and manager verification fields, creation time, saved addresses (Arabic and English title/city/district/street fields, coordinates, notes, and default flag), computed order count/spend/favorite/last-order summary, and currency. Never return password hashes, OTPs, or access tokens.

## 5. Customer order history

`GET /api/v1/manager/customers/{customer}/orders`

Query parameters: `page`, `per_page` (default 15, maximum 50), and optional `status` using the existing manager order status values or `all`.

Return the paginated manager order summary fields already used by `GET /api/v1/manager/orders`: order number, status, date, delivery mode/address, item preview/count, payment status/method, and total amount. Order records must be constrained by the bound customer's ID. The existing `GET /api/v1/manager/orders/{order}` can be used to open any selected order's full details.

## 6. Update customer profile and VIP flag

`PATCH /api/v1/manager/customers/{customer}`

Allow partial updates to `full_name`, `phone`, `email`, and `is_vip`. Validate email as unique while ignoring the current customer. Normalize phone the same way as OTP authentication and validate uniqueness. When phone changes, clear `phone_verified_at`, clear manager verification fields, and revoke the customer's existing Sanctum tokens; the customer must verify the new number through OTP again. Return the updated customer resource. Setting `is_vip` true/false powers the existing VIP segment. Never let this endpoint directly set phone or manager verification timestamps.

Example:

```json
{
  "full_name": "خالد الشمري",
  "email": "khalid@example.test",
  "is_vip": true
}
```

## 7. Verify customer account (manager review)

`PATCH /api/v1/manager/customers/{customer}/verification`

Request:

```json
{"is_verified":true}
```

When true, set `manager_verified_at` and `manager_verified_by_staff_user_id` to the current time and authenticated staff user. When false, clear both values. Return the customer ID, `phone_verified_at`, `manager_verified_at`, and `manager_verified_by_staff_user_id`. This action records staff review only; it must not set `phone_verified_at`, bypass OTP, or grant a customer token. If the phone changes, clear the manager review mark because the reviewed identity/contact has changed.

The customer resource should expose `phone_verified_at` and manager verification status/time. Persist `phone_verified_at` in the customer OTP verification flow. Add nullable `manager_verified_at` and `manager_verified_by_staff_user_id` fields (with an appropriate staff-user foreign key) if they do not exist yet.

## 8. Block and reactivate customer account

`PATCH /api/v1/manager/customers/{customer}/status`

Request:

```json
{"status": "blocked"}
```

Accept only `active` or `blocked`. When blocking an account, revoke all of that customer's Sanctum tokens so existing sessions stop working as well as preventing future OTP login. Reactivation sets status to `active`; the customer can sign in again through the normal OTP flow. Return the updated customer ID and status. This is the safe replacement for deleting a customer.

## 9. Export customer list

`GET /api/v1/manager/customers/export`

Accept the same `search`, `segment`, and `status` filters as the customer list. Return a UTF-8 CSV attachment compatible with Excel, including a UTF-8 BOM for Arabic text. Export only customer fields needed by managers: name, phone, email, account status, VIP flag, primary address, completed order count, completed spend, last order date, and favorite item. Do not include wallet/coupon fields, passwords, tokens, or other secrets. The export action must apply the same filters as the visible list.

## Error and access behavior

- Return `401` without a valid manager token and `403` for a staff token without the `super_admin` role, following the existing manager API middleware.
- Return `404` for an unknown customer or an order that does not belong to the requested customer.
- Return Laravel validation errors with `422` for invalid filters or update payloads.
- Preserve customer records and order history when an account is blocked.
