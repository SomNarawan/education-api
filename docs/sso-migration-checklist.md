# Checklist: KU All Login (Staff) และ Baseline Migration

แผนงานแบบ step-by-step สำหรับ

1. ปิดช่องโหว่ด่วนบน production
2. ทำให้ `database/migrations` ตรงกับฐานข้อมูลจริง เพื่อให้ `migrate` และ queue worker ตอน deploy ไม่ล้ม
3. เชื่อมระบบฝั่ง staff เข้ากับ KU All Login ผ่าน ENG Main (`https://office.eng.kps.ku.ac.th/eng-main/home`) โดยยังมี mock login ไว้ทดสอบบนเครื่องตัวเอง

ติ๊ก `[x]` เมื่อเสร็จ ท้ายแต่ละ Phase มีหัวข้อ **ตรวจว่าเสร็จ** ให้เช็คก่อนไป Phase ถัดไป

## ภาพรวมลำดับงาน

| Phase | งาน | ต้องรอ |
|-------|-----|--------|
| 0 | ปิดช่องโหว่ด่วน | — |
| 1 | Baseline migration | — |
| 2 | ประสานผู้ดูแล ENG Main | — (ทำคู่ขนานกับ Phase 1) |
| 3 | Portal login ฝั่ง API (`education-api`) | Phase 1, 2 |
| 4 | Portal login ฝั่ง Web (`education-web`) | Phase 3 |
| 5 | Mock login สำหรับ local | Phase 3 |
| 6 | ทดสอบ | Phase 3–5 |
| 7 | ขึ้น production | Phase 6 |

**เอกสารอ้างอิง**

- คู่มือเชื่อมต่อสำหรับระบบลูก (std-eng-auth-service) และ API Flow Documentation ของ ENG Main — ควรเก็บสำเนาไว้ใน `docs/` ด้วย
- ระบบลาที่เชื่อม KU All Login สำเร็จแล้ว (ใช้เป็นตัวอย่าง)
  - `kukps-eng-leavesystem/leave-system-backend/src/modules/auth/org-auth.service.ts` — exchange token, get-role, verify.php
  - `kukps-eng-leavesystem/leave-system-backend/src/modules/auth/central-session.service.ts` — re-check session ทุก 120 วินาที
  - `kukps-eng-leavesystem/leave-system-frontend/src/components/guards/PrivateRouteGuard.tsx` — รับ token จาก URL และ redirect ไป portal

---

## Phase 0 — ปิดช่องโหว่ด่วน

**ปัญหา:** `.env.production` ตั้ง `MOCK_LOGIN_ENABLED=true` และ mock login ทำงานได้ทุก `APP_ENV` (ตั้งแต่ commit `88036c6`) ใครก็เปิด `/api/mock-login/admin` แล้วได้ JWT สิทธิ์ admin นอกจากนี้ `JWT_SECRET` ของ production สั้นเกินไป (เดา secret ได้ก็ปลอม token ได้) และ `JWT_REQUIRE_EXPIRATION=false` ทำให้ token ที่ไม่มี `exp` ใช้ได้ตลอดไป

- [ ] **0.1** จำกัด `/api/mock-login` ที่ Apache ให้เข้าได้เฉพาะ IP ภายใน (ใช้จนกว่า Phase 7 จะปิด mock ถาวร)
  ```apache
  <Location "/kukps-eng-education-ssd-api/api/mock-login">
      Require ip <ช่วง IP ภายในคณะ>
  </Location>
  ```
  ถ้าไม่ต้องใช้ mock บน production แล้ว ให้ตั้ง `MOCK_LOGIN_ENABLED=false` แทน (แต่ระหว่างนั้นจะไม่มีทาง login เลยจนกว่า Phase 7)
- [x] **0.2** สร้าง `JWT_SECRET` ใหม่ด้วย `openssl rand -hex 32` แล้วใส่ใน `.env.production` (ทุกคนต้อง login ใหม่หนึ่งครั้ง)
  > 2026-10-27: เปลี่ยนใน `.env.production` บนเครื่อง dev แล้ว (ของเดิมยาว 10 ตัวอักษร) — ยังต้อง copy ขึ้น server ตอน 0.5
- [x] **0.3** ตั้ง `JWT_REQUIRE_EXPIRATION=true` (mock ออก token ที่มี `exp` 8 ชั่วโมงอยู่แล้ว จึงไม่กระทบ)
  > ตั้งใน `.env.production` บนเครื่อง dev แล้ว และเปลี่ยนค่าใน `.env.example`, `.env.production.example`, default ใน `docker-compose.yml` เป็น `true`
- [x] **0.4** ลบค่า key จริงออกจากไฟล์ที่ commit แล้วเปลี่ยนเป็น placeholder
  - `PORTAL_MAIN_API_KEY` และ `HEALTH_TOKEN` ใน `.env.example`
  - ตัวอย่าง `X-API-KEY` ใน `docs/portal-main-student-api.md`
  - ค่าที่อยู่ใน git history แล้วถือว่ารั่ว ถ้าเคยใช้ค่าเหล่านี้ที่ไหนจริง ให้สร้างใหม่ด้วย `openssl rand -hex 32`
  > branch นี้: `.env.example` ว่างอยู่แล้ว, เปลี่ยน key ใน docs เป็น `<PORTAL_MAIN_API_KEY>` แล้ว · **ยังเหลือ** ค่าจริงใน `.env.example` ของ branch `chore/docker-production-stack` · `PORTAL_MAIN_API_KEY` ใน `.env.production` ไม่ตรงกับค่าที่รั่วทั้งสองค่า จึงไม่ต้อง rotate (`HEALTH_TOKEN` ไม่ได้ตั้งใน `.env.production`)
- [ ] **0.5** Deploy: `docker/deploy.sh .env.production`

