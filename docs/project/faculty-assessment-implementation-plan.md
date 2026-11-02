# แผนลงมือพัฒนา: วิธีประเมินคณะวิศวกรรมศาสตร์

วันที่ 2 พฤศจิกายน 2569 — ร่างแผนปฏิบัติงาน ยังไม่ได้เริ่มแก้ระบบ

ใช้คู่กับ [ข้อกำหนดวิธีคณะ](faculty-assessment-proposal.md) ซึ่งเป็นแหล่งข้อสรุปทางธุรกิจ แผนนี้ระบุวิธีแบ่งงาน ลำดับการแก้ฐานข้อมูล/โค้ด และการตรวจรับ ชื่อ service และตารางใหม่เป็นข้อเสนอ ไม่ใช่สิ่งที่มีอยู่แล้ว

## 1. หลักในการลงมือ

- ทำทีละชุดงานที่ตรวจได้ครบฐานข้อมูล → backend → Inertia props → UI → tests
- แยกกติกาตามวิธีของรอบ ไม่เปลี่ยนเกณฑ์วิธีเดิมทั่วระบบ
- ใช้ข้อมูลสมรรถนะที่ Admin จัดการได้ ไม่ hard-code จำนวน 6/3/8 หรือรายชื่อสมรรถนะ
- คะแนนและสิทธิ์ตัดสินที่ backend หน้าจอแสดงผลคำนวณเบื้องต้นได้แต่ไม่เป็นแหล่งคะแนนสุดท้าย
- คง ExpectedLevelResolver และ ReviewerChainResolver รวมการเลือก snapshot ของรอบผ่าน resolver เดิม
- เปิดให้ใช้วิธีคณะจริงเมื่อ flow ตั้งค่า → ประเมิน → อนุมัติ → IDP ครบแล้ว ไม่เปิดรอบจริงกลางระยะพัฒนา
- เรื่องที่ยังไม่ตกลงให้พักเฉพาะงานที่ขึ้นกับคำตอบ ทำงานฐานและกติกาที่ยืนยันแล้วต่อได้

## 2. ข้อค้นพบจากโค้ดที่ใช้กำหนดแผน

| ส่วน | สภาพปัจจุบันที่ตรวจพบ | สิ่งที่ต้องทำ |
|---|---|---|
| รอบประเมิน | จัดการผ่าน Hr/AssessmentRoundController และหน้าจอ HR | เพิ่มวิธีประเมินและช่องทางที่ Admin ตั้งค่าได้ พร้อมตรวจสิทธิ์ backend |
| FC | ใช้รอบ + position_title_id ในชุดและกฎจำนวน | เพิ่มขอบเขตองค์กร โดยใช้ resolver เดียวทุกจุดที่อ่าน/ตรวจ FC |
| คะแนนตนเอง | AssessmentController รับ score จาก request และบันทึกผลติ๊ก | วิธีคณะต้องคำนวณ score จาก indicator ที่ตรวจสอบแล้ว |
| ผลติ๊ก | การบันทึกตนเองลบแล้วเขียนผลติ๊กของ assessment ใหม่ | ต้องแยกผลตนเองกับผู้ให้คะแนน ไม่ให้การบันทึกทับกัน |
| ผู้อนุมัติ | approve/reject บันทึก scores และเลื่อนสถานะ ไม่มี payload ติ๊กของผู้ให้คะแนนใน action นี้ | เพิ่มงานให้คะแนนและสิทธิ์เฉพาะประเภท แยกจากการอนุมัติอย่างเดียว |
| ส่งกลับ | reject เปลี่ยนเป็น revision_required และแจ้งบุคลากร | เพิ่มผู้รับงานส่งกลับและประเภทงาน ไม่เปิดให้บุคลากรแก้คะแนนทุกกรณี |
| Snapshot | มี user_round_profiles และ user_round_reviewer_steps ผ่าน AssessmentRoundSnapshotService | ขยายข้อมูลสายงาน/ตำแหน่งบริหาร/ขอบเขตที่กติกาใหม่ต้องใช้ และรักษาผลรอบเดิม |
| IDP | มี parent idps แต่ IdpItemReviewWorkflow คุมอนุมัติราย item และสรุปสถานะขึ้น parent | วิธีคณะต้องมี workflow ของแผนโดยตรง ไม่ใช่แค่รวมหน้าจอแล้วส่งทีละ item |

