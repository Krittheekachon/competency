export type DetailField = {
  key: string;
  label: string;
  type?: 'text' | 'choice' | 'area' | 'supervisor-chain' | 'expert-name';
  choices?: string[];
  showWhen?: { key: string; value: string };
};

export type RowField = {
  key: string;
  label: string;
  type?: 'text' | 'choice' | 'checkbox-choice' | 'multi-checkbox' | 'area';
  choices?: string[];
  value?: string;
  placeholder?: string;
  owner?: string;
  group?: string;
};

export type IdpFormDefinition = {
  number: string;
  title: string;
  focus: string;
  detailTitle: string;
  detailFields: DetailField[];
  rowTitle: string;
  rowFields: RowField[];
  note?: string;
};

export const formDefinitions: Record<string, IdpFormDefinition> = {
  form_1_idp_plan: {
    number: '1',
    title: 'แบบฟอร์มกำหนดแผนพัฒนารายบุคคล (Individual Development Plan: IDP)',
    focus: 'IDP Master Plan',
    detailTitle: 'คำรับรอง',
    detailFields: [
      { key: 'employeeAcknowledgement', label: 'คำรับรองผู้รับการพัฒนา', type: 'area' },
      { key: 'supervisorAcknowledgement', label: 'คำรับรองหัวหน้างาน', type: 'area' },
    ],
    rowTitle: 'ข้อมูลประกอบการวางแผน',
    rowFields: [
      { key: 'competency', label: 'ความสามารถที่ต้องการพัฒนา', type: 'area' },
      { key: 'behaviorResult', label: 'เป้าหมายในการพัฒนา (Behavior Result)', type: 'area' },
      { key: 'successCriteria', label: 'ตัวชี้วัดสำเร็จของการพัฒนา', type: 'area' },
      { key: 'developmentMethod', label: 'เครื่องมือ/แนวทางการพัฒนา', type: 'area' },
      { key: 'documentReference', label: 'เอกสารประกอบหมายเลข' },
      { key: 'timeline', label: 'ระยะเวลาที่คาดว่าจะดำเนินการ' },
    ],
  },
  form_2_performance_report: {
    number: '2',
    title: 'แบบฟอร์มรายงานผลการพัฒนาตามแผนพัฒนารายบุคคล (IDP Performance Report)',
    focus: 'IDP Performance Report',
    detailTitle: 'ข้อมูลสรุปผล',
    detailFields: [
      { key: 'summary', label: 'สรุปผลรายงานการพัฒนา', type: 'area' },
    ],
    rowTitle: 'รายงานผลการพัฒนา',
    rowFields: [
      { key: 'competency', label: 'ความสามารถที่ต้องการพัฒนา', type: 'area' },
      { key: 'behaviorResult', label: 'เป้าหมายในการพัฒนา (Behavior Result)', type: 'area' },
      { key: 'developmentMethod', label: 'วิธีการพัฒนา', type: 'area' },
      { key: 'documentReference', label: 'เอกสารประกอบหมายเลข' },
      { key: 'operationResult', label: 'ผลการดำเนินการพัฒนาตามแผน', type: 'checkbox-choice', choices: ['เป็นไปตามแผน', 'ไม่เป็นไปตามแผน'] },
      { key: 'developmentResult', label: 'ผลการพัฒนา', type: 'checkbox-choice', choices: ['บรรลุเกินเป้าหมาย', 'บรรลุตามเป้าหมาย', 'ไม่บรรลุผล'] },
    ],
  },
  form_3_project_assignment: {
    number: '3',
    title: 'แบบฟอร์มการมอบหมายงานโครงการ/งานพิเศษ',
    focus: 'Experiential Learning Focus (70)',
    detailTitle: 'รายละเอียดการมอบหมายงาน/โครงการ',
    detailFields: [
      { key: 'projectName', label: 'ชื่อโครงการ/แผนงาน' },
      { key: 'roleResponsibility', label: 'บทบาทหน้าที่' },
      { key: 'workProportion', label: 'สัดส่วนงาน' },
    ],
    rowTitle: 'แผนการดำเนินงาน',
    rowFields: [
      { key: 'assignedIssue', label: 'ประเด็นที่มอบหมาย', type: 'area' },
      { key: 'durationMonths', label: 'ระยะเวลาดำเนินการ 1-12 เดือน' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
  },
  form_4_ojt: {
    number: '4',
    title: 'แบบฟอร์มการเรียนรู้จากการปฏิบัติงานจริง (On the Job Training/Learning: OJT)',
    focus: 'Experiential Learning Focus (70)',
    detailTitle: 'รายละเอียดการมอบหมายงาน/โครงการ',
    detailFields: [
      { key: 'supervisorType', label: 'ผู้สอนงาน/ผู้มอบหมาย', type: 'choice', choices: ['หัวหน้างาน', 'ผู้เชี่ยวชาญ'] },
      { key: 'supervisorUserId', label: 'เลือกหัวหน้างานในสายการบังคับบัญชา', type: 'supervisor-chain', showWhen: { key: 'supervisorType', value: 'หัวหน้างาน' } },
      { key: 'expertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'supervisorType', value: 'ผู้เชี่ยวชาญ' } },
      { key: 'workTopic', label: 'หัวข้อ/งานที่ปฏิบัติจริง', type: 'area' },
    ],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นการฝึกปฏิบัติงาน', type: 'area' },
      { key: 'developmentStart', label: 'เริ่ม', group: 'ระยะการพัฒนา' },
      { key: 'developmentEnd', label: 'สิ้นสุด', group: 'ระยะการพัฒนา' },
      { key: 'hours', label: 'จำนวนชั่วโมง' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
  },
  form_5_coaching: {
    number: '5',
    title: 'แบบฟอร์มการสอนงาน (Coaching)',
    focus: 'Social Learning Focus (20)',
    detailTitle: 'รายละเอียดการสอนงาน',
    detailFields: [
      { key: 'coachType', label: 'ผู้สอนงาน', type: 'choice', choices: ['หัวหน้างาน', 'ผู้เชี่ยวชาญ'] },
      { key: 'coachSupervisorUserId', label: 'เลือกหัวหน้างานในสายการบังคับบัญชา', type: 'supervisor-chain', showWhen: { key: 'coachType', value: 'หัวหน้างาน' } },
      { key: 'coachExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'coachType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'topic', label: 'หัวข้อทักษะ/ประเด็นการสอนงาน', type: 'area' },
      { key: 'coachingApproaches', label: 'A', type: 'multi-checkbox', value: 'A', group: 'แนวทางการสอนงาน' },
      { key: 'coachingApproaches', label: 'B', type: 'multi-checkbox', value: 'B', group: 'แนวทางการสอนงาน' },
      { key: 'coachingApproaches', label: 'C', type: 'multi-checkbox', value: 'C', group: 'แนวทางการสอนงาน' },
      { key: 'coachingApproaches', label: 'D', type: 'multi-checkbox', value: 'D', group: 'แนวทางการสอนงาน' },
      { key: 'developmentStart', label: 'เริ่ม', group: 'ระยะการพัฒนา' },
      { key: 'developmentEnd', label: 'สิ้นสุด', group: 'ระยะการพัฒนา' },
      { key: 'sessionCount', label: 'จำนวน', group: 'ระยะเวลาต่อครั้ง', placeholder: 'เช่น 3 ครั้ง' },
      { key: 'sessionDuration', label: 'ระยะเวลา', group: 'ระยะเวลาต่อครั้ง', placeholder: 'เช่น ครั้งละ 1 ชั่วโมง' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
    note: 'แนวทางการสอนงาน ได้แก่ A : ส่งเสริม , B : สร้างสรรค์ , C : กระตุ้น , D : แก้ปัญหา',
  },
  form_6_mentoring: {
    number: '6',
    title: 'แบบฟอร์มการเป็นพี่เลี้ยง (Mentoring)',
    focus: 'Social Learning Focus (20)',
    detailTitle: 'รายละเอียดการเป็นพี่เลี้ยง',
    detailFields: [
      { key: 'mentorType', label: 'ผู้สอนงาน', type: 'choice', choices: ['หัวหน้างาน', 'ผู้เชี่ยวชาญ'] },
      { key: 'mentorSupervisorUserId', label: 'เลือกหัวหน้างานในสายการบังคับบัญชา', type: 'supervisor-chain', showWhen: { key: 'mentorType', value: 'หัวหน้างาน' } },
      { key: 'mentorExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'mentorType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'technique', label: 'เทคนิค' },
      { key: 'developmentStart', label: 'เริ่ม', group: 'ระยะการพัฒนา' },
      { key: 'developmentEnd', label: 'สิ้นสุด', group: 'ระยะการพัฒนา' },
      { key: 'sessionCount', label: 'จำนวน', group: 'ระยะเวลาต่อครั้ง' },
      { key: 'sessionDuration', label: 'ระยะเวลา', group: 'ระยะเวลาต่อครั้ง' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
  },
  form_7_group_activity: {
    number: '7',
    title: 'แบบฟอร์มการเรียนรู้แบบกระบวนการกลุ่ม (Group Activity)',
    focus: 'Social Learning Focus (20)',
    detailTitle: 'รายละเอียดการเป็นพี่เลี้ยง',
    detailFields: [
      { key: 'facilitatorType', label: 'ผู้นำเสนอ/ผู้นำกิจกรรม', type: 'choice', choices: ['หัวหน้างาน', 'ผู้เชี่ยวชาญ'] },
      { key: 'facilitatorSupervisorUserId', label: 'เลือกหัวหน้างานในสายการบังคับบัญชา', type: 'supervisor-chain', showWhen: { key: 'facilitatorType', value: 'หัวหน้างาน' } },
      { key: 'facilitatorExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'facilitatorType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'learningTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'tactic', label: 'เทคนิค' },
      { key: 'activitySchedule', label: 'กำหนดการจัดกิจกรรม' },
      { key: 'assessmentTools', label: 'เครื่องมือและเงื่อนไขการประเมิน', type: 'area' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
  },
  form_8_feedback: {
    number: '8',
    title: 'การเรียนรู้ผ่านการให้ข้อมูลป้อนกลับ (Feedback)',
    focus: 'Social Learning Focus (20)',
    detailTitle: 'รายละเอียดการให้ข้อมูลป้อนกลับ',
    detailFields: [
      { key: 'feedbackProviderType', label: 'ผู้ให้ข้อมูล', type: 'choice', choices: ['หัวหน้างาน', 'ผู้เชี่ยวชาญ'] },
      { key: 'feedbackSupervisorUserId', label: 'เลือกหัวหน้างานในสายการบังคับบัญชา', type: 'supervisor-chain', showWhen: { key: 'feedbackProviderType', value: 'หัวหน้างาน' } },
      { key: 'feedbackExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'feedbackProviderType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'feedbackSource', label: 'แหล่งข้อมูลป้อนกลับ', type: 'area' },
      { key: 'developmentStart', label: 'เริ่ม', group: 'ระยะการพัฒนา' },
      { key: 'developmentEnd', label: 'สิ้นสุด', group: 'ระยะการพัฒนา' },
      { key: 'sessionCount', label: 'จำนวน', group: 'ระยะเวลาต่อครั้ง' },
      { key: 'sessionDuration', label: 'ระยะเวลา', group: 'ระยะเวลาต่อครั้ง' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
    note: 'แหล่งข้อมูลป้อนกลับ เช่น สถิติการขาด ลา มาสาย, รายงานผลการปฏิบัติงาน, ข้อร้องเรียน, ข้อสังเกตจากหัวหน้างาน, ผลประเมิน 360',
  },
  form_9_field_trip: {
    number: '9',
    title: 'แบบฟอร์มการเรียนรู้นอกสถานที่ (Field Trip)',
    focus: 'Social Learning Focus (20)',
    detailTitle: '',
    detailFields: [],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'learningPlace', label: 'สถานที่/แหล่งศึกษาดูงาน', type: 'area' },
      { key: 'activitySchedule', label: 'กำหนดการจัดกิจกรรม' },
      { key: 'assessmentTools', label: 'เครื่องมือและเงื่อนไขการประเมิน', type: 'area' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
    note: 'ตัวอย่างเทคนิคการสอนงาน เช่น การเป็นแบบอย่างที่ดี, การแบ่งปันประสบการณ์, การให้คำปรึกษา (Consult) โดยเน้นการถ่ายทอด Tacit Knowledge ที่เกิดจากการสั่งสมประสบการณ์ของพี่เลี้ยง',
  },
  form_10_training: {
    number: '10',
    title: 'แบบฟอร์มการฝึกอบรม',
    focus: 'Formal Learning Focus (10)',
    detailTitle: '',
    detailFields: [],
    rowTitle: 'แผนดำเนินงาน',
    rowFields: [
      { key: 'trainingType', label: 'รูปแบบการอบรม', type: 'checkbox-choice', choices: ['In-class Training', 'e-Learning'] },
      { key: 'courseCode', label: 'รหัสหลักสูตร' },
      { key: 'courseName', label: 'ชื่อหลักสูตร', type: 'area' },
      { key: 'trainingDate', label: 'วันที่', group: 'ระยะเวลาการอบรม' },
      { key: 'trainingHours', label: 'จำนวนชั่วโมง', group: 'ระยะเวลาการอบรม' },
      { key: 'cost', label: 'ค่าใช้จ่าย (บาท)' },
      { key: 'operationResult', label: 'ผลการดำเนินงาน', type: 'checkbox-choice', choices: ['สำเร็จ', 'ไม่สำเร็จ'], owner: 'อัปเดตผล' },
      { key: 'developmentAdvice', label: 'ข้อแนะนำในการพัฒนา', type: 'area', owner: 'หัวหน้า' },
    ],
  },
};

export const formOptionFor = (code: string) => {
  const form = formDefinitions[code];
  return form ? { value: code, label: `แบบฟอร์มที่ ${form.number} · ${form.title}` } : null;
};

export const allPreviewFormOptions = Object.keys(formDefinitions).map((code) => ({
  value: code,
  label: `แบบฟอร์มที่ ${formDefinitions[code].number} · ${formDefinitions[code].title}`,
}));

export const hasGroupedRowFields = (fields: RowField[]) => fields.some((field) => Boolean(field.group));

export const rowHeaderCells = (fields: RowField[]) => {
  const cells: Array<{ key: string; label: string; owner?: string; colspan: number; rowspan: number }> = [];
  for (let index = 0; index < fields.length; index += 1) {
    const field = fields[index];
    if (!field.group) {
      cells.push({ key: field.key, label: field.label, owner: field.owner, colspan: 1, rowspan: hasGroupedRowFields(fields) ? 2 : 1 });
      continue;
    }

    const grouped = fields.slice(index).filter((item, itemIndex) =>
      itemIndex === 0 || (item.group === field.group && fields[index + itemIndex - 1]?.group === field.group)
    );
    const colspan = grouped.findIndex((item) => item.group !== field.group);
    const groupSize = colspan === -1 ? grouped.length : colspan;
    cells.push({ key: `${field.group}-${index}`, label: field.group, colspan: groupSize, rowspan: 1 });
    index += groupSize - 1;
  }

  return cells;
};

export const rowSubHeaderFields = (fields: RowField[]) => fields.filter((field) => field.group);
