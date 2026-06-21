<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
  user: any;
  setUsers: any;
  competencies?: any[];
  blocked?: boolean;
  blockReasons?: string[];
}>();

const assignedCompetencies = computed(() => props.competencies || []);
const blockReasons = computed(() => props.blockReasons || []);
const isAssessmentBlocked = computed(() => Boolean(props.blocked) || blockReasons.value.length > 0);
const selectedCompetency = ref<any | null>(null);
const checkedIndicators = ref<Record<string, boolean>>({});
const competencyNotes = ref<Record<string, string>>({});
const isSaving = ref(false);
const showSubmitConfirm = ref(false);
const lockedCompetencies = ref<Record<string, boolean>>({});
const competencyStatuses = ref<Record<string, string>>({});
const competencyDraftSavedAt = ref<Record<string, string>>({});
const competencyRejectComments = ref<Record<string, string>>({});
const autoSaveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const draftSavedAt = ref<string>('');
const suppressAutoSave = ref(false);
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

const isLockedStatus = (status: string) => !['draft', 'revision_required'].includes(status);
const isApprovedStatus = (status: string) => ['unit_evaluated', 'dept_evaluated', 'approved', 'dean_approved'].includes(status);
const competencyStatus = (item: any) => competencyStatuses.value[String(item?.id || '')] || item?.assessmentStatus || 'draft';
const submittedAssessmentCount = computed(() =>
  assignedCompetencies.value.reduce((total: number, item: any) => {
    const status = competencyStatus(item);
    if (status === 'revision_required') return total - 1;
    if (status === 'self_submitted' || isApprovedStatus(status)) return total + 1;
    return total;
  }, 0),
);
const competencyStatusLabel = (item: any) => {
  const status = competencyStatus(item);
  if (isApprovedStatus(status)) return 'อนุมัติแล้ว';
  if (status === 'self_submitted') return 'รออนุมัติ';
  if (status === 'revision_required') return 'ส่งกลับแก้ไข';
  return '';
};

const draftStatusText = (item: any) => {
  const value = competencyDraftSavedAt.value[String(item?.id || '')] || item?.lastDraftSavedAt || '';
  const date = value ? new Date(value) : null;
  if (!date || Number.isNaN(date.getTime())) return '';

  return `บันทึกฉบับร่างเมื่อ ${new Intl.DateTimeFormat('th-TH', {
    day: '2-digit',
    month: 'short',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)}`;
};

