# Google API Setup

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and select or create a project.
2. Enable the Google Sheets API for that project.
3. Open **IAM & Admin → Service Accounts**, select **Create service account**, and finish the account setup.
4. Open the service account, go to **Keys → Add key → Create new key → JSON**, then download the key. Keep it private and do not commit it.
5. Open the JSON file and copy the `client_email` value. Share each spreadsheet with that address and grant **Viewer** access.
6. Copy the JSON key to `storage/app/google/credentials.json`, or set `GOOGLE_CREDENTIALS_PATH` to an absolute path in `.env`.

The application requests the read-only `spreadsheets.readonly` scope. Credential files under `storage/app/google/` are ignored by Git. Never commit the JSON key.

The default path is:

```dotenv
GOOGLE_CREDENTIALS_PATH=storage/app/google/credentials.json
```

The Google client is created lazily on the first API call. Missing or invalid credentials therefore do not prevent the admin panel or non-Google features from booting.
