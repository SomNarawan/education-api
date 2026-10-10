# Education API

REST API backend (Laravel 12) สำหรับ **ระบบฐานข้อมูลนิสิต** ใช้คู่กับ Frontend `education-web` (React) โปรเจกต์นี้ทำหน้าที่เป็นศูนย์กลางข้อมูลนิสิต หลักสูตร อาจารย์ที่ปรึกษา และเชื่อมต่อ/ประสานข้อมูลกับระบบภายนอกของมหาวิทยาลัย (ระบบบุคลากร, ระบบทะเบียน)

## Project Description

ระบบนี้เก็บและให้บริการข้อมูลของนิสิต (ประวัติ, สถานะการศึกษา, อาจารย์ที่ปรึกษา, แผนการเรียน/หลักสูตร) โดยมีจุดสำคัญคือ

- **ไม่ได้เป็นเจ้าของฐานข้อมูลหลักเพียงผู้เดียว** — ตาราง Eloquent เช่น `students`, `system_departments` และ master data ชี้ไปยังฐานข้อมูล MySQL ที่มีอยู่แล้ว (`education_dss`) ซึ่งถูกสร้าง/ดูแลจากภายนอกโปรเจกต์ Laravel นี้ (ดูหัวข้อ [Database Setup](#database-setup))
- **Authentication เป็นแบบ JWT ที่ออกโดยระบบอื่น** (SSO/ระบบบุคลากรมหาวิทยาลัย) — API นี้แค่ตรวจสอบลายเซ็นของ token ที่ส่งเข้ามา ไม่มีหน้า login เป็นของตัวเอง
- **Sync ข้อมูลจากระบบภายนอก** — คณะ/ภาควิชา (System Faculty/Department) ถูกดึงจาก Personnel API ผ่าน endpoint `*/sync` ส่วนรายชื่ออาจารย์อ่านจาก CMIS API โดยไม่เก็บตารางหรือประวัติ sync ในฐานข้อมูลนี้
- **ข้อมูลผลการเรียน/การลงทะเบียน** (enrollments, เกรด, กราฟสรุปผล) ไม่ได้เก็บใน DB แต่อ่านจากไฟล์ JSON ที่วางไว้ใน storage ของแอป (ดูหัวข้อ [Usage](#usage))

## Features

### จัดการข้อมูลนิสิต (Students)
- ค้นหา/กรองรายชื่อนิสิตตามอาจารย์ที่ปรึกษา, ภาควิชา, คณะ, สถานะการศึกษา, ชื่อ, หรือข้อความในบันทึก (`GET /api/students`)
- ดูรายชื่อ "นิสิตที่กำลังศึกษาอยู่" ของอาจารย์คนหนึ่ง (`GET /api/students/studying`)
- ดูรายชื่อ "นิสิตที่ยังไม่มีอาจารย์ที่ปรึกษา" แยกตามภาควิชา (`GET /api/students/studying/without-advisor`)
- มอบหมาย/ถอดอาจารย์ที่ปรึกษาให้นิสิตหลายคนพร้อมกันแบบ transaction ป้องกันการชนกันของอาจารย์ (`PATCH /api/students/advisor`)
- เพิ่ม/แก้ไข/ลบ (soft delete) ข้อมูลนิสิตรายคน พร้อมคำนวณชั้นปี/ภาคการศึกษาปัจจุบัน โดยระบุภาควิชาผ่าน `system_department_id` (`App\Actions\Students\SaveStudent`, `AcademicStandingCalculator`)
- บันทึกช่วยจำ (Notes) แนบกับนิสิตแต่ละคน พร้อมประเภทบันทึก (`/api/notes`)

### ข้อมูลผลการเรียน/การลงทะเบียน (จากไฟล์ JSON)
- ดึงข้อมูลการลงทะเบียนเรียนของนิสิตรายคน (`/api/students/{studentCode}/enrollments`)
- ดึงสถานะรายวิชา: ผ่าน/ไม่ผ่าน/เกินแผน (`/api/students/{studentCode}/enrollment-statuses`)
- ดึงข้อมูลสรุปผลการเรียนสำหรับทำกราฟ/แดชบอร์ด แยกตามหน่วยกิต, กลุ่มวิชา, ภาคการศึกษา (`/api/students/{studentCode}/performance-summary`)

### อาจารย์ / โครงสร้างองค์กร
- รายชื่ออาจารย์ที่ปรึกษาอ่านจาก CMIS API ผ่าน `GET /api/list-of-values/curriculum-personnel`
- คณะ/ภาควิชา (ระบบ) พร้อม sync จาก Personnel API (`/api/system-faculties`, `/api/system-departments` และ `*/sync`)

### ข้อมูลอ้างอิง (Master/Reference data)
คำนำหน้าชื่อ, ช่องทางการรับเข้า, โรงเรียนเดิม, ความสัมพันธ์ผู้ปกครอง, สถานะการศึกษา และประเภทบันทึกมี endpoint ของระบบ ส่วนหลักสูตร แผนการเรียน และบุคลากรหลักสูตรอ่านจาก CMIS API ผ่าน `GET /api/list-of-values/{type}`

### Sync log
- ดูประวัติ/สถานะการ sync ล่าสุดของแต่ละประเภท (`GET /api/syncs`)

### ข้อมูลหลักสูตรจากระบบภายนอก
ข้อมูลหลักสูตร รายวิชา และวิทยาเขตถูกอ่านจาก CMIS API ภายนอก ส่วน `Province`, `District` และ `Subdistrict` ใช้เป็นข้อมูลที่อยู่ของโรงเรียนในฐานข้อมูลนี้

## Technologies

| ประเภท | เทคโนโลยี |
|---|---|
| Backend Framework | Laravel 12 (PHP ^8.2) |
| Database | MySQL (เชื่อมต่อฐานข้อมูล `education_dss` ที่มีอยู่แล้ว) |
| Authentication | Custom JWT middleware (`App\Http\Middleware\AuthenticateJwt` + `App\Services\JwtVerifier`, รองรับ HS256/384/512) — token ออกโดยระบบภายนอก |
| ORM | Eloquent |
| Frontend build (asset ของ Laravel เอง) | Vite 7, TailwindCSS 4 |
| Testing | PHPUnit 11 |
| Code style | Laravel Pint |
| External integration | CMIS API และ Personnel API ของมหาวิทยาลัย |

> หมายเหตุ: โปรเจกต์ยังมี `laravel/sanctum` ติดตั้งอยู่ (มาจาก Laravel skeleton เริ่มต้น) แต่ API ปัจจุบัน**ไม่ได้ใช้ Sanctum ในการยืนยันตัวตน** — ใช้ JWT middleware ที่เขียนเองแทนทั้งหมด

## Requirements

- PHP >= 8.2 พร้อม extension ที่ Laravel 12 ต้องการ (pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath)
- Composer 2.x
- Node.js + npm (สำหรับ build asset ของ Laravel เอง เช่นหน้า welcome/Vite)
- MySQL 8.x / MariaDB ที่เข้าถึงฐานข้อมูล `education_dss` ได้ (ต้องขอไฟล์ dump หรือสิทธิ์เข้าถึงจากทีม)
- ไฟล์ `.env` ที่มีค่าเชื่อมต่อจริง (ฐานข้อมูล, `JWT_SECRET`, `PERSONNEL_API_URL`) — ขอจากทีม/หัวหน้างาน

## Installation

เปิด MySQL/MariaDB ในเครื่องก่อนเริ่ม API (เช่นผ่าน XAMPP บน Windows หรือ
`brew services start mysql` บน macOS) แล้วทำตามขั้นตอนต่อไปนี้:

```bash
# 1. Clone และเข้าโฟลเดอร์โปรเจกต์
git clone <repo-url> education-api
cd education-api

# 2. ติดตั้ง PHP dependencies
composer install

# 3. เตรียมไฟล์ .env
cp .env.example .env
# ตั้งค่า DB_*, JWT_SECRET, CMIS_URL และ PERSONNEL_API_URL ให้ตรงกับ environment
php artisan key:generate   # ข้ามได้ถ้าใช้ .env ที่มี APP_KEY อยู่แล้ว

# 4. ติดตั้ง frontend asset ของ Laravel เอง (Vite/Tailwind)
npm install

# 5. รันเซิร์ฟเวอร์ (server + queue + log + vite พร้อมกัน)
composer run dev
# หรือรันแยกเอง
php artisan serve
```

API จะพร้อมใช้งานที่ `http://localhost:8000/api` (หรือ URL ตาม `APP_URL`/`php artisan serve`)

> อย่ารัน `php artisan migrate` กับฐานข้อมูล `education_dss` ที่ใช้งานร่วมกันโดยอัตโนมัติ
> ให้ import dump/ใช้ schema ที่ทีมจัดเตรียม และรัน migration เฉพาะเมื่อผู้ดูแลฐานข้อมูลยืนยันแล้ว

## Docker Compose

`docker-compose.yml` ตั้งค่า default ให้เหมาะกับ local development เพื่อให้รัน `docker compose up --build` ได้ทันที:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3001,http://localhost:3002
MOCK_LOGIN_FRONTEND_URL=http://localhost:3001
VITE_API_URL=http://localhost:8000/api
```

ถ้ารันผ่าน Docker Compose ในเครื่อง ค่า URL จะต้องตรงกับ port mapping ของ compose:

```env
APP_URL=http://localhost:3009
FRONTEND_URL=http://localhost:3008
MOCK_LOGIN_FRONTEND_URL=http://localhost:3008
VITE_API_URL=http://localhost:3009/api
MOCK_LOGIN_ENABLED=true
```

`FRONTEND_URL` รองรับหลาย origin คั่นด้วย comma สำหรับ CORS ส่วน `MOCK_LOGIN_FRONTEND_URL`
กำหนด URL เดียวที่ backend จะ redirect กลับหลัง Mock Login

เมื่อต้อง deploy บน server ให้ใช้ไฟล์ env production แทน default ของ compose:

```bash
cp .env.production.example .env.production
# ใส่ APP_KEY, JWT_SECRET, DB_PASSWORD, PORTAL_MAIN_API_KEY และค่า production อื่น ๆ ให้ครบ
# PORTAL_MAIN_API_KEY สร้างด้วย: openssl rand -hex 32
docker compose --env-file .env.production up -d --build
```

ค่าที่ต้องเป็น URL จริงบน server:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd-api
FRONTEND_URL=https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd
MOCK_LOGIN_FRONTEND_URL=https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd
VITE_API_URL=https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd-api/api
```

สรุปคือ `FRONTEND_URL` ใช้กำหนด CORS, `MOCK_LOGIN_FRONTEND_URL` ใช้กำหนดปลายทาง
redirect ของ Mock Login และ `VITE_API_URL` ถูกฝังตอน build frontend เวลาเปลี่ยนจาก local
เป็น server ต้องตั้งค่าทั้งหมดให้ตรงกับ URL จริงและ rebuild frontend container ใหม่

## Database Setup

**ข้อควรระวัง:** โฟลเดอร์ `database/migrations` มีเฉพาะ migration เริ่มต้นของ Laravel และ **ไม่มี migration ของตารางข้อมูลหลัก** เช่น `students` และ `system_departments`

ตารางเหล่านี้ Eloquent Model อ้างอิงถึงโดยตรง (ผ่าน `$table`) และคาดว่ามีอยู่แล้วในฐานข้อมูล MySQL ชื่อ `education_dss` ซึ่งน่าจะถูกดูแล/สร้างขึ้นจากระบบอื่น (เช่นระบบทะเบียนของมหาวิทยาลัย) ดังนั้นขั้นตอนเตรียมฐานข้อมูลคือ

1. ขอไฟล์ dump ฐานข้อมูล `education_dss` (หรือสิทธิ์เข้าถึง MySQL instance ที่มีอยู่แล้ว) จากทีม
2. Import เข้าฐานข้อมูล MySQL ในเครื่อง แล้วตั้งค่า `DB_DATABASE=education_dss` ใน `.env` ให้ตรงกับชื่อฐานข้อมูลนั้น
3. ไม่ต้องรัน `php artisan migrate` กับฐานข้อมูลที่ใช้งานร่วมกัน เว้นแต่ต้องการเพิ่มตารางระบบของ Laravel และได้รับการยืนยันจากผู้ดูแลฐานข้อมูลแล้ว

ตารางหลักที่ระบบคาดหวังว่ามีอยู่แล้ว (ดูรายละเอียดคอลัมน์ใน [ER Diagram](#er-diagram)):

`students`, `titles`, `student_statuses`, `admission_channels`, `high_schools`, `relationships`, `notes`, `note_types`, `imports`, `import_types`, `system_faculties`, `system_departments`, `sync_types`, `syncs`, `provinces`, `districts`, `subdistricts`

> การเพิ่มนิสิตต้องส่ง `system_department_id` ที่เปิดใช้งานจาก `system_departments` มาใน request โดยตรง ระบบไม่ resolve ภาควิชาจากแผนการเรียน

## Usage

### Authentication

API ทั้งหมด (ยกเว้น `/up` health check) ถูกครอบด้วย JWT middleware (`bootstrap/app.php` → `AuthenticateJwt` แปะเข้า group `api`) ต้องแนบ token แบบ Bearer ทุกครั้ง:

```
Authorization: Bearer <jwt-token>
```

- Token ต้องเซ็นด้วย secret เดียวกับ `JWT_SECRET` ใน `.env` (algorithm ตาม `JWT_ALGORITHM`, ค่าเริ่มต้น `HS256`)
- ต้องมี claim `exp` เสมอถ้า `JWT_REQUIRE_EXPIRATION=true`
- Claims ที่ระบบใช้งาน: `nontri_id`, `name`, `role`, `current_role`, `department_id`, `iat`, `exp`
- เรียก `GET /api/me` เพื่อตรวจสอบ token และอ่านข้อมูลผู้ใช้จาก JWT claims เช่น `nontri_id`, `department_id` และ `faculty_id`

ตัวอย่างการเรียก:

```bash
curl http://localhost:8000/api/me \
  -H "Authorization: Bearer <jwt-token>"

curl "http://localhost:8000/api/students?teacher_id=1" \
  -H "Authorization: Bearer <jwt-token>"
```

รูปแบบ Response มาตรฐาน (ดู `App\Helpers\ApiResponse`):

```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

### ข้อมูลผลการเรียน (JSON files)

Endpoint กลุ่ม `/api/students/{studentCode}/enrollments|enrollment-statuses|performance-summary` อ่านไฟล์ JSON จาก local disk (`storage/app/private/`) โดยตรง ต้องวางไฟล์ตามโครงสร้าง:

```
storage/app/private/data/
├── enrollments/{studentCode}.json
├── enrollments_pass/{studentCode}.json
├── enrollments_not_pass/{studentCode}.json
├── enrollments_over/{studentCode}.json
└── graph/
    ├── by_credit/{studentCode}.json
    ├── by_semester/{studentCode}.json
    └── by_group/{studentCode}_1.json, _2.json, _3.json
```

ไฟล์เหล่านี้คาดว่าจะมาจากขั้นตอน sync เกรด (ดูงาน "ดูระบบ sync เกรด" ในแผนงาน) — ถ้าไม่มีไฟล์ endpoint จะตอบ 404 หรือระบุ `missing_sections`

ไฟล์ใต้ `storage/app/private/` เป็น runtime data ที่อาจมีข้อมูลส่วนบุคคล จึงถูก
`.gitignore` ไว้ทั้งหมดและต้องไม่ commit ลง Git หากต้องย้ายข้อมูลระหว่างเครื่องให้ใช้
ช่องทางที่ทีมอนุมัติ โดยเก็บไฟล์ไว้ใน path เดิมบนแต่ละ environment

### Sync ข้อมูลจากภายนอก

```bash
curl -X POST http://localhost:8000/api/system-departments/sync -H "Authorization: Bearer <jwt-token>"
curl -X POST http://localhost:8000/api/system-faculties/sync -H "Authorization: Bearer <jwt-token>"
```

ต้องตั้งค่า `PERSONNEL_API_URL` ใน `.env` ให้ชี้ไปยัง Personnel API ของมหาวิทยาลัย (base path ปัจจุบัน hardcode เป็น `/kukps-eng-personnel-api/api/portal-student-map` ใน `config/services.php`)

### ตรวจสอบรายการ API

ดู route ที่เปิดใช้งานจริงจาก source ได้ด้วยคำสั่ง:

```bash
php artisan route:list --path=api --except-vendor
```

รายละเอียด integration สำหรับ Portal Main อยู่ที่
[`docs/portal-main-student-api.md`](docs/portal-main-student-api.md)

### Testing

```bash
composer test
# หรือ
php artisan test
```

## Project Structure

```
app/
├── Actions/Students/          # Use-case ที่มี business logic ซับซ้อน (สร้าง/แก้ไขนิสิต, มอบหมายอาจารย์ที่ปรึกษา)
├── Helpers/ApiResponse.php    # รูปแบบ JSON response มาตรฐาน (success/error)
├── Http/
│   ├── Controllers/Api/       # Controller ของทุก endpoint (ผูก 1 controller ต่อ 1 resource)
│   ├── Middleware/            # AuthenticateJwt.php — ตรวจสอบ JWT ทุก request
│   ├── Requests/               # Form Request สำหรับ validate input (Student, Note)
│   └── Responses/             # API Resource สำหรับ format ข้อมูล output
├── Models/                    # Eloquent model ของทุกตาราง (ส่วนใหญ่ชี้ไปยัง DB ภายนอกที่มีอยู่แล้ว)
├── Providers/
└── Services/
    ├── JwtVerifier.php            # ตรวจลายเซ็น/claims ของ JWT
    ├── PersonnelApiService.php    # เรียก Personnel API ของมหาวิทยาลัย
    └── Students/                  # StudentQueryService (filter/search), AcademicStandingCalculator

routes/
├── api.php       # endpoint หลักทั้งหมด (ครอบด้วย JWT middleware)
├── web.php       # หน้า welcome เริ่มต้นของ Laravel เท่านั้น
└── console.php

database/
├── migrations/   # เฉพาะตารางระบบของ Laravel (users, cache, jobs, personal_access_tokens)
├── factories/
└── seeders/

config/
├── jwt.php       # การตั้งค่า JWT (secret, algorithm, issuer, audience, leeway)
└── services.php  # การตั้งค่า Personnel API

storage/app/private/
├── data/         # JSON ผลการเรียน/การลงทะเบียนและไฟล์สถานะการประมวลผล
└── imports/      # ไฟล์นำเข้าและผลลัพธ์ที่สร้างระหว่างทำงาน
```

ทั้ง `data/` และ `imports/` เป็น local/runtime data ซึ่งไม่ถูก track โดย Git

## ER Diagram

แผนภาพนี้ครอบคลุมความสัมพันธ์หลักตามที่นิยามไว้ใน Eloquent Model (`app/Models/*.php`) ตารางทั้งหมดอยู่ในฐานข้อมูล `education_dss` ภายนอกโปรเจกต์ (ดู [Database Setup](#database-setup))

```mermaid
erDiagram
    STUDENTS {
        int id PK
        string student_code UK
        string teacher_id "external CMIS id"
        int curriculum_id "external CMIS id"
        int study_plan_id "external CMIS id"
        int title_id FK
        int student_status_id FK
        int admission_channel_id FK
        int high_school_id FK
        int system_department_id FK
        int guardian_title_id FK
        int guardian_relationship_id FK
    }
    TITLES {
        int id PK
    }
    STUDENT_STATUSES {
        int id PK
    }
    ADMISSION_CHANNELS {
        int id PK
    }
    RELATIONSHIPS {
        int id PK
    }
    NOTES {
        int id PK
        int student_id "indexed; no DB FK"
        int note_type_id FK
    }
    NOTE_TYPES {
        int id PK
    }
    HIGH_SCHOOLS {
        int id PK
        int subdistrict_id FK
    }
    PROVINCES {
        int id PK
    }
    DISTRICTS {
        int id PK
        int province_id FK
    }
    SUBDISTRICTS {
        int id PK
        int district_id FK
    }
    IMPORT_TYPES {
        int id PK
    }
    IMPORTS {
        int id PK
        int import_type_id FK
    }
    SYSTEM_FACULTIES {
        int id PK
    }
    SYSTEM_DEPARTMENTS {
        int id PK
        int system_faculty_id FK
    }
    SYNC_TYPES {
        int id PK
    }
    SYNCS {
        int id PK
        int sync_type FK
    }

    TITLES ||--o{ STUDENTS : title
    TITLES ||--o{ STUDENTS : guardian_title
    STUDENT_STATUSES ||--o{ STUDENTS : status
    ADMISSION_CHANNELS ||--o{ STUDENTS : admission_channel
    RELATIONSHIPS ||--o{ STUDENTS : guardian_relationship
    HIGH_SCHOOLS ||--o{ STUDENTS : high_school
    SYSTEM_DEPARTMENTS ||--o{ STUDENTS : department
    STUDENTS ||--o{ NOTES : logical_notes
    NOTE_TYPES ||--o{ NOTES : categorizes
    PROVINCES ||--o{ DISTRICTS : has
    DISTRICTS ||--o{ SUBDISTRICTS : has
    SUBDISTRICTS ||--o{ HIGH_SCHOOLS : located_in
    IMPORT_TYPES ||--o{ IMPORTS : categorizes
    SYSTEM_FACULTIES ||--o{ SYSTEM_DEPARTMENTS : has
    SYNC_TYPES ||--o{ SYNCS : run_history
```

> หมายเหตุ: `curriculum_id`, `study_plan_id` และ `teacher_id` ใน `students` เป็นรหัสจากระบบภายนอก จึงไม่มีตารางปลายทางหรือ foreign key ภายในฐานข้อมูลนี้
