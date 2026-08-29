<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Mock Login</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; color: #222; }
        h1 { font-size: 20px; }
        .banner { background: #fff3cd; border: 1px solid #ffe69c; padding: 10px 14px; border-radius: 6px; font-size: 14px; margin-bottom: 24px; }
        input { width: 100%; padding: 8px 10px; font-size: 14px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
        ul { list-style: none; padding: 0; margin: 12px 0 0; }
        li a { display: block; padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 6px; text-decoration: none; color: #222; }
        li a:hover { background: #f5f5f5; }
        .muted { color: #777; font-size: 12px; }
        .admin-btn { display: inline-block; margin-bottom: 24px; padding: 8px 14px; background: #222; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Mock Login (ใช้ทดสอบเท่านั้น)</h1>
    <div class="banner">Endpoint นี้ออก JWT เองโดยไม่ผ่าน SSO จริง — ใช้เฉพาะ local/staging และต้องลบ/ปิดก่อนต่อ SSO จริง</div>

    <a class="admin-btn" href="/api/mock-login/admin">Login as Admin</a>

    <div>
        <input id="q" type="text" placeholder="ค้นหาอาจารย์ด้วยชื่อหรือ nontri_id (เช่น fengame)">
        <ul id="results"></ul>
        <div class="muted">พิมพ์เพื่อค้นหา แล้วคลิกชื่อเพื่อ login เป็นอาจารย์คนนั้น (role: teacher)</div>
    </div>

    <script>
        const input = document.getElementById('q');
        const results = document.getElementById('results');

        async function search(q) {
            const res = await fetch('/api/mock-login/search?q=' + encodeURIComponent(q));
            const systemTeachers = await res.json();

            results.innerHTML = systemTeachers.map(systemTeacher =>
                `<li><a href="/api/mock-login/system-teacher/${encodeURIComponent(systemTeacher.nontri_id)}">${systemTeacher.full_name_th} <span class="muted">(${systemTeacher.nontri_id}${systemTeacher.is_admin ? ' — admin+teacher' : ''})</span></a></li>`
            ).join('');
        }

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => search(input.value.trim()), 200);
        });

        search('');
    </script>
</body>
</html>