ยังไม่ได้รัน migrate:status ในการจัดทำแผนนี้ จึงเป็นข้อค้นพบจากโค้ด ไม่ใช่การยืนยัน schema ของฐานข้อมูลที่กำลังใช้งาน

## 3. ลำดับและการพึ่งพา

`P0 ตรวจฐาน → P1 วิธีรายรอบ → P2 ชุดสมรรถนะ/FC → P3 คำนวณคะแนน → P4 สิทธิ์/ส่งกลับ/หลักฐาน → P5 ผลและเกณฑ์ IDP → P6 แผนรวม → P7 ตรวจรับและเปิดใช้`

เตรียม Role และตารางสิทธิ์ของ P4 ได้หลัง P1 แต่ไม่เปิดให้อนุมัติวิธีคณะจนผ่านการคำนวณและหลักฐานครบ ส่วน P6 เริ่มออกแบบได้ก่อน แต่ต้องอาศัยผลอนุมัติและ requires_idp จาก P5 ก่อนทดสอบครบ flow

## P0 — ตรวจฐานและกำหนดรายละเอียดที่มีผลต่อ schema

- [ ] ตรวจ git status, AGENTS.md, เอกสารโครงการ และ migration ที่เกี่ยวข้อง รักษางานค้างเดิม
- [ ] รัน php artisan migrate:status และ baseline tests บนฐานทดสอบที่แยกจากข้อมูลใช้งาน
- [ ] ตรวจโครงสร้างองค์กรจริงและการเชื่อม positions กับ position_titles ก่อนเลือกคีย์ขอบเขต FC
- [ ] ตรวจการเก็บ Role เดี่ยว/ตำแหน่งบริหารและทางเข้า employee ของผู้มี Role ผู้บริหาร ไม่จำเป็นต้องทำระบบหลาย Role เต็มรูปแบบหากไม่ต้องใช้
- [ ] ตรวจเอกสารสมรรถนะฉบับเต็มเมื่อได้รับ และยืนยันว่าทุกกลุ่มรองรับเกณฑ์สะสมเดียวกัน
- [ ] ตัดสินใจเรื่องค้างตามหัวข้อ 5 ก่อนเริ่มงานที่ขึ้นกับคำตอบ

**จบเมื่อ:** รู้ baseline ที่ผ่าน/ล้มเหลวเดิม มีรายการ schema จริง และไม่มีการนำ failure เดิมไปนับเป็น regression ใหม่โดยไม่ตรวจ

## P1 — เพิ่มวิธีประเมินรายรอบ

งาน:

- [ ] เพิ่ม forward migration สำหรับ assessment_rounds.assessment_method เสนอค่า standard / faculty และให้ข้อมูลรอบเดิมเป็น standard
- [ ] เพิ่ม service เลือกกติกาจากรอบ เช่น AssessmentMethodResolver โดยโหลดจากฐานข้อมูล ไม่เชื่อค่าที่ client ส่งอ้างวิธี
- [ ] แสดงวิธีผ่าน DashboardController และเพิ่มช่องตั้งค่าให้ Admin โดยใช้กลไกรอบเดิมร่วมกัน
- [ ] ตรวจ validation, copy round, readiness และการแก้รอบที่มีข้อมูลแล้ว เสนอล็อกวิธีหลังมีการส่งครั้งแรก
- [ ] เก็บ version ของกติกาวิธีคณะเพื่อให้เปลี่ยนกติกาอนาคตโดยไม่คำนวณผลเก่าย้อนหลัง

ไฟล์หลัก: routes/web.php, app/Http/Controllers/Hr/AssessmentRoundController.php, app/Services/AssessmentRoundReadinessService.php, app/Http/Controllers/DashboardController.php, resources/js/Pages/HR/HrAssessmentRounds.vue, resources/js/Pages/Admin/Dashboard.vue และ resources/js/data.ts

ทดสอบ: ขยาย HrAssessmentRoundTest และ AssessmentRoundDeadlineTest; เพิ่มกรณี Admin ตั้งวิธีได้ ผู้ไม่มีสิทธิ์ตั้งไม่ได้ รอบเก่าใช้วิธีเดิม และ copy round ไม่เปลี่ยนข้อมูลรอบต้นทาง

