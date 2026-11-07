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
docker compose up -d --build
```

Open http://localhost:8080/admin. Login: **admin@bullsmedia.test** / **password**.

## First Sync

1. Add a sheet by its URL.
2. Add a tab.
3. Click **Load headers** and adjust the mappings.
4. Click **Sync now**.

## Useful Commands

```bash
docker compose exec app php artisan sheets:sync --now
docker compose exec app php artisan test
docker compose logs -f queue
docker compose down
```
