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
| `currency` | `string` | `sometimes`, `size:3` | Currency code (default: `USD`) |
| `balance` | `numeric` | `sometimes`, `min:0`, `max:999999999.99` | Initial opening balance |
| `interest_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Applicable interest % |
| `opened_date` | `date` | `sometimes`, `date` | Default: today |

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
    "opened_date": "2026-09-19"
  }
}
```

---

### `GET /api/accounts/{id}`
Retrieve single account details with linked owner and branch data.

- **Role**: All authenticated roles

---

### `PUT /api/accounts/{id}`
Update account status (freeze/unfreeze) or interest rate.

- **Role**: `admin`, `manager`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `status` | `string` | `sometimes`, `in:active,frozen,closed` |
| `interest_rate` | `numeric` | `sometimes`, `min:0`, `max:100` |

---

### `DELETE /api/accounts/{id}`
Close an account (sets status to `closed`). Account must have balance = 0.

- **Role**: `admin`, `manager`

---

### `GET /api/accounts/{id}/transactions`
List transaction history for a specific account.

- **Role**: All authenticated roles
- **Query Parameters**: `type`, `status`, `date_from`, `date_to`, `page`, `per_page`

---

## 5. Transaction Endpoints

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
- Completed transactions execute balance adjustment atomically using pessimistic row-locking (`lockForUpdate`).
- Withdrawals exceeding available balance reject with `422 Unprocessable Entity` ("Insufficient funds").
- Transactions on `frozen` or `closed` accounts are rejected.

- **Role**: `admin`, `manager`, `csr`
- **Scoping**:
  - `manager` and `csr`: Can only record transactions on accounts in their assigned branch.
  - `csr`: Restricted to `deposit` and `withdrawal` only. Attempting `transfer` or `wire` returns `403 Forbidden`.

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `account_id` | `integer` | `required`, `exists:accounts,id` | Target account |
| `transaction_type` | `string` | `required`, `in:deposit,withdrawal,transfer,wire` | Type |
| `amount` | `numeric` | `required`, `min:0.01`, `max:999999999.99` | Amount |
| `currency` | `string` | `sometimes`, `size:3` | Default `USD` |
| `description` | `string` | `nullable` | Description |
| `channel` | `string` | `sometimes`, `in:online,branch,atm,mobile` | Channel |
| `counterparty` | `string` | `nullable`, `max:255` | Counterparty info |
| `status` | `string` | `sometimes`, `in:completed,pending` | Default `completed` |

---

### `GET /api/transactions/{id}`
Retrieve details for a single transaction.

- **Role**: All authenticated roles

---

### `POST /api/transactions/{id}/approve`
Approve a pending or flagged transaction. Atomically applies balance changes and logs approver metadata.

- **Role**: `admin`, `manager`

---

### `POST /api/transactions/{id}/flag`
Flag a transaction for AML/compliance investigation. Automatically creates a polymorphic compliance alert.

- **Role**: `admin`, `manager`, `compliance`

---

## 6. Loan Endpoints

### `GET /api/loans`
List paginated loans with optional filtering.

- **Role**: All authenticated roles
- **Query Parameters**: `customer_id`, `type`, `status` (`pending`, `active`, `completed`, `delinquent`, `defaulted`)

---

### `POST /api/loans`
Submit a new loan application. Created with `pending` status.

- **Role**: `admin`, `manager`
- **Scoping**: `manager` can only submit loans for customers in their assigned branch.

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `customer_id` | `integer` | `required`, `exists:customers,id` | Applicant customer |
| `loan_type` | `string` | `required`, `in:mortgage,personal,auto,business` | Type |
| `principal_amount` | `numeric`| `required`, `min:1`, `max:999999999.99` | Principal |
| `interest_rate` | `numeric` | `required`, `min:0`, `max:100` | Rate % |
| `term_months` | `integer` | `required`, `min:1`, `max:480` | Term |
| `start_date` | `date` | `required`, `date` | Start date |

---

### `GET /api/loans/{id}`
Retrieve single loan details.

- **Role**: All authenticated roles

---

### `PUT /api/loans/{id}`
Update loan balance, payment schedule, or status.
- Transitioning status to `delinquent` or `defaulted` automatically creates a compliance alert.
- Valid status transitions: `pending → active`, `active → delinquent`, `delinquent → defaulted`, `defaulted → completed`. Invalid transitions return `422`.

