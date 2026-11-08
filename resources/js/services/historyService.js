const viewer = { id: 'self', name: 'นางสาวกมลชนก วัฒนชัย', position: 'วิศวกรโยธา', unit: 'ภาควิชาวิศวกรรมโยธา' };
const people = [viewer, { id: 'unit-1', name: 'นายธนกร พิมพ์ใจ', position: 'วิศวกรไฟฟ้า', unit: 'ภาควิชาวิศวกรรมไฟฟ้า' }, { id: 'unit-2', name: 'นางสาวปวีณา ศรีสุวรรณ', position: 'นักวิชาการศึกษา', unit: 'สำนักงานคณบดี' }];
const periods = [
  { id: '2569-1', year: 2569, round: 1, label: 'รอบที่ 1/2569', range: 'ต.ค. 68 – มี.ค. 69', current: true },
  { id: '2568-2', year: 2568, round: 2, label: 'รอบที่ 2/2568', range: 'เม.ย. 68 – ก.ย. 68', current: false },
  { id: '2568-1', year: 2568, round: 1, label: 'รอบที่ 1/2568', range: 'ต.ค. 67 – มี.ค. 68', current: false },
  { id: '2567-2', year: 2567, round: 2, label: 'รอบที่ 2/2567', range: 'เม.ย. 67 – ก.ย. 67', current: false },
  { id: '2567-1', year: 2567, round: 1, label: 'รอบที่ 1/2567', range: 'ต.ค. 66 – มี.ค. 67', current: false },
  { id: '2566-2', year: 2566, round: 2, label: 'รอบที่ 2/2566', range: 'เม.ย. 66 – ก.ย. 66', current: false },
  { id: '2566-1', year: 2566, round: 1, label: 'รอบที่ 1/2566', range: 'ต.ค. 65 – มี.ค. 66', current: false },
];
const skillNames = ['การสื่อสารและประสานงาน', 'การวิเคราะห์และแก้ปัญหา', 'การวางแผนงานอย่างเป็นระบบ', 'การทำงานร่วมกัน'];
const methods = ['อบรมหลักสูตรการสื่อสารในงานวิชาชีพ', 'ฝึกปฏิบัติกับงานจริง', 'แลกเปลี่ยนเรียนรู้กับผู้เชี่ยวชาญ'];
const statusValues = ['approved', 'pending', 'draft', 'rejected', 'approved', 'pending'];
const ownPlansByRound = {
  '2569-1': [0, 1], '2568-2': [2], '2568-1': [], '2567-2': [3], '2567-1': [], '2566-2': [4, 5], '2566-1': [],
};

