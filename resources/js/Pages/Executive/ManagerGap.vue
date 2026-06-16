<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
  users?: any[];
  canSendReminders?: boolean;
}>();

const selectedUnit = ref<any | null>(null);
const isPendingListOpen = ref(false);
const openedCommonGapKey = ref<string | null>(null);
const openedDetailGroupName = ref<string | null>(null);

const activeUsers = computed(() => (props.users || []).filter((user) =>
  user?.act !== false && !['admin', 'dean'].includes(user?.r)
));

const isAssessed = (user: any) =>
  Boolean(user?.evalStatus) && user.evalStatus !== 'draft';

const negativeGapCount = (user: any) => {
  if (Array.isArray(user?.competencyGaps)) {
    return user.competencyGaps.filter((gap: any) => Number(gap?.gap) < 0).length;
  }

  return Array.isArray(user?.gaps) ? user.gaps.length : 0;
};

const severityClass = (count: number) => {
  if (count === 0) return 'complete';
  if (count <= 2) return 'moderate';
  return 'high';
};

const summary = computed(() => {
  const assessedUsers = activeUsers.value.filter(isAssessed);

  return {
    total: activeUsers.value.length,
    assessed: assessedUsers.length,
    complete: assessedUsers.filter((user) => negativeGapCount(user) === 0).length,
    moderate: assessedUsers.filter((user) => {
      const count = negativeGapCount(user);
      return count >= 1 && count <= 2;
    }).length,
    high: assessedUsers.filter((user) => negativeGapCount(user) >= 3).length,
  };
});

const commonFacultyGaps = computed(() => {
  const gapCounts = new Map<string, { code: string; name: string; count: number; users: any[] }>();

  activeUsers.value.filter(isAssessed).forEach((user) => {
    const seen = new Set<string>();
    const rows = Array.isArray(user?.competencyGaps) ? user.competencyGaps : [];

    rows.forEach((gap: any) => {
      if (Number(gap?.gap) >= 0) return;
      const code = String(gap?.cd || gap?.code || '').trim();
      const name = String(gap?.n || gap?.name || gap?.title || 'ไม่ระบุสมรรถนะ').trim();
      const key = code || name;
      if (!key || seen.has(key)) return;

      seen.add(key);
      const current = gapCounts.get(key) || { code, name, count: 0, users: [] };
      current.count += 1;
      current.users.push(user);
      gapCounts.set(key, current);
    });
  });

  return Array.from(gapCounts.values())
    .sort((left, right) => right.count - left.count || left.name.localeCompare(right.name, 'th'))
    .slice(0, 5);
});

const maxCommonGapCount = computed(() =>
  Math.max(...commonFacultyGaps.value.map((item) => item.count), 1)
);

const pendingUsers = computed(() =>
  activeUsers.value
    .filter((user) => !isAssessed(user))
    .sort((left, right) => String(left?.n || '').localeCompare(String(right?.n || ''), 'th'))
);

const jobFamilyName = (user: any) => {
  const parts = String(user?.d || '').split(' > ').map((part) => part.trim()).filter(Boolean);
  return parts[0] || 'ยังไม่ระบุกลุ่มงาน';
};

const worklineName = (user: any) =>
  String(user?.w || '').trim() || 'ยังไม่ระบุสายงาน';

const userById = (id: any) =>
  activeUsers.value.find((user) => Number(user?.db_id) === Number(id));

const displayName = (user: any) =>
  user ? `${user?.t || ''}${user?.n || ''}`.trim() : '';

const evaluatorName = (user: any, level: 1 | 2 | 3) => {
  const id = level === 1
    ? user?.supervisor_id_1
    : level === 2
      ? user?.supervisor_id_2
      : user?.supervisor_id_3;

  return displayName(userById(id));
};

const supervisorName = (users: any[]) => {
  const name = users
    .map((user) => evaluatorName(user, 2) || evaluatorName(user, 3) || evaluatorName(user, 1))
    .find(Boolean);

  return name || 'ยังไม่ระบุผู้บังคับบัญชา';
};

