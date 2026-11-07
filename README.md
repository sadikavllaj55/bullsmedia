# BullsMedia

Syncs Google Sheets into MySQL daily, configured from an admin UI.

[![GitHub Actions CI](https://img.shields.io/badge/CI-GitHub%20Actions-blue?logo=githubactions&logoColor=white)](.github/workflows/ci.yml)

## Requirements

Docker and Docker Compose.

## Google API Key

1. Create a Google Cloud project.
2. Enable the Google Sheets API.
3. Create a service account.
4. Download its JSON key to `storage/app/google/credentials.json`.
5. Share each sheet with the service account email as **Viewer**.

[Troubleshooting](docs/google-api-setup.md).

## Run With Docker

```bash
git clone https://github.com/sadikavllaj55/bullsmedia.git
cd bullsmedia
cp .env.example .env
# Set DB_PASSWORD, MYSQL_ROOT_PASSWORD, and ADMIN_PASSWORD in .env.
# Use long, unique values; Docker Compose will refuse to start while they are empty.
docker compose up -d --build
```

Open http://localhost:8080/admin. Sign in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`.

On Ubuntu, place the Google service-account key at `storage/app/google/credentials.json`, then run `sudo ./install.sh`. The installer installs Docker Compose if needed, generates local passwords in `.env`, and starts the services.

## First Sync

1. Add a sheet by its URL.
2. Add a tab.
3. Click **Load headers** and adjust the mappings.
4. Click **Sync now**.

Keyed syncs upsert rows and do not remove rows deleted from the source sheet. Deactivating a tab or changing its target table also leaves previously imported rows in the old target table. Unkeyed syncs replace all rows belonging to that spreadsheet file in the target table.

The Google credentials directory is mounted read-only into the container. Ensure `credentials.json` is readable by the container's `www-data` user; on a single-user development machine, `chmod 644 storage/app/google/credentials.json` is one option. Avoid making credentials world-readable on shared hosts.

## Useful Commands

```bash
docker compose exec app php artisan sheets:sync --now
docker compose exec app php artisan test
docker compose logs -f queue
docker compose down
```
