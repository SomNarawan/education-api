# Portal-Main Student API

API สำหรับให้ระบบ Portal-Main เรียกดูข้อมูลนิสิตจากระบบ Education API โดยอ้างอิงด้วย `nontriId`

## Base URL

```
http://localhost:3009/api/portal-main-student
```

## Authentication

ทุก endpoint ต้องแนบ API key ผ่าน header:

```
X-API-KEY: <PORTAL_MAIN_API_KEY>
```

ค่า `PORTAL_MAIN_API_KEY` กำหนดไว้ใน `.env` ของฝั่ง backend หากไม่แนบ หรือแนบผิด จะได้ `401 Unauthorized`:

```json
{ "message": "Invalid or missing API key" }
```

## หมายเหตุเรื่องรูปแบบ `nontriId`

`nontriId` อาจมีตัวอักษรนำหน้ารหัสนิสิต เช่น `b6020501361` ในขณะที่ในฐานข้อมูลเก็บ `student_code` เป็นตัวเลขล้วน (`6020501361`) ระบบจะตัดตัวอักษรนำหน้าออกก่อนค้นหาให้อัตโนมัติ และจะคืนค่า `nontriId` ตามรูปแบบที่ส่งเข้ามาในทุก endpoint ที่รับ `nontriId` เป็น input โดยตรง (`check-user`, `get-user-data-by-nontri`, `get-user-data-list-by-nontri`)

ส่วน endpoint ที่ค้นหาด้วยชื่อ/อีเมล/หน่วยงาน (`search-nontri-by-any`, `search-nontri`) จะคืนค่า `nontriId` เป็นรหัสตัวเลขล้วนตามที่เก็บในฐานข้อมูล เนื่องจากไม่มีตัวอักษรนำหน้าต้นทางให้อ้างอิง

---

## 1. ตรวจสอบว่ามีผู้ใช้อยู่หรือไม่

```
GET /check-user/{nontriId}
```

**ตัวอย่าง**

```bash
curl -H "X-API-KEY: <API_KEY>" \
  http://localhost:3009/api/portal-main-student/check-user/b6020501361
```

**Response 200**

```json
{
  "nontriId": "b6020501361",
  "exists": true
}
```

---

## 2. ดึงข้อมูลผู้ใช้ 1 คน ด้วย nontriId

```
GET /get-user-data-by-nontri/{nontriId}
```

**ตัวอย่าง**

```bash
curl -H "X-API-KEY: <API_KEY>" \
  http://localhost:3009/api/portal-main-student/get-user-data-by-nontri/b6020501361
```

**Response 200**

```json
{
  "nontriId": "b6020501361",
  "name": "นราวัลย์",
  "surname": "เอี่ยมสอาด",
  "kuMail": "xxxx.yyyy@ku.th",
  "agency": "วิศวกรรมคอมพิวเตอร์"
}
```

**Response 404** (ไม่พบผู้ใช้)

```json
{ "message": "User not found" }
```

---

## 3. ดึงข้อมูลผู้ใช้หลายคนพร้อมกัน

```
POST /get-user-data-list-by-nontri
Content-Type: application/json
```

**Request body**

| field       | type              | required | หมายเหตุ                         |
|-------------|-------------------|----------|-----------------------------------|
| `nontriIds` | `string[]`        | ✅       | อย่างน้อย 1 รายการ, แต่ละตัวยาวไม่เกิน 50 ตัวอักษร |

```json
{
  "nontriIds": ["b6020501361", "b6400000002", "unknown-id"]
}
```

**ตัวอย่าง**

```bash
curl -X POST \
  -H "X-API-KEY: <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"nontriIds": ["b6020501361", "b6400000002", "unknown-id"]}' \
  http://localhost:3009/api/portal-main-student/get-user-data-list-by-nontri
```

**Response 200**

คืนเฉพาะรายการที่พบ (id ที่ไม่พบจะถูกข้าม ไม่ error):

