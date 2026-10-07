# BankVision — Backend API

A RESTful API powering the BankVision banking management platform, built with **Laravel 12** and secured with **Laravel Sanctum** token authentication.

---

## Overview

BankVision Backend is a multi-role banking operations API that serves a React SPA. It handles core banking entities (customers, accounts, transactions, loans), compliance workflows (KYC, AML alerts, SAR filings), and a configurable analytics dashboard — all protected by fine-grained role-based access control.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Authentication | Laravel Sanctum (API tokens + 2FA) |
| Database | SQLite (dev) / MySQL (prod-ready) |
| Testing | PHPUnit 11 |
| Code Style | Laravel Pint |

---

## Features

### Core Banking
- **Customers** — full lifecycle management with KYC document upload, verification, and secure download
- **Accounts** — multi-type accounts (checking, savings, etc.) linked to customers and branches
- **Transactions** — record, approve, and flag transactions; high-value threshold detection
- **Loans** — creation, approval workflow, and lifecycle tracking

### Compliance & Risk
- **Alerts** — automated alert generation, assignment, and resolution workflow
- **AML / SAR Filings** — Suspicious Activity Report management for compliance officers
- **KYC Queue** — document-based customer identity verification workflow
- **Audit Logs** — immutable, queryable log of all system actions

### Security
- **Two-Factor Authentication (2FA)** — per-user TOTP-based login challenge with resend support
- **Session Management** — view active sessions, revoke individual or all sessions
- **Login History** — full login activity log per user
- **API Token Management** — admin-scoped personal access token registry

### Dashboard & Analytics
- **Role-Based Dashboards** — each role (Admin, Manager, Compliance, Analyst, CSR, Auditor) receives a tailored dashboard view
- **Customizable Layouts** — per-user drag-and-resize widget layout, persisted server-side
- **Reports & Risk Analysis** — financial metrics, transaction volume, risk indicators
- **Audit Investigation Dashboard** — dedicated stats for auditors

### System
- **Branch Management** — multi-branch support with manager assignments
- **User Management** — staff accounts with role assignment
- **System Settings** — configurable banking thresholds and interest rates (admin-only)
- **In-App Notifications** — bell-icon notification feed with mark-read support
- **Global Search** — cross-entity search across customers, accounts, transactions, and more

---

## Role-Based Access Control

The API enforces role restrictions at the route level using a custom `role` middleware. The available roles are:

| Role | Description |
|---|---|
| `admin` | Full system access including user management and system config |
| `manager` | Branch operations, loan approvals, transaction approvals |
| `compliance` | AML/SAR management, customer updates, alert resolution |
| `analyst` | Read-only analytics and reporting access |
| `csr` | Customer service — customer/account/transaction creation |
| `auditor` | Audit log access and investigation dashboards |

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/   # 19 API controllers
│   ├── Middleware/        # Auth, role, throttle middleware
│   └── Requests/          # Form request validation classes
├── Models/                # Eloquent models (User, Customer, Account, Transaction, Loan, …)
├── Services/              # Business logic layer (15 service classes)
├── Policies/              # Authorization policies
├── Observers/             # Model event observers
└── Enums/                 # Typed PHP enums

database/
├── migrations/            # 21 timestamped migrations
├── factories/             # Model factories for testing/seeding
└── seeders/               # Database seeders

routes/
└── api.php                # All API route definitions (~290 lines, grouped by resource)
```

---

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm (for Vite asset compilation)

### Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy the environment file and configure it
cp .env.example .env

# 3. Generate the application key
php artisan key:generate

# 4. Run database migrations
php artisan migrate

# 5. (Optional) Seed the database with sample data
php artisan db:seed
```

### Running the Development Server

```bash
# Start API server on http://localhost:8000
php artisan serve

# Or run all services concurrently (server + queue + log watcher)
composer run dev
```

### Running Tests

```bash
composer run test
# or
php artisan test
```

---

## Environment Configuration

Copy `.env.example` to `.env` and configure the following key variables:

```env
# Database (SQLite by default; switch to MySQL for production)
DB_CONNECTION=sqlite

# Banking business logic thresholds
BANKING_HIGH_VALUE_THRESHOLD=10000      # Transactions above this trigger alerts
BANKING_EST_FEE_INCOME_RATE=0.005
BANKING_EST_OPERATING_COST_RATIO=0.35
BANKING_EST_CORPORATE_TAX_RATE=0.21
# ... (see .env.example for the full list)
```

---

## API Overview

All endpoints are prefixed with `/api`. Protected routes require a `Bearer` token in the `Authorization` header (issued on login).

| Resource | Endpoints |
|---|---|
| Auth | `POST /login`, `POST /login/2fa`, `POST /logout`, `GET /user` |
| Dashboard | `GET /dashboard/stats`, `/chart-data`, `/recent-activity`, `/risk-analysis` |
| Customers | `GET/POST /customers`, `GET/PUT/DELETE /customers/{id}` |
| KYC Docs | `POST/GET /customers/{id}/kyc-documents`, `GET/DELETE /kyc-documents/{id}` |
| Accounts | `GET/POST /accounts`, `GET/PUT/DELETE /accounts/{id}` |
| Transactions | `GET/POST /transactions`, `POST /transactions/{id}/approve`, `/flag` |
| Loans | `GET/POST /loans`, `GET/PUT /loans/{id}`, `POST /loans/{id}/approve` |
| Alerts | `GET /alerts`, `POST /alerts/{id}/resolve`, `/assign` |
| SAR Filings | `GET/POST /sar-filings` |
| Audit Logs | `GET /audit-logs`, `GET /audit-logs/{id}` |
| Branches | `GET/POST /branches`, `GET/PUT/DELETE /branches/{id}` |
| Users | `GET/POST /users`, `GET/PUT/DELETE /users/{id}` |
| Settings | `PUT /settings/profile`, `/security/2fa`, `GET /settings/system` |
| Notifications | `GET /notifications`, `POST /notifications/{id}/read` |
| Search | `GET /search` |

---

## License

MIT
