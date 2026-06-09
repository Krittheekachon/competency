<template>
  <!-- ── Header ───────────────────────────────────────────────────────────── -->
  <div class="flex ic jb mb20">
    <div>
      <div class="sec-t">แผนพัฒนา IDP</div>
      <div class="sec-s">
        ดึงข้อมูลสมรรถนะที่ต้องการพัฒนาอัตโนมัติ ปีงบประมาณ 2568 สถานะ: draft
      </div>
    </div>
    <button v-if="hasIdpPlan" class="btn btn-s">Export PDF</button>
  </div>

  <div v-if="!hasIdpPlan" class="card empty-idp">
    <div class="empty-icon"></div>
    <div class="empty-title">ยังไม่มีแผน IDP</div>
    <div class="empty-desc">
      เมื่อมีผลการประเมินหรือสมรรถนะที่ต้องพัฒนา ระบบจะแสดงรายการสำหรับจัดทำแผนพัฒนารายบุคคลที่หน้านี้
    </div>
  </div>

  <!-- ── Learning methods card ─────────────────────────────────────────────── -->
  <div v-if="hasIdpPlan" class="card mb20">
    <div class="ch"><div class="ct">รูปแบบการเรียนรู้ที่นำไปใช้ได้</div></div>
    <div class="cb" style="padding-top: 10px">
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px">
        <div
          v-for="m in resolvedMethods"
          :key="m.key"
          style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--r); padding: 11px 14px"
          :style="{ borderLeft: `3px solid ${m.color}` }"
        >
          <div class="fw7 fs12" :style="{ color: m.color, marginBottom: '4px' }">
            {{ m.label }}
          </div>
          <div class="fs12 muted">{{ m.desc }}</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Gap cards ─────────────────────────────────────────────────────────── -->
  <IDPGapCard
    v-for="(g, idx) in gaps"
    :key="g.cd"
    :gap="g"
    :activities="activitiesByGap[g.cd] || []"
    :goal="goalsByGap[g.cd] || ''"
    :is-form-open="openForms.includes(idx)"
    :activity-form="getForm(idx)"
    :methods="resolvedMethods"
    @submit-gap="submitGapPlan"
    @toggle-form="toggleForm(idx)"
    @add-activity="addActivity(idx)"
    @save-draft-activity="() => {}"
    @update:goal="val => updateGoal(g.cd, val)"
    @update:activity-form="patch => setForm(idx, patch)"
  />

  <!-- ── Footer actions ────────────────────────────────────────────────────── -->
  <div v-if="hasIdpPlan" class="flex g8 mt4" style="padding-top: 4px">
    <button class="btn btn-t" @click="submitAllIDP">ส่ง IDP ทั้งหมด</button>
    <button class="btn btn-s" @click="saveIDPDraft">บันทึกร่าง</button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect } from 'vue'
import { IDP_GAPS_DATA, IDP_ACTIVITIES_DATA } from '../../data'
import IDPGapCard from './IDPGapCard.vue'
import type { LearningMethodOption, ActivityForm } from './IDPForm.vue'
import type { Gap, Activity } from './IDPGapCard.vue'

// ── Props ─────────────────────────────────────────────────────────────────────

const props = withDefaults(
  defineProps<{
    learningMethods?: LearningMethodOption[]
  }>(),
  { learningMethods: () => [] }
)

// ── Storage helpers ───────────────────────────────────────────────────────────

const EMPLOYEE_IDP_GAPS_KEY      = 'cidp-employee-idp-gaps'
const EMPLOYEE_IDP_ACTIVITIES_KEY = 'cidp-employee-idp-activities'
const EMPLOYEE_IDP_FORMS_KEY     = 'cidp-employee-idp-forms'
const EMPLOYEE_IDP_GOALS_KEY     = 'cidp-employee-idp-goals'

function readStorage<T>(key: string, fallback: T): T {
  if (typeof window === 'undefined') return fallback
  try {
    const raw = window.localStorage.getItem(key)
    return raw ? (JSON.parse(raw) as T) : fallback
  } catch {
    return fallback
  }
}

function writeStorage(key: string, value: unknown) {
  if (typeof window === 'undefined') return
  try {
    window.localStorage.setItem(key, JSON.stringify(value))
  } catch { /* keep mock flows usable */ }
}

// ── State ─────────────────────────────────────────────────────────────────────

const gaps = ref<Gap[]>(readStorage(EMPLOYEE_IDP_GAPS_KEY, IDP_GAPS_DATA))
const hasIdpPlan = computed(() => gaps.value.length > 0)

const activitiesByGap = ref<Record<string, Activity[]>>(
  readStorage(EMPLOYEE_IDP_ACTIVITIES_KEY, IDP_ACTIVITIES_DATA)
)

const activityForm = ref<Record<number, ActivityForm>>(
  readStorage(EMPLOYEE_IDP_FORMS_KEY, {})
)

const goalsByGap = ref<Record<string, string>>(
  readStorage(EMPLOYEE_IDP_GOALS_KEY, {})
)

/** Indices of gap cards that have their add-form open */
const openForms = ref<number[]>([])

// ── Persist to localStorage ───────────────────────────────────────────────────

watchEffect(() => writeStorage(EMPLOYEE_IDP_GAPS_KEY, gaps.value))
watchEffect(() => writeStorage(EMPLOYEE_IDP_ACTIVITIES_KEY, activitiesByGap.value))
watchEffect(() => writeStorage(EMPLOYEE_IDP_FORMS_KEY, activityForm.value))
watchEffect(() => writeStorage(EMPLOYEE_IDP_GOALS_KEY, goalsByGap.value))

// ── Learning methods with palette ─────────────────────────────────────────────