**ตรวจว่าเสร็จ**

- [ ] เปิด `https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd-api/api/mock-login/admin` จากนอกเครือข่ายคณะ → ได้ 403 (หรือ 404 ถ้าปิด mock)
- [ ] token เก่าที่เซ็นด้วย secret เดิมเรียก `/api/me` → ได้ 401

---

## Phase 1 — Baseline migration

**ปัญหา:** `database/migrations` มีแค่ 4 ไฟล์ default ของ Laravel (users, cache, jobs, personal_access_tokens) ตั้งแต่ commit แรก แต่แอปใช้ตารางอีกประมาณ 17 ตาราง (`students`, `notes`, `imports`, `import_types`, `syncs`, `titles`, `high_schools` ฯลฯ) ซึ่งไม่มี migration เลย โครงสร้างอยู่แค่ใน DB/dump และถูกแก้ผ่าน phpMyAdmin

**ผลตอน deploy:** service `laravel-migrate` รัน `migrate --force` ก่อน แล้ว `laravel-app` และ `laravel-queue` จะเริ่มก็ต่อเมื่อ migrate สำเร็จ ถ้า DB มีตาราง `jobs`/`cache`/`users` อยู่แล้ว แต่ตาราง `migrations` ไม่ได้บันทึกไว้ จะล้มด้วย `Table already exists` ทำให้ทั้ง app และ queue ไม่ start

**เป้าหมาย:** `php artisan migrate` บน DB ว่างต้องได้ schema เดียวกับ production และบน DB ที่มีอยู่แล้วต้องรันผ่านโดยไม่แตะข้อมูล

### 1A. เก็บสถานะปัจจุบัน

- [ ] **1.1** Backup production เต็ม (เก็บไว้นอก repo)
  ```bash
  mysqldump --single-transaction --routines --triggers -u <user> -p kukps-education-dss \
    > prod-full-$(date +%Y%m%d).sql
  ```
- [ ] **1.2** Dump เฉพาะโครงสร้างทั้งของ production และ DB ที่ dev ใช้ (ตัด `AUTO_INCREMENT` ออก ไม่งั้น diff จะต่างทุกตาราง)
  ```bash
  mysqldump --no-data --skip-comments --skip-dump-date --routines -u <user> -p <database> \
    | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > schema-prod.sql   # ทำซ้ำกับ DB dev → schema-dev.sql
  ```
- [ ] **1.3** ดูตาราง `migrations` บน production แล้วจดว่ามีตารางนี้ไหม และมีแถวอะไรบ้าง
  ```sql
  SELECT migration, batch FROM migrations;
  ```

### 1B. ทำ schema ของ dev และ prod ให้ตรงกัน

- [ ] **1.4** `diff schema-prod.sql schema-dev.sql` แล้วจดทุกจุดที่ต่างลงตารางด้านล่าง
- [ ] **1.5** ตัดสินว่าแต่ละจุดฝั่งไหนถูก (ปกติยึดฝั่งที่โค้ดปัจจุบันใช้) แล้วแก้ DB ฝั่งที่ผิด — ครั้งนี้เป็นครั้งสุดท้ายที่แก้โครงสร้างด้วยมือ
- [ ] **1.6** Dump ซ้ำตามข้อ 1.2 แล้ว diff อีกครั้ง → ต้องไม่มีผลต่าง

| ตาราง / คอลัมน์ | prod | dev | ฝั่งที่ถูก | แก้แล้ว |
|-----------------|------|-----|-----------|---------|
|                 |      |     |           | [ ]     |

### 1C. สร้าง migration จาก schema

- [ ] **1.7** ติดตั้งตัวสร้าง migration เป็น dev dependency (ไม่ติดไปกับ Docker image เพราะ build ใช้ `--no-dev`)
  ```bash
  composer require --dev kitloong/laravel-migrations-generator
  ```
- [ ] **1.8** Generate จาก DB ที่แก้ให้ตรงกันแล้ว โดยข้ามตารางที่มี migration อยู่แล้ว และไม่เขียนลงตาราง `migrations`
  ```bash
  php artisan migrate:generate --skip-log \
    --ignore="migrations,users,password_reset_tokens,sessions,cache,cache_locks,jobs,job_batches,failed_jobs,personal_access_tokens"
  ```
  เช็ค option ด้วย `php artisan migrate:generate --help` ก่อน ถ้าเวอร์ชันที่ติดตั้งมี `--with-has-table` ให้ใส่ด้วย จะได้ guard ตามข้อ 1.9 ให้อัตโนมัติ
- [ ] **1.9** ใส่ guard ในทุกไฟล์ `create_*_table` ที่ generate ออกมา: DB เดิมจะแค่บันทึกว่ารันแล้ว ส่วน DB ใหม่จะสร้างตารางให้
  ```php
  public function up(): void
  {
      if (Schema::hasTable('students')) {
          return;
      }

      Schema::create('students', function (Blueprint $table) {
          // ...
      });
  }
  ```
- [ ] **1.10** ใส่ guard ในไฟล์ foreign key (`add_foreign_keys_to_*_table`) โดยเช็คจากชื่อ FK
  ```php
  public function up(): void
  {
      $existing = array_column(Schema::getForeignKeys('students'), 'name');

      Schema::table('students', function (Blueprint $table) use ($existing) {
          if (! in_array('students_title_id_foreign', $existing, true)) {
              $table->foreign('title_id', 'students_title_id_foreign')->references('id')->on('titles');
          }
      });
  }
  ```
