# Store Order & Inventory Mini-System

A Laravel 12 mini-system for managing customers, products, order creation, stock deduction, customer order history, low-stock reporting, and queued order confirmations.

This project was developed as a Laravel Developer take-home assignment.

## Features

- Product catalogue with:
  - Name
  - Unique product code
  - Unit price
  - Tax percentage
  - Stock quantity
- Customer records with unique email addresses
- Multi-product order creation
- Server-side subtotal, tax, and grand-total calculation
- Transactional stock deduction
- Concurrency-safe inventory updates using database row locking
- Historical unit-price and tax snapshots on order items
- Customer order-history lookup by email
- Configurable low-stock reporting
- Queued order-confirmation simulation
- Blade + Alpine.js frontend
- Automated feature tests
- Factories and seeders for sample data

## Technology Stack

### Backend

- PHP 8.2+
- Laravel 12
- Eloquent ORM
- MySQL / InnoDB
- Laravel database queues
- PHPUnit

### Frontend

- Laravel Blade
- Alpine.js
- Native Fetch API
- Vite
- Dedicated CSS and JavaScript assets

Alpine.js is installed locally through npm. No CDN is required.

## Architecture

The application intentionally uses a small, focused architecture suitable for the scope of the assignment:

```text
HTTP Request
    |
    v
Controller
    |
    v
Form Request
    |
    v
OrderService
    |
    v
Eloquent / MySQL
```

Controllers remain thin, request validation is handled by Laravel Form Requests, and transactional order business logic is located in `OrderService`.

A repository layer was intentionally not introduced because the current data-access requirements are straightforward and Eloquent already provides the required abstraction. Adding repositories for this scope would add complexity without providing a meaningful benefit.

## Database Design

The core application uses four domain tables:

```text
customers
    |
    | 1:N
    v
orders
    |
    | 1:N
    v
order_items
    |
    | N:1
    v
products
```

### Customers

Stores:

- name
- unique email

### Products

Stores:

- name
- unique code
- unit price
- tax percentage
- stock quantity

### Orders

Stores:

- customer reference
- subtotal
- tax amount
- grand total

### Order Items

Stores:

- order reference
- product reference
- quantity
- unit price snapshot
- tax percentage snapshot
- tax amount
- line total

Unit price and tax percentage are copied to the order item when an order is created. This preserves the historical financial values of an order even if the product price or tax percentage changes later.

## Installation

### 1. Clone the repository

```bash
git clone <repository-url>
cd mallow-order-system
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Create the environment file

Windows:

```powershell
Copy-Item .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

### 5. Generate the application key

```bash
php artisan key:generate
```

## Database Setup

The application is designed to use MySQL/InnoDB.

Create a database:

```sql
CREATE DATABASE mallow_order_system;
```

Configure `.env` if your MySQL credentials differ from the defaults:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mallow_order_system
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations and seed sample data:

```bash
php artisan migrate --seed
```

The seeder creates sample customers and products for development/demo purposes.

## Running the Application

Start Laravel:

```bash
php artisan serve
```

For frontend development, run Vite in another terminal:

```bash
npm run dev
```

The application will normally be available at:

```text
http://127.0.0.1:8000
```

For a production frontend build:

```bash
npm run build
```

## Queue Worker

Order creation dispatches a queued confirmation job after the database transaction completes.

The default project configuration uses the database queue:

```dotenv
QUEUE_CONNECTION=database
```

Run the worker in a separate terminal:

```bash
php artisan queue:work
```

The confirmation job simulates sending an order-confirmation email by writing order/customer information to the application log.

The log can be inspected at:

```text
storage/logs/laravel.log
```

## API Endpoints

### Product Catalogue

```http
GET /api/products
```

Returns products ordered by name, including current stock information.

Zero-stock products are intentionally returned so clients can display them while preventing selection where appropriate.

### Create Order

```http
POST /api/orders
```

Example request:

```json
{
    "customer": {
        "name": "John Doe",
        "email": "john@example.com"
    },
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        },
        {
            "product_id": 2,
            "quantity": 1
        }
    ]
}
```

The server:

1. validates the request;
2. resolves or creates the customer;
3. locks the requested product rows;
4. verifies available stock;
5. calculates subtotal, tax, and grand total;
6. creates the order and order items;
7. deducts inventory;
8. commits the transaction;
9. dispatches the confirmation job.

Prices and tax percentages are always read from the server-side product records. The API does not trust client-supplied monetary values.

Duplicate product IDs within the same order request are rejected.

### Customer Order History

```http
GET /api/customers/orders?email=john@example.com
```

Returns the customer's orders newest first, including order items.

An existing customer with no orders receives an empty order list.

An unknown customer email returns a not-found response.

### Low-Stock Products

```http
GET /api/products/low-stock
```

The threshold is configurable:

```dotenv
LOW_STOCK_THRESHOLD=10
```

A product is considered low stock when:

```text
stock_quantity < LOW_STOCK_THRESHOLD
```

Products exactly equal to the threshold are therefore not included.

## Order Calculation

For each order line:

```text
line subtotal = unit price × quantity

line tax = line subtotal × tax percentage / 100

line total = line subtotal + line tax
```

Order totals are calculated by aggregating the line values.

Monetary values are rounded to two decimal places during calculation and stored in DECIMAL database columns.

For the scope of this assignment, this provides predictable currency values without introducing additional arbitrary-precision monetary libraries.

## Transaction and Rollback Strategy

