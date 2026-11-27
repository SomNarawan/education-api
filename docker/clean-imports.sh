#!/bin/sh
# ล้างข้อมูลที่ import นิสิต/เกรด ใน container api เพื่อทดสอบการ import ใหม่
# (ห่อ php artisan dev:clean-import-data) คำสั่งจะแสดงสรุปและถามยืนยันก่อนลบเสมอ
#
#   ./docker/clean-imports.sh                          ล้างทั้งหมด (= --all)
#   ./docker/clean-imports.sh --grades-only --all      ล้างเฉพาะเกรด เก็บนิสิตไว้
#   ./docker/clean-imports.sh --plan=123               เฉพาะแผนการเรียน
#   ./docker/clean-imports.sh --student=6510000001     เฉพาะรหัสนิสิต
#   ./docker/clean-imports.sh --all --dry-run          ดูอย่างเดียว ไม่ลบ
#
# -u www-data: ถ้ารันเป็น root ไฟล์ log ที่สร้างใหม่จะเป็นของ root แล้วเว็บจะ error 500
# -e APP_ENV=local: คำสั่งยอมรันเฉพาะ local/testing ส่วน container อาจตั้งเป็น staging
set -eu

CONTAINER="${CONTAINER:-api}"

if [ "$#" -eq 0 ]; then
    set -- --all
fi

if [ -t 0 ] && [ -t 1 ]; then
    exec docker exec -it -u www-data -e APP_ENV=local "$CONTAINER" php artisan dev:clean-import-data "$@"
fi

exec docker exec -u www-data -e APP_ENV=local "$CONTAINER" php artisan dev:clean-import-data "$@"