**จบเมื่อ:** สร้างรอบทดสอบสองวิธีและอ่านค่าถูกต้องทั้ง UI/backend โดยวิธีคณะยังไม่เปิดใช้ในรอบจริง

## P2 — Role ชุดสมรรถนะ และ FC ตามองค์กร

งาน:

- [ ] เพิ่ม Role รองคณบดี/ผู้ช่วยคณบดีในข้อมูลหลักแบบไม่สร้างซ้ำ ตรวจ normalization, middleware, navigation, mock SSO และหน้าจอผู้ใช้ทุกแห่ง
- [ ] แยกสถานะผู้รับการประเมินที่ต้องมี MC ออกจากการใช้ Role ให้คะแนนผู้อื่น และเก็บบริบทที่จำเป็นใน snapshot ของรอบ
- [ ] สร้างการตั้งชุด CC ตามสายงานและ MC เพิ่มสำหรับรอง/ผู้ช่วย ผ่านข้อมูลที่ Admin/HR แก้ได้
- [ ] เพิ่ม scope องค์กรในชุด FC และกฎจำนวน พร้อม constraint ไม่ให้มีกฎซ้ำในขอบเขตเดียวกัน ตรวจการจัดการ NULL และ unique index บน PostgreSQL
- [ ] ทำตัว resolve ชุดสมรรถนะ/FC เช่น CompetencyAssignmentResolver และใช้ร่วมกันใน sync, dashboard, FC submit, FC approve และ assessment validation
- [ ] ปรับ copy round ให้คัดลอก scope และจำนวนครบ ไม่ลบ configuration หน่วยงานอื่นโดยไม่ได้ตั้งใจ
- [ ] ตรวจกรณี HR เปลี่ยนชุดหลังบุคลากรเลือก/ส่งแล้ว ให้ใช้กติกา snapshot ที่กำหนด ไม่ทำให้หัวข้อที่อนุมัติหายเงียบ ๆ

ไฟล์หลัก: app/Models/User.php, app/Http/Controllers/Hr/PositionCompetencyController.php, app/Services/CompetencyAssessmentSyncService.php, app/Services/AssessmentRoundSnapshotService.php, app/Http/Controllers/Employee/FcTopicSelectionController.php, app/Http/Controllers/FcTopicSelectionApprovalController.php และ DashboardController

ทดสอบ: HrPositionCompetencyTest, FcTopicSelectionFlowTest, ReviewerChainSnapshotPolicyTest และทดสอบ Role ใหม่ โดยยึดรูปแบบ DivisionHeadRoleTest

**จบเมื่อ:** ตำแหน่งเดียวกันคนละหน่วยได้ตัวเลือก/จำนวนต่างกัน บุคลากรเลือกนอกชุดไม่ได้ และผู้บริหารสายวิชาการได้รับ CC + MC โดยหัวหน้าภาควิชาไม่เพิ่ม MC อัตโนมัติ

## P3 — คำนวณคะแนนจากผลติ๊กที่ backend

งาน:

- [ ] เพิ่ม FacultyAssessmentScorer แบบคำนวณล้วน รับชุดพฤติกรรมของรอบและรายการที่ติ๊ก คืนระดับ 0–5 ตามเกณฑ์สะสม
- [ ] ตรวจ indicator ID/key ว่าเป็นของสมรรถนะและชุดของรอบจริง ปฏิเสธรายการปลอม ซ้ำ หรือข้ามสมรรถนะตาม validation ที่กำหนด
- [ ] วิธีคณะไม่รับ score จาก client เป็นคะแนนจริง แม้ผู้ใช้แก้ request ก็ต้องคำนวณจากผลติ๊ก
- [ ] แยกข้อมูล self กับ reviewer โดยเสนอตารางผลติ๊กผู้ให้คะแนนใหม่ เชื่อม assessment + reviewer + revision ไม่ใช้การลบผลติ๊กตนเองเขียนทับ
- [ ] สร้าง component ติ๊กพฤติกรรมร่วมสำหรับบุคลากรและผู้ให้คะแนน พร้อมแสดงผลจาก server หลังบันทึก
- [ ] ปิดช่องกรอกน้ำหนักเฉพาะวิธีคณะ โดยไม่ลบข้อมูลน้ำหนักวิธีเดิม

