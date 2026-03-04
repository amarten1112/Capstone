# Capstone
<div align="center">

# 🌱 Virginia Market Square
### Community Vendors Platform — Full-Stack E-Commerce Web Application

**CIS2987 Web Development Capstone · Spring 2026**

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat-square&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Stripe](https://img.shields.io/badge/Stripe-Test_Mode-635BFF?style=flat-square&logo=stripe&logoColor=white)](https://stripe.com)
[![License](https://img.shields.io/badge/License-Academic-2D5016?style=flat-square)](#license)

---

*A full-stack e-commerce platform connecting customers with local farmers, makers, and producers within 50 miles of Virginia, Minnesota.*

[Live Demo](#deployment) · [Database Schema](#database-schema) · [Setup Guide](#local-development-setup) · [Documentation](#documentation)

</div>

---

## 📖 Table of Contents

- [Project Overview](#project-overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Database Schema](#database-schema)
- [Project Structure](#project-structure)
- [Local Development Setup](#local-development-setup)
- [User Roles & Access](#user-roles--access)
- [Screenshots](#screenshots)
- [Development Timeline](#development-timeline)
- [Security Implementation](#security-implementation)
- [Known Limitations](#known-limitations)
- [Future Enhancements](#future-enhancements)
- [Documentation](#documentation)
- [License](#license)

---

## Project Overview

**Virginia Market Square** is a non-profit farmers market operating every **Thursday, June–October (2:30–6:00 PM)** at 111 South 9th Avenue W, Virginia, MN 55792. This capstone project replaces their existing Squarespace site with a custom, database-driven platform that supports three distinct user roles, a full e-commerce workflow, and vendor self-management.

### The Problem

The organization's Squarespace site had no vendor self-management, no online store, no searchable product directory, and increasing subscription costs with no path to ownership. Vendors had to contact admin for every content update.

### The Solution

A purpose-built PHP/MySQL application with:
- Vendor self-service portals (login, manage products, track orders)
- A full shopping cart → checkout → Stripe payment pipeline
- An admin control panel for approvals, events, and contact management
- A normalized 12-table database designed to 3NF

---

## Features

### 🛍️ Public-Facing Store
- Responsive homepage with featured vendors and upcoming market events
- Searchable vendor directory with category filtering
- Product catalog with category browsing, search, and vendor filtering
- Individual vendor storefronts with product listings
- Events calendar with color-coded event types
- Vendor application form with admin approval workflow
- Contact form with optional vendor-specific routing

### 🛒 E-Commerce (Customer)
- Persistent database-backed shopping cart (survives session restarts)
- 5-step checkout: Cart → Shipping → Review → Payment → Confirmation
- Stripe payment processing (test mode, PCI-compliant tokenization)
- Customer account registration, login, and order history
- Order status tracking

### 🏪 Vendor Portal
- Secure vendor login (invitation-only after admin approval)
- Sales dashboard with revenue metrics and top product analytics
- Product management: add, edit, toggle availability, update stock
- Order management: view and update fulfillment status
- Sales history with monthly breakdowns

### ⚙️ Admin Panel
- Unified admin dashboard with system-wide KPIs
- Vendor management: approve, verify, edit, and deactivate vendors
- Vendor application review and approval workflow
- Event calendar management
- Contact submission inbox with status tracking

---

## Technology Stack

| Technology | Version | Purpose |
|---|---|---|
| **PHP** | 8.x | Server-side logic, routing, session management, form handling |
| **MySQL** / MariaDB | 8.0 | Relational database — 12-table normalized schema (3NF) |
| **HTML5** | Semantic | Document structure and accessible markup |
| **CSS3** | Custom + Bootstrap | Styling, responsive grid, component overrides |
| **Bootstrap** | 5.3 (CDN) | Responsive layout, navbar, cards, forms, modals |
| **JavaScript** | Vanilla ES6+ | Form validation, AJAX cart updates, interactive UI |
| **Stripe PHP SDK** | Latest | Payment processing — test mode, PCI-compliant |
| **Composer** | 2.x | PHP dependency management (Stripe SDK) |
| **Apache** | XAMPP / cPanel | Local dev server and production hosting |
| **Git + GitHub** | — | Version control and project history |

---

## Database Schema

The database uses **12 tables** normalized to **Third Normal Form (3NF)** with InnoDB for full foreign key constraint support.

```
┌─────────────────── AUTHENTICATION & USERS ───────────────────┐
│  users ──────┬──── vendors ──────── products ─── categories  │
│              └──── customers                                  │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────── E-COMMERCE ──────────────────────────┐
│  customers ──┬──── cart ◄──── products                       │
│              └──── orders ───── order_items ◄── products     │
│                         └───── transactions                   │
└──────────────────────────────────────────────────────────────┘

┌────────────────────────── SUPPORT ───────────────────────────┐
│  events   contacts ◄── vendors   vendor_applications         │
└──────────────────────────────────────────────────────────────┘
```

### Tables at a Glance

| # | Table | Rows (Sample) | Purpose |
|---|---|---|---|
| 1 | `users` | ~25 | Authentication for all 3 role types |
| 2 | `vendors` | ~10 | Vendor business profiles |
| 3 | `customers` | ~10 | Customer shipping & contact info |
| 4 | `categories` | ~8 | Product category lookup |
| 5 | `products` | ~50 | Vendor product listings |
| 6 | `cart` | — | Persistent shopping cart |
| 7 | `orders` | — | Order headers |
| 8 | `order_items` | — | Line items per order |
| 9 | `transactions` | — | Stripe payment records |
| 10 | `events` | ~12 | Market calendar events |
| 11 | `contacts` | — | Contact form submissions |
| 12 | `vendor_applications` | — | New vendor applications |

> 📄 Full schema with CREATE TABLE statements: [`/docs/database_design.pdf`](docs/)
> 
> 🗺️ ER Diagram: [`/docs/er_diagram.png`](docs/)

---

## Project Structure

```
virginia-market-square/
│
├── index.php                   # Homepage
├── vendors.php                 # Vendor directory
├── vendor-detail.php           # Individual vendor page
├── products.php                # Product catalog
├── product-detail.php          # Product detail page
├── events.php                  # Events calendar
├── contact.php                 # Contact form
├── about.php                   # About / Mission page
├── vendor-apply.php            # Vendor application form
│
├── auth/
│   ├── login.php               # Customer login
│   ├── register.php            # Customer registration
│   ├── vendor-login.php        # Vendor login (invitation only)
│   └── logout.php
│
├── cart/
│   ├── cart.php                # View cart
│   ├── add-to-cart.php         # Add item handler
│   └── update-cart.php         # Update/remove handler
│
├── checkout/
│   ├── checkout.php            # Step 1 — Cart summary
│   ├── checkout-shipping.php   # Step 2 — Shipping info
│   ├── checkout-review.php     # Step 3 — Order review
│   ├── checkout-payment.php    # Step 4 — Stripe payment
│   └── order-confirmation.php  # Step 5 — Confirmation
│
├── customer/
│   ├── dashboard.php           # Customer account
│   └── order-history.php       # Past orders
│
├── vendor/
│   ├── dashboard.php           # Vendor sales dashboard
│   ├── products.php            # Product management
│   └── orders.php              # Order management
│
├── admin/
│   ├── dashboard.php           # Admin overview
│   ├── vendors.php             # Vendor management
│   ├── applications.php        # Application review
│   ├── events.php              # Event management
│   └── contacts.php            # Contact submissions
│
├── includes/
│   ├── config.php              # DB connection & constants
│   ├── header.php              # Reusable nav header
│   ├── footer.php              # Reusable footer
│   ├── auth-check.php          # Role-based access guard
│   └── functions.php           # Shared helper functions
│
├── css/
│   └── style.css               # Custom styles + Bootstrap overrides
│
├── js/
│   └── script.js               # Cart AJAX, form validation, UI
│
├── docs/
│   ├── project_charter.pdf
│   ├── database_design.pdf
│   ├── er_diagram.pdf
│   └── wireframes.pdf
│
├── sql/
│   ├── schema.sql              # Full CREATE TABLE statements
│   └── sample_data.sql         # Test vendors, products, events
│
├── vendor/                     # Composer packages (Stripe SDK)
├── composer.json
└── README.md
```

---

## Local Development Setup

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + PHP 8.x + MySQL) or equivalent
- [Composer](https://getcomposer.org/) for PHP dependencies
- [Git](https://git-scm.com/)
- A free [Stripe account](https://stripe.com) for test API keys

### 1. Clone the Repository

```bash
git clone https://github.com/YOUR-USERNAME/virginia-market-square.git
cd virginia-market-square
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Set Up the Database

Open **phpMyAdmin** (via XAMPP) or your MySQL client and run:

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE farmers_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import the schema
mysql -u root -p farmers_market < sql/schema.sql

# Load sample data
mysql -u root -p farmers_market < sql/sample_data.sql
```

### 4. Configure the Application

Open `includes/config.php` and update your local credentials:

```php
$db_host = '127.0.0.1';
$db_user = 'root';           // your MySQL username
$db_pass = '';               // your MySQL password
$db_name = 'farmers_market';

// Stripe Test Keys (from https://dashboard.stripe.com/test/apikeys)
define('STRIPE_PUBLIC_KEY',  'pk_test_YOUR_KEY_HERE');
define('STRIPE_SECRET_KEY',  'sk_test_YOUR_KEY_HERE');
```

> ⚠️ **Never commit real API keys.** Add `includes/config.php` to `.gitignore` if it contains secrets.

### 5. Start the Server

Place the project folder inside XAMPP's `htdocs/` directory, then:

1. Open **XAMPP Control Panel**
2. Start **Apache** and **MySQL**
3. Visit `http://localhost/virginia-market-square/`

---

## User Roles & Access

The application supports three role types, all authenticated through the `users` table:

| Role | Login URL | Access |
|---|---|---|
| **Customer** | `/auth/login.php` | Browse, cart, checkout, order history |
| **Vendor** | `/auth/vendor-login.php` | Dashboard, product management, order tracking |
| **Admin** | `/admin/` | Full system access — all vendors, events, applications |

### Test Credentials (Sample Data)

```
Admin:    admin@virginiamarketsquare.com  /  Admin1234!
Vendor:   vendor@rusticcedarhomestead.com /  Vendor1234!
Customer: customer@example.com           /  Customer1234!
```

### Stripe Test Cards

```
✅ Success:  4242 4242 4242 4242  |  Any future date  |  Any 3-digit CVC
❌ Decline:  4000 0000 0000 0002  |  Any future date  |  Any 3-digit CVC
```

---

## Development Timeline

| Week | Dates | Focus | Phases |
|---|---|---|---|
| 1 | Mar 16–22 | Planning, wireframes (22+ pages), ER diagram, database design | 1–2 |
| 2 | Mar 23–29 | SQL schema (12 tables), test data, all 3 authentication systems | 2–3 |
| 3 | Mar 30–Apr 5 | Product browsing with JOINs, vendor storefront, search | 4 |
| 4 | Apr 6–12 | Shopping cart, 5-step checkout, order creation | 4 |
| 5 | Apr 13–19 | Bootstrap responsive design across all 25+ pages | 6 |
| 6 | Apr 20–26 | Stripe integration, vendor dashboard analytics, JS/AJAX | 5, 7 |
| 7 | Apr 27–May 1 | Sample content, testing, bug fixes, deployment | 8–9 |

**Total: 52 hours across 9 phases**

---

## Security Implementation

| Measure | Implementation |
|---|---|
| **SQL Injection** | All queries use PDO prepared statements with bound parameters |
| **Password Storage** | `password_hash()` with `PASSWORD_BCRYPT` — never plain text |
| **CSRF Protection** | Session token generated per form, validated server-side |
| **XSS Prevention** | `htmlspecialchars()` on all user-supplied output |
| **Role Enforcement** | `auth-check.php` validates session role on every protected page |
| **Least Privilege** | DB user has SELECT/INSERT/UPDATE/DELETE only — no DDL access |
| **Session Security** | `session_regenerate_id(true)` on login; 30-minute inactivity timeout |
| **HTTPS** | SSL certificate required on production hosting |

---

## Known Limitations

> These are intentional decisions for the capstone scope — not oversights.

- **Stripe is test mode only** — no real payments are processed
- **No email notifications** — order confirmations use basic `mail()` only
- **No product reviews** — out of scope for v1.0
- **No inventory alerts** — stock quantity is tracked but no threshold notifications
- **No customer wishlist** — deferred to Phase 2
- **No native mobile app** — responsive web only

---

## Future Enhancements

Phase 2 opportunities after capstone completion:

- [ ] Real Stripe production payments with live webhook handling
- [ ] Automated transactional email (order confirmations, shipping updates)
- [ ] Product review and rating system
- [ ] Inventory low-stock alerts for vendors
- [ ] Google Analytics integration
- [ ] Customer wishlist / saved items
- [ ] Advanced admin analytics with Chart.js dashboards
- [ ] Newsletter signup and email marketing integration
- [ ] Progressive Web App (PWA) for mobile home screen installation

---

## Documentation

All project documentation is in the `/docs/` folder:

| Document | Description |
|---|---|
| [`project_charter.pdf`](docs/project_charter.pdf) | Full project charter with objectives, scope, timeline, and sign-off |
| [`database_design.pdf`](docs/database_design.pdf) | 12-table schema with field definitions, indexes, and SQL statements |
| [`er_diagram.png`](docs/er_diagram.png) | Entity Relationship Diagram with crow's foot notation |
| [`wireframes.pdf`](docs/wireframes.pdf) | 22-page wireframe set covering all public, vendor, and admin pages |

---

## License

This project was developed as a **CIS2987 Web Development Capstone** academic project. The codebase is shared for educational and portfolio purposes.

**Virginia Market Square** is a real non-profit organization in Virginia, Minnesota. This project was built to serve their actual operational needs.

---

<div align="center">

Built with 🌱 for **Virginia Market Square** · Virginia, Minnesota

*Supporting local farmers, makers, and producers within 50 miles*

**Thursdays · June–October · 2:30–6:00 PM · 111 South 9th Avenue W, Virginia MN 55792**

</div>