- [x] **1.11** ใส่ guard แบบเดียวกันใน 3 ไฟล์ default ของ Laravel (`0001_01_01_000000`, `000001`, `000002`) ทีละ `Schema::create` ได้แก่ `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` — นี่คือจุดที่ทำให้ `laravel-migrate` ล้มด้วย `already exists`
  > ใส่ใน `2026_06_07_073727_create_personal_access_tokens_table` ด้วย (ล้มได้แบบเดียวกันถ้า DB มีตารางนี้แล้ว)
- [ ] **1.12** เช็คลำดับไฟล์: ตารางที่ถูกอ้างอิงต้องสร้างก่อน และไฟล์ FK ต้องอยู่หลังไฟล์ create ทั้งหมด

### 1D. ทดสอบ

- [ ] **1.13** ทดสอบบน DB ว่าง → schema ต้องตรงกับ production
  ```bash
  mysql -u <user> -p -e "CREATE DATABASE edu_baseline_test"
  DB_DATABASE=edu_baseline_test php artisan migrate
  mysqldump --no-data --skip-comments --skip-dump-date --routines -u <user> -p edu_baseline_test \
    | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > schema-fresh.sql
  diff schema-prod.sql schema-fresh.sql
  ```
  ผลต่างที่ยอมรับได้มีแค่ตาราง `migrations` และตารางของ Laravel ที่ production อาจยังไม่มี
- [ ] **1.14** ทดสอบบนสำเนาของ production → migrate ต้องผ่าน และโครงสร้างต้องไม่เปลี่ยน
  ```bash
  mysql -u <user> -p -e "CREATE DATABASE edu_prod_copy"
  mysql -u <user> -p edu_prod_copy < prod-full-YYYYMMDD.sql
  DB_DATABASE=edu_prod_copy php artisan migrate
  DB_DATABASE=edu_prod_copy php artisan migrate:status   # ทุกไฟล์ต้องเป็น Ran
  ```
  จากนั้น dump `--no-data` ของ `edu_prod_copy` แล้ว diff กับ `schema-prod.sql` → ต่างได้แค่ตาราง `migrations`
  (อย่าใช้ `migrate --pretend` ตรวจ เพราะใน pretend mode `Schema::hasTable()` จะคืน false เสมอ ผลที่ได้จึงหลอกตา)
- [ ] **1.15** ทดสอบผ่าน Docker ด้วย image จริง: ตั้ง `DB_DATABASE=edu_prod_copy` ใน env ที่ใช้ แล้วรัน `docker compose run --rm laravel-migrate` → log ต้องขึ้น `migrations finished`
- [ ] **1.16** `php artisan test` — ตอนนี้ DB สำหรับเทส (`kukps-education-dss-testing` ใน `phpunit.xml`) สร้างได้ด้วย `migrate` แล้ว

### 1E. กติกาหลังจากนี้

- [ ] **1.17** อัปเดต README หัวข้อ Database Setup: schema มาจาก migration ส่วน dump ใช้เฉพาะข้อมูล
- [ ] **1.18** ตกลงกติกากับทีม
  - เปลี่ยนโครงสร้างตาราง = สร้างด้วย `php artisan make:migration` แล้ว commit ไปพร้อมโค้ด
  - ห้ามแก้โครงสร้างผ่าน phpMyAdmin
  - ห้ามรัน `migrate:fresh`, `migrate:refresh` หรือ `migrate:reset` บน server
- [ ] **1.19** ทำ seeder ข้อมูลอ้างอิง (`titles`, `student_statuses`, `note_types`, `import_types`, `sync_types`, `provinces`, `districts`, `subdistricts`) เพื่อให้ DB ใหม่ใช้งานได้ และแทน `DatabaseSeeder` เดิมที่สร้าง user ทดสอบ
- [ ] **1.20** ตั้ง `.env` ของ local เป็น `QUEUE_CONNECTION=database` ให้เหมือน production (ตอนนี้เป็น `sync` จึงไม่เคยทดสอบผ่านตาราง `jobs` เลย)
- [ ] **1.21** Backup production อีกครั้ง แล้ว merge และ deploy ด้วย `docker/deploy.sh .env.production`

**ตรวจว่าเสร็จ**

- [ ] deploy ผ่าน และ `docker compose --env-file .env.production exec laravel-app php artisan migrate:status` แสดงทุกไฟล์เป็น Ran
- [ ] `docker compose --env-file .env.production ps` → `laravel-app` healthy และ `laravel-queue` running
- [ ] นำเข้าเกรด (ที่ใช้ queue) บน production ได้ตามปกติ

---

## Phase 2 — ประสานผู้ดูแล ENG Main

ทำคู่ขนานกับ Phase 1 ได้ Phase 3 ต้องใช้ข้อมูลจาก Phase นี้

- [x] **2.1** ลงทะเบียนระบบใน ENG Main → ได้ `systemId` = `33`
  > เดิมจดไว้เป็น `32` ซึ่งในตาราง `system` ของ ENG Main คือ CMIS (`kukps-cmis-system`) ระบบนี้คือ `33` (`education ssd`, short_name `es`) · 2026-10-27 ยิง get-role ด้วย `33` ได้ role ของระบบนี้ ส่วน `32` ได้ `roles: []`
- [x] **2.2** สร้าง role ใน ENG Main พร้อมลิงก์แยกต่อ role (ค่าอยู่ในตารางด้านล่าง) — ENG Main จะต่อ `?exchangeToken=…&intranetToken=…` ท้ายลิงก์ให้เอง
  > ENG Main สร้างปลายทางจาก `system.link` + `/` + `role.link` ลิงก์ role จึงต้องเป็น path แบบไม่มีโดเมน ถ้าใส่ URL เต็มจะได้ `…/kukps-eng-education-ssd/https://…` และเปิดเป็นหน้าขาว (แก้แล้ว 2026-10-27)