- **Role**: `admin`, `manager`

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

---

## 7. Alert Endpoints

### `GET /api/alerts`
List compliance, AML, and security alerts with optional filters.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `severity` (`string`, optional): `high`, `medium`, `low`
  - `status` (`string`, optional): `open`, `in-progress`, `resolved`
  - `assigned_to` (`integer`, optional): Staff User ID
  - `alert_type` (`string`, optional): `suspicious_transaction`, `kyc_expiring`, `login_attempt`, `loan_delinquent`

---

### `GET /api/alerts/{id}`
Retrieve single alert details with linked entity (`Customer`, `Account`, `Transaction`, or `Loan`).

- **Role**: All authenticated roles

---

### `POST /api/alerts/{id}/assign`
Assign an alert to a specific compliance officer. Transitions status to `in-progress` if `open`.

- **Role**: `admin`, `manager`, `compliance`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `user_id` | `integer` | `required`, `exists:users,id` |

---

### `POST /api/alerts/{id}/resolve`
Mark an alert as resolved and record resolution timestamp.

- **Role**: `admin`, `manager`, `compliance`

---

## 8. Suspicious Activity Report (SAR) Endpoints

Used by compliance officers and management for FinCEN / regulatory anti-money laundering filings.

### `GET /api/sar-filings`
List paginated SAR filings with search and status filters.

- **Role**: `admin`, `manager`, `compliance`, `analyst`, `auditor`
- **Query Parameters**:
  - `search` (`string`, optional): Search customer name, number, or narrative
  - `status` (`string`, optional): `draft`, `under_review`, `filed`, `escalated`
  - `page` (`integer`, optional): Page number
  - `per_page` (`integer`, optional): Results per page (default: 15)

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 1,
      "reference": "SAR-2026-00001",
      "customer_name": "ACME Holdings Corp",
      "customer_number": "CUST-2026-00045",
      "category": "Structuring / Smurfing (<$10k Cash)",
      "amount": "49500.00",
      "status": "under_review",
      "narrative": "Five consecutive deposits of $9,900 made over 48 hours across three branches.",
      "action_taken": "Accounts frozen pending compliance review",
      "alert_id": 14,
      "created_at": "2026-09-18 14:30:00",
      "user": {
        "id": 3,
        "name": "David Compliance",
        "email": "compliance@bankvision.com"
      }
    }
  ]
}
```

---

### `POST /api/sar-filings`
File a new Suspicious Activity Report.

- **Role**: `admin`, `manager`, `compliance`

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `customer_name` | `string` | `required`, `max:255` | Name of suspect customer |
| `customer_number` | `string` | `nullable`, `max:50` | Customer reference number |
| `category` | `string` | `required`, `in:Structuring / Smurfing (<$10k Cash),Rapid Wire Movement / Pass-through Account,Unusual Transaction for Profile / Industry,Suspected Shell Company / Opaque Ownership,PEP (Politically Exposed Person) Sanctions Check,Terrorist Financing Suspicion,Cyber Fraud / Account Takeover Infiltration` | FinCEN AML classification |
| `amount` | `numeric` | `required`, `min:0.01` | Aggregate suspicious amount |
| `status` | `string` | `sometimes`, `in:draft,under_review,filed,escalated` | Default `draft` |
| `narrative` | `string` | `required`, `min:15`, `max:5000` | Detailed case narrative |
| `action_taken` | `string` | `nullable`, `max:255` | Immediate action taken |
| `alert_id` | `integer` | `nullable`, `exists:alerts,id` | Linked compliance alert |

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Suspicious Activity Report SAR-2026-00002 recorded.",
  "data": {
    "id": 2,
    "reference": "SAR-2026-00002",
    "customer_name": "Alexander Vance",
    "status": "draft"
  }
}
```

---

## 9. Branch Endpoints

### `GET /api/branches`
List paginated physical bank branches.

- **Role**: All authenticated roles
- **Scoping**: `manager` and `csr` see **only their assigned branch**.
- **Query Parameters**: `search`, `status`, `city`, `manager_id`, `sort_by`, `sort_direction`, `per_page`

---

### `GET /api/branches/{id}`
Retrieve single branch details.

- **Role**: All authenticated roles (`manager` and `csr` restricted to own branch)

