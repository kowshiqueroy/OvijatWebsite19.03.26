# PROJECT_TRACKER: sobjiwali.com

## Phase Roadmap
- [x] **Phase 0: Initial Presence** - Create high-performance "Coming Soon" landing page.
- [x] **Phase 1: Foundation & Security** - Directory structure, `.htaccess` routing, Database connection (PDO), and Core Utility classes.
- [x] **Phase 2: Database Architecture** - Schema design for Users, Products (Variations/FIFO), Orders, and Wallets.
- [x] **Phase 3: Product Engine** - CRUD for products, WebP optimization, and SKU management.
- [x] **Phase 4: Authentication System** - Retail/Wholesale registration, Admin stealth login, and Pending Approval workflow.
- [x] **Phase 5: Cart & Checkout Logic** - LocalStorage-to-Backend sync, Shipping/Tax engine, and Stripe Auth/Capture integration.
- [x] **Phase 6: Admin Dashboard & Warehouse** - Order lifecycle tracking, QR Invoices, and Thermal Packing slips.
- [x] **Phase 7: Advanced Features** - Internal Wallet, VIP Loyalty, and Subscribe & Save.
- [x] **Phase 8: Frontend Implementation** - Home, Catalog, Cart, Checkout, and User Accounts.
- [x] **Phase 9: Admin Product Management** - Secure CRUD for Categories and Products.
- [x] **Phase 10: Production Readiness** - Image optimization, dynamic details, and security hardening.
- [x] **Phase 11: Granular RBAC** - Expanded roles (Editor, Warehouse, Reports) and multi-role AuthManager.
- [x] **Phase 12: Role-Specific UI** - Dynamic sidebar and role-based data masking on dashboard.
- [x] **Phase 13: Enhanced Analytics** - Chart.js integration and 7-day sales trend visualization.
- [x] **Phase 14: Bulk Operations** - Multi-product status and category management.
- [x] **Phase 15: B2B & Logistics Overhaul** - Wholesale pricing, min order qty, and weight-based shipping rules.
- [x] **Phase 16: Customer UX Hardening** - Role-aware pricing on catalog and detailed product specifications.
- [x] **Phase 17: B2B Communication Hub** - Floating chat popup, automated notifications, and dedicated Support role.
- [x] **Phase 18: Professional Service Desk** - Customer 360 insights, staff internal notes, and quick-action chat tools.
- [x] **Bug Fixes & Maintenance** - Notification count persistence, PRG pattern for messaging, and login activity tracking.

## Completed Tasks
- [x] **2026-05-22:** Perfected B2B Communication & Service Desk:
    - Fixed **Notification Count** persistence issue by syncing read states across tables.
    - Launched **Customer 360 Sidebar** in Admin Hub (Orders, Total Value, Last Login).
    - Implemented **Staff Internal Notes** and **Priority Levels** for thread management.
    - Enhanced **Customer Chat UI** with Quick Actions (FAQ, Order Inquiry, Delivery Help).
    - Built **Order Picker** within chat for precise inquiry referencing.
    - Applied **PRG Pattern** to prevent duplicate message submission on refresh.
    - Integrated **Login Activity Tracking** for engagement analytics.
- [x] **2026-05-22:** Implemented Advanced B2B Communication & Notifications:
    - Built a **Notification Engine** for real-time order status and message alerts.
    - Launched a **Floating Support Chat** for customers with automated polling.
    - Created the **Admin Support Hub** for multi-role message management.
    - Introduced the **'Support' Role** for dedicated customer inquiry handling.
    - Integrated notification badges in both Storefront and Admin headers.
- [x] **2026-05-22:** Implemented Advanced B2B Logistics & Frontend Synchronization:
    - Updated `CartManager` and `CheckoutManager` to handle **Wholesale Prices** and **Minimum Order Quantities**.
    - Built a new **Tax & Shipping Manager** for state-based taxes and weight-based logistics.
    - Refactored `catalog.php` and `product_detail.php` to dynamically show user-specific pricing.
    - Upgraded **Inventory Editor** with variation editing, image deletion, and quick stock updates.
    - Fixed **Preloader** behavior to prevent site hang during DB maintenance.
