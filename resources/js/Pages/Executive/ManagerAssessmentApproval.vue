<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  users?: any[];
  currentUserId?: number;
}>();

const selectedUser = ref<any | null>(null);
const openedCompetencyKey = ref<string | null>(null);
const processingUserId = ref<number | null>(null);
const rejectDecision = ref<{ user: any; row: any } | null>(null);
const rejectNote = ref('');

const reviewerStepsForUser = (user: any) => {
  const steps = Array.isArray(user?.reviewerSteps) && user.reviewerSteps.length
    ? user.reviewerSteps
    : (Array.isArray(user?.supervisorChain) ? user.supervisorChain : []);

  return steps
    .map((step: any, index: number) => ({
      step: Number(step.step || index + 1),
      reviewer_id: Number(step.id || step.reviewer_id || 0),
    }))
    .filter((step: any) => step.step > 0 && step.reviewer_id > 0);
};

const evaluatorLevel = (user: any) => {
  const currentUserId = Number(props.currentUserId || 0);
  if (!currentUserId) return 0;
  return reviewerStepsForUser(user)
    .find((step) => step.reviewer_id === currentUserId)
    ?.step || 0;
};

const statusForReviewerStep = (step: number) => {
  if (step === 1) return 'self_submitted';
  if (step === 2) return 'unit_evaluated';
  if (step === 3) return 'dept_evaluated';
  return step > 3 ? `review_step_${step}` : '';
};

const expectedStatus = (user: any) => {
  const level = evaluatorLevel(user);
  return statusForReviewerStep(level);
};

const nextStatus = (user: any) => {
  const level = evaluatorLevel(user);
  const nextStep = reviewerStepsForUser(user).find((step) => step.step > level);
  return nextStep ? statusForReviewerStep(nextStep.step) : 'approved';
};

const rows = computed(() =>
  (props.users || [])
    .filter((user) => user?.act !== false && evaluatorLevel(user) > 0)
    .map((user) => ({
      ...user,
      results: assessmentRows(user),
      submittedAt: submittedAt(user),
    }))
    .sort((left, right) =>
      String(left?.n || '').localeCompare(String(right?.n || ''), 'th'))
);

const pendingRows = computed(() => rows.value.filter((user) => canApprove(user)));
const forwardedRows = computed(() => rows.value.filter((user) => user.evalStatus === nextStatus(user)));
const approvedRows = computed(() => rows.value.filter((user) => user.evalStatus === 'dean_approved'));

const canApprove = (user: any) =>
  Boolean(user?.db_id) && user.evalStatus === expectedStatus(user);

const canApproveCompetency = (user: any, row: any) =>
  Boolean(user?.db_id) && row?.hasAssessment && row?.status === expectedStatus(user);

const statusMeta = (user: any) => {
  if (canApprove(user)) return { label: 'รอตรวจ', cls: 'pending' };
  if (!user?.evalStatus || user.evalStatus === 'draft') return { label: 'ยังไม่ประเมิน', cls: 'muted' };
  if (user.evalStatus === 'revision_required') return { label: 'ส่งกลับแก้ไข', cls: 'danger' };
  if (user.evalStatus === 'self_submitted') return { label: 'รอหัวหน้าหน่วยอนุมัติ', cls: 'pending' };
  if (user.evalStatus === 'unit_evaluated') return { label: evaluatorLevel(user) === 2 ? 'รอตรวจ' : 'รอหัวหน้างานอนุมัติ', cls: 'pending' };
  if (user.evalStatus === 'dept_evaluated') return { label: evaluatorLevel(user) === 3 ? 'รอตรวจ' : 'รอคณบดีอนุมัติ', cls: 'pending' };
  if (user.evalStatus === 'dean_approved') return { label: 'ปิดรอบประเมินแล้ว', cls: 'success' };
  return { label: user.evalStatus, cls: 'muted' };
};

