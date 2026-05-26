<template>
  <!-- ── Header ───────────────────────────────────────────────────────────── -->
  <div class="mb20">
    <div class="sec-t">อัปเดตความก้าวหน้า</div>
    <div class="sec-s">บันทึกผลการพัฒนา แนบหลักฐาน และอัปเดตสถานะกิจกรรม IDP</div>
  </div>

  <div v-if="totalActivities === 0" class="card empty-progress">
    <div class="empty-icon">📭</div>
    <div class="empty-title">ยังไม่มีกิจกรรมให้ติดตามความก้าวหน้า</div>
    <div class="empty-desc">
      เมื่อมีการเพิ่มกิจกรรมในแผน IDP แล้ว รายการสำหรับบันทึกความก้าวหน้าจะแสดงที่หน้านี้
    </div>
  </div>

  <!-- ── Gap sections ──────────────────────────────────────────────────────── -->
  <div v-for="{ g, acts } in list" v-else :key="g.cd" style="margin-bottom: 24px">

    <!-- Gap header -->
    <div
      style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px 14px; border-radius: var(--r-lg)"
      :style="{
        background:   hasFailed(acts) ? '#FEF2F2'        : 'var(--blue-lt)',
        border:       `1px solid ${hasFailed(acts) ? '#FECACA' : 'var(--blue-md)'}`,
        borderLeft:   `4px solid ${hasFailed(acts) ? 'var(--red)' : 'var(--blue)'}`,
      }"
    >
      <span v-if="hasFailed(acts)" style="font-size: 16px; flex-shrink: 0">!</span>
      <span :class="gapTagClass(g.t)">{{ g.t }}</span>
      <span class="fw7 fs13" :style="{ color: hasFailed(acts) ? 'var(--red)' : 'inherit' }">
        {{ g.n }}
      </span>
      <div style="display: flex; align-items: center; gap: 6px; margin-left: auto">
        <span v-if="hasFailed(acts)" class="b br">มีกิจกรรมไม่ผ่าน</span>
        <span :class="`b ${g.pri === 'high' ? 'br' : 'by'}`">
          {{ g.pri === 'high' ? 'เร่งด่วน' : 'ต้องพัฒนา' }}
        </span>
      </div>
    </div>

    <!-- Activity cards -->
    <div
      v-for="(act, aIdx) in acts"
      :key="aIdx"
      class="card mb10"
    >
      <!-- Card header -->
      <div class="ch">
        <div
          style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0"
          :style="{ background: act.bg }"
        >
          {{ act.ic }}
        </div>
        <div style="flex: 1; margin-left: 10px">
          <div class="fw7 fs13">{{ act.t }}</div>
          <div class="muted fs12 mt4">{{ act.m }} · ครบ {{ act.due }}</div>
        </div>
        <span :class="`b ${act.stC}`">{{ act.st }}</span>
      </div>

      <!-- Failed banner -->
      <div
        v-if="act.result === 'failed'"
        style="background: #FEF2F2; border-top: 1px solid #FECACA; border-bottom: 1px solid #FECACA; padding: 12px 18px; display: flex; gap: 14px; align-items: flex-start"
      >
        <div style="font-size: 20px; flex-shrink: 0">!</div>
        <div style="flex: 1">
          <div class="fw7 fs13" style="color: var(--red); margin-bottom: 2px">กิจกรรมนี้ไม่ผ่าน</div>
          <div class="fs12" style="color: #991B1B; margin-bottom: 6px">
            <span class="fw6">{{ act.rejectedBy }}</span> · {{ act.rejectedDate }}
          </div>
          <div
            class="fs12"
            style="background: #fff; border: 1px solid #FECACA; border-radius: 6px; padding: 8px 10px; color: var(--text2); margin-bottom: 10px"
          >
            "{{ act.rejectComment }}"
          </div>
        </div>
      </div>

      <!-- Card body -->
      <div class="cb" style="padding-top: 10px">

        <!-- Progress form (hidden when failed) -->
        <div
          v-if="act.result !== 'failed'"
          style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--r); padding: 12px; margin-bottom: 12px"
        >
          <div class="fw7 fs12 mb8">บันทึกความก้าวหน้าใหม่</div>

          <!-- Note textarea -->
          <div class="fg mb8">
            <label class="lbl" style="font-weight: 500; font-size: 11px">บันทึก</label>
            <textarea
              class="ta"
              style="min-height: 52px; font-size: 12px"
              placeholder="สรุปสิ่งที่ทำ ผลที่ได้รับ..."
              :value="getForm(g.cd, aIdx).note"
              @input="e => setForm(g.cd, aIdx, { note: (e.target as HTMLTextAreaElement).value })"
            />
          </div>

          <!-- Evidence section -->
          <div class="g2 mb8">
            <!-- File upload -->
            <div>
              <div class="lbl mb8" style="font-size: 11px">
                แนบหลักฐาน <span class="lbl-opt">(ถ้ามี)</span>
              </div>
              <label
                class="upload-area"
                style="width: 100%; padding: 12px; background: #fff; cursor: pointer; display: block"
              >
                <input
                  type="file"
                  style="display: none"
                  @change="e => onFileChange(e, g.cd, aIdx)"
                />
                <div style="font-size: 18px; margin-bottom: 4px">📎</div>
                <div class="fw6 fs12">
                  {{ getForm(g.cd, aIdx).fileName || 'คลิกเพื่อแนบไฟล์' }}
                </div>
                <div class="muted fs11">PDF, Word, รูปภาพ</div>
              </label>
            </div>

            <!-- URL + description -->
            <div>
              <div class="fg">
                <label class="lbl" style="font-weight: 500; font-size: 11px">URL หลักฐาน</label>
                <input
                  class="inp"
                  style="font-size: 12px"
                  placeholder="https://..."
                  :value="getForm(g.cd, aIdx).evidenceUrl"
                  @input="e => setForm(g.cd, aIdx, { evidenceUrl: (e.target as HTMLInputElement).value })"
                />
              </div>
              <div class="fg mb0">
                <label class="lbl" style="font-weight: 500; font-size: 11px">คำอธิบาย</label>
                <textarea
                  class="ta"
                  style="min-height: 48px; font-size: 12px"
                  placeholder="อธิบายหลักฐานหรือรายละเอียดเพิ่มเติม..."
                  :value="getForm(g.cd, aIdx).evidenceDesc"
                  @input="e => setForm(g.cd, aIdx, { evidenceDesc: (e.target as HTMLTextAreaElement).value })"
                />
              </div>
            </div>
          </div>

          <!-- Action buttons -->
          <div style="display: flex; justify-content: flex-end; gap: 6px">
            <button class="btn btn-s btn-sm" type="button" @click="saveProgress(g.cd, aIdx, 'draft')">
              บันทึกร่าง
            </button>
            <button class="btn btn-t btn-sm" type="button" @click="saveProgress(g.cd, aIdx, 'saved')">
              บันทึก
            </button>
          </div>
        </div>

        <!-- Log history -->
        <div class="fw7 fs12 mb8 muted">ประวัติการอัปเดต</div>
        <div
          v-for="(L, lIdx) in act.logs"
          :key="lIdx"
          class="flex ic g8"
          style="padding: 8px 0; border-bottom: 1px solid var(--border)"
          :style="{ background: L.type === 'reject' ? '#FEF2F2' : 'transparent' }"
        >
          <div style="width: 60px; font-size: 11px; color: var(--text3); flex-shrink: 0">
            {{ L.d }}
          </div>
          <div style="flex: 1; font-size: 12px">{{ L.n }}</div>
          <span class="muted fs11">by {{ L.by }}</span>
        </div>

      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'
