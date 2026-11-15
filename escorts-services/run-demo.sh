#!/usr/bin/env sh
# Local preview with the placeholder UPI QR (fake payee — no real money can move).
# Uses a throwaway data folder so your real storage/ is untouched.
cd "$(dirname "$0")" || exit 1
export APP_UPI_DEMO=1
export APP_DATA_DIR="${APP_DATA_DIR:-$(mktemp -d)}"
echo "Demo data folder: $APP_DATA_DIR"
echo "Create a coordinator with:  APP_DATA_DIR=$APP_DATA_DIR php setup-admin.php coordinator"
exec php -d upload_max_filesize=5M -d post_max_size=16M -d max_file_uploads=4 -S 127.0.0.1:8000
