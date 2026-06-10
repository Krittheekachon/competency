export const ROLES_CONFIG = {
  admin: { lbl: "ผู้ดูแลระบบ", name: "วิชัย ระบบดี", av: "ว", pos: "ผู้ดูแลระบบคณะ", col: "#7C3AED" },
  hr: { lbl: "งานทรัพยากรบุคคล", name: "คุณมาลี ดีเสมอ", av: "ม", pos: "งานทรัพยากรบุคคล", col: "#2563EB" },
  employee: { lbl: "บุคลากร", name: "สมชาย มีสุข", av: "ส", pos: "นักวิชาการศึกษา · สายสนับสนุน", col: "#0EA5A0" },
  supervisor: { lbl: "หัวหน้างาน", name: "กัญญารัตน์ ศรีวิชา", av: "ก", pos: "หัวหน้างานสนับสนุนการศึกษาและวิชาการ", col: "#EA580C" },
  manager_dept: { lbl: "ผู้บังคับบัญชา", name: "ผศ.ดร.ธนพล ไชยรักษ์", av: "ธ", pos: "รองคณบดีฝ่ายบริหาร", col: "#D97706" },
  manager: { lbl: "ผู้บริหารคณะ", name: "รศ.ดร.กิตติพงศ์ แสงทอง", av: "ก", pos: "คณบดี", col: "#0F2D5B" }
};

export const NAV_CONFIG = {
  employee: [
    {
      sec: "การประเมินของฉัน",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผล Competency Gap" },
        { id: "emp-idp", ic: "", lb: "แผนพัฒนา IDP" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    }
  ],
  supervisor: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผล Competency Gap" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "dh-assess", ic: "", lb: "ประเมินลูกน้อง" },
        { id: "sup-gap", ic: "", lb: "Competency Gap ทีม" },
        { id: "dh-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  manager_dept: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผล Competency Gap" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "sup-assess", ic: "", lb: "ประเมินลูกน้อง" },
        { id: "sup-gap", ic: "", lb: "Competency Gap ทีม" },
        { id: "sup-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  manager: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผล Competency Gap" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "ภาพรวมคณะ",
      items: [
        { id: "mgr-gap", ic: "", lb: "Competency Gap คณะ" },
        { id: "mgr-idp", ic: "", lb: "ติดตาม IDP ภาพรวม" },
        { id: "mgr-assessment-approval", ic: "", lb: "อนุมัติผลการประเมิน" },
        { id: "mgr-idp-approval", ic: "", lb: "อนุมัติแผน IDP" }
      ]
    }
  ],
  hr: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผล Competency Gap" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "HR",
      items: [
        { id: "hr-position-competencies", ic: "", lb: "กำหนดสมรรถนะประจำตำแหน่ง" },
        { id: "hr-cycle", ic: "", lb: "รอบการประเมิน" },
        { id: "hr-template", ic: "", lb: "กำหนดความคาดหวัง" }
      ]
    },
    {
      sec: "ภาพรวมคณะ",
      items: [
        { id: "hr-comp-overview", ic: "", lb: "ภาพรวม Competency คณะ" },
        { id: "hr-idp-overview", ic: "", lb: "ภาพรวม IDP คณะ" }
      ]
    }
  ],
  admin: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "สรุปผลสมรรถนะ" },
        { id: "emp-idp", ic: "", lb: "แผนพัฒนา IDP" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" }
      ]
    },
    {
      sec: "ระบบ",
      items: [
        { id: "admin-users", ic: "", lb: "จัดการผู้ใช้งาน" },
        { id: "admin-org", ic: "", lb: "จัดการสายการบังคับบัญชา" },
        { id: "admin-org-structure", ic: "", lb: "จัดการโครงสร้างองค์กร & สมรรถนะ" },
        { id: "admin-dict", ic: "", lb: "พจนานุกรมสมรรถนะ" },
        { id: "admin-idp-tools", ic: "", lb: "เครื่องมือ / แนวทาง สำหรับทำแผน IDP" }
      ]
    }
  ]
};