- [x] **2.3** ตรวจชื่อ role ที่ `get-role` ส่งกลับมาจริง (ชื่ออังกฤษ ชื่อไทย หรือตัวพิมพ์ใหญ่) แล้วแก้ `PORTAL_ROLE_MAP` ให้ตรง
  > ส่งมาเป็นชื่ออังกฤษตัวเล็ก `admin` และ `staff` ตรงกับ `PORTAL_ROLE_MAP=admin:admin,staff:teacher` เดิม ไม่ต้องแก้
- [x] **2.4** ให้ผู้ดูแลกำหนด role ให้ผู้ใช้ชุดแรก (admin และอาจารย์)
  > 2026-10-27 ตาราง `role_user_in_system` (`system_id = 33`): admin 2 บัญชี, อาจารย์ 3 บัญชี (2 บัญชีมีทั้งสอง role) · ผู้ใช้จริงที่เหลือเพิ่มได้ที่หน้าจัดการ role
- [ ] **2.5** ยืนยัน URL ของ exchange-token, get-role, std-eng-auth-service และหน้า logout ของ portal
  > 2026-10-27 ยิงทดสอบจากเครื่อง dev: get-role ตอบ 400 `Missing required query parameters (nontriId, systemId)`, exchange-token ตอบ 400 `Missing exchangeToken in request body`, verify.php ตอบ 401 — URL และชื่อ parameter ถูกต้อง · **เหลือหน้า logout ของ portal**
- [ ] **2.6** ขอ CA certificate ถ้า TLS verify ไม่ผ่าน (ระบบลาเคยต้องปิด verify; ควรใช้ CA file แทนการปิด)
  > จากเครื่อง dev (container `php:8.2-cli`) TLS verify ผ่านด้วย CA ปกติของระบบ ทั้ง office.eng และ eng.kps · ยังต้องยืนยันจาก container บน server (ข้อ 2.8) ถ้าไม่ผ่านให้ตั้ง `PORTAL_CA_FILE=storage/certs/office-ca.pem`
- [ ] **2.7** ขอบัญชีทดสอบ 3 แบบ: มี role admin, มี role teacher, ไม่มี role ในระบบนี้
  > มีบัญชีที่มีทั้งสอง role และบัญชีที่มีแค่อาจารย์แล้ว (ข้อ 2.4) แต่ยังไม่มีบัญชีที่เป็น admin อย่างเดียว · บัญชีที่ไม่มี role คือใครก็ได้ที่ไม่อยู่ใน `system_id = 33`
- [ ] **2.8** ทดสอบว่า container บน server เรียก ENG Main ได้
  ```bash
  docker compose --env-file .env.production exec laravel-app php artisan tinker \
    --execute="dump(Http::timeout(10)->get('https://office.eng.kps.ku.ac.th/eng-main/api/external/get-role')->status());"
  ```
  ได้ status 400/401 = เชื่อมต่อได้ · ได้ `cURL error 6/7/28` = DNS หรือ network · ได้ `cURL error 60` = certificate
- [ ] **2.9** ตัดสินใจแหล่ง `department_id` / `faculty_id` ของอาจารย์ตอน login จริง (mock ใช้จาก `resources/mocks/mock-login.json` แต่ `TeacherApiService` คืนมาแค่ `department_name`)
  - ทางเลือก ก: map `department_name` → `system_departments.th_name`
  - ทางเลือก ข: ขอให้ CMIS / Personnel API ส่ง id มาให้
  > โค้ดใช้ทางเลือก ก ไปก่อน (`StaffProfileResolver`) — ถ้าเลือก ข แก้ที่ไฟล์นั้นไฟล์เดียว · ยังต้องยืนยันว่า `department_name` จาก CMIS ตรงกับ `th_name` จริง

**ข้อมูลที่ได้จาก ENG Main** (กรอกเมื่อได้รับ)

| รายการ | ค่า |
|--------|-----|
| `systemId` | `33` (short_name `es`) · `32` คือ CMIS · get-role รับ short_name ได้ด้วย (ระบบลาใช้ `LE`) |
| URL ของระบบ (`system.link`) | `https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd` |
| หน้าจัดการ role ใน ENG Main | `https://office.eng.kps.ku.ac.th/eng-main/private/system/33/role` |
| Role ผู้ดูแลระบบ | `role_id` 902 · ชื่ออังกฤษ `admin` · ลิงก์ `auth/portal/admin` |
| Role อาจารย์ | `role_id` 903 · ชื่ออังกฤษ `staff` · ลิงก์ `auth/portal/teacher` |
| exchange-token | `https://office.eng.kps.ku.ac.th/eng-main/api/external/exchange-token` (ยืนยัน 2026-10-27) |
| get-role | `https://office.eng.kps.ku.ac.th/eng-main/api/external/get-role` (ยืนยัน 2026-10-27; error ใช้ field `errorMsg`) |
| std-eng-auth-service | `https://eng.kps.ku.ac.th/std-eng-auth-service` |
| หน้า login ของ portal | `https://office.eng.kps.ku.ac.th/eng-main/home` |
| หน้า logout ของ portal | |
| role ผู้ดูแลระบบ ที่ `get-role` ส่งมาจริง (2.3) | `admin` |
| role อาจารย์ ที่ `get-role` ส่งมาจริง (2.3) | `staff` |
| แหล่ง department / faculty (2.9) | |

---

## Phase 3 — Portal login ฝั่ง API (`education-api`)

**Flow**

```
Portal redirect → /kukps-eng-education-ssd/auth/portal/{admin|teacher}?exchangeToken=…&intranetToken=…
Web             → POST /api/auth/portal-login { exchangeToken, intranetToken, role }
API             → 1) decode intranetToken → nontriId (sub), exp
                  2) GET  {PORTAL_MAIN_API_URL}/external/get-role   (Bearer intranetToken) → roles
                  3) POST {PORTAL_MAIN_API_URL}/external/exchange-token { exchangeToken } → ssoToken
                  4) หา department / faculty
                  5) บันทึก auth_sessions (เข้ารหัส token)
                  6) ออก JWT ของระบบเรา (JwtIssuer เดิม) + claim sid
Web             → เก็บ JWT แล้วเรียก /api/me ตามเดิม
```

