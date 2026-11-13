# Render deployment guide

## Important: demo use only on Render Free

The current patient CRUD routes have no authentication or authorization. Anyone who can reach the public service can view, add, edit, and delete patient records. Do not deploy real patient information until access control and the clinic's privacy/security requirements have been implemented and reviewed.

Render documents Free instances as unsuitable for production. A Free web service has an ephemeral filesystem and can spin down; a Free Render Postgres database is limited to 1 GB, has no backups, and expires 30 days after creation. Use Free only for a demo with synthetic data. For real clinic operations, choose an appropriately secured, backed-up paid hosting/database arrangement and confirm its regulatory suitability.

## What this deployment uses

- The root `Dockerfile` builds Vite assets and a PHP 8.3/Apache runtime.
- Apache serves Laravel's `public` directory and listens on Render's `PORT` (defaulting to `10000`).
- The image defaults to production mode, debug off, PostgreSQL, database-backed sessions/cache/queues, secure session cookies, and logs to standard error. Laravel trusts the Render proxy headers so HTTPS requests and generated URLs are recognized correctly. Render provides `APP_KEY`, `APP_URL`, and `DB_URL` at runtime.
- Migrations are not run at image build or container startup. Apply them manually after creating the database.

## Before using real data

The existing Composer lockfile reports seven security advisories affecting Symfony components. The dependencies were not upgraded as part of this deployment setup. Review the current `composer audit` output, update to compatible patched versions, and rerun the test suite before treating the service as production-ready. The lack of authentication/authorization is a separate blocker for real patient data.

## 1. Prepare GitHub

Review the changes locally, then commit and push them to the repository's `main` branch when you are ready. Do not add `.env`, database exports, patient data, or secret values to Git.

## 2. Create the Render database

1. In the Render Dashboard, choose **New > PostgreSQL**.
2. Choose the same region as the web service.
3. For a demo, the Free plan is available but temporary and has no backups. For production, choose a paid plan with backup and retention appropriate to your needs.
4. Keep the database's **Internal Database URL** private. Use it for a web service in the same Render region.

The project already has a Laravel PostgreSQL connection. The patients migration uses ordinary columns and declares `sex` as a Laravel enum. Laravel 13.37.0 compiles that enum for PostgreSQL as `varchar(255)` with a `CHECK` constraint, so this migration does not require a MySQL-only schema rewrite. The current patient CRUD queries are also compatible with PostgreSQL.

Changing from local MySQL to PostgreSQL does not transfer existing data. The migration command only creates schema; if you need existing MySQL patient records, first make a verified backup and plan a separate cross-engine data migration and reconciliation. Do not point the production app at a new empty database and assume the old records have moved.

## 3. Create the Docker web service

1. Choose **New > Web Service**, connect the GitHub repository, and select the `main` branch.
2. Set **Language** to **Docker**.
3. Leave **Root Directory** empty (repository root) and use `Dockerfile` as the Dockerfile path. Do not select `bootstrap/Dockerfile`.
4. Leave the Docker command unset so the image entrypoint starts Apache.
5. Select the same region as PostgreSQL.
6. Create the service. Render supplies `PORT`; the container listens on it. Do not replace the start command with a development server.

## 4. Set Render environment variables

Open the web service's **Environment** page and add:

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | A newly generated key; treat it as a secret |
| `APP_URL` | The deployed HTTPS URL, such as `https://your-service.onrender.com` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | The PostgreSQL **Internal Database URL** (use Render's secret/reference mechanism if available) |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `info` |

Render sets the `PORT` variable for the running service; the Docker image has a fallback of `10000`. Do not set `APP_KEY` in the Dockerfile or commit it to the repository.

To generate a key from the project directory without writing it into `.env`, run:

```powershell
php artisan key:generate --show
```

Copy the printed value directly into Render's `APP_KEY` setting. Do not paste it into chat, Git, or a Docker build argument. Keep the same key across redeploys; changing it invalidates encrypted cookies and other encrypted application data.

## 5. Apply migrations safely

Do not run `db:wipe`, `migrate:fresh`, `migrate:refresh`, or seed production. `DatabaseSeeder` inserts a demo user and is not needed for this application.

For a paid Render service with Shell access, open the service shell and inspect status first:

```sh
php artisan migrate:status
php artisan migrate --force
```

Render Free web services do not provide Shell access or one-off jobs. For a new Free/demo database, run the migration from your Windows project checkout using the database's **External Database URL** and a PHP installation with `pdo_pgsql` enabled. Enter the URL at a hidden prompt rather than putting its password in a command or source file:

```powershell
$secureUrl = Read-Host "External PostgreSQL URL" -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureUrl)
$oldConnection = $env:DB_CONNECTION
$oldUrl = $env:DB_URL

try {
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_URL = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    php artisan migrate:status
    if ($LASTEXITCODE -ne 0) { throw 'Could not inspect migration status.' }
    php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'The migration command failed.' }
}
finally {
    if ($null -eq $oldConnection) { Remove-Item Env:DB_CONNECTION -ErrorAction SilentlyContinue }
    else { $env:DB_CONNECTION = $oldConnection }
    if ($null -eq $oldUrl) { Remove-Item Env:DB_URL -ErrorAction SilentlyContinue }
    else { $env:DB_URL = $oldUrl }
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
}
```

This command targets the URL supplied in that temporary process environment; confirm that it is the intended database before proceeding. A successful migration does not copy records from local MySQL. Check Render logs and the migration status afterward.

## 6. Verify the deployment

1. Wait for the Render deploy to finish and check the deploy/runtime logs for Composer, Vite, Apache, and database errors.
2. Open `https://your-service.onrender.com/up`; Laravel should return a healthy response.
3. Open `/` and confirm it redirects to `/patients`.
4. After migrations, open `/patients` and check that the page's CSS/assets load.
5. Only test create/edit/delete with synthetic data. Do not enter real patient information in this unauthenticated application.

## Uploads and persistent storage

There are no upload fields or file storage calls in the current patient CRUD. Render Free container files are ephemeral: local uploads and SQLite files disappear on restart, spin-down, or redeploy. If uploads are added later, use a durable private object-storage service (for example S3-compatible storage), add the corresponding Laravel filesystem adapter, and implement file validation plus authorization before enabling patient documents or images. Do not use the container's local disk as the only copy.