---

### `POST /api/branches`
Create a new branch.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `branch_code` | `string` | `required`, `unique:branches,branch_code` |
| `branch_name` | `string` | `required`, `max:255` |
| `address` | `string` | `nullable` |
| `city` | `string` | `nullable`, `max:100` |
| `phone` | `string` | `nullable`, `max:20` |
| `status` | `string` | `sometimes`, `in:active,inactive,under_renovation` |
| `manager_id` | `integer` | `nullable`, `exists:users,id` |

---

### `PUT /api/branches/{id}`
Update branch information.

- **Role**: `admin`

---

### `DELETE /api/branches/{id}`
Delete a branch. Cannot be deleted if employees or customers are assigned to it.

- **Role**: `admin`

---

## 10. User & Staff Management Endpoints

### `GET /api/users`
List staff accounts with role filtering, status, and pagination.

- **Role**: `admin`, `manager`, `compliance`, `analyst`, `auditor`
- **Scoping**: `manager` sees only staff assigned to their branch.
- **Query Parameters**:
  - `search` (`string`, optional): Search name, email, or phone
  - `role` (`string`, optional): `admin`, `manager`, `compliance`, `analyst`, `csr`, `auditor`
  - `status` (`string`, optional): `pending`, `active`, `suspended`
  - `branch_id` (`integer`, optional): Filter by branch (admin/auditor only)
  - `page` (`integer`, optional)
  - `per_page` (`integer`, optional)

---

### `GET /api/users/{id}`
Retrieve single user account details.

- **Role**: `admin`, `manager`, `compliance`, `analyst`, `auditor`

---

### `GET /api/users/eligible-relationship-managers`
Retrieve active staff eligible to be assigned as customer Relationship Managers (`manager`, `csr`) for a branch.

- **Role**: `admin`, `manager`, `csr`
- **Scoping**: `manager` and `csr` can only query their own branch.
- **Query Parameters**:
  - `branch_id` (`integer`, required): Branch ID to look up

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 2,
      "name": "James Smith",
      "email": "manager@bankvision.com",
      "role": "manager",
      "branch_id": 1
    }
  ]
}
```

---

### `POST /api/users`
Create a new staff account.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `name` | `string` | `required`, `max:255` | Full name |
| `email` | `string` | `required`, `email`, `max:255`, `unique:users,email` | Unique corporate email |
| `password` | `string` | `required`, `min:8` | Password |
| `role` | `string` | `required`, `in:admin,manager,compliance,analyst,csr,auditor` | Assigned role |
| `branch_id` | `integer` | `nullable`, `exists:branches,id` | Required for branch-scoped roles |
| `status` | `string` | `sometimes`, `in:pending,active,suspended` | Default `active` |
| `phone` | `string` | `nullable`, `max:50` | Contact phone |

---

### `PUT /api/users/{id}`
Update an existing staff account.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `name` | `string` | `sometimes`, `required`, `max:255` |
| `email` | `string` | `sometimes`, `required`, `email`, `unique:users,email,{id}` |
| `password` | `string` | `sometimes`, `nullable`, `min:8` |
| `role` | `string` | `sometimes`, `required`, `in:admin,manager,compliance,analyst,csr,auditor` |
| `branch_id` | `integer` | `nullable`, `exists:branches,id` |
| `status` | `string` | `sometimes`, `required`, `in:pending,active,suspended` |
| `phone` | `string` | `nullable`, `max:50` |

---

### `DELETE /api/users/{id}`
Delete a staff account. Cannot delete self or users with active operational assignments.

- **Role**: `admin`

---

## 11. Audit Log Endpoints

Immutable compliance and security trail recording actions across models, users, and IP addresses.

### `GET /api/audit-logs`
List paginated audit logs with rich multi-parameter filtering.

- **Role**: `admin`, `auditor`, `compliance`, `manager`
- **Scoping**:
  - `admin`, `auditor`: Full bank-wide audit logs.
  - `compliance`: Restricted to compliance-relevant models (`customers`, `accounts`, `transactions`, `loans`, `alerts`) and critical actions (`approve`, `flag`, `freeze`, etc.).
  - `manager`: Restricted to actions performed by staff in their assigned branch.
- **Query Parameters**:
  - `search` (`string`, optional)
  - `action` (`string`, optional): e.g. `login`, `create`, `update`, `approve`, `flag`
  - `table_name` (`string`, optional): e.g. `transactions`, `customers`, `users`
  - `user_id` (`integer`, optional)
  - `record_id` (`integer`, optional)
  - `ip_address` (`string`, optional)
  - `role` (`string`, optional)
  - `date_from` (`date`, optional): `YYYY-MM-DD`
  - `date_to` (`date`, optional): `YYYY-MM-DD`
  - `sort_by` (`string`, optional): `created_at`, `action`, `table_name`, `record_id`, `ip_address`
  - `sort_direction` (`string`, optional): `asc` or `desc`
  - `page` (`integer`, optional)
  - `per_page` (`integer`, optional): Max 100

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 140,
      "action": "transaction.approve",
      "table_name": "transactions",
      "record_id": 89,
      "ip_address": "192.168.1.50",
      "old_values": { "status": "pending" },
      "new_values": { "status": "completed", "approved_at": "2026-09-19 14:00:00" },
      "created_at": "2026-09-19 14:00:00",
      "user": {
        "id": 2,
        "name": "James Smith",
        "role": "manager"
      }
    }
  ]
}
```