JWT ของเรามี claims ชุดเดียวกับ mock เพื่อให้ `AuthenticateJwt`, `MeController` และ frontend ใช้โค้ดเดิมได้ทั้งหมด ส่วน `intranetToken` และ `ssoToken` อยู่ฝั่ง server เท่านั้น ห้ามส่งลง browser

### Config

- [x] **3.1** สร้าง `config/portal_auth.php` ที่อ่านค่าจาก env
  ```env
  PORTAL_MAIN_API_URL=https://office.eng.kps.ku.ac.th/eng-main/api
  PORTAL_SYSTEM_ID=33
  PORTAL_ROLE_MAP=admin:admin,staff:teacher   # role ใน ENG Main : role ของระบบเรา (ยืนยันแล้วในข้อ 2.3)
  ORG_AUTH_API_URL=https://eng.kps.ku.ac.th/std-eng-auth-service
  PORTAL_VERIFY_SSL=true
  PORTAL_CA_FILE=
  PORTAL_TIMEOUT=10
  PORTAL_RECHECK_SECONDS=120
  ```
- [x] **3.2** เพิ่มตัวแปรข้างต้นใน `.env.example`, `.env.production.example` และ **`docker-compose.yml` ส่วน `x-laravel-env`** — compose ส่ง env เข้า container เฉพาะตัวที่ระบุไว้ ถ้าลืม container จะไม่มีค่า
  > branch นี้ (แตกจาก `develop`) ยังไม่มี `x-laravel-env` จึงใส่ใน `backend.environment` แทน — **ตอน merge กับ `chore/docker-production-stack` ต้องย้ายไปไว้ใน `x-laravel-env`** · ทุกตัวมีค่า default ใน config แล้ว ถ้าไม่ตั้งก็ใช้ค่าตามตารางนี้

### Database

- [x] **3.3** Migration `create_auth_sessions_table` (เป็น migration ใหม่ตัวแรกหลัง baseline)
  > ไฟล์ `2026_10_27_000000_create_auth_sessions_table.php` — ถ้า baseline (1.8) generate ทีหลังจะได้ timestamp ใหม่กว่า ไม่กระทบเพราะตารางนี้ไม่มี FK แต่ถ้าอยากให้ลำดับตรงตามแผนให้เปลี่ยนชื่อไฟล์ให้อยู่หลัง baseline ก่อน deploy ครั้งแรก
  - `id` (uuid, PK)
  - `nontri_id` (index), `roles` (json)
  - `intranet_token` (text), `sso_token` (text, nullable)
  - `expires_at`, `checked_at`, `revoked_at` (nullable), `revoke_reason` (nullable), timestamps
- [x] **3.4** Model `AuthSession` ใช้ cast `'intranet_token' => 'encrypted'`, `'sso_token' => 'encrypted'`, `'roles' => 'array'` — เข้ารหัสด้วย `APP_KEY` จึงห้ามเปลี่ยน `APP_KEY` (ถ้าเปลี่ยน session ทั้งหมดจะใช้ไม่ได้)

### Service

- [x] **3.5** `app/Services/PortalAuthService.php`
  - `readIntranetToken()` — decode payload แบบ base64url เพื่ออ่าน `sub` และ `exp` โดยไม่ verify ลายเซ็น (portal ไม่แชร์ key ให้ ความน่าเชื่อถือมาจาก get-role) และปฏิเสธทันทีถ้า `exp` ผ่านไปแล้ว
  - `fetchRoles()` — GET get-role ส่ง `nontriId` และ `systemId`; ถ้า response มี `nontriId` ต้องตรงกับที่ขอ; map ด้วย `PORTAL_ROLE_MAP` โดยเทียบแบบไม่สนตัวพิมพ์ใหญ่/เล็ก
  - `exchangeToken()` — POST exchange-token แล้วคืน `ssoToken`
  - `verifySession()` — GET `verify.php` (ไม่ใช้ `deep=1`)
  - ทุกเมธอดคืนผล 3 แบบ: `ok` / `rejected` (HTTP 4xx หรือ `status: fail`) / `unavailable` (5xx, timeout, network)
  - ใช้ `Http::timeout()` และ `withOptions(['verify' => PORTAL_CA_FILE หรือ PORTAL_VERIFY_SSL])`
  - ห้าม log ค่า token
  > ผลลัพธ์เป็น `PortalAuthResult` · HTTP 408/429 นับเป็น `unavailable` (ไม่ใช่การปฏิเสธ) · ถ้า map role ไม่ได้เลย reason ใน log จะมีชื่อ role ที่ ENG Main ส่งมา (ใช้ตรวจข้อ 2.3) · การ re-check อยู่ใน `PortalSessionService`
- [x] **3.6** หา department / faculty ตามที่ตัดสินใจในข้อ 2.9
  > `StaffProfileResolver` (ทางเลือก ก) — CMIS ล่มหรือหาไม่เจอ → `null` และ login ต่อได้ · ชื่อมาจาก `full_name_th` ของ CMIS (ไม่เจอ = nontri id)

### Endpoint