const buildUnitRows = (getName: (user: any) => string) => {
  const grouped = new Map<string, any[]>();

  activeUsers.value.forEach((user) => {
    const name = getName(user);
    grouped.set(name, [...(grouped.get(name) || []), user]);
  });

  return Array.from(grouped.entries())
    .map(([name, users], index) => {
      const assessedUsers = users.filter(isAssessed);
      const completeUsers = assessedUsers.filter((user) => negativeGapCount(user) === 0).length;

      return {
        id: `สายงาน-${index}-${name}`,
        type: 'สายงาน',
        name,
        supervisor: supervisorName(users),
        users,
        assessed: assessedUsers.length,
        total: users.length,
        complete: completeUsers,
      };
    })
    .sort((left, right) => left.name.localeCompare(right.name, 'th'));
};

const worklines = computed(() => buildUnitRows(worklineName));

const completionRate = computed(() =>
  summary.value.total ? Math.round((summary.value.assessed / summary.value.total) * 100) : 0
);
const pendingCount = computed(() => Math.max(summary.value.total - summary.value.assessed, 0));
const visibleUnits = computed(() => worklines.value);

const completionPercent = (unit: any) =>
  unit.total ? Math.round((unit.assessed / unit.total) * 100) : 0;
const pendingFor = (unit: any) => Math.max(unit.total - unit.assessed, 0);

const commonGapKey = (item: any) => item.code || item.name;
const toggleCommonGap = (item: any) => {
  const key = commonGapKey(item);
  openedCommonGapKey.value = openedCommonGapKey.value === key ? null : key;
};

const assessmentTotals = (user: any) => {
  const rows = Array.isArray(user?.competencyGaps) ? user.competencyGaps : [];
  const expected = rows.reduce((total: number, row: any) =>
    total + Number(row?.expectedIndicatorCount ?? 0), 0);
  const actual = rows.reduce((total: number, row: any) =>
    total + Number(row?.checkedIndicatorCount ?? row?.actual ?? 0), 0);

  return {
    expected,
    actual,
    gap: actual - expected,
  };
};

const detailGroups = computed(() => {
  if (!selectedUnit.value) return [];

  const grouped = new Map<string, any[]>();
  selectedUnit.value.users.forEach((user: any) => {
    const name = jobFamilyName(user);
    grouped.set(name, [...(grouped.get(name) || []), user]);
  });

  return Array.from(grouped.entries())
    .map(([name, users]) => {
      const assessedUsers = users.filter(isAssessed);
      const completeUsers = assessedUsers.filter((user) => negativeGapCount(user) === 0).length;

      return {
        name,
        supervisor: users.map((user) => evaluatorName(user, 1)).find(Boolean)
          || 'ยังไม่ระบุหัวหน้างาน',
        users: [...users].sort((left, right) =>
          String(left?.n || '').localeCompare(String(right?.n || ''), 'th')),
        complete: completeUsers,
        assessed: assessedUsers.length,
      };
    })
    .sort((left, right) => left.name.localeCompare(right.name, 'th'));
});

const openUnitDetail = (unit: any) => {
  selectedUnit.value = unit;
  openedDetailGroupName.value = null;
};

const closeUnitDetail = () => {
  selectedUnit.value = null;
  openedDetailGroupName.value = null;
};

const toggleDetailGroup = (name: string) => {
  openedDetailGroupName.value = openedDetailGroupName.value === name ? null : name;
};
</script>