const approvalProgressMeta = (user: any) => {
  const submittedRows = (user.results || []).filter((row: any) => row.hasAssessment);
  if (submittedRows.length === 0) {
    return { label: 'ยังไม่ได้รับผลการประเมิน', cls: 'muted' };
  }

  const expected = expectedStatus(user);
  const reviewedRows = submittedRows.filter((row: any) => row.status && row.status !== expected);

  return {
    label: `ประเมิน ${reviewedRows.length}/${submittedRows.length}`,
    cls: reviewedRows.length === submittedRows.length ? 'success' : 'pending',
  };
};

function assessmentRows(user: any) {
  return Array.isArray(user?.competencyGaps)
    ? user.competencyGaps.map((row: any) => ({
        ...row,
        code: row.cd || row.code || '-',
        title: row.n || row.name || row.title || 'ไม่ระบุสมรรถนะ',
        group: row.t || row.type || 'CC',
        hasAssessment: row.status && row.status !== 'draft',
      }))
    : [];
}

const submittedAt = (user: any) => {
  const dates = assessmentRows(user)
    .map((row: any) => row.updatedAt || row.submittedAt || row.updated_at)
    .filter(Boolean);
  return dates[0] || user.updatedAt || '-';
};

const expectedCount = (row: any) => Number(row.expectedIndicatorCount ?? totalIndicatorCount(row) ?? 0);
const actualCount = (row: any) => Number(row.checkedIndicatorCount ?? row.actual ?? 0);
const gapValue = (row: any) => {
  if (row.gap !== null && row.gap !== undefined) return Number(row.gap);
  return actualCount(row) - expectedCount(row);
};

const totalIndicatorCount = (row: any) => {
  const expected = Number(row.expected ?? row.expectedLevel ?? 0);
  return (row.levels || [])
    .filter((level: any) => Number(level.lvl) <= expected)
    .reduce((total: number, level: any) => total + (level.indicators?.length || 0), 0);
};

const competencyIdentity = (row: any) => row.competencyId || row.competency_id || row.id || row.code || row.title;

const checkedIndicatorKey = (row: any, level: any, index: number) =>
  `${competencyIdentity(row)}:${level.id || level.lvl}:${index}`;

const checkedIndicatorSet = (row: any) => new Set(row.checkedIndicatorKeys || []);

const indicatorsForLevel = (row: any, level: any) =>
  (level.indicators || [])
    .map((indicator: string, index: number) => ({
      indicator,
      index,
      checked: checkedIndicatorSet(row).has(checkedIndicatorKey(row, level, index)),
    }));

const checkedLevelsForRow = (row: any) =>
  (row.levels || [])
    .map((level: any) => {
      const indicators = indicatorsForLevel(row, level);
      return {
        ...level,
        visibleIndicators: indicators,
        checkedIndicators: indicators.filter((item: any) => item.checked),
      };
    })
    .filter((level: any) => level.visibleIndicators.length > 0);

const competencyKey = (row: any) => String(competencyIdentity(row));

const toggleCompetency = (row: any) => {
  if (!row.hasAssessment) return;
  const key = competencyKey(row);
  openedCompetencyKey.value = openedCompetencyKey.value === key ? null : key;
};

const openModal = (user: any) => {
  if (!user.results.length) return;
  selectedUser.value = user;
  openedCompetencyKey.value = null;
};

const closeModal = () => {
  if (processingUserId.value) return;
  selectedUser.value = null;
  openedCompetencyKey.value = null;
  rejectDecision.value = null;
  rejectNote.value = '';
};

const approve = (user: any, row: any) => {
  if (!canApproveCompetency(user, row) || processingUserId.value) return;

  processingUserId.value = Number(user.db_id);
  router.post(route('assessments.approve'), {
    user_id: user.db_id,
    competency_id: row.competencyId || row.competency_id || row.id,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: [],
    onSuccess: () => {
      selectedUser.value = {
        ...selectedUser.value,
        results: (selectedUser.value?.results || []).map((item: any) =>
          String(competencyIdentity(item)) === String(competencyIdentity(row)) ? { ...item, status: nextStatus(user) } : item),
      };
    },
    onFinish: () => {
      processingUserId.value = null;
    },
  });
};