const formatDraftSavedAt = (value: string) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return new Intl.DateTimeFormat('th-TH', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

const autoSaveText = computed(() => {
  if (isSelectedCompetencyLocked.value) return '';
  if (autoSaveState.value === 'saving') return 'กำลังบันทึกฉบับร่าง...';
  if (autoSaveState.value === 'error') return 'บันทึกฉบับร่างไม่สำเร็จ';
  const time = formatDraftSavedAt(draftSavedAt.value);
  return time ? `บันทึกฉบับร่าง เมื่อ ${time}` : 'ยังไม่ได้บันทึกฉบับร่าง';
});

const clearAutoSaveTimer = () => {
  if (!autoSaveTimer) return;
  clearTimeout(autoSaveTimer);
  autoSaveTimer = null;
};

const checkedIndicatorsForCompetency = (competencyId: string | number) => {
  const prefix = `${competencyId}:`;
  return Object.fromEntries(
    Object.entries(checkedIndicators.value).filter(([key, checked]) => key.startsWith(prefix) && checked),
  );
};

const autosaveSignature = computed(() => {
  if (!selectedCompetency.value || isSelectedCompetencyLocked.value || isAssessmentBlocked.value) return '';
  const competencyId = selectedCompetency.value.id;

  return JSON.stringify({
    competencyId,
    checked: checkedIndicatorsForCompetency(competencyId),
    note: competencyNotes.value[noteKey.value] || '',
    score: currentScore.value,
  });
});

const saveDraft = async () => {
  if (!selectedCompetency.value || isSelectedCompetencyLocked.value || isAssessmentBlocked.value) return;

  autoSaveState.value = 'saving';

  try {
    const { data } = await (window as any).axios.post(route('assessments.draft'), {
      competency_id: selectedCompetency.value.id,
      checked_indicators: checkedIndicators.value,
      note: competencyNotes.value[noteKey.value] || '',
      score: parseFloat(currentScore.value),
    });

    draftSavedAt.value = data.lastDraftSavedAt || new Date().toISOString();
    competencyDraftSavedAt.value[String(selectedCompetency.value.id)] = draftSavedAt.value;
    selectedCompetency.value.lastDraftSavedAt = draftSavedAt.value;
    competencyStatuses.value[String(selectedCompetency.value.id)] = data.status || 'draft';
    autoSaveState.value = 'saved';
  } catch {
    autoSaveState.value = 'error';
  }
};

const openCompetencyDetail = async (item: any) => {
  if (isAssessmentBlocked.value) return;

  clearAutoSaveTimer();
  suppressAutoSave.value = true;
  autoSaveState.value = 'idle';
  draftSavedAt.value = '';
  selectedCompetency.value = item;
  competencyStatuses.value[String(item.id)] = item.assessmentStatus || 'draft';
  lockedCompetencies.value[String(item.id)] = isLockedStatus(competencyStatuses.value[String(item.id)]);

  try {
    const res = await fetch(
      route('assessments.load')
      + `?competency_id=${item.id}`
    );
    const data = await res.json();
    const prefix = `${item.id}:`;
    checkedIndicators.value = {
      ...Object.fromEntries(Object.entries(checkedIndicators.value).filter(([key]) => !key.startsWith(prefix))),
      ...(data.checked || {}),
    };
    competencyNotes.value[String(item.id)] = data.note || '';
    competencyStatuses.value[String(item.id)] = data.status || 'draft';
    lockedCompetencies.value[String(item.id)] = Boolean(data.locked);
    draftSavedAt.value = data.lastDraftSavedAt || '';
    competencyRejectComments.value[String(item.id)] = data.reject_comment || item.rejectComment || '';
    if (data.lastDraftSavedAt) {
      competencyDraftSavedAt.value[String(item.id)] = data.lastDraftSavedAt;
      selectedCompetency.value.lastDraftSavedAt = data.lastDraftSavedAt;
    }
    autoSaveState.value = data.lastDraftSavedAt ? 'saved' : 'idle';
  } catch {
    // ถ้า load ไม่ได้ ใช้ค่าเดิม
  } finally {
    suppressAutoSave.value = false;
  }
};

const closeCompetencyDetail = () => {
  clearAutoSaveTimer();
  showSubmitConfirm.value = false;
  selectedCompetency.value = null;
  autoSaveState.value = 'idle';
  draftSavedAt.value = '';
};

const openSubmitConfirm = () => {
  if (!selectedCompetency.value || isSaving.value || isAssessmentBlocked.value) return;
  if (isSelectedCompetencyLocked.value) return;
  showSubmitConfirm.value = true;
};

const cancelSubmitConfirm = () => {
  showSubmitConfirm.value = false;
};

const saveAndClose = async () => {
  if (!selectedCompetency.value || isSaving.value || isAssessmentBlocked.value || isSelectedCompetencyLocked.value) return;
  clearAutoSaveTimer();
  isSaving.value = true;
  showSubmitConfirm.value = false;

  router.post(route('assessments.save'), {
    competency_id: selectedCompetency.value.id,
    checked_indicators: checkedIndicators.value,
    note: competencyNotes.value[noteKey.value] || '',
    score: parseFloat(currentScore.value),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      competencyStatuses.value[String(selectedCompetency.value?.id || '')] = 'self_submitted';
      lockedCompetencies.value[String(selectedCompetency.value?.id || '')] = true;
      closeCompetencyDetail();
    },
    onError: () => {
      alert('ไม่สามารถบันทึกได้ กรุณาลองใหม่');
    },
    onFinish: () => {
      isSaving.value = false;
    },
  });
};

