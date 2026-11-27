<?php

return [
    'internal_server' => 'เกิดข้อผิดพลาดภายในระบบ',

    'common' => [
        'actor_missing' => 'ไม่พบข้อมูลผู้ดำเนินการในโทเคน',
    ],

    'auth' => [
        'missing_bearer_token' => 'ไม่พบโทเคนสำหรับเข้าสู่ระบบ',
        'invalid_token' => 'โทเคนสำหรับเข้าสู่ระบบไม่ถูกต้องหรือหมดอายุ',
        'not_configured' => 'ระบบยืนยันตัวตนยังไม่ได้รับการตั้งค่า',
        'portal_session_ended' => 'เซสชันจากพอร์ทัลสิ้นสุดแล้ว กรุณาเข้าสู่ระบบจากพอร์ทัลอีกครั้ง',
        'portal_session_required' => 'โทเคนไม่ได้เชื่อมโยงกับเซสชันจากพอร์ทัล',
        'student_endpoint_forbidden' => 'นิสิตไม่มีสิทธิ์เรียกใช้งานส่วนนี้',
        'nontri_id_missing' => 'ไม่พบรหัสบัญชีนนทรีในโทเคน',
        'teacher_admin_only' => 'ส่วนนี้สำหรับอาจารย์และผู้ดูแลระบบเท่านั้น',
        'admin_student_create_options_only' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่โหลดตัวเลือกสำหรับเพิ่มนิสิตได้',
        'admin_reset_only' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถล้างผลการเรียนได้',
        'portal_token_invalid' => 'โทเคนจากพอร์ทัลไม่ถูกต้องหรือหมดอายุ กรุณาเข้าสู่ระบบจากพอร์ทัลอีกครั้ง',
        'portal_role_forbidden' => 'บัญชีนี้ไม่มีสิทธิ์ใช้งานระบบในบทบาทที่เลือก',
        'portal_token_used' => 'โทเคนจากพอร์ทัลหมดอายุหรือถูกใช้ไปแล้ว กรุณาเข้าสู่ระบบจากพอร์ทัลอีกครั้ง',
        'portal_unavailable' => 'ระบบกลางไม่ตอบสนอง กรุณาลองใหม่อีกครั้งภายหลัง',
    ],

    'master_data' => [
        'not_found' => [
            'title_name_th' => 'ไม่พบข้อมูลคำนำหน้าชื่อ',
            'channel_name' => 'ไม่พบข้อมูลช่องทางการรับเข้า',
            'relationship_name' => 'ไม่พบข้อมูลความสัมพันธ์',
            'status_name' => 'ไม่พบข้อมูลสถานะนิสิต',
            'note' => 'ไม่พบข้อมูลประเภทบันทึก',
        ],
    ],

    'student' => [
        'not_found' => 'ไม่พบข้อมูลนิสิต',
        'invalid_data' => 'ข้อมูลนิสิตไม่ถูกต้อง',
        'academic_overview_invalid' => 'ข้อมูลภาพรวมการศึกษาไม่ถูกต้อง',
        'unattempted_courses_invalid' => 'ข้อมูลรายวิชาที่ยังไม่ได้ลงทะเบียนไม่ถูกต้อง',
        'enrollment_invalid' => 'ข้อมูลการลงทะเบียนไม่ถูกต้อง',
        'enrollment_not_found' => 'ไม่พบข้อมูลการลงทะเบียน',
        'enrollment_status_not_found' => 'ไม่พบข้อมูลสถานะการลงทะเบียน',
        'graph_not_found' => 'ไม่พบข้อมูลกราฟ',
        'json_invalid' => 'ข้อมูล JSON ไม่ถูกต้อง',
        'grade_data_not_found' => 'ไม่พบข้อมูลผลการเรียนของนิสิต',
        'previous_semester_only' => 'สามารถล้างผลการเรียนได้เฉพาะภาคการศึกษาก่อนหน้าภาคการศึกษาปัจจุบันเท่านั้น',
        'previous_semester_required' => 'ชั้นปีและภาคการศึกษาที่เลือกต้องอยู่ก่อนภาคการศึกษาปัจจุบัน',
    ],

    'validation' => [
        'study_plan_invalid' => ':attribute ที่เลือกไม่ถูกต้อง',
        'search_criteria_required' => 'กรุณาระบุรหัสบัญชีนนทรี ชื่อ-นามสกุล หรือหน่วยงานอย่างน้อยหนึ่งรายการ',
        'note_detail_required' => 'กรุณากรอกรายละเอียด',
        'search_note_detail_required' => 'กรุณากรอกรายละเอียดบันทึก',
        'advisor_change_required' => 'กรุณาเลือกนิสิตที่จะเพิ่มหรือนำอาจารย์ที่ปรึกษาออกอย่างน้อยหนึ่งคน',
        'advisor_action_conflict' => 'ไม่สามารถเพิ่มและนำอาจารย์ที่ปรึกษาออกจากนิสิตคนเดียวกันพร้อมกันได้',
        'advisor_wrong_study_plan' => 'นิสิตไม่ได้อยู่ในแผนการเรียนที่เลือก: :ids',
        'advisor_already_assigned' => 'นิสิตมีอาจารย์ที่ปรึกษาคนอื่นอยู่แล้ว: :ids',
        'entry_year_calendar_mismatch' => 'ปีการศึกษาที่เข้าศึกษาไม่สอดคล้องกับปฏิทินการศึกษาปัจจุบัน',
    ],

    'note' => [
        'not_found' => 'ไม่พบข้อมูลบันทึก',
    ],

    'high_school' => [
        'not_found' => 'ไม่พบข้อมูลโรงเรียน',
    ],

    'import' => [
        'not_found' => 'ไม่พบรายการนำเข้าข้อมูล',
        'result_not_found' => 'ไม่พบไฟล์ผลลัพธ์การนำเข้าข้อมูล',
        'grade_template_not_found' => 'ไม่พบไฟล์แม่แบบนำเข้าผลการเรียน',
        'grade_type_missing' => 'ไม่พบประเภทการนำเข้าผลการเรียนที่เปิดใช้งาน',
        'grade_file_store_failed' => 'ไม่สามารถจัดเก็บไฟล์นำเข้าผลการเรียนได้',
        'student_type_missing' => 'ไม่พบประเภทการนำเข้าข้อมูลนิสิตที่เปิดใช้งาน',
        'student_template_create_failed' => 'ไม่สามารถสร้างไฟล์แม่แบบนำเข้าข้อมูลนิสิตได้',
        'student_data_missing' => 'ไม่มีข้อมูลสำหรับนำเข้า',
        'student_file_unreadable' => 'ไม่สามารถอ่านไฟล์ Excel ได้: :error',
        'student_sheet_missing' => 'ไม่พบชีต :sheet ในไฟล์ Excel',
        'student_header_missing' => 'ไม่พบหัวตารางที่แถว 1-2 ในชีต :sheet',
        'student_header_invalid' => 'รูปแบบหัวตารางไม่ตรงกับแม่แบบนำเข้าข้อมูลนิสิต',
        'student_row_save_failed' => 'ไม่สามารถบันทึกข้อมูลลงฐานข้อมูลได้',
        'student_reference_missing' => 'ไม่พบ:label ":value" ในระบบ',
        'student_result_create_failed' => 'ไม่สามารถสร้างไฟล์ผลลัพธ์การนำเข้าได้',
        'student_row_failed' => 'แถว :row: :errors',
        'student_failed' => 'ไม่สามารถนำเข้าข้อมูลได้',
    ],

    'grade_calculation' => [
        'student_only' => 'เฉพาะนิสิตเท่านั้นที่สามารถบันทึกการคำนวณเกรดได้',
        'role_forbidden' => 'บทบาทนี้ไม่มีสิทธิ์ใช้งานการคำนวณเกรด',
        'student_required' => 'กรุณาระบุข้อมูลนิสิต',
    ],

    'sync' => [
        'master_data_failed' => 'ไม่สามารถซิงก์ข้อมูลพื้นฐานได้',
        'academic_calendar_failed' => 'ไม่สามารถซิงก์ปฏิทินการศึกษาได้',
        'student_academic_period_failed' => 'ไม่สามารถซิงก์ชั้นปีและภาคการศึกษาของนิสิตได้',
    ],

    'mock_login' => [
        'user_not_found' => 'ไม่พบผู้ใช้จำลองรหัส :nontri_id ในไฟล์ resources/mocks/mock-login.json',
        'student_not_found' => 'ไม่พบนิสิตรหัส :student_code ในตาราง students',
        'file_unreadable' => 'ไม่สามารถอ่านไฟล์ resources/mocks/mock-login.json ได้',
        'file_invalid' => 'รูปแบบไฟล์ resources/mocks/mock-login.json ไม่ถูกต้อง',
    ],
];
