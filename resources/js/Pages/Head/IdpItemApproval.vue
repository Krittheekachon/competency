<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const comments = ref({});
const processingId = ref(null);

const approve = (item) => {
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
                <h2>แผนสมรรถนะที่รออนุมัติ</h2>
                <p>ตรวจและดำเนินการแยกทีละสมรรถนะ</p>
            </div>
            <span>{{ items.length }} รายการ</span>
        </header>

        <div v-if="items.length === 0" class="approval-empty">ยังไม่มีแผนสมรรถนะที่รออนุมัติ</div>

        <article v-for="item in items" :key="item.id" class="approval-card">
            <div class="approval-title">
                <div>
                    <span>{{ item.userSso }}</span>
                    <strong>{{ item.userName }}</strong>
                    <small>{{ item.userPosition || item.userDepartment }}</small>
                </div>
                <div class="competency">
                    <span class="review-step">รออนุมัติลำดับ {{ item.currentReviewStep }}</span>
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

            <footer>
                <button class="reject" type="button" :disabled="processingId === item.id" @click="reject(item)">ตีกลับให้แก้ไข</button>
                <button class="approve" type="button" :disabled="processingId === item.id" @click="approve(item)">อนุมัติสมรรถนะนี้</button>
            </footer>
        </article>
    </section>
</template>

<style scoped>
.approval-panel { margin-bottom: 20px; border: 1px solid #b8d7cb; border-radius: 8px; background: #f3faf7; overflow: hidden; }
.approval-panel > header { display: flex; justify-content: space-between; gap: 16px; padding: 16px 18px; border-bottom: 1px solid #cce2da; background: #e8f5f0; }
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