const makePlan = (owner, period, index, planIndex) => {
  const status = statusValues[index % statusValues.length];
  const fillMode = (index + period.year + period.round) % 3 === 0 ? 'form' : 'quick';
  const skillCount = index % 3 + 1;
  return {
    id: `${owner.id}-${period.id}-${planIndex + 1}`,
    ownerId: owner.id,
    ownerName: owner.name,
    roundId: period.id,
    roundLabel: period.label,
    roundRange: period.range,
    planCode: `IDP-${period.year}-${period.round}-${String(planIndex + 1).padStart(3, '0')}`,
    submittedAt: status === 'draft' ? '' : `${period.year - 543}-${String((index % 9) + 1).padStart(2, '0')}-${String((index % 25) + 1).padStart(2, '0')}`,
    status,
    fillMode,
    skills: Array.from({ length: skillCount }, (_, skillIndex) => {
      const skillNumber = (index + skillIndex) % skillNames.length;
      const behaviorCount = 1 + ((index + skillIndex) % 2);
      const skill = {
        id: `${owner.id}-${period.id}-${planIndex}-skill-${skillIndex}`,
        name: skillNames[skillNumber],
        actualLevel: Math.max(1, 2 + ((index + skillIndex) % 2)),
        expectedLevel: 4,
        behaviors: Array.from({ length: behaviorCount }, (_, behaviorIndex) => ({
          id: `${skillNumber}-${behaviorIndex}`,
          level: 2 + behaviorIndex,
          text: ['สื่อสารข้อมูลให้ผู้เกี่ยวข้องเข้าใจตรงกัน', 'วิเคราะห์สาเหตุและเสนอแนวทางแก้ไขที่เหมาะสม', 'จัดลำดับงานและติดตามผลตามเป้าหมาย'][((index + skillIndex + behaviorIndex) % 3)],
          methods: [methods[(index + skillIndex + behaviorIndex) % methods.length], ...(behaviorIndex === 1 ? [methods[(index + 1) % methods.length]] : [])],
        })),
      };
      if (fillMode === 'form') {
        const filled = (index + skillIndex) % 2 === 0;
        const formFields = filled
          ? [{ label: 'ชื่อ', value: owner.name }, { label: 'รหัสพนักงาน', value: 'EN-00428' }, { label: 'ตำแหน่ง', value: owner.position }]
          : [{ label: 'ชื่อ', value: '' }, { label: 'รหัสพนักงาน', value: '' }, { label: 'ตำแหน่ง', value: '' }];
        const makeMethod = (methodIndex, type, formTitle, sectionTitle) => ({
          name: methods[methodIndex % methods.length], type, formTitle, filled,
          sections: [
            { title: 'ข้อมูลผู้รับการพัฒนา', badge: 'ดึงจากระบบ', fields: formFields },
            { title: 'ข้อมูลประกอบการวางแผน', badge: 'จากสมรรถนะที่ต้องพัฒนา', fields: [{ label: 'ชื่อสมรรถนะ', value: filled ? skill.name : '' }, { label: 'Gap', value: filled ? `ระดับ ${skill.actualLevel} → ${skill.expectedLevel}` : '' }] },
            { title: sectionTitle, fields: [], repeatable: [0, 1].map((topicIndex) => ({ title: `${sectionTitle === 'ผู้สอนงาน' ? 'หัวข้อการสอนงาน' : 'หัวข้อกิจกรรม'} ${topicIndex + 1}`, fields: [{ label: 'หัวข้อทักษะ / ประเด็น', value: filled ? skill.name : '' }, { label: 'เป้าหมายในการพัฒนา', value: filled ? 'นำทักษะไปประยุกต์ใช้กับงานจริง' : '' }, { label: 'ช่วงเวลาดำเนินการ', value: filled ? `ไตรมาส ${topicIndex + 1}` : '' }] })) },
          ],
        });
        skill.methods = [makeMethod(index + skillIndex, 'Coaching', 'แบบฟอร์มการสอนงาน (Coaching)', 'ผู้สอนงาน')];
      }
      return skill;
    }),
    approvals: status === 'draft' ? [] : [
      { approver: 'หัวหน้าหน่วยงาน', status: status === 'rejected' ? 'ไม่อนุมัติ' : 'อนุมัติแล้ว', date: `${period.year - 543}-03-12`, comment: status === 'rejected' ? 'โปรดเพิ่มตัวชี้วัดผลการพัฒนาให้ชัดเจน' : 'เห็นชอบตามแผน' },
      ...(status === 'approved' ? [{ approver: 'ผู้บังคับบัญชาตามสายการประเมิน', status: 'อนุมัติแล้ว', date: `${period.year - 543}-03-20`, comment: 'อนุมัติแผน IDP' }] : status === 'pending' ? [{ approver: 'ผู้บังคับบัญชาลำดับถัดไป', status: 'รออนุมัติ', date: '', comment: '' }] : []),
    ],
  };
};

const plans = periods.flatMap((period) => {
  const ownIndexes = ownPlansByRound[period.id] || [];
  return [
    ...ownIndexes.map((index, planIndex) => makePlan(viewer, period, index, planIndex)),
    ...people.slice(1).flatMap((person, personIndex) => (personIndex === 0 && period.id === '2568-1' ? [] : [makePlan(person, period, period.year + personIndex, personIndex)])),
  ];
});

export function listPeriods() {
  return [{ id: 'all', label: 'ทุกรอบ', range: '', current: false }, ...periods.map((period) => ({ ...period }))];
}

export function getViewerScope() {
  return { scope: 'self', viewer: { ...viewer }, people: people.map((person) => ({ ...person })) };
}

export function listIdpPlans({ roundId = 'all', ownerId = 'self' } = {}) {
  return Promise.resolve(plans.filter((plan) => (ownerId === 'all' || plan.ownerId === ownerId) && (roundId === 'all' || plan.roundId === roundId)).map((plan) => ({
    ...plan,
    skills: plan.skills.map((skill) => ({ ...skill, behaviors: skill.behaviors.map((behavior) => ({ ...behavior, methods: [...behavior.methods] })) })),
  })));
}

