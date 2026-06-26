<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
  formDefinitions,
  hasGroupedRowFields,
  rowHeaderCells,
  rowSubHeaderFields,
  type RowField,
} from '../../idpFormDefinitions';

type Gap = {
  id: number;
  competencyId: number;
  cd: string;
  n: string;
  t?: string;
  expected?: number;
  actual?: number;
  gap?: number;
  note?: string;
  status?: string;
  requiresIdp?: boolean;
  missingIndicators?: Array<{
    level: number;
    label: string;
    indicators: Array<{ code: string; description: string }>;
  }>;
};

type Method = { key: string; label: string; desc?: string };
type DevelopmentTool = { id: number; code?: string; focusType: string; title: string; formCode?: string; isActive?: boolean };
type Catalog = {
  id: number;
  code?: string;
  name: string;
  deliveryType?: string;
  formCode?: string;
  competencyIds?: number[];
  description?: string;
  isActive?: boolean;
};
type SupervisorChainOption = {
  id: number;
  step: number;
  label: string;
  name: string;
  position?: string;
};
type FormDetails = Record<string, any>;
type Activity = {
  clientKey: string;
  methodKey: string;
  developmentToolId: number | null;
  learningCatalogId: number | null;
  activityName: string;
  activityDescription: string;
  documentReferenceNumber: string;
  weightPercent: number | '';
  startDate: string;
  endDate: string;
  formCode: string;
  formDetails: FormDetails;
};
type Plan = {
  competencyGapId: number;
  goal: string;
  successCriteria: string;
  status: string;
  submissionVersion: number;
  currentReviewStep: number | null;
  rejectComment: string;
  activities: Activity[];
};

const props = defineProps<{
  learningMethods?: Method[];
  idpLearningMethods?: DevelopmentTool[];
  learningCatalogs?: Catalog[];
  gaps?: Gap[];
  idp?: { status?: string; items?: Array<Omit<Plan, 'activities'> & { activities?: Activity[] }> } | null;
  user?: any;
}>();

const page = usePage();
const plans = ref<Plan[]>([]);
const selectedGapId = ref<number | null>(null);
const activeFormActivityKey = ref<string | null>(null);
const showCoachingApproachHelp = ref(false);
const saveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const lastSavedAt = ref('');
const lastSavedSignature = ref('');
const queuedSave = ref(false);
let activitySequence = 0;
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

const idpGaps = computed(() => (props.gaps || [])
  .filter((gap) => gap.requiresIdp && Number(gap.gap) < 0 && ['approved', 'dean_approved'].includes(gap.status || ''))
  .sort((a, b) => String(a.cd).localeCompare(String(b.cd))));
const methods = computed(() => props.learningMethods || []);
const activeTools = computed(() => (props.idpLearningMethods || []).filter((tool) => tool.isActive !== false));
const activeCatalogs = computed(() => (props.learningCatalogs || []).filter((catalog) => catalog.isActive !== false));
const supervisorChainOptions = computed<SupervisorChainOption[]>(() => props.user?.supervisorChain || []);
const selectedGap = computed(() => idpGaps.value.find((gap) => gap.id === selectedGapId.value) || null);
const selectedPlan = computed(() => plans.value.find((plan) => plan.competencyGapId === selectedGapId.value) || null);
const isReviewStatus = (status: string) => /^review_step_[123]$/.test(status);
const isPlanLocked = (plan: Plan | null) =>
  plan?.status === 'approved' || isReviewStatus(plan?.status || '');
const selectedPlanLocked = computed(() => isPlanLocked(selectedPlan.value));
const errors = computed(() => page.props.errors || {});
const coachingApproachDescriptions = [
  {
    code: 'A',
    title: 'ส่งเสริม',
    summary: 'เน้นเสริมจุดแข็งและพฤติกรรมที่ดีให้ชัดขึ้น',
    items: [
      'ความรับผิดชอบ: รับผิดชอบต่อหน้าที่และการกระทำของตนเอง',
      'ความซื่อสัตย์และจริยธรรม: ยึดมั่นในจริยธรรมในการทำงาน',
      'ความขยันหมั่นเพียร: มีความพยายามและมุ่งมั่นในการทำงานเพื่อบรรลุเป้าหมาย',
      'การทำงานเป็นทีม: ทำงานร่วมกับผู้อื่นได้อย่างมีประสิทธิภาพ',
      'ความคิดสร้างสรรค์และนวัตกรรม: คิดค้นวิธีการใหม่ ๆ ในการทำงาน',
      'การพัฒนาตนเอง: เรียนรู้และพัฒนาทักษะใหม่อย่างต่อเนื่อง',
      'การสื่อสารที่มีประสิทธิภาพ: สื่อสารได้อย่างชัดเจน',
      'การคิดเชิงวิพากษ์: วิเคราะห์และประเมินสถานการณ์ได้รอบคอบ',
      'ความยืดหยุ่นและการปรับตัว: ปรับตัวได้ดีในสถานการณ์ที่เปลี่ยนแปลง',
      'การให้และรับข้อเสนอแนะ: รับข้อเสนอแนะเพื่อการพัฒนาอย่างสร้างสรรค์',
      'ความเป็นผู้นำ: นำทีมและมีอิทธิพลเชิงบวกต่อผู้อื่น',
      'การตัดสินใจที่ดี: ตัดสินใจรวดเร็วและแม่นยำในแนวทางที่เหมาะสม',
    ],
  },
  {
    code: 'B',
    title: 'สร้างสรรค์',
    summary: 'เน้นการคิดนอกกรอบและสร้างแนวทางใหม่',
    items: [
      'ความคิดนอกกรอบ: คิดต่างจากคนทั่วไปและหาแนวทางใหม่ในการแก้ปัญหา',
      'การมีจินตนาการสูง: สร้างภาพในจินตนาการและสร้างสรรค์สิ่งใหม่',
      'การยอมรับความเสี่ยง: กล้าลองสิ่งใหม่แม้มีความเสี่ยง',
      'การมองเห็นโอกาส: มองเห็นโอกาสในสถานการณ์ต่าง ๆ',
      'ความยืดหยุ่น: ปรับตัวและปรับวิธีทำงานตามสถานการณ์',
      'ความอยากรู้อยากเห็น: สนใจเรียนรู้สิ่งใหม่อยู่เสมอ',
      'การมองโลกในแง่บวก: เห็นโอกาสและไม่ย่อท้อต่ออุปสรรค',
      'การคิดวิเคราะห์: วิเคราะห์และใช้ข้อมูลสร้างสรรค์ผลงาน',
      'การทำงานร่วมกับผู้อื่น: รับฟังและแลกเปลี่ยนความคิดเห็นได้ดี',
      'ความอดทนและความพยายาม: มุ่งมั่นแม้เจออุปสรรค',
      'ความมั่นใจในตนเอง: เชื่อมั่นในความคิดและความสามารถของตนเอง',
      'การรับข้อเสนอแนะ: รับฟังข้อเสนอแนะเพื่อปรับปรุงผลงาน',
    ],
  },
  {
    code: 'C',
    title: 'กระตุ้น',
    summary: 'เน้นกระตุ้นให้เกิดความมั่นใจ แรงจูงใจ และการลงมือทำ',
    items: [
      'ความรับผิดชอบ: กระตุ้นให้รับผิดชอบต่อหน้าที่และการกระทำของตนเอง',
      'ความคิดสร้างสรรค์: ส่งเสริมให้คิดนอกกรอบและเสนอแนวคิดใหม่',
      'การทำงานเป็นทีม: กระตุ้นการทำงานร่วมกันและแบ่งปันข้อมูล',
      'การพัฒนาตนเอง: สนับสนุนให้เรียนรู้และพัฒนาทักษะใหม่อย่างต่อเนื่อง',
      'ความขยันหมั่นเพียร: กระตุ้นให้มุ่งมั่นและพยายามบรรลุเป้าหมาย',
      'การคิดเชิงวิพากษ์: ส่งเสริมการวิเคราะห์และประเมินสถานการณ์อย่างรอบคอบ',
      'ความมั่นใจในตนเอง: กระตุ้นให้กล้าตัดสินใจในสถานการณ์ต่าง ๆ',
      'การให้และรับข้อเสนอแนะ: สนับสนุนการแลกเปลี่ยนข้อเสนอแนะเพื่อพัฒนา',
      'การจัดการเวลา: กระตุ้นให้บริหารเวลาอย่างมีประสิทธิภาพ',
      'ความยืดหยุ่นและการปรับตัว: ส่งเสริมการปรับตัวต่อสถานการณ์เปลี่ยนแปลง',
      'ความเป็นผู้นำ: กระตุ้นศักยภาพด้านการนำทีม',
      'การสื่อสารที่มีประสิทธิภาพ: สนับสนุนการสื่อสารอย่างชัดเจน',
    ],
  },
  {
    code: 'D',
    title: 'แก้ไขปัญหา',
    summary: 'เน้นวิเคราะห์ปัญหา เลือกวิธีแก้ และตัดสินใจอย่างมีข้อมูล',
    items: [
      'การคิดเชิงวิพากษ์: วิเคราะห์ข้อมูลและสถานการณ์อย่างรอบคอบ',
      'ความคิดสร้างสรรค์: คิดนอกกรอบและเสนอวิธีใหม่ในการแก้ปัญหา',
      'ความสามารถในการวิเคราะห์: ใช้เครื่องมือ เช่น SWOT เพื่อหาสาเหตุและผลกระทบของปัญหา',
      'ความมั่นใจในตนเอง: มั่นใจในการตัดสินใจและการกระทำแม้ในสถานการณ์ยาก',
      'ความยืดหยุ่น: ปรับวิธีหรือแผนตามสถานการณ์และข้อมูลใหม่',
      'การรวบรวมข้อมูล: จัดการข้อมูลที่เกี่ยวข้องเพื่อสนับสนุนการตัดสินใจ',
      'การทำงานเป็นทีม: ทำงานร่วมกับผู้อื่นเพื่อหาวิธีแก้ปัญหา',
      'ทักษะการสื่อสาร: สื่อสารปัญหาและแนวทางแก้ไขให้ทีมเข้าใจ',
      'การจัดลำดับความสำคัญ: กำหนดลำดับการแก้ไขอย่างมีประสิทธิภาพ',
      'การใช้เครื่องมือและเทคนิค: ใช้เครื่องมือที่ช่วยแก้ปัญหา เช่น การวิเคราะห์ข้อมูลหรือการสร้างแผนภูมิ',
      'ความอดทนและความพยายาม: ไม่ย่อท้อต่อปัญหาและความท้าทาย',
      'การเรียนรู้จากประสบการณ์: นำบทเรียนจากความผิดพลาดมาปรับปรุงแนวทางในอนาคต',
    ],
  },
];