ไฟล์หลัก: app/Http/Controllers/AssessmentController.php, resources/js/Pages/Employee/EmployeeAssess.vue, DashboardController และหน้าจอ reviewer ที่เรียก action ประเมิน

ทดสอบใหม่: FacultyAssessmentScorerTest, FacultyAssessmentSubmissionTest ครอบคลุม 0, 1, ครบกลุ่มแรก, เริ่ม/ครบกลุ่มระดับ 3, ระดับ 5 หนึ่ง/สองข้อ, ติ๊กข้ามระดับ และปลอม score

**จบเมื่อ:** การติ๊กเดียวกันให้คะแนนเท่ากันทั้งบุคลากร/ผู้ให้คะแนน และวิธีเดิมยังผ่าน tests เดิม

## P4 — สิทธิ์ การส่งกลับ และหลักฐานระดับ 5

งาน:

- [ ] เพิ่ม policy/service เช่น FacultyAssessmentPermissionResolver ที่ตัดสินจากวิธีรอบ + ผู้รับการประเมิน + ประเภทสมรรถนะ + ผู้ให้คะแนน + ขั้นตอนจริง
- [ ] คง ReviewerChainResolver เป็นแหล่งสายอนุมัติ รวม snapshot ไม่คัดลอกสิทธิ์จาก template มาใช้ runtime และไม่ผูก Role กับ step 1/2/3 ตายตัว
- [ ] เก็บ current task, ผู้รับงาน, return target, submission/review version และประวัติการตัดสินใจแบบเพิ่มรายการ เพื่อส่งกลับผู้ให้คะแนนหรือผู้แนบหลักฐานอย่างเจาะจง
- [ ] แยก action ให้คะแนน, อนุมัติอย่างเดียว, ส่งกลับแก้คะแนน, ขอหลักฐาน, ส่งหลักฐาน และตรวจหลักฐาน
- [ ] เมื่อส่ง CC กลับหัวหน้า ให้เปลี่ยนเฉพาะงาน CC ไม่ reset คะแนน MC หรือเปิด MC ให้หัวหน้าแก้
- [ ] เพิ่มที่เก็บไฟล์หลักฐานประเมินและ metadata แยกจาก legacy assessment_evidences ที่ไม่ใช่แหล่ง checked indicators
- [ ] จำกัดการอ่านไฟล์ตามเจ้าของ/ผู้ตรวจที่มีสิทธิ์ ตรวจประเภทและขนาดไฟล์ รวม ownership ก่อนแนบหลักฐานกับสมรรถนะ
- [ ] self ระดับ 5 ไม่มีหลักฐานส่งไม่ได้; reviewer ปรับเป็น 5 ต้องรอหลักฐาน; บุคลากรในงานเพิ่มหลักฐานแก้คะแนนไม่ได้
- [ ] อัปเดต NotificationService/NotificationRecipientResolver ให้ส่งงานถูกคนในขั้นปัจจุบัน ไม่แจ้งบุคลากรทุกครั้งที่ส่งกลับ
- [ ] ใช้ transaction และล็อก/version check กันคำขอซ้ำหรือ reviewer สองคนตัดสินผลเก่าพร้อมกัน

ไฟล์หลัก: AssessmentController, ReviewerChainResolver, AssessmentRoundSnapshotService, NotificationService, NotificationRecipientResolver, routes/web.php และ dashboard ของ Head/Super/Executive/Employee

ทดสอบ: ขยาย AssessmentReviewerChainTest และ NotificationRecipientResolverTest; เพิ่ม FacultyAssessmentPermissionsTest, FacultyAssessmentReturnTest, FacultyAssessmentEvidenceTest ตรวจสิทธิ์จาก HTTP โดยตรง ไม่ตรวจแค่การซ่อนปุ่ม

**จบเมื่อ:** ผ่านตารางสิทธิ์ทุกช่องในเอกสารข้อกำหนด ส่ง CC กลับแล้ว MC คงอยู่ และเพิ่มหลักฐานกลับถึงผู้ให้คะแนนเดิมได้

