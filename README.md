# Nordic Inventory

Simple inventory and order management app built with Laravel, Sanctum, Vue 3, Inertia, and Vite.

## Features

- User register and login with Sanctum token auth
- Product create, update, delete, and search
- Order create, update, delete with stock-safe transaction logic
- MMK price display format in UI
- OpenAPI spec available at `docs/openapi.yaml`

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+ and npm
- MySQL 8+

## Setup

1. Install dependencies:

```bash
composer install
npm install
```

2. Create environment file:

```bash
copy .env.example .env
```

3. Generate app key:

```bash
php artisan key:generate
```

4. Configure database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nordic_inventory
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

5. Run migrations:

```bash
php artisan migrate
```

## Run the Project

Use separate terminals:

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Open: `http://127.0.0.1:8000`

## Build for Production

```bash
npm run build
```

## Run Tests

```bash
php artisan test
```

## API Authentication Flow

1. Register: `POST /api/register`
2. Login: `POST /api/login`
3. Copy `token` from login response
4. Send header for protected endpoints:

```text
Authorization: Bearer <token>
```

## API Documentation

- OpenAPI file: `docs/openapi.yaml`