const reject = (user: any, row: any) => {
  if (!canApproveCompetency(user, row) || processingUserId.value) return;
  rejectDecision.value = { user, row };
  rejectNote.value = '';
};

const closeRejectDecision = () => {
  if (processingUserId.value) return;
  rejectDecision.value = null;
  rejectNote.value = '';
};

const submitReject = () => {
  const user = rejectDecision.value?.user;
  const row = rejectDecision.value?.row;
  if (!user || !row || !canApproveCompetency(user, row) || processingUserId.value) return;

  processingUserId.value = Number(user.db_id);
  router.post(route('assessments.reject'), {
    user_id: user.db_id,
    competency_id: row.competencyId || row.competency_id || row.id,
    reject_note: rejectNote.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: [],
    onSuccess: () => {
      selectedUser.value = {
        ...selectedUser.value,
        results: (selectedUser.value?.results || []).map((item: any) =>
          String(competencyIdentity(item)) === String(competencyIdentity(row)) ? { ...item, status: 'revision_required' } : item),
      };
      rejectDecision.value = null;
      rejectNote.value = '';
    },
    onFinish: () => {
      processingUserId.value = null;
    },
  });
};
</script>

<template>
  <section class="dean-approval-page">
    <header class="approval-page-head">
      <div>
        <h1>ตรวจประเมินลูกน้อง</h1>
        <p>คณบดีตรวจผลแบบ read-only ก่อนอนุมัติและปิดรอบประเมิน</p>
      </div>
    </header>

    <div class="approval-summary-grid">
      <article class="approval-summary-card">
        <span>รอตรวจ</span>
        <strong class="orange">{{ pendingRows.length }}</strong>
        <small>Pending คณบดี</small>
      </article>
      <article class="approval-summary-card">
        <span>อนุมัติแล้ว</span>
        <strong class="red">{{ forwardedRows.length }}</strong>
        <small>ดำเนินการในชั้นนี้แล้ว</small>
      </article>
      <article class="approval-summary-card">
        <span>อนุมัติแล้ว</span>
        <strong class="green">{{ approvedRows.length }}</strong>
        <small>ปิดรอบประเมินแล้ว</small>
      </article>
    </div>

    <section class="approval-list-card">
      <header class="approval-list-head">
        <div>
          <h2>รายการรอดำเนินการ</h2>
          <p>เฉพาะบุคลากรที่เชื่อมกับคณบดี และอยู่ในสถานะรอตรวจ</p>
        </div>
        <span>{{ pendingRows.length }} รายการ</span>
      </header>

      <div class="approval-table-wrap">
        <table>
          <thead>
            <tr>
              <th>ชื่อ-นามสกุล</th>
              <th>ตำแหน่ง</th>
              <th>วันที่ส่งประเมิน</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in rows"
              :key="user.db_id || user.sso"
              :class="{ disabled: !user.results.length }"
              @click="openModal(user)"
            >
              <td>
                <strong>{{ `${user.t || ''}${user.n || '-'}` }}</strong>
                <small>{{ user.d || 'ยังไม่ระบุหน่วยงาน' }}</small>
              </td>
              <td>{{ user.p || 'ยังไม่ระบุตำแหน่ง' }}</td>
              <td>{{ user.submittedAt }}</td>
              <td>
                <span class="approval-status-pill" :class="approvalProgressMeta(user).cls">
                  {{ approvalProgressMeta(user).label }}
                </span>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="4" class="approval-empty">ยังไม่มีบุคลากรที่เลือกคณบดีเป็นผู้ประเมิน</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="selectedUser" class="approval-modal-backdrop" @click.self="closeModal">
      <section class="approval-modal">
        <header class="approval-modal-head">
          <div>
            <h2>ตรวจสอบผลประเมิน · {{ `${selectedUser.t || ''}${selectedUser.n || '-'}` }}</h2>
            <p>{{ selectedUser.p || 'ยังไม่ระบุตำแหน่ง' }} · แสดงระดับและจำนวนพฤติกรรมบ่งชี้ที่คาดหวังของแต่ละสมรรถนะ</p>
          </div>
          <button class="btn btn-s btn-sm" type="button" @click="closeModal">ปิด</button>
        </header>

        <div class="approval-modal-body">
          <div class="approval-score-grid">
            <article
              v-for="row in selectedUser.results"
              :key="`summary-${competencyKey(row)}`"
              class="approval-score-card"
              :class="{ disabled: !row.hasAssessment }"
            >
              <h3>{{ row.code }}</h3>
              <div class="approval-score-meta">
                <span>ระดับ {{ row.expected ?? '-' }}</span>
                <span>{{ expectedCount(row) }} พฤติกรรมบ่งชี้</span>
              </div>
              <template v-if="row.hasAssessment">
                <div class="approval-gap-row">
                  <span>Gap</span>
                  <strong
                    :class="{
                      negative: gapValue(row) < 0,
                      positive: gapValue(row) > 0,
                      neutral: gapValue(row) === 0,
                    }"
                  >{{ gapValue(row) > 0 ? '+' : '' }}{{ gapValue(row) }}</strong>
                </div>
                <em
                  :class="{
                    negative: gapValue(row) < 0,
                    positive: gapValue(row) > 0,
                    neutral: gapValue(row) === 0,
                  }"
                >{{ gapValue(row) < 0 ? 'ต่ำกว่าเกณฑ์' : 'ผ่านเกณฑ์' }}</em>
              </template>
              <template v-else>
                <div class="approval-gap-row muted">
                  <span>Gap</span>
                  <strong>-</strong>
                </div>
                <em class="neutral">ยังไม่ประเมิน</em>
              </template>
              <small>{{ actualCount(row) }}/{{ expectedCount(row) }} ข้อ</small>
            </article>
          </div>

          <div class="approval-gap-note">
            หมายเหตุ: Gap คำนวณจากจำนวนพฤติกรรมบ่งชี้ที่ประเมินได้ ลบด้วยจำนวนพฤติกรรมบ่งชี้ที่คาดหวัง
          </div>

          <article
            v-for="row in selectedUser.results"
            :key="`detail-${competencyKey(row)}`"
            class="approval-competency-card"
            :class="{ disabled: !row.hasAssessment }"
          >
            <div
              class="approval-competency-head"
              :class="{ open: openedCompetencyKey === competencyKey(row), disabled: !row.hasAssessment }"
              role="button"
              tabindex="0"
              :aria-disabled="!row.hasAssessment"
              :aria-expanded="openedCompetencyKey === competencyKey(row)"
              @click="toggleCompetency(row)"
            >
              <span class="approval-competency-title">
                <span class="approval-tag" :class="{ fc: row.group === 'FC' }">{{ row.group }}</span>
                {{ row.code }} · {{ row.title }}
                <span class="approval-accordion-label">{{ row.hasAssessment ? (openedCompetencyKey === competencyKey(row) ? 'ซ่อน' : 'ดูรายละเอียด') : 'ยังไม่ประเมิน' }}</span>
              </span>
              <span class="approval-competency-meta">
                <span>ระดับคาดหวัง {{ row.expected ?? '-' }}</span>
                <strong>ประเมินได้ {{ actualCount(row) }}/{{ expectedCount(row) }} ข้อ</strong>
                <span v-if="!canApproveCompetency(selectedUser, row)" class="approval-row-status">
                  {{ row.status === 'revision_required' ? 'ส่งกลับแก้ไขแล้ว' : row.status === nextStatus(selectedUser) ? 'อนุมัติแล้ว' : 'ยังไม่พร้อมอนุมัติ' }}
                </span>
                <span v-else class="approval-row-actions" @click.stop>
                  <button
                    class="approval-row-button reject"
                    type="button"
                    :disabled="processingUserId === Number(selectedUser.db_id)"
                    @click="reject(selectedUser, row)"
                  >
                    ส่งกลับแก้ไข
                  </button>
                  <button
                    class="approval-row-button approve"
                    type="button"
                    :disabled="processingUserId === Number(selectedUser.db_id)"
                    @click="approve(selectedUser, row)"
                  >
                    อนุมัติ
                  </button>
                </span>
              </span>
            </div>

            <div v-if="openedCompetencyKey === competencyKey(row)" class="approval-accordion-body">
              <section
                v-for="level in checkedLevelsForRow(row)"
                :key="level.id || level.lvl"
                class="approval-level-card"
              >
                <div class="approval-level-head">
                  <strong>ระดับที่ {{ level.lvl }}</strong>
                  <span>เลือกแล้ว {{ level.checkedIndicators.length }}/{{ level.indicators?.length || 0 }} พฤติกรรม</span>
                </div>
                <div class="approval-checklist">
                  <label
                    v-for="{ indicator, index, checked } in level.visibleIndicators"
                    :key="`${competencyKey(row)}-${level.id || level.lvl}-${index}`"
                    class="approval-check-row"
                    :class="{ unchecked: !checked }"
                  >
                    <input :checked="checked" disabled type="checkbox" />
                    <span><strong>ข้อ {{ level.lvl }}.{{ index + 1 }}</strong> {{ indicator }}</span>
                  </label>
                </div>
              </section>
              <div v-if="checkedLevelsForRow(row).length === 0" class="approval-empty compact">
                ไม่มีพฤติกรรมบ่งชี้
              </div>
              <div class="approval-comment-box">
                <strong>Comment จากผู้ประเมินตนเอง</strong>
                <span>{{ row.note || 'ไม่มี Note' }}</span>
              </div>
            </div>
          </article>

          <div v-if="selectedUser.results.length === 0" class="approval-empty">
            ไม่มีผลคะแนนในรายการนี้
          </div>
        </div>

      </section>
    </div>

    <div v-if="rejectDecision" class="approval-decision-backdrop" @click.self="closeRejectDecision">
      <div class="approval-decision-modal">
        <div class="approval-decision-title">ยืนยันส่งกลับแก้ไข</div>
        <div class="approval-decision-message">
          ต้องการส่งกลับให้แก้ไขผลการประเมินรายสมรรถนะ
          {{ rejectDecision.row.code }} - {{ rejectDecision.row.title }} หรือไม่
        </div>
        <label class="approval-reject-note">
          <span>Note สำหรับแจ้งกลับ</span>
          <textarea
            v-model="rejectNote"
            rows="4"
            maxlength="2000"
            placeholder="ระบุเหตุผลหรือข้อเสนอแนะสำหรับการแก้ไข"
          ></textarea>
        </label>
        <div class="approval-decision-actions">
          <button class="btn btn-s" type="button" :disabled="Boolean(processingUserId)" @click="closeRejectDecision">
            ยกเลิก
          </button>
          <button class="approval-reject-button" type="button" :disabled="Boolean(processingUserId)" @click="submitReject">
            {{ processingUserId ? 'กำลังดำเนินการ...' : 'ยืนยัน' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dean-approval-page {
  display: grid;
  gap: 16px;
}

.approval-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.approval-page-head h1 {
  margin: 0;
  color: var(--text);
  font-size: 18px;
  font-weight: 900;
}

.approval-page-head p,
.approval-list-head p {
  margin: 6px 0 0;
  color: var(--text3);
  font-size: 12px;
}

.approval-export-actions {
  display: flex;
  gap: 8px;
}

.approval-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.approval-summary-card {
  min-height: 104px;
  padding: 18px 20px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
  box-shadow: var(--sh);
}

.approval-summary-card span,
.approval-summary-card small {
  display: block;
  color: var(--text3);
  font-size: 11px;
  font-weight: 700;
}

.approval-summary-card strong {
  display: block;
  margin: 6px 0 2px;
  font-size: 28px;
  line-height: 1;
}

.approval-summary-card .orange { color: #d97706; }
.approval-summary-card .red { color: #c2410c; }
.approval-summary-card .green { color: #16a34a; }

.approval-list-card {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
  box-shadow: var(--sh);
}

.approval-list-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 22px;
  border-bottom: 1px solid var(--border);
}

.approval-list-head h2 {
  margin: 0;
  color: var(--text);
  font-size: 15px;
  font-weight: 900;
}

.approval-list-head > span {
  padding: 5px 10px;
  border-radius: 999px;
  background: #fff7ed;
  color: #ea580c;
  font-size: 11px;
  font-weight: 900;
}

.approval-table-wrap {
  padding: 22px;
  overflow-x: auto;
}

.approval-table-wrap table {
  width: 100%;
  border-collapse: collapse;
}

.approval-table-wrap th {
  padding: 14px 16px;
  background: #f8fafc;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  text-align: left;
}

.approval-table-wrap td {
  padding: 15px 16px;
  border-top: 1px solid var(--border);
  color: var(--text);
  font-size: 12px;
}

.approval-table-wrap tbody tr {
  cursor: pointer;
}

.approval-table-wrap tbody tr:hover {
  background: #f8fafc;
}

.approval-table-wrap tbody tr.disabled {
  cursor: default;
  opacity: 0.6;
}

.approval-table-wrap td strong,
.approval-table-wrap td small {
  display: block;
}

.approval-table-wrap td small {
  margin-top: 5px;
  color: var(--text3);
  font-size: 11px;
}

.approval-status-pill {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 900;
}

.approval-status-pill.pending {
  background: #fff7ed;
  color: #ea580c;
}

.approval-status-pill.success {
  background: #dcfce7;
  color: #16a34a;
}

.approval-status-pill.danger {
  background: #fee2e2;
  color: #b91c1c;
}

.approval-status-pill.muted {
  background: #f1f5f9;
  color: #64748b;
}

.approval-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(15, 23, 42, 0.42);
}

.approval-modal {
  width: min(1080px, 100%);
  max-height: calc(100vh - 36px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 22px 70px rgba(15, 23, 42, 0.28);
}

.approval-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 24px;
  border-bottom: 1px solid var(--border);
}

.approval-modal-head h2 {
  margin: 0;
  color: var(--text);
  font-size: 18px;
  font-weight: 900;
}

.approval-modal-head p {
  margin: 6px 0 0;
  color: var(--text3);
  font-size: 12px;
}

.approval-modal-body {
  display: grid;
  gap: 14px;
  padding: 20px 24px;
  overflow-y: auto;
}

.approval-score-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.approval-score-card {
  min-height: 132px;
  display: grid;
  align-content: start;
  gap: 8px;
  padding: 14px 16px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
}

.approval-score-card.disabled {
  background: #f8fafc;
  opacity: 1;
}

.approval-score-card h3 {
  margin: 0;
  color: var(--text);
  font-size: 14px;
  font-weight: 900;
}

.approval-score-meta,
.approval-gap-row,
.approval-score-card small,
.approval-score-card em {
  color: var(--text3);
  font-size: 11px;
  font-style: normal;
  font-weight: 800;
}

.approval-score-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.approval-score-meta span {
  padding: 3px 7px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  line-height: 1.2;
}

.approval-gap-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}

.approval-gap-row strong {
  color: #64748b;
  font-size: 22px;
  font-weight: 900;
  line-height: 1;
}

.approval-gap-row strong.negative,
.approval-score-card em.negative {
  color: #dc2626;
}

.approval-gap-row strong.positive,
.approval-score-card em.positive {
  color: #16a34a;
}

.approval-gap-row strong.neutral,
.approval-score-card em.neutral,
.approval-gap-row.muted strong {
  color: #64748b;
}

.approval-score-card em {
  line-height: 1.25;
}

.approval-score-card small {
  margin-top: 2px;
  color: #94a3b8;
}

.approval-gap-note {
  padding: 12px 14px;
  border-left: 3px solid #94a3b8;
  background: #f8fafc;
  color: #334155;
  font-size: 12px;
}

.approval-competency-card {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
}

.approval-competency-card.disabled {
  background: #f8fafc;
  opacity: 0.62;
}

.approval-competency-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 0;
  background: #fff;
  color: inherit;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.approval-competency-head:hover,
.approval-competency-head.open {
  background: #f8fafc;
}

.approval-competency-head:disabled {
  cursor: default;
}

.approval-competency-title,
.approval-competency-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.approval-competency-title {
  color: var(--text);
  font-size: 13px;
  font-weight: 900;
}

.approval-row-status {
  padding: 4px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  color: var(--text3);
  font-size: 12px;
  font-weight: 800;
}

.approval-row-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.approval-row-button {
  min-height: 28px;
  padding: 5px 10px;
  border: 1px solid transparent;
  border-radius: 6px;
  font-family: inherit;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

.approval-row-button.reject {
  border-color: #fecaca;
  background: #fff;
  color: #dc2626;
}

.approval-row-button.approve {
  background: #c93a25;
  color: #fff;
}

.approval-row-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.approval-tag {
  padding: 4px 8px;
  border-radius: 4px;
  background: #fee2e2;
  color: #ef4444;
  font-size: 10px;
  font-weight: 900;
}

.approval-tag.fc {
  background: #ffedd5;
  color: #ea580c;
}

.approval-accordion-label,
.approval-competency-meta span,
.approval-competency-meta strong {
  padding: 5px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 10px;
  font-weight: 900;
  white-space: nowrap;
}

.approval-competency-meta strong {
  background: #fff7ed;
  color: #ea580c;
}

.approval-accordion-body {
  display: grid;
  gap: 12px;
  padding: 14px 16px 16px;
  border-top: 1px solid var(--border);
  background: #f8fafc;
}

.approval-level-card {
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
}

.approval-level-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  color: var(--text);
  font-size: 12px;
}

.approval-level-head span {
  color: var(--text3);
  font-size: 11px;
}

.approval-checklist {
  display: grid;
  gap: 8px;
}

.approval-check-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  color: var(--text2);
  font-size: 12px;
}

.approval-check-row input {
  margin-top: 2px;
}

.approval-check-row.unchecked {
  color: var(--text3);
}

.approval-comment-box {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 6px;
  background: #fff;
  color: var(--text2);
  font-size: 12px;
}

.approval-empty {
  padding: 28px 16px;
  color: var(--text3);
  text-align: center;
}

.approval-empty.compact {
  padding: 14px;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
}

.approval-decision-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(15, 23, 42, 0.46);
}