## P5 — ผลสุดท้าย เกณฑ์ 80% และ Spider Chart

งาน:

- [ ] resolve Actual จากผู้มีสิทธิ์ให้คะแนนหลัก ไม่ใช้คะแนนจากผู้อนุมัติคนสุดท้ายโดยอัตโนมัติ
- [ ] รวมการคำนวณ Gap/IDP policy ไว้จุดเดียว เช่น CompetencyGapCalculator แยกวิธีเดิม gap < 0 กับวิธีคณะ ratio < 80%
- [ ] คง gap = actual - expected และตรวจสิทธิ์สร้างแผนจากสถานะ approved เท่านั้น
- [ ] ตัดสิน threshold จากค่าจริงก่อนปัดแสดงผล และป้องกัน Expected ศูนย์/ว่างตามนโยบายที่ตกลง
- [ ] ใช้ผลเดียวกันใน EmployeeGap, dashboard, รายงาน/analytics และข้อมูลต้นทางของ IDP ไม่ให้แต่ละหน้าคำนวณคนละกติกา
- [ ] เพิ่ม Radar/Spider component แสดงทุกสมรรถนะที่บุคคลได้รับ พร้อม Actual/Expected และตารางอ่านข้อความได้เมื่อกราฟมีหลายแกน
- [ ] รองรับไม่มีข้อมูล/มีเพียง 1–2 สมรรถนะด้วยข้อความหรือตารางที่ถูกต้อง ตรวจชื่อยาวและหน้าจอแคบ

ไฟล์หลัก: AssessmentController, ExpectedLevelResolver (ใช้หลักเกณฑ์เดิม), DashboardController, FacultyAnalyticsService, resources/js/Pages/Employee/EmployeeGap.vue และ component กราฟใหม่

ทดสอบ: FacultyCompetencyGapTest และ FacultyAnalyticsDashboardTest; 75% ต้องทำ IDP, 80% ไม่ต้อง, Actual 0, ผลยังไม่อนุมัติ และรอบวิธีเดิมยังใช้เกณฑ์เดิม

**จบเมื่อ:** หน้าผล แผน IDP และรายงานใช้ผลเดียวกัน และแสดงสมรรถนะครบโดยไม่สร้างรายการที่ไม่ได้ประเมิน

## P6 — IDP รวมและ Formal แบบลดรายละเอียด

ก่อน migration ของระยะนี้: ยืนยัน scope ของ Formal, น้ำหนักกิจกรรม, ความครอบคลุมสมรรถนะ และการปิดแผนตามหัวข้อ 5

แนวทาง schema ที่เสนอ:

- ใช้ idps เป็นแผนต่อ user/round ต่อไป ตรวจและจัดการข้อมูลซ้ำก่อนเพิ่ม constraint
- เพิ่ม workflow fields และประวัติการอนุมัติระดับแผน เช่น idp_plan_reviews
- เพิ่มความสัมพันธ์ plan ↔ approved competency gaps เพื่อแสดงทุกหัวข้อที่ตก
- กิจกรรมวิธีคณะต้องเป็นของแผนโดยตรง เสนอเพิ่ม idp_id ใน idp_activities และรองรับ item linkage เดิม โดยมี validation/constraint แยก mode; สรุปความสัมพันธ์กิจกรรม↔สมรรถนะก่อนกำหนด pivot
- idp_items และ IdpItemReviewWorkflow ยังใช้กับวิธีเดิม ห้ามทำ parent status แบบสรุป item มาทับสถานะ workflow ของแผนวิธีคณะ

งาน:

