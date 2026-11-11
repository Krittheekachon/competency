<script setup lang="ts">
import { computed, nextTick, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CompetencySpiderChart from '@/Components/CompetencySpiderChart.vue';

type Review = { reviewStep: number | null; reviewerName: string; reviewerPosition: string; comment: string; decision: string; submittedAt: string | null };
type Row = {
  code: string; name: string; type: string; expected: number | null; actual: number | null; gap: number | null;
  status: string; requiresIdp: boolean; developmentRecommendation: string | null;
  reviewHistory: Review[];
};

const props = defineProps<{
  exportRound: { id: number; label: string; year: number | null };
  exportUser: { name: string; position: string; level: string; workline: string; organization: string; employeeType: string };
  exportRows: Row[];
  exportChain: { self: { name: string; position: string; submittedAt: string | null }; steps: Array<{ step: number; name: string; position: string }> };
  exportedAt: string;
  autoPrint: boolean;
}>();
const page = usePage();

const rows = computed(() => props.exportRows || []);
const isApproved = (row: Row) => ['approved', 'dean_approved'].includes(row.status);
const completedRows = computed(() => rows.value.filter(isApproved));
const spiderItems = computed(() => completedRows.value.map((row) => ({ code: row.code, name: row.name.replace(/\s*\([A-Za-z][^)]*\)\s*$/, ''), type: row.type, actual: row.actual, expected: row.expected })));
const summary = computed(() => {
  const approved = completedRows.value;
  return {
    total: rows.value.length,
    passed: approved.filter((row) => !row.requiresIdp).length,
    needs: approved.filter((row) => row.requiresIdp).length,
    pending: rows.value.length - approved.length,
  };
});
const fmt = (value: number | null) => (value === null || value === undefined ? '-' : Number(value).toLocaleString('th-TH', { maximumFractionDigits: 2 }));
const fmtGap = (value: number | null) => (value === null || value === undefined ? '-' : `${value > 0 ? '+' : ''}${fmt(value)}`);

const resultOf = (row: Row) => {
  if (!isApproved(row)) return { label: 'รอการอนุมัติ', tone: 'pending' };
  return row.requiresIdp ? { label: 'ควรพัฒนา', tone: 'fail' } : { label: 'ผ่านเกณฑ์', tone: 'pass' };
};

const toDate = (value?: string | null) => (value ? new Date(String(value).replace(' ', 'T')) : null);
const dateOf = (value?: string | null) => {
  const date = toDate(value);
  return date && !Number.isNaN(date.getTime()) ? date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';
};
const timeOf = (value?: string | null) => {
  const date = toDate(value);
  return date && !Number.isNaN(date.getTime()) ? `${date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })} น.` : '';
};
const longDate = (value?: string | null) => {
  const date = toDate(value);
  return date && !Number.isNaN(date.getTime()) ? date.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' }) : '-';
};

// Who passed each review step: every competency passes each step on its own, so summarise per step.
const chainRows = computed(() => {
  const submitted = rows.value;
  const total = submitted.length;
  return props.exportChain.steps.map((step) => {
    const reviews = submitted.flatMap((row) => (row.reviewHistory || []).filter((review) => review.reviewStep === step.step));
    const approved = reviews.filter((review) => review.decision === 'approved');
    const rejected = reviews.filter((review) => review.decision === 'rejected');
    const passedCount = submitted.filter((row) => (row.reviewHistory || []).some((review) => review.reviewStep === step.step && review.decision === 'approved')).length;
    const people = [...new Map(approved.map((review) => [review.reviewerName, review])).values()];
    const latest = approved.map((review) => review.submittedAt).filter(Boolean).sort().at(-1) || null;
    const comments = [...new Set(reviews.map((review) => review.comment).filter(Boolean))];
    let state = 'waiting';
    let label = 'รอการประเมิน';
    if (total && passedCount === total) { state = 'passed'; label = 'ผ่านแล้ว'; }
    else if (rejected.length && !approved.length) { state = 'returned'; label = 'ส่งกลับแก้ไข'; }
    else if (passedCount > 0) { state = 'partial'; label = `ผ่านแล้ว ${passedCount}/${total} รายการ`; }
    return {
      step: step.step,
      assigned: step.name,
      position: step.position,
      state, label,
      passedBy: people.map((review) => ({ name: review.reviewerName, position: review.reviewerPosition })),
      at: latest,
      comments,
    };
  });
});
const allPassed = computed(() => chainRows.value.length > 0 && chainRows.value.every((row) => row.state === 'passed'));