---

### `GET /api/audit-logs/{id}`
Retrieve single audit log entry with diff details.

- **Role**: `admin`, `auditor`, `compliance`, `manager` (subject to scoping rules)

---

## 12. Dashboard & Analytical Reports Endpoints

### `GET /api/dashboard/stats`
Aggregated high-level KPI metrics (cached for 60 seconds).

- **Role**: All authenticated roles

---

### `GET /api/dashboard/chart-data`
Daily transaction volume and transaction count for charting.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `days` (`integer`, optional): Number of days (default: 30)

---

### `GET /api/dashboard/recent-activity`
Recent transactions and open alerts for the activity feed.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `limit` (`integer`, optional): Number of records (default: 10)

---

### `GET /api/dashboard/risk-analysis`
Comprehensive risk distribution across customers, loans, transactions, and branches.

- **Role**: All authenticated roles (also available at alias `/api/reports/risk-analysis`)

---

### `GET /api/dashboard/reports`
Analytical portfolio and transaction reports with date-range and branch breakdowns.

- **Role**: `admin`, `manager`, `compliance`, `analyst`, `auditor` (also available at alias `/api/reports`)
- **Query Parameters**:
  - `start_date` (`date`, optional): `YYYY-MM-DD`
  - `end_date` (`date`, optional): `YYYY-MM-DD`
  - `branch_id` (`integer`, optional): Filter branch
  - `period` (`string`, optional): `monthly`, `quarterly`, `yearly`

---

### `GET /api/dashboard/audit-stats`
Auditor investigation dashboard: total audit events, destructive action tallies, suspicious activities, and 24h event timeline.

- **Role**: `admin`, `auditor`

---

### `GET /api/dashboard/audit-report`
Auditor investigation report: destructive action breakdown, user activity rankings, high-risk customer audit logs, flagged transactions, and at-risk loans.

- **Role**: `admin`, `auditor`

---

### `GET /api/dashboard/layout`
Get current user's personalized dashboard layout, or fall back to their role-based default.

- **Role**: All authenticated roles

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "role": "admin",
    "layout_data": [
      { "id": "stats", "x": 0, "y": 0, "w": 12, "h": 2, "visible": true },
      { "id": "charts", "x": 0, "y": 2, "w": 8, "h": 4, "visible": true },
      { "id": "recentActivity", "x": 8, "y": 2, "w": 4, "h": 4, "visible": true }
    ],
    "is_custom": true
  }
}
```

---

### `PUT /api/dashboard/layout`
Persist custom grid positions, sizes, and visibility of dashboard widgets.

- **Role**: All authenticated roles

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `layout_data` | `array` | `required`, `array` |
| `layout_data.*.id` | `string` | `required`, `string` |
| `layout_data.*.visible` | `boolean` | `sometimes`, `boolean` |

---

### `POST /api/dashboard/layout/reset`
Reset the user's dashboard layout to their role's canonical default layout.

- **Role**: All authenticated roles

---

## 13. In-App Notification Endpoints

### `GET /api/notifications`
Retrieve latest in-app notifications for the authenticated user along with total unread count.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `limit` (`integer`, optional): Default: 15

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "unread_count": 2,
    "notifications": [
      {
        "id": 18,
        "title": "New sign-in detected",
        "message": "You signed in to BankVision from a new session.",
        "link": "/dashboard",
        "type": "info",
        "read": false,
        "created_at": "2026-09-19 15:30:00"
      }
    ]
  }
}
```