<template>
  <section class="faculty-gap-page">
    <header class="faculty-page-head">
      <div>
        <h1>ภาพรวมผลการประเมินคณะ</h1>
        <p>มุมมองสำหรับคณบดีและ HR คณะวิศวกรรมศาสตร์ · รอบประเมิน 2568</p>
      </div>
    </header>

    <section class="faculty-summary-panel" aria-label="สรุปผลการประเมินคณะ">
      <div class="faculty-summary-metrics">
        <div class="faculty-summary-item">
          <span>บุคลากรทั้งหมด</span>
          <strong>{{ summary.total }}</strong>
          <small>รวมทุกสายงาน</small>
        </div>
        <div class="faculty-summary-item">
          <span>ประเมินแล้ว</span>
          <strong>{{ summary.assessed }}</strong>
          <small>จากทั้งหมด</small>
        </div>
        <div class="faculty-summary-item complete">
          <span>ครบตามเกณฑ์</span>
          <strong>{{ summary.complete }}</strong>
          <small>คน</small>
        </div>
        <div class="faculty-summary-item moderate">
          <span>ขาด 1-2 สมรรถนะ</span>
          <strong>{{ summary.moderate }}</strong>
          <small>คน</small>
        </div>
        <div class="faculty-summary-item high">
          <span>ขาด 3+ สมรรถนะ</span>
          <strong>{{ summary.high }}</strong>
          <small>คน</small>
        </div>
      </div>

      <div
        class="faculty-completion-ring"
        :style="{ '--completion': `${completionRate * 3.6}deg` }"
        :aria-label="`ประเมินแล้ว ${completionRate} เปอร์เซ็นต์`"
      >
        <div>
          <strong>{{ completionRate }}%</strong>
          <span>ประเมินแล้ว</span>
        </div>
      </div>
    </section>

    <section
      v-if="props.canSendReminders"
      class="faculty-reminder"
      :class="{ open: isPendingListOpen }"
    >
      <div class="faculty-reminder-top">
        <button
          class="faculty-reminder-toggle"
          type="button"
          :aria-expanded="isPendingListOpen"
          @click="isPendingListOpen = !isPendingListOpen"
        >
          <span class="faculty-reminder-mark" aria-hidden="true">!</span>
          <span class="faculty-reminder-copy">
            <strong>แจ้งเตือนผู้ที่ยังไม่ประเมินตนเอง</strong>
            <span>พบ {{ pendingCount }} คนที่ยังไม่ส่งแบบประเมินตนเอง กดเพื่อดูรายชื่อ</span>
          </span>
          <span class="faculty-accordion-icon" :class="{ open: isPendingListOpen }" aria-hidden="true">›</span>
        </button>
        <button class="faculty-reminder-button" type="button">ส่งแจ้งเตือนทั้งหมด</button>
      </div>

      <div v-if="isPendingListOpen" class="faculty-pending-list">
        <div v-for="user in pendingUsers" :key="user.db_id || user.sso" class="faculty-pending-row">
          <div>
            <strong>{{ `${user.t || ''}${user.n || '-'}` }}</strong>
            <span>{{ user.p || 'ยังไม่ระบุตำแหน่ง' }}</span>
          </div>
          <span>{{ jobFamilyName(user) }}</span>
          <span class="faculty-pending-status">ยังไม่ประเมิน</span>
        </div>
        <div v-if="pendingUsers.length === 0" class="faculty-pending-empty">
          บุคลากรทุกคนส่งแบบประเมินแล้ว
        </div>
      </div>
    </section>

    <section class="faculty-common-gap-section">
      <header class="faculty-common-gap-head">
        <div>
          <h2>สมรรถนะที่เป็น Gap ร่วมของคณะ</h2>
          <p>5 อันดับสมรรถนะที่มีบุคลากรประเมินได้ต่ำกว่าความคาดหวังมากที่สุด</p>
        </div>
      </header>

      <div v-if="commonFacultyGaps.length" class="faculty-common-gap-list">
        <template v-for="(item, index) in commonFacultyGaps" :key="item.code || item.name">
          <button
            class="faculty-common-gap-row"
            type="button"
            :class="{ open: openedCommonGapKey === commonGapKey(item) }"
            :aria-expanded="openedCommonGapKey === commonGapKey(item)"
            @click="toggleCommonGap(item)"
          >
            <span class="faculty-common-gap-rank">{{ index + 1 }}</span>
            <span class="faculty-common-gap-label">
              <strong>{{ item.code || '-' }} · {{ item.name }}</strong>
              <span>{{ item.count }} คนมี Gap ติดลบ · กดเพื่อดูรายชื่อ</span>
            </span>
            <span class="faculty-common-gap-track" aria-hidden="true">
              <span :style="{ width: `${(item.count / maxCommonGapCount) * 100}%` }" />
            </span>
            <span class="faculty-common-gap-count">
              {{ item.count }} คน
              <span
                class="faculty-common-gap-chevron"
                :class="{ open: openedCommonGapKey === commonGapKey(item) }"
                aria-hidden="true"
              >›</span>
            </span>
          </button>

          <div v-if="openedCommonGapKey === commonGapKey(item)" class="faculty-common-gap-people">
            <div v-for="user in item.users" :key="user.db_id || user.sso" class="faculty-common-gap-person">
              <div>
                <strong>{{ `${user.t || ''}${user.n || '-'}` }}</strong>
                <span>{{ user.p || 'ยังไม่ระบุตำแหน่ง' }}</span>
              </div>
              <span>{{ jobFamilyName(user) }}</span>
              <span class="faculty-gap-person-badge">ขาด {{ negativeGapCount(user) }} สมรรถนะ</span>
            </div>
          </div>
        </template>
      </div>
      <div v-else class="faculty-common-gap-empty">
        ยังไม่มีสมรรถนะที่มี Gap ติดลบ
      </div>
    </section>

    <section class="faculty-unit-section">
      <header class="faculty-unit-head">
        <div>
          <h2>ภาพรวมผลการประเมินตามสายงาน</h2>
          <p>เลือกสายงานเพื่อดูความคืบหน้าการประเมิน แยกตามกลุ่มงานและรายบุคคล</p>
        </div>
      </header>

      <div class="faculty-unit-grid">
        <button
          v-for="unit in visibleUnits"
          :key="unit.id"
          class="faculty-unit-card"
          type="button"
          @click="openUnitDetail(unit)"
        >
          <div class="faculty-unit-type">{{ unit.type }}</div>
          <h3>{{ unit.name }}</h3>
          <p>ผู้บังคับบัญชา: {{ unit.supervisor }}</p>

          <div class="faculty-unit-numbers">
            <div>
              <strong>{{ unit.assessed }}/{{ unit.total }}</strong>
              <span>คนประเมินแล้ว</span>
            </div>
          </div>

          <div class="faculty-progress-track" aria-hidden="true">
            <span :style="{ width: `${completionPercent(unit)}%` }" />
          </div>

          <div class="faculty-unit-footer">
            <span>ประเมินแล้ว {{ unit.assessed }} คน</span>
            <strong v-if="pendingFor(unit) > 0">ยังไม่ประเมิน {{ pendingFor(unit) }} คน</strong>
            <strong v-else>ประเมินครบแล้ว</strong>
          </div>
        </button>

        <div v-if="visibleUnits.length === 0" class="faculty-unit-empty">
          ยังไม่มีข้อมูลสายงานสำหรับแสดงผล
        </div>
      </div>
    </section>

    <div v-if="selectedUnit" class="faculty-detail-backdrop" @click.self="closeUnitDetail">
      <section class="faculty-detail-modal" role="dialog" aria-modal="true" :aria-label="`รายละเอียด${selectedUnit.type} ${selectedUnit.name}`">
        <header class="faculty-detail-head">
          <div>
            <h2>{{ selectedUnit.type }}: {{ selectedUnit.name }}</h2>
            <p>ผู้บังคับบัญชา: {{ selectedUnit.supervisor }} · รายละเอียดผลการประเมินแยกตามกลุ่มงานและรายบุคคล</p>
          </div>
          <button class="faculty-detail-close" type="button" @click="closeUnitDetail">ปิด</button>
        </header>

        <div class="faculty-detail-body">
          <section v-for="group in detailGroups" :key="group.name" class="faculty-detail-group">
            <button
              class="faculty-detail-group-head"
              type="button"
              :aria-expanded="openedDetailGroupName === group.name"
              @click="toggleDetailGroup(group.name)"
            >
              <div>
                <h3>{{ group.name }}</h3>
                <p>หัวหน้างาน: {{ group.supervisor }}</p>
              </div>
              <span class="faculty-detail-group-summary">
                <span class="faculty-group-complete">ครบตามเกณฑ์ {{ group.complete }}/{{ group.assessed }} คนที่ประเมินแล้ว</span>
                <span
                  class="faculty-detail-group-chevron"
                  :class="{ open: openedDetailGroupName === group.name }"
                  aria-hidden="true"
                >›</span>
              </span>
            </button>

            <template v-if="openedDetailGroupName === group.name">
              <div class="faculty-person-row faculty-person-columns" aria-hidden="true">
                <div>บุคลากร</div>
                <div>ตำแหน่ง</div>
                <div>คาดหวัง</div>
                <div>ประเมินได้</div>
                <div>Gap</div>
                <div>สถานะ</div>
              </div>

              <div
                v-for="user in group.users"
                :key="user.db_id || user.sso"
                class="faculty-person-row"
                :class="{ pending: !isAssessed(user) }"
              >
                <div class="faculty-person-name">
                  <div class="av faculty-person-avatar">{{ user.n?.[0] || '?' }}</div>
                  <div class="faculty-person-copy">
                    <strong>{{ `${user.t || ''}${user.n || '-'}` }}</strong>
                  </div>
                </div>
                <div class="faculty-person-unit">{{ user.p || 'ยังไม่ระบุตำแหน่ง' }}</div>
                <template v-if="isAssessed(user)">
                  <div>คาดหวัง {{ assessmentTotals(user).expected }} ข้อ</div>
                  <div>ได้จริง {{ assessmentTotals(user).actual }} ข้อ</div>
                  <div
                    class="faculty-person-gap"
                    :class="{ passed: assessmentTotals(user).gap >= 0, failed: assessmentTotals(user).gap < 0 }"
                  >
                    {{ assessmentTotals(user).gap > 0 ? '+' : '' }}{{ assessmentTotals(user).gap }}
                  </div>
                  <span
                    class="faculty-person-status"
                    :class="severityClass(negativeGapCount(user))"
                  >
                    {{ negativeGapCount(user) === 0 ? 'ครบตามเกณฑ์' : `ขาด ${negativeGapCount(user)} สมรรถนะ` }}
                  </span>
                </template>
                <template v-else>
                  <div>คาดหวัง -</div>
                  <div>ได้จริง -</div>
                  <div>-</div>
                  <span class="faculty-person-status pending">ยังไม่เริ่ม/ยังไม่ส่ง</span>
                </template>
              </div>
            </template>
          </section>

          <div v-if="detailGroups.length === 0" class="faculty-unit-empty">
            ยังไม่มีบุคลากรใน{{ selectedUnit.type }}นี้
          </div>
        </div>
      </section>
    </div>
  </section>
