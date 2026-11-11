import { formDefinitions } from '../idpFormDefinitions';

// Read-only history of the signed-in user. Data comes from:
// - GET employee.history.idp                        → { periods, plans }
// - GET employee.history.evaluation-periods         → { periods }
// - GET employee.history.evaluations {round}        → { rows } (same shape as currentUserCompetencyGaps)

const getJson = async (url) => {
  const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
};

const monthYear = (value) => {
  if (!value) return '';
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('th-TH', { month: 'short', year: '2-digit' });
};
const dateOnly = (value) => (value ? String(value).slice(0, 10) : '');
const toPeriod = (period) => ({
  ...period,
  range: [monthYear(period.startDate), monthYear(period.endDate)].filter(Boolean).join(' – '),
});
const ALL_PERIOD = { id: 'all', label: 'ทุกรอบ', range: '', current: false };

export function getViewerScope() {
  // Only the signed-in user's own history is exposed for now.
  return { scope: 'self', viewer: { id: 'self', name: '' }, people: [] };
}

let idpHistoryRequest = null;
const loadIdpHistory = () => {
  idpHistoryRequest ||= getJson(route('employee.history.idp')).catch((error) => {
    idpHistoryRequest = null;
    throw error;
  });
  return idpHistoryRequest;
};

export async function listPeriods() {
  const { periods } = await loadIdpHistory();
  return [ALL_PERIOD, ...periods.map(toPeriod)];
}

export async function listEvaluationPeriods() {
  const { periods } = await getJson(route('employee.history.evaluation-periods'));
  return periods.map(toPeriod);
}

export async function listEvaluationResults({ roundId } = {}) {
  if (!roundId || roundId === 'none') return [];
  const { rows } = await getJson(route('employee.history.evaluations', { round: roundId }));
  return rows;
}

const planStatus = (status) => {
  if (status === 'approved') return 'approved';
  if (status === 'revision_required') return 'rejected';
  if (status === 'draft') return 'draft';
  return 'pending';
};
const hasValue = (value) => (Array.isArray(value) ? value.some(hasValue) : value !== null && value !== undefined && String(value).trim() !== '');
const displayValue = (value) => {
  if (Array.isArray(value)) return value.filter(hasValue).map(displayValue).join(', ');
  if (value && typeof value === 'object') return Object.values(value).filter(hasValue).map(displayValue).join(', ');
  return hasValue(value) ? String(value) : '';
};

function methodForActivity(activity) {
  const details = activity.formDetails || {};
  const definition = formDefinitions[activity.formCode] || null;
  const sections = [{
    title: 'ข้อมูลกิจกรรม',
    fields: [
      { label: 'ชื่อกิจกรรม', value: activity.name },
      { label: 'รายละเอียด', value: activity.description },
      { label: 'วันที่เริ่มต้น', value: dateOnly(activity.startDate) },
      { label: 'วันที่สิ้นสุด', value: dateOnly(activity.endDate) },
      { label: 'น้ำหนัก (%)', value: activity.weightPercent ?? '' },
    ].filter((field) => hasValue(field.value)),
  }];
  if (definition?.detailFields?.length) {
    sections.push({
      title: definition.detailTitle || 'รายละเอียด',
      fields: definition.detailFields.map((field) => ({ label: field.label, value: displayValue(details[field.key]) })),
    });
  }
  const rows = Array.isArray(details.planRows) ? details.planRows : [];
  if (definition?.rowFields?.length && rows.length) {
    sections.push({
      title: definition.rowTitle || 'รายละเอียดแผน',
      fields: [],
      repeatable: rows.map((row, index) => ({
        title: `${definition.rowTitle || 'รายการ'} ${index + 1}`,
        fields: definition.rowFields.map((field) => ({ label: field.label, value: displayValue(row?.[field.key]) })),
      })),
    });
  }
  if (!definition) {
    const extra = Object.entries(details)
      .filter(([key, value]) => !key.startsWith('_') && !['behaviors', 'planRows'].includes(key) && hasValue(value))
      .map(([key, value]) => ({ label: key, value: displayValue(value) }));
    if (extra.length) sections.push({ title: 'รายละเอียดเพิ่มเติม', fields: extra });
  }
  const visibleSections = sections.filter((section) => section.fields.length || section.repeatable?.length);

  return {
    name: activity.name || activity.methodLabel || 'วิธีพัฒนา',
    type: activity.methodLabel || '',
    formTitle: definition?.title || activity.name || 'รายละเอียดกิจกรรม',
    filled: visibleSections.some((section) => section.fields.some((field) => hasValue(field.value))
      || section.repeatable?.some((row) => row.fields.some((field) => hasValue(field.value)))),
    sections: visibleSections,
  };
}

function behaviorsForGap(plan, gap) {
  const single = plan.gaps.length === 1;
  const byKey = new Map();
  plan.activities.forEach((activity) => {
    (activity.formDetails?.behaviors || []).forEach((behavior) => {
      if (!behavior || (!single && Number(behavior.gapId) !== gap.id)) return;
      const key = behavior.key || `${behavior.level}:${behavior.code}:${behavior.description}`;
      if (!byKey.has(key)) byKey.set(key, { id: key, level: behavior.level ?? '-', text: behavior.description || '', methods: [] });
      const name = activity.name || activity.methodLabel;
      if (name && !byKey.get(key).methods.includes(name)) byKey.get(key).methods.push(name);
    });
  });
  return [...byKey.values()];
}

function toPlan(plan, periodsById) {
  const period = periodsById.get(plan.roundId);
  const methods = plan.activities.map(methodForActivity);
  const skills = plan.gaps.map((gap, index) => ({
    id: `${plan.id}-${gap.id}`,
    name: gap.name,
    actualLevel: gap.actualLevel ?? '-',
    expectedLevel: gap.expectedLevel ?? '-',
    behaviors: behaviorsForGap(plan, gap),
    // A faculty-method plan covers several competencies with one shared set of activities.
    methods: index === 0 ? methods : [],
    sharedMethodsNote: index === 0 ? '' : `ใช้วิธีพัฒนาร่วมกับ ${plan.gaps[0].name}`,
  }));
  const approvals = plan.reviews.map((review) => ({
    approver: review.reviewerName || `ผู้อนุมัติลำดับ ${review.step}`,
    status: review.decision === 'approved' ? 'อนุมัติแล้ว' : 'ส่งกลับแก้ไข',
    date: dateOnly(review.decidedAt),
    comment: review.comment,
  }));
  if (plan.pendingReviewer) approvals.push({ approver: plan.pendingReviewer.name, status: 'รออนุมัติ', date: '', comment: '' });

  return {
    id: plan.id,
    ownerId: 'self',
    roundId: plan.roundId,
    roundLabel: period?.label || 'ไม่ระบุรอบ',
    roundRange: period?.range || '',
    planCode: `IDP-${period?.year ?? plan.year ?? ''}-${String(plan.id).padStart(4, '0')}`,
    submittedAt: dateOnly(plan.submittedAt),
    status: planStatus(plan.status),
    fillMode: plan.fillMode,
    skills,
    approvals,
  };
}

async function allPlans() {
  const { periods, plans } = await loadIdpHistory();
  const periodsById = new Map(periods.map((period) => [period.id, toPeriod(period)]));
  return plans.map((plan) => toPlan(plan, periodsById));
}

export async function listIdpPlans({ roundId = 'all' } = {}) {
  return (await allPlans()).filter((plan) => roundId === 'all' || plan.roundId === roundId);
}

export async function getIdpPlan(id) {
  return (await allPlans()).find((plan) => plan.id === id) || null;
}
