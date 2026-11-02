# Manager Admin Seeder

This development seeder creates one active `super_admin` staff account for signing in to the manager app. It does not add a public HTTP route and does not reset or overwrite an existing account.

## Account details

- Username: `admin`
- Email: `admin@domain.com`
- Password: set locally through `MANAGER_ADMIN_SEED_PASSWORD` (use `505050` for the requested development account)

The password is read from the local environment rather than stored in source code. Do not set this weak development password in production.

## Run the seeder

Add this to your local `.env` file:

```dotenv
MANAGER_ADMIN_SEED_PASSWORD=505050
```

Then run:

```powershell
php artisan db:seed --class=AdminUserSeeder
```

The seeder uses the `staff_users` table and hashes the password through the `StaffUser` model's `hashed` cast.

If a staff account already uses the username `admin` or email `admin@domain.com`, the seeder prints `Admin account already exists; no changes were made.` and leaves the existing account and password unchanged. It will not reset an existing password; change credentials through a trusted administrative process.

After creating the account, manager login is available at `POST /api/v1/manager/auth/login`. Use either `admin` or `admin@domain.com` as `identifier`, and the configured password as `password`.