</template>

<style scoped>
.faculty-gap-page {
  display: grid;
  gap: 16px;
}

.faculty-page-head h1 {
  margin: 0;
  color: var(--text);
  font-size: 22px;
  font-weight: 900;
}

.faculty-page-head p,
.faculty-unit-head p {
  margin: 6px 0 0;
  color: var(--text3);
  font-size: 12px;
}

.faculty-summary-panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 126px;
  align-items: center;
  gap: 28px;
  min-height: 154px;
  padding: 24px 28px;
  border-radius: 8px;
  background: var(--navy);
  color: #fff;
  box-shadow: var(--sh);
}

.faculty-summary-metrics {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.faculty-summary-item {
  min-width: 0;
  padding: 4px 24px;
  border-left: 1px solid rgba(255, 255, 255, 0.18);
}

.faculty-summary-item:first-child {
  padding-left: 0;
  border-left: 0;
}

.faculty-summary-item span,
.faculty-summary-item small {
  display: block;
  color: rgba(255, 255, 255, 0.68);
  font-size: 11px;
  font-weight: 700;
}

.faculty-summary-item strong {
  display: block;
  margin: 3px 0;
  color: #fff;
  font-size: 28px;
  font-weight: 900;
  line-height: 1;
}

.faculty-summary-item.complete strong {
  color: #5eead4;
}

.faculty-summary-item.moderate strong {
  color: #fde047;
}

.faculty-summary-item.high strong {
  color: #fdba74;
}

.faculty-completion-ring {
  width: 96px;
  aspect-ratio: 1;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: conic-gradient(#14b8a6 0 var(--completion), rgba(255, 255, 255, 0.16) var(--completion) 360deg);
}

.faculty-completion-ring::before {
  content: '';
  grid-area: 1 / 1;
  width: 70px;
  aspect-ratio: 1;
  border-radius: 50%;
  background: var(--navy);
}

.faculty-completion-ring div {
  grid-area: 1 / 1;
  z-index: 1;
  display: grid;
  place-items: center;
}

.faculty-completion-ring strong {
  font-size: 20px;
  font-weight: 900;
}

.faculty-completion-ring span {
  color: rgba(255, 255, 255, 0.68);
  font-size: 9px;
}

.faculty-reminder {
  border: 1px solid var(--border);
  border-left: 4px solid #f97316;
  border-radius: 6px;
  background: #fff;
  box-shadow: var(--sh);
  overflow: hidden;
}

.faculty-reminder-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 14px 18px;
}

.faculty-reminder-toggle {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.faculty-reminder-mark {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 50%;
  background: #fff7ed;
  color: #c2410c;
  font-size: 14px;
  font-weight: 900;
}

.faculty-reminder-copy strong {
  display: block;
  color: var(--text);
  font-size: 13px;
  font-weight: 900;
}

.faculty-reminder-copy > span {
  display: block;
  margin-top: 3px;
  color: var(--text3);
  font-size: 11px;
}

.faculty-accordion-icon {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  flex: 0 0 auto;
  border-radius: 50%;
  background: #fff7ed;
  color: #9a3412;
  font-size: 20px;
  font-weight: 900;
  line-height: 1;
  transition: transform 0.18s ease;
}

.faculty-accordion-icon.open {
  transform: rotate(90deg);
}

.faculty-reminder-button {
  min-height: 34px;
  padding: 0 14px;
  border: 1px solid var(--blue);
  border-radius: 6px;
  background: var(--blue);
  color: #fff;
  font-family: inherit;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}

.faculty-reminder-button:hover {
  background: var(--navy);
  border-color: var(--navy);
}

.faculty-pending-list {
  border-top: 1px solid var(--border);
  background: #fff;
}

.faculty-pending-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.4fr) minmax(180px, 1fr) 110px;
  align-items: center;
  gap: 14px;
  padding: 12px 18px;
  border-bottom: 1px solid var(--border);
  color: var(--text3);
  font-size: 11px;
}

