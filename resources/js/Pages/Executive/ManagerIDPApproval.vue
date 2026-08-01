<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
  users?: any[];
  currentUserId?: number;
}>();

const selectedUser = ref<any | null>(null);
const approvedIds = ref<number[]>([]);

const reviewerStepsForUser = (user: any) => {
  const steps = Array.isArray(user?.reviewerSteps) && user.reviewerSteps.length
    ? user.reviewerSteps
    : (Array.isArray(user?.supervisorChain) ? user.supervisorChain : []);

  if (steps.length) {
    return steps
      .map((step: any, index: number) => ({
        step: Number(step.step || index + 1),
        reviewer_id: Number(step.id || step.reviewer_id || 0),
      }))
      .filter((step: any) => step.step > 0 && step.reviewer_id > 0);
  }

  return [user?.supervisor_id_1, user?.supervisor_id_2, user?.supervisor_id_3]
    .map((id, index) => ({ step: index + 1, reviewer_id: Number(id || 0) }))
    .filter((step) => step.reviewer_id > 0);
};

const evaluatorLevel = (user: any) => {
  const currentUserId = Number(props.currentUserId || 0);
  if (!currentUserId) return 0;
  return reviewerStepsForUser(user)
    .find((step) => step.reviewer_id === currentUserId)
    ?.step || 0;
};

const userById = (id: any) =>
  (props.users || []).find((user) => Number(user?.db_id) === Number(id));

const displayName = (user: any) =>
  user ? `${user?.t || ''}${user?.n || ''}`.trim() : '';

const evaluatorName = (user: any, level: 1 | 2 | 3) => {
  const id = reviewerStepsForUser(user).find((step) => step.step === level)?.reviewer_id;

  return displayName(userById(id));
};

const plansFor = (user: any) => {
  const plans = user?.idpDetails || user?.idpPlans || user?.developmentPlans;
  if (Array.isArray(plans) && plans.length) return plans;

  const gaps = Array.isArray(user?.competencyGaps)
    ? user.competencyGaps.filter((gap: any) => Number(gap?.gap) < 0)
    : [];

  return gaps.map((gap: any) => ({
    topic: gap.n || gap.name || 'ไม่ระบุสมรรถนะ',
    method: 'รอจัดทำแผนพัฒนา',
    due: '-',
    outcome: gap.note || 'พัฒนาสมรรถนะให้ครบตามระดับที่คาดหวัง',
  }));
};

const rows = computed(() =>
  (props.users || [])
    .filter((user) => user?.act !== false && evaluatorLevel(user) > 0)
    .sort((left, right) =>
      String(left?.n || '').localeCompare(String(right?.n || ''), 'th'))
);

const isApproved = (user: any) => approvedIds.value.includes(Number(user.db_id));
const approve = (user: any) => {
  const id = Number(user?.db_id);
  if (!id || isApproved(user)) return;
  approvedIds.value = [...approvedIds.value, id];
  selectedUser.value = null;
};
</script>

<template>
  <section>
    <div class="mb20">
      <div class="sec-t">อนุมัติแผน IDP รายบุคคล</div>
      <div class="sec-s">
        แสดงเฉพาะบุคลากรที่เลือกคุณเป็นผู้ประเมินในหน้าเพิ่มผู้ใช้
      </div>
    </div>

    <div class="card">
      <div class="ch">
        <div class="ct">รายการแผน IDP</div>
      </div>
      <div class="cb approval-table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>บุคลากร</th>
              <th>หัวหน้าหน่วย</th>
              <th>หัวหน้างาน</th>
              <th>ลำดับของคุณ</th>
              <th>หัวข้อ IDP</th>
              <th>สถานะ</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in rows" :key="user.db_id || user.sso">
              <td>
                <div class="fw7 fs13">{{ `${user.t || ''}${user.n || '-'}` }}</div>
                <div class="muted fs11">{{ user.p || 'ยังไม่ระบุตำแหน่ง' }} · {{ user.d || 'ยังไม่ระบุหน่วยงาน' }}</div>
              </td>
              <td><div class="fw6 fs12">{{ evaluatorName(user, 1) || '—' }}</div></td>
              <td><div class="fw6 fs12">{{ evaluatorName(user, 2) || '—' }}</div></td>
              <td><span class="b bt">ผู้ประเมินลำดับที่ {{ evaluatorLevel(user) }}</span></td>
              <td>
                <div class="flex ic g4 idp-topic-list">
                  <span v-for="plan in plansFor(user)" :key="plan.topic" class="b bt">{{ plan.topic }}</span>
                  <span v-if="plansFor(user).length === 0" class="muted fs11">ยังไม่มีแผน IDP</span>
                </div>
              </td>
              <td><span class="b" :class="isApproved(user) ? 'bg' : 'by'">{{ isApproved(user) ? 'ยืนยันแล้ว' : 'รอยืนยัน' }}</span></td>
              <td>
                <div class="flex ic g6">
                  <button class="btn btn-s btn-xs" type="button" @click="selectedUser = user">ดูรายละเอียด</button>
                  <button
                    class="btn btn-t btn-xs"
                    type="button"
                    :disabled="isApproved(user) || plansFor(user).length === 0"
                    @click="approve(user)"
                  >
                    ยืนยัน
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="7" class="approval-empty">
                ยังไม่มีบุคลากรที่เลือกคุณเป็นผู้ประเมิน
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="selectedUser" class="mo" style="z-index: 300" @mousedown.self="selectedUser = null">
      <div class="mo-box approval-modal">
        <div class="mo-h">
          <div>
            <div class="fw8 fs14">{{ `${selectedUser.t || ''}${selectedUser.n || '-'}` }}</div>
            <div class="muted fs11">ผู้ประเมินลำดับที่ {{ evaluatorLevel(selectedUser) }}</div>
          </div>
          <button class="btn btn-s btn-sm" type="button" @click="selectedUser = null">ปิด</button>
        </div>
        <div class="mo-b">
          <div v-for="plan in plansFor(selectedUser)" :key="plan.topic" class="idp-detail-row">
            <span class="b bt">{{ plan.topic }}</span>
            <span class="fw6 fs12">{{ plan.method || 'ยังไม่ระบุวิธีพัฒนา' }}</span>
            <span class="b bgr">{{ plan.due || '-' }}</span>
            <span class="muted fs12">{{ plan.outcome || 'ยังไม่ระบุผลลัพธ์ที่คาดหวัง' }}</span>
          </div>
          <div v-if="plansFor(selectedUser).length === 0" class="approval-empty">
            บุคลากรรายนี้ยังไม่มีแผน IDP
          </div>
          <button
            v-if="plansFor(selectedUser).length"
            class="btn btn-t btn-sm approval-confirm"
            type="button"
            :disabled="isApproved(selectedUser)"
            @click="approve(selectedUser)"
          >
            {{ isApproved(selectedUser) ? 'ยืนยันแผน IDP แล้ว' : 'ยืนยันแผน IDP' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.approval-table-wrap {
  padding: 0;
  overflow-x: auto;
}

.approval-empty {
  padding: 28px 16px;
  color: var(--text3);
  text-align: center;
}

.approval-modal {
  width: min(760px, calc(100vw - 32px));
}

.idp-topic-list {
  flex-wrap: wrap;
}

.idp-detail-row {
  display: grid;
  grid-template-columns: 150px 150px 90px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}

.approval-confirm {
  width: 100%;
  justify-content: center;
  margin-top: 14px;
}

@media (max-width: 680px) {
  .idp-detail-row {
    grid-template-columns: 1fr;
  }
}
</style>
