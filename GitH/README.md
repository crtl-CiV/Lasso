# LASSO — Learning Asset Subscription and Sales Operation
Deployable PHP + MySQL system for XAMPP.

Renaming applied per your request: **Browse Materials → Store**, **My Bag → Cart**, **My Learning → Library**.

## What's inside
```
lasso/
├── database/
│   └── lasso_db.sql              ← import this first (phpMyAdmin)
└── htdocs/
    └── lasso/                    ← copy this whole folder into your XAMPP htdocs
        ├── index.html            ← student login
        ├── register.html         ← student registration (with ID photo upload)
        ├── admin-login.html      ← administrator login
        ├── setup_admin.php       ← run once to create the first admin account
        ├── student/
        │   ├── store.html        ← Store (was "Browse Materials")
        │   ├── cart.html         ← Cart (was "My Bag") + checkout/billing
        │   ├── library.html      ← Library (was "My Learning") + reader/progress
        │   └── profile.html      ← profile, validation code, change password
        ├── admin/
        │   ├── dashboard.html
        │   ├── materials.html    ← upload/edit/publish/promote/trash/restore
        │   ├── users.html        ← manage student & admin accounts
        │   └── sales.html        ← cashier payment confirmation + sales reports
        ├── assets/                css/js shared by all pages
        └── api/                  all PHP backend endpoints (see below)
```

## 1. Install prerequisites
- XAMPP with PHP 8.0+ and MySQL/MariaDB (Apache + MySQL modules running).

## 2. Import the database
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Click **Import**, choose `database/lasso_db.sql`, click **Go**.
   This creates the `lasso_db` database, all tables, and seed reference data
   (sample departments/programs — edit `college_departments` /
   `college_programs` to match your actual school).

## 3. Deploy the files
1. Copy the entire `htdocs/lasso` folder into your XAMPP `htdocs` directory, so you end up with:
   `C:\xampp\htdocs\lasso\...` (Windows) or `/opt/lampp/htdocs/lasso/...` (Linux).
2. Make the upload folders writable by the web server:
   `htdocs/lasso/api/uploads/materials/`, `.../uploads/ids/`, `.../uploads/covers/`
   (on Windows this is usually already fine; on Linux/Mac run
   `chmod -R 775 api/uploads`).
3. If your MySQL root user has a password, edit
   `htdocs/lasso/api/config/db.php` and set `DB_PASS`.

## 4. Create the first administrator
Visit `http://localhost/lasso/setup_admin.php` once in your browser.
This creates:
- **Admin ID:** `admin001`
- **Password:** `Admin@123`

The script locks itself after the first admin exists. Log in at
`http://localhost/lasso/admin-login.html`, change the password from
Profile settings if desired, then delete `setup_admin.php` from the
server for security.

## 5. Use the system
- Students register at `http://localhost/lasso/register.html`
  (requires front & back photos of their Student ID) and then log in at
  `http://localhost/lasso/index.html`.
- Admins log in at `http://localhost/lasso/admin-login.html`, upload
  instructional materials (ordered page images + cover image) under
  **Materials**, then **Publish** them so they appear in the student Store.

## How the core flows map to your diagrams
- **Register** → `api/auth/register.php` (rejects duplicate Student IDs, per your sequence diagram).
- **Store / Limited Preview** → `api/materials/get.php` + `view_page.php` enforce the
  "first 5 body pages only, admin can exclude specific pages" rule from your summary.
- **Cart → Checkout → Billing Statement + Validation Code** → `api/cart/checkout.php`
  creates a billing statement and a validation code with status `awaiting_payment`.
- **Cashier confirms payment** → Admin → Sales & Cashier page → `api/admin/confirm_payment.php`
  flips the code to `ready` (simulates the code being transmitted to, then released by, the cashier).
- **Submit Validation Code** → Student → Profile → `api/subscription/validate.php` activates
  subscriptions (extends expiry by +1 semester if it's a repeat subscription, exactly as specified).
- **Progress Tracker** → logged automatically in `view_page.php`, counting body pages only
  (pages you mark as `non_body_pages` when uploading — TOC, glossary, cover, etc. — are excluded).
- **Admin material management** (Edit / Trash / Restore / Promote) →
  `api/admin/materials_action.php` and `materials_edit.php`.
- **Sales Report** (by material / department / program / year level / semester) →
  `api/admin/sales_report.php`.
- **Subscription Status Monitoring** → `api/admin/subscription_status.php`.

## Piracy / access-control measures implemented
- Material page images are stored **outside any web-linkable path with an `.htaccess`
  deny-all rule**; they're only ever served through `view_page.php`, which re-checks the
  student's subscription/preview eligibility on every single request.
- Pages are streamed with `no-store` cache headers and `Content-Disposition: inline`
  (never as a downloadable attachment).
- The frontend disables right-click and common save/print/dev-tools shortcuts while a
  `.protected` viewer is open, and images are marked non-draggable/non-selectable.
- **Caveat:** no browser-based system can fully block screenshots or screen recording —
  this implements the deterrents that are realistically achievable in a web app.

## Notes / things you may want to adjust
- `api/config/bootstrap.php` → `currentTerm()` guesses the semester from today's date;
  change the month ranges to match your actual academic calendar.
- Subscription length is hard-coded to 6 months ("+1 semester") in
  `api/subscription/validate.php` — adjust if your semesters run differently.
- Prices are entered manually per material by the admin during upload.
- This is a functional MVP covering every flow in your diagrams and summary; you're free
  to restyle `assets/css/style.css` to match your Figma design more closely.