export const PAGE_TITLES = {
  "profile": "โปรไฟล์",
  "emp-assess": "ประเมินตนเอง",
  "emp-gap": "สรุปผลสมรรถนะ",
  "emp-idp": "แผนพัฒนา IDP",
  "emp-progress": "อัปเดตความก้าวหน้า",
  "emp-idp-detail": "รายละเอียด IDP",
  "sup-assess": "ประเมินลูกน้อง",
  "sup-gap": "Competency Gap ทีม",
  "sup-idp": "IDP & ติดตามทีม",
  "dh-assess": "ประเมินลูกน้อง (หน่วยงาน)",
  "dh-idp": "IDP & ติดตามทีม",
  "dept-monitor": "ภาพรวมหน่วยงาน (Department View)",
  "mgr-gap": "Competency Gap คณะ",
  "mgr-idp": "ติดตาม IDP ภาพรวม",
  "mgr-assessment-approval": "อนุมัติผลการประเมิน",
  "mgr-idp-approval": "อนุมัติแผน IDP",
  "hr-cycle": "รอบการประเมิน",
  "hr-template": "กำหนดความคาดหวัง",
  "hr-position-competencies": "กำหนดสมรรถนะประจำตำแหน่ง",
  "hr-monitor": "ติดตามภาพรวม",
  "hr-comp-overview": "ภาพรวม Competency คณะ",
  "hr-idp-overview": "ภาพรวม IDP คณะ",
  "admin-users": "จัดการผู้ใช้งาน",
  "admin-org": "จัดการสายการบังคับบัญชา",
  "admin-org-structure": "จัดการโครงสร้างองค์กร & สมรรถนะ",
  "admin-dict": "พจนานุกรมสมรรถนะ",
  "admin-idp-tools": "เครื่องมือ / แนวทาง สำหรับทำแผน IDP"
};

export const DEPT_STRUCTURE = {
  "ฝ่ายบริหาร": [
    { work: "งานบริหาร", units: ["หน่วยสารบรรณ", "หน่วยภูมิทัศน์", "หน่วยโครงสร้างพื้นฐานและซ่อมบำรุง", "หน่วยอาคารสถานที่และยานพาหนะ"] },
    { work: "งานคลังและพัสดุ", units: ["หน่วยการเงินและบัญชี", "หน่วยพัสดุ"] }
  ],
  "ฝ่ายการศึกษาและพัฒนาทักษะการเรียนรู้": [
    { work: "งานการศึกษาและพัฒนาทักษะการเรียนรู้", units: ["หน่วยวิชาการและหลักสูตร", "หน่วยพัฒนานักศึกษาและทักษะการเรียนรู้", "หน่วยศิษย์เก่าสัมพันธ์"] }
  ],
  "ฝ่ายวิจัย นวัตกรรมและการต่างประเทศ": [
    { work: "งานวิจัย บัณฑิตศึกษาและการต่างประเทศ", units: ["หน่วยส่งเสริมวิจัยและนวัตกรรม", "หน่วยการต่างประเทศ", "หน่วยสื่อสารองค์กร", "หน่วยบัณฑิตศึกษา"] },
    { work: "งานปฏิบัติการและบริการทางวิศวกรรม", units: ["หน่วยปฏิบัติการทางวิศวกรรม", "หน่วยเครื่องมือกลางทดสอบ และบริการห้องปฏิบัติการทางวิศวกรรม", "หน่วยบริการวิชาการและถ่ายทอดเทคโนโลยี"] }
  ],
  "ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร": [
    { work: "งานแผนยุทธศาสตร์", units: ["หน่วยแผนยุทธศาสตร์", "หน่วยประกันคุณภาพ", "หน่วยเทคโนโลยีสารสนเทศ", "หน่วยพัฒนาทรัพยากรบุคคล"] }
  ]
};

export const INITIAL_USERS = [];

export const INITIAL_COMPETENCIES = [];

export const IDP_GAPS_DATA = [];

export const IDP_ACTIVITIES_DATA = {};