- [x] **3.7** `POST /api/auth/portal-login` (`withoutMiddleware(AuthenticateJwt::class)`, `throttle:10,1`) ทำตามลำดับ
  1. validate `exchangeToken`, `intranetToken` (required string) และ `role` (`admin` หรือ `teacher` — role ที่ผู้ใช้กดเข้ามาจาก ENG Main)
  2. `readIntranetToken()` → `nontriId`
  3. `fetchRoles()` — rejected, ไม่มี role ของระบบนี้ หรือไม่มี `role` ที่ส่งมา → 403 · unavailable → 503
  4. `exchangeToken()` **ทำหลังสุด** เพราะใช้ได้ครั้งเดียว — rejected → 401 · unavailable → 503
  5. หา department / faculty
  6. สร้าง `AuthSession` โดย `expires_at` = ค่าที่น้อยกว่าระหว่าง `exp` ของ intranetToken กับ now + 8 ชั่วโมง
  7. ออก JWT ด้วย `JwtIssuer` claims: `sid`, `nontri_id`, `name`, `role` (ทุก role ที่มี), `current_role` (= `role` ที่ส่งมา), `department_id`, `faculty_id`, `iat`, `exp`
  8. ตอบผ่าน `ApiResponse::success` → `{ token, user }`
  > error มี `code`: `PORTAL_LOGIN_REJECTED` (401/403) หรือ `PORTAL_UNAVAILABLE` (503) · ⚠️ `throttle:10,1` นับตาม IP แต่ยังไม่ได้ตั้ง trusted proxy — หลัง Apache ทุกคนจะเป็น IP เดียวกัน (10 login/นาที ทั้งคณะ) ต้องตั้ง `trustProxies` หรือปรับ limit ก่อน Phase 7
- [x] **3.8** `POST /api/auth/logout` (ต้องมี JWT) → ตั้ง `revoked_at` และ `revoke_reason = logout`
  **ห้ามเรียก `/api/logout.php` ของ std-eng-auth-service** เพราะ token ใช้ร่วมกับระบบแม่และระบบลูกอื่น ผู้ใช้จะถูกเตะออกจากทุกระบบ

### Middleware

- [x] **3.9** แก้ `app/Http/Middleware/AuthenticateJwt.php`
  - token มี `sid` → หา `AuthSession` ถ้าไม่พบ ถูก revoke หรือหมดอายุ → 401 `{ code: "PORTAL_SESSION_ENDED" }`
  - token ไม่มี `sid` → ยอมรับเฉพาะตอน `config('mock_login.enabled')` เป็น true (= token จาก mock) ไม่งั้นตอบ 401
  - ถ้า `checked_at` เก่ากว่า `PORTAL_RECHECK_SECONDS` → ตรวจ get-role และ verify.php พร้อมกัน (`Http::pool`) และใช้ `Cache::lock("auth-recheck:{sid}")` กัน request พร้อมกันยิงซ้ำ
    - rejected / session ended → revoke แล้วตอบ 401 `PORTAL_SESSION_ENDED`
    - ok → อัปเดต `roles` และ `checked_at`
    - unavailable → ให้ผ่าน, log warning และอัปเดต `checked_at` (ห้ามเตะผู้ใช้ออกเพราะระบบกลางล่ม)
  > ถ้าได้ lock ไม่ได้ (มี request อื่นกำลัง re-check) จะให้ผ่านด้วยสถานะล่าสุด ไม่รอ · `APP_KEY` เปลี่ยนจนถอดรหัส token ไม่ได้ → revoke (`token_unreadable`)
- [x] **3.10** `MeController` — ถ้า token มี `sid` ให้ใช้ `roles` จาก `AuthSession` (ที่ re-check แล้ว) แทน claim ใน JWT
  > ทำใน `AuthenticateJwt` แทน: แทนที่ `role` (และ `current_role` ถ้าไม่อยู่ใน role แล้ว) ใน `jwt_claims` ด้วยค่าจาก session — `MeController` และทุกจุดที่อ่าน `jwt_claims` (เช่น `ResetStudentGradesRequest`) จึงเห็น role ล่าสุดโดยไม่ต้องแก้
- [x] **3.11** ลบ session ที่หมดอายุ — ตอนนี้ยังไม่มี scheduler container ให้เลือก
  > ใช้ทางเลือก ก
  - ก: ลบใน `portal-login` เลย (`AuthSession::where('expires_at', '<', now()->subDay())->delete()`)
  - ข: เพิ่ม service `php artisan schedule:work` ใน `docker-compose.yml`

**ตรวจว่าเสร็จ:** test ฝั่ง API ใน Phase 6 ผ่านทั้งหมด
> 2026-10-27: `PortalLoginTest` ผ่าน 28 tests (รันใน container `php:8.2-cli` เพราะเครื่อง dev ไม่มี PHP)

---

## Phase 4 — Portal login ฝั่ง Web (`education-web`)

- [x] **4.1** เพิ่ม env `VITE_AUTH_PROVIDER=mock|portal`, `VITE_PORTAL_LOGIN_URL`, `VITE_PORTAL_LOGOUT_URL` ใน
  - `.env.staff`, `.env.student`, `.env.production.example`
  - `Dockerfile` (ARG / ENV)
  - `docker-compose.yml` ของ `education-web`
  - `frontend.build.args` ใน `docker-compose.yml` ของ `education-api`
  (ค่า `VITE_*` ฝังตอน build ต้อง rebuild ทุกครั้งที่เปลี่ยน)
  > ค่าเริ่มต้นเมื่อไม่ได้ตั้ง = `portal` (Dockerfile และ compose ของ API) ส่วน `.env.staff`, `.env.student`, compose ของ web = `mock` · `.env` ของ API บนเครื่องนี้เพิ่ม `VITE_AUTH_PROVIDER=mock` สำหรับ `docker compose` local
