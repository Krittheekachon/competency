<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  setPage: (p: string) => void;
  gaps?: any[];
  evalStatus?: string;
}>();

const rows = computed(() => (props.gaps || []).filter((row) => row.gap !== null && row.gap !== undefined));
const passedRows = computed(() => rows.value.filter((row) => Number(row.gap) >= 0));
const failedRows = computed(() => rows.value
  .filter((row) => Number(row.gap) < 0)
  .sort((a, b) => Number(a.gap) - Number(b.gap)));
const isFinalApproved = computed(() => ['approved', 'dean_approved'].includes(props.evalStatus || ''));
const gapStatusLabel = computed(() => {
  if (props.evalStatus === 'unit_evaluated') {
    return 'หัวหน้าหน่วยอนุมัติผลการประเมินแล้ว — รอการตรวจสอบจากหัวหน้างาน';
  }
  if (props.evalStatus === 'dept_evaluated') {
    return 'หัวหน้างานอนุมัติผลการประเมินแล้ว — รอการตรวจสอบขั้นถัดไป';
  }
  if (isFinalApproved.value) {
    return 'Gap (ยืนยันครบทุกลำดับแล้ว)';
  }

  return 'Gap เบื้องต้น (จากการประเมินตนเอง) — รอการยืนยันจากหัวหน้าหน่วย';
});
const developmentStatusLabel = (row: any) => {
  if (Number(row.gap) >= 0) return 'ผ่านเกณฑ์';
  return isFinalApproved.value ? 'เข้า IDP' : 'รอผลอนุมัติ';
};

const formatLevel = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '-';
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return '-';

  return numberValue.toFixed(2).replace(/\.00$/, '');
};

const formatGap = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '-';
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return '-';

  return `${numberValue > 0 ? '+' : ''}${numberValue.toFixed(2).replace(/\.00$/, '')}`;
};