const DEFAULT_LEARNING_METHOD_OPTIONS: LearningMethodOption[] = [
  {
    key: 'experiential',
    label: 'Experiential Learning',
    desc: 'การเรียนรู้ผ่านประสบการณ์จากการทำงานจริง เช่น OJT โครงการพิเศษ หรือ Job Rotation',
  },
  {
    key: 'social',
    label: 'Social Learning',
    desc: 'การเรียนรู้ผ่านบุคคลอื่น การปฏิสัมพันธ์ แลกเปลี่ยนความคิดเห็น ประสบการณ์ร่วมกัน หรือการมีผู้คอยให้คำแนะนำ',
  },
  {
    key: 'formal',
    label: 'Formal Learning',
    desc: 'การเรียนรู้อย่างเป็นทางการ มีแบบแผน หรือการเรียนในห้องเรียน',
  },
]

const METHOD_PALETTE = [
  { color: 'var(--orange)', bg: '#FFF7ED', ic: 'EX' },
  { color: 'var(--green)',  bg: '#F0FDF4', ic: 'SO' },
  { color: 'var(--blue)',   bg: '#EFF6FF', ic: 'FO' },
  { color: '#7C3AED',       bg: '#F5F3FF', ic: 'LR' },
  { color: '#0F766E',       bg: '#F0FDFA', ic: 'DV' },
]

const resolvedMethods = computed<(LearningMethodOption & { color: string; bg: string; ic: string })[]>(
  () => {
    const base = props.learningMethods.length
      ? props.learningMethods
      : DEFAULT_LEARNING_METHOD_OPTIONS
    return base.map((m, i) => ({ ...m, ...METHOD_PALETTE[i % METHOD_PALETTE.length] }))
  }
)

const methodMap = computed(
  () => new Map(resolvedMethods.value.map(m => [m.key, m]))
)

// ── Form helpers ──────────────────────────────────────────────────────────────

const EMPTY_FORM: ActivityForm = {
  catalog: '', method: '', title: '',
  startDate: '', endDate: '', duration: '', weight: '', note: '',
}

function getForm(idx: number): ActivityForm {
  return activityForm.value[idx] ?? { ...EMPTY_FORM }
}

function setForm(idx: number, patch: Partial<ActivityForm>) {
  activityForm.value = {
    ...activityForm.value,
    [idx]: { ...getForm(idx), ...patch },
  }
}

function updateGoal(cd: string, val: string) {
  goalsByGap.value = { ...goalsByGap.value, [cd]: val }
}

// ── Toggle form open/close ────────────────────────────────────────────────────

function toggleForm(idx: number) {
  const i = openForms.value.indexOf(idx)
  if (i === -1) {
    openForms.value = [...openForms.value, idx]
  } else {
    openForms.value = openForms.value.filter(n => n !== idx)
  }
}

// ── Add activity ──────────────────────────────────────────────────────────────

function addActivity(idx: number) {
  const f = getForm(idx)

  if (!f.title.trim() || !f.method || !f.startDate || !f.endDate || !f.weight) return

  if (f.endDate < f.startDate) {
    alert('วันที่สิ้นสุดต้องไม่อยู่ก่อนวันที่เริ่ม')
    return
  }
  if (+f.weight > 100 || +f.weight <= 0) {
    alert('น้ำหนักต้องอยู่ระหว่าง 1-100')
    return
  }

  const gap = gaps.value[idx]
  const selectedMethod = methodMap.value.get(f.method)
  const methodTheme = selectedMethod
    ? { ic: selectedMethod.ic, bg: selectedMethod.bg }
    : { ic: 'LR', bg: '#EFF6FF' }

  const newActivity: Activity = {
    ...methodTheme,
    t: f.title.trim(),
    m: selectedMethod?.label ?? f.method,
    due: f.endDate,
    weight: f.weight,
    note: f.note,
    st: 'ร่าง',
    stC: 'by',
    result: null,
    logs: [],
  }

  activitiesByGap.value = {
    ...activitiesByGap.value,
    [gap.cd]: [...(activitiesByGap.value[gap.cd] ?? []), newActivity],
  }

  // Reset form and close
  activityForm.value = { ...activityForm.value, [idx]: { ...EMPTY_FORM } }
  toggleForm(idx)
}

// ── Submit / save ─────────────────────────────────────────────────────────────

function submitGapPlan(cd: string) {
  gaps.value = gaps.value.map(g =>
    g.cd === cd ? { ...g, status: 'submitted' } : g
  )
  alert('ส่งแผน IDP ให้หัวหน้าเรียบร้อยแล้ว')
}

function submitAllIDP() {
  gaps.value = gaps.value.map(g =>
    g.status === 'draft' || g.status === 'rejected'
      ? { ...g, status: 'submitted' }
      : g
  )
  alert('ส่ง IDP ทั้งหมดเรียบร้อยแล้ว')
}

function saveIDPDraft() {
  writeStorage(EMPLOYEE_IDP_GAPS_KEY, gaps.value)
  writeStorage(EMPLOYEE_IDP_ACTIVITIES_KEY, activitiesByGap.value)
  writeStorage(EMPLOYEE_IDP_FORMS_KEY, activityForm.value)
  writeStorage(EMPLOYEE_IDP_GOALS_KEY, goalsByGap.value)
  alert('บันทึกร่าง IDP เรียบร้อย')
}
</script>

<style scoped>
.empty-idp {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 54px 24px;
  text-align: center;
}

.empty-icon {
  font-size: 38px;
  line-height: 1;
}

.empty-title {
  color: var(--text);
  font-size: 16px;
  font-weight: 800;
}

.empty-desc {
  max-width: 560px;
  color: var(--text3);
  font-size: 13px;
}
</style>
