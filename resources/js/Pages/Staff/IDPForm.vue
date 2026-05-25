<template>
  <div style="padding: 18px; background: #F8FBFF; border-top: 1px solid #DBE7F5; animation: slideDown .22s ease">
    <div class="fw7 fs13 mb14">เพิ่มกิจกรรมพัฒนา</div>

    <div class="g2 mb12">
      <div class="fg" style="margin: 0">
        <label class="lbl" style="font-size: 11px">เลือกกิจกรรมจาก Catalog (HR)</label>
        <select
          class="sel"
          style="font-size: 12px; margin-top: 4px"
          :value="form.catalog"
          @change="onCatalogChange"
        >
          <option value="">— เลือกกิจกรรมจาก Catalog —</option>
          <option v-for="c in catalogOptions" :key="c" :value="c">{{ c }}</option>
          <option value="custom">ระบุกิจกรรมเอง</option>
        </select>
      </div>
      <div class="fg" style="margin: 0">
        <label class="lbl" style="font-size: 11px">
          ประเภทการเรียนรู้ <span style="color: var(--red)">*</span>
        </label>
        <select
          class="sel"
          style="font-size: 12px; margin-top: 4px"
          :value="form.method"
          @change="e => emit('update:form', { method: (e.target as HTMLSelectElement).value })"
        >
          <option value="">— เลือกประเภท —</option>
          <option v-for="m in methods" :key="m.key" :value="m.key">{{ m.label }}</option>
        </select>
      </div>
    </div>

    <div class="fg mb12">
      <label class="lbl" style="font-size: 11px">
        ชื่อกิจกรรม <span style="color: var(--red)">*</span>
      </label>
      <input
        class="inp"
        style="font-size: 12px; margin-top: 4px"
        :value="form.title"
        :readonly="!!form.catalog && form.catalog !== 'custom'"
        @input="e => emit('update:form', { title: (e.target as HTMLInputElement).value })"
        placeholder="เช่น อบรม AI & Data Analytics หรือระบุกิจกรรมของตัวเอง"
      />
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px">
      <div class="fg" style="margin: 0">
        <label class="lbl" style="font-size: 11px">
          วันที่เริ่ม <span style="color: var(--red)">*</span>
        </label>
        <input
          type="date"
          class="inp"
          style="font-size: 12px; margin-top: 4px"
          :value="form.startDate"
          @change="e => emit('update:form', { startDate: (e.target as HTMLInputElement).value })"
        />
      </div>
      <div class="fg" style="margin: 0">
        <label class="lbl" style="font-size: 11px">
          วันที่สิ้นสุด <span style="color: var(--red)">*</span>
        </label>
        <input
          type="date"
          class="inp"
          style="font-size: 12px; margin-top: 4px"
          :style="endDateBorderStyle"
          :value="form.endDate"
          :min="form.startDate || undefined"
          @change="e => emit('update:form', { endDate: (e.target as HTMLInputElement).value })"
        />
        <div
          v-if="form.endDate && form.startDate && form.endDate < form.startDate"
          style="color: var(--red); font-size: 11px; margin-top: 3px"
        >
          วันสิ้นสุดต้องอยู่หลังวันที่เริ่ม
        </div>
      </div>
      <div class="fg" style="margin: 0">
        <label class="lbl" style="font-size: 11px">
          น้ำหนัก (%) <span style="color: var(--red)">*</span>
        </label>
        <input
          type="number"
          class="inp"
          :min="1"
          :max="100"
          style="font-size: 12px; margin-top: 4px"
          :style="weightBorderStyle"
          :value="form.weight"
          @input="e => emit('update:form', { weight: (e.target as HTMLInputElement).value })"
          placeholder="เช่น 30"
        />
        <div
          v-if="form.weight && (+form.weight > 100 || +form.weight <= 0)"
          style="color: var(--red); font-size: 11px; margin-top: 3px"
        >
          น้ำหนักต้องอยู่ระหว่าง 1-100
        </div>
      </div>
    </div>

    <div class="fg mb14">
      <label class="lbl" style="font-size: 11px">
        หมายเหตุ <span class="lbl-opt">(ไม่บังคับ)</span>
      </label>
      <textarea
        class="ta"
        style="font-size: 12px; min-height: 52px; margin-top: 4px"
        :value="form.note"
        @input="e => emit('update:form', { note: (e.target as HTMLTextAreaElement).value })"
        placeholder="อธิบายว่าทำไมถึงเลือกกิจกรรมนี้ หรือรายละเอียดเพิ่มเติม..."
      />
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 8px; padding-top: 10px; border-top: 1px solid var(--border)">
      <button class="btn btn-s btn-sm" type="button" @click="emit('cancel')">ยกเลิก</button>
      <button class="btn btn-s btn-sm" type="button" @click="emit('save-draft')">บันทึกร่าง</button>
      <button
        class="btn btn-p btn-sm"
        type="button"
        :disabled="isSubmitDisabled"
        @click="emit('add')"
      >
        เพิ่มกิจกรรม
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

// ── Types ────────────────────────────────────────────────────────────────────

export type LearningMethodOption = {
  key: string
  label: string
  desc?: string
  color?: string
  bg?: string
  ic?: string
}

export type ActivityForm = {
  catalog: string
  method: string
  title: string
  startDate: string
  endDate: string
  duration: string
  weight: string
  note: string
}

// ── Props & Emits ─────────────────────────────────────────────────────────────

const props = defineProps<{
  form: ActivityForm
  methods: LearningMethodOption[]
}>()

const emit = defineEmits<{
  'update:form': [patch: Partial<ActivityForm>]
  cancel: []
  'save-draft': []
  add: []
}>()

// ── Static data ───────────────────────────────────────────────────────────────

const catalogOptions = [
  '[Formal] หลักสูตร AI & Data Analytics',
  '[Formal] Workshop การสื่อสาร',
  '[Formal] e-Learning ภาษาอังกฤษ',
  '[Social] Mentoring Program',
  '[Social] Coaching by หัวหน้าฝ่าย',
  '[Social] Peer Learning / Group Activity',
  '[Experiential] OJT / มอบหมายโครงการพิเศษ',
  '[Experiential] Job Rotation',
]

// ── Computed ──────────────────────────────────────────────────────────────────

const endDateBorderStyle = computed(() => {
  if (props.form.endDate && props.form.startDate && props.form.endDate < props.form.startDate) {
    return { borderColor: 'var(--red)' }
  }
  return {}
})

const weightBorderStyle = computed(() => {
  if (props.form.weight && (+props.form.weight > 100 || +props.form.weight <= 0)) {
    return { borderColor: 'var(--red)' }
  }
  return {}
})

const isSubmitDisabled = computed(() => {
  const f = props.form
  return (
    !f.title.trim() ||
    !f.method ||
    !f.startDate ||
    !f.endDate ||
    !f.weight ||
    f.endDate < f.startDate ||
    +f.weight > 100 ||
    +f.weight <= 0
  )
})

// ── Handlers ──────────────────────────────────────────────────────────────────

function onCatalogChange(e: Event) {
  const val = (e.target as HTMLSelectElement).value
  emit('update:form', {
    catalog: val,
    title: val && val !== 'custom' ? val : '',
  })
}
</script>