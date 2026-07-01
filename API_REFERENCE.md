# API_REFERENCE.md
## WTF Inventory Backend — Complete API Reference

---

## Overview

**Base URL (Production):** `https://inventory-backend-3ktz.onrender.com`
**Base URL (Local):** `http://127.0.0.1:8000`
**API Prefix:** `/api/v1/`
**Format:** All requests and responses use JSON.

### Required Headers (All Requests)
```
Accept: application/json
Content-Type: application/json
```

### Authentication Header (Protected Routes)
```
Authorization: Bearer <plain-text-sanctum-token>
```

### Response Envelope
There is no standard wrapper envelope. Responses are direct objects or arrays.
Error responses follow this shape:
```json
{
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```
The `errors` key is only present on 422 responses.

---

## Table of Contents

- [Authentication](#authentication)
  - [Register Company](#post-apiv1register)
  - [Login](#post-apiv1login)
  - [Logout](#post-apiv1logout)
- [Password Reset](#password-reset)
  - [Forgot Password](#post-apiv1passwordforgot)
  - [Reset Password](#post-apiv1passwordreset)
- [Dashboard](#dashboard)
  - [Summary](#get-apiv1dashboard)
- [Categories](#categories)
  - [List](#get-apiv1categories)
  - [Create](#post-apiv1categories)
  - [Show](#get-apiv1categoriescategory)
  - [Update](#putpatch-apiv1categoriescategory)
  - [Delete](#delete-apiv1categoriescategory)
- [Suppliers](#suppliers)
  - [List](#get-apiv1suppliers)
  - [Create](#post-apiv1suppliers)
  - [Show](#get-apiv1supplierssupplier)
  - [Update](#putpatch-apiv1supplierssupplier)
  - [Delete](#delete-apiv1supplierssupplier)
- [Products](#products)
  - [List](#get-apiv1products)
  - [Create](#post-apiv1products)
  - [Show](#get-apiv1productsproduct)
  - [Update](#putpatch-apiv1productsproduct)
  - [Delete](#delete-apiv1productsproduct)
- [Inventory Transactions](#inventory-transactions)
  - [List](#get-apiv1transactions)
  - [Create](#post-apiv1transactions)
  - [Show](#get-apiv1transactionstransaction)
- [Invitations](#invitations)
  - [List](#get-apiv1invitations)
  - [Send](#post-apiv1invitations)
  - [Accept](#post-apiv1invitationsaccepttoken)
  - [Cancel](#patch-apiv1invitationsinvitationcancel)
- [Users](#users)
  - [List](#get-apiv1users)
  - [Deactivate](#patch-apiv1usersuserdeactivate)
  - [Reactivate](#patch-apiv1usersuserreactivate)
  - [Revoke Own Tokens](#post-apiv1merevoke-tokens)
  - [Revoke User Tokens](#post-apiv1usersuserrevoke-tokens)

---

## Authentication

---

### `POST /api/v1/register`

Creates a new company and owner account in a single atomic transaction.

**Authentication:** None (public)
**Rate Limit:** 5 requests per minute per IP

**Request Body:**
```json
{
  "company_name": "Acme Corp",
  "company_email": "info@acme.com",
  "company_phone": "555-1234",
  "company_address": "123 Main St",
  "owner_name": "Jane Doe",
  "owner_email": "jane@acme.com",
  "owner_password": "securepassword123",
  "owner_password_confirmation": "securepassword123"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| company_name | required, string |
| company_email | required, valid email, unique across companies |
| company_phone | required, string |
| company_address | optional, string |
| owner_name | required, string |
| owner_email | required, valid email, unique across users |
| owner_password | required, string, min 8 characters, must match confirmation |
| owner_password_confirmation | required |

**Response: 201 Created**
```json
{
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@acme.com",
    "company_id": 1,
    "role": "owner",
    "deactivated_at": null,
    "created_at": "2026-07-01T08:26:09.000000Z",
    "updated_at": "2026-07-01T08:26:09.000000Z"
  },
  "company": {
    "id": 1,
    "name": "Acme Corp",
    "email": "info@acme.com",
    "phone": "555-1234",
    "address": null,
    "created_at": "2026-07-01T08:26:07.000000Z",
    "updated_at": "2026-07-01T08:26:07.000000Z"
  },
  "token": "1|abc123..."
}
```

**Error Responses:**
- `422` — Validation failed (duplicate email, missing required field, password mismatch)

**Notes:**
- The owner's `role` is always set to `"owner"` — this cannot be changed through any API endpoint.
- The `token` in the response is the only time this token's plain-text value is visible. Store it immediately.
- The company and user are created in a single database transaction — if either fails, neither is created.

---

### `POST /api/v1/login`

Authenticates an existing user and returns a Sanctum token.

**Authentication:** None (public)
**Rate Limit:** 5 requests per minute per IP

**Request Body:**
```json
{
  "email": "jane@acme.com",
  "password": "securepassword123"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| email | required, valid email |
| password | required, string |

**Response: 200 OK**
```json
{
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@acme.com",
    "email_verified_at": null,
    "company_id": 1,
    "role": "owner",
    "deactivated_at": null,
    "created_at": "2026-07-01T08:26:09.000000Z",
    "updated_at": "2026-07-01T08:26:09.000000Z"
  },
  "token": "2|xyz789..."
}
```

**Error Responses:**
- `401` — `{"message": "Invalid credentials"}` (either email not found or password wrong — intentionally generic)
- `403` — `{"message": "This account has been deactivated."}` (correct credentials but user is deactivated)
- `422` — Validation failed (missing required field, invalid email format)

**Notes:**
- Does NOT invalidate existing tokens. Multiple tokens may exist simultaneously (multi-device support).
- The deactivation check occurs AFTER password verification to avoid leaking whether an email is registered.

---

### `POST /api/v1/logout`

Deletes the current Bearer token, invalidating this session.

**Authentication:** Required
**Permissions:** Any authenticated role

**Request Body:** None

**Response: 200 OK**
```json
{
  "message": "Logged out successfully"
}
```

**Notes:**
- Only the token used to make THIS request is deleted.
- Other tokens for the same user remain valid (other devices stay logged in).
- To invalidate all sessions, use `POST /api/v1/me/revoke-tokens`.

---

## Password Reset

---

### `POST /api/v1/password/forgot`

Initiates a password reset flow. Sends a reset email if the account exists.

**Authentication:** None (public)
**Rate Limit:** 3 requests per minute per IP (tightest limit — protects against inbox spam)

**Request Body:**
```json
{
  "email": "jane@acme.com"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| email | required, valid email format |

**Response: 200 OK** (always — regardless of whether the email exists)
```json
{
  "message": "If an account exists with that email, a password reset link has been sent."
}
```

**Notes:**
- The response is intentionally identical whether the email is registered or not. This prevents user enumeration attacks.
- The reset token is valid for 1 hour.
- The email is queued — delivery may take a few seconds after the response is returned.
- The reset link in the email points to a placeholder URL (`https://yourapp.com/reset-password?token=...`). When a frontend is built, update the Blade template at `resources/views/emails/password-reset.blade.php`.
- The raw token is included in the email body for development/testing purposes while no frontend exists.

---

### `POST /api/v1/password/reset`

Completes a password reset using a token received by email.

**Authentication:** None (public)
**Rate Limit:** 10 requests per minute per IP

**Request Body:**
```json
{
  "token": "abc123...60charactertoken...",
  "password": "newpassword456",
  "password_confirmation": "newpassword456"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| token | required, string |
| password | required, string, min 8 characters, must match confirmation |
| password_confirmation | required |

**Response: 200 OK**
```json
{
  "message": "Your password has been reset successfully. You may now log in."
}
```

**Error Responses:**
- `410 Gone` — `{"message": "This password reset link is invalid or has expired."}` (token not found, already used, or expired)
- `422` — Validation failed

**Notes:**
- After success, the user must log in via `POST /api/v1/login` using their new password. No token is issued automatically.
- The reset token is marked as used (`used_at` is set). It cannot be used again.
- Does NOT revoke existing Sanctum tokens — the user's existing sessions remain active.

---

## Dashboard

---

### `GET /api/v1/dashboard`

Returns aggregated analytics for the authenticated user's company.

**Authentication:** Required
**Permissions:** All roles (owner, admin, staff)

**Response: 200 OK**
```json
{
  "total_products": 3,
  "total_categories": 2,
  "total_suppliers": 1,
  "total_sale_value": "2394.97",
  "total_cost_value": "967.50",
  "low_stock_products": [
    {
      "id": 2,
      "company_id": 1,
      "category_id": 1,
      "supplier_id": 1,
      "name": "USB Hub",
      "sku": "UH-001",
      "description": null,
      "price": "29.99",
      "cost": "12.00",
      "current_stock": 3,
      "image_url": null,
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "recent_transactions": [
    {
      "id": 5,
      "company_id": 1,
      "product_id": 1,
      "created_by": 2,
      "type": "sale",
      "quantity": -5,
      "notes": null,
      "created_at": "...",
      "updated_at": "...",
      "product": { "id": 1, "name": "Wireless Mouse", "sku": "WM-001", "..." },
      "creator": { "id": 2, "name": "Jane Doe", "email": "jane@acme.com", "..." }
    }
  ],
  "units_sold_this_week": 17,
  "units_sold_last_week": 0,
  "units_sold_this_month": 17,
  "units_sold_last_month": 0,
  "avg_purchase_quantity": "50.0000",
  "avg_sale_quantity": 12
}
```

**Field Descriptions:**

| Field | Description |
|---|---|
| total_products | COUNT of all products in the company |
| total_categories | COUNT of all categories in the company |
| total_suppliers | COUNT of all suppliers in the company |
| total_sale_value | SUM(price × current_stock) — what you could sell current inventory for |
| total_cost_value | SUM(COALESCE(cost, 0) × current_stock) — capital tied up in inventory |
| low_stock_products | Full product objects where current_stock < 10 (includes out-of-stock) |
| recent_transactions | Last 5 transactions, with product and creator eagerly loaded |
| units_sold_this_week | Absolute value of SUM(quantity) for sales in the last 7 days |
| units_sold_last_week | Absolute value of SUM(quantity) for sales 8–14 days ago |
| units_sold_this_month | Absolute value of SUM(quantity) for sales in the last 30 days |
| units_sold_last_month | Absolute value of SUM(quantity) for sales 31–60 days ago |
| avg_purchase_quantity | AVG(quantity) for purchase-type transactions |
| avg_sale_quantity | Absolute value of AVG(quantity) for sale-type transactions |

**Notes:**
- `total_cost_value` uses `COALESCE(cost, 0)` — products without a recorded cost contribute 0 to the total rather than being silently excluded.
- `avg_purchase_quantity` and `avg_sale_quantity` are computed separately (not blended). Blending positive purchase quantities with negative sale quantities produces a meaningless number.
- All values are automatically scoped to the authenticated user's company.

---

## Categories

---

### `GET /api/v1/categories`

Returns all categories belonging to the authenticated user's company.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK**
```json
[
  {
    "id": 1,
    "company_id": 1,
    "name": "Electronics",
    "description": null,
    "created_at": "2026-06-29T03:17:23.000000Z",
    "updated_at": "2026-06-29T03:17:23.000000Z"
  },
  {
    "id": 2,
    "company_id": 1,
    "name": "Office Supplies",
    "description": "Stationery and desk accessories",
    "created_at": "2026-06-29T03:57:11.000000Z",
    "updated_at": "2026-06-29T03:57:11.000000Z"
  }
]
```

---

### `POST /api/v1/categories`

Creates a new category for the authenticated user's company.

**Authentication:** Required
**Permissions:** owner, admin only

**Request Body:**
```json
{
  "name": "Electronics",
  "description": "Electronic devices and accessories"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| name | required, string, max 255 chars, unique within company |
| description | optional, string |

**Response: 201 Created**
```json
{
  "id": 1,
  "company_id": 1,
  "name": "Electronics",
  "description": "Electronic devices and accessories",
  "created_at": "2026-06-29T03:17:23.000000Z",
  "updated_at": "2026-06-29T03:17:23.000000Z"
}
```

**Error Responses:**
- `403` — Insufficient role (staff cannot create categories)
- `422` — Duplicate name within the company, or validation failure

**Notes:**
- `company_id` is set automatically from the authenticated user. It cannot be supplied or overridden by the client.

---

### `GET /api/v1/categories/{category}`

Returns a single category by ID.

**Authentication:** Required
**Permissions:** All roles
**Route Parameter:** `{category}` — integer ID of the category

**Response: 200 OK**
```json
{
  "id": 1,
  "company_id": 1,
  "name": "Electronics",
  "description": null,
  "created_at": "2026-06-29T03:17:23.000000Z",
  "updated_at": "2026-06-29T03:17:23.000000Z"
}
```

**Error Responses:**
- `404` — Category not found, or belongs to a different company (both appear as 404)

---

### `PUT/PATCH /api/v1/categories/{category}`

Updates an existing category. All fields are optional — only supplied fields are updated.

**Authentication:** Required
**Permissions:** owner, admin only
**Route Parameter:** `{category}` — integer ID of the category

**Request Body:** (all fields optional)
```json
{
  "name": "Updated Electronics",
  "description": "Updated description"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| name | optional; if supplied: string, max 255, unique within company (excluding self) |
| description | optional, string or null |

**Response: 200 OK** — Full updated category object

**Error Responses:**
- `403` — Insufficient role
- `404` — Category not found or belongs to different company
- `422` — Duplicate name within company

---

### `DELETE /api/v1/categories/{category}`

Permanently deletes a category.

**Authentication:** Required
**Permissions:** owner, admin only
**Route Parameter:** `{category}` — integer ID of the category

**Response: 204 No Content** — Empty body

**Error Responses:**
- `403` — Insufficient role
- `404` — Category not found or belongs to different company
- `500` — Database constraint violation if category has associated products (RESTRICT foreign key)

**Notes:**
- Hard delete — no soft delete. If the category has products referencing it, the database will reject the deletion with a constraint error. Delete or reassign products first.

---

## Suppliers

---

### `GET /api/v1/suppliers`

Returns all suppliers belonging to the authenticated user's company.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK**
```json
[
  {
    "id": 1,
    "company_id": 1,
    "name": "Global Parts Co",
    "contact_name": "John Smith",
    "email": "orders@globalparts.com",
    "phone": "555-9876",
    "address": "456 Industrial Ave",
    "created_at": "2026-06-29T04:49:26.000000Z",
    "updated_at": "2026-06-29T04:49:26.000000Z"
  }
]
```

---

### `POST /api/v1/suppliers`

Creates a new supplier.

**Authentication:** Required
**Permissions:** owner, admin only

**Request Body:**
```json
{
  "name": "Global Parts Co",
  "contact_name": "John Smith",
  "email": "orders@globalparts.com",
  "phone": "555-9876",
  "address": "456 Industrial Ave"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| name | required, string, max 255, unique within company |
| contact_name | optional, string |
| email | optional, valid email format, unique within company |
| phone | optional, string |
| address | optional, string |

**Response: 201 Created** — Full supplier object

**Error Responses:**
- `403` — Insufficient role
- `422` — Duplicate name or email within company, invalid email format

**Notes:**
- Both `name` uniqueness and `email` uniqueness are scoped per company (composite unique indexes).
- `email` is nullable — multiple suppliers without an email are allowed (MySQL NULL uniqueness semantics: NULL ≠ NULL).
- Setting `email: null` on a supplier that has an email will clear the email field.

---

### `GET /api/v1/suppliers/{supplier}`

Returns a single supplier by ID.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK** — Full supplier object

**Error Responses:**
- `404` — Supplier not found or belongs to different company

---

### `PUT/PATCH /api/v1/suppliers/{supplier}`

Updates an existing supplier. All fields are optional.

**Authentication:** Required
**Permissions:** owner, admin only

**Validation Rules:**

| Field | Rules |
|---|---|
| name | optional; if supplied: string, max 255, unique within company (excluding self) |
| contact_name | optional, string or null |
| email | optional, email format, unique within company (excluding self), or null |
| phone | optional, string or null |
| address | optional, string or null |

**Response: 200 OK** — Full updated supplier object

---

### `DELETE /api/v1/suppliers/{supplier}`

Permanently deletes a supplier.

**Authentication:** Required
**Permissions:** owner, admin only

**Response: 204 No Content**

**Error Responses:**
- `500` — Database constraint if supplier has products referencing it

---

## Products

---

### `GET /api/v1/products`

Returns all products for the company, with category and supplier eagerly loaded.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK**
```json
[
  {
    "id": 1,
    "company_id": 1,
    "category_id": 1,
    "supplier_id": 1,
    "name": "Wireless Mouse",
    "sku": "WM-001",
    "description": null,
    "price": "19.99",
    "cost": "8.50",
    "current_stock": 38,
    "image_url": "https://example.com/mouse.jpg",
    "created_at": "2026-06-29T08:52:02.000000Z",
    "updated_at": "2026-06-30T10:04:23.000000Z",
    "category": {
      "id": 1,
      "company_id": 1,
      "name": "Electronics",
      "description": null,
      "created_at": "...",
      "updated_at": "..."
    },
    "supplier": {
      "id": 1,
      "company_id": 1,
      "name": "Global Parts Co",
      "contact_name": null,
      "email": "orders@globalparts.com",
      "phone": null,
      "address": null,
      "created_at": "...",
      "updated_at": "..."
    }
  }
]
```

---

### `POST /api/v1/products`

Creates a new product.

**Authentication:** Required
**Permissions:** owner, admin only

**Request Body:**
```json
{
  "name": "Wireless Mouse",
  "sku": "WM-001",
  "description": "Ergonomic wireless mouse",
  "price": 19.99,
  "cost": 8.50,
  "category_id": 1,
  "supplier_id": 1,
  "image_url": "https://example.com/mouse.jpg"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| name | required, string, max 255 |
| sku | required, string, max 255, unique within company |
| description | optional, string |
| price | required, numeric, min 0 |
| cost | optional, numeric, min 0 |
| category_id | required, integer, must exist in categories table AND belong to this company |
| supplier_id | required, integer, must exist in suppliers table AND belong to this company |
| image_url | optional, valid URL format |

**Response: 201 Created** — Product object (without eager-loaded relationships)

**Error Responses:**
- `403` — Insufficient role
- `422` — Duplicate SKU, invalid category_id (wrong company or nonexistent), invalid supplier_id, invalid URL format

**Notes:**
- `category_id` and `supplier_id` are validated against the authenticated company's data. Supplying an ID from another company returns 422 (not 403).
- `current_stock` starts at 0 for all new products. It is maintained exclusively by Inventory Transactions.
- `company_id` is never accepted from the client — it is auto-stamped from the authenticated user.

---

### `GET /api/v1/products/{product}`

Returns a single product with category and supplier eagerly loaded.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK** — Full product object with `category` and `supplier` nested

**Error Responses:**
- `404` — Product not found or belongs to different company

---

### `PUT/PATCH /api/v1/products/{product}`

Updates an existing product. All fields are optional.

**Authentication:** Required
**Permissions:** owner, admin only

**Validation Rules:**

| Field | Rules |
|---|---|
| name | optional; if supplied: string, max 255 |
| sku | optional; if supplied: string, max 255, unique within company (excluding self) |
| description | optional, string or null |
| price | optional; if supplied: numeric, min 0 |
| cost | optional, numeric, min 0, or null |
| category_id | optional; if supplied: integer, must exist and belong to this company |
| supplier_id | optional; if supplied: integer, must exist and belong to this company |
| image_url | optional, valid URL or null |

**Response: 200 OK** — Full updated product object

**Notes:**
- Do NOT use this endpoint to change `current_stock`. Stock is managed exclusively via Inventory Transactions.

---

### `DELETE /api/v1/products/{product}`

Permanently deletes a product.

**Authentication:** Required
**Permissions:** owner, admin only

**Response: 204 No Content**

**Error Responses:**
- `500` — Database constraint if product has inventory transactions referencing it (RESTRICT)

---

## Inventory Transactions

> **Design Note:** Transactions are an append-only audit ledger. There are intentionally no update or delete endpoints. To correct an error, create a new transaction with a compensating quantity and a note explaining the correction.

---

### `GET /api/v1/transactions`

Returns all transactions for the company, with product and creator eagerly loaded.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK**
```json
[
  {
    "id": 1,
    "company_id": 1,
    "product_id": 1,
    "created_by": 1,
    "type": "purchase",
    "quantity": 50,
    "notes": "Initial stock delivery",
    "created_at": "2026-06-29T10:03:04.000000Z",
    "updated_at": "2026-06-29T10:03:04.000000Z",
    "product": {
      "id": 1,
      "name": "Wireless Mouse",
      "sku": "WM-001",
      "current_stock": 38,
      "..."
    },
    "creator": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@acme.com",
      "role": "owner",
      "..."
    }
  },
  {
    "id": 2,
    "company_id": 1,
    "product_id": 1,
    "created_by": 3,
    "type": "sale",
    "quantity": -12,
    "notes": null,
    "created_at": "2026-06-29T10:04:23.000000Z",
    "updated_at": "2026-06-29T10:04:23.000000Z",
    "product": { "..." },
    "creator": { "..." }
  }
]
```

---

### `POST /api/v1/transactions`

Records a new inventory transaction. Automatically updates the product's `current_stock`.

**Authentication:** Required
**Permissions:** All roles including staff (staff routinely process sales and receive deliveries)

**Request Body:**
```json
{
  "product_id": 1,
  "type": "purchase",
  "quantity": 50,
  "notes": "Delivery from Global Parts Co"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| product_id | required, integer, must exist in products AND belong to this company |
| type | required, must be one of: `purchase`, `sale`, `adjustment` |
| quantity | required, integer |
| notes | optional, string |

**Cross-Field Validation Rules (withValidator):**
- `type: "purchase"` requires `quantity > 0` (positive)
- `type: "sale"` requires `quantity < 0` (negative)
- `type: "adjustment"` accepts any non-zero integer (positive or negative)

**Response: 201 Created**
```json
{
  "id": 3,
  "company_id": 1,
  "product_id": 1,
  "created_by": 1,
  "type": "purchase",
  "quantity": 50,
  "notes": "Delivery from Global Parts Co",
  "created_at": "2026-07-01T09:00:00.000000Z",
  "updated_at": "2026-07-01T09:00:00.000000Z",
  "product": {
    "id": 1,
    "current_stock": 88,
    "..."
  }
}
```

**Error Responses:**
- `422` — Invalid product_id (nonexistent or belongs to different company), invalid type, sign mismatch between type and quantity

**Notes:**
- `created_by` is set automatically from the authenticated user. It cannot be supplied by the client.
- `company_id` is set automatically. Cannot be supplied.
- After creation, the referenced product's `current_stock` is atomically updated using `INCREMENT` SQL — `current_stock = current_stock + quantity`. This is thread-safe.
- `quantity` of 0 is technically allowed (represents a stock audit that confirmed no change). This is intentional — the transaction ledger should record all events honestly.

**Transaction Type Reference:**

| Type | Quantity Direction | Example Use Case |
|---|---|---|
| purchase | Positive (e.g., +50) | Stock received from supplier |
| sale | Negative (e.g., -12) | Items sold to customer |
| adjustment | Positive or negative | Damage, theft, miscounting correction |

---

### `GET /api/v1/transactions/{transaction}`

Returns a single transaction with product and creator eagerly loaded.

**Authentication:** Required
**Permissions:** All roles

**Response: 200 OK** — Full transaction object with `product` and `creator` nested

**Error Responses:**
- `404` — Transaction not found or belongs to different company

---

## Invitations

---

### `GET /api/v1/invitations`

Returns all invitations for the company, regardless of status.

**Authentication:** Required
**Permissions:** owner, admin only

**Response: 200 OK**
```json
[
  {
    "id": 1,
    "company_id": 1,
    "email": "newstaff@example.com",
    "role": "staff",
    "token": "EsKi9gHoz4...(60 chars)...",
    "expires_at": "2026-07-08T10:00:00.000000Z",
    "accepted_at": "2026-07-01T12:36:17.000000Z",
    "cancelled_at": null,
    "created_at": "2026-07-01T10:00:00.000000Z",
    "updated_at": "2026-07-01T12:36:17.000000Z"
  },
  {
    "id": 2,
    "company_id": 1,
    "email": "cancelled@example.com",
    "role": "admin",
    "token": "OhrEKQiH...(60 chars)...",
    "expires_at": "2026-07-08T11:00:00.000000Z",
    "accepted_at": null,
    "cancelled_at": "2026-07-01T13:02:52.000000Z",
    "created_at": "2026-07-01T11:00:00.000000Z",
    "updated_at": "2026-07-01T13:02:52.000000Z"
  }
]
```

**Invitation Status Reference:**

| Status | Condition |
|---|---|
| Pending | `accepted_at IS NULL` AND `cancelled_at IS NULL` AND `expires_at > now()` |
| Accepted | `accepted_at IS NOT NULL` |
| Cancelled | `cancelled_at IS NOT NULL` |
| Expired | `expires_at <= now()` AND `accepted_at IS NULL` AND `cancelled_at IS NULL` |

---

### `POST /api/v1/invitations`

Sends an invitation email to a new team member.

**Authentication:** Required
**Permissions:** owner, admin only

**Request Body:**
```json
{
  "email": "newadmin@example.com",
  "role": "admin"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| email | required, valid email format, must NOT already be a registered user |
| role | required, must be `admin` or `staff` (owner cannot be invited) |

**Cross-Field Validation:**
- Email must not have an existing pending (not accepted, not cancelled, not expired) invitation within this company.

**Response: 201 Created**
```json
{
  "id": 3,
  "company_id": 1,
  "email": "newadmin@example.com",
  "role": "admin",
  "token": "hZHwd07bGp...(60 chars)...",
  "expires_at": "2026-07-08T10:12:00.000000Z",
  "accepted_at": null,
  "cancelled_at": null,
  "created_at": "2026-07-01T10:12:00.000000Z",
  "updated_at": "2026-07-01T10:12:00.000000Z"
}
```

**Error Responses:**
- `403` — Insufficient role
- `422` — Email already registered as a user, email already has a pending invitation, invalid role (including "owner"), invalid email format

**Notes:**
- An invitation email is sent asynchronously (queued). The response returns before the email is delivered.
- The token is 60 random characters. The email link points to a placeholder URL pending frontend development.
- To re-invite someone whose invitation expired, first cancel the expired invitation, then send a new one. Or simply send a new one — after the expired one ages past its `expires_at`, it no longer counts as "pending."

---

### `POST /api/v1/invitations/accept/{token}`

Accepts an invitation and creates a new user account. **This endpoint is unauthenticated.**

**Authentication:** None (public — the invited person has no account yet)
**Rate Limit:** 10 requests per minute per IP

**Route Parameter:** `{token}` — The 60-character token from the invitation email

**Request Body:**
```json
{
  "name": "New Admin Person",
  "password": "securepassword123",
  "password_confirmation": "securepassword123"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| name | required, string |
| password | required, string, min 8 characters, must match confirmation |
| password_confirmation | required |

**Response: 201 Created**
```json
{
  "user": {
    "id": 5,
    "name": "New Admin Person",
    "email": "newadmin@example.com",
    "company_id": 1,
    "role": "admin",
    "deactivated_at": null,
    "created_at": "2026-07-01T12:36:17.000000Z",
    "updated_at": "2026-07-01T12:36:17.000000Z"
  },
  "token": "22|abc123..."
}
```

**Error Responses:**
- `410 Gone` — Invitation not found, already accepted, cancelled, or expired
- `422` — Validation failed

**Notes:**
- The user's `email`, `role`, and `company_id` come from the invitation record — not from the request body. The client cannot override these.
- A Sanctum token is returned immediately so the user can start making authenticated requests without a separate login step.
- The invitation's `accepted_at` is set to the current timestamp.

---

### `PATCH /api/v1/invitations/{invitation}/cancel`

Cancels a pending invitation.

**Authentication:** Required
**Permissions:** owner, admin only
**Route Parameter:** `{invitation}` — Integer ID of the invitation

**Request Body:** None

**Response: 200 OK**
```json
{
  "id": 2,
  "company_id": 1,
  "email": "newadmin@example.com",
  "role": "admin",
  "token": "hZHwd07b...",
  "expires_at": "2026-07-08T10:12:00.000000Z",
  "accepted_at": null,
  "cancelled_at": "2026-07-01T13:02:52.000000Z",
  "created_at": "...",
  "updated_at": "2026-07-01T13:02:52.000000Z"
}
```

**Error Responses:**
- `403` — Insufficient role
- `404` — Invitation not found or belongs to different company
- `409 Conflict` — `{"message": "This invitation has already been accepted and cannot be cancelled."}` (already accepted)
- `409 Conflict` — `{"message": "This invitation has already been cancelled."}` (already cancelled)

**Notes:**
- Cancellation is a soft operation — the invitation row is preserved for audit purposes. Only `cancelled_at` is set.
- After cancellation, any attempt to use the token via `/accept/{token}` will return 410 Gone.
- Cancelled invitations do NOT count as "pending" for the duplicate-invitation check. You can send a new invitation to the same email after cancelling the previous one.

---

## Users

---

### `GET /api/v1/users`

Returns all users belonging to the authenticated user's company.

**Authentication:** Required
**Permissions:** owner, admin only

**Response: 200 OK**
```json
[
  {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@acme.com",
    "email_verified_at": null,
    "company_id": 1,
    "role": "owner",
    "deactivated_at": null,
    "created_at": "2026-07-01T08:26:09.000000Z",
    "updated_at": "2026-07-01T08:26:09.000000Z"
  },
  {
    "id": 3,
    "name": "Staff Member",
    "email": "staff@acme.com",
    "email_verified_at": null,
    "company_id": 1,
    "role": "staff",
    "deactivated_at": "2026-07-01T14:08:12.000000Z",
    "updated_at": "2026-07-01T14:08:12.000000Z"
  }
]
```

**Notes:**
- `password` is never included in responses (hidden via `$hidden` on the User model).
- Deactivated users are included in the list — check `deactivated_at` to determine status.

---

### `PATCH /api/v1/users/{user}/deactivate`

Deactivates a user account. The user's existing tokens stop working immediately.

**Authentication:** Required
**Permissions:** owner or admin (with restrictions — see Notes)
**Route Parameter:** `{user}` — Integer ID of the user

**Request Body:** None

**Response: 200 OK** — Full updated user object with `deactivated_at` set

**Error Responses:**
- `403` — Insufficient role, attempting to deactivate the owner, attempting to deactivate yourself, admin attempting to deactivate another admin
- `404` — User not found or belongs to different company
- `409 Conflict` — `{"message": "This user is already deactivated."}`

**Permission Rules:**
- Owner can deactivate: admins, staff (but NOT themselves, NOT the owner role)
- Admin can deactivate: staff only (NOT other admins, NOT owners, NOT themselves)
- Staff cannot deactivate anyone

---

### `PATCH /api/v1/users/{user}/reactivate`

Reactivates a previously deactivated user. Clears `deactivated_at`.

**Authentication:** Required
**Permissions:** owner or admin (with restrictions)
**Route Parameter:** `{user}` — Integer ID of the user

**Request Body:** None

**Response: 200 OK** — Full updated user object with `deactivated_at: null`

**Error Responses:**
- `403` — Insufficient role, admin attempting to reactivate another admin
- `404` — User not found or belongs to different company
- `409 Conflict` — `{"message": "This user is already active."}`

**Notes:**
- Same role restrictions as deactivation: admins cannot reactivate peer admins.
- Does NOT automatically restore Sanctum tokens that were revoked. If tokens were revoked before/during deactivation, the user must log in again after reactivation.

---

### `POST /api/v1/me/revoke-tokens`

Revokes ALL Sanctum tokens for the currently authenticated user. Use for "log me out everywhere."

**Authentication:** Required
**Permissions:** All roles (self-service)

**Request Body:** None

**Response: 200 OK**
```json
{
  "message": "All your sessions have been logged out."
}
```

**Notes:**
- The token used to make this request is also revoked. Subsequent requests with any previously-issued token will return 401.
- The user account itself is not affected — they can log in again to get a new token.

---

### `POST /api/v1/users/{user}/revoke-tokens`

Revokes ALL Sanctum tokens for a specific user within the company. For incident response.

**Authentication:** Required
**Permissions:** owner, admin (with restrictions)
**Route Parameter:** `{user}` — Integer ID of the user

**Request Body:** None

**Response: 200 OK**
```json
{
  "message": "All sessions for this user have been logged out."
}
```

**Error Responses:**
- `403` — Insufficient role, attempting to target yourself (use `/me/revoke-tokens` instead), targeting the owner, admin targeting another admin
- `404` — User not found or belongs to different company

**Notes:**
- Same role restrictions as deactivation, plus: you cannot use this endpoint to revoke your own tokens (use `/me/revoke-tokens` for that — the error message directs you there).
- Does not deactivate the user. The user can log in again to get new tokens.

---

## Status Code Summary

| Code | Meaning | When Used |
|---|---|---|
| 200 | OK | Successful GET, PATCH, POST with body |
| 201 | Created | Successful resource creation |
| 204 | No Content | Successful DELETE |
| 401 | Unauthenticated | Missing/invalid/expired token |
| 403 | Forbidden | Valid token, insufficient role or deactivated |
| 404 | Not Found | Resource doesn't exist or belongs to another company |
| 409 | Conflict | Resource already in desired state |
| 410 | Gone | Token-based resource is permanently unusable |
| 422 | Unprocessable | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Unhandled exception (APP_DEBUG=false in production) |