const indicatorKey = (level: any, index: number) => `${selectedCompetency.value?.id || 'item'}:${level.id || level.lvl}:${index}`;
const selectedCount = (level: any) => (level.indicators || []).filter((_: any, index: number) => checkedIndicators.value[indicatorKey(level, index)]).length;
const totalIndicators = computed(() => (selectedCompetency.value?.levels || []).reduce((total: number, level: any) => total + (level.indicators?.length || 0), 0));
const selectedIndicators = computed(() => (selectedCompetency.value?.levels || []).reduce((total: number, level: any) => total + selectedCount(level), 0));
const currentScore = computed(() => (selectedIndicators.value * 0.25).toFixed(2));
const noteKey = computed(() => String(selectedCompetency.value?.id || ''));
const isSelectedCompetencyLocked = computed(() => Boolean(lockedCompetencies.value[noteKey.value]));
const selectedCompetencyStatus = computed(() => competencyStatuses.value[noteKey.value] || selectedCompetency.value?.assessmentStatus || 'draft');
const isSelectedCompetencyApproved = computed(() => isApprovedStatus(selectedCompetencyStatus.value));
const selectedRejectComment = computed(() => competencyRejectComments.value[noteKey.value] || selectedCompetency.value?.rejectComment || '');

const flattenedIndicators = computed(() => {
  const rows: any[] = [];
  (selectedCompetency.value?.levels || []).forEach((level: any, levelIndex: number) => {
    (level.indicators || []).forEach((indicator: string, indicatorIndex: number) => {
      rows.push({ level, levelIndex, indicator, indicatorIndex, key: indicatorKey(level, indicatorIndex) });
    });
  });
  return rows;
});

const isIndicatorUnlocked = (level: any, indicatorIndex: number) => {
  if (isSelectedCompetencyLocked.value) return false;
  const key = indicatorKey(level, indicatorIndex);
  const position = flattenedIndicators.value.findIndex((row) => row.key === key);
  if (position <= 0) return true;
  return Boolean(checkedIndicators.value[flattenedIndicators.value[position - 1].key]);
};

const handleIndicatorChange = (level: any, indicatorIndex: number) => {
  if (isSelectedCompetencyLocked.value) return;
  const key = indicatorKey(level, indicatorIndex);
  const position = flattenedIndicators.value.findIndex((row) => row.key === key);

  if (!checkedIndicators.value[key]) {
    flattenedIndicators.value.slice(position + 1).forEach((row) => {
      checkedIndicators.value[row.key] = false;
    });
  }
};

watch(autosaveSignature, (signature) => {
  clearAutoSaveTimer();
  if (!signature || suppressAutoSave.value) return;

  autoSaveTimer = setTimeout(() => {
    saveDraft();
  }, 1000);
});