- [x] **2026-05-22:** Implemented Advanced RBAC & UI Modernization (Phases 11-14):
    - Refactored `AuthManager.php` to support array-based role validation.
    - Expanded `users` role enum to include `editor`, `warehouse`, and `reports`.
    - Built dynamic Sidebar in `layout_header.php` with permission-based link filtering.
    - Integrated **Chart.js** into `dashboard.php` for real-time sales trend visualization.
    - Implemented **Bulk Actions** in `products.php` for mass status/category updates.
    - Restricted sensitive KPI visibility on the dashboard based on user roles.
    - Updated `migrate_db.php` to synchronize the new role schema.
- [x] **2026-05-21:** Finalized Bug Fixes & Multi-Address System:
    - Created `migrate_db.php` to resolve "Unknown Column" and table mismatch errors.
    - Fixed `checkout.php` logic bugs (undefined variables, saved address processing).
    - Unified `account.php` address management with proper SQL field mapping.
    - Verified entire Guest/Member checkout flow with dynamic form validation.
- [x] **2026-05-21:** Created `default.php` with modern CSS animations and SVG graphics for "Coming Soon" status.
- [x] **2026-05-21:** Initialized `PROJECT_TRACKER.md` for multi-session state continuity.
- [x] **2026-05-21:** Established Phase 1 Foundation:
    - Created directory structure (`includes`, `config`, `assets`, `admin_stealth_zone`).
    - Implemented `Database.php` PDO singleton wrapper.
    - Configured `config.php` for environment variables.
    - Set up `.htaccess` and `index.php` for clean URL routing.
- [x] **2026-05-21:** Completed Phase 2 Database Architecture:
    - Designed comprehensive schema for Users, Wallets, Products, Inventory (FIFO), and Orders.
    - Implemented Categories and Product Images support.
    - Created `database/schema.sql` with optimized indexes and foreign keys.
- [x] **2026-05-21:** Completed Phase 3 Product Engine:
    - Implemented `ProductManager.php` for robust Product/Variation/Inventory CRUD.
    - Developed `ImageOptimizer.php` for automatic WebP conversion using GD.
    - Added SKU generation logic with category prefixes and modifiers.
    - Verified functionality with `test_product_engine.php`.
- [x] **2026-05-21:** Completed Phase 4 Authentication System:
    - Implemented `AuthManager.php` with session security and CSRF protection.
    - Developed Retail and Wholesale registration (with pending approval state).
    - Created `gatekeeper.php` for stealth Admin login.
    - Verified multi-role authentication with `test_auth_system.php`.
- [x] **2026-05-21:** Completed Phase 5 Cart & Checkout Logic:
    - Implemented `CartManager.php` for server-side cart validation and synchronization.
    - Developed `StripeClient.php` (zero-dependency cURL wrapper) for Auth-Only payments.
    - Built `CheckoutManager.php` to handle order creation and payment orchestration.
    - Verified the full checkout flow with `test_checkout.php`.
- [x] **2026-05-21:** Completed Phase 6 Admin Dashboard & Warehouse:
    - Implemented `WarehouseManager.php` with robust FIFO inventory deduction logic.
    - Developed a secure `dashboard.php` for order management and lifecycle tracking.
    - Created printable `invoice.php` (A4) and `packing_slip.php` (80mm Thermal).
    - Integrated QR code generation via public API for invoice verification.
    - Verified FIFO stock deduction and order state transitions with `test_warehouse.php`.
- [x] **2026-05-21:** Completed Phase 7 Advanced Features:
    - Implemented `WalletManager.php` for internal balances and transaction ledgers.
    - Integrated VIP Loyalty system (1% cashback on shipped orders).
    - Developed `SubscriptionManager.php` for 'Subscribe & Save' recurring orders.
    - Verified all advanced features with `test_advanced.php`.
