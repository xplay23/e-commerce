# NORD E-commerce

Vue 3 storefront with a PHP 8.1 REST API and MySQL 8 database.

## Database

```bash
mysql -u root -p < backend/database/schema.sql
mysql -u root -p < backend/database/seed.sql
```

The seed creates eight products, five categories and a local administrator:

- Email: `admin@nord.test`
- Password: `password`

Change the administrator password before deploying the project.

## PHP API

Copy `backend/.env.example` to `backend/.env`, configure MySQL, then run:

```bash
php -S localhost:8080 -t backend/public backend/router.php
```

PHP 8.1+ with the `pdo_mysql` extension is required.

## Frontend

Copy `.env.example` to `.env.local`, then run:

```bash
npm install
npm run dev
```

The frontend expects the API at `http://localhost:8080/api` by default.

## Verification

```bash
npm run typecheck
npm run build
npm run format:check
php -l backend/public/index.php
php -l backend/src/bootstrap.php
```