const nextClientKey = () => `activity-${Date.now()}-${++activitySequence}`;
const blankActivity = (): Activity => ({
  clientKey: nextClientKey(),
  methodKey: '',
  developmentToolId: null,
  learningCatalogId: null,
  activityName: '',
  activityDescription: '',
  documentReferenceNumber: '',
  weightPercent: '',
  startDate: '',
  endDate: '',
  formCode: '',
  formDetails: {},
});
const savedPlans = () => new Map((props.idp?.items || []).map((item) => [item.competencyGapId, item]));

const hydratePlans = () => {
  const saved = savedPlans();
  plans.value = idpGaps.value.map((gap) => {
    const item = saved.get(gap.id);
    return {
      competencyGapId: gap.id,
      goal: item?.goal || '',
      successCriteria: item?.successCriteria || '',
      status: item?.status || 'draft',
      submissionVersion: item?.submissionVersion || 0,
      currentReviewStep: item?.currentReviewStep || null,
      rejectComment: item?.rejectComment || '',
      activities: (item?.activities || []).map((activity) => ({
        ...activity,
        clientKey: nextClientKey(),
        formCode: activity.formCode || '',
        formDetails: normalizeFormDetails(activity.formCode || '', activity.formDetails || {}),
      })),
    };
  });
  if (!selectedGapId.value || !plans.value.some((plan) => plan.competencyGapId === selectedGapId.value)) {
    selectedGapId.value = plans.value[0]?.competencyGapId || null;
  }
  lastSavedSignature.value = signature();
};

const gapForPlan = (plan: Plan) => idpGaps.value.find((gap) => gap.id === plan.competencyGapId);
const weightTotal = (plan: Plan) => plan.activities.reduce((sum, item) => sum + Number(item.weightPercent || 0), 0);
const missingIndicators = (gap: Gap) => (gap.missingIndicators || []).flatMap((group) =>
  group.indicators.map((indicator) => ({ ...indicator, level: group.level })));
const formatNumber = (value: unknown) => {
  const number = Number(value);
  return Number.isFinite(number) ? number.toFixed(Number.isInteger(number) ? 0 : 2) : '-';
};
const focusType = (methodKey: string) => {
  const normalized = methodKey.toLowerCase();
  if (normalized.includes('experiential') || normalized.includes('experience')) return 'experiential';
  if (normalized.includes('social')) return 'social';
  if (normalized.includes('formal')) return 'formal';
  return normalized;
};
const toolsFor = (activity: Activity) => activeTools.value.filter((tool) => tool.focusType === focusType(activity.methodKey));
const catalogsFor = (gap: Gap) => activeCatalogs.value.filter((catalog) =>
  (catalog.competencyIds || []).includes(gap.competencyId));
const methodLabel = (key: string) => methods.value.find((method) => method.key === key)?.label || key;
const toolFor = (activity: Activity) => activeTools.value.find((item) => item.id === Number(activity.developmentToolId));
const catalogFor = (activity: Activity) => activeCatalogs.value.find((item) => item.id === Number(activity.learningCatalogId));
const normalizeCode = (code?: string) => String(code || '').trim().padStart(2, '0');
const formCodeForActivity = (activity: Activity) => {
  if (['experiential', 'social'].includes(focusType(activity.methodKey))) {
    const tool = toolFor(activity);
    if (tool && typeof tool.formCode === 'string') {
      return tool.formCode;
    }
    const code = normalizeCode(toolFor(activity)?.code);
    return ({
      '01': 'form_3_project_assignment',
      '02': 'form_4_ojt',
      '03': 'form_5_coaching',
      '04': 'form_6_mentoring',
      '05': 'form_7_group_activity',
      '06': 'form_8_feedback',
      '07': 'form_9_field_trip',
    } as Record<string, string>)[code] || '';
  }

  if (focusType(activity.methodKey) === 'formal') {
    return catalogFor(activity)?.formCode || activity.formCode || 'form_10_training';
  }

  return '';
};
const effectiveFormCode = (activity: Activity) => {
  if (['experiential', 'social'].includes(focusType(activity.methodKey)) && activity.developmentToolId) {
    return formCodeForActivity(activity);
  }
  return activity.formCode || formCodeForActivity(activity);
};
const formDefinitionFor = (activity: Activity) => formDefinitions[effectiveFormCode(activity)] || null;
const hasActivityForm = (activity: Activity) => Boolean(effectiveFormCode(activity) && formDefinitionFor(activity));
const isFormSaved = (activity: Activity) => Boolean(activity.formDetails?._saved);
const shouldShowDetailField = (field: { showWhen?: { key: string; value: string } }, detail: Record<string, any>) =>
  !field.showWhen || detail[field.showWhen.key] === field.showWhen.value;
const toggleChoice = (row: Record<string, any>, key: string, choice: string) => {
  row[key] = row[key] === choice ? '' : choice;
};
const multiChoices = (row: Record<string, any>, key: string) => {
  if (!Array.isArray(row[key])) row[key] = [];
  return row[key] as string[];
};
const toggleMultiChoice = (row: Record<string, any>, key: string, choice: string, checked: boolean) => {
  const choices = multiChoices(row, key);
  row[key] = checked
    ? Array.from(new Set([...choices, choice]))
    : choices.filter((item) => item !== choice);
};
const fixedTopicLabel = (activity: Activity | null, row: Record<string, any>, rowIndex: number) => {
  const formCode = activity ? effectiveFormCode(activity) : '';
  if (formCode === 'form_7_group_activity') {
    return row.fixedTopicLabel ||
      (rowIndex === 0
        ? '1) หัวข้อการเรียนรู้ฯ'
        : rowIndex === 1
          ? '2) ผู้รับการพัฒนาจัดทำรายงานสรุปผลการแลกเปลี่ยนเรียนรู้'
          : '');
  }
  if (formCode === 'form_9_field_trip') {
    return row.fixedTopicLabel ||
      (rowIndex === 0
        ? '1) ประเด็นที่ต้องการพัฒนา'
        : rowIndex === 1
          ? '2) ผู้รับการพัฒนาจัดทำรายงานสรุปผลการศึกษาดูงาน'
          : '');
  }
  return row.fixedTopicLabel || '';
};
const hasFixedFormRows = (activity: Activity | null) =>
  activity ? ['form_7_group_activity', 'form_9_field_trip'].includes(effectiveFormCode(activity)) : false;
const hasLockedFormRows = (activity: Activity | null) =>
  activity ? ['form_7_group_activity', 'form_9_field_trip', 'form_10_training'].includes(effectiveFormCode(activity)) : false;
const hasDetailFields = (activity: Activity | null) =>
  activity ? Boolean(formDefinitionFor(activity)?.detailFields.length) : false;
const shouldPlaceDetailAtBottom = (activity: Activity | null) =>
  activity ? effectiveFormCode(activity) === 'form_9_field_trip' && hasDetailFields(activity) : false;
const isFixedTopicField = (field: RowField, rowIndex: number) =>
  activeFormActivity.value &&
  hasFixedFormRows(activeFormActivity.value) &&
  ['learningTopic', 'skillTopic'].includes(field.key) &&
  rowIndex < 2;
const handleDetailChoiceChange = (fieldKey: string) => {
  if (!activeFormActivity.value) return;
  const detail = activeFormActivity.value.formDetails.detail;
  const selectedValue = detail[fieldKey];

  formDefinitionFor(activeFormActivity.value)?.detailFields
    .filter((field) => field.showWhen?.key === fieldKey && field.showWhen.value !== selectedValue)
    .forEach((field) => {
      detail[field.key] = '';
    });
};
const planStatusLabel = (plan: Plan | null) => ({
  review_step_1: 'รอผู้อนุมัติลำดับ 1',
  review_step_2: 'รอผู้อนุมัติลำดับ 2',
  review_step_3: 'รอผู้อนุมัติลำดับ 3',
  approved: 'อนุมัติครบทุกลำดับแล้ว',
  revision_required: 'ตีกลับให้แก้ไข',
}[plan?.status || ''] || 'ร่าง');

