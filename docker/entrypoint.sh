#!/bin/sh
set -e

DATA_DIR=/data
mkdir -p "$DATA_DIR"

# 1) APP_KEY 只生成一次,存在 volume,之後重用
#    (token 用這把 key 加密;每次重生會導致舊 token 解不開)
if [ ! -f "$DATA_DIR/app_key" ]; then
    php artisan key:generate --show --no-ansi > "$DATA_DIR/app_key"
fi
export APP_KEY="$(cat "$DATA_DIR/app_key")"

# 2) SQLite 資料庫檔放 volume 上
[ -f "$DATA_DIR/database.sqlite" ] || touch "$DATA_DIR/database.sqlite"

# 3) schema + 快取(APP_KEY 已匯出)
php artisan migrate --force
php artisan config:cache
php artisan view:cache

# 4) 統一修正擁有者(volume 是 runtime 才掛上,WAL/快取檔都要 www-data 能寫)
chown -R www-data:www-data "$DATA_DIR" storage bootstrap/cache

exec "$@"
