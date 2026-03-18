# 🛒 POS System — Developer Documentation

## Folder Structure

```
pos/
│
├── index.php                  # Front controller / router
├── login.php                  # Standalone login page
├── setup.php                  # DB installer & seeder (run once)
├── config.php                 # All app configuration constants
│
├── includes/
│   ├── bootstrap.php          # Loaded first: requires config, db, helpers, auth
│   ├── db.php                 # PDO singleton + shorthand query helpers
│   ├── auth.php               # Session, login, logout, role guards
│   ├── helpers.php            # Logger, formatters, flash, pagination, QR
│   ├── header.php             # Common HTML header + side nav (all pages)
│   └── footer.php            # Common HTML footer + JS includes
│
├── assets/
│   ├── css/
│   │   └── app.css            # Mobile-first CSS (dark theme, variables)
│   └── js/
│       └── app.js             # Cart, modals, nav, AJAX helpers
│
├── modules/
│   ├── dashboard/
│   │   └── dashboard.php      # KPIs, recent sales, low-stock alerts
│   ├── pos/
│   │   └── pos.php            # POS grid, cart, checkout, AJAX endpoints
│   ├── products/
│   │   └── products.php       # Product CRUD + variant management
│   ├── categories/
│   │   └── categories.php     # Category CRUD
│   ├── customers/
│   │   └── customers.php      # Customer CRUD + loyalty points
│   ├── sales/
│   │   └── sales.php          # Sales list, filter, cancel
│   ├── invoices/
│   │   └── invoice.php        # Invoice viewer + A4 landscape dual-copy print
│   ├── finance/
│   │   └── finance.php        # Income/expense ledger, balance
│   ├── reports/
│   │   └── reports.php        # Custom date-range reports (sales/expenses/products)
│   ├── users/
│   │   └── users.php          # User management (admin only)
│   └── logs/
│       └── logs.php           # Action log viewer (admin only)
│
└── uploads/
    └── barcodes/              # (Reserved for barcode image exports)
```

---

## Quick Start

1. **Create MySQL database:** `CREATE DATABASE pos_db CHARACTER SET utf8mb4;`
2. **Edit `config.php`:** Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`.
3. **Run setup:** Visit `http://localhost/pos/setup.php` → click **Run Setup**.
4. **Login:** `admin` / `admin123` → change password immediately.
5. **Start selling:** Navigate to **Point of Sale**.

---

## User Roles

| Permission        | Admin | SR (Sales Rep) |
|-------------------|:-----:|:--------------:|
| View all pages    | ✅    | ✅             |
| Add / Edit        | ✅    | ✅             |
| Delete records    | ✅    | ❌             |
| Manage Users      | ✅    | ❌             |
| View Action Logs  | ✅    | ❌             |

---

## Key Design Decisions

### Routing
All pages go through `index.php?page=<name>`. Add new modules by:
1. Creating `modules/<name>/<name>.php`
2. Adding the route in the `$routes` array in `index.php`

### Database Helpers (includes/db.php)
```php
dbFetch($sql, $params)        // → single row or null
dbFetchAll($sql, $params)     // → array of rows
dbInsert($table, $data)       // → new ID
dbUpdate($table, $data, $where, $whereParams) // → affected rows
dbDelete($table, $where, $params)             // → affected rows
```

### Logging
Every create/update/delete calls:
```php
logAction('ACTION', 'module', $recordId, 'Human-readable note');
```
Logs are stored in `action_logs` and viewable at Admin → Action Logs.

### POS Cart
Cart state lives in `sessionStorage` on the client. On checkout, the full
JSON is posted to the server in `hiddenCartJson`. Stock is deducted and a
finance entry is auto-created for completed cash sales.

### Invoice Printing
The invoice page renders a hidden `.invoice-page` div containing two
`.invoice-copy` columns (Customer Copy + Showroom Copy) in A4 landscape
format. `window.print()` reveals them via CSS `@media print`. QR codes are
fetched from the free `api.qrserver.com` API.

### Extending
- **New report:** Add a query in `modules/reports/reports.php` under a new `$type` branch.
- **New payment method:** Add to the `ENUM` in `setup.php` and the `<select>` in `pos.php`.
- **Email receipts:** Add a `sendMail()` helper in `includes/helpers.php` using PHP's `mail()`.
- **Barcode scanning:** Wire a USB/Bluetooth scanner to the barcode input on the POS page—it types like a keyboard and submits on Enter.

---

## Security Checklist (before going live)

- [ ] Change `admin123` password
- [ ] Set `display_errors = 0` in `config.php`
- [ ] Add `.htaccess` to block direct access to `includes/` and `modules/`
- [ ] Use HTTPS
- [ ] Set strong `DB_PASS`
- [ ] Remove or password-protect `setup.php`
