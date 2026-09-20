# BankVision Backend API Documentation

**Base URL**: `http://localhost:8000/api`  
**API Version**: `v1`  
**Authentication**: Laravel Sanctum (`Bearer Token`)  
**Data Format**: JSON (`Accept: application/json`, `Content-Type: application/json`)

---

## Table of Contents
1. [Authentication & Authorization](#authentication--authorization)
2. [Global Error & Response Formats](#global-error--response-formats)
3. [Authentication & 2FA Endpoints](#1-authentication--2fa-endpoints)
4. [Customer Endpoints](#2-customer-endpoints)
5. [KYC Document Endpoints](#3-kyc-document-endpoints)
6. [Account Endpoints](#4-account-endpoints)
7. [Transaction Endpoints](#5-transaction-endpoints)
8. [Loan Endpoints](#6-loan-endpoints)
9. [Alert Endpoints](#7-alert-endpoints)
10. [Suspicious Activity Report (SAR) Endpoints](#8-suspicious-activity-report-sar-endpoints)
11. [Branch Endpoints](#9-branch-endpoints)
12. [User & Staff Management Endpoints](#10-user--staff-management-endpoints)
13. [Audit Log Endpoints](#11-audit-log-endpoints)
14. [Dashboard & Analytical Reports Endpoints](#12-dashboard--analytical-reports-endpoints)
15. [In-App Notification Endpoints](#13-in-app-notification-endpoints)
16. [Settings & Administration Endpoints](#14-settings--administration-endpoints)
17. [Global Search & Public Endpoints](#15-global-search--public-endpoints)

### Compatibility paths

The canonical analytical report paths are `/api/dashboard/reports` and
`/api/dashboard/risk-analysis`. The legacy `/api/reports` and
`/api/reports/risk-analysis` paths remain as compatibility aliases for
existing external clients and have the same RBAC middleware and response
contract. The authenticated `/api/search` endpoint is also retained as a
server-side bridge for external clients; the React SPA uses its local search
index instead.

---

## Authentication & Authorization

Protected endpoints require a Sanctum Bearer token in the `Authorization` header:
```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

### Role-Based Access Control (RBAC) Matrix

> [!NOTE]
> **Branch Scoping**: Branch Manager and CSR operations are automatically scoped to their assigned `branch_id`. Accessing resources in other branches returns `403 Forbidden`.

| Module | Endpoint | Admin | Manager | Compliance | Analyst | CSR | Auditor |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Auth** | Login / 2FA Challenge | Public | Public | Public | Public | Public | Public |
| **Auth** | Logout / Current Profile | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Customers** | List / View | ✅ | ✅ (branch only) | ✅ (all) | ✅ | ✅ (branch only) | ✅ |
| **Customers** | Create | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Customers** | Update (contact info) | ✅ | ✅ | ❌ | ❌ | ✅ (contact only) | ❌ |
| **Customers** | Update (KYC / risk level) | ✅ | ✅ | ✅ (KYC only) | ❌ | ❌ | ❌ |
| **Customers** | Delete | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **KYC Docs** | List / Metadata / Download | ✅ | ✅ (branch only) | ✅ (all) | ✅ | ✅ (branch only) | ✅ |
| **KYC Docs** | Upload & Verify | ✅ | ✅ (branch only) | ✅ (all) | ❌ | ❌ | ❌ |
| **KYC Docs** | Delete Document | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Accounts** | List / View | ✅ | ✅ (branch only) | ✅ (flagged/high-risk) | ✅ | ✅ (branch only) | ✅ |
| **Accounts** | Open | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Accounts** | Update / Close (Delete) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Transactions** | List / View | ✅ | ✅ (branch only) | ✅ (flagged/high-value/wire) | ✅ | ✅ (branch, deposit/withdrawal) | ✅ |
| **Transactions** | Create (Record) | ✅ | ✅ | ❌ | ❌ | ✅ (deposit/withdrawal only) | ❌ |
| **Transactions** | Approve | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Transactions** | Flag | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Loans** | List / View | ✅ | ✅ (branch only) | ✅ (delinquent/defaulted) | ✅ | ✅ (branch only, read-only) | ✅ |
| **Loans** | Submit Application / Update | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Loans** | Approve Loan | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Alerts** | List / View | ✅ | ✅ (branch only) | ✅ (all) | ✅ (risk-related) | ✅ (customer-related, branch) | ✅ |
| **Alerts** | Assign / Resolve | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **SAR Filings** | List / View | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **SAR Filings** | Create (File SAR) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Branches** | List | ✅ | ✅ (own branch only) | ✅ | ✅ | ✅ (own branch only) | ✅ |
| **Branches** | View (single) | ✅ | ✅ (own branch only) | ✅ | ✅ | ✅ (own branch only) | ✅ |
| **Branches** | Create / Update / Delete | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Users / Staff** | List / View | ✅ | ✅ (branch staff only) | ✅ | ✅ | ❌ | ✅ |
| **Users / Staff** | Eligible Relationship Managers | ✅ | ✅ (branch staff only) | ❌ | ❌ | ✅ (branch staff only) | ❌ |
| **Users / Staff** | Create / Update / Delete | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Audit Logs** | View List & Details | ✅ | ✅ (branch staff logs) | ✅ (compliance-relevant) | ❌ | ❌ | ✅ |
| **Dashboard** | Stats / Chart / Recent / Risk | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Dashboard** | Analytical Reports | ✅ | ✅ (own branch) | ✅ | ✅ | ❌ | ✅ |
| **Dashboard** | Auditor Investigation Stats & Report | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Dashboard** | Layout (Get, Update, Reset) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notifications** | List / Mark Read / Mark All Read | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Settings** | Profile / Password / Avatar | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Settings** | User Notifications & Preferences | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Settings** | Security: 2FA / Sessions / History | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Settings** | API Tokens Registry | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Settings** | System Configuration & Health | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Search** | Grouped Search Bridge | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Public** | Published Interest Rates | Public | Public | Public | Public | Public | Public |

---

## Global Error & Response Formats

### Standard Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... }
}
```

### Validation Error (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "Validation error.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

### Unauthenticated (401 Unauthorized)
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Access Denied (403 Forbidden)
```json
{
  "success": false,
  "message": "Unauthorized. Requires one of: admin, manager"
}
```

### Resource Not Found (404 Not Found)
```json
{
  "success": false,
  "message": "Resource not found."
}
```

---

## 1. Authentication & 2FA Endpoints

### `POST /api/login`
Authenticate staff credentials, verify active status, and generate a Sanctum API token. When 2FA is active for the user, the token is withheld and a one-time verification challenge is dispatched to the user's registered email. Rate-limited to 5 requests per minute (`throttle:login`).

- **Role**: Public
- **Headers**: `Accept: application/json`, `Content-Type: application/json`

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `email` | `string` | `required`, `email` | Registered staff email address |
| `password` | `string` | `required`, `string` | Account password |

**Request Example:**
```json
{
  "email": "admin@bankvision.com",
  "password": "password"
}
```

**Standard Response (`200 OK` - 2FA disabled):**
```json
{
  "success": true,
  "message": "Authenticated successfully.",
  "token": "1|qW89J...XyZ",
  "user": {
    "id": 1,
    "name": "Sarah Connor",
    "email": "admin@bankvision.com",
    "role": "admin",
    "status": "active",
    "phone": "+1-555-0199",
    "avatar": "avatars/admin.png",
    "avatar_url": "http://localhost:8000/storage/avatars/admin.png",
    "last_login_at": "2026-09-19 15:30:00",
    "branch": {
      "id": 1,
      "branch_code": "BR001",
      "branch_name": "Main Downtown Branch",
      "city": "Metropolis",
      "status": "active"
    }
  }
}
```

**2FA Challenge Response (`200 OK` - 2FA enabled):**
```json
{
  "success": false,
  "requires_2fa": true,
  "message": "A verification code has been sent to your email address.",
  "email": "ad***@bankvision.com",
  "dev_hint": "Development: the verification code is written to storage/logs/laravel.log"
}
```

---

### `POST /api/login/2fa`
Complete a two-factor login challenge. Verifies the 6-digit code sent by email and issues the Sanctum Bearer token. Rate-limited (`throttle:login`).

- **Role**: Public

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `email` | `string` | `required`, `email` | Staff email address |
| `code` | `string` | `required`, `string` | 6-digit verification code |

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Authenticated successfully.",
  "token": "2|kL82N...PqR",
  "user": {
    "id": 1,
    "name": "Sarah Connor",
    "email": "admin@bankvision.com",
    "role": "admin",
    "status": "active",
    "branch": { ... }
  }
}
```

---

### `POST /api/login/2fa/resend`
Re-issues and sends a fresh 2FA verification code to the user's email if a login challenge is active. Rate-limited (`throttle:login`).

- **Role**: Public

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `email` | `string` | `required`, `email` | Staff email address |

**Response (`200 OK`):**
```json
{
  "success": false,
  "requires_2fa": true,
  "message": "A new verification code has been sent to your email address.",
  "email": "ad***@bankvision.com"
}
```

---

### `POST /api/logout`
Revoke the current Sanctum access token for the authenticated user and record an audit log entry.

- **Role**: All authenticated roles
- **Headers**: `Authorization: Bearer <token>`

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Logged out successfully."
}
```

---

### `GET /api/user`
Retrieve current authenticated staff profile with assigned branch details.

- **Role**: All authenticated roles

**Response (`200 OK`):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Sarah Connor",
    "email": "admin@bankvision.com",
    "role": "admin",
    "status": "active",
    "phone": "+1-555-0199",
    "avatar_url": "http://localhost:8000/storage/avatars/admin.png",
    "last_login_at": "2026-09-19 15:30:00",
    "branch": {
      "id": 1,
      "branch_code": "BR001",
      "branch_name": "Main Downtown Branch"
    }
  }
}
```

---

## 2. Customer Endpoints

### `GET /api/customers`
List paginated customers with optional search and filtering.

- **Role**: All authenticated roles
- **Scoping**:
  - `admin`, `auditor`, `analyst`, `compliance`: Bank-wide.
  - `manager`, `csr`: Scoped to assigned `branch_id`.
- **Query Parameters**:
  - `page` (`integer`, optional): Page number (default: 1)
  - `per_page` (`integer`, optional): Results per page (default: 15)
  - `search` (`string`, optional): Search by name, email, phone, or customer number
  - `type` (`string`, optional): `premium`, `regular`, `business`
  - `kyc_status` (`string`, optional): `verified`, `pending`, `expired`
  - `risk_level` (`string`, optional): `low`, `medium`, `high`
  - `branch_id` (`integer`, optional): Filter by branch (admin, auditor, compliance only)
  - `sort_by` (`string`, optional): Sort field (default: `created_at`)
  - `sort_direction` (`string`, optional): `asc` or `desc`

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 10,
      "customer_number": "CUST-2026-00010",
      "full_name": "John Doe",
      "email": "john.doe@example.com",
      "phone": "+1-555-0144",
      "address": "123 Financial Way",
      "city": "Metropolis",
      "customer_type": "premium",
      "kyc_status": "verified",
      "risk_level": "low",
      "registration_date": "2026-01-15",
      "accounts_count": 2,
      "loans_count": 1,
      "document_count": 3,
      "branch": {
        "id": 1,
        "branch_code": "BR001",
        "branch_name": "Main Downtown Branch"
      },
      "relationship_manager": {
        "id": 2,
        "name": "James Smith",
        "email": "manager@bankvision.com"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 65
  }
}
```

---

### `POST /api/customers`
Create a new customer profile.

- **Role**: `admin`, `manager`, `csr`
- **Scoping**: `manager` and `csr` may only create customers in their assigned branch.

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `full_name` | `string` | `required`, `max:255` | Full legal name |
| `email` | `string` | `required`, `email`, `unique:customers,email` | Unique customer email |
| `phone` | `string` | `required`, `max:20` | Contact phone number |
| `address` | `string` | `nullable` | Residential/office address |
| `city` | `string` | `nullable`, `max:100` | City |
| `customer_type` | `string` | `required`, `in:premium,regular,business` | Customer classification |
| `kyc_status` | `string` | `sometimes`, `in:verified,pending,expired` | Default: `pending` |
| `risk_level` | `string` | `sometimes`, `in:low,medium,high` | Default: `low` |
| `registration_date` | `date` | `sometimes`, `date` | Default: today |
| `branch_id` | `integer` | `required`, `exists:branches,id` | Assigned branch |
| `relationship_manager_id`| `integer` | `nullable`, `exists:users,id` | Assigned staff manager |

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Customer created successfully.",
  "data": {
    "id": 11,
    "customer_number": "CUST-2026-00011",
    "full_name": "Jane Wilson",
    "email": "jane.wilson@example.com",
    "phone": "+1-555-0812",
    "customer_type": "regular",
    "kyc_status": "pending",
    "risk_level": "low",
    "registration_date": "2026-09-19"
  }
}
```

---

### `GET /api/customers/{id}`
Retrieve single customer profile with accounts, loans, and branch details.

- **Role**: All authenticated roles (branch-scoped for manager/csr)

---

### `PUT /api/customers/{id}`
Update an existing customer record.

- **Role**: `admin`, `manager`, `csr`, `compliance`
- **Permission rules by role**:
  - `admin`, `manager`: Can update all fields including `kyc_status`, `risk_level`, and personal info.
  - `csr`: Can update contact fields only (`phone`, `address`, `city`). Attempting to update `kyc_status` or `risk_level` returns `403 Forbidden`.
  - `compliance`: Can update `kyc_status` only. Attempting to update personal details returns `403 Forbidden`.

**Validation Rules:**
| Field | Type | Rules | Roles Allowed |
| :--- | :--- | :--- | :--- |
| `full_name` | `string` | `sometimes`, `max:255` | `admin`, `manager` |
| `email` | `string` | `sometimes`, `email`, `unique:customers,email,{id}` | `admin`, `manager` |
| `phone` | `string` | `sometimes`, `max:20` | `admin`, `manager`, `csr` |
| `address` | `string` | `nullable` | `admin`, `manager`, `csr` |
| `city` | `string` | `nullable`, `max:100` | `admin`, `manager`, `csr` |
| `customer_type` | `string` | `sometimes`, `in:premium,regular,business` | `admin`, `manager` |
| `kyc_status` | `string` | `sometimes`, `in:verified,pending,expired` | `admin`, `manager`, `compliance` |
| `risk_level` | `string` | `sometimes`, `in:low,medium,high` | `admin`, `manager` |
| `branch_id` | `integer` | `sometimes`, `exists:branches,id` | `admin` |
| `relationship_manager_id`| `integer` | `nullable`, `exists:users,id` | `admin`, `manager` |

---

### `DELETE /api/customers/{id}`
Delete a customer. Cannot be deleted if the customer has accounts with balance > 0 or outstanding active loans.

- **Role**: `admin`

---

### `GET /api/customers/{id}/accounts`
List all accounts belonging to a customer.

- **Role**: All authenticated roles

---

### `GET /api/customers/{id}/loans`
List all loans belonging to a customer.

- **Role**: All authenticated roles

---

### `GET /api/customers/{id}/transactions`
List transaction history across all accounts belonging to a customer.

- **Role**: All authenticated roles
- **Query Parameters**: `page`, `per_page`

---

## 3. KYC Document Endpoints

Documents are stored on private storage (`storage/app/private/kyc/{customer_id}/{uuid}.{ext}`). Direct public HTTP access is disallowed; all downloads are authenticated and permission-checked.

### `GET /api/customers/{customer}/kyc-documents`
List all KYC verification documents uploaded for a specific customer.

- **Role**: `admin`, `compliance`, `analyst`, `auditor` (all); `manager`, `csr` (assigned branch only)

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 1,
      "customer_id": 10,
      "document_type": "passport",
      "document_number": "A12345678",
      "issuing_country": "United States",
      "expiry_date": "2030-05-15",
      "file_name": "passport_scan.pdf",
      "file_size": 245100,
      "mime_type": "application/pdf",
      "status": "verified",
      "notes": "Verified against state identity database",
      "uploaded_at": "2026-09-18 10:20:00",
      "verified_at": "2026-09-18 10:20:00",
      "uploaded_by": {
        "id": 2,
        "name": "James Smith",
        "role": "manager"
      }
    }
  ]
}
```

---

### `POST /api/customers/{customer}/kyc-documents`
Upload and verify a customer identity document. Updates customer `kyc_status` to `verified` and creates an audit entry `kyc.document.uploaded`.

- **Role**: `admin`, `compliance`, `manager` (assigned branch)
- **Headers**: `Content-Type: multipart/form-data`

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `document_type` | `string` | `required`, `max:50` | e.g. `passport`, `national_id`, `driving_license` |
| `document_number` | `string` | `required`, `max:100` | ID / Document number |
| `issuing_country` | `string` | `nullable`, `max:100` | Country of issuance |
| `expiry_date` | `date` | `nullable`, `date` | Document expiration date |
| `file` | `file` | `required`, `mimes:jpg,jpeg,png,pdf`, `max:10240` | File upload (max 10MB) |
| `attestation` | `boolean` | `required`, `accepted` | Confirmation checkbox |
| `notes` | `string` | `nullable`, `max:1000` | Staff verification notes |

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "KYC document uploaded and verified successfully.",
  "data": {
    "id": 2,
    "document_type": "national_id",
    "document_number": "ID-987654321",
    "status": "verified",
    "file_name": "id_card.jpg"
  },
  "customer": {
    "id": 10,
    "kyc_status": "verified",
    "document_count": 2
  }
}
```

---

### `GET /api/kyc-documents/{id}`
Retrieve metadata for a single KYC document.

- **Role**: `admin`, `compliance`, `analyst`, `auditor` (all); `manager`, `csr` (assigned branch)

---

### `GET /api/kyc-documents/{id}/download`
Stream-download the binary file from private storage with verified MIME type.

- **Role**: `admin`, `compliance`, `analyst`, `auditor` (all); `manager`, `csr` (assigned branch)
- **Response**: Binary file stream with `Content-Disposition: attachment; filename="..."`

---

### `DELETE /api/kyc-documents/{id}`
Permanently delete the document record and remove the physical file from private disk.

- **Role**: `admin` only

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "KYC document deleted successfully."
}
```

---

## 4. Account Endpoints

### `GET /api/accounts`
List paginated bank accounts with optional filters.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `page` (`integer`, optional)
  - `customer_id` (`integer`, optional)
  - `type` (`string`, optional): `savings`, `checking`, `business`
  - `status` (`string`, optional): `active`, `frozen`, `closed`
  - `currency` (`string`, optional): 3-letter currency code (e.g. `USD`, `EUR`)

---

### `POST /api/accounts`
Open a new bank account.

- **Role**: `admin`, `manager`, `csr`
- **Scoping**: `manager` and `csr` may only open accounts for customers in their assigned branch.

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `customer_id` | `integer` | `required`, `exists:customers,id` | Customer ID |
| `account_type` | `string` | `required`, `in:savings,checking,business` | Account type |
| `currency` | `string` | `sometimes`, `size:3` | Currency code (default `USD`) |
| `balance` | `numeric` | `sometimes`, `min:0`, `max:999999999.99` | Initial opening balance |
| `interest_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Applicable interest % |
| `opened_date` | `date` | `sometimes`, `date` | Default: today |

**Request Example:**
```json
{
  "customer_id": 10,
  "account_type": "savings",
  "currency": "USD",
  "balance": 500.00,
  "interest_rate": 2.5
}
```

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Account opened successfully.",
  "data": {
    "id": 12,
    "account_number": "ACC-2026-00012",
    "customer_id": 10,
    "account_type": "savings",
    "currency": "USD",
    "balance": "500.00",
    "status": "active",
    "interest_rate": "2.50",
    "opened_date": "2026-08-23"
  }
}
```

---

### `GET /api/accounts/{id}`
Retrieve single account details with owner information.

- **Role**: All authenticated roles

---

### `PUT /api/accounts/{id}`
Update account status (freeze/unfreeze) or interest rate.

- **Role**: `admin`, `manager`

> [!CAUTION]
> **CSR** cannot update or close accounts — returns `403 Forbidden`.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `status` | `string` | `sometimes`, `in:active,frozen,closed` |
| `interest_rate` | `numeric` | `sometimes`, `min:0`, `max:100` |

---

### `DELETE /api/accounts/{id}`
Close an account (sets status to `closed`). Cannot be deleted if account has a non-zero balance.

- **Role**: `admin`, `manager`

> [!CAUTION]
> **CSR** cannot close accounts — returns `403 Forbidden`.

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Account closed successfully."
}
```

---

### `GET /api/accounts/{id}/transactions`
List transaction history for a specific account.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `type` (`string`, optional): `deposit`, `withdrawal`, `transfer`, `wire`
  - `status` (`string`, optional): `completed`, `pending`, `failed`, `flagged`
  - `date_from` (`date`, optional): `YYYY-MM-DD`
  - `date_to` (`date`, optional): `YYYY-MM-DD`

---

## 4. Transaction Endpoints

### `GET /api/transactions`
List paginated transactions across the institution.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `account_id` (`integer`, optional)
  - `type` (`string`, optional): `deposit`, `withdrawal`, `transfer`, `wire`
  - `status` (`string`, optional): `completed`, `pending`, `failed`, `flagged`
  - `channel` (`string`, optional): `online`, `branch`, `atm`, `mobile`
  - `date_from` (`date`, optional): `YYYY-MM-DD`
  - `date_to` (`date`, optional): `YYYY-MM-DD`

---

### `POST /api/transactions`
Record a new financial transaction.
- For `completed` status: executes balance change atomically with row-level pessimistic locking.
- Withdrawals exceeding available balance throw `422 Unprocessable Entity` ("Insufficient funds").
- Transactions on `frozen` or `closed` accounts are rejected.

- **Role**: `admin`, `manager`, `csr`
- **Scoping**:
  - `manager` and `csr`: Can only record transactions on accounts belonging to their assigned branch.
  - `csr`: Restricted to `deposit` and `withdrawal` types only. Attempting `transfer` or `wire` returns `403 Forbidden`.

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `account_id` | `integer` | `required`, `exists:accounts,id` | Target account |
| `transaction_type` | `string` | `required`, `in:deposit,withdrawal,transfer,wire` | Transaction classification |
| `amount` | `numeric` | `required`, `min:0.01`, `max:999999999.99` | Transaction amount |
| `currency` | `string` | `sometimes`, `size:3` | Default `USD` |
| `description` | `string` | `nullable` | Memo / description |
| `channel` | `string` | `sometimes`, `in:online,branch,atm,mobile` | Channel used (default `branch`) |
| `counterparty` | `string` | `nullable`, `max:255` | Counterparty name/IBAN |
| `status` | `string` | `sometimes`, `in:completed,pending` | Default: `completed` |

**Request Example:**
```json
{
  "account_id": 4,
  "transaction_type": "deposit",
  "amount": 2500.00,
  "currency": "USD",
  "channel": "branch",
  "description": "Payroll direct deposit"
}
```

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Transaction recorded successfully.",
  "data": {
    "id": 89,
    "transaction_number": "TXN-2026-00089",
    "account_id": 4,
    "transaction_type": "deposit",
    "amount": "2500.00",
    "currency": "USD",
    "status": "completed",
    "channel": "branch",
    "transaction_date": "2026-08-23 15:45:10"
  }
}
```

---

### `GET /api/transactions/{id}`
Retrieve details for a single transaction.

- **Role**: All authenticated roles

---

### `POST /api/transactions/{id}/approve`
Approve a pending or flagged transaction. Executes underlying account balance changes atomically and sets approver audit metadata.

- **Role**: `admin`, `manager`
- **Scoping**: `manager` can only approve transactions for accounts in their assigned branch.

> [!CAUTION]
> **Compliance Officer** cannot approve transactions — returns `403 Forbidden`. The compliance role is restricted to flagging suspicious transactions only.

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Transaction approved successfully.",
  "data": {
    "id": 89,
    "status": "completed",
    "approved_at": "2026-08-23 15:46:00",
    "approver": {
      "id": 3,
      "name": "David Compliance",
      "email": "compliance@bankvision.com"
    }
  }
}
```

---

### `POST /api/transactions/{id}/flag`
Flag a transaction for compliance investigation. Automatically creates a polymorphic compliance alert. If an open alert for this transaction already exists, no duplicate is created.

- **Role**: `admin`, `manager`, `compliance`

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Transaction flagged for review.",
  "data": {
    "id": 89,
    "status": "flagged"
  }
}
```

---

## 5. Loan Endpoints

### `GET /api/loans`
List paginated loans with optional filters.

- **Role**: All authenticated roles
- **Query Parameters**: `customer_id`, `type`, `status` (`pending`, `active`, `completed`, `delinquent`, `defaulted`)

---

### `POST /api/loans`
Submit a new loan application. New loans are created with `pending` status.

- **Role**: `admin`, `manager`
- **Scoping**: `manager` can only submit loan applications for customers in their assigned branch.

> [!CAUTION]
> **CSR** cannot submit loan applications — returns `403 Forbidden`.

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `customer_id` | `integer` | `required`, `exists:customers,id` | Applicant customer |
| `loan_type` | `string` | `required`, `in:mortgage,personal,auto,business` | Type of loan |
| `principal_amount` | `numeric`| `required`, `min:1`, `max:999999999.99` | Principal requested |
| `interest_rate` | `numeric` | `required`, `min:0`, `max:100` | Annual interest rate % |
| `term_months` | `integer` | `required`, `min:1`, `max:480` | Loan term in months |
| `start_date` | `date` | `required`, `date` | Effective start date |

**Request Example:**
```json
{
  "customer_id": 10,
  "loan_type": "personal",
  "principal_amount": 15000.00,
  "interest_rate": 6.50,
  "term_months": 36,
  "start_date": "2026-09-01"
}
```

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Loan application submitted successfully.",
  "data": {
    "id": 18,
    "loan_number": "LN-2026-00018",
    "customer_id": 10,
    "loan_type": "personal",
    "principal_amount": "15000.00",
    "outstanding_balance": "15000.00",
    "interest_rate": "6.50",
    "term_months": 36,
    "status": "pending",
    "start_date": "2026-09-01",
    "next_payment_date": "2026-10-01"
  }
}
```

---

### `GET /api/loans/{id}`
Retrieve single loan details.

- **Role**: All authenticated roles

---

### `PUT /api/loans/{id}`
Update loan balance, payment schedule, or status.
- Setting `outstanding_balance` to `0` automatically transitions status to `completed`.
- Transitioning status to `delinquent` or `defaulted` generates a high-priority compliance alert.
- Valid status transitions: `pending → active`, `active → delinquent`, `delinquent → defaulted`, `defaulted → completed`. Invalid transitions return `422`.
- Completed loans cannot be modified.

- **Role**: `admin`, `manager`

> [!CAUTION]
> **CSR** cannot update loans — returns `403 Forbidden`.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `outstanding_balance` | `numeric` | `sometimes`, `min:0` |
| `next_payment_date` | `date` | `sometimes`, `date` |
| `status` | `string` | `sometimes`, `in:pending,active,completed,defaulted,delinquent` |

---

### `POST /api/loans/{id}/approve`
Approve a pending loan application. Transitions status to `active`.

- **Role**: `admin`, `manager`

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Loan approved and activated successfully.",
  "data": {
    "id": 18,
    "status": "active"
  }
}
```

---

## 6. Alert Endpoints

### `GET /api/alerts`
List security, compliance, and AML alerts with optional filters.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `severity` (`string`, optional): `high`, `medium`, `low`
  - `status` (`string`, optional): `open`, `in-progress`, `resolved`
  - `assigned_to` (`integer`, optional): Staff User ID
  - `alert_type` (`string`, optional): `suspicious_transaction`, `kyc_expiring`, `login_attempt`, `loan_delinquent`

---

### `GET /api/alerts/{id}`
Retrieve single alert details with linked polymorphic entity (`Customer`, `Account`, `Transaction`, or `Loan`).

- **Role**: All authenticated roles

---

### `POST /api/alerts/{id}/assign`
Assign an alert to a specific compliance officer / staff member. Transitions status to `in-progress` if currently `open`.

- **Role**: `admin`, `manager`, `compliance`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `user_id` | `integer` | `required`, `exists:users,id` |

**Request Example:**
```json
{
  "user_id": 3
}
```

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Alert assigned successfully.",
  "data": {
    "id": 5,
    "alert_number": "ALT-2026-58219",
    "status": "in-progress",
    "assigned_to": {
      "id": 3,
      "name": "David Compliance",
      "email": "compliance@bankvision.com"
    }
  }
}
```

---

### `POST /api/alerts/{id}/resolve`
Mark an alert as resolved and record the `resolved_at` timestamp.

- **Role**: `admin`, `manager`, `compliance`

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Alert resolved successfully.",
  "data": {
    "id": 5,
    "status": "resolved",
    "resolved_at": "2026-08-23 15:50:00"
  }
}
```

---

## 7. Branch Endpoints

### `GET /api/branches`
List paginated physical bank branches with manager information and total employee counts.

- **Role**: All authenticated roles
- **Scoping**:
  - `admin`, `compliance`, `analyst`, `auditor`: See all branches bank-wide.
  - `manager`, `csr`: See **only their assigned branch** (returns a list of 1).
- **Query Parameters**:
  - `search` (`string`, optional): Search branch code, name, or phone
  - `status` (`string`, optional): `active`, `inactive`, `under_renovation`
  - `city` (`string`, optional): Filter by city
  - `manager_id` (`integer`, optional): Filter by assigned manager
  - `sort_by` (`string`, optional): Column to sort (default: `created_at`)
  - `sort_direction` (`string`, optional): `asc` or `desc`
  - `per_page` (`integer`, optional): Results per page (default: 15)

---

### `GET /api/branches/{id}`
Retrieve single branch details.

- **Role**: All authenticated roles
- **Scoping**: `manager` and `csr` can only view their own assigned branch. Accessing any other branch ID returns `403 Forbidden`.

---

### `POST /api/branches`
Create a new bank branch.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `branch_code` | `string` | `required`, `string`, `unique:branches,branch_code` |
| `branch_name` | `string` | `required`, `string`, `max:255` |
| `address` | `string` | `nullable` |
| `city` | `string` | `nullable`, `max:100` |
| `phone` | `string` | `nullable`, `max:20` |
| `status` | `string` | `sometimes`, `in:active,inactive,under_renovation` |
| `manager_id` | `integer` | `nullable`, `exists:users,id` |

**Request Example:**
```json
{
  "branch_code": "BR005",
  "branch_name": "Uptown Financial Center",
  "address": "500 Madison Ave",
  "city": "Metropolis",
  "phone": "+1-555-9000",
  "status": "active",
  "manager_id": 2
}
```

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Branch created successfully.",
  "data": {
    "id": 5,
    "branch_code": "BR005",
    "branch_name": "Uptown Financial Center",
    "city": "Metropolis",
    "status": "active",
    "total_employees": 0
  }
}
```

---

### `PUT /api/branches/{id}`
Update branch details.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `branch_name` | `string` | `sometimes`, `max:255` |
| `address` | `string` | `nullable` |
| `city` | `string` | `nullable`, `max:100` |
| `phone` | `string` | `nullable`, `max:20` |
| `status` | `string` | `sometimes`, `in:active,inactive,under_renovation` |
| `manager_id` | `integer` | `nullable`, `exists:users,id` |

---

### `DELETE /api/branches/{id}`
Delete a branch. Cannot be deleted if employees or customers are assigned to it.

- **Role**: `admin`

**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Branch deleted successfully."
}
```

---

## 8. Dashboard Endpoints

### `GET /api/dashboard/stats`
Retrieve aggregated KPI statistics (cached for 60 seconds).

- **Role**: All authenticated roles

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "total_customers": 150,
    "total_accounts": 280,
    "total_transactions": 1420,
    "total_loans": 45,
    "open_alerts": 8,
    "flagged_transactions": 3,
    "pending_loans": 5,
    "active_accounts": 265
  }
}
```

---

### `GET /api/dashboard/chart-data`
Retrieve daily transaction volume and transaction count for the last 30 days (cached for 300 seconds).

- **Role**: All authenticated roles

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "date": "2026-07-25",
      "count": 42,
      "volume": "128450.50"
    },
    {
      "date": "2026-07-26",
      "count": 38,
      "volume": "94200.00"
    }
  ]
}
```

---

### `GET /api/dashboard/recent-activity`
Retrieve recent transactions and open alerts for the dashboard activity feed.

- **Role**: All authenticated roles

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "recent_transactions": [
      {
        "type": "transaction",
        "id": 89,
        "number": "TXN-2026-00089",
        "description": "Deposit — USD 2,500.00",
        "status": "completed",
        "customer": "John Doe",
        "date": "2026-08-23 15:45:10"
      }
    ],
    "recent_alerts": [
      {
        "type": "alert",
        "id": 5,
        "number": "ALT-2026-58219",
        "description": "Large cross-border wire transfer detected.",
        "severity": "high",
        "alert_type": "suspicious_transaction",
        "date": "2026-08-23 15:40:00"
      }
    ]
  }
}
```