import { IDP_GAPS_DATA, IDP_ACTIVITIES_DATA } from '../../data'

// ── Types ─────────────────────────────────────────────────────────────────────

type ProgressForm = {
  note: string
  evidenceUrl: string
  evidenceDesc: string
  fileName: string
}

type Activity = {
  ic: string
  bg: string
  t: string
  m: string
  due: string
  st: string
  stC: string
  result: string | null
  rejectedBy?: string
  rejectedDate?: string
  rejectComment?: string
  logs: { d: string; n: string; by: string; type: string }[]
}

// ── Storage helpers ───────────────────────────────────────────────────────────

const EMPLOYEE_IDP_ACTIVITIES_KEY  = 'cidp-employee-idp-activities'
const EMPLOYEE_PROGRESS_FORMS_KEY  = 'cidp-employee-progress-forms'

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

const activitiesByGap = ref<Record<string, Activity[]>>(
  readStorage(EMPLOYEE_IDP_ACTIVITIES_KEY, IDP_ACTIVITIES_DATA)
)

const progressForms = ref<Record<string, ProgressForm>>(
  readStorage(EMPLOYEE_PROGRESS_FORMS_KEY, {})
)

// ── Persist ───────────────────────────────────────────────────────────────────

watchEffect(() => writeStorage(EMPLOYEE_IDP_ACTIVITIES_KEY, activitiesByGap.value))
watchEffect(() => writeStorage(EMPLOYEE_PROGRESS_FORMS_KEY, progressForms.value))