- [ ] เพิ่ม FacultyIdpPlanWorkflow สำหรับ draft → review step → approved / revision_required ใช้สาย chain_type=idp เดิม
- [ ] เพิ่มการบันทึกและส่งทั้งแผนแบบ transaction; ส่งซ้ำไม่สร้างแผน/รายการซ้ำ และ lock ระหว่างรออนุมัติ
- [ ] ป้องกันการส่งวิธีคณะผ่าน submit-item เพื่อหลบการตรวจแผนรวม และป้องกัน autosave เขียนทับแผนที่ส่งแล้ว
- [ ] แสดงข้อที่ตกอัตโนมัติในหน้าเดียว เพิ่มกิจกรรมและปุ่มส่งแผนครั้งเดียว
- [ ] Formal ดึงเฉพาะหลักสูตรที่เชื่อมกับข้อที่ตกในแผน ไม่เปิดทุกหลักสูตร และลดฟิลด์ตามขอบเขตที่ยืนยัน
- [ ] ปรับหน้าผู้ตรวจให้อนุมัติ/ส่งกลับแผนรวม พร้อมประวัติและ comment; ใช้ submission version กันตัดสิน revision เก่า
- [ ] ต่อการติดตามกิจกรรมและปิดผลเข้ากับสถานะอนุมัติแผน ตามนโยบายที่ยืนยัน ไม่ปล่อยให้กิจกรรมคณะถูกบล็อกเพราะไม่มี approved item แบบเดิม
- [ ] อัปเดต notifications/digest ให้หนึ่งการส่งแผนไม่กลายเป็นหลายรายการแจ้งรายสมรรถนะ

ไฟล์หลัก: app/Http/Controllers/Employee/IdpController.php, IdpApprovalController.php, Employee/IdpActivityUpdateController.php, IdpCompletionReviewController.php, app/Services/IdpItemReviewWorkflow.php, DashboardController, resources/js/Pages/Employee/EmployeeIDP.vue, EmployeeIDPDetail.vue, resources/js/Pages/Head/IdpItemApproval.vue และ Admin/LearningCatalogController.php

ทดสอบ: FacultyIdpPlanTest, FacultyIdpApprovalTest และ regression EmployeeIdpPlanTest, IdpItemApprovalTest, IdpActivityUpdateTest, IdpActivityProgressReviewTest, AdminLearningCatalogTest

**จบเมื่อ:** ตกสองข้อได้หนึ่งแผนหนึ่ง submission อนุมัติครบแล้วติดตามกิจกรรมได้ วิธีเดิมยังส่งและอนุมัติแยก item ได้ตามเดิม

## P7 — ตรวจรับข้อมูลจริงและเปิดใช้งาน

- [ ] นำเอกสารสมรรถนะเข้าชุดทดสอบ ตรวจข้อความ พฤติกรรม และ Expected ร่วมกับผู้ใช้ ไม่สร้างรายชื่อแทนเอกสารจริง
- [ ] ใช้บัญชีทดสอบสายสนับสนุนต่างหน่วย สายวิชาการ หัวหน้าสาขา รองคณบดี ผู้ช่วยคณบดี คณบดี HR และ Admin
- [ ] เดินครบ flow ทั้งอนุมัติ ส่งกลับ CC ขอหลักฐาน แก้ไขแล้วส่งใหม่ และ IDP รวม
- [ ] ตรวจ refresh/ย้อนกลับ/เปิดหลายแท็บ, validation, empty states และ layout ทั้งสองด้านของ breakpoint 900px
- [ ] รัน full suite, npm run build และ migrate:status; ทดสอบ migration บน PostgreSQL พร้อมข้อมูลวิธีเดิมที่เป็นตัวอย่างปลอดภัย
- [ ] ตรวจรายงาน การแจ้งเตือน หลักฐาน และการย้ายตำแหน่ง/สายอนุมัติหลังจับ snapshot
- [ ] ปรับ docs/project/requirements.md, architecture.md, current-state.md, decisions.md และคู่มือ skill ให้ระบุ invariants แยกตามวิธีหลังพัฒนาจริง
- [ ] เปิดวิธีคณะในรอบทดสอบให้ผู้ใช้ตรวจรับก่อนเปิดรอบใช้งาน

**จบเมื่อ:** ผู้ใช้เดินงานจริงครบ ทุก test ที่เกี่ยวข้องผ่านหรือมี failure เดิมที่อธิบายชัด และไม่มีหัวข้อธุรกิจค้างที่กระทบการใช้งาน

## 4. แผน migration และถอยกลับ