.faculty-pending-row:last-child {
  border-bottom: 0;
}

.faculty-pending-row > div {
  display: grid;
  gap: 3px;
}

.faculty-pending-row strong {
  color: var(--text);
  font-size: 12px;
  font-weight: 900;
}

.faculty-pending-status {
  justify-self: start;
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #f8fafc;
  color: #64748b;
  font-weight: 800;
}

.faculty-pending-empty {
  padding: 24px 18px;
  color: #0f766e;
  font-size: 12px;
  font-weight: 800;
  text-align: center;
}

.faculty-common-gap-section {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--sh);
}

.faculty-common-gap-head {
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
}

.faculty-common-gap-head h2 {
  margin: 0;
  color: var(--text);
  font-size: 15px;
  font-weight: 900;
}

.faculty-common-gap-head p {
  margin: 5px 0 0;
  color: var(--text3);
  font-size: 11px;
}

.faculty-common-gap-list {
  display: grid;
  padding: 8px 20px 14px;
}

.faculty-common-gap-row {
  width: 100%;
  display: grid;
  grid-template-columns: 28px minmax(220px, 1.2fr) minmax(240px, 2fr) 64px;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border: 0;
  border-bottom: 1px solid var(--border);
  background: #fff;
  color: inherit;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.faculty-common-gap-row:hover,
.faculty-common-gap-row.open {
  background: #f8fafc;
}

.faculty-common-gap-row:last-child {
  border-bottom: 0;
}

.faculty-common-gap-rank {
  width: 26px;
  height: 26px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #fff7ed;
  color: #c2410c;
  font-size: 11px;
  font-weight: 900;
}

.faculty-common-gap-label {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.faculty-common-gap-label strong {
  overflow: hidden;
  color: var(--text);
  font-size: 12px;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.faculty-common-gap-label span {
  color: var(--text3);
  font-size: 10px;
}

.faculty-common-gap-track {
  height: 12px;
  overflow: hidden;
  border-radius: 3px;
  background: #e2e8f0;
}

.faculty-common-gap-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #c2410c;
}

.faculty-common-gap-count {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 7px;
  color: #9a3412;
  font-size: 11px;
  font-weight: 900;
  text-align: right;
}

.faculty-common-gap-chevron {
  display: inline-block;
  font-size: 18px;
  line-height: 1;
  transition: transform 0.18s ease;
}

.faculty-common-gap-chevron.open {
  transform: rotate(90deg);
}

.faculty-common-gap-people {
  padding: 4px 14px 10px 42px;
  border-bottom: 1px solid var(--border);
  background: #f8fafc;
}

.faculty-common-gap-person {
  display: grid;
  grid-template-columns: minmax(210px, 1.4fr) minmax(180px, 1fr) 130px;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  color: var(--text3);
  font-size: 11px;
}

.faculty-common-gap-person:last-child {
  border-bottom: 0;
}

.faculty-common-gap-person > div {
  display: grid;
  gap: 3px;
}

.faculty-common-gap-person strong {
  color: var(--text);
  font-size: 12px;
  font-weight: 900;
}

.faculty-gap-person-badge {
  justify-self: start;
  padding: 4px 8px;
  border: 1px solid #fb923c;
  border-radius: 999px;
  background: #fff7ed;
  color: #9a3412;
  font-weight: 900;
  white-space: nowrap;
}

.faculty-common-gap-empty {
  padding: 34px 20px;
  color: var(--text3);
  font-size: 12px;
  text-align: center;
}

.faculty-unit-section {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--sh);
}

.faculty-unit-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
}