---

### `POST /api/notifications/{id}/read`
Mark a specific notification as read.

- **Role**: All authenticated roles

---

### `POST /api/notifications/read-all`
Mark all unread notifications for the current user as read.

- **Role**: All authenticated roles

---

## 14. Settings & Administration Endpoints

### Profile & Identity (All roles)

#### `PUT /api/settings/profile`
Update own name, email, and phone.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `name` | `string` | `sometimes`, `required`, `max:255` |
| `email` | `string` | `sometimes`, `required`, `email`, `max:255`, `unique:users,email,{id}` |
| `phone` | `string` | `nullable`, `max:50` |

---

#### `PUT /api/settings/profile/password`
Update password. Verifies current password before updating.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `current_password` | `string` | `required` (must match current hash) |
| `password` | `string` | `required`, `min:8`, `confirmed` |
| `password_confirmation` | `string` | `required` |

---

#### `POST /api/settings/profile/avatar`
Upload a profile photo. Replaces any previous photo and stores on public disk.

- **Headers**: `Content-Type: multipart/form-data`
- **Validation**: `avatar` (`required`, `image`, `mimes:jpg,jpeg,png,webp`, `max:2048`)

---

#### `DELETE /api/settings/profile/avatar`
Remove own profile photo.

---

### User Preferences & Notifications (All roles)

#### `GET /api/settings`
Retrieve combined user settings (2FA state, notification toggles, UI preferences).

---

#### `PUT /api/settings/notifications`
Update notification channel preferences and alert category subscriptions.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `email_notifications` | `boolean` | `sometimes`, `boolean` |
| `push_notifications` | `boolean` | `sometimes`, `boolean` |
| `transaction_alerts` | `boolean` | `sometimes`, `boolean` |
| `loan_alerts` | `boolean` | `sometimes`, `boolean` |
| `account_alerts` | `boolean` | `sometimes`, `boolean` |
| `weekly_digest` | `boolean` | `sometimes`, `boolean` |
| `alert_preferences` | `array` | `sometimes`, `array` (critical, high, medium, low booleans) |

---

#### `PUT /api/settings/preferences`
Update UI theme, language, dashboard view density, and timezone.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `theme` | `string` | `sometimes`, `in:dark,light,system` |
| `language` | `string` | `sometimes`, `in:en,fr,es,de,ar` |
| `dashboard_view` | `string` | `sometimes`, `in:default,compact,detailed` |
| `timezone` | `string` | `sometimes`, valid timezone string |
| `date_format` | `string` | `sometimes`, `in:Y-m-d,d/m/Y,m/d/Y,d M Y` |
| `items_per_page` | `integer` | `sometimes`, `in:10,15,25,50,100` |

---

### Security & Sessions (All roles)

#### `POST /api/settings/security/2fa`
Toggle two-factor authentication. Disabling takes effect immediately. Enabling triggers an email verification challenge.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `enabled` | `boolean` | `required`, `boolean` |
| `channel` | `string` | `sometimes`, `in:email,sms,authenticator` (email supported) |

---

#### `POST /api/settings/security/2fa/send-code`
Send a one-time 6-digit confirmation code to email to activate 2FA. Code expires in 10 minutes.

---

#### `POST /api/settings/security/2fa/verify`
Verify code and enable 2FA on the account.

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `code` | `string` | `required`, `string` |

---

#### `GET /api/settings/security/sessions`
List all active personal access tokens for the user, highlighting the current session.

---

#### `DELETE /api/settings/security/sessions/{id}`
Revoke a specific session. Current active session cannot be revoked via this endpoint (use `/api/logout`).

---

#### `POST /api/settings/security/sessions/revoke-all`
Revoke all other active sessions for the authenticated user.

---

#### `GET /api/settings/security/login-history`
List paginated historical logins with IP address, browser, platform, and success status.

- **Query Parameters**: `per_page` (default: 15, max: 100)

---

### API Token Management (Admin only)