- ใช้ forward migrations แยกตามระยะ ไม่แก้ migration ที่คนอื่นรันแล้ว
- เริ่มจากเพิ่ม field/table ที่ไม่ทำลายข้อมูล แล้วค่อยเปลี่ยนเส้นทางอ่านเขียนเฉพาะ faculty
- รอบเดิม/ข้อมูลเก่าเป็น standard ไม่คำนวณคะแนนเก่าใหม่หรือรวม IDP เก่าอัตโนมัติ
- รันสำรองข้อมูลและทดลอง restore ตามกระบวนการ deployment ก่อนเปลี่ยนฐานใช้งาน
- ถ้าพบปัญหาก่อนมีข้อมูลคณะ ใช้ปิดการเปิดรอบคณะและแก้ต่อได้
- ถ้ามีข้อมูลคณะแล้ว ห้าม rollback ด้วยการ drop ตารางหรือเปลี่ยนรอบเป็น standard; ระงับการรับงานคณะชั่วคราวและแก้แบบ forward โดยรักษาข้อมูล
- การ deploy และเปิดรอบจริงเป็นขั้นดำเนินงานภายหลัง ไม่ใช่สิ่งที่ทำในงานเขียนแผนนี้

## 5. การตัดสินใจที่ต้องปิดก่อนงานที่เกี่ยวข้อง

| ประเด็น | ข้อเสนอเริ่มต้น / สิ่งที่ต้องตอบ | ต้องปิดก่อน |
|---|---|---|
| ขอบเขต FC | ใช้ระดับองค์กรจากข้อมูลจริง ให้ค่าที่เฉพาะกว่าทับค่าตำแหน่ง แต่ต้องยืนยันระดับและลำดับ | P2 schema |
| หัวหน้าสาขา | ยืนยันว่าตรงกับ academic_department_head หรือเป็นอีกตำแหน่ง | P2–P4 |
| รองคณบดีหลายคน/ไม่มี | กำหนดผู้ให้คะแนนหลักอย่างชัดเจน ไม่เดาลำดับ; เสนอบล็อก readiness หากขาดผู้ให้คะแนน | P4 |
| ปลายทางส่งกลับอื่น | ผู้ช่วยคณบดีส่งกลับใคร และสายวิชาการทั่วไปกลับหัวหน้าสาขาหรือไม่ | P4 |
| Expected ไม่พร้อม | เสนอแจ้ง HR แก้ค่าก่อนสรุปผล ไม่ถือว่าผ่านอัตโนมัติ | P5 |
| Formal ลดข้อมูลที่ใด | ทะเบียน Admin, แบบกิจกรรม หรือทั้งสอง; วันเวลา/น้ำหนัก/ติดตามเก็บอย่างไร | P6 schema/UI |
| กิจกรรมครอบคลุมข้อที่ตก | ต้องครบทุกข้อก่อนส่งหรือไม่ และระบบเชื่อมให้อย่างไร; ผู้ใช้ไม่ต้องสร้างแผนซ้ำ | P6 schema/validation |
| ปิดแผนหลังพัฒนา | ปิดทั้งแผนหรือพิจารณารายกิจกรรมตามเดิม | P6 workflow |

คำตอบเหล่านี้เป็นการกำหนดพฤติกรรม ไม่ควรให้ผู้พัฒนาเดาแล้วเขียนลงฐานข้อมูลก่อน

## 6. รูปแบบส่งงานแต่ละระยะ

แต่ละระยะส่งเป็นชุดเปลี่ยนแปลงที่ review ได้ พร้อมระบุไฟล์ที่แก้ migration ที่เพิ่ม test ที่รัน ผล UI ที่ตรวจ และข้อจำกัดที่เหลือ หากสร้าง branch ใช้ codex/ ตามแนวทางโครงการ

เริ่มลงมือจาก **P0 และ P1** หลังรับแผนนี้ แล้วเดินตาม dependency ข้างต้น ไม่จำเป็นต้องประมาณวันเสร็จก่อนตรวจ baseline และปิดรายละเอียด schema ที่ยังค้าง

## 7. สถานะเอกสารนี้

ตรวจโค้ดที่เกี่ยวข้องเพื่อจัดลำดับงานแล้ว แต่ยังไม่ได้รัน baseline tests, build หรือ migration และยังไม่มีการเปลี่ยน application code หรือฐานข้อมูล เอกสารนี้เป็น checklist สำหรับเริ่มพัฒนาภายหลัง
