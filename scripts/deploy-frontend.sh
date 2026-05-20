#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TF_DIR="$ROOT/infra/terraform"

cd "$TF_DIR"
BUCKET="$(terraform output -raw s3_bucket_name)"
DIST_ID="$(terraform output -raw cloudfront_distribution_id)"
REVERB_KEY="$(grep -E '^[[:space:]]*reverb_app_key[[:space:]]*=' terraform.tfvars | sed -E 's/.*"([^"]+)".*/\1/')"

cd "$ROOT/frontend"
export VITE_REVERB_APP_KEY="$REVERB_KEY"
export VITE_REVERB_SCHEME=https
export VITE_REVERB_PORT=443
npm ci
npm run build

aws s3 sync dist/ "s3://${BUCKET}/" --delete
aws cloudfront create-invalidation --distribution-id "$DIST_ID" --paths "/*"

echo "Frontend deployed to s3://${BUCKET}"
terraform -chdir="$TF_DIR" output -raw cloudfront_url