onBeforeUnmount(() => {
  clearAutoSaveTimer();
});
</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>ประเมินตนเอง</h1>
        <!-- <p>รอเชื่อมข้อมูลสมรรถนะและแบบประเมินจริง</p> -->
      </div>
      <span class="b" :class="isAssessmentBlocked ? 'br' : 'bgr'">
        {{ isAssessmentBlocked ? 'ยังไม่พร้อมประเมิน' : (assignedCompetencies.length ? 'พร้อมประเมิน' : 'ยังไม่มีข้อมูล') }}
      </span>
    </div>

    <div v-if="isAssessmentBlocked" class="blocked-card">
      <div class="blocked-title">ยังไม่สามารถประเมินตนเองได้</div>
      <div class="blocked-copy">กรุณาให้ Admin ตรวจสอบข้อมูลโครงสร้างและกำหนดหัวหน้างาน/ผู้บังคับบัญชาก่อนเริ่มประเมิน</div>
      <ul v-if="blockReasons.length" class="blocked-list">
        <li v-for="reason in blockReasons" :key="reason">{{ reason }}</li>
      </ul>
    </div>

    <div v-if="assignedCompetencies.length && !isAssessmentBlocked" class="summary-grid">
      <div class="summary-card">
        <div class="summary-label">สมรรถนะที่ต้องประเมิน</div>
        <div class="summary-value">{{ assignedCompetencies.length }}</div>
        <div class="summary-copy">รายการ</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">ประเมินตนเองแล้ว</div>
        <div class="summary-value">{{ Math.max(0, submittedAssessmentCount) }}</div>
        <div class="summary-copy">รายการ</div>
      </div>
    </div>

    <div v-if="assignedCompetencies.length && !isAssessmentBlocked" class="content-card">
      <div class="card-head">
        <div>
          <h2>หัวข้อสมรรถนะที่ต้องประเมิน</h2>
          <p>รายการเหล่านี้มาจากการกำหนดของ HR</p>

        </div>
      </div>
      <div class="competency-list">
        <button
          v-for="item in assignedCompetencies"
          :key="item.id"
          class="competency-row"
          :class="{
            'status-approved': isApprovedStatus(competencyStatus(item)),
            'status-pending': competencyStatus(item) === 'self_submitted',
            'status-revision': competencyStatus(item) === 'revision_required',
            'status-draft': competencyStatus(item) === 'draft',
          }"
          type="button"
          @click="openCompetencyDetail(item)"
        >
          <div class="competency-title">
            <strong>{{ item.n }}</strong>
          </div>
          <div class="row-actions">
            <span v-if="draftStatusText(item)" class="draft-status">{{ draftStatusText(item) }}</span>
            <span
              v-if="competencyStatusLabel(item)"
              class="assessment-status"
              :class="{
                approved: isApprovedStatus(competencyStatus(item)),
                pending: competencyStatus(item) === 'self_submitted',
                revision: competencyStatus(item) === 'revision_required'
              }"
            >
              {{ competencyStatusLabel(item) }}
            </span>
            <span class="chevron-btn" aria-label="รายละเอียด">›</span>
          </div>
        </button>
      </div>
    </div>

    <div v-else-if="!isAssessmentBlocked" class="empty-card">
      <div class="empty-title">ยังไม่มีหัวข้อสมรรถนะที่ต้องประเมิน</div>
      <div class="empty-copy">เมื่อเชื่อมข้อมูลจริงแล้ว รายการสมรรถนะและแบบประเมินจะแสดงที่นี่</div>
    </div>

    <div v-if="selectedCompetency" class="modal-backdrop" @click.self="closeCompetencyDetail">
      <div class="detail-modal">
        <div class="modal-head">
          <div>
            <div class="modal-code">
              <span class="type-tag">{{ selectedCompetency.t || '-' }}</span>
              <span>{{ selectedCompetency.cd }}</span>
            </div>
            <h2>{{ selectedCompetency.n }}</h2>
            <p>เลือกพฤติกรรมที่ทำได้จริงตามลำดับสะสม ระบบยังไม่แสดงคะแนน Gap ในขั้นนี้</p>
            <p v-if="isSelectedCompetencyApproved" class="approved-copy">ผลการประเมินสมรรถนะนี้ผ่านการอนุมัติจากผู้บังคับบัญชาแล้ว</p>
          <p v-else-if="isSelectedCompetencyLocked" class="locked-copy">ผลการประเมินนี้ถูกส่งให้ผู้บังคับบัญชาแล้ว จะแก้ไขได้เมื่อผู้บังคับบัญชาส่งกลับมาแก้ไข</p>
          <p v-else-if="selectedCompetencyStatus === 'revision_required'" class="revision-copy">ผู้ประเมินส่งกลับมาให้ประเมินสมรรถนะนี้ใหม่</p>
          </div>
          <button class="btn btn-s btn-sm" type="button" @click="closeCompetencyDetail">ปิด</button>
        </div>

        <div class="modal-body">
          <section class="competency-detail-card">
            <div class="detail-card-top">
              <div class="detail-card-meta">
                <span class="type-tag">{{ selectedCompetency.t || '-' }}</span>
                <span>{{ selectedCompetency.cd || '-' }}</span>
              </div>
              <span class="level-pill">ความคาดหวังคะแนนของคุณ : {{ selectedCompetency.expectedLevel || '-' }}</span>
            </div>
            <h3>{{ selectedCompetency.n }}</h3>
            <div class="competency-description-box">
              <div class="description-label">คำอธิบายสมรรถนะ</div>
              <p>{{ selectedCompetency.det || 'ไม่มีคำอธิบาย' }}</p>
            </div>
          </section>

          <div v-if="!selectedCompetency.levels?.length" class="empty-card compact">
            <div class="empty-title">ยังไม่มีรายละเอียดระดับ</div>
            <div class="empty-copy">เมื่อ Admin เพิ่มระดับและพฤติกรรม รายละเอียดจะแสดงที่นี่</div>
          </div>

          <section v-for="level in selectedCompetency.levels" v-else :key="level.id || level.lvl" class="emp-level-section">
            <div class="emp-level-head">
              <div>
                <h3>{{ level.label || `ระดับ ${level.lvl}` }}</h3>
                <p>เลือกแล้ว {{ selectedCount(level) }}/{{ level.indicators?.length || 0 }} พฤติกรรม</p>
              </div>
              <span
                class="emp-level-status"
                :class="{
                  idle: selectedCount(level) === 0,
                  done: selectedCount(level) === (level.indicators?.length || 0) && (level.indicators?.length || 0) > 0
                }"
              >
                {{
                  selectedCount(level) === 0
                    ? 'ยังไม่เริ่ม'
                    : selectedCount(level) === (level.indicators?.length || 0) && (level.indicators?.length || 0) > 0
                      ? 'ครบระดับ'
                      : 'กำลังประเมิน'
                }}
              </span>
            </div>

            <div class="emp-indicator-list">
              <label v-for="(indicator, index) in level.indicators" :key="index" class="emp-indicator-row">
                <input
                  v-model="checkedIndicators[indicatorKey(level, index)]"
                  :disabled="isSelectedCompetencyLocked || !isIndicatorUnlocked(level, index)"
                  type="checkbox"
                  @change="handleIndicatorChange(level, index)"
                />
                <span class="emp-indicator-copy">
                  <strong>ข้อ {{ level.lvl }}.{{ index + 1 }}</strong>
                  <small>{{ indicator }}</small>
                </span>

                <em
                  v-if="isIndicatorUnlocked(level, index) && !checkedIndicators[indicatorKey(level, index)]"
                  class="next-pill"
                >
                  ลำดับถัดไป
                </em>
              </label>

              <div v-if="!level.indicators?.length" class="emp-indicator-empty">
                ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้
              </div>
            </div>
          </section>

          <section v-if="selectedCompetencyStatus === 'revision_required' && selectedRejectComment" class="reviewer-comment-section">
            <div class="reviewer-comment-label">Comment จากผู้ประเมิน</div>
            <p>{{ selectedRejectComment }}</p>
          </section>

          <section v-if="selectedCompetency.levels?.length" class="emp-note-section">
            <label for="competency-note">ความคิดเห็นต่อสมรรถนะนี้</label>
            <textarea
              id="competency-note"
              v-model="competencyNotes[noteKey]"
              :disabled="isSelectedCompetencyLocked"
              placeholder="อธิบายเหตุผลประกอบการประเมินสมรรถนะนี้..."
            />
          </section>
        </div>

        <div class="modal-foot">
          <div class="assessment-save-meta">
            <span>คะแนนปัจจุบัน {{ currentScore }}/5.00 จาก {{ selectedIndicators }}/{{ totalIndicators }} พฤติกรรม</span>
            <small
              v-if="autoSaveText"
              class="autosave-status"
              :class="{ saving: autoSaveState === 'saving', saved: autoSaveState === 'saved', error: autoSaveState === 'error' }"
            >
              {{ autoSaveText }}
            </small>
          </div>
          <button class="btn btn-t" type="button" :disabled="isSaving || isSelectedCompetencyLocked" @click="openSubmitConfirm">
            {{ isSelectedCompetencyApproved ? 'อนุมัติแล้ว' : (isSelectedCompetencyLocked ? 'ส่งแล้ว' : (isSaving ? 'กำลังบันทึก...' : 'บันทึกและส่งตรวจ')) }}
            <small>{{ selectedIndicators }}/{{ totalIndicators }}</small>
          </button>
        </div>
      </div>
    </div>

    <div v-if="showSubmitConfirm" class="confirm-backdrop" @click.self="cancelSubmitConfirm">
      <div class="confirm-modal">
        <div class="confirm-title">ยืนยันการส่งผลการประเมิน</div>
        <p>หากกดบันทึกและส่งตรวจจะเป็นการยืนยันผลการประเมินและส่งต่อไปยังผู้บังคับบัญชา ต้องการยืนยันหรือไม่</p>
        <div class="confirm-actions">
          <button class="btn btn-s" type="button" @click="cancelSubmitConfirm">ยกเลิก</button>
          <button class="btn btn-t" type="button" :disabled="isSaving" @click="saveAndClose">ยืนยัน</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.employee-page { display: grid; gap: 20px; }
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-head h1 { margin: 0; color: var(--text); font-size: 22px; font-weight: 900; }
.page-head p { margin: 8px 0 0; color: var(--text3); font-size: 13px; }
.blocked-card {
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fff7f7;
  padding: 18px 20px;
  color: #991b1b;
}
.blocked-title { font-size: 16px; font-weight: 900; }
.blocked-copy { margin-top: 6px; color: #b91c1c; font-size: 13px; }
.blocked-list { margin: 12px 0 0; padding-left: 20px; color: #7f1d1d; font-size: 13px; }
.blocked-list li + li { margin-top: 4px; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.summary-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 18px 20px;
  box-shadow: var(--shadow);
}
.summary-label { color: var(--text3); font-size: 12px; font-weight: 700; }
.summary-value { color: var(--text); font-size: 28px; font-weight: 900; line-height: 1.1; margin-top: 10px; }
.summary-copy { color: var(--text3); font-size: 12px; margin-top: 6px; }
.content-card {
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.card-head { padding: 18px 20px; border-bottom: 1px solid var(--border); }
.card-head h2 { margin: 0; color: var(--text); font-size: 16px; font-weight: 900; }
.card-head p { margin: 6px 0 0; color: var(--text3); font-size: 12px; }
.competency-list { display: grid; gap: 10px; padding: 16px 20px; }
.competency-row {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) 320px 150px 42px;
  align-items: center;
  gap: 16px;
  width: 100%;
  border: 1px solid var(--border);
  border-left: 3px solid #94a3b8;
  border-radius: 12px;
  background: #fff;
  padding: 14px 16px;
  text-align: left;
  cursor: pointer;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.competency-row.status-approved { border-left-color: #10b981; }
.competency-row.status-pending { border-left-color: #f59e0b; }
.competency-row.status-revision { border-left-color: #ef4444; }
.competency-row.status-draft { border-left-color: #94a3b8; }
.competency-row:hover { border-color: rgba(37, 99, 235, .35); box-shadow: 0 12px 28px rgba(15, 23, 42, .08); }
.competency-row.status-approved:hover { border-left-color: #10b981; }
.competency-row.status-pending:hover { border-left-color: #f59e0b; }
.competency-row.status-revision:hover { border-left-color: #ef4444; }
.competency-row.status-draft:hover { border-left-color: #94a3b8; }
.competency-title {
  display: grid;
  gap: 4px;
  min-width: 0;
  color: var(--text);
  line-height: 1.35;
}
.competency-title strong {
  min-width: 0;
  font-size: 14px;
  font-weight: 900;
  overflow: hidden;
  text-overflow: ellipsis;
}
.competency-title small {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}
.type-tag {
  min-width: 28px;
  border-radius: 8px;
  background: var(--blue-lt);
  color: var(--blue);
  padding: 4px 7px;
  text-align: center;
  font-size: 11px;
  font-weight: 900;
}
.row-actions {
  display: contents;
}
.draft-status {
  grid-column: 2;
  align-items: center;
  min-width: 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.assessment-status {
  grid-column: 3;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  border: 1px solid #dbe5f1;
  border-radius: 999px;
  background: #f8fafc;
  color: #475569;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 900;
  line-height: 1;
  white-space: nowrap;
  justify-self: center;
}
.assessment-status.approved {
  border-color: #bbf7d0;
  background: #dcfce7;
  color: #15803d;
}
.assessment-status.pending {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}
.assessment-status.revision {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}
.chevron-btn {
  grid-column: 4;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #475569;
  padding: 0;
  font-size: 22px;
  font-weight: 900;
  line-height: 1;
  justify-self: end;
}
.chevron-btn { font-weight: 900; }
.empty-card {
  display: grid;
  place-items: center;
  min-height: 260px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 32px;
  text-align: center;
}
.empty-title { color: var(--text); font-size: 16px; font-weight: 900; }
.empty-copy { color: var(--text3); font-size: 13px; margin-top: 8px; }
.compact { min-height: 160px; }
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, .48);
  backdrop-filter: blur(2px);
  padding: 12px;
}
.detail-modal {
  display: flex;
  flex-direction: column;
  width: min(820px, calc(100vw - 24px));
  height: min(760px, calc(100vh - 24px));
  max-height: calc(100vh - 24px);
  min-height: 0;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
  overflow: hidden;
}
.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  flex: 0 0 auto;
  padding: 24px 28px 22px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
}
.modal-code { display: flex; align-items: center; gap: 10px; color: var(--text3); font-size: 12px; font-weight: 900; }
.modal-head h2 { margin: 12px 0 0; color: var(--text); font-size: 24px; font-weight: 900; line-height: 1.15; }
.modal-head p { margin: 6px 0 0; color: var(--text3); font-size: 12px; }
.modal-head .locked-copy {
  color: #b45309;
  font-weight: 800;
}
.modal-head .approved-copy {
  color: #047857;
  font-weight: 900;
}
.modal-head .revision-copy {
  color: #b91c1c;
  font-weight: 900;
}
.modal-body {
  flex: 1 1 auto;
  min-height: 0;
  padding: 18px 28px;
  overflow-y: auto;
  background: #f6f8fb;
}
.competency-detail-card {
  display: grid;
  gap: 10px;
  margin-bottom: 14px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  padding: 16px 18px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.detail-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.detail-card-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text3);
  font-size: 12px;
  font-weight: 900;
}
.competency-detail-card h3 {
  margin: 0;
  color: var(--text);
  font-size: 17px;
  font-weight: 900;
  line-height: 1.35;
}
.competency-description-box {
  border: 1px solid #dbe5f1;
  border-radius: 12px;
  background: #f8fafc;
  padding: 12px 14px;
}
.description-label {
  margin-bottom: 6px;
  color: #64748b;
  font-size: 12px;
  font-weight: 900;
}
.competency-description-box p {
  margin: 0;
  color: var(--text2, #475569);
  font-size: 13px;
  line-height: 1.65;
}
.level-pill {
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--green-bg, #ecfdf5);
  color: var(--green, #047857);
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}
.emp-level-section {
  display: block;
  margin-bottom: 14px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.emp-level-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  background: #eefafa;
  border-bottom: 1px solid var(--border);
}
.emp-level-head h3 { margin: 0; color: var(--text); font-size: 14px; font-weight: 900; }
.emp-level-head p { margin: 5px 0 0; color: var(--text3); font-size: 12px; }
.emp-level-status {
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.emp-level-status.idle {
  background: #e2e8f0;
  color: #475569;
}

.emp-level-status.done {
  background: #ccfbf1;
  color: #0f766e;
}
.emp-indicator-list { display: block; width: 100%; background: #fff; }
.emp-indicator-row {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  width: 100%;
  padding: 15px 18px;
  border-bottom: 1px solid var(--border);
  background: #fff;
  cursor: pointer;
  box-sizing: border-box;
}
.emp-indicator-row:hover { background: #f8fafc; }
.emp-indicator-row:last-child { border-bottom: 0; }
.emp-indicator-row:has(input:disabled) {
  background: #fafbfc;
  color: #9aa6b2;
  cursor: not-allowed;
}
.emp-indicator-row input {
  flex: 0 0 auto;
  width: 14px;
  height: 14px;
  margin-top: 2px;
  accent-color: var(--teal);
}
.emp-indicator-row input:focus,
.emp-indicator-row input:focus-visible {
  outline: none;
  box-shadow: none;
}
.emp-indicator-row input:disabled {
  opacity: .45;
  cursor: not-allowed;
}
.emp-indicator-copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  width: 100%;
}
.emp-indicator-row strong { display: block; color: var(--text); font-size: 12px; font-weight: 900; }
.emp-indicator-row small {
  display: block;
  color: var(--text);
  font-size: 13px;
  line-height: 1.65;
  white-space: normal;
  overflow: visible;
  text-overflow: initial;
  word-break: break-word;
}
.emp-indicator-row:has(input:disabled) small { color: #94a3b8; }
.emp-indicator-row em {
  display: block;
  margin-top: 6px;
  color: #94a3b8;
  font-size: 11px;
  font-style: normal;
  font-weight: 700;
}
.emp-indicator-empty { padding: 14px 16px; color: var(--text3); font-size: 12px; }
.emp-note-section {
  display: grid;
  gap: 8px;
  margin-top: 12px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  padding: 16px 18px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.emp-note-section label { color: var(--text); font-size: 13px; font-weight: 900; }
.emp-note-section textarea {
  min-height: 110px;
  width: 100%;
  resize: vertical;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  color: var(--text);
  font: inherit;
  font-size: 13px;
  line-height: 1.55;
  outline: none;
}
.emp-note-section textarea:focus {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(15, 170, 167, .12);
}
.emp-note-section textarea:disabled {
  background: #f8fafc;
  color: #94a3b8;
  cursor: not-allowed;
}
.reviewer-comment-section {
  display: grid;
  gap: 8px;
  border: 1px solid #fed7aa;
  border-radius: 14px;
  background: #fff7ed;
  padding: 16px 18px;
  color: #7c2d12;
  box-shadow: 0 8px 22px rgba(194, 65, 12, .08);
}
.reviewer-comment-label {
  color: #c2410c;
  font-size: 13px;
  font-weight: 900;
}
.reviewer-comment-section p {
  margin: 0;
  white-space: pre-wrap;
  color: #431407;
  font-size: 14px;
  line-height: 1.65;
}
.modal-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex: 0 0 auto;
  min-height: 64px;
  padding: 12px 26px;
  border-top: 1px solid var(--border);
  background: #fff;
  color: var(--text3);
  font-size: 12px;
}
.assessment-save-meta {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.assessment-save-meta > span {
  color: var(--text2);
  font-size: 12px;
  font-weight: 700;
}

.autosave-status {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  line-height: 1.35;
}

.autosave-status.saving {
  color: #d97706;
}

.autosave-status.saved {
  color: #16a34a;
}

.autosave-status.error {
  color: #dc2626;
}

.modal-foot .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 132px;
  border-radius: 10px;
  background: var(--teal, #0faaa7);
  color: #fff;
  border-color: var(--teal, #0faaa7);
  opacity: 1;
  visibility: visible;
}
.next-pill {
  flex: 0 0 auto;
  margin-left: auto;
  align-self: center;
  border-radius: 999px;
  background: #eff6ff;
  color: #2563eb;
  padding: 4px 10px;
  font-size: 11px;
  font-style: normal;
  font-weight: 800;
  white-space: nowrap;
}
.modal-foot .btn small { font-size: 11px; opacity: .85; }
.confirm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(15, 23, 42, .42);
}
.confirm-modal {
  width: min(440px, 100%);
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
  padding: 22px 24px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
}
.confirm-title {
  color: var(--text);
  font-size: 17px;
  font-weight: 900;
}
.confirm-modal p {
  margin: 10px 0 0;
  color: var(--text2);
  font-size: 13px;
  line-height: 1.65;
}
.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
}
.confirm-actions .btn {
  min-width: 96px;
  border-radius: 8px;
  font-weight: 900;
  opacity: 1;
  visibility: visible;
  justify-content: center;
  text-align: center;
}
.confirm-actions .btn-s {
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--text);
    border-radius: 8px;
}

.confirm-actions .btn-s:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: var(--text);
}
.confirm-actions .btn-t {
  border: 1px solid var(--teal, #0faaa7);
  background: var(--teal, #0faaa7);
  color: #fff;
}
.confirm-actions .btn-t:hover:not(:disabled) {
  border-color: #0f766e;
  background: #0f766e;
  color: #fff;
}
.confirm-actions .btn:disabled {
  opacity: .55;
  cursor: not-allowed;
}
@media (max-width: 760px) {
  .page-head { align-items: flex-start; flex-direction: column; }
  .summary-grid { grid-template-columns: 1fr; }
  .competency-row {
    grid-template-columns: minmax(0, 1fr) 42px;
    align-items: flex-start;
  }
  .draft-status {
    grid-column: 1 / -1;
    grid-row: 2;
  }
  .assessment-status {
    grid-column: 1;
    grid-row: 3;
    justify-self: start;
  }
  .chevron-btn {
    grid-column: 2;
    grid-row: 1;
  }
  .modal-head,
  .modal-foot { flex-direction: column; align-items: stretch; }
  .modal-body,
  .modal-head,
  .modal-foot { padding-left: 16px; padding-right: 16px; }
}
</style>
