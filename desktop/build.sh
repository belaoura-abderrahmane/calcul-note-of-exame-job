#!/usr/bin/env bash
# بناء ملف exe واحد لويندوز:  bash desktop/build.sh <مجلد PHP لويندوز> <ملف الإخراج>
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHPWIN="$1"            # مجلد يحتوي php.exe و php8ts.dll و ext\ والمكتبات
OUT="$2"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "==> نسخ البرنامج"
mkdir -p "$STAGE/payload/app"
( cd "$ROOT" && tar \
    --exclude=./vendor --exclude=./node_modules --exclude=./.git --exclude=./tests \
    --exclude=./dist --exclude=./desktop --exclude=./.env --exclude=./database/database.sqlite \
    --exclude='./storage/logs/*.log' --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*.php' --exclude='./storage/framework/cache/data/*' \
    --exclude='./bootstrap/cache/*.php' --exclude=./.phpunit.result.cache \
    --exclude='*.bat' --exclude=./CLAUDE.md --exclude=./AGENTS.md -cf - . ) | ( cd "$STAGE/payload/app" && tar -xf - )
mkdir -p "$STAGE/payload/app/storage/framework/"{sessions,views,cache/data} "$STAGE/payload/app/storage/logs"

echo "==> نسخ المكتبات (بدون مكتبات التطوير)"
( cd "$ROOT" && tar --exclude='.git' --exclude='.github' -cf - vendor ) | ( cd "$STAGE/payload/app" && tar -xf - )
( cd "$STAGE/payload/app" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --optimize-autoloader --no-interaction -q \
  && cp .env.example .env && php artisan package:discover -q && rm .env )
# حذف ملفات غير ضرورية من vendor لتصغير الحجم
find "$STAGE/payload/app/vendor" -type d \( -iname tests -o -iname docs -o -iname .github \) -prune -exec rm -rf {} +

echo "==> نسخ PHP لويندوز"
cp -r "$PHPWIN" "$STAGE/payload/php"

echo "==> ضغط الحمولة"
( cd "$STAGE/payload" && zip -qr -9 "$STAGE/payload.zip" app php )
cp "$STAGE/payload.zip" "$ROOT/desktop/launcher/payload.zip"

echo "==> بناء exe"
( cd "$ROOT/desktop/launcher" && GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build -trimpath -ldflags "-H windowsgui -s -w" -o "$OUT" . )
if [ "${TEST_LINUX:-0}" = 1 ]; then
  ( cd "$ROOT/desktop/launcher" && CGO_ENABLED=0 go build -o "$OUT.linux" . )
fi
rm -f "$ROOT/desktop/launcher/payload.zip"
ls -lh "$OUT"