- [x] **2026-05-21:** Initialized Phase 8 Frontend Infrastructure:
    - Updated `index.php` router to handle basic page requests.
    - Created `templates/header.php` and `templates/footer.php` for UI consistency.
    - Implemented `login.php`, `register.php`, and `logout.php`.
    - Developed `catalog.php` with "Add to Cart" functionality.
    - Built `cart.php` with LocalStorage-to-Server synchronization.
    - Created `account.php` (User Dashboard) with wallet and order overview.
    - Refactored `AuthManager.php` and `ProductManager.php` to support frontend features.
- [x] **2026-05-21:** Completed Phase 9 Admin Product Management:
    - Created `admin_stealth_zone/products.php` for catalog overview.
    - Built `admin_stealth_zone/categories.php` with inline edit support.
    - Implemented `admin_stealth_zone/product_edit.php` for rich product management.
    - Integrated category selection and status toggling (Active/Hidden).
- [x] **2026-05-21:** Completed Phase 9.1 Variation & Inventory Management:
    - Integrated variation management (Add/Remove) directly into the Product Editor.
    - Implemented modal-based "Stock Receipt" flow for adding FIFO inventory batches.
    - Added cost-price tracking for each stock entry.
    - Enabled automatic SKU generation for new variations.
- [x] **2026-05-21:** Completed Phase 8.5 Multi-Gateway Checkout:
    - Built `checkout.php` supporting Stripe (Auth-Only) and Manual Bank Transfer.
    - Updated `CheckoutManager.php` to handle multi-method order creation.
    - Enhanced Admin Dashboard to display payment methods and transaction references.
    - Added configurable Bank Details to `config.php`.
- [x] **2026-05-21:** Finalized Production Readiness (Phase 10):
    - Implemented **WebP Image Uploader** in Admin with automatic optimization.
    - Launched professional **Home Page** with farm-to-table storytelling.
    - Built **Product Details** view with gallery and variation selection.
    - Created **Wholesale Management** UI for partner approvals.
    - Hardened entire system with **CSRF Protection** on all forms.

## File Directory Tree
```text
/
├── .htaccess
├── default.php
├── index.php
├── PROJECT_TRACKER.md
├── admin_stealth_zone/
│   ├── dashboard.php
│   ├── gatekeeper.php
│   ├── invoice.php
│   ├── packing_slip.php
│   ├── test_advanced.php
│   ├── test_auth_system.php
│   ├── test_checkout.php
│   ├── test_product_engine.php
│   └── test_warehouse.php
├── assets/
│   ├── css/
│   ├── img/
│   └── js/
├── config/
│   └── config.php
├── database/
│   └── schema.sql
├── includes/
    ├── AuthManager.php
    ├── CartManager.php
    ├── CheckoutManager.php
    ├── Database.php
    ├── ImageOptimizer.php
    ├── ProductManager.php
    ├── StripeClient.php
    ├── SubscriptionManager.php
    ├── WalletManager.php
    └── WarehouseManager.php
```

## Decision & Modification Ledger
1. **[2026-05-21] Project Start:** Confirmed zero-dependency stack (Vanilla JS, Native CSS, Core PHP 8+, MySQL PDO).
2. **[2026-05-21] Coming Soon:** Implemented a single-file `default.php` to serve as a temporary index while the core platform is built.
3. **[2026-05-21] Architecture:** Adopted "Authorized-only" Stripe capture logic to allow Admin manual inventory verification before funds are taken.
4. **[2026-05-21] Security:** Implemented a Singleton `Database` class with strict PDO prepared statements to prevent SQL injection.
5. **[2026-05-21] Stealth Admin:** Created `admin_stealth_zone` folder to house administrative logic behind a custom URL path.
6. **[2026-05-21] Database Design:** Opted for a robust FIFO inventory batch system to track cost prices and stock age accurately. Included separate `user_profiles` and `wallets` for better data separation.
7. **[2026-05-21] Zero-Dependency Stripe:** Implemented a lightweight cURL-based Stripe client to maintain the "no-composer" architecture while supporting advanced Auth-Only flows.