export function getIdpPlan(id) {
  const plan = plans.find((item) => item.id === id);
  return Promise.resolve(plan ? {
    ...plan,
    skills: plan.skills.map((skill) => ({ ...skill, methods: skill.methods?.map((method) => ({ ...method, sections: method.sections.map((section) => ({ ...section, fields: section.fields.map((field) => ({ ...field })), repeatable: section.repeatable?.map((item) => ({ ...item, fields: item.fields.map((field) => ({ ...field })) })) })) })) || skill.behaviors.flatMap((behavior) => behavior.methods).map((name) => ({ name })), behaviors: skill.behaviors.map((behavior) => ({ ...behavior, methods: [...behavior.methods] })) })),
    approvals: plan.approvals.map((approval) => ({ ...approval })),
  } : null);
}

const evaluationRowsByRound = {
  '2568-2': [
    { cd: 'CC01', n: 'การสื่อสารและประสานงาน', t: 'CC', expected: 4, actual: 3, gap: -1, requiresIdp: true },
    { cd: 'MC02', n: 'การวิเคราะห์และแก้ปัญหา', t: 'MC', expected: 3, actual: 4, gap: 1, requiresIdp: false },
    { cd: 'FC03', n: 'การวางแผนงานอย่างเป็นระบบ', t: 'FC', expected: 3, actual: 3, gap: 0, requiresIdp: false },
  ],
  '2568-1': [],
  '2567-2': [
    { cd: 'CC01', n: 'การสื่อสารและประสานงาน', t: 'CC', expected: 4, actual: 2, gap: -2, requiresIdp: true },
    { cd: 'MC02', n: 'การวิเคราะห์และแก้ปัญหา', t: 'MC', expected: 3, actual: 2, gap: -1, requiresIdp: true },
  ],
  '2567-1': [
    { cd: 'CC01', n: 'การสื่อสารและประสานงาน', t: 'CC', expected: 3, actual: 4, gap: 1, requiresIdp: false },
    { cd: 'MC02', n: 'การวิเคราะห์และแก้ปัญหา', t: 'MC', expected: 3, actual: 3, gap: 0, requiresIdp: false },
    { cd: 'FC03', n: 'การวางแผนงานอย่างเป็นระบบ', t: 'FC', expected: 4, actual: 2, gap: -2, requiresIdp: true },
    { cd: 'CC04', n: 'การทำงานร่วมกัน', t: 'CC', expected: 3, actual: 4, gap: 1, requiresIdp: false },
  ],
  '2566-2': [
    { cd: 'CC01', n: 'การสื่อสารและประสานงาน', t: 'CC', expected: 3, actual: 2, gap: -1, requiresIdp: true },
    { cd: 'MC02', n: 'การวิเคราะห์และแก้ปัญหา', t: 'MC', expected: 2, actual: 4, gap: 2, requiresIdp: false },
  ],
};

export function listEvaluationResults({ roundId } = {}) {
  const source = evaluationRowsByRound[roundId] || [];
  return Promise.resolve(source.map((row, index) => ({
    ...row,
    id: `${roundId}-${row.cd}`,
    competencyId: `${roundId}-${row.cd}`,
    status: 'approved',
    achievementPercentage: row.expected ? Math.round((row.actual / row.expected) * 100) : null,
    det: `รายละเอียดสมรรถนะ${row.n} และแนวทางการประเมินตามระดับที่กำหนด`,
    checkedIndicatorKeys: [],
    levels: Array.from({ length: 5 }, (_, levelIndex) => ({ id: `${roundId}-${row.cd}-level-${levelIndex + 1}`, lvl: levelIndex + 1, indicators: [`สามารถปฏิบัติงาน${row.n}ตามระดับ ${levelIndex + 1}`, `แสดงพฤติกรรม${row.n}อย่างต่อเนื่อง`] })),
    reviewerComments: [{ reviewerId: `reviewer-${index}`, reviewerName: 'ผู้ประเมินตามสายงาน', reviewStep: 1, comment: row.requiresIdp ? 'ควรวางแผนพัฒนาสมรรถนะนี้เพิ่มเติม' : 'ผลการประเมินเป็นไปตามเกณฑ์', createdAt: '2025-09-12T09:00:00+07:00' }],
  })));
}
