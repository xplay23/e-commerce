# NORD PHP API

Requirements: PHP 8.1+ with `pdo_mysql`, MySQL 8+.

```bash
cp backend/.env.example backend/.env
mysql -u root -p < backend/database/schema.sql
mysql -u root -p < backend/database/seed.sql
php -S localhost:8080 -t backend/public backend/router.php
```

The development administrator is `admin@nord.test` with password `password`.
Change this password before exposing the application to a network.

Frontend configuration:

```env
VITE_API_URL=http://localhost:8080/api
```
