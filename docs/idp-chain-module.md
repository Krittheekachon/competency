# ลำดับการประเมิน & IDP — UI prototype

โมดูลนี้ใช้ข้อมูลจำลองในหน่วยความจำเท่านั้น รีโหลดหน้าแล้วกลับเป็นข้อมูลเริ่มต้น ไม่มีการเรียก API ข้อมูลหรือเขียนข้อมูลจริง

## โครงไฟล์

- `resources/js/Pages/Admin/IdpChainModule.vue`: tabs, Preset cards, member modal, assignment drawer 3 ขั้น, client filtering/focus trap/toast
- `resources/js/services/idpChainService.js`: mock users 30 คน, units 3 ชั้น, presets eval/idp อย่างละ 2 และการคำนวณเป้าหมาย
- `resources/js/Pages/Admin/ReviewerPresetFields.vue`: template ฟอร์มเดิมที่แยกมาใช้ร่วมกับ modal เดิมใน Dashboard; handlers เดิมของระบบจริงยังอยู่ที่ Dashboard
- `resources/js/Pages/Admin/Dashboard.vue`, `resources/js/data.ts`: ใช้ shell เดิม เพิ่มเมนูและลิงก์จาก modal เดิม
- `routes/web.php`: เพิ่มเพียง GET `/admin/idp-chains?type=eval|idp` ชื่อ `admin.idp-chains` ใน middleware auth/active/role:admin เดียวกับจัดการผู้ใช้ ไม่โหลดข้อมูลโครงสร้างจริง

## Service contract

ทุกฟังก์ชันเป็น async: `listPresets(type)`, `savePreset(payload)`, `listUsers()`, `listUnits()`, `previewUnitTargets(unitId, includeChildren)`, `applyPreset(payload)`.

```js
{
  type: 'eval', // 'eval' | 'idp'
  presetId: 2,
  mode: 'users', // 'users' | 'unit'
  userIds: [1, 4], // เมื่อ mode=users
  // unitId: 1, includeChildren: true, // เมื่อ mode=unit
  existingChain: 'keep' // 'keep' | 'override'
}
```

`applyPreset` log payload และคืน `{ added, replaced, skipped }`; UI แสดง toast “บันทึก (ทดสอบ)” และอ่านจำนวน mock ใหม่ ไม่มี network request

`previewUnitTargets` คืน `{ users, childUnits }` ซึ่งรวมลูกทุกชั้นเมื่อเปิด includeChildren และไม่ซ้ำผู้ใช้

`savePreset` ใช้ `{ id?, type, name, description, reviewerIds, userIds? }`; ทำสำเนาโดยไม่ส่ง id; ลบโดย `{ id, type, remove: true }` จะล้างเฉพาะการอ้างอิง mock ของประเภทนั้น ระบบกำหนดแก้/ลบไม่ได้

## การต่อ backend ภายหลัง

เปลี่ยนเฉพาะ service adapter เป็น API ที่ได้รับอนุมัติภายหลัง จัด mapping eval กับ chain_type=assessment และ idp กับ chain_type=idp ให้ชัดเจน ฝั่ง server ต้องคำนวณกลุ่มเป้าหมาย/สิทธิ์/การข้ามสายเดิมซ้ำและใช้ transaction ห้ามถือว่าตัวเลข preview ฝั่ง client เป็นอำนาจอนุมัติจริง และต้องใช้ ReviewerChainResolver/user_reviewer_steps ตามกติกาโครงการ

ยังไม่ได้เพิ่ม endpoint ข้อมูล, controller, schema, migration หรือแก้ runtime reviewer permissions ใด ๆ

## ตรวจอัตโนมัติ

ใช้ runner ที่มากับ Node โดยไม่ติดตั้ง dependency: `node --test tests/frontend/idpChainService.test.mjs` ตรวจจำนวน direct/descendants, keep/override, individual duplicate และ system lock

`php artisan test tests/Feature/IdpChainPrototypeTest.php` ตรวจ GET admin และปฏิเสธ employee; `npm run build` ตรวจ Vue

## เช็กลิสต์ทดสอบมือ

- เข้า Admin จาก sidebar เมนูอยู่หลังโครงสร้างองค์กรก่อนพจนานุกรม; ทั้งสองแท็บ active ถูกต้องและ URL type เปลี่ยน
- เปิดจากลิงก์ใน modal ประเมิน/IDP เดิมแล้วได้แท็บตรงกัน ฟอร์มเดิมเพิ่ม/แก้ได้เหมือนเดิม
- ค้นหา Preset/เพิ่ม/แก้/ทำสำเนา/ลบ พร้อมจำนวนผู้ได้รับผลและ system lock
- เลือก/ยกเลิกรายบุคคล กรองหน่วย/ตำแหน่งแล้วติ๊กและจำนวนยังอยู่; เลือกทั้งหมดที่แสดงและล้าง
- เลือกฝ่ายบริหาร: ไม่รวมย่อย=4 คน; รวมย่อย=21 คน/5 หน่วยย่อย ดูตัวอย่าง 5 ชื่อและดูทั้งหมด
- keep ค่าเริ่มต้น: เพิ่มใหม่ 7/ข้าม 14 สำหรับฝ่ายบริหาร eval เริ่มต้น; override: แทนที่ 14/เพิ่ม 7
- ยืนยันแล้ว toast/จำนวนการ์ดอัปเดต สลับ eval/idp ไม่เปลี่ยนสายของอีกประเภท
- Tab/Shift+Tab อยู่ใน drawer; Esc/คลิกนอก confirm เมื่อเลือกค้าง; ปิด member modal แล้วโฟกัสกลับ drawer
- จอ desktop/900px/<768px ไม่มีแนวนอนล้น ปุ่มล่างคงเห็น และรายการยาวเลื่อนได้
- รีโหลดแล้วข้อมูล mock กลับค่าเริ่มต้น; Network ต้องไม่มี request ไป endpoint ข้อมูลจริงจากโมดูลนี้

การตรวจผ่าน build/unit test ไม่แทนการตรวจด้วย browser ที่ล็อกอินจริง รายการข้างต้นต้องตรวจบนเครื่องที่เปิดเว็บได้