const addActivity = (plan: Plan) => {
  plan.activities.push(blankActivity());
};
const removeActivity = (plan: Plan, clientKey: string) => {
  plan.activities = plan.activities.filter((activity) => activity.clientKey !== clientKey);
};
const changeMethod = (activity: Activity) => {
  activity.developmentToolId = null;
  activity.learningCatalogId = null;
  activity.activityName = '';
  activity.activityDescription = '';
  activity.formCode = '';
  activity.formDetails = {};
};
const chooseTool = (activity: Activity) => {
  const tool = activeTools.value.find((item) => item.id === Number(activity.developmentToolId));
  activity.activityName = tool ? `${tool.code ? `${tool.code} · ` : ''}${tool.title}` : '';
  activity.formCode = formCodeForActivity(activity);
  activity.formDetails = defaultFormDetails(activity.formCode);
};
const chooseCatalog = (activity: Activity) => {
  const catalog = activeCatalogs.value.find((item) => item.id === Number(activity.learningCatalogId));
  activity.activityName = catalog?.name || '';
  activity.activityDescription = catalog?.description || '';
  activity.formCode = formCodeForActivity(activity);
  activity.formDetails = {
    ...defaultFormDetails(activity.formCode),
    planRows: defaultPlanRows(activity.formCode).map((row, index) => index === 0 ? {
      ...row,
      trainingType: catalog?.deliveryType === 'in_class' ? 'In-class Training' : 'e-Learning',
      courseCode: catalog?.code || '',
      courseName: catalog?.name || '',
      cost: '',
    } : row),
  };
};
const defaultPlanRows = (formCode: string) => {
  if (formCode === 'form_7_group_activity') {
    return [
      { fixedTopicLabel: '1) หัวข้อการเรียนรู้ฯ', learningTopic: '' },
      { fixedTopicLabel: '2) ผู้รับการพัฒนาจัดทำรายงานสรุปผลการแลกเปลี่ยนเรียนรู้', learningTopic: '' },
    ];
  }
  if (formCode === 'form_9_field_trip') {
    return [
      { fixedTopicLabel: '1) ประเด็นที่ต้องการพัฒนา', skillTopic: '' },
      { fixedTopicLabel: '2) ผู้รับการพัฒนาจัดทำรายงานสรุปผลการศึกษาดูงาน', skillTopic: '' },
    ];
  }
  if (formCode === 'form_10_training') {
    return Array.from({ length: 6 }, () => ({}));
  }

  return [{}];
};
const defaultFormDetails = (formCode: string): FormDetails => ({
  detail: {},
  planRows: defaultPlanRows(formCode),
  developmentResult: {},
  assessment: {},
  acknowledgements: {},
  _saved: false,
  _formCode: formCode,
});
const normalizeFormDetails = (formCode: string, details?: FormDetails): FormDetails => {
  const source = details || {};
  const rows = Array.isArray(source.planRows) && source.planRows.length
    ? source.planRows
    : defaultPlanRows(formCode);
  const rowsWithFormDefaults = formCode === 'form_10_training'
    ? defaultPlanRows(formCode).map((defaultRow, index) => ({
      ...defaultRow,
      ...(rows[index] || {}),
      trainingHours: rows[index]?.trainingHours || rows[index]?.trainingDays || defaultRow.trainingHours,
    }))
    : ['form_7_group_activity', 'form_9_field_trip'].includes(formCode) && !rows.some((row: Record<string, any>) => row?.fixedTopicLabel)
    ? defaultPlanRows(formCode).map((defaultRow, index) => ({
      ...(rows[index] || {}),
      ...defaultRow,
      learningTopic: rows[index]?.learningTopic || defaultRow.learningTopic,
      skillTopic: rows[index]?.skillTopic || defaultRow.skillTopic,
    }))
    : rows;
  const normalizedRows = rowsWithFormDefaults.map((row: Record<string, any>) => {
    const cloned = { ...(row || {}) };
    const legacyApproaches = ['A', 'B', 'C', 'D'].filter((code) => cloned[`approach${code}`]);
    if (!Array.isArray(cloned.coachingApproaches) && legacyApproaches.length) {
      cloned.coachingApproaches = legacyApproaches;
    }
    return cloned;
  });

  return {
    ...defaultFormDetails(formCode),
    ...source,
    detail: { ...(source.detail || {}) },
    planRows: normalizedRows,
    developmentResult: { ...(source.developmentResult || {}) },
    assessment: { ...(source.assessment || {}) },
    acknowledgements: { ...(source.acknowledgements || {}) },
  };
};
const openActivityForm = (activity: Activity) => {
  if (!activity.formCode) {
    activity.formCode = effectiveFormCode(activity);
  }
  activity.formDetails = normalizeFormDetails(activity.formCode, activity.formDetails);
  showCoachingApproachHelp.value = false;
  activeFormActivityKey.value = activity.clientKey;
};
const closeActivityForm = () => {
  showCoachingApproachHelp.value = false;
  activeFormActivityKey.value = null;
};
const activeFormActivity = computed(() =>
  selectedPlan.value?.activities.find((activity) => activity.clientKey === activeFormActivityKey.value) || null);
const saveActivityForm = () => {
  if (activeFormActivity.value) {
    activeFormActivity.value.formDetails = {
      ...activeFormActivity.value.formDetails,
      _saved: true,
    };
  }
  closeActivityForm();
};
const addFormRow = (activity: Activity) => {
  if (hasLockedFormRows(activity)) return;
  const rows = Array.isArray(activity.formDetails?.planRows) ? activity.formDetails.planRows : [];
  activity.formDetails.planRows = [...rows, {}];
};
const removeFormRow = (activity: Activity, index: number) => {
  if (hasLockedFormRows(activity)) return;
  const rows = Array.isArray(activity.formDetails?.planRows) ? activity.formDetails.planRows : [];
  activity.formDetails.planRows = rows.filter((_, rowIndex) => rowIndex !== index);
};
const formRows = (activity: Activity) => {
  if (!Array.isArray(activity.formDetails?.planRows) || activity.formDetails.planRows.length === 0) {
    activity.formDetails.planRows = [{}];
  }
  return activity.formDetails.planRows;
};

const requestPayload = () => ({
  items: plans.value.map((plan) => ({
    competencyGapId: plan.competencyGapId,
    goal: plan.goal,
    successCriteria: plan.successCriteria,
    activities: plan.activities.map(({ clientKey, ...activity }) => ({
      ...activity,
      weightPercent: activity.weightPercent === '' ? null : Number(activity.weightPercent),
    })),
  })),
});
const signature = () => JSON.stringify(requestPayload());

watch(() => [props.gaps, props.idp], hydratePlans, { immediate: true });

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

const performAutoSave = async () => {
  if (plans.value.every((plan) => isPlanLocked(plan)) || saveState.value === 'saving') {
    queuedSave.value = saveState.value === 'saving';
    return;
  }
  const nextSignature = signature();
  if (nextSignature === lastSavedSignature.value) return;

  saveState.value = 'saving';
  try {
    const response = await fetch(route('employee.idp.draft'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(requestPayload()),
    });

    if (!response.ok) {
      throw new Error(`Auto-save failed with status ${response.status}`);
    }

    lastSavedSignature.value = nextSignature;
    lastSavedAt.value = new Intl.DateTimeFormat('th-TH', { hour: '2-digit', minute: '2-digit' }).format(new Date());
    saveState.value = 'saved';
  } catch {
    saveState.value = 'error';
  } finally {
    if (queuedSave.value || signature() !== lastSavedSignature.value) {
      queuedSave.value = false;
      window.setTimeout(performAutoSave, 250);
    }
  }
};

watch(plans, () => {
  if (plans.value.every((plan) => isPlanLocked(plan)) || signature() === lastSavedSignature.value) return;
  saveState.value = 'idle';
  if (autoSaveTimer) window.clearTimeout(autoSaveTimer);
  autoSaveTimer = window.setTimeout(performAutoSave, 1000);
}, { deep: true });

const planIssue = (plan: Plan): string => {
  if (!plan.goal.trim()) return 'ยังไม่ได้ระบุเป้าหมาย';
  if (!plan.successCriteria.trim()) return 'ยังไม่ได้ระบุตัวชี้วัดความสำเร็จ';
  if (plan.activities.length === 0) return 'ยังไม่มีกิจกรรม';
  if (Math.round(weightTotal(plan) * 100) / 100 !== 100) return 'น้ำหนักกิจกรรมต้องรวม 100%';

  for (const activity of plan.activities) {
    if (!activity.methodKey || !activity.activityName || activity.weightPercent === '' || !activity.startDate || !activity.endDate) {
      return 'ข้อมูลกิจกรรมยังไม่ครบ';
    }
    if (hasActivityForm(activity) && !isFormSaved(activity)) {
      return 'ยังไม่ได้กรอกรายละเอียดฟอร์มกิจกรรม';
    }
    if (['experiential', 'social'].includes(focusType(activity.methodKey)) && !activity.developmentToolId) {
      return 'ยังไม่ได้เลือกเครื่องมือพัฒนา';
    }
    if (focusType(activity.methodKey) === 'formal' && !activity.learningCatalogId) {
      return 'ยังไม่ได้เลือกหลักสูตร';
    }
  }
  return '';
};
const submitSelectedPlan = () => {
  if (!selectedPlan.value || selectedPlanLocked.value) return;
  const issue = planIssue(selectedPlan.value);
  if (issue) {
    window.alert(issue);
    return;
  }
  if (autoSaveTimer) window.clearTimeout(autoSaveTimer);
  saveState.value = 'saving';
  router.post(route('employee.idp.submit-item'), {
    competencyGapId: selectedPlan.value.competencyGapId,
    item: {
      competencyGapId: selectedPlan.value.competencyGapId,
      goal: selectedPlan.value.goal,
      successCriteria: selectedPlan.value.successCriteria,
      activities: selectedPlan.value.activities.map(({ clientKey, ...activity }) => ({
        ...activity,
        weightPercent: activity.weightPercent === '' ? null : Number(activity.weightPercent),
      })),
    },
  }, {
    preserveScroll: true,
    onError: () => { saveState.value = 'error'; },
    onFinish: () => { saveState.value = 'idle'; },
  });
};
</script>