const print = () => window.print();
onMounted(async () => {
  document.title = `สรุปผลการประเมินสมรรถนะ - ${props.exportUser.name}`;
  if (props.autoPrint) {
    await nextTick();
    // Let the chart and fonts settle before the print dialog opens.
    setTimeout(() => window.print(), 600);
  }
});
void page;
</script>

<template>
  <div class="export-shell">
    <div class="export-toolbar no-print">
      <div>
        <strong>ตัวอย่างเอกสารสรุปผลการประเมิน</strong>
        <span>กด "พิมพ์ / บันทึกเป็น PDF" แล้วเลือกปลายทางเป็น "บันทึกเป็น PDF"</span>
      </div>
      <div class="export-toolbar-actions">
        <button type="button" class="tb-secondary" @click="() => history.back()">กลับ</button>
        <button type="button" class="tb-primary" @click="print">พิมพ์ / บันทึกเป็น PDF</button>
      </div>
    </div>

    <article class="sheet">
      <header class="doc-head">
        <p class="org">คณะวิศวกรรมศาสตร์ มหาวิทยาลัยขอนแก่น</p>
        <h1>แบบสรุปผลการประเมินสมรรถนะ และแผนพัฒนารายบุคคล (IDP)</h1>
        <p class="round">{{ exportRound.label }}</p>
      </header>

      <div class="top">
        <div class="left">
          <h2>ส่วนที่ 1 ข้อมูลผู้รับการประเมิน</h2>
          <table class="info">
            <tbody>
              <tr><td>ชื่อ - สกุล</td><td><b>{{ exportUser.name }}</b></td></tr>
              <tr><td>ตำแหน่ง</td><td>{{ exportUser.position || '-' }}</td></tr>
              <tr><td>ระดับ</td><td>{{ exportUser.level || '-' }}</td></tr>
              <tr><td>สายงาน</td><td>{{ exportUser.workline || '-' }}</td></tr>
              <tr><td>สังกัด</td><td>{{ exportUser.organization || '-' }}</td></tr>
              <tr v-if="exportUser.employeeType"><td>ประเภทบุคลากร</td><td>{{ exportUser.employeeType }}</td></tr>
            </tbody>
          </table>

          <p v-if="!rows.length" class="empty">ยังไม่มีผลการประเมินในรอบนี้</p>
          <table v-else class="res">
            <thead>
              <tr><th style="width: 6%">ที่</th><th style="text-align: left">สมรรถนะ</th><th style="width: 14%">คาดหวัง</th><th style="width: 8%">ได้</th><th style="width: 8%">Gap</th><th style="width: 24%">ผลประเมิน</th></tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in rows" :key="`${row.code}-${row.name}`">
                <td class="c">{{ index + 1 }}</td>
                <td><span class="code">{{ row.type }}</span> {{ row.name }}</td>
                <td class="c">{{ fmt(row.expected) }}</td>
                <td class="c"><b>{{ fmt(isApproved(row) ? row.actual : null) }}</b></td>
                <td class="c" :class="{ neg: isApproved(row) && (row.gap ?? 0) < 0 }">{{ isApproved(row) ? fmtGap(row.gap) : '-' }}</td>
                <td class="c">
                  <span class="b" :class="`b-${resultOf(row).tone}`">{{ resultOf(row).label }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="right">
          <h2>ส่วนที่ 2 ผลการประเมินสมรรถนะ (กราฟใยแมงมุม)</h2>
          <div class="chartbox">
            <CompetencySpiderChart v-if="spiderItems.length >= 3" :items="spiderItems" compact />
            <p v-else class="empty">ต้องมีสมรรถนะที่อนุมัติแล้วอย่างน้อย 3 รายการจึงจะแสดงกราฟ</p>
            <div class="legend">
              <span><i class="lg-actual"></i>คะแนนจริง</span>
              <span><i class="lg-expected"></i>ระดับคาดหวัง</span>
            </div>
          </div>
        </div>
      </div>

      <div class="steps">
        <h2>ส่วนที่ 3 ลำดับการประเมิน <span class="allpass" :class="{ wait: !allPassed }">{{ allPassed ? 'ผ่านการประเมินครบทุกลำดับ' : 'อยู่ระหว่างการประเมิน' }}</span></h2>
        <table>
          <thead>
            <tr><th style="width: 8%">ลำดับ</th><th style="width: 30%">ผู้ประเมิน</th><th style="width: 20%">ผลการพิจารณา</th><th style="width: 26%">ผ่านโดย</th><th>วันที่</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>0</td>
              <td>{{ exportChain.self.name }}<div class="sub">{{ exportChain.self.position }} · ประเมินตนเอง</div></td>
              <td :class="exportChain.self.submittedAt ? 'sent' : 'wait'">{{ exportChain.self.submittedAt ? 'ส่งผลประเมินแล้ว' : 'ยังไม่ส่ง' }}</td>
              <td>{{ exportChain.self.name }}</td>
              <td>{{ dateOf(exportChain.self.submittedAt) }}<div class="sub">{{ timeOf(exportChain.self.submittedAt) }}</div></td>
            </tr>
            <tr v-for="step in chainRows" :key="step.step">
              <td>{{ step.step }}</td>
              <td>{{ step.assigned }}<div class="sub">{{ step.position }}</div></td>
              <td :class="{ pass: step.state === 'passed', wait: step.state === 'waiting' || step.state === 'partial', neg: step.state === 'returned' }">
                {{ step.label }}
                <div v-for="comment in step.comments" :key="comment" class="sub">“{{ comment }}”</div>
              </td>
              <td>
                <template v-if="step.passedBy.length"><div v-for="person in step.passedBy" :key="person.name">{{ person.name }}<div class="sub">{{ person.position }}</div></div></template>
                <span v-else class="sub">-</span>
              </td>
              <td>{{ dateOf(step.at) }}<div class="sub">{{ timeOf(step.at) }}</div></td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer>ออกเอกสารเมื่อ {{ longDate(exportedAt) }} · ระบบสมรรถนะและแผนพัฒนารายบุคคล (ENKKU)</footer>
    </article>
  </div>
</template>

<style scoped>
.export-shell { min-height: 100vh; padding: 76px 16px 32px; background: #e9ecef; color: #1f2a44; }
.export-toolbar { position: fixed; inset: 0 0 auto 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 24px; background: #1f2933; color: #fff; box-shadow: 0 2px 10px rgb(0 0 0 / .25); }
.export-toolbar strong { display: block; font-size: 15px; }
.export-toolbar span { font-size: 12px; opacity: .75; }
.export-toolbar-actions { display: flex; gap: 8px; }
.export-toolbar button { min-height: 38px; padding: 6px 16px; border-radius: 8px; border: 1px solid rgb(255 255 255 / .4); font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; }
.tb-secondary { background: transparent; color: #fff; }
.tb-primary { background: #c7432b; border-color: #c7432b !important; color: #fff; }
.sheet { width: 210mm; max-width: 100%; height: 297mm; margin: 0 auto; padding: 12mm 12mm 10mm; box-sizing: border-box; display: flex; flex-direction: column; background: #fff; box-shadow: 0 2px 14px rgb(0 0 0 / .15); font-size: 12px; line-height: 1.4; overflow: hidden; }
.doc-head { text-align: center; border-bottom: 3px double #1f3b73; padding-bottom: 8px; margin-bottom: 10px; }
.doc-head h1 { margin: 2px 0; font-size: 18px; font-weight: 800; color: #1f3b73; }
.doc-head .org { margin: 0; color: #1f2a44; font-size: 13px; font-weight: 700; }
.doc-head .round { margin: 0; color: #5b6579; font-size: 11.5px; }
.top { display: flex; gap: 12px; align-items: stretch; }
.left { width: 53%; }
.right { width: 47%; display: flex; flex-direction: column; }
h2 { font-size: 13.5px; margin: 0 0 5px; color: #1f3b73; border-left: 4px solid #e07b1a; padding-left: 6px; }
table { border-collapse: collapse; width: 100%; }
tr { break-inside: avoid; }
.info { border: 1px solid #1f3b73; }
.info td { padding: 6px 8px; border: 1px solid #b9c3d9; vertical-align: top; font-size: 12px; }
.info td:first-child { width: 30%; color: #1f3b73; font-weight: 700; background: #e8eefb; }
.res { margin-top: 14px; }
.res th { background: #1f3b73; color: #fff; font-size: 11px; padding: 6px 3px; font-weight: 700; }
.res td { padding: 9px 4px; border-bottom: 1px solid #e6e9ef; font-size: 11.5px; line-height: 1.5; vertical-align: middle; }
.c { text-align: center; }
.code { display: inline-block; background: #e8eefb; color: #1f3b73; border-radius: 3px; padding: 0 4px; font-size: 9.5px; font-weight: 700; }
.neg { color: #c0392b; font-weight: 700; }
.b { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
.b-pass { background: #e1f4e6; color: #1d7a3a; }
.b-fail { background: #fdeacf; color: #b35a00; }
.b-pending { background: #fff3d6; color: #8a5a07; }
.chartbox { border: 1px solid #e6e9ef; border-radius: 6px; padding: 10px 2px 8px; flex: 1; display: flex; flex-direction: column; justify-content: center; }
.legend { display: flex; gap: 16px; justify-content: center; font-size: 11.5px; margin-top: -2px; }
.legend i { display: inline-block; width: 14px; height: 0; border-top: 2px solid; vertical-align: middle; margin-right: 4px; }
.lg-actual { border-color: #e07b1a; }
.lg-expected { border-color: #2f6fb5; border-top-style: dashed !important; }
.steps { margin-top: 14px; }
.steps th { background: #f0f3f9; color: #1f3b73; padding: 6px 6px; font-size: 11.5px; text-align: left; border-bottom: 2px solid #1f3b73; }
.steps td { padding: 8px 6px; border-bottom: 1px solid #e6e9ef; font-size: 12px; vertical-align: top; }
.sub { color: #5b6579; font-size: 10.5px; }
.pass { color: #1d7a3a; font-weight: 700; }
.sent { color: #2f6fb5; font-weight: 700; }
.wait { color: #b35a00; font-weight: 700; }
.allpass { float: right; font-weight: 400; font-size: 12px; color: #1d7a3a; }
.allpass.wait { color: #b35a00; }
.empty { color: #5b6579; margin: 8px 0; }
footer { margin-top: 8px; font-size: 9px; color: #8a91a0; border-top: 1px solid #e6e9ef; padding-top: 5px; }
@media screen and (max-width: 800px) { .export-toolbar span { display: none; } .sheet { height: auto; padding: 12px; } .top { flex-direction: column; } .left, .right { width: 100%; } }
@media print {
  @page { size: A4; margin: 0; }
  :global(html), :global(body) { background: #fff !important; }
  .no-print { display: none !important; }
  .export-shell { min-height: 0; padding: 0; background: #fff; }
  .sheet { width: 210mm; height: 296mm; margin: 0; box-shadow: none; }
  .res th, .code, .b, .info td:first-child, .steps th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
