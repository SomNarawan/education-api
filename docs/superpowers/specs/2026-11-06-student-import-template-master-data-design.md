# Student Import Template Master Data Design

## Intent

ลดความผิดพลาดจากการพิมพ์ข้อมูลอ้างอิงในไฟล์นำเข้านิสิต โดยให้ template แสดงตัวเลือกชุดเดียวกับ popup เพิ่มนิสิต และให้รายชื่ออาจารย์ที่ปรึกษาตรงกับหลักสูตรที่ผู้ใช้เลือก

## Goals

- ผู้ใช้ต้องเลือกหลักสูตรก่อนดาวน์โหลด Student Import Template
- Template มีชีต `Master Data` ที่สร้างจากข้อมูลปัจจุบันของระบบ
- `Sheet1` มี dropdown สำหรับคำนำหน้า อาจารย์ที่ปรึกษา ช่องทางรับเข้า โรงเรียน ความสัมพันธ์ และสถานภาพนิสิต
- อาจารย์ที่ปรึกษาใน popup และการ Import เป็นข้อมูลไม่บังคับ
- การ Import ยังคงยอมรับชื่อหรือรหัสจาก master data ตามพฤติกรรมเดิม

## Non-goals

- ไม่ย้ายภาควิชา หลักสูตร หรือแผนการเรียนลงไปเป็นคอลัมน์ใน Excel เพราะข้อมูลเหล่านี้ถูกเลือกหนึ่งครั้งบนหน้า Import และใช้ร่วมกันทุกแถว
- ไม่เปลี่ยนโครงสร้างคอลัมน์หรือ header อื่นของไฟล์
- ไม่เปลี่ยนกฎการบังคับกรอกข้อมูลอื่น

## Constraints

- `SimpleXLSXGen` สร้างหลายชีตได้ แต่ไม่มี API สำหรับ Excel Data Validation จึงต้องเติม named ranges และ data validations ลงใน XML ภายใน `.xlsx` หลังสร้างไฟล์
- รายชื่ออาจารย์มาจาก CMIS `curriculum-personnel` และต้องมี `curriculum_id`
- Dropdown จะรองรับแถวข้อมูล 3-1002 ใน `Sheet1`

## Approaches

1. สร้าง template ตามหลักสูตรที่เลือก: FE ส่ง `curriculum_id` ไปยัง endpoint ดาวน์โหลด, BE โหลดข้อมูลอ้างอิงและอาจารย์ของหลักสูตร แล้วสร้าง Master Data/dropdown แบบไดนามิก วิธีนี้ถูกเลือกเพราะข้อมูลตรงกับ popup และไม่แสดงอาจารย์ต่างหลักสูตร
2. สร้าง template กลางโดยไม่รับหลักสูตร: ทำ dropdown ได้เฉพาะข้อมูลกลาง และไม่สามารถทำรายการอาจารย์ที่ถูกต้องได้ จึงไม่เลือก

## Design

- FE บังคับ validate ช่องหลักสูตรก่อนดาวน์โหลด และเรียก `GET /students/import/template?curriculum_id={id}`
- BE validate `curriculum_id` และส่งเข้า `StudentImportService::createTemplate()`
- ชีต `Master Data` มีรายการจาก `titles`, `curriculum-personnel`, `admission-channels`, `high-schools`, `relationships` และ `student-statuses`
- `Sheet1` ผูก dropdown ที่ C, K, L, M, N, Q และ S โดย C/N ใช้รายการคำนำหน้าร่วมกัน
- ถ้าหลักสูตรไม่มีอาจารย์ dropdown คอลัมน์ K จะไม่มีรายการ แต่ผู้ใช้ยังเว้นว่างได้
- ถ้าแถว Import ไม่ระบุอาจารย์ ให้ `teacher_id` และ `teacher_full_name` เป็น `null`; ถ้าระบุให้ resolve และ validate ด้วย master data ตามเดิม
- เพิ่ม automated tests ครอบคลุม request validation, โครงสร้าง workbook/dropdown และการ Import ที่ไม่ระบุอาจารย์