- [x] **4.2** `src/config/auth.ts` — export `authProvider`, `portalLoginUrl`, `portalLogoutUrl`
- [x] **4.3** `src/services/authService.ts` — `portalLogin(exchangeToken, intranetToken, role)` และ `logout()`
- [x] **4.4** หน้า `/auth/portal/:role` ใน `src/App.tsx` (วางระดับเดียวกับ `/auth/callback` อยู่นอก `MainLayout` และ `ProtectedRoute` เพราะ `ProtectedRoute` จะ redirect ไป `/` แล้ว query string หาย)
  - รับเฉพาะ `:role` เป็น `admin` หรือ `teacher` ค่าอื่นให้แสดงหน้า error
  - อ่าน `exchangeToken` และ `intranetToken` จาก `window.location.search`
  - ลบออกจาก URL ทันทีด้วย `window.history.replaceState()`
  - ใช้ `useRef` กันยิงซ้ำ (React StrictMode รัน effect 2 รอบตอน dev แต่ exchangeToken ใช้ได้ครั้งเดียว)
  - เรียก `portalLogin()` แล้วต่อด้วย `loginWithToken(token)` ที่มีอยู่แล้วใน `AuthContext`
  - ให้ role ที่กดมาเป็น current role — ตอนนี้ `AuthContext` เลือก `admin` ก่อนเสมอ คนที่มีทั้งสอง role จะกด tile อาจารย์แล้วเข้าเป็น admin ต้องแก้ให้ใช้ `current_role` จาก `/me`
  - สำเร็จ → `navigate(getDefaultRouteForRole(role))` (admin → `/students/department`, teacher → `/students/advisor`)
  - ถ้าล้มเหลว → แสดงหน้า error พร้อมปุ่ม "กลับไปที่ Portal" (ห้าม redirect อัตโนมัติ จะวนลูป)
  > `src/pages/PortalLogin.tsx` · สำเร็จแล้ว `navigate('/')` ให้ `RoleRedirect` รอ `/me` แล้วพาไปหน้าแรกของ `current_role` (ได้ปลายทางเดียวกับ `getDefaultRouteForRole(role)` แต่ไม่ render หน้าก่อนโหลด user เสร็จ) · `AuthContext` เลือก role ตามลำดับ: role ที่เลือกจากเมนู → `current_role` จาก `/me` → admin → role แรก
- [x] **4.5** `src/components/RoleRedirect.tsx` — ไม่มี token และ provider เป็น `portal` → `window.location.assign(portalLoginUrl)` ส่วน provider เป็น `mock` → ไป `/mock-login` เหมือนเดิม
- [x] **4.6** `src/config/axios.ts` — เพิ่ม response interceptor: ได้ 401 → ล้าง `auth_token`, `auth_user`, `current_role` แล้วไป `portalLoginUrl` (หรือ `/` เมื่อเป็น mock) โดยยกเว้น request `portal-login` เอง
- [x] **4.7** Logout (`HeaderBar` → `AuthContext.logout`) — เรียก `/api/auth/logout` (ไม่ต้องสนใจ error) → ล้าง localStorage → ถ้าเป็น portal ให้ไป `portalLogoutUrl`
  > เพิ่ม `clearSession()` (ล้างเฉพาะในเครื่อง) ให้ 3 หน้าที่เรียก `logout()` เมื่อเจอ 401 (`MasterDataManagementPage`, `StudentImportPicker`, `SyncData`) ไม่งั้น session หมดอายุจะพาไปหน้า logout ของ portal และเตะผู้ใช้ออกจาก portal ด้วย
- [x] **4.8** ให้ `/mock-login` และ `/auth/callback` ใช้ได้เฉพาะเมื่อ provider เป็น `mock`
- [x] **4.9** `npm run lint` และ `npm run build` ผ่าน
  > 2026-10-27 ผ่านทั้งคู่ (branch `feature/portal-sso-login` ของ `education-web`) · ยังไม่ได้ทดสอบ flow ใน browser จริง

---

## Phase 5 — Mock login สำหรับ local

- [x] **5.1** `config/mock_login.php` — ห้ามเปิด mock บน production (ย้อนพฤติกรรมของ commit `88036c6`) ถ้าต้องการ server ไว้ทดสอบ ให้ตั้ง `APP_ENV=staging` บน server นั้น
  ```php
  'enabled' => filter_var(env('MOCK_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOL)
      && env('APP_ENV') !== 'production',
  ```
- [x] **5.2** `MockLoginController` ออก claims ครบทุก key เหมือน portal login ยกเว้น `sid`
  > มีครบอยู่แล้ว ไม่ต้องแก้ · ตัด `app()->environment('production')` ใน constructor ออก เพราะ config ตามข้อ 5.1 เช็คให้แล้ว · ผลข้างเคียงของ 4.4: mock user ที่มี admin จะเข้าเป็น teacher ก่อน (ตาม `current_role`) แล้วสลับเป็น admin จากเมนูได้
- [x] **5.3** อัปเดตคอมเมนต์ใน `config/mock_login.php`, ตั้ง `.env.production.example` เป็น `MOCK_LOGIN_ENABLED=false` และอัปเดต README
- [x] **5.4** ตั้งค่า env ของ local
  > `education-api/.env` เป็นค่าตามตารางอยู่แล้ว · เพิ่ม `VITE_AUTH_PROVIDER=mock` ใน `.env.staff`, `.env.student`, `.env.development` ของ web

  | ไฟล์ | ค่า |
  |------|-----|
  | `education-api/.env` | `APP_ENV=local`, `MOCK_LOGIN_ENABLED=true` |
  | `education-web/.env.staff` | `VITE_AUTH_PROVIDER=mock`, `VITE_MOCK_LOGIN_ENABLED=true` |
  | `education-web/.env.student` | `VITE_AUTH_PROVIDER=mock` |

- [x] **5.5** โหมดนิสิต (`npm run dev:student`) ยังใช้ mock ตามเดิม — login ของนิสิตไม่อยู่ในขอบเขตของ checklist นี้