// ── Derived list ──────────────────────────────────────────────────────────────

const list = computed(() => IDP_GAPS_DATA
  .map(g => ({
    g,
    acts: activitiesByGap.value[g.cd] || [],
  }))
  .filter(item => item.acts.length > 0)
)

const totalActivities = computed(() =>
  list.value.reduce((sum, item) => sum + item.acts.length, 0)
)

// ── Helpers ───────────────────────────────────────────────────────────────────

function hasFailed(acts: Activity[]) {
  return acts.some(a => a.result === 'failed')
}

function gapTagClass(type: string) {
  if (type === 'CC')  return 'tag-cc'
  if (type === 'MC')  return 'tag-mc'
  if (type === 'FC1') return 'tag-fc1'
  if (type === 'FC2') return 'tag-fc2'
  return 'tag-fc'
}

function formKey(gapCode: string, actIdx: number) {
  return `${gapCode}-${actIdx}`
}

const EMPTY_FORM: ProgressForm = { note: '', evidenceUrl: '', evidenceDesc: '', fileName: '' }

function getForm(gapCode: string, actIdx: number): ProgressForm {
  return progressForms.value[formKey(gapCode, actIdx)] ?? { ...EMPTY_FORM }
}

function setForm(gapCode: string, actIdx: number, patch: Partial<ProgressForm>) {
  const key = formKey(gapCode, actIdx)
  progressForms.value = {
    ...progressForms.value,
    [key]: { ...getForm(gapCode, actIdx), ...patch },
  }
}

function clearForm(gapCode: string, actIdx: number) {
  const key = formKey(gapCode, actIdx)
  progressForms.value = { ...progressForms.value, [key]: { ...EMPTY_FORM } }
}

// ── Event handlers ────────────────────────────────────────────────────────────

function onFileChange(e: Event, gapCode: string, actIdx: number) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) setForm(gapCode, actIdx, { fileName: file.name })
  ;(e.target as HTMLInputElement).value = ''
}

function buildLogMessage(form: ProgressForm, mode: 'draft' | 'saved') {
  const parts = [form.note.trim()]
  if (form.fileName)            parts.push(`แนบไฟล์: ${form.fileName}`)
  if (form.evidenceUrl.trim())  parts.push(`URL: ${form.evidenceUrl.trim()}`)
  if (form.evidenceDesc.trim()) parts.push(`คำอธิบาย: ${form.evidenceDesc.trim()}`)
  const summary = parts.filter(Boolean).join(' | ')
  return mode === 'draft'
    ? summary ? `บันทึกร่าง: ${summary}` : 'บันทึกร่างความก้าวหน้า'
    : summary || 'อัปเดตความก้าวหน้า'
}

function saveProgress(gapCode: string, actIdx: number, mode: 'draft' | 'saved') {
  const form = getForm(gapCode, actIdx)
  if (!form.note.trim() && !form.fileName && !form.evidenceUrl.trim() && !form.evidenceDesc.trim()) {
    alert('กรุณากรอกบันทึกหรือแนบหลักฐานอย่างน้อย 1 รายการ')
    return
  }

  const today = new Date().toLocaleDateString('th-TH', {
    day: 'numeric', month: 'short', year: '2-digit',
  })

  const updatedGapActs = (activitiesByGap.value[gapCode] || []).map((act, index) => {
    if (index !== actIdx) return act
    return {
      ...act,
      ...(mode === 'draft'
        ? { st: 'ร่าง', stC: 'by' }
        : act.result === 'done'
          ? { st: act.st, stC: act.stC }
          : { st: 'กำลังดำเนินการ', stC: 'bt' }),
      logs: [
        {
          d: today,
          n: buildLogMessage(form, mode),
          by: 'ผู้ใช้งานปัจจุบัน',
          type: mode === 'draft' ? 'draft' : 'log',
        },
        ...(act.logs || []),
      ],
    }
  })

  activitiesByGap.value = { ...activitiesByGap.value, [gapCode]: updatedGapActs }
  clearForm(gapCode, actIdx)
  alert(mode === 'draft' ? 'บันทึกร่างเรียบร้อย' : 'บันทึกความก้าวหน้าเรียบร้อย')
}
</script>

<style scoped>
.empty-progress {
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
  max-width: 520px;
  color: var(--text3);
  font-size: 13px;
}
</style>