<template>
  <section class="idp-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">INDIVIDUAL DEVELOPMENT PLAN</p>
        <h1>แผนพัฒนา IDP</h1>
        <p class="subtitle">หนึ่งสมรรถนะต่อหนึ่งแผน และเพิ่มกิจกรรมพัฒนาได้หลายรายการ</p>
      </div>
      <div class="save-indicator" :class="saveState">
        <span class="save-dot" />
        <span v-if="selectedPlanLocked">{{ planStatusLabel(selectedPlan) }}</span>
        <span v-else-if="saveState === 'saving'">กำลังบันทึก...</span>
        <span v-else-if="saveState === 'error'">บันทึกไม่สำเร็จ</span>
        <span v-else-if="lastSavedAt">บันทึกแล้ว {{ lastSavedAt }}</span>
        <span v-else>บันทึกร่างอัตโนมัติ</span>
      </div>
    </header>

    <div v-if="idpGaps.length === 0" class="empty-state">
      <strong>ยังไม่มีสมรรถนะที่ต้องจัดทำ IDP</strong>
      <span>ระบบจะแสดงรายการเมื่อผลประเมินได้รับอนุมัติและมี Gap ติดลบ</span>
    </div>

    <div v-else class="workspace">
      <aside class="plan-nav">
        <div class="person">
          <span>{{ props.user?.sso || '-' }}</span>
          <strong>{{ props.user?.t }}{{ props.user?.n || '-' }}</strong>
          <small>{{ props.user?.p || props.user?.d || '-' }}</small>
        </div>
        <div class="nav-heading">
          <strong>สมรรถนะที่ต้องพัฒนา</strong>
          <span>{{ idpGaps.length }}</span>
        </div>
        <button
          v-for="plan in plans"
          :key="plan.competencyGapId"
          class="plan-nav-item"
          :class="{ active: selectedGapId === plan.competencyGapId }"
          type="button"
          @click="selectedGapId = plan.competencyGapId"
        >
          <span class="competency-code">{{ gapForPlan(plan)?.cd }}</span>
          <span class="competency-copy">
            <strong>{{ gapForPlan(plan)?.n }}</strong>
            <small>{{ isPlanLocked(plan) ? planStatusLabel(plan) : (planIssue(plan) || 'พร้อมส่งสมรรถนะนี้') }}</small>
          </span>
          <span class="completion-mark" :class="{ ready: !planIssue(plan) }">{{ planIssue(plan) ? '!' : '✓' }}</span>
        </button>
      </aside>

      <main class="plan-content">
        <div v-if="!selectedPlan || !selectedGap" class="select-prompt">
          <strong>เลือกสมรรถนะเพื่อจัดการแผน</strong>
          <span>แต่ละสมรรถนะจะมีเป้าหมายและรายการกิจกรรมของตัวเอง</span>
        </div>

        <template v-else>
          <section class="competency-header">
            <div>
              <span class="type-tag">{{ selectedGap.t || 'CC' }}</span>
              <h2>{{ selectedGap.cd }} · {{ selectedGap.n }}</h2>
              <p v-if="selectedGap.note">{{ selectedGap.note }}</p>
            </div>
            <div class="score-row">
              <div><span>Expected</span><strong>{{ formatNumber(selectedGap.expected) }}</strong></div>
              <div><span>Actual</span><strong>{{ formatNumber(selectedGap.actual) }}</strong></div>
              <div class="negative"><span>Gap</span><strong>{{ formatNumber(selectedGap.gap) }}</strong></div>
            </div>
          </section>

          <div v-if="selectedPlan.status === 'revision_required' && selectedPlan.rejectComment" class="revision-alert">
            <strong>หัวหน้าตีกลับให้แก้ไข</strong>
            <span>{{ selectedPlan.rejectComment }}</span>
          </div>

          <section class="section-card behavior-section">
            <header class="section-title">
              <div>
                <span class="section-number">01</span>
                <div><h3>พฤติกรรมที่ต้องพัฒนา</h3><p>ข้อมูลจากผลประเมิน แก้ไขไม่ได้</p></div>
              </div>
              <b class="section-badge">ล็อก · {{ missingIndicators(selectedGap).length }} ข้อ</b>
            </header>
            <div class="section-body indicator-list">
              <div v-for="indicator in missingIndicators(selectedGap)" :key="`${indicator.level}-${indicator.code}`" class="indicator-row">
                <span>ระดับ {{ indicator.level }} · ข้อ {{ indicator.code }}</span>
                <p>{{ indicator.description }}</p>
              </div>
              <div v-if="missingIndicators(selectedGap).length === 0" class="indicator-row">
                <span>Competency Gap</span>
                <p>พัฒนา {{ selectedGap.n }} ให้ถึงระดับที่คาดหวัง</p>
              </div>
            </div>
          </section>

          <section class="section-card goal-section">
            <header class="section-title">
              <div>
                <span class="section-number">02</span>
                <div><h3>เป้าหมายของแผน</h3><p>ใช้ร่วมกันทุกกิจกรรมในสมรรถนะนี้</p></div>
              </div>
              <b class="section-badge">ข้อมูลแผน</b>
            </header>
            <div class="section-body goal-grid">
              <label>
                <span>เป้าหมายในการพัฒนา</span>
                <textarea v-model="selectedPlan.goal" :disabled="selectedPlanLocked" rows="3" placeholder="ผลลัพธ์ด้านพฤติกรรมที่ต้องการเห็นหลังจบแผน" />
              </label>
              <label>
                <span>ตัวชี้วัดความสำเร็จ</span>
                <textarea v-model="selectedPlan.successCriteria" :disabled="selectedPlanLocked" rows="3" placeholder="ระบุหลักฐานหรือผลลัพธ์ที่ใช้ยืนยันความสำเร็จ" />
              </label>
            </div>
          </section>

          <section class="section-card activities-section">
            <header class="section-title">
              <div>
                <span class="section-number">03</span>
                <div><h3>กิจกรรมการพัฒนา</h3><p>เพิ่มได้หลายกิจกรรม น้ำหนักรวมต้องครบ 100%</p></div>
              </div>
              <div class="weight-total" :class="{ complete: weightTotal(selectedPlan) === 100 }">
                น้ำหนักรวม {{ formatNumber(weightTotal(selectedPlan)) }}%
              </div>
            </header>

            <div class="section-body">
              <div v-if="selectedPlan.activities.length === 0" class="activities-empty">
                <strong>ยังไม่มีกิจกรรมในแผนนี้</strong>
                <span>เริ่มจากเลือก Experiential, Social หรือ Formal Learning</span>
              </div>

              <article v-for="(activity, index) in selectedPlan.activities" :key="activity.clientKey" class="activity">
                <header>
                  <div class="activity-identity">
                    <span class="activity-number">{{ index + 1 }}</span>
                    <div>
                      <span>{{ methodLabel(activity.methodKey) || 'ยังไม่เลือกรูปแบบ' }}</span>
                      <strong>{{ activity.activityName || 'กิจกรรมใหม่' }}</strong>
                    </div>
                  </div>
                  <button v-if="!selectedPlanLocked" type="button" title="ลบกิจกรรม" @click="removeActivity(selectedPlan, activity.clientKey)">×</button>
                </header>
                <div class="activity-form">
                <label>
                  <span>รูปแบบการเรียนรู้</span>
                  <select v-model="activity.methodKey" :disabled="selectedPlanLocked" @change="changeMethod(activity)">
                    <option value="">เลือกจากรายการ: รูปแบบการเรียนรู้</option>
                    <option v-for="method in methods" :key="method.key" :value="method.key">{{ method.label }}</option>
                  </select>
                </label>

                <label v-if="['experiential', 'social'].includes(focusType(activity.methodKey))">
                  <span>เครื่องมือพัฒนา</span>
                  <select v-model.number="activity.developmentToolId" :disabled="selectedPlanLocked" @change="chooseTool(activity)">
                    <option :value="null">เลือกจากรายการ: เครื่องมือพัฒนา</option>
                    <option v-for="tool in toolsFor(activity)" :key="tool.id" :value="tool.id">
                      {{ tool.code ? `${tool.code} · ` : '' }}{{ tool.title }}
                    </option>
                  </select>
                </label>

                <label v-else-if="focusType(activity.methodKey) === 'formal'">
                  <span>Learning Catalog</span>
                  <select v-model.number="activity.learningCatalogId" :disabled="selectedPlanLocked" @change="chooseCatalog(activity)">
                    <option :value="null">เลือกจากรายการ: หลักสูตร</option>
                    <option v-for="catalog in catalogsFor(selectedGap)" :key="catalog.id" :value="catalog.id">
                      {{ catalog.code ? `${catalog.code} · ` : '' }}{{ catalog.name }}
                    </option>
                  </select>
                </label>

                <label v-else>
                  <span>แหล่งกิจกรรม</span>
                  <input disabled value="เลือกรูปแบบการเรียนรู้ก่อน" />
                </label>

                <label class="wide">
                  <span>ชื่อกิจกรรม</span>
                  <input v-model="activity.activityName" :disabled="selectedPlanLocked" placeholder="ชื่อกิจกรรมที่ต้องดำเนินการ" />
                </label>
                <label class="document-reference-field">
                  <span>เอกสารประกอบหมายเลข</span>
                  <input
                    v-model="activity.documentReferenceNumber"
                    :disabled="selectedPlanLocked"
                    maxlength="255"
                  />
                </label>
                <label>
                  <span>สัดส่วนกิจกรรม (%)</span>
                  <input
                    v-model.number="activity.weightPercent"
                    :disabled="selectedPlanLocked"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="เช่น 30"
                  />
                </label>
                <label>
                  <span>วันที่เริ่ม</span>
                  <input v-model="activity.startDate" :disabled="selectedPlanLocked" type="date" />
                </label>
                <label>
                  <span>วันที่สิ้นสุด</span>
                  <input v-model="activity.endDate" :disabled="selectedPlanLocked" type="date" />
                </label>
                <div class="activity-detail-action wide">
                  <div>
                    <strong>{{ formDefinitionFor(activity)?.title || 'ยังไม่มีแบบฟอร์มรายละเอียดสำหรับหัวข้อนี้' }}</strong>
                    <span :class="{ saved: isFormSaved(activity) }">
                      {{ !hasActivityForm(activity) ? 'หัวข้อนี้ยังไม่เปิดให้กรอกรายละเอียดฟอร์ม' : isFormSaved(activity) ? 'กรอกรายละเอียดฟอร์มแล้ว' : 'ยังไม่ได้กรอกรายละเอียดฟอร์ม' }}
                    </span>
                  </div>
                  <button
                    type="button"
                    :disabled="!hasActivityForm(activity) || selectedPlanLocked"
                    @click="openActivityForm(activity)"
                  >
                    กรอกรายละเอียดฟอร์ม
                  </button>
                </div>
                </div>
              </article>

              <button v-if="!selectedPlanLocked" class="add-activity" type="button" @click="addActivity(selectedPlan)">
                <span>+</span> เพิ่มกิจกรรมพัฒนา
              </button>
            </div>
          </section>

          <div v-if="Object.keys(errors).length" class="error-box">
            กรุณาตรวจสอบข้อมูลที่กรอก ระบบยังไม่สามารถบันทึกหรือส่งแผนได้
          </div>
        </template>
      </main>
    </div>

    <div v-if="activeFormActivity && formDefinitionFor(activeFormActivity) && selectedGap && selectedPlan" class="form-modal-backdrop">
      <div class="form-modal">
        <header class="form-modal-top">
          <div>
            <strong>กรอกรายละเอียดฟอร์มกิจกรรม</strong>
            <span>แบบฟอร์มที่ {{ formDefinitionFor(activeFormActivity)?.number }} · {{ formDefinitionFor(activeFormActivity)?.title }}</span>
          </div>
          <button type="button" @click="closeActivityForm">×</button>
        </header>

        <div class="form-paper">
          <section class="form-title-band">
            <div>
              <h3>แบบฟอร์มที่ {{ formDefinitionFor(activeFormActivity)?.number }} {{ formDefinitionFor(activeFormActivity)?.title }}</h3>
              <p>{{ formDefinitionFor(activeFormActivity)?.focus }}</p>
            </div>
            <label>
              <span>เอกสารประกอบหมายเลข</span>
              <input v-model="activeFormActivity.documentReferenceNumber" />
            </label>
          </section>

          <section class="form-block readonly-block">
            <header>
              <h4>ข้อมูลผู้รับการพัฒนา</h4>
              <span>ดึงจากระบบ</span>
            </header>
            <div class="form-grid three">
              <label>
                <span>ชื่อผู้รับการพัฒนา</span>
                <input :value="`${props.user?.t || ''}${props.user?.n || '-'}`" disabled />
              </label>
              <label>
                <span>รหัสพนักงาน</span>
                <input :value="props.user?.sso || '-'" disabled />
              </label>
              <label>
                <span>ตำแหน่ง</span>
                <input :value="props.user?.p || '-'" disabled />
              </label>
              <label class="wide">
                <span>สังกัด</span>
                <input :value="props.user?.d || '-'" disabled />
              </label>
            </div>
          </section>

          <section class="form-block readonly-block">
            <header>
              <h4>ข้อมูลประกอบการวางแผน</h4>
              <span>จากสมรรถนะที่ต้องพัฒนา</span>
            </header>
            <div class="form-grid three">
              <label>
                <span>รหัส</span>
                <input :value="selectedGap.cd" disabled />
              </label>
              <label>
                <span>ชื่อสมรรถนะ</span>
                <input :value="selectedGap.n" disabled />
              </label>
              <label>
                <span>Gap</span>
                <input :value="formatNumber(selectedGap.gap)" disabled />
              </label>
            </div>
          </section>

          <section v-if="hasDetailFields(activeFormActivity) && !shouldPlaceDetailAtBottom(activeFormActivity)" class="form-block">
            <header>
              <h4>{{ formDefinitionFor(activeFormActivity)?.detailTitle }}</h4>
              <span>พนักงานกรอกตอนทำแผน</span>
            </header>
            <div class="form-grid">
              <label
                v-for="field in formDefinitionFor(activeFormActivity)?.detailFields"
                :key="field.key"
                v-show="shouldShowDetailField(field, activeFormActivity.formDetails.detail)"
                :class="{ wide: field.type === 'area' }"
              >
                <span>{{ field.label }}</span>
                <textarea
                  v-if="field.type === 'area'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  rows="3"
                />
                <select
                  v-else-if="field.type === 'choice'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  @change="handleDetailChoiceChange(field.key)"
                >
                  <option value="">เลือกจากรายการ</option>
                  <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
                </select>
                <select
                  v-else-if="field.type === 'supervisor-chain'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  :disabled="supervisorChainOptions.length === 0"
                >
                  <option value="">
                    {{ supervisorChainOptions.length ? 'เลือกจากรายการ: สายการบังคับบัญชา' : 'ยังไม่ได้กำหนดสายการบังคับบัญชา' }}
                  </option>
                  <option v-for="person in supervisorChainOptions" :key="person.id" :value="person.id">
                    {{ person.label }} · {{ person.name }}{{ person.position ? ` (${person.position})` : '' }}
                  </option>
                </select>
                <input
                  v-else-if="field.type === 'expert-name'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  placeholder="กรอกชื่อผู้เชี่ยวชาญ"
                />
                <input v-else v-model="activeFormActivity.formDetails.detail[field.key]" />
              </label>
            </div>
          </section>

          <section class="form-block">
            <header>
              <h4>{{ formDefinitionFor(activeFormActivity)?.rowTitle }}</h4>
              <button v-if="!hasLockedFormRows(activeFormActivity)" type="button" @click="addFormRow(activeFormActivity)">+ เพิ่มแถว</button>
            </header>
            <div class="form-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th
                      v-if="!hasFixedFormRows(activeFormActivity)"
                      class="row-number"
                      :rowspan="hasGroupedRowFields(formDefinitionFor(activeFormActivity)?.rowFields || []) ? 2 : 1"
                    >ที่</th>
                    <th
                      v-for="cell in rowHeaderCells(formDefinitionFor(activeFormActivity)?.rowFields || [])"
                      :key="cell.key"
                      :colspan="cell.colspan"
                      :rowspan="cell.rowspan"
                    >
                      {{ cell.label }}
                      <small v-if="cell.owner">{{ cell.owner }}</small>
                    </th>
                    <th
                      v-if="!hasLockedFormRows(activeFormActivity)"
                      class="row-control"
                      :rowspan="hasGroupedRowFields(formDefinitionFor(activeFormActivity)?.rowFields || []) ? 2 : 1"
                    ></th>
                  </tr>
                  <tr v-if="hasGroupedRowFields(formDefinitionFor(activeFormActivity)?.rowFields || [])">
                    <th
                      v-for="(field, fieldIndex) in rowSubHeaderFields(formDefinitionFor(activeFormActivity)?.rowFields || [])"
                      :key="`sub-${field.key}-${field.label}-${fieldIndex}`"
                    >
                      {{ field.label }}
                      <small v-if="field.owner">{{ field.owner }}</small>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex">
                    <td v-if="!hasFixedFormRows(activeFormActivity)" class="row-number">{{ rowIndex + 1 }}</td>
                    <td
                      v-for="(field, fieldIndex) in formDefinitionFor(activeFormActivity)?.rowFields"
                      :key="`${field.key}-${field.label}-${fieldIndex}`"
                    >
                      <select v-if="field.type === 'choice'" v-model="row[field.key]">
                        <option value="">เลือกจากรายการ</option>
                        <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
                      </select>
                      <div v-else-if="field.type === 'checkbox-choice'" class="checkbox-choice-group">
                        <label v-for="choice in field.choices" :key="choice">
                          <input
                            type="checkbox"
                            :checked="row[field.key] === choice"
                            @change="toggleChoice(row, field.key, choice)"
                          />
                          <span>{{ choice }}</span>
                        </label>
                      </div>
                      <button
                        v-else-if="field.type === 'multi-checkbox'"
                        type="button"
                        class="multi-check-button"
                        :class="{ checked: multiChoices(row, field.key).includes(field.value || field.label) }"
                        :aria-pressed="multiChoices(row, field.key).includes(field.value || field.label)"
                        @click.stop="toggleMultiChoice(row, field.key, field.value || field.label, !multiChoices(row, field.key).includes(field.value || field.label))"
                      >
                        <span>✓</span>
                      </button>
                      <div v-else-if="isFixedTopicField(field, rowIndex)" class="fixed-topic-cell">
                        <strong>{{ fixedTopicLabel(activeFormActivity, row, rowIndex) }}</strong>
                        <label v-if="rowIndex === 0">
                          <span>ระบุ</span>
                          <textarea v-model="row[field.key]" class="table-textarea" rows="2" />
                        </label>
                      </div>
                      <textarea
                        v-else-if="field.type === 'area'"
                        v-model="row[field.key]"
                        class="table-textarea"
                        rows="3"
                      />
                      <input v-else v-model="row[field.key]" :placeholder="field.placeholder || ''" />
                    </td>
                    <td v-if="!hasLockedFormRows(activeFormActivity)" class="row-control">
                      <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="formDefinitionFor(activeFormActivity)?.note" class="form-note">
              <span>{{ formDefinitionFor(activeFormActivity)?.note }}</span>
              <button
                v-if="effectiveFormCode(activeFormActivity) === 'form_5_coaching'"
                type="button"
                @click="showCoachingApproachHelp = true"
              >
                ดูคำอธิบายแนวทาง
              </button>
            </div>
          </section>

          <section class="form-block result-block">
            <header>
              <h4>ผลการดำเนินการ</h4>
              <span>ใช้หลังแผนอนุมัติแล้ว</span>
            </header>
            <div class="form-grid three">
              <label>
                <span>เป้าหมายในการพัฒนา (Behavior Result)</span>
                <textarea :value="selectedPlan.goal" disabled rows="3" />
              </label>
              <label>
                <span>ตัวชี้วัดผลสำเร็จของการพัฒนา</span>
                <textarea :value="selectedPlan.successCriteria" disabled rows="3" />
              </label>
              <label>
                <span>ผลลัพธ์จากการพัฒนา</span>
                <textarea v-model="activeFormActivity.formDetails.developmentResult.outcome" rows="3" />
              </label>
            </div>
          </section>

          <section class="form-block assessment-block">
            <header>
              <h4>การติดตามประเมินผล / คำรับรอง</h4>
              <span>หัวหน้าใช้ตอนติดตามผล</span>
            </header>
            <div class="form-grid">
              <label>
                <span>ผลดำเนินการ</span>
                <select v-model="activeFormActivity.formDetails.assessment.operationStatus">
                  <option value="">เลือกจากรายการ</option>
                  <option value="ตามแผน">เป็นไปตามแผน</option>
                  <option value="ไม่ตามแผน">ไม่เป็นไปตามแผน</option>
                </select>
              </label>
              <label>
                <span>ผลการพัฒนา</span>
                <select v-model="activeFormActivity.formDetails.assessment.developmentStatus">
                  <option value="">เลือกจากรายการ</option>
                  <option value="บรรลุ">บรรลุเป้าหมายที่กำหนด</option>
                  <option value="ไม่บรรลุ">ไม่บรรลุผล ควรพัฒนาต่อ</option>
                </select>
              </label>
              <label class="wide">
                <span>เหตุผล / หมายเหตุ</span>
                <textarea v-model="activeFormActivity.formDetails.assessment.comment" rows="2" />
              </label>
            </div>
          </section>

          <section v-if="shouldPlaceDetailAtBottom(activeFormActivity)" class="form-block">
            <header>
              <h4>{{ formDefinitionFor(activeFormActivity)?.detailTitle }}</h4>
              <span>กรอกตอนท้าย</span>
            </header>
            <div class="form-grid">
              <label
                v-for="field in formDefinitionFor(activeFormActivity)?.detailFields"
                :key="field.key"
                v-show="shouldShowDetailField(field, activeFormActivity.formDetails.detail)"
                :class="{ wide: field.type === 'area' }"
              >
                <span>{{ field.label }}</span>
                <textarea
                  v-if="field.type === 'area'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  rows="3"
                />
                <select
                  v-else-if="field.type === 'choice'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  @change="handleDetailChoiceChange(field.key)"
                >
                  <option value="">เลือกจากรายการ</option>
                  <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
                </select>
                <select
                  v-else-if="field.type === 'supervisor-chain'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  :disabled="supervisorChainOptions.length === 0"
                >
                  <option value="">
                    {{ supervisorChainOptions.length ? 'เลือกจากรายการ: สายการบังคับบัญชา' : 'ยังไม่ได้กำหนดสายการบังคับบัญชา' }}
                  </option>
                  <option v-for="person in supervisorChainOptions" :key="person.id" :value="person.id">
                    {{ person.label }} · {{ person.name }}{{ person.position ? ` (${person.position})` : '' }}
                  </option>
                </select>
                <input
                  v-else-if="field.type === 'expert-name'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  placeholder="กรอกชื่อผู้เชี่ยวชาญ"
                />
                <input v-else v-model="activeFormActivity.formDetails.detail[field.key]" />
              </label>
            </div>
          </section>
        </div>

        <footer class="form-modal-footer">
          <button type="button" @click="closeActivityForm">ยกเลิก</button>
          <button type="button" class="primary" @click="saveActivityForm">บันทึกฟอร์ม</button>
        </footer>
      </div>
    </div>

    <div v-if="showCoachingApproachHelp" class="approach-modal-backdrop">
      <div class="approach-modal" role="dialog" aria-modal="true" aria-labelledby="coaching-approach-title">
        <header class="approach-modal-header">
          <div>
            <h3 id="coaching-approach-title">คำอธิบายแนวทางการสอนงาน</h3>
            <p>ใช้ประกอบการเลือกแนวทาง A, B, C, D ในแบบฟอร์มการสอนงาน</p>
          </div>
          <span class="approach-modal-badge">4 แนวทาง</span>
          <button type="button" aria-label="ปิดคำอธิบายแนวทาง" @click="showCoachingApproachHelp = false">×</button>
        </header>
        <div class="approach-modal-guide">
          <strong>วิธีใช้:</strong>
          <span>อ่านความหมายของแต่ละแนวทาง แล้วเลือก A, B, C หรือ D ในช่องแนวทางการสอนงานของแต่ละแถว</span>
        </div>
        <div class="approach-modal-body">
          <article
            v-for="approach in coachingApproachDescriptions"
            :key="approach.code"
            class="approach-help-card"
            :class="`approach-${approach.code.toLowerCase()}`"
          >
            <header>
              <b>{{ approach.code }}</b>
              <div>
                <strong>{{ approach.title }}</strong>
                <span>{{ approach.summary }}</span>
              </div>
            </header>
            <ul>
              <li v-for="item in approach.items" :key="item">{{ item }}</li>
            </ul>
          </article>
        </div>
        <footer class="approach-modal-footer">
          <button type="button" @click="showCoachingApproachHelp = false">ปิด</button>
        </footer>
      </div>
    </div>

    <footer v-if="idpGaps.length" class="submit-bar">
      <div>
        <strong>{{ selectedPlan ? `${selectedGap?.cd} · ${planStatusLabel(selectedPlan)}` : 'เลือกสมรรถนะ' }}</strong>
        <span v-if="selectedPlan?.status === 'revision_required'">เมื่อส่งใหม่ ระบบจะเริ่มตรวจจากผู้อนุมัติลำดับแรกอีกครั้ง</span>
        <span v-else>{{ selectedPlanLocked ? 'สมรรถนะอื่นยังสามารถจัดทำและส่งแยกได้' : 'ข้อมูลร่างจะบันทึกอัตโนมัติหลังหยุดกรอก' }}</span>
      </div>
      <button type="button" :disabled="!selectedPlan || selectedPlanLocked || saveState === 'saving' || !!planIssue(selectedPlan)" @click="submitSelectedPlan">
        {{ selectedPlanLocked ? planStatusLabel(selectedPlan) : 'ส่งสมรรถนะนี้ให้หัวหน้า' }}
      </button>
    </footer>
  </section>