```json
[
  {
    "nontriId": "b6020501361",
    "name": "นราวัลย์",
    "surname": "เอี่ยมสอาด",
    "kuMail": "xxxx.yyyy@ku.th",
    "agency": "วิศวกรรมคอมพิวเตอร์"
  },
  {
    "nontriId": "b6400000002",
    "name": "สมหญิง",
    "surname": "ใจดี",
    "kuMail": "somying.j@ku.th",
    "agency": "ภาควิชาวิศวกรรมคอมพิวเตอร์"
  }
]
```

---

## 4. ค้นหา nontriId ด้วยคำค้นทั่วไป (ชื่อ/อีเมล/รหัส)

```
GET /search-nontri-by-any?search={keyword}
```

**Query params**

| param    | type   | required | หมายเหตุ                    |
|----------|--------|----------|-------------------------------|
| `search` | string | ✅       | 1–255 ตัวอักษร ค้นจากรหัสนิสิต, ชื่อ-นามสกุล (ไทย/อังกฤษ), อีเมล |

**ตัวอย่าง**

```bash
curl -H "X-API-KEY: <API_KEY>" \
  "http://localhost:3009/api/portal-main-student/search-nontri-by-any?search=%E0%B8%AA%E0%B8%A1%E0%B8%8A%E0%B8%B2%E0%B8%A2"
```

**Response 200**

```json
{ "nontriIds": ["6020501361"] }
```

---

## 5. ค้นหา nontriId ด้วยเงื่อนไขเฉพาะฟิลด์

```
GET /search-nontri?nontriId={..}&fullName={..}&agency={..}
```

**Query params** (ต้องส่งอย่างน้อย 1 ตัว)

| param      | type   | required | หมายเหตุ                              |
|------------|--------|----------|------------------------------------------|
| `nontriId` | string | อย่างน้อย 1 ใน 3 | ค้นแบบ partial match กับรหัสนิสิต        |
| `fullName` | string | อย่างน้อย 1 ใน 3 | ค้นแบบแยกคำ (token) กับชื่อ-นามสกุล ไทย/อังกฤษ |
| `agency`   | string | อย่างน้อย 1 ใน 3 | ค้นแบบ partial match กับชื่อหน่วยงาน (ไทย/อังกฤษ) |

ถ้าไม่ส่งครบทั้ง 3 ตัวเลย → `422 Unprocessable Entity`

**ตัวอย่าง**

```bash
curl -H "X-API-KEY: <API_KEY>" \
  "http://localhost:3009/api/portal-main-student/search-nontri?fullName=%E0%B8%AA%E0%B8%A1%E0%B8%8A%E0%B8%B2%E0%B8%A2&agency=%E0%B8%A7%E0%B8%B4%E0%B8%A8%E0%B8%A7%E0%B8%81%E0%B8%A3%E0%B8%A3%E0%B8%A1%E0%B8%84%E0%B8%AD%E0%B8%A1%E0%B8%9E%E0%B8%B4%E0%B8%A7%E0%B9%80%E0%B8%95%E0%B8%AD%E0%B8%A3%E0%B9%8C"
```

**Response 200**

```json
{ "nontriIds": ["6020501361"] }
```

---

## สรุปตาราง Endpoint

| # | Method | Path                                  | ใช้ทำอะไร                          |
|---|--------|----------------------------------------|-------------------------------------|
| 1 | GET    | `/check-user/{nontriId}`               | เช็คว่ามีผู้ใช้นี้อยู่ในระบบหรือไม่ |
| 2 | GET    | `/get-user-data-by-nontri/{nontriId}`  | ดึงข้อมูลผู้ใช้ 1 คน                |
| 3 | POST   | `/get-user-data-list-by-nontri`        | ดึงข้อมูลผู้ใช้หลายคนพร้อมกัน       |
| 4 | GET    | `/search-nontri-by-any?search=`        | ค้นหา nontriId ด้วยคำค้นทั่วไป      |
| 5 | GET    | `/search-nontri?nontriId=&fullName=&agency=` | ค้นหา nontriId ด้วยเงื่อนไขเฉพาะฟิลด์ |

ทุก endpoint ต้องแนบ header `X-API-KEY` มิเช่นนั้นจะได้ `401 Unauthorized`
