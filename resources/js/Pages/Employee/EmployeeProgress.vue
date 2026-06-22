<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

type ApprovedActivity = {
  id: number;
  competencyCode: string;
  competencyName: string;
  name: string;
  startDate: string;
  endDate: string;
  latestProgressNote: string;
  latestPercentComplete: number;
  latestEvidenceUrl: string;
  latestEvidenceDescription: string;
};

const props = defineProps<{ activities?: ApprovedActivity[] }>();
const forms = reactive<Record<number, {
  progressNote: string;
  percentComplete: number;
  evidenceUrl: string;
  evidenceDescription: string;
}>>({});
const processingId = ref<number | null>(null);

watch(() => props.activities, (activities) => {
  for (const activity of activities || []) {
    forms[activity.id] = {
      progressNote: activity.latestProgressNote || '',
      percentComplete: activity.latestPercentComplete || 0,
      evidenceUrl: activity.latestEvidenceUrl || '',
      evidenceDescription: activity.latestEvidenceDescription || '',
    };
  }
}, { immediate: true, deep: true });

const saveProgress = (activityId: number) => {
  processingId.value = activityId;
  router.post(route('employee.idp-activities.update-progress'), {
    activityId,
    ...forms[activityId],
  }, {
    preserveScroll: true,
    onFinish: () => { processingId.value = null; },
  });
};
</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>อัปเดตความก้าวหน้า IDP</h1>
        <p>บันทึกผลการดำเนินงานของกิจกรรมที่ผ่านการอนุมัติครบทุกลำดับแล้ว</p>
      </div>
    </div>

    <div v-if="!activities?.length" class="empty-card">
      <div class="empty-title">ยังไม่มีกิจกรรมที่ต้องอัปเดต</div>
      <div class="empty-copy">กิจกรรมจะแสดงเมื่อแผนสมรรถนะผ่านการอนุมัติครบทุกลำดับ</div>
    </div>

    <div v-else class="progress-list">
      <article v-for="activity in activities" :key="activity.id" class="progress-item">
        <header>
          <span>{{ activity.competencyCode }}</span>
          <strong>{{ activity.competencyName }}</strong>
          <small>{{ activity.name }} · {{ activity.startDate }} - {{ activity.endDate }}</small>
        </header>
        <div class="progress-grid">
          <label class="wide">
            <span>บันทึกความก้าวหน้า</span>
            <textarea v-model="forms[activity.id].progressNote" rows="3" />
          </label>
          <label>
            <span>ความสำเร็จ (%)</span>
            <input v-model.number="forms[activity.id].percentComplete" type="number" min="0" max="100" />
          </label>
          <label>
            <span>ลิงก์หลักฐาน</span>
            <input v-model="forms[activity.id].evidenceUrl" type="url" />
          </label>
          <label class="wide">
            <span>คำอธิบายหลักฐาน</span>
            <textarea v-model="forms[activity.id].evidenceDescription" rows="2" />
          </label>
        </div>
        <footer>
          <button
            type="button"
            :disabled="processingId === activity.id"
            @click="saveProgress(activity.id)"
          >
            บันทึกความก้าวหน้า
          </button>
        </footer>
      </article>
    </div>
  </section>
</template>

<style scoped>
.employee-page { display: grid; gap: 16px; }
.page-head h1 { margin: 0; color: var(--text); font-size: 22px; font-weight: 900; }
.page-head p { margin: 8px 0 0; color: var(--text3); font-size: 13px; }
.empty-card { display: grid; place-items: center; min-height: 260px; border: 1px dashed var(--border); border-radius: 8px; background: #fff; padding: 32px; text-align: center; }
.empty-title { color: var(--text); font-size: 16px; font-weight: 900; }
.empty-copy { color: var(--text3); font-size: 13px; margin-top: 8px; }
.progress-list { display: grid; gap: 12px; }
.progress-item { overflow: hidden; border: 1px solid #d6dee7; border-radius: 8px; background: #fff; }
.progress-item header { display: grid; gap: 3px; padding: 13px 15px; border-bottom: 1px solid #e2e7ec; background: #f5faf8; }
.progress-item header span { color: #247260; font-size: 11px; font-weight: 900; }
.progress-item header strong { font-size: 14px; }
.progress-item header small { color: #718096; font-size: 11px; }
.progress-grid { display: grid; grid-template-columns: 160px 1fr; gap: 11px; padding: 14px 15px; }
.progress-grid label { display: grid; gap: 6px; color: #59677a; font-size: 11px; font-weight: 800; }
.progress-grid .wide { grid-column: 1 / -1; }
input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d5dde7; border-radius: 6px; padding: 9px 10px; color: #172033; font: inherit; font-size: 12px; }
textarea { resize: vertical; }
footer { display: flex; justify-content: flex-end; padding: 11px 15px; border-top: 1px solid #e2e7ec; background: #f8fafb; }
button { border: 1px solid #247260; border-radius: 6px; background: #247260; padding: 9px 13px; color: #fff; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
button:disabled { opacity: .55; cursor: wait; }
@media (max-width: 700px) {
  .progress-grid { grid-template-columns: 1fr; }
  .progress-grid .wide { grid-column: 1; }
}
</style>