.faculty-unit-head h2 {
  margin: 0;
  color: var(--text);
  font-size: 15px;
  font-weight: 900;
}

.faculty-unit-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  padding: 16px 18px;
}

.faculty-unit-card {
  width: 100%;
  min-width: 0;
  padding: 18px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  color: inherit;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}

.faculty-unit-card:hover,
.faculty-unit-card:focus-visible {
  border-color: var(--blue);
  box-shadow: 0 5px 16px rgba(15, 23, 42, 0.1);
  outline: none;
}

.faculty-unit-empty {
  grid-column: 1 / -1;
  min-height: 180px;
  display: grid;
  place-items: center;
  padding: 28px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  color: var(--text3);
  font-size: 12px;
  text-align: center;
}

.faculty-unit-type {
  color: var(--teal);
  font-size: 10px;
  font-weight: 900;
}

.faculty-unit-card h3 {
  min-height: 40px;
  margin: 7px 0 4px;
  color: var(--text);
  font-size: 14px;
  font-weight: 900;
  line-height: 1.45;
}

.faculty-unit-card > p {
  min-height: 32px;
  margin: 0;
  color: var(--text3);
  font-size: 11px;
  line-height: 1.45;
}

.faculty-unit-numbers {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  margin-top: 16px;
}

.faculty-unit-numbers > div:first-child {
  display: grid;
}

