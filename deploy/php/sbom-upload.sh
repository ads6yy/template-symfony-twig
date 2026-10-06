#!/bin/sh
# Generates the CycloneDX SBOM of the application (Composer dependencies, dev excluded)
# and uploads it to Dependency-Track. Run daily by cron in the php container (see sbom-upload.cron).
set -eu

: "${DTRACK_API_URL:?DTRACK_API_URL is not set}"
: "${DTRACK_API_KEY:?DTRACK_API_KEY is not set (Dependency-Track API key with BOM_UPLOAD and PROJECT_CREATION_UPLOAD)}"
: "${DTRACK_PROJECT_NAME:?DTRACK_PROJECT_NAME is not set}"
: "${DTRACK_PROJECT_VERSION:?DTRACK_PROJECT_VERSION is not set}"

PROJECT_DIR="${SBOM_PROJECT_DIR:-/srv}"
SBOM_FILE="$(mktemp)"
trap 'rm -f "$SBOM_FILE"' EXIT

composer --working-dir="$PROJECT_DIR" --no-interaction CycloneDX:make-sbom \
    --output-format=JSON \
    --spec-version=1.6 \
    --omit=dev \
    --validate \
    --output-file="$SBOM_FILE"

curl --fail-with-body --silent --show-error \
    -X POST "$DTRACK_API_URL/api/v1/bom" \
    -H "X-Api-Key: $DTRACK_API_KEY" \
    -F "autoCreate=true" \
    -F "projectName=$DTRACK_PROJECT_NAME" \
    -F "projectVersion=$DTRACK_PROJECT_VERSION" \
    -F "bom=@$SBOM_FILE"
echo

echo "$(date -Iseconds) SBOM uploaded to Dependency-Track project $DTRACK_PROJECT_NAME@$DTRACK_PROJECT_VERSION"
