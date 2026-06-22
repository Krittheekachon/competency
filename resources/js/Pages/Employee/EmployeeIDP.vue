<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

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
type DevelopmentTool = { id: number; code?: string; focusType: string; title: string; isActive?: boolean };
type Catalog = {
  id: number;
  code?: string;
  name: string;
  competencyIds?: number[];
  description?: string;
  isActive?: boolean;
};
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
const selectedGap = computed(() => idpGaps.value.find((gap) => gap.id === selectedGapId.value) || null);
const selectedPlan = computed(() => plans.value.find((plan) => plan.competencyGapId === selectedGapId.value) || null);
const isReviewStatus = (status: string) => /^review_step_[123]$/.test(status);
const isPlanLocked = (plan: Plan | null) =>
  plan?.status === 'approved' || isReviewStatus(plan?.status || '');
const selectedPlanLocked = computed(() => isPlanLocked(selectedPlan.value));
const errors = computed(() => page.props.errors || {});

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
};
const chooseTool = (activity: Activity) => {
  const tool = activeTools.value.find((item) => item.id === Number(activity.developmentToolId));
  activity.activityName = tool ? `${tool.code ? `${tool.code} · ` : ''}${tool.title}` : '';
};
const chooseCatalog = (activity: Activity) => {
  const catalog = activeCatalogs.value.find((item) => item.id === Number(activity.learningCatalogId));
  activity.activityName = catalog?.name || '';
  activity.activityDescription = catalog?.description || '';
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

watch(() => [props.gaps, props.idp], hydratePlans, { immediate: true, deep: true });

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
                    <option value="">เลือกรูปแบบ</option>
                    <option v-for="method in methods" :key="method.key" :value="method.key">{{ method.label }}</option>
                  </select>
                </label>

                <label v-if="['experiential', 'social'].includes(focusType(activity.methodKey))">
                  <span>เครื่องมือพัฒนา</span>
                  <select v-model.number="activity.developmentToolId" :disabled="selectedPlanLocked" @change="chooseTool(activity)">
                    <option :value="null">เลือกเครื่องมือ</option>
                    <option v-for="tool in toolsFor(activity)" :key="tool.id" :value="tool.id">
                      {{ tool.code ? `${tool.code} · ` : '' }}{{ tool.title }}
                    </option>
                  </select>
                </label>

                <label v-else-if="focusType(activity.methodKey) === 'formal'">
                  <span>Learning Catalog</span>
                  <select v-model.number="activity.learningCatalogId" :disabled="selectedPlanLocked" @change="chooseCatalog(activity)">
                    <option :value="null">เลือกหลักสูตร</option>
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
                <label class="wide">
                  <span>รายละเอียดกิจกรรม</span>
                  <textarea v-model="activity.activityDescription" :disabled="selectedPlanLocked" rows="2" placeholder="รายละเอียด ขอบเขต หรือผลลัพธ์ของกิจกรรม" />
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
                  <span>น้ำหนัก (%)</span>
                  <input v-model.number="activity.weightPercent" :disabled="selectedPlanLocked" type="number" min="0" max="100" />
                </label>
                <label>
                  <span>วันที่เริ่ม</span>
                  <input v-model="activity.startDate" :disabled="selectedPlanLocked" type="date" />
                </label>
                <label>
                  <span>วันที่สิ้นสุด</span>
                  <input v-model="activity.endDate" :disabled="selectedPlanLocked" type="date" />
                </label>
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
textarea { resize: vertical; line-height: 1.5; }
input:focus, select:focus, textarea:focus { outline: 2px solid #b9d8cf; border-color: #247260; }
input:disabled, select:disabled, textarea:disabled { background: #f2f4f7; color: #7a8798; }
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
.document-reference-field input { border-color: #dfc36f; background: #fffbed; }
.document-reference-field > span { color: #80610c; }
.add-activity { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; margin-top: 11px; border: 1px dashed #9dbbb3; border-radius: 6px; background: #f6fbf9; padding: 11px; color: #246b5a; font-size: 12px; font-weight: 900; cursor: pointer; }
.add-activity span { font-size: 18px; }
.error-box { margin-top: 12px; padding: 10px 12px; border: 1px solid #fecaca; border-radius: 6px; background: #fff7f7; color: #b42318; font-size: 12px; }
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
  .goal-grid, .activity-form { grid-template-columns: 1fr; }
  .activity-form .wide { grid-column: span 1; }
  .score-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .indicator-row { grid-template-columns: 1fr; }
  .section-title { align-items: flex-start; }
  .submit-bar button { width: 100%; }
}
</style>