.faculty-unit-numbers strong {
  color: var(--navy);
  font-size: 24px;
  font-weight: 900;
  line-height: 1;
}

.faculty-unit-numbers span {
  color: var(--text3);
  font-size: 10px;
}

.faculty-result-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.faculty-result-pills span {
  padding: 4px 8px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
}

.faculty-result-pills .passed {
  border-color: #99f6e4;
  background: #f0fdfa;
  color: #0f766e;
}

.faculty-result-pills .failed {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}

.faculty-progress-track {
  height: 10px;
  margin-top: 10px;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}

.faculty-progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--teal);
}

.faculty-unit-footer {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-top: 6px;
  font-size: 10px;
}

.faculty-unit-footer span {
  color: var(--text3);
}

.faculty-unit-footer strong {
  color: #c2410c;
  font-weight: 800;
}

.faculty-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(15, 23, 42, 0.48);
}

.faculty-detail-modal {
  width: min(1040px, 100%);
  max-height: calc(100vh - 36px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}

.faculty-detail-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
}

.faculty-detail-head h2,
.faculty-detail-group-head h3 {
  margin: 0;
  color: var(--text);
  font-size: 15px;
  font-weight: 900;
}

.faculty-detail-head p,
.faculty-detail-group-head p {
  margin: 4px 0 0;
  color: var(--text3);
  font-size: 11px;
}

