<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tracking: { type: Boolean, default: false },
    items: {
        type: Array,
        default: () => [],
    },
});

const comments = ref({});
const processingId = ref(null);
const selectedId = ref(null);
const visibleItems = computed(() => props.items.filter(item => props.tracking ? !item.canReview : item.canReview));
const statusLabel = (item) => item.canReview ? `รอคุณอนุมัติ · ลำดับ ${item.currentReviewStep}`
    : item.status === 'approved' ? 'อนุมัติครบแล้ว'
    : item.status === 'revision_required' ? 'ถูกส่งกลับแก้ไข'
    : /^review_step_/.test(item.status) ? `รอผู้อนุมัติลำดับ ${item.currentReviewStep}` : 'ร่าง';

const approve = (item) => {
    if (!item.canReview || props.tracking) return;
    processingId.value = item.id;
    router.post(route('idp-items.approve'), {
        idpItemId: item.id,
        comment: String(comments.value[item.id] || '').trim() || null,
    }, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
};

const reject = (item) => {
    if (!item.canReview || props.tracking) return;
    const comment = String(comments.value[item.id] || '').trim();
    if (!comment) {
        window.alert('กรุณาระบุเหตุผลที่ตีกลับ');
        return;
    }

    processingId.value = item.id;
    router.post(route('idp-items.reject'), {
        idpItemId: item.id,
        comment,
    }, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
};
</script>

<template>
    <section class="approval-panel">
        <header>
            <div>
                <h2>{{ tracking ? 'ติดตามสถานะ' : 'รอคุณอนุมัติ' }}</h2>
                <p>{{ tracking ? 'แผนที่ไม่ได้รอคุณอนุมัติขณะนี้ · เปิดดูรายละเอียดและประวัติแบบอ่านอย่างเดียว' : 'แผนที่มาถึงลำดับของคุณ · เลือกรายการเพื่อตรวจและอนุมัติทีละสมรรถนะ' }}</p>
            </div>
            <span>{{ visibleItems.length }} รายการ</span>
        </header>

        <div v-if="visibleItems.length === 0" class="approval-empty">{{ tracking ? 'ไม่มีแผนสมรรถนะที่ต้องติดตาม' : 'ยังไม่มีแผนสมรรถนะที่รอคุณอนุมัติ' }}</div>

        <div v-if="visibleItems.length" class="queue-table-wrap">
            <table class="queue-table">
                <thead><tr><th>ชื่อ-นามสกุล</th><th>สมรรถนะ</th><th>วันที่ส่งแผน</th><th>สถานะ</th></tr></thead>
                <tbody>
                    <tr v-for="item in visibleItems" :key="item.id" :class="{ selected: selectedId === item.id }">
                        <td><button class="person-link" type="button" @click="selectedId = selectedId === item.id ? null : item.id" :aria-expanded="selectedId === item.id">{{ item.userName }}</button><small>{{ item.userPosition || item.userDepartment }}</small></td>
                        <td><strong>{{ item.competencyCode }}</strong><small>{{ item.competencyName }}</small></td>
                        <td>{{ item.submittedAt || '—' }}</td>
                        <td><span class="queue-status">{{ statusLabel(item) }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <article v-for="item in visibleItems.filter((entry) => entry.id === selectedId)" :key="item.id" class="approval-card">
            <div class="detail-heading"><strong>รายละเอียดแผนสมรรถนะ</strong><button type="button" @click="selectedId = null">ปิดรายละเอียด</button></div>
            <div class="approval-title">
                <div>
                    <span>{{ item.userSso }}</span>
                    <strong>{{ item.userName }}</strong>
                    <small>{{ item.userPosition || item.userDepartment }}</small>
                </div>
                <div class="competency">
                    <span class="review-step">{{ statusLabel(item) }}</span>
                    <span>{{ item.competencyCode }}</span>
                    <strong>{{ item.competencyName }}</strong>
                </div>
            </div>

            <div class="plan-copy">
                <div><span>เป้าหมาย</span><p>{{ item.goal }}</p></div>
                <div><span>ตัวชี้วัดความสำเร็จ</span><p>{{ item.successCriteria }}</p></div>
            </div>

            <div class="activity-list">
                <div v-for="(activity, index) in item.activities" :key="activity.id" class="activity-row">
                    <b>{{ index + 1 }}</b>
                    <div>
                        <strong>{{ activity.name }}</strong>
                        <span>{{ activity.methodLabel }} · {{ activity.weightPercent }}%</span>
                        <small v-if="activity.documentReferenceNumber">เอกสาร {{ activity.documentReferenceNumber }}</small>
                    </div>
                    <span>{{ activity.startDate }} - {{ activity.endDate }}</span>
                </div>
            </div>

            <textarea
                v-if="!tracking && item.canReview"
                v-model="comments[item.id]"
                rows="2"
                placeholder="ความคิดเห็นเพิ่มเติม (ไม่บังคับสำหรับอนุมัติ)"
            />

            <details v-if="item.reviewHistory?.length" class="review-history">
                <summary>ประวัติการพิจารณา {{ item.reviewHistory.length }} รายการ</summary>
                <div
                    v-for="review in item.reviewHistory"
                    :key="`${review.submissionVersion}-${review.reviewStep}`"
                    class="history-row"
                >
                    <strong>
                        ครั้งที่ {{ review.submissionVersion }} · ลำดับ {{ review.reviewStep }}
                        · {{ review.decision === 'approved' ? 'อนุมัติ' : 'ตีกลับ' }}
                    </strong>
                    <span>{{ review.reviewerName }} · {{ review.decidedAt }}</span>
                    <p v-if="review.comment">{{ review.comment }}</p>
                </div>
            </details>

            <footer v-if="!tracking && item.canReview">
                <button class="reject" type="button" :disabled="processingId === item.id" @click="reject(item)">ไม่อนุมัติ</button>
                <button class="approve" type="button" :disabled="processingId === item.id" @click="approve(item)">อนุมัติ</button>
            </footer>
        </article>
    </section>
</template>

<style scoped>
.approval-panel { margin-bottom: 20px; border: 1px solid #dce3ea; border-radius: 8px; background: #fff; overflow: hidden; box-shadow: 0 2px 5px #17203308; }
.approval-panel > header { display: flex; justify-content: space-between; gap: 16px; padding: 22px; border-bottom: 1px solid #e2e7ec; background: #fff; }
.queue-table-wrap { overflow-x: auto; padding: 16px; }
.queue-table { width: 100%; border: 1px solid #dce3ea; border-radius: 8px; border-spacing: 0; overflow: hidden; text-align: left; }
.queue-table th { padding: 12px 16px; background: #f1f4f7; color: #657287; font-size: 12px; }
.queue-table td { padding: 16px; border-top: 1px solid #e4e9ee; font-size: 12px; }
.queue-table td:first-child { border-left: 3px solid #bed2c9; }
.queue-table small { display: block; margin-top: 5px; color: #718096; font-size: 11px; }
.queue-table tr.selected td, .queue-table tbody tr:hover td { background: #f0f7f4; }
.person-link { border: 0; padding: 0; background: none; color: #263d35; text-align: left; }
.queue-status { display: inline-block; padding: 5px 9px; border-radius: 20px; background: #fff8e8; color: #a7650c; font-weight: 800; white-space: nowrap; }
.detail-heading { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: #edf6f2; color: #246b59; font-size: 13px; }
.detail-heading button { border: 1px solid #c6d8cf; background: #fff; color: #246b59; }
@media (max-width: 900px) { .queue-table { min-width: 680px; } }
h2, p { margin: 0; }
h2 { font-size: 16px; }
header p { margin-top: 4px; color: #62756f; font-size: 12px; }
header > span { align-self: center; padding: 5px 9px; border-radius: 5px; background: #fff; color: #216b59; font-size: 12px; font-weight: 900; }
.approval-empty { padding: 22px; color: #718096; text-align: center; font-size: 13px; }
.approval-card { margin: 12px; border: 1px solid #d4dde5; border-radius: 7px; background: #fff; overflow: hidden; }
.approval-title { display: flex; justify-content: space-between; gap: 18px; padding: 13px 15px; border-bottom: 1px solid #e2e7ec; }
.approval-title span, .approval-title strong, .approval-title small { display: block; }
.approval-title span { color: #68768a; font-size: 11px; }
.approval-title strong { margin-top: 3px; font-size: 13px; }
.approval-title small { margin-top: 3px; color: #7a8798; font-size: 11px; }
.competency { max-width: 55%; text-align: right; }
.competency span { color: #247260; font-weight: 900; }
.competency .review-step { margin-bottom: 4px; color: #8a5a08; }
.plan-copy { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 13px 15px; }
.plan-copy > div { padding: 10px; border: 1px solid #d9e3ec; border-radius: 6px; background: #f8fafc; }
.plan-copy span { color: #64748b; font-size: 11px; font-weight: 800; }
.plan-copy p { margin-top: 5px; font-size: 12px; line-height: 1.55; }
.activity-list { display: grid; gap: 7px; padding: 0 15px 13px; }
.activity-row { display: grid; grid-template-columns: 25px 1fr auto; align-items: center; gap: 9px; padding: 9px; border: 1px solid #dce4eb; border-radius: 6px; }
.activity-row > b { display: grid; place-items: center; width: 25px; height: 25px; border-radius: 5px; background: #dceee8; color: #246b59; }
.activity-row strong, .activity-row span, .activity-row small { display: block; }
.activity-row strong { font-size: 12px; }
.activity-row span, .activity-row small { margin-top: 2px; color: #718096; font-size: 10px; }
textarea { width: calc(100% - 30px); margin: 0 15px 13px; border: 1px solid #d4dde6; border-radius: 6px; padding: 9px; box-sizing: border-box; font: inherit; font-size: 12px; resize: vertical; }
.review-history { margin: 0 15px 13px; border: 1px solid #d9e3ec; border-radius: 6px; background: #f8fafc; padding: 9px 11px; }
.review-history summary { color: #344054; font-size: 11px; font-weight: 900; cursor: pointer; }
.history-row { display: grid; gap: 3px; padding: 9px 0; border-top: 1px solid #e2e7ec; }
.history-row:first-of-type { margin-top: 8px; }
.history-row strong { font-size: 11px; }
.history-row span, .history-row p { margin: 0; color: #718096; font-size: 10px; }
footer { display: flex; justify-content: flex-end; gap: 8px; padding: 11px 15px; border-top: 1px solid #e2e7ec; background: #f8fafb; }
button { border-radius: 6px; padding: 8px 12px; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
button:disabled { opacity: .55; cursor: wait; }
.reject { border: 1px solid #efb8b8; background: #fff; color: #b42318; }
.approve { border: 1px solid #247260; background: #247260; color: #fff; }
@media (max-width: 900px) {
    .approval-title, .plan-copy { grid-template-columns: 1fr; flex-direction: column; }
    .competency { max-width: none; text-align: left; }
    .activity-row { grid-template-columns: 25px 1fr; }
    .activity-row > span { grid-column: 2; }
}
</style>
