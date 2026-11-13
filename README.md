# A Flower Arrangement Event Booking and Inventory Management System with Gemini AI-Assisted Image Analysis

Raflora is a web application for managing floral and event-service bookings, quotations, payment-reference tracking and verification, inventory, and post-event returns. Guests can submit booking requests and claim them after creating an account; clients, administrators, and staff then manage the booking through its lifecycle.

---

## Group Members & Roles

- **Prudenciano, Christian Art** — Project Manager and Quality Assurance
- **Nadonza, Lismer John** — Lead Programmer
- **Labonite, John Lloyd** — UI/UX Designer and Quality Assurance
- **Pantua, Ryan** — Lead Researcher and Quality Assurance
- **Samonte, Daniela Rose** — Documenter

## Application Roles

- **Client:** Submits bookings, communicates with Raflora, reviews quotations, submits payment references, and tracks their verification and booking progress.
- **Admin:** Reviews bookings and payment references, prepares quotations, manages inventory and packages, and oversees operations.
- **Staff:** Handles booking preparation, dispatch, event execution, returns, and inventory reconciliation.

## System Workflow

The standard booking lifecycle has 14 stages:

1. **Guest:** Request Submitted → Awaiting Claim
2. **Client and Admin:** Raflora Review → Material Preparation / Validation → Quotation → Approval → Payment Reference Submission & Admin Verification → Confirmed
3. **Staff:** Preparation & Reservation → Dispatch → Event Execution → Material Return → Inventory Reconciliation → Completion

Guests can submit a request without an account, but must register or sign in with the same email address and claim the request to continue in the client workflow. Delivery bookings use a shorter operational workflow because material return and inventory reconciliation do not apply to sold items. Payments are made outside Raflora; the system records submitted payment references and their verification status.

## Tech Stack

- **Backend:** PHP 8.2+ and Laravel 12
- **Frontend:** Laravel Blade, Tailwind CSS 4, JavaScript, and Vite 7
- **Database:** SQLite by default; MySQL and MariaDB are supported
- **Integrations:** Google OAuth, Google Gemini image/material analysis, and Brevo email (configuration required)
- **Tools & Version Control:** Composer, Node.js/npm, Git

## System Requirements & Prerequisites

Install the following before setting up the project:

- PHP 8.2 or later, with the extensions required by Laravel and the selected database driver (for SQLite, enable `pdo_sqlite` and `sqlite3`)
- Composer
- Node.js 20+ and npm
- Git
- SQLite for the default local setup, or MySQL/MariaDB if you choose to use that database

## Installation & Setup Instructions

### 1. Clone the Repository

```bash
git clone https://github.com/grclismer/Deployment-Raflora.git
cd Deployment-Raflora
```

### 2. Install Dependencies and Configure the Application

```bash
composer install
npm ci
```

Create your local environment file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

### 3. Configure the Database

The example environment uses SQLite. Create its local database file:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

The default SQLite connection uses `database/database.sqlite`; no database credentials are required. If you prefer MySQL or MariaDB, create a database and update `.env` with the appropriate connection settings:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=raflora
DB_USERNAME=root
DB_PASSWORD=
```

Apply the schema and optionally load the development/demo records:

```bash
php artisan migrate
php artisan db:seed
```

The migrations create the schema; there is no separate SQL backup required for a fresh local setup. Seeding creates demo accounts and sample data. Do not use demo credentials in production.

### 4. Build Frontend Assets

```bash
npm run build
```

## Optional External Services

The core application can be set up without external service credentials. To use the integrations, configure their values in your local `.env`:

- **Gemini image/material analysis:** Set `GEMINI_API_KEY`.
- **Google sign-in:** Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI`.
- **Email delivery:** Configure the mail provider and its credentials (for Brevo, set `BREVO_API_KEY`).

## Running the Application

For the full local development environment (web server, queue worker, logs, and Vite):

```bash
composer run dev
```

Open [http://localhost:8000](http://localhost:8000). To run services individually instead, use separate terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

Run the automated test suite with:

```bash
composer run test
```

## Login Credentials

These accounts are intended for local development only:

- **Admin:** `admin@raflora.com` / `Admin@12345`
- **Staff:** `lismerpalce09@gmail.com` / `Staff@12345`
- **Client:** `client@demo.com` / `Client@12345`

