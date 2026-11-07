# Google API Setup

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and select or create a project.
2. Enable the Google Sheets API for that project.
3. Create a service account and generate a JSON key. Keep the downloaded key private.
4. Copy the key to `storage/app/google/credentials.json`, or set `GOOGLE_CREDENTIALS_PATH` to an absolute path in `.env`.
5. Share each spreadsheet with the service account's `client_email` from the JSON key, with Viewer access.

The application requests the read-only `spreadsheets.readonly` scope. Credential files under `storage/app/google/` are ignored by Git. Never commit the JSON key.

The default path is:

```dotenv
GOOGLE_CREDENTIALS_PATH=storage/app/google/credentials.json
```

The Google client is created lazily on the first API call. Missing or invalid credentials therefore do not prevent the admin panel or non-Google features from booting.