.approval-decision-modal {
  width: min(460px, 100%);
  padding: 22px 24px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.approval-decision-title {
  color: var(--text);
  font-size: 17px;
  font-weight: 900;
}

.approval-decision-message {
  margin-top: 10px;
  color: var(--text2);
  font-size: 13px;
  line-height: 1.65;
}

.approval-reject-note {
  display: grid;
  gap: 8px;
  margin-top: 14px;
  color: var(--text);
  font-size: 13px;
  font-weight: 800;
}

.approval-reject-note textarea {
  width: 100%;
  min-height: 96px;
  resize: vertical;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text);
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.55;
}

.approval-reject-note textarea:focus {
  outline: none;
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.approval-decision-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

.approval-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  background: #fff;
}

.approval-reject-button,
.approval-confirm-button {
  min-height: 36px;
  padding: 0 16px;
  border-radius: 6px;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}

.approval-reject-button {
  border: 1px solid #fca5a5;
  background: #fff;
  color: #dc2626;
}

.approval-confirm-button {
  border: 1px solid var(--teal);
  background: var(--teal);
  color: #fff;
}

.approval-reject-button:disabled,
.approval-confirm-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

@media (max-width: 980px) {
  .approval-summary-grid,
  .approval-score-grid {
    grid-template-columns: 1fr;
  }

  .approval-page-head,
  .approval-competency-head,
  .approval-competency-title,
  .approval-competency-meta {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