#### `GET /api/settings/security/tokens`
List all personal access tokens issued across the platform.

- **Role**: `admin`
- **Query Parameters**: `per_page` (default: 15)

---

#### `POST /api/settings/security/tokens`
Issue a new programmatic API token.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules |
| :--- | :--- | :--- |
| `name` | `string` | `required`, `max:255` |
| `abilities` | `array` | `sometimes`, `array` (list of abilities) |

**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "API token created successfully.",
  "data": {
    "id": 5,
    "name": "Integration-Service-Token",
    "token": "5|gT891...PqL"
  }
}
```

---

#### `DELETE /api/settings/security/tokens/{id}`
Revoke an API token.

- **Role**: `admin`

---

### System Configuration & Health (Admin only)

#### `GET /api/settings/system`
Retrieve institution-wide parameters (bank identity, SWIFT code, currency, default interest rates).

- **Role**: `admin`

---

#### `PUT /api/settings/system`
Update institution-wide configuration and baseline interest rates.

- **Role**: `admin`

**Validation Rules:**
| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `bank.name` | `string` | `sometimes`, `required`, `max:255` | Institution name |
| `bank.legal_name` | `string` | `sometimes`, `required`, `max:255` | Registered legal entity |
| `bank.address` | `string` | `sometimes`, `required`, `max:500` | Headquarters address |
| `bank.city` | `string` | `sometimes`, `required`, `max:100` | City |
| `bank.country` | `string` | `sometimes`, `required`, `max:100` | Country |
| `bank.phone` | `string` | `sometimes`, `required`, `max:50` | Primary phone |
| `bank.email` | `string` | `sometimes`, `required`, `email` | Primary contact email |
| `bank.swift_code` | `string` | `sometimes`, `required`, `max:20` | SWIFT / BIC |
| `bank.website` | `string` | `sometimes`, `required`, `max:255` | Corporate website |
| `currency.code` | `string` | `sometimes`, `in:USD,EUR,GBP,TND,CHF,JPY` | Operating currency |
| `currency.symbol` | `string` | `sometimes`, `required`, `max:10` | Currency symbol |
| `interest.savings_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual savings % |
| `interest.checking_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual checking % |
| `interest.fixed_deposit_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual fixed deposit % |
| `interest.personal_loan_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual personal loan % |
| `interest.business_loan_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual business loan % |
| `interest.mortgage_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual mortgage % |
| `interest.overdraft_rate` | `numeric` | `sometimes`, `min:0`, `max:100` | Annual overdraft % |
| `interest.late_payment_penalty`| `numeric` | `sometimes`, `min:0`, `max:100` | Penalty % |

---

#### `GET /api/settings/system/health`
Platform health snapshot for operations monitoring.

- **Role**: `admin`

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "database": "connected",
    "cache": "operational",
    "storage": "writable",
    "environment": "local",
    "php_version": "8.2.12",
    "laravel_version": "11.x"
  }
}
```

---

## 15. Global Search & Public Endpoints

### `GET /api/search`
Grouped global search used as a server-side bridge for external clients. Searches across customers, accounts, transactions, loans, alerts, users, branches, and audit logs according to the authenticated user's permissions and branch scoping.

- **Role**: All authenticated roles
- **Query Parameters**:
  - `q` (`string`, required): Search string (minimum 2 characters)

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "customers": [
      {
        "id": 10,
        "title": "John Doe",
        "subtitle": "#CUST-2026-00010",
        "meta": "john.doe@example.com"
      }
    ],
    "accounts": [
      {
        "id": 4,
        "title": "ACC-2026-00004",
        "subtitle": "Savings · John Doe",
        "meta": "14,500.000 USD · Active"
      }
    ]
  }
}
```

---

### `GET /api/public/interest-rates`
Published deposit and lending interest rates. Publicly accessible without authentication (mirrors marketing rate sheets).

- **Role**: Public (No authentication required)

**Response (`200 OK`):**
```json
{
  "success": true,
  "data": {
    "savings_rate": 2.5,
    "checking_rate": 0.5,
    "fixed_deposit_rate": 4.25,
    "personal_loan_rate": 7.5,
    "business_loan_rate": 6.0,
    "mortgage_rate": 4.8,
    "overdraft_rate": 12.0,
    "late_payment_penalty": 2.0
  }
}
```