</template>

<style scoped>
.idp-page { display: grid; gap: 16px; color: #172033; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 16px; border-bottom: 1px solid #dfe5ed; }
.eyebrow { margin: 0 0 5px; color: #28705f; font-size: 11px; font-weight: 900; }
.page-header h1 { margin: 0; font-size: 24px; font-weight: 900; }
.subtitle { margin: 6px 0 0; color: #68768a; font-size: 13px; }
.save-indicator { display: flex; align-items: center; gap: 7px; min-height: 34px; padding: 7px 11px; border: 1px solid #d8e0e9; border-radius: 6px; background: #fff; color: #657287; font-size: 12px; font-weight: 800; }
.save-dot { width: 7px; height: 7px; border-radius: 50%; background: #9aa7b7; }
.save-indicator.saving .save-dot { background: #d97706; animation: pulse 1s infinite; }
.save-indicator.saved .save-dot { background: #16835d; }
.save-indicator.error { color: #b42318; border-color: #fecaca; background: #fff7f7; }
.save-indicator.error .save-dot { background: #dc2626; }
.workspace { display: grid; grid-template-columns: 290px minmax(0, 1fr); min-height: 620px; border: 1px solid #ccd5df; border-radius: 8px; background: #eef2f5; overflow: hidden; }
.plan-nav { border-right: 1px solid #cfd8e2; background: #f7f9fb; }
.person { display: grid; gap: 3px; padding: 17px; border-bottom: 1px solid #d8e0e8; background: #fff; }
.person span, .person small { color: #718096; font-size: 11px; }
.person strong { font-size: 14px; }
.nav-heading { display: flex; justify-content: space-between; align-items: center; padding: 16px 15px 9px; font-size: 12px; }
.nav-heading span { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; background: #dfe8e5; color: #216b59; font-weight: 900; }
.plan-nav-item { width: 100%; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; border: 0; border-left: 3px solid transparent; background: transparent; padding: 12px; text-align: left; cursor: pointer; }
.plan-nav-item:hover { background: #eef3f5; }
.plan-nav-item.active { border-left-color: #247260; background: #e4f0ec; }
.competency-code { min-width: 51px; color: #247260; font-size: 11px; font-weight: 900; }
.competency-copy { min-width: 0; }
.competency-copy strong, .competency-copy small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.competency-copy strong { font-size: 12px; }
.competency-copy small { margin-top: 3px; color: #7a8798; font-size: 10px; }
.completion-mark { display: grid; place-items: center; width: 21px; height: 21px; border-radius: 50%; background: #fff1f2; color: #c2414b; font-size: 11px; font-weight: 900; }
.completion-mark.ready { background: #dcfce7; color: #16835d; }
.plan-content { min-width: 0; padding: 20px; background: #eef2f5; }
.select-prompt, .empty-state, .activities-empty { display: grid; place-items: center; align-content: center; gap: 7px; min-height: 260px; color: #7a8798; text-align: center; }
.select-prompt strong, .empty-state strong, .activities-empty strong { color: #344054; }
.competency-header { display: flex; justify-content: space-between; gap: 18px; padding: 16px; border: 1px solid #cfd8e2; border-radius: 7px; background: #fff; box-shadow: 0 3px 12px rgba(26, 45, 59, .04); }
.competency-header h2 { margin: 8px 0 0; font-size: 20px; }
.competency-header p { max-width: 660px; margin: 7px 0 0; color: #69778a; font-size: 12px; line-height: 1.6; }
.type-tag { display: inline-flex; padding: 4px 7px; border-radius: 4px; background: #e3eeeb; color: #246b5a; font-size: 11px; font-weight: 900; }
.score-row { display: grid; grid-template-columns: repeat(3, 72px); gap: 7px; flex: 0 0 auto; }
.score-row > div { padding: 9px; border: 1px solid #e0e6ed; border-radius: 6px; text-align: center; }
.score-row span, .score-row strong { display: block; }
.score-row span { color: #7a8798; font-size: 10px; }
.score-row strong { margin-top: 4px; font-size: 17px; }
.score-row .negative { border-color: #fecaca; background: #fff7f7; color: #b42318; }
.revision-alert { display: grid; gap: 4px; margin-top: 14px; padding: 11px 13px; border: 1px solid #efb8b8; border-radius: 7px; background: #fff5f5; color: #9f2520; }
.revision-alert strong { font-size: 12px; }
.revision-alert span { font-size: 11px; line-height: 1.55; }
.section-card { margin-top: 14px; overflow: hidden; border: 1px solid; border-radius: 7px; background: #fff; box-shadow: 0 3px 12px rgba(26, 45, 59, .04); }
.behavior-section { border-color: #e8c4aa; }
.goal-section { border-color: #b8d0e8; }
.activities-section { border-color: #a9d4c5; }
.section-title { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin: 0; padding: 11px 13px; border-bottom: 1px solid; }
.behavior-section .section-title { border-color: #ecd2be; background: #fff4ec; }
.goal-section .section-title { border-color: #c9dced; background: #eef6fc; }
.activities-section .section-title { border-color: #c1e0d5; background: #edf8f4; }
.section-title > div:first-child { display: flex; align-items: center; gap: 10px; }
.section-number { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 5px; color: #fff; font-size: 11px; font-weight: 900; flex: 0 0 auto; }
.behavior-section .section-number { background: #c6682a; }
.goal-section .section-number { background: #3979b7; }
.activities-section .section-number { background: #28745f; }
.section-title h3, .section-title p { margin: 0; }
.section-title h3 { font-size: 14px; }
.section-title p { margin-top: 3px; color: #7a8798; font-size: 11px; }
.section-badge { padding: 5px 8px; border: 1px solid currentColor; border-radius: 5px; background: rgba(255,255,255,.78); font-size: 10px; font-weight: 900; white-space: nowrap; }
.behavior-section .section-badge { color: #a7531d; }
.goal-section .section-badge { color: #3979b7; }
.section-body { padding: 13px; }
.indicator-list { display: grid; gap: 7px; }
.indicator-row { display: grid; grid-template-columns: 125px 1fr; gap: 12px; padding: 10px 12px; border: 1px solid #ead6c7; border-radius: 5px; background: #fffcf9; }
.indicator-row span { color: #9b501f; font-size: 11px; font-weight: 900; }
.indicator-row p { margin: 0; font-size: 12px; line-height: 1.5; }
.goal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label { display: grid; gap: 6px; min-width: 0; color: #59677a; font-size: 11px; font-weight: 800; }
input, select, textarea { width: 100%; border: 1px solid #d5dde7; border-radius: 6px; background: #fff; padding: 9px 10px; color: #172033; font: inherit; font-size: 12px; box-sizing: border-box; }
select {
  appearance: none;
  padding-right: 34px;
  background-image:
    linear-gradient(45deg, transparent 50%, #667085 50%),
    linear-gradient(135deg, #667085 50%, transparent 50%);
  background-position:
    calc(100% - 17px) 50%,
    calc(100% - 12px) 50%;
  background-size: 5px 5px, 5px 5px;
  background-repeat: no-repeat;
  cursor: pointer;
}
textarea { resize: vertical; line-height: 1.5; }
input:focus, select:focus, textarea:focus { outline: 2px solid #b9d8cf; border-color: #247260; }
input:disabled, select:disabled, textarea:disabled { background: #f2f4f7; color: #7a8798; }
select:disabled { cursor: not-allowed; }
.goal-section label > span { color: #3970a6; }
.goal-section textarea { border-color: #c8d8e8; background: #fbfdff; }
.weight-total { min-width: 112px; padding: 6px 9px; border: 1px solid #e8b7b7; border-radius: 5px; background: #fff; color: #b42318; text-align: center; font-size: 11px; font-weight: 900; }
.weight-total.complete { border-color: #87c8b0; background: #e6f6ef; color: #16835d; }
.activities-empty { min-height: 130px; border: 1px dashed #9fc3b8; border-radius: 6px; background: #f7fbfa; font-size: 12px; }
.activity { margin-top: 10px; border: 1px solid #cfd9e2; border-radius: 7px; overflow: hidden; background: #fff; box-shadow: 0 3px 10px rgba(26, 45, 59, .05); }
.activity:first-of-type { margin-top: 0; }
.activity > header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 11px 13px; border-bottom: 1px solid #e2e7ed; background: #f7f9fa; }
.activity-identity { display: flex; align-items: center; gap: 9px; min-width: 0; }
.activity-identity > div { min-width: 0; }
.activity-number { display: grid !important; place-items: center; width: 28px; height: 28px; border-radius: 5px; background: #dceee8; color: #246b59 !important; font-size: 11px !important; flex: 0 0 auto; }
.activity > header span, .activity > header strong { display: block; }
.activity > header span { color: #247260; font-size: 10px; font-weight: 900; }
.activity > header strong { margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
.activity > header button { width: 28px; height: 28px; border: 1px solid #e3c5c8; border-radius: 5px; background: #fff; color: #b42318; font-size: 18px; cursor: pointer; }
.activity-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 11px; padding: 13px; }
.activity-form .wide { grid-column: span 3; }
.activity-detail-action { display: flex; align-items: center; justify-content: space-between; gap: 14px; border: 1px solid #d7e4ee; border-radius: 7px; background: #fbfdff; padding: 12px; }
.activity-detail-action strong, .activity-detail-action span { display: block; }
.activity-detail-action strong { color: #172033; font-size: 12px; }
.activity-detail-action span { margin-top: 4px; color: #9a4d00; font-size: 11px; font-weight: 900; }
.activity-detail-action span.saved { color: #16835d; }
.activity-detail-action button { border: 0; border-radius: 6px; background: #247260; padding: 9px 12px; color: #fff; font-size: 12px; font-weight: 900; cursor: pointer; white-space: nowrap; }
.activity-detail-action button:disabled { background: #aab5c2; cursor: not-allowed; }
.add-activity { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; margin-top: 11px; border: 1px dashed #9dbbb3; border-radius: 6px; background: #f6fbf9; padding: 11px; color: #246b5a; font-size: 12px; font-weight: 900; cursor: pointer; }
.add-activity span { font-size: 18px; }
.error-box { margin-top: 12px; padding: 10px 12px; border: 1px solid #fecaca; border-radius: 6px; background: #fff7f7; color: #b42318; font-size: 12px; }
.form-modal-backdrop { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; background: rgba(15, 23, 42, .48); padding: 8px; }
.form-modal { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; width: min(1680px, calc(100vw - 16px)); max-height: calc(100vh - 28px); overflow: hidden; border-radius: 10px; background: #f8fafc; box-shadow: 0 30px 90px rgba(15, 23, 42, .3); }
.form-modal-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #d8e0e9; background: #fff; padding: 14px 18px; }
.form-modal-top strong, .form-modal-top span { display: block; }
.form-modal-top strong { font-size: 16px; }
.form-modal-top span { margin-top: 4px; color: #667085; font-size: 12px; font-weight: 800; }
.form-modal-top button { width: 36px; height: 36px; border: 1px solid #d8e0e9; border-radius: 7px; background: #fff; color: #b42318; font-size: 22px; cursor: pointer; }
.form-paper { overflow: auto; padding: 18px; }
.form-title-band { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border: 1px solid #f3c8b7; border-radius: 8px; background: #fff1e8; padding: 13px 15px; }
.form-title-band h3, .form-title-band p { margin: 0; }
.form-title-band h3 { font-size: 20px; }
.form-title-band p { margin-top: 5px; color: #667085; font-size: 12px; font-weight: 800; }
.form-title-band label { width: 260px; }
.form-block { margin-top: 14px; overflow: hidden; border: 1px solid #d8e0e9; border-radius: 8px; background: #fff; }
.form-block > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #d8e0e9; background: #f8fafc; padding: 10px 12px; }
.form-block > header h4 { margin: 0; font-size: 14px; }
.form-block > header span { color: #667085; font-size: 11px; font-weight: 900; }
.form-block > header button { border: 1px solid #98c9b7; border-radius: 6px; background: #edf8f4; color: #247260; padding: 7px 10px; font-size: 11px; font-weight: 900; cursor: pointer; }
.readonly-block > header { background: #edf8f4; }
.result-block > header { background: #fff0fb; }
.assessment-block > header { background: #eef6ff; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
.form-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.form-grid .wide { grid-column: 1 / -1; }
.form-table-wrap { overflow-x: auto; padding: 12px; }
.form-table-wrap table { width: 100%; min-width: 1180px; border-collapse: collapse; }
.form-table-wrap th, .form-table-wrap td { border: 1px solid #cfd8e3; padding: 8px; vertical-align: top; }
.form-table-wrap th { background: #f8fafc; color: #475467; font-size: 11px; font-weight: 900; text-align: left; }
.form-table-wrap th small { display: block; margin-top: 3px; color: #9a4d00; font-size: 10px; }
.form-table-wrap input, .form-table-wrap select { min-width: 120px; }
.form-table-wrap .table-textarea { min-width: 240px; min-height: 72px; }
.fixed-topic-cell { display: grid; gap: 10px; min-width: 280px; color: #172033; font-size: 12px; font-weight: 900; }
.fixed-topic-cell label { display: grid; gap: 6px; color: #475467; font-size: 11px; font-weight: 900; }
.fixed-topic-cell .table-textarea { min-width: 260px; min-height: 56px; }
.checkbox-choice-group { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; min-width: 150px; padding: 7px 2px; }
.checkbox-choice-group label { display: inline-flex; align-items: center; gap: 5px; min-width: 0; color: #172033; font-size: 11px; font-weight: 800; white-space: nowrap; }
.checkbox-choice-group input { width: 14px; min-width: 14px; height: 14px; margin: 0; padding: 0; accent-color: #247260; }
.multi-check-button { display: grid; place-items: center; width: 22px; height: 22px; margin: 0 auto; border: 1.5px solid #b8c7d9; border-radius: 5px; background: #fff; color: transparent; cursor: pointer; transition: background .15s ease, border-color .15s ease, box-shadow .15s ease; }
.multi-check-button span { color: inherit; font-size: 14px; font-weight: 900; line-height: 1; }
.multi-check-button.checked { border-color: #247260; background: #247260; color: #fff; box-shadow: 0 0 0 3px rgba(36, 114, 96, .14); }
.row-number { width: 42px; text-align: center !important; }
.row-control { width: 62px; text-align: center; }
.row-control button { border: 1px solid #efb8b8; border-radius: 5px; background: #fff; color: #b42318; padding: 7px 9px; font-size: 11px; font-weight: 900; }
.row-control button:disabled { opacity: .35; }
.form-note { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 12px 12px; border-left: 4px solid #f59e0b; background: #fffbeb; padding: 9px 11px; color: #8a5300; font-size: 12px; font-weight: 800; }
.form-note button { border: 1px solid #f2b94b; border-radius: 6px; background: #fff7d6; color: #8a5300; padding: 7px 10px; font-size: 11px; font-weight: 900; cursor: pointer; white-space: nowrap; }
.approach-modal-backdrop { position: fixed; inset: 0; z-index: 110; display: grid; place-items: center; background: rgba(15, 23, 42, .62); padding: 6px; }
.approach-modal { display: grid; grid-template-rows: auto auto minmax(0, 1fr) auto; width: min(1360px, calc(100vw - 12px)); height: calc(100vh - 12px); min-height: 0; overflow: hidden; border: 1px solid #d7e0ea; border-radius: 12px; background: #fff; box-shadow: 0 26px 80px rgba(15, 23, 42, .34); }
.approach-modal-header { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 12px; border-bottom: 1px solid #d8e0e9; background: #fff; padding: 14px 18px; }
.approach-modal-header h3, .approach-modal-header p { margin: 0; }
.approach-modal-header h3 { color: #172033; font-size: 22px; font-weight: 900; }
.approach-modal-header p { margin-top: 4px; color: #667085; font-size: 14px; font-weight: 800; }
.approach-modal-badge { border: 1px solid #b9ddd4; border-radius: 999px; background: #edf8f5; color: #247260; padding: 8px 13px; font-size: 13px; font-weight: 900; white-space: nowrap; }
.approach-modal-header button { display: grid; place-items: center; width: 40px; height: 40px; border: 1px solid #efb8b8; border-radius: 7px; background: #fff; color: #b42318; font-size: 26px; font-weight: 600; line-height: 1; cursor: pointer; }
.approach-modal-guide { display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #d8e0e9; background: #f7fbfa; padding: 10px 18px; color: #46576b; font-size: 14px; font-weight: 800; }
.approach-modal-guide strong { color: #247260; }
.approach-modal-body { display: flex; flex-direction: column; gap: 12px; min-height: 0; overflow-y: auto; background: #eef3f6; padding: 14px 18px 18px; }
.approach-help-card { display: block; flex: 0 0 auto; border: 1px solid #d8e0e9; border-radius: 10px; background: #fff; overflow: visible; }
.approach-help-card header { display: flex; align-items: flex-start; gap: 12px; border-bottom: 1px solid #dfe6ee; background: #f8fafc; padding: 14px 16px; }
.approach-help-card header b { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 8px; background: #247260; color: #fff; font-size: 15px; flex: 0 0 auto; box-shadow: 0 8px 18px rgba(36, 114, 96, .18); }
.approach-help-card header strong, .approach-help-card header span { display: block; }
.approach-help-card header strong { color: #172033; font-size: 17px; font-weight: 900; }
.approach-help-card header span { margin-top: 5px; color: #667085; font-size: 14px; font-weight: 800; line-height: 1.4; }
.approach-help-card ul { display: grid; gap: 8px; margin: 0; padding: 16px 28px 18px 46px; color: #344054; font-size: 15px; line-height: 1.55; list-style: none; }
.approach-help-card li { position: relative; margin: 0; padding-left: 18px; }
.approach-help-card li::before { content: ''; position: absolute; top: .68em; left: 0; width: 7px; height: 7px; border-radius: 50%; background: #247260; transform: translateY(-50%); }
.approach-help-card.approach-b header b { background: #315f9f; box-shadow: 0 8px 18px rgba(49, 95, 159, .18); }
.approach-help-card.approach-c header b { background: #8a5b18; box-shadow: 0 8px 18px rgba(138, 91, 24, .18); }
.approach-help-card.approach-d header b { background: #8a3a4f; box-shadow: 0 8px 18px rgba(138, 58, 79, .18); }
.approach-modal-footer { display: flex; justify-content: flex-end; border-top: 1px solid #d8e0e9; background: #fff; padding: 9px 14px; }
.approach-modal-footer button { border: 1px solid #247260; border-radius: 7px; background: #247260; color: #fff; padding: 10px 20px; font-size: 14px; font-weight: 900; cursor: pointer; }
.form-modal-footer { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #d8e0e9; background: #fff; padding: 13px 18px; }
.form-modal-footer button { border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; padding: 10px 14px; font-size: 12px; font-weight: 900; cursor: pointer; }
.form-modal-footer button.primary { border-color: #247260; background: #247260; color: #fff; }
.submit-bar { position: sticky; bottom: 0; z-index: 5; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 13px 16px; border: 1px solid #d7dfe8; border-radius: 8px; background: rgba(255,255,255,.96); box-shadow: 0 -8px 22px rgba(23,32,51,.08); }
.submit-bar strong, .submit-bar span { display: block; }
.submit-bar strong { font-size: 13px; }
.submit-bar span { margin-top: 3px; color: #7a8798; font-size: 11px; }
.submit-bar button { min-width: 180px; border: 0; border-radius: 6px; background: #247260; padding: 11px 16px; color: #fff; font-size: 12px; font-weight: 900; cursor: pointer; }
.submit-bar button:disabled { background: #aab5c2; cursor: not-allowed; }
@keyframes pulse { 50% { opacity: .35; } }
@media (max-width: 900px) {
  .page-header, .competency-header, .submit-bar { align-items: stretch; flex-direction: column; }
  .workspace { grid-template-columns: 1fr; }
  .plan-nav { border-right: 0; border-bottom: 1px solid #dfe5ed; }
  .goal-grid, .activity-form, .form-grid, .form-grid.three { grid-template-columns: 1fr; }
  .approach-modal-header { grid-template-columns: minmax(0, 1fr) auto; }
  .approach-modal-badge { display: none; }
  .approach-modal-guide { align-items: flex-start; flex-direction: column; }
  .activity-form .wide { grid-column: span 1; }
  .activity-detail-action, .form-title-band { align-items: stretch; flex-direction: column; }
  .form-title-band label { width: 100%; }
  .score-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .indicator-row { grid-template-columns: 1fr; }
  .section-title { align-items: flex-start; }
  .submit-bar button { width: 100%; }
}
</style>
