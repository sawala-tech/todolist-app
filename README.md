# TaskHub Todo List (PHP)

TaskHub is a PHP + MySQL todo application with authentication, role-based access, task management, file attachments, and an admin dashboard.

## Repository Context

Repository context has been saved in `REPOSITORY_CONTEXT.md`.

## Features

- User signup and signin
- Session-based authentication
- Role-aware navigation (`admin` and `user`)
- Task CRUD on dashboard (add, edit, delete)
- Task status workflow: `open`, `in_progress`, `done`
- Optional task attachment upload
- Admin dashboard for task/user overview and summary

## Tech Stack

- PHP (mysqli)
- MySQL / MariaDB
- Apache with `.htaccess` rewrite rules
- Tailwind utility classes (loaded via CDN at runtime)
- Vanilla JavaScript + jQuery
- SweetAlert2 and Flowbite (CDN)

## Project Structure

```text
assets/
	css/style.css
	db/todo_list.sql
	helpers/
		functions.php   # DB + app logic
		libs.php        # URL/path helpers
	js/script.js
	public/           # Uploaded attachments
components/
	navbar/
	templates/
pages/
	auth/signin/
	auth/signup/
	auth/signout/
	dashboard/
	admin/
.htaccess           # Route mapping
```

## Routes

Defined in `.htaccess`:

- `/` -> `/auth/signin`
- `/dashboard` -> `pages/dashboard/index.php`
- `/admin` -> `pages/admin/index.php`
- `/auth/signin` -> `pages/auth/signin/index.php`
- `/auth/signup` -> `pages/auth/signup/index.php`
- `/auth/signout` -> `pages/auth/signout/index.php`

## Database Setup

1. Create a database named `todo_list`.
2. Import SQL from `assets/db/todo_list.sql`.

The SQL includes:

- `roles` table (`admin`, `user`)
- `users` table
- `tasks` table
- A seeded admin user

## Local Setup (MAMP)

1. Place this project in your web root (example: `htdocs/todolist`).
2. Start Apache and MySQL from MAMP.
3. Import `assets/db/todo_list.sql` into MySQL.
4. Confirm DB credentials in `assets/helpers/functions.php`:
	 - host: `localhost`
	 - username: `root`
	 - password: `root`
	 - database: `todo_list`
5. Open the app in browser:
	 - `http://localhost:8888/todolist` (default MAMP port)

## Default Admin Account

- Username: `admin`
- Password: `admin`

Note: Passwords are hashed in app logic using SHA-256.

## Notes

- `assets/public/` is used for uploaded files.
- `middleware.php` exists, but page-level guards are currently enforced using helper functions (`checkLogin`, `checkAdmin`).
