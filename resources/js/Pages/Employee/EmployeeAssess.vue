<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
  user: any;
  setUsers: any;
  competencies?: any[];
  roundId?: number;
}>();

const assignedCompetencies = computed(() => props.competencies || []);
const selectedCompetency = ref<any | null>(null);
const checkedIndicators = ref<Record<string, boolean>>({});
const competencyNotes = ref<Record<string, string>>({});
const isSaving = ref(false);

const openCompetencyDetail = async (item: any) => {
  selectedCompetency.value = item;

  try {
    const res = await fetch(
      route('assessments.load')
      + `?competency_id=${item.id}&round_id=${props.roundId || 1}`
    );
    const data = await res.json();
    if (data.checked) checkedIndicators.value = { ...checkedIndicators.value, ...data.checked };
    if (data.note) competencyNotes.value[String(item.id)] = data.note;
  } catch {
    // ถ้า load ไม่ได้ ใช้ค่าเดิม
  }
};

const closeCompetencyDetail = () => {
  selectedCompetency.value = null;
};

const saveAndClose = async () => {
  if (!selectedCompetency.value || isSaving.value) return;
  isSaving.value = true;

  router.post(route('assessments.save'), {
    competency_id: selectedCompetency.value.id,
    assessment_round_id: props.roundId || 1,
    checked_indicators: checkedIndicators.value,
    note: competencyNotes.value[noteKey.value] || '',
    score: parseFloat(currentScore.value),
  }, {
    preserveScroll: true,
    onSuccess: () => {
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
  const key = indicatorKey(level, indicatorIndex);
  const position = flattenedIndicators.value.findIndex((row) => row.key === key);
  if (position <= 0) return true;
  return Boolean(checkedIndicators.value[flattenedIndicators.value[position - 1].key]);
};

const handleIndicatorChange = (level: any, indicatorIndex: number) => {
  const key = indicatorKey(level, indicatorIndex);
  const position = flattenedIndicators.value.findIndex((row) => row.key === key);

  if (!checkedIndicators.value[key]) {
    flattenedIndicators.value.slice(position + 1).forEach((row) => {
      checkedIndicators.value[row.key] = false;
    });
  }
};
</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>ประเมินตนเอง</h1>
        <p>รอเชื่อมข้อมูลสมรรถนะและแบบประเมินจริง</p>
      </div>
      <span class="b bgr">{{ assignedCompetencies.length ? 'พร้อมประเมิน' : 'ยังไม่มีข้อมูล' }}</span>
    </div>

    <div v-if="assignedCompetencies.length" class="summary-grid">
      <div class="summary-card">
        <div class="summary-label">สมรรถนะที่ต้องประเมิน</div>
        <div class="summary-value">{{ assignedCompetencies.length }}</div>
        <div class="summary-copy">รายการที่ HR ผูกกับกลุ่มงาน/ระดับของคุณ</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">ประเมินตนเองแล้ว</div>
        <div class="summary-value">0</div>
        <div class="summary-copy">รอพัฒนาแบบฟอร์มให้คะแนนจริง</div>
      </div>
    </div>

    <div v-if="assignedCompetencies.length" class="content-card">
      <div class="card-head">
        <div>
          <h2>หัวข้อสมรรถนะที่ต้องประเมิน</h2>
          <p>รายการนี้มาจากการผูก Competency กับกลุ่มงานโดย HR</p>
        </div>
      </div>
      <div class="competency-list">
        <button v-for="item in assignedCompetencies" :key="item.id" class="competency-row" type="button" @click="openCompetencyDetail(item)">
          <div>
            <div class="competency-title">
              <span class="type-tag">{{ item.t || '-' }}</span>
              <span>{{ item.cd }} · {{ item.n }}</span>
            </div>
            <div class="competency-detail">{{ item.det || 'ไม่มีคำอธิบาย' }}</div>
          </div>
          <div class="row-actions">
            <span class="level-pill">Expected {{ item.expectedLevel || '-' }}</span>
            <span class="detail-link">รายละเอียด</span>
          </div>
        </button>
      </div>
    </div>

    <div v-else class="empty-card">
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
          </div>
          <button class="btn btn-s btn-sm" type="button" @click="closeCompetencyDetail">ปิด</button>
        </div>

        <div class="modal-body">
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
                  :disabled="!isIndicatorUnlocked(level, index)"
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

          <section v-if="selectedCompetency.levels?.length" class="emp-note-section">
            <label for="competency-note">ความคิดเห็นต่อสมรรถนะนี้</label>
            <textarea
              id="competency-note"
              v-model="competencyNotes[noteKey]"
              placeholder="อธิบายเหตุผลประกอบการประเมินสมรรถนะนี้..."
            />
          </section>
        </div>

        <div class="modal-foot">
          <span>คะแนนปัจจุบัน {{ currentScore }}/5.00 จาก {{ selectedIndicators }}/{{ totalIndicators }} พฤติกรรม</span>
          <button class="btn btn-t" type="button" :disabled="isSaving" @click="saveAndClose">
            {{ isSaving ? 'กำลังบันทึก...' : 'บันทึกและปิด' }}
            <small>{{ selectedIndicators }}/{{ totalIndicators }}</small>
          </button>
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
  padding: 14px 16px;
  text-align: left;
  cursor: pointer;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.competency-row:hover { border-color: rgba(37, 99, 235, .35); box-shadow: 0 12px 28px rgba(15, 23, 42, .08); }
.competency-title { display: flex; align-items: center; gap: 10px; color: var(--text); font-size: 14px; font-weight: 900; }
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
.competency-detail { margin-top: 6px; color: var(--text3); font-size: 12px; }
.level-pill {
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--green-bg, #ecfdf5);
  color: var(--green, #047857);
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 900;
}
.row-actions { display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
.detail-link { color: var(--blue); font-size: 12px; font-weight: 900; }
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
.modal-body {
  flex: 1 1 auto;
  min-height: 0;
  padding: 18px 28px;
  overflow-y: auto;
  background: #f6f8fb;
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
.modal-foot small { font-size: 11px; opacity: .85; }
@media (max-width: 760px) {
  .page-head { align-items: flex-start; flex-direction: column; }
  .summary-grid { grid-template-columns: 1fr; }
  .competency-row { align-items: flex-start; flex-direction: column; }
  .row-actions { width: 100%; justify-content: space-between; }
  .modal-head,
  .modal-foot { flex-direction: column; align-items: stretch; }
  .modal-body,
  .modal-head,
  .modal-foot { padding-left: 16px; padding-right: 16px; }
}
</style>
