# Student Management System (SMS)

A complete web-based Student Management System built with **PHP 8 + MySQL**, designed to replace
manual record keeping in a school or college with a single digital platform.

Manage students, teachers, courses, subjects, attendance, fees, examinations, results and reports
from one secure admin dashboard — plus an optional AI assistant powered by OpenAI.

---

## Features

| Module | What you can do |
| --- | --- |
| **Authentication** | Admin login/logout, password change, new-admin registration, session hardening, CSRF protection on every form |
| **Dashboard** | Student/teacher/course counters, fee totals, today's attendance, 7-day attendance bar chart, students-per-course doughnut chart, recent registrations, notices |
| **Students** | Full CRUD, photo upload, search & filters (course, semester, status), pagination, profile page with attendance %, fee balance and exam results |
| **Teachers** | Full CRUD, photo upload, search, profile page listing assigned subjects |
| **Courses** | Card-based CRUD with student/subject counts, duration and total fee |
| **Subjects** | CRUD per course + semester, assignment to a teacher, filterable list |
| **Attendance** | Daily marking per course/semester (Present / Absent / Late), upsert-safe saving, history view with per-student percentages and progress bars |
| **Fees** | Record payments with validation against the outstanding balance, auto-generated receipt numbers, printable receipts, payment search/filters, student balance overview |
| **Examinations** | Exam CRUD per course/semester, marks entry grid per subject, printable student result sheet with percentage, grade and pass/fail |
| **Reports** | Students / Attendance / Fees / Examination reports with filters, summary cards and **CSV export** (Excel-compatible, UTF-8 BOM) |
| **Notices** | Publish, list and delete announcements shown on the dashboard |
| **AI Assistant** | Chat UI that sends live, aggregate institutional data to OpenAI (`gpt-4o-mini`) with CSRF checks and rate limiting |

---

## Requirements

- **PHP 8.0+** with the `pdo_mysql`, `curl` and `fileinfo` extensions
- **MySQL 5.7+ / MariaDB 10.3+**
- Apache (XAMPP works out of the box) — or the PHP built-in server for quick testing
- A modern browser (Bootstrap 5 + Chart.js load from CDN, so internet access is needed for styling/charts)
- *Optional:* an OpenAI API key for the AI assistant

---

## Installation (XAMPP)

1. **Copy the project** into your htdocs folder, e.g.
   `C:\xampp\htdocs\Student Management System`

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Import the database.** Either
   - open <http://localhost/phpmyadmin>, go to *Import*, and choose
     `database/student_management.sql`, **or**
   - from a terminal:
     ```bash
     C:\xampp\mysql\bin\mysql.exe -u root < "database/student_management.sql"
     ```

   This creates the `student_management_db` database, all tables and demo data.

4. **Check the DB credentials** in `config/database.php` (defaults: host `127.0.0.1`,
   user `root`, empty password — the XAMPP default).

5. **Open the app:** <http://localhost/Student%20Management%20System/>

   You will be redirected to the login page.

### Default login

| Username | Password |
| --- | --- |
| `admin` | `admin123` |

> Change this password immediately after logging in (avatar menu → *Change password*).

---

## Running without Apache (optional)

From the project root:

```bash
C:\xampp\php\php.exe -S 127.0.0.1:8099 -t .
```

Then open <http://127.0.0.1:8099/>. `BASE_URL` is calculated automatically, so the app works both
at the server root and in a sub-folder (including folder names with spaces).

---

## OpenAI assistant setup

The API key is **never** exposed to the browser; it is read server-side only.

1. Copy `config/local.php.example` to `config/local.php`.
2. Put your key inside:

   ```php
   <?php
   define('OPENAI_API_KEY', 'sk-...');
   // define('OPENAI_MODEL', 'gpt-4o-mini'); // optional override
   ```

3. Reload the *AI Assistant* page. The status badge should read **Connected**.

Alternatively, set the `OPENAI_API_KEY` environment variable instead of creating the file.
`config/local.php` is excluded by `.gitignore` and the whole `config/` folder is blocked from
direct web access by `.htaccess`.

The assistant receives only **aggregate** figures (student/teacher/course counts, fee totals,
today's attendance) and is instructed not to invent numbers or expose internals.

---

## Project structure

```
Student Management System/
├── index.php                  # Entry point → redirects to login/dashboard
├── admin/
│   ├── dashboard.php          # Stats + charts + recent activity
│   ├── change_password.php
│   ├── notices.php
│   ├── students/              # index, create, edit, view, delete, _form
│   ├── teachers/              # index, create, edit, view, delete, _form
│   ├── courses/               # index, create, edit, delete, _form, subjects
│   ├── attendance/            # index (mark), history
│   ├── fees/                  # index, balances, receipt, delete
│   ├── examinations/          # index (CRUD), results (marks + result sheet)
│   └── reports/               # index, export (CSV)
├── ai/
│   ├── assistant.php          # Chat UI
│   └── chat.php               # JSON endpoint (CSRF + rate limited)
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── config/
│   ├── config.php             # App constants, BASE_URL, session setup
│   ├── database.php           # PDO singleton
│   ├── openai.php             # OpenAI helpers
│   ├── local.php.example      # Template for secrets
│   └── .htaccess              # Deny direct access
├── includes/
│   ├── bootstrap.php          # Loads config + helpers
│   ├── auth.php               # Login guard
│   ├── functions.php          # Helpers (CSRF, uploads, pagination, fees, …)
│   ├── header.php / sidebar.php / footer.php   # Layout
│   └── .htaccess              # Deny direct access
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── images/                # Uploaded photos (students/, teachers/)
└── database/
    ├── student_management.sql # Schema + seed data
    └── .htaccess              # Deny direct access
```

---

## Security notes

- **Hashed passwords** — `password_hash()` / `password_verify()` (bcrypt).
- **Prepared statements everywhere** — all SQL uses PDO with emulation disabled.
- **CSRF tokens** on every state-changing form and on the AI JSON endpoint.
- **Session hardening** — custom session name, `HttpOnly`, `SameSite=Lax`, ID regeneration on login.
- **Access control** — every admin page includes `includes/auth.php`, which redirects anonymous
  users to the login page.
- **Upload validation** — MIME sniffing via `finfo`, `getimagesize()` check, 2 MB limit, random
  filenames, whitelist of JPG/PNG/WEBP.
- **Output escaping** — all dynamic output goes through `e()` (`htmlspecialchars`).
- **Protected folders** — `config/`, `database/` and `includes/` are blocked by `.htaccess`.
- **Secrets** — API keys live in `config/local.php` or an environment variable, never in JS.

Before deploying publicly:

1. Set `APP_DEBUG` to `false` in `config/config.php`.
2. Change the default admin password and remove the demo credentials hint from `auth/login.php`.
3. Create `config/local.php` with a real key (or leave the assistant unconfigured).
4. Serve over HTTPS so the session cookie is never sent in clear text.

---

## Demo data included

- 5 courses (BCA, BSCIT, BCOM, BBA, MCA)
- 5 teachers, 14 subjects
- 12 students with courses/semesters
- ~10 days of attendance records
- 9 fee payments with receipts
- 6 examinations with 26 result rows
- 3 notices

Re-importing `database/student_management.sql` resets everything to this clean state
(it drops and recreates the database).

---

## Troubleshooting

| Problem | Fix |
| --- | --- |
| "Database connection failed" | Start MySQL in XAMPP; confirm credentials in `config/database.php`; import the SQL file |
| Blank page / 500 error | Check Apache/PHP error log; ensure PHP ≥ 8.0 and `pdo_mysql` is enabled |
| Styles or charts missing | Bootstrap/Chart.js load from CDN — check your internet connection |
| "OpenAI is not configured" | Create `config/local.php` with `OPENAI_API_KEY` (see above) |
| Photos not saving | Make sure `assets/images/` is writable by Apache |
| Links 404 under XAMPP | The folder must stay inside `htdocs`; `BASE_URL` is derived from `DOCUMENT_ROOT` |