const levelTitle = (level: any) => `ระดับ ${level?.level || '-'}`;
</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>ผลการประเมิน</h1>
        <p>คำนวณจากคะแนนการประเมินตนเองลบด้วยค่าความคาดหวัง</p>
        <span class="gap-status-badge" :class="{ confirmed: isFinalApproved }">
          {{ gapStatusLabel }}
        </span>
      </div>
      <button class="btn btn-s btn-sm" type="button" @click="setPage('emp-assess')">กลับไปประเมิน</button>
    </div>

    <div v-if="rows.length" class="summary-grid">
      <div class="summary-card">
        <div class="summary-label">สมรรถนะทั้งหมด</div>
        <div class="summary-value">{{ rows.length }}</div>
        <div class="summary-copy">รายการที่ประเมิน</div>
      </div>
      <div class="summary-card success">
        <div class="summary-label">ผ่านเกณฑ์</div>
        <div class="summary-value">{{ passedRows.length }}</div>
        <div class="summary-copy">Gap ≥ 0</div>
      </div>
      <div class="summary-card danger">
        <div class="summary-label">ไม่ผ่านเกณฑ์</div>
        <div class="summary-value">{{ failedRows.length }}</div>
        <div class="summary-copy">Gap ติดลบ</div>
      </div>
    </div>

    <section v-if="passedRows.length" class="result-section passed">
      <div class="section-head">
        <div>
          <h2>สมรรถนะที่ผ่านเกณฑ์</h2>
          <p>รายการที่คะแนนจริงเท่ากับหรือสูงกว่าคะแนนคาดหวัง</p>
        </div>
        <span>{{ passedRows.length }} รายการ</span>
      </div>
      <div class="competency-card-list">
        <article v-for="row in passedRows" :key="row.id" class="competency-card">
          <div class="competency-main">
            <span class="type-tag">{{ row.t || '-' }}</span>
            <div>
              <strong>{{ row.cd }} · {{ row.n }}</strong>
              <small>ระดับคาดหวัง {{ formatLevel(row.expected) }} · คาดหวัง {{ row.expectedIndicatorCount }} ข้อ · ทำได้ {{ formatLevel(row.actual) }} ข้อ</small>
            </div>
          </div>
          <span class="gap-badge passed">Gap {{ formatGap(row.gap) }}</span>
        </article>
      </div>
    </section>

    <section v-if="failedRows.length" class="result-section failed">
      <div class="section-head">
        <div>
          <h2>สมรรถนะที่ไม่ผ่านเกณฑ์</h2>
          <p>{{ isFinalApproved ? 'เรียงจาก Gap ติดลบมากไปน้อย เพื่อส่งต่อไปสร้าง IDP' : 'แสดงรายการที่อาจต้องพัฒนา เมื่ออนุมัติครบทุกลำดับแล้วจึงจะจัดทำ IDP ได้' }}</p>
        </div>
        <span>{{ failedRows.length }} รายการ</span>
      </div>

      <div class="failed-card-list">
        <article v-for="row in failedRows" :key="row.id" class="failed-card">
          <div class="failed-card-head">
            <div class="competency-main">
              <span class="type-tag">{{ row.t || '-' }}</span>
              <div>
                <strong>{{ row.cd }} · {{ row.n }}</strong>
                <small>Expected Level {{ formatLevel(row.expected) }} · Expected {{ row.expectedIndicatorCount }} indicators · Actual {{ formatLevel(row.actual) }}</small>
              </div>
            </div>
            <span class="gap-badge failed">Gap {{ formatGap(row.gap) }}</span>
          </div>

          <div v-if="row.missingIndicators?.length" class="missing-block">
            <div class="missing-title">พฤติกรรมที่ยังขาดทั้งหมด ({{ row.missingIndicatorCount || 0 }} ข้อ)</div>
            <div class="level-blocks">
              <section v-for="level in row.missingIndicators" :key="level.level" class="level-block">
                <div class="level-head">
                  <strong>{{ levelTitle(level) }}</strong>
                  <span>{{ level.indicators?.length || 0 }} ข้อ</span>
                </div>
                <div class="indicator-lines">
                  <div v-for="indicator in level.indicators" :key="indicator.code" class="indicator-line">
                    <span>ข้อ {{ indicator.code }}</span>
                    <p>{{ indicator.description }}</p>
                  </div>
                </div>
              </section>
            </div>
          </div>

          <div v-else class="missing-empty">ยังไม่พบรายการพฤติกรรมบ่งชี้ที่ขาดในระบบ</div>
        </article>
      </div>
    </section>

    <section v-if="rows.length" class="table-section">
      <div class="section-head compact">
        <div>
          <h2>ตารางผลการประเมินทั้งหมด</h2>
          <p>ใช้ดูรายละเอียดคะแนนคาดหวัง คะแนนจริง และสถานะการพัฒนา</p>
        </div>
      </div>
      <div class="result-table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th>สมรรถนะ</th>
              <th>จำนวนข้อที่คาดหวัง</th>
              <th>จำนวนข้อที่ประเมินได้</th>
              <th>ผลการประเมิน</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`table-${row.id}`">
              <td>
                <div class="table-competency">
                  <span class="type-tag">{{ row.t || '-' }}</span>
                  <div>
                    <strong>{{ row.cd }}</strong>
                    <small>{{ row.n }}</small>
                  </div>
                </div>
              </td>
              <td>{{ row.expectedIndicatorCount }}</td>
              <td>{{ formatLevel(row.actual) }}</td>
              <td>
                <span class="gap-pill" :class="{ negative: Number(row.gap) < 0, positive: Number(row.gap) >= 0 }">
                  {{ formatGap(row.gap) }}
                </span>
              </td>
              <td>
                <span class="status-pill" :class="{ failed: Number(row.gap) < 0, passed: Number(row.gap) >= 0 }">
                  {{ developmentStatusLabel(row) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="!rows.length" class="empty-card">
      <div class="empty-title">ยังไม่มีผลการประเมิน</div>
      <div class="empty-copy">เมื่อบันทึกการประเมินตนเองแล้ว ผลการประเมินจะแสดงในหน้านี้</div>
      <button class="btn btn-t btn-sm" type="button" @click="setPage('emp-assess')">เริ่มประเมินตนเอง</button>
    </div>
  </section>
</template>

<style scoped>
.employee-page { display: grid; gap: 18px; }
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.page-head h1 { margin: 0; color: var(--text); font-size: 22px; font-weight: 900; }
.page-head p { margin: 6px 0 0; color: var(--text3); font-size: 13px; }
.gap-status-badge {
  display: inline-flex;
  align-items: center;
  margin-top: 8px;
  padding: 4px 10px;
  border-radius: 999px;
  background: #f1f5f9;
  color: var(--text3);
  font-size: 11px;
  font-weight: 800;
}
.gap-status-badge.confirmed {
  background: #ecfdf5;
  color: #059669;
}
.summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.summary-card,
.result-section,
.table-section {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.summary-card { padding: 18px 20px; }
.summary-card.success .summary-value { color: #059669; }
.summary-card.danger .summary-value { color: #dc2626; }
.summary-label { color: var(--text3); font-size: 12px; font-weight: 800; }
.summary-value { margin-top: 8px; color: var(--text); font-size: 28px; font-weight: 900; line-height: 1.1; }
.summary-copy { margin-top: 6px; color: var(--text3); font-size: 12px; }
.result-section.passed { border-color: #86efac; background: #f0fdf4; }
.result-section.failed { border-color: #fecaca; background: #fef2f2; }
.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(148, 163, 184, .28);
}
.section-head.compact { border-bottom-color: var(--border); }
.section-head h2 { margin: 0; color: var(--text); font-size: 16px; font-weight: 900; }
.section-head p { margin: 6px 0 0; color: var(--text3); font-size: 13px; }
.section-head > span { color: inherit; font-size: 12px; font-weight: 900; white-space: nowrap; }
.passed .section-head > span { color: #059669; }
.failed .section-head > span { color: #dc2626; }
.competency-card-list,
.failed-card-list { display: grid; gap: 10px; padding: 16px 20px; }
.competency-card,
.failed-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
}
.passed .competency-card { border-color: #86efac; }
.failed-card { border-color: #fecaca; }
.competency-card,
.failed-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 16px;
}
.failed-card-head { border-bottom: 1px solid #fecaca; }
.competency-main,
.table-competency {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;
}
.competency-main strong,
.table-competency strong { display: block; color: var(--text); font-size: 14px; font-weight: 900; }
.competency-main small,
.table-competency small { display: block; margin-top: 5px; color: var(--text3); font-size: 12px; line-height: 1.4; }
.type-tag {
  flex: 0 0 auto;
  min-width: 28px;
  border-radius: 6px;
  background: var(--blue-lt);
  color: var(--blue);
  padding: 3px 6px;
  text-align: center;
  font-size: 11px;
  font-weight: 900;
}
.gap-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 26px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}
.gap-badge.passed { background: #ecfdf5; color: #059669; }
.gap-badge.failed { background: #fff1f2; color: #dc2626; }
.missing-block { padding: 14px 16px 16px; }
.missing-title { margin-bottom: 10px; color: var(--text); font-size: 13px; font-weight: 900; }
.level-blocks { display: grid; gap: 10px; }
.level-block {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
}
.level-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}
.level-head strong { color: var(--text); font-size: 13px; font-weight: 900; }
.level-head span {
  display: inline-flex;
  align-items: center;
  min-height: 22px;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fff;
  color: var(--text3);
  font-size: 11px;
  font-weight: 900;
}
.indicator-lines { display: grid; }
.indicator-line {
  display: grid;
  grid-template-columns: 82px minmax(0, 1fr);
  gap: 12px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
}
.indicator-line:last-child { border-bottom: 0; }
.indicator-line span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  border-radius: 999px;
  background: var(--teal-lt);
  color: var(--teal);
  font-size: 11px;
  font-weight: 900;
}
.indicator-line p { margin: 0; color: var(--text); font-size: 13px; line-height: 1.6; }
.missing-empty { padding: 14px 16px 16px; color: var(--text3); font-size: 13px; }
.result-table-wrap { overflow-x: auto; padding: 16px 20px 20px; }
.result-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.result-table th {
  background: var(--bg);
  color: var(--text3);
  font-size: 12px;
  font-weight: 900;
  padding: 12px 14px;
  text-align: left;
}
.result-table td {
  border-top: 1px solid var(--border);
  color: var(--text);
  font-size: 13px;
  padding: 14px;
  vertical-align: middle;
}
.result-table th:not(:first-child),
.result-table td:not(:first-child) { text-align: center; }
.gap-pill,
.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 12px;
  font-weight: 900;
}
.gap-pill.negative,
.status-pill.failed { background: #fef2f2; color: #dc2626; }
.gap-pill.positive,
.status-pill.passed { background: #ecfdf5; color: #047857; }
.empty-card {
  display: grid;
  place-items: center;
  gap: 10px;
  min-height: 260px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 32px;
  text-align: center;
}
.empty-title { color: var(--text); font-size: 16px; font-weight: 900; }
.empty-copy { color: var(--text3); font-size: 13px; }
@media (max-width: 900px) {
  .summary-grid { grid-template-columns: 1fr; }
  .page-head,
  .competency-card,
  .failed-card-head { flex-direction: column; align-items: stretch; }
}
@media (max-width: 560px) {
  .section-head { flex-direction: column; }
  .indicator-line { grid-template-columns: 1fr; }
}
</style>