---

## Phase 6 — ทดสอบ

### Feature test ฝั่ง API (`tests/Feature/PortalLoginTest.php` ใช้ `Http::fake()`)

- [x] login สำเร็จ → 200, token มี `sid` และมีแถวใน `auth_sessions`
- [x] intranetToken หมดอายุ → 401 และไม่มี request ออกไปข้างนอก (`Http::assertNothingSent()`)
- [x] get-role ตอบ 403 หรือ roles ว่าง → 403 และ**ไม่**เรียก exchange-token
- [x] get-role ตอบ `nontriId` ไม่ตรงกับที่ขอ → 403
- [x] ส่ง `role` ที่ผู้ใช้ไม่มีใน get-role (เช่น `admin` แต่มีแค่ `staff`) → 403
- [x] ผู้ใช้มีทั้งสอง role ส่ง `role=teacher` → JWT มี `current_role=teacher` และ `role` มีทั้ง `admin`, `teacher`
- [x] exchange-token ตอบ 401 (หมดอายุหรือใช้ไปแล้ว) → 401
- [x] ENG Main timeout หรือ 5xx ตอน login → 503 (ไม่ใช่ 401)
- [x] re-check: get-role rejected → 401 `PORTAL_SESSION_ENDED` และ session ถูก revoke
- [x] re-check: verify.php ตอบ 401 → 401 `PORTAL_SESSION_ENDED`
- [x] re-check: ENG Main ล่ม → request ยังผ่าน
- [x] logout → `revoked_at` ถูกตั้ง, token เดิมใช้ไม่ได้ และไม่มี request ไป `logout.php` (`Http::assertNotSent()`)
- [x] `APP_ENV=production` + `MOCK_LOGIN_ENABLED=true` → `/api/mock-login/*` ตอบ 404
- [x] token ที่ไม่มี `sid` ขณะ mock ปิด → 401

### ทดสอบด้วยมือบน server (ใช้บัญชีทดสอบจากข้อ 2.7)

- [ ] เปิด `https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd/` โดยไม่มี session → ถูกพาไป ENG Main
- [ ] กด tile ผู้ดูแลระบบ → `/students/department` · กด tile อาจารย์ → `/students/advisor` · บัญชีไม่มี role → หน้า error และไม่วนลูป
- [ ] บัญชีที่มีทั้งสอง role: กด tile อาจารย์ → เข้าเป็น teacher (ไม่ใช่ admin) และยังสลับเป็น admin ได้จากเมนูมุมขวาบน
- [ ] หลัง login แล้ว address bar ไม่มี token เหลืออยู่
- [ ] refresh หน้าแล้วยังอยู่ในระบบ
- [ ] login ใหม่ที่ ENG Main ในแท็บอื่น → ภายในประมาณ 2 นาทีระบบเราเด้งกลับ portal (เพราะ token เดิมถูก revoke)
- [ ] ถอน role ใน ENG Main → ภายในประมาณ 2 นาทีถูกเด้งออก
- [ ] กด logout → ไปหน้า portal และระบบอื่นใน portal ยังใช้งานได้ตามปกติ

---

## Phase 7 — ขึ้น production

### ก่อน deploy

- [ ] **7.1** Backup ฐานข้อมูล
- [x] **7.2** ยืนยันว่า ENG Main ตั้ง redirect URL ชี้ production แล้ว (ข้อ 2.2)
  > `system.link` ของระบบ 33 ชี้ production อยู่แล้ว (ตรวจจาก DB ของ ENG Main 2026-10-27)
- [ ] **7.3** `.env.production` ฝั่ง API: `MOCK_LOGIN_ENABLED=false`, ค่า `PORTAL_*` ครบ และ `JWT_REQUIRE_EXPIRATION=true`
- [ ] **7.4** ค่า build ของ web: `VITE_AUTH_PROVIDER=portal`, `VITE_PORTAL_LOGIN_URL`, `VITE_PORTAL_LOGOUT_URL` (ใน compose ของ API `VITE_MOCK_LOGIN_ENABLED` อ่านจาก `MOCK_LOGIN_ENABLED` จึงเป็น false ตามข้อ 7.3)

### Deploy

- [ ] **7.5** `docker/deploy.sh .env.production` — migrate จะสร้างตาราง `auth_sessions` ก่อน rollout
- [ ] **7.6** Build frontend ใหม่ — `deploy.sh` build แค่ `laravel-app` กับ `nginx` และ `up` จะไม่ build frontend ใหม่ถ้ามี image อยู่แล้ว
  ```bash
  docker compose --env-file .env.production build frontend
  docker compose --env-file .env.production up -d frontend
  ```
- [ ] **7.7** กฎ Apache จากข้อ 0.1 เก็บไว้ได้ (กันซ้ำอีกชั้น)

### หลัง deploy

- [ ] **7.8** ทดสอบด้วยมือตามรายการใน Phase 6
- [ ] **7.9** `/api/mock-login/admin` → 404
- [ ] **7.10** ดู log ช่วงแรก: `docker compose --env-file .env.production logs -f laravel-app`

### Rollback

- ใช้ image ก่อนหน้า: `APP_TAG=previous docker compose --env-file .env.production up -d --wait`
- image เก่ายังไม่มี portal login จึงต้องคืนค่า `MOCK_LOGIN_ENABLED=true` (พร้อมกฎ Apache ข้อ 0.1) ด้วย ไม่งั้นจะไม่มีทาง login
- ตาราง `auth_sessions` เหลือไว้ได้ ไม่กระทบ image เก่า

---

## ยังไม่อยู่ในขอบเขต

- Login ฝั่งนิสิต (`dev:student` และ server ของนิสิต)
- ย้าย JWT จาก localStorage ไปเป็น HttpOnly cookie