.faculty-detail-close {
  min-width: 42px;
  min-height: 34px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
  color: var(--text);
  font-family: inherit;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

.faculty-detail-close:hover {
  border-color: var(--navy);
  background: var(--navy);
  color: #fff;
}

.faculty-detail-body {
  display: grid;
  gap: 14px;
  padding: 20px;
  overflow-y: auto;
}

.faculty-detail-group {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
}

.faculty-detail-group-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 16px;
  border: 0;
  border-left: 4px solid var(--teal);
  border-bottom: 1px solid var(--border);
  background: #f8fafc;
  color: inherit;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.faculty-detail-group-head:hover {
  background: #f1f5f9;
}

.faculty-detail-group-head:focus-visible {
  outline: 2px solid var(--blue);
  outline-offset: -2px;
}

.faculty-detail-group-summary {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
}

.faculty-detail-group-chevron {
  display: inline-block;
  color: var(--text3);
  font-size: 22px;
  font-weight: 900;
  line-height: 1;
  transition: transform 0.18s ease;
}

.faculty-detail-group-chevron.open {
  transform: rotate(90deg);
}

.faculty-group-complete {
  padding: 5px 9px;
  border: 1px solid #99f6e4;
  border-radius: 999px;
  background: #f0fdfa;
  color: #0f766e;
  font-size: 10px;
  font-weight: 900;
  white-space: nowrap;
}

.faculty-person-row {
  display: grid;
  grid-template-columns: minmax(210px, 1.4fr) minmax(170px, 1.1fr) 100px 100px 64px 130px;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  color: var(--text2);
  font-size: 12px;
}

.faculty-person-columns {
  min-height: 38px;
  padding-top: 9px;
  padding-bottom: 9px;
  background: #eef2f7;
  color: #64748b;
  font-size: 10px;
  font-weight: 900;
}

.faculty-person-columns:nth-child(even) {
  background: #eef2f7;
}

.faculty-person-row:last-child {
  border-bottom: 0;
}

.faculty-person-row:nth-child(even) {
  background: #f8fafc;
}

.faculty-person-row.pending {
  color: var(--text3);
  opacity: 0.55;
}

.faculty-person-name {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.faculty-person-avatar {
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  background: var(--navy);
  color: #fff;
  font-size: 12px;
}

.faculty-person-copy {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.faculty-person-name strong {
  color: var(--text);
  font-size: 13px;
  font-weight: 900;
}

.faculty-person-copy span,
.faculty-person-unit {
  color: var(--text3);
  font-size: 11px;
}

.faculty-person-gap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  justify-self: start;
  min-width: 42px;
  min-height: 26px;
  padding: 3px 9px;
  border: 1px solid;
  border-radius: 999px;
  font-weight: 900;
}

.faculty-person-gap.passed {
  border-color: #99f6e4;
  background: #f0fdfa;
  color: #0f766e;
}

.faculty-person-gap.failed {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.faculty-person-status {
  justify-self: start;
  padding: 5px 9px;
  border: 1px solid;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 900;
  white-space: nowrap;
}

.faculty-person-status.complete {
  border-color: #5eead4;
  background: #f0fdfa;
  color: #0f766e;
}

.faculty-person-status.moderate {
  border-color: #facc15;
  background: #fefce8;
  color: #854d0e;
}

.faculty-person-status.high {
  border-color: #fb923c;
  background: #fff7ed;
  color: #9a3412;
}

.faculty-person-status.pending {
  border-color: #cbd5e1;
  background: #f8fafc;
  color: #64748b;
}

@media (max-width: 1180px) {
  .faculty-unit-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .faculty-summary-panel {
    grid-template-columns: 1fr;
  }

  .faculty-summary-metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    row-gap: 20px;
  }

  .faculty-summary-item:nth-child(3) {
    padding-left: 0;
    border-left: 0;
  }

  .faculty-completion-ring {
    justify-self: center;
  }

  .faculty-person-row {
    grid-template-columns: minmax(180px, 1fr) repeat(4, auto);
  }

  .faculty-person-columns {
    display: none;
  }

  .faculty-person-unit {
    grid-column: 1 / -1;
    grid-row: 2;
  }

  .faculty-common-gap-row {
    grid-template-columns: 28px minmax(0, 1fr) 54px;
  }

  .faculty-common-gap-track {
    grid-column: 2 / -1;
  }

  .faculty-common-gap-person {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 620px) {
  .faculty-unit-head {
    align-items: stretch;
    flex-direction: column;
  }

  .faculty-reminder-top {
    align-items: stretch;
    flex-direction: column;
  }

  .faculty-reminder-button {
    width: 100%;
  }

  .faculty-pending-row {
    grid-template-columns: minmax(0, 1fr);
  }

  .faculty-unit-grid,
  .faculty-summary-metrics {
    grid-template-columns: minmax(0, 1fr);
  }

  .faculty-common-gap-row {
    grid-template-columns: 28px minmax(0, 1fr) 50px;
    gap: 9px;
  }

  .faculty-common-gap-people {
    padding-left: 14px;
  }

  .faculty-common-gap-person {
    grid-template-columns: minmax(0, 1fr);
  }

  .faculty-summary-item,
  .faculty-summary-item:nth-child(3) {
    padding: 12px 0 0;
    border-top: 1px solid rgba(255, 255, 255, 0.16);
    border-left: 0;
  }

  .faculty-summary-item:first-child {
    padding-top: 0;
    border-top: 0;
  }

  .faculty-detail-backdrop {
    padding: 0;
  }

  .faculty-detail-modal {
    width: 100%;
    max-height: 100vh;
    min-height: 100vh;
    border: 0;
    border-radius: 0;
  }

  .faculty-detail-group-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .faculty-detail-group-summary {
    width: 100%;
    justify-content: space-between;
  }

  .faculty-person-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .faculty-person-name,
  .faculty-person-unit,
  .faculty-person-status {
    grid-column: 1 / -1;
  }
}
</style>
