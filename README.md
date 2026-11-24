# Product Inventory API

A small REST API for managing a product inventory, built with **Laravel 11** and **Sanctum** token auth.

- Products belong to a **category** and can have many **suppliers**
- Full CRUD for products, with filtering and pagination
- Soft deletes on products
- Form Requests for validation, API Resources for responses
- Feature tests (PHPUnit), seeders, Docker setup, Swagger docs, caching and rate limiting

## Requirements

- PHP 8.2+ (with `pdo_mysql`, `mbstring`, `zip`)
- Composer 2
- MySQL 5.7+ / 8

## A note on Laravel 11 and security advisories

The brief asks for Laravel 11.x. That release line no longer gets security fixes, so Composer flags every 11.x version and refuses to install it. `composer.json` lists those advisory IDs under `config.policy.advisories.ignore-id` so `composer install` works. That is fine for this exercise, but for a real deployment upgrade to a supported Laravel version and remove that block.

## Setup (local, MySQL)

First create an empty database (phpMyAdmin, HeidiSQL or the mysql CLI):

```sql
CREATE DATABASE product_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
git clone <this-repo-url>
cd product-inventory-api

composer install
cp .env.example .env        # on Windows: copy .env.example .env
php artisan key:generate
```

Open `.env` and check the database settings match your MySQL. The defaults are:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=product_inventory
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate --seed
php artisan serve
```

The API is now at `http://localhost:8000/api`.

The seeder creates a test user you can log in with:

| email              | password      |
|--------------------|---------------|
| test@example.com   | password   |

It also adds 5 categories, 4 suppliers and 30 random products.

## Setup (Docker)

```bash
docker compose up --build
```

This starts the app on `http://localhost:8000` plus a MySQL 8 container. Migrations and seeders run automatically on start (the seeders are safe to run more than once).

## API docs

Swagger UI is at **http://localhost:8000/docs**. The spec itself is `public/openapi.yaml`.

## Endpoints

All routes are under `/api`. Send `Accept: application/json` and, for protected routes, `Authorization: Bearer <token>`.

| Method    | URL                | Auth | What it does                      |
|-----------|--------------------|------|-----------------------------------|
| POST      | `/register`        | no   | Create an account, returns token  |
| POST      | `/login`           | no   | Log in, returns token             |
| POST      | `/logout`          | yes  | Revoke the current token          |
| GET       | `/products`        | yes  | List products (filter + paginate) |
| POST      | `/products`        | yes  | Create a product                  |
| GET       | `/products/{id}`   | yes  | Show a product                    |
| PUT/PATCH | `/products/{id}`   | yes  | Update a product                  |
| DELETE    | `/products/{id}`   | yes  | Soft delete a product             |
| GET       | `/categories`      | yes  | List categories (cached)          |
| GET       | `/suppliers`       | yes  | List suppliers (cached)           |

### Quick example

```bash
# log in
curl -X POST http://localhost:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# use the token it gives back
curl http://localhost:8000/api/products \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Creating a product

```json
POST /api/products
{
  "category_id": 1,
  "name": "USB-C Cable",
  "sku": "usb-100",
  "description": "2m braided cable",
  "price": 12.5,
  "stock": 25,
  "supplier_ids": [1, 2]
}
```

The SKU is saved upper case (`USB-100`) and has to be unique. `supplier_ids` is optional; on update it replaces the product's suppliers, and if you leave it out the suppliers are left alone.

### Filtering and pagination

`GET /api/products` accepts these query parameters (all optional, they can be combined):

| Parameter     | Example           | Meaning                                       |
|---------------|-------------------|-----------------------------------------------|
| `category_id` | `category_id=2`   | Only products in that category                |
| `min_price`   | `min_price=10`    | Price is at least this                        |
| `max_price`   | `max_price=100`   | Price is at most this                         |
| `min_stock`   | `min_stock=50`    | Stock is at least this                        |
| `max_stock`   | `max_stock=5`     | Stock is at most this                         |
| `in_stock`    | `in_stock=1`      | Only products with stock above 0              |
| `low_stock`   | `low_stock=1`     | Only products with 10 or fewer left           |
| `search`      | `search=cable`    | Matches product name or SKU                   |
| `per_page`    | `per_page=20`     | Page size, 1-100 (default 15)                 |
| `page`        | `page=2`          | Which page                                    |

```
GET /api/products?category_id=2&min_price=10&max_price=100&low_stock=1&per_page=10
```

The response looks like:

```json
{
  "data": [ { "id": 1, "name": "...", "sku": "...", "price": 12.5, "stock": 3, "is_low_stock": true, "category": {}, "suppliers": [] } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 30 }
}
```

### Errors

- `401` missing or bad token
- `404` product not found (soft deleted products also give 404)
- `422` validation failed, the body has an `errors` object
- `429` too many requests

## Tests

```bash
php artisan test
```

The tests use an in-memory SQLite database (set in `phpunit.xml`), so they never touch your MySQL data and nothing needs to be set up. They cover registration/login/logout, protected routes, product CRUD, soft deletes, validation, every filter and the rate limit.

## How it's put together

```
app/
  Http/
    Controllers/Api/   AuthController, ProductController, CategoryController, SupplierController
    Requests/          Form Requests (validation)
    Resources/         API Resources (response format)
  Models/              Product, Category, Supplier, User
database/
  migrations/
  factories/
  seeders/
routes/api.php
tests/Feature/
```

A few notes on decisions:

- **Eloquent extras on `Product`:** scopes `inStock()` and `lowStock()`; an accessor for `formatted_price` and `is_low_stock`; a mutator that upper-cases the `sku`.
- **Soft deletes:** deleted products are hidden from the API but stay in the table. Their SKU stays reserved, so it can't be reused by a new product.
- **Low stock** means 10 or fewer. It's the `Product::LOW_STOCK_LEVEL` constant if you want to change it.
- **Rate limiting:** 10 requests/minute per user for the API, and 3/minute per IP for `/login` and `/register`. Limiters are defined in `AppServiceProvider`.
- **Caching:** the category and supplier lists are cached for an hour. The models clear the cache automatically when a row is saved or deleted. Run `php artisan cache:clear` if you ever edit the tables by hand.
- **JSON everywhere:** `bootstrap/app.php` makes every `/api` error come back as JSON, even if the client forgets the `Accept` header.