Order creation runs inside a Laravel database transaction.

The transaction includes:

- customer creation when necessary
- product row locking
- stock validation
- order creation
- order-item creation
- stock deduction

If any requested product has insufficient stock, the transaction fails and all changes are rolled back.

This includes a customer that may have been created as part of the failed order attempt.

The confirmation job is dispatched only after the order transaction has successfully completed.

## Concurrency Safety

Preventing overselling is a core requirement of the project.

During order creation, requested product IDs are sorted into a deterministic order and retrieved using:

```php
lockForUpdate()
```

The stock check occurs after those rows have been locked.

Conceptually:

```text
Begin transaction
      |
      v
Sort product IDs
      |
      v
Lock product rows
      |
      v
Check current stock
      |
      +---- insufficient ----> rollback / validation failure
      |
      v
Create order and items
      |
      v
Deduct stock
      |
      v
Commit
```

Sorting the IDs provides consistent lock ordering when orders contain multiple products, reducing deadlock risk.

### Manual Concurrency Verification

Concurrency behavior was manually verified using MySQL with two independent PHP processes/database connections competing for a product with:

```text
stock_quantity = 1
```

Observed result:

```text
Request A -> SUCCESS
Request B -> FAILED: insufficient stock

Final product stock -> 0
Persisted quantity sold -> 1
Order items created -> 1
```

The losing request did not oversell the product, and its newly created customer was rolled back.

This manual integration check complements the automated test suite. It is intentionally described as manual verification rather than an automated parallel PHPUnit test.

The concurrency guarantee assumes a transactional database with appropriate row-locking semantics, such as MySQL/InnoDB.

## Automated Tests

The project includes feature coverage for:

- successful multi-product order creation
- monetary totals
- order-item price/tax snapshots
- inventory deduction
- insufficient-stock rollback
- rollback of a newly created customer
- queue dispatch behavior
- quantity validation
- duplicate product rejection
- customer input-length validation
- customer order history
- unknown customer handling
- configurable low-stock reporting
- product catalogue behavior

Current verified test result:

```text
14 tests passed
97 assertions
```

Run:

```bash
php artisan test
```

## Testing Database Safety

Tests use Laravel's `RefreshDatabase` behavior.

A separate MySQL testing database should be used so tests never migrate or reset the development database.

Create it:

```sql
CREATE DATABASE mallow_order_system_test;
```

Create a local `.env.testing` file:

Windows:

```powershell
Copy-Item .env .env.testing
```

Linux/macOS:

```bash
cp .env .env.testing
```

Change the database name in `.env.testing`:

```dotenv
APP_ENV=testing

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mallow_order_system_test
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
```

`.env.testing` is intentionally excluded from Git because it is machine-specific environment configuration.

Verify the testing environment before running tests if required:

```bash
php artisan env --env=testing
```

Then run:

```bash
php artisan test
```

## Frontend

The root page provides a lightweight dashboard for:

- creating an order
- adding/removing product lines
- viewing current product stock
- displaying server-calculated order totals
- searching customer order history
- viewing products below the configured stock threshold

The frontend intentionally does not calculate authoritative prices, taxes, or order totals.

Those values are calculated by Laravel so manipulated browser values cannot override product pricing or tax rules.

Zero-stock products remain visible but cannot be selected for order creation.

## Validation and Error Handling

The application validates:

- customer name
- customer email
- maximum customer field lengths
- at least one order item
- valid product IDs
- duplicate product IDs
- positive integer quantities
- available stock

Stock availability is checked again inside the database transaction after acquiring product locks.

This means request validation alone is not relied upon for inventory integrity.

## Design Decisions and Assumptions

### MySQL/InnoDB

MySQL/InnoDB is used because the assignment requires concurrency-safe stock deduction and the implementation relies on transactional row locking.

### Thin Controllers

Controllers delegate validation and business logic rather than containing the order workflow directly.

### Service Layer

`OrderService` contains the transactional order-creation workflow because this is the primary business operation requiring coordination across multiple models.

### No Repository Layer

A repository layer was intentionally omitted. Eloquent queries are currently straightforward, and an additional repository abstraction would add ceremony without improving the solution for this scope.

### Historical Order Data

Unit price and tax percentage are stored on order items so historical orders are not altered by future product changes.

### Low-Stock Definition

"Below threshold" is interpreted strictly as:

```text
stock < threshold
```

### Simulated Email

The assignment permits a simulated confirmation. The queued job therefore logs confirmation information instead of integrating an external mail provider.

## AI-Assisted Development

AI tools were used as development assistants during this assignment for activities such as:

- discussing architecture and implementation options
- reviewing Laravel patterns
- generating focused implementation suggestions
- reviewing tests and edge cases
- performing a final code-review pass

AI-generated suggestions were reviewed before being applied. Architecture decisions, implementation changes, testing, debugging, and final verification remained developer-controlled.

Representative prompt evidence is included in the repository's `prompts/` directory as required by the assignment.

## Final Verification

Before submission, the project was verified with:

```bash
npm run build
php artisan test
```

Verified result:

```text
Vite production build: PASS
Laravel tests: 14 passed
Assertions: 97
```

The concurrency scenario was separately verified against MySQL using independent processes/connections.

## Project Scope

This repository is intentionally focused on the requirements of the take-home assignment.

Features such as authentication, payment processing, administration, Docker deployment, caching, event-driven architecture, repository abstractions, and external email delivery were intentionally not added because they are outside the requested scope.