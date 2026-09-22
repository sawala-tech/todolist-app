# TaskHub — Project Collaboration Edition (v2)

TaskHub adalah aplikasi PHP + MySQL untuk manajemen task personal dan project kolaborasi dengan sistem invitation, review workflow, dan dashboard berbasis role.

---

## Fitur

### Autentikasi & Akun
- Login dengan **username atau email**
- Password lama berbasis SHA-256 otomatis di-rehash ke bcrypt saat login pertama
- Status akun: `active`, `pending`, `suspended`
- Signup publik dinonaktifkan — akun baru hanya melalui invitation admin

### User Invitation
- Admin membuat akun pending + password sementara
- Token kriptografis (SHA-256 hash disimpan, raw token hanya di URL dan email)
- Default expiry: **24 jam**
- Admin dapat resend (token lama otomatis direvoke) dan revoke
- Pada environment tanpa SMTP: link activation ditampilkan di halaman admin (dev fallback)
- Aktivasi 2 langkah: verifikasi email + password sementara → set password baru

### Role
- Invitation dapat memilih role `user` atau `admin`
- Admin dapat mengubah role user yang sudah aktif dari halaman manajemen user

### Project Collaboration
- Admin membuat project (draft / active / archived) dan mengelola anggota
- User hanya melihat project yang menjadi anggotanya
- Task project bisa di-assign ke satu member aktif
- Priority: `low`, `medium`, `high`; label/kategori bebas

### Task Review Workflow
```
open → in_progress → review → done
                        ↓
                     revision → in_progress
```
- User tidak dapat langsung menandai task `done`
- Admin menyetujui (`review → done`) atau mengembalikan (`review → revision`) dengan feedback
- Feedback hanya satu field per task (bukan thread komentar)

### Dashboard
- **Admin**: ringkasan user per status, project aktif, review queue, progress bar per project
- **User**: daftar project yang diikuti, task yang ditugaskan, deadline 7 hari ke depan
- Task personal (tanpa project) tetap tampil di dashboard user

---

## Tech Stack

- PHP (mysqli, prepared statements)
- MySQL / MariaDB
- Apache dengan `.htaccess` rewrite
- Tailwind CSS (CDN)
- jQuery, SweetAlert2, Flowbite (CDN)

---

## Struktur Direktori

```text
assets/
  db/
    todo_list.sql        # Schema awal (v1)
    migration_v2.sql     # Migration v2 (jalankan setelah todo_list.sql)
  helpers/
    functions.php        # DB connection, task CRUD lama, auth dasar
    libs.php             # URL/path helpers
    auth_helpers.php     # CSRF, password bcrypt+SHA256, access guards
    invitation_helpers.php  # Invitation backend
    project_helpers.php  # Project & membership CRUD
    task_helpers.php     # State machine, review workflow, project tasks
  public/                # Uploaded attachments
components/
  navbar/index.php
  partials/project_form_fields.php
  templates/
pages/
  admin/
    index.php            # Admin dashboard
    users/index.php      # Manajemen user & invitation
    projects/
      index.php          # Project list + member panel
      tasks/create.php   # Buat tugas (admin)
  auth/
    signin/index.php     # Login (username atau email)
    signup/index.php     # Disabled — redirect ke signin
    signout/index.php
    invite/index.php     # Aktivasi invitation
  dashboard/index.php    # User dashboard
  backlog/
    index.php             # Backlog tugas pribadi
    task/create.php       # Buat tugas pribadi
    task/detail.php       # Detail/edit tugas pribadi
  projects/
    create/index.php      # Buat project
    detail.php            # Project view (user)
    tasks/create/index.php # Buat tugas dalam project
    tasks/detail.php      # Detail tugas + review actions
.htaccess                # Route mapping
.env.php                 # (Tidak di-commit) Konfigurasi mail
```

---

## Setup

### 1. Jalankan database

```bash
# Buat database
mysql -u root -p -e "CREATE DATABASE todo_list;"

# Import schema awal
mysql -u root -p todo_list < assets/db/todo_list.sql

# Import migration v2
mysql -u root -p todo_list < assets/db/migration_v2.sql
```

### 2. Konfigurasi koneksi DB

Edit `assets/helpers/functions.php`:
```php
$host     = "localhost";
$username = "root";
$password = "root";
$dbname   = "todo_list";
```

### 3. Web server (MAMP)

1. Letakkan project di `htdocs/todolist`
2. Nyalakan Apache dan MySQL
3. Buka: `http://localhost:8888/todolist`

---

## Konfigurasi Mail (Opsional)

Buat file `.env.php` di root project (jangan commit ke repository):

```php
<?php
define('MAIL_FROM',     'noreply@contoh.com');
define('MAIL_HOST',     'smtp.contoh.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'user@contoh.com');
define('MAIL_PASSWORD', 'rahasia');
```

Jika tidak dikonfigurasi, link invitation ditampilkan sebagai copyable link di halaman admin setelah membuat user baru (**development mode**).

---

## Akun Default

| Role  | Username | Password |
|-------|----------|----------|
| Admin | `admin`  | `admin`  |

> Password lama (SHA-256) otomatis di-upgrade ke bcrypt saat pertama login.

---

## Routes

| Method | Path | Halaman |
|--------|------|---------|
| GET/POST | `/auth/signin` | Login (username atau email) |
| GET | `/auth/signup` | Redirect ke signin (disabled) |
| GET/POST | `/auth/invite?token=...` | Aktivasi invitation |
| GET | `/auth/signout` | Logout |
| GET | `/dashboard` | Dashboard user |
| GET | `/admin` | Dashboard admin |
| GET/POST | `/admin/users` | Manajemen user & invitation |
| GET/POST | `/admin/projects` | Manajemen project & member |
| GET/POST | `/admin/projects/tasks/create?project_id=X` | Buat tugas (admin) |
| GET | `/projects/create` | Buat project |
| GET | `/projects/{id}` | Detail project (user) |
| GET/POST | `/projects/{id}/tasks/create` | Buat tugas dalam project |
| GET/POST | `/projects/{id}/tasks/{taskId}` | Detail & review tugas |
| GET/POST | `/backlog/task/create` | Buat tugas pribadi |
| GET/POST | `/backlog/task/{id}` | Detail tugas pribadi |

---

## Backward Compatibility

- User admin existing tetap dapat login dengan password SHA-256 lama
- Task personal existing (tanpa project) tetap tampil di dashboard user
- Migration v2 aman dijalankan pada database existing (menggunakan `IF NOT EXISTS` dan `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`)
- Existing user otomatis diberi status `active`

---

## Catatan Keamanan

- Semua form mutasi dilindungi CSRF token
- Semua query baru menggunakan prepared statement
- Token invitation disimpan hanya sebagai SHA-256 hash — raw token tidak pernah dicatat di log
- Upload file divalidasi berdasarkan ukuran, MIME type, extension, dan nama file
- Password baru menggunakan `password_hash()` (bcrypt)

---

## Fitur yang Tidak Diimplementasi (by design)

Sesuai spec, berikut **tidak tersedia**:

- Komentar (real-time maupun AJAX)
- Subtasks / checklist bertingkat
- Tampilan kalender
- Task dependency
- Notifikasi push/email
- Export PDF/CSV
- Recurring tasks
- Riwayat aktivitas / audit history
