<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { getIdpPlan, getViewerScope, listIdpPlans, listPeriods } from '../../services/historyService.js';
import RoundSelect from '../../Components/RoundSelect.vue';

const scopeInfo = getViewerScope();
const scope = scopeInfo.scope;
const people = scopeInfo.people || [];
const periods = ref<any[]>([{ id: 'all', label: 'ทุกรอบ', range: '', current: false }]);
const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
const roundId = ref(params.get('round') || 'all');
const ownerId = ref('self');
const plans = ref<any[]>([]);
const selectedPlan = ref<any | null>(null);
const activeStatus = ref('all');
const loading = ref(false);
const error = ref(false);
const drawer = ref<HTMLElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
let opener: HTMLElement | null = null;
let requestId = 0;
const statusFilters = [{ id: 'all', label: 'ทั้งหมด' }, { id: 'approved', label: 'อนุมัติแล้ว' }, { id: 'pending', label: 'รออนุมัติ' }, { id: 'rejected', label: 'ส่งกลับแก้ไข' }, { id: 'draft', label: 'ร่าง' }];
const viewingOther = computed(() => ownerId.value !== 'self');
const viewerName = computed(() => people.find((person: any) => person.id === ownerId.value)?.name || scopeInfo.viewer.name);
const filteredPlans = computed(() => plans.value.filter(plan => activeStatus.value === 'all' || plan.status === activeStatus.value));
const groupedPlans = computed(() => periods.value.filter(period => period.id !== 'all' && (roundId.value === 'all' || period.id === roundId.value)).map(period => ({
  ...period,
  plans: filteredPlans.value.filter(plan => plan.roundId === period.id),
})));
const noSelectedRoundPlans = computed(() => roundId.value !== 'all' && !loading.value && !error.value && groupedPlans.value.every(group => group.plans.length === 0));
const totalBehaviors = (plan: any) => plan.skills.reduce((sum: number, skill: any) => sum + skill.behaviors.length, 0);
const activityExpanded = ref<Record<string, boolean>>({});
function isActivityExpanded(skillId: string, activityIndex: number) { return activityExpanded.value[`${skillId}-${activityIndex}`] ?? activityIndex === 0; }
function toggleActivity(skillId: string, activityIndex: number) { const key = `${skillId}-${activityIndex}`; activityExpanded.value[key] = !isActivityExpanded(skillId, activityIndex); }
const selectedMethod = ref<any | null>(null);
const methodModal = ref<HTMLElement | null>(null);
const methodCloseButton = ref<HTMLButtonElement | null>(null);
let methodOpener: HTMLElement | null = null;
const developmentMethodCount = (plan: any) => plan.fillMode === 'form'
  ? plan.skills.reduce((sum: number, skill: any) => sum + (skill.methods?.length || 0), 0)
  : plan.skills.reduce((sum: number, skill: any) => sum + skill.behaviors.reduce((behaviorTotal: number, behavior: any) => behaviorTotal + behavior.methods.length, 0), 0);
function openMethod(method: any, event: MouseEvent) { methodOpener = event.currentTarget as HTMLElement; selectedMethod.value = method; nextTick(() => methodCloseButton.value?.focus()); }
function closeMethod() { selectedMethod.value = null; nextTick(() => methodOpener?.focus()); }

async function loadPlans() {
  const id = ++requestId;
  loading.value = true; error.value = false;
  try {
    const [nextPeriods, nextPlans] = await Promise.all([listPeriods(), listIdpPlans({ roundId: roundId.value })]);
    if (id !== requestId) return;
    periods.value = nextPeriods;
    plans.value = nextPlans;
  }
  catch { if (id === requestId) error.value = true; }
  finally { if (id === requestId) loading.value = false; }
}
function setRound(value: string) {
  roundId.value = value;
  const url = new URL(window.location.href);
  if (value === 'all') url.searchParams.delete('round'); else url.searchParams.set('round', value);
  window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
  loadPlans();
}
function statusLabel(value: string) { return ({ draft: 'ร่าง', pending: 'รออนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ส่งกลับแก้ไข' } as Record<string, string>)[value] || value; }
function formatDate(value?: string) { return value ? new Date(`${value}T00:00:00`).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) : 'ยังไม่ส่ง'; }
function trapFocus(event: KeyboardEvent) {
  if (selectedMethod.value) {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeMethod(); return; }
    if (event.key === 'Tab' && methodModal.value) {
      const modalFocusable = [...methodModal.value.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])')];
      const first = modalFocusable[0], last = modalFocusable[modalFocusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    return;
  }
  if (!selectedPlan.value) return;
  if (event.key === 'Escape') { closeDrawer(); return; }
  if (event.key !== 'Tab' || !drawer.value) return;
  const focusable = [...drawer.value.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], select, input, [tabindex]:not([tabindex="-1"])')];
  if (!focusable.length) return;
  const first = focusable[0], last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
async function openPlan(plan: any, event: MouseEvent) {
  opener = event.currentTarget as HTMLElement;
  try { selectedPlan.value = await getIdpPlan(plan.id); }
  catch { selectedPlan.value = plan; }
  await nextTick(); closeButton.value?.focus();
}
function closeDrawer() { selectedPlan.value = null; nextTick(() => opener?.focus()); }

watch(ownerId, loadPlans);
onMounted(() => { window.addEventListener('keydown', trapFocus); loadPlans(); });
onBeforeUnmount(() => window.removeEventListener('keydown', trapFocus));
</script>

<template>
  <section class="history-page">
    <header class="page-head"><div><h1>ประวัติ IDP</h1><p>{{ viewingOther ? `ประวัติ IDP ของ ${viewerName}` : 'แผน IDP และทักษะที่พัฒนาตามรอบการประเมิน' }}</p></div></header>

    <section class="toolbar">
      <RoundSelect :model-value="roundId" :periods="periods" @update:model-value="setRound" />
      <label v-if="scope === 'unit' || scope === 'all'" class="person-control"><span>ดูประวัติของ</span><select v-model="ownerId"><option value="self">ตัวเอง</option><option v-for="person in people.filter((item: any) => item.id !== 'self')" :key="person.id" :value="person.id">{{ person.name }}</option></select></label>
    </section>
    <div v-if="viewingOther" class="viewing-banner">กำลังดูประวัติของ {{ viewerName }}</div>

    <nav class="status-filters" aria-label="กรองสถานะแผน"><button v-for="filter in statusFilters" :key="filter.id" type="button" :class="{ active: activeStatus === filter.id }" :aria-pressed="activeStatus === filter.id" @click="activeStatus = filter.id">{{ filter.label }}</button></nav>

    <div v-if="loading" class="plan-list"><div v-for="n in 3" :key="n" class="plan-skeleton"><i></i><div><i></i><i></i><i></i></div></div></div>
    <div v-else-if="error" class="message-state"><strong>โหลดข้อมูลไม่สำเร็จ</strong><span>โปรดลองอีกครั้ง</span><button type="button" @click="loadPlans">ลองใหม่</button></div>
    <div v-else-if="noSelectedRoundPlans" class="message-state"><strong>ไม่พบแผน IDP ในรอบนี้</strong><span>ยังไม่มีรายการแผนสำหรับรอบการประเมินที่เลือก</span><button type="button" @click="setRound('all')">ดูทุกรอบ</button></div>
    <div v-else class="round-groups">
      <section v-for="period in groupedPlans" :key="period.id" class="round-group">
        <header class="round-heading"><div><h2>{{ period.label }} <span v-if="period.current" class="current-tag">ปัจจุบัน</span></h2><p>{{ period.range }}</p></div><span>{{ period.plans.length }} แผน</span></header>
        <p v-if="!period.plans.length" class="round-empty">ไม่มีแผน IDP ในรอบนี้</p>
        <div v-else class="plan-list">
          <button v-for="plan in period.plans" :key="plan.id" type="button" class="plan-card" @click="openPlan(plan, $event)">
            <span class="plan-top"><strong>{{ plan.planCode }}</strong><span class="plan-badges"><span v-if="plan.fillMode === 'form'" class="form-badge">แบบฟอร์ม</span><span class="status-badge" :class="`status-${plan.status}`">{{ statusLabel(plan.status) }}</span></span></span>
            <span class="plan-meta"><span>วันที่ส่ง</span><strong>{{ formatDate(plan.submittedAt) }}</strong></span>
            <span class="skill-caption">ทักษะที่พัฒนา</span>
            <span class="skill-chips"><span v-for="skill in plan.skills.slice(0, 4)" :key="skill.id" class="skill-chip">{{ skill.name }}</span><span v-if="plan.skills.length > 4" class="more-chip">+{{ plan.skills.length - 4 }}</span></span>
            <span class="behavior-count">พัฒนา {{ totalBehaviors(plan) }} พฤติกรรม · วิธีพัฒนา {{ developmentMethodCount(plan) }} วิธี</span>
          </button>
        </div>
      </section>
    </div>

    <div v-if="selectedPlan" class="drawer-backdrop" @click.self="closeDrawer">
      <aside ref="drawer" class="detail-drawer" role="dialog" aria-modal="true" aria-label="รายละเอียดแผน IDP" tabindex="-1">
        <header class="drawer-head"><div><small>รอบการประเมิน</small><h2>{{ selectedPlan.roundLabel }}</h2><p>{{ selectedPlan.roundRange }}</p></div><button ref="closeButton" type="button" aria-label="ปิดรายละเอียด" @click="closeDrawer">×</button></header>
        <section class="drawer-plan-meta"><strong>{{ selectedPlan.planCode }}</strong><span class="plan-badges"><span v-if="selectedPlan.fillMode === 'form'" class="form-badge">แบบฟอร์ม</span><span class="status-badge" :class="`status-${selectedPlan.status}`">{{ statusLabel(selectedPlan.status) }}</span></span><div><small>วันที่ส่ง</small><b>{{ formatDate(selectedPlan.submittedAt) }}</b></div></section>
        <section class="drawer-section"><h3>ทักษะที่พัฒนา</h3>
          <article v-for="skill in selectedPlan.skills" :key="skill.id" :class="selectedPlan.fillMode === 'form' ? 'form-skill-row' : 'skill-detail'"><header><h4>{{ skill.name }}</h4><span>ระดับ {{ skill.actualLevel }} → {{ skill.expectedLevel }}</span><button v-if="selectedPlan.fillMode === 'form'" type="button" class="skill-expand-button" :aria-expanded="isActivityExpanded(skill.id, 0)" @click="toggleActivity(skill.id, 0)">{{ isActivityExpanded(skill.id, 0) ? 'ซ่อนรายละเอียด' : 'ดูรายละเอียด' }}</button></header>
            <template v-if="selectedPlan.fillMode === 'form'">
              <div class="skill-expand-collapse" :class="{ expanded: isActivityExpanded(skill.id, 0) }" :inert="!isActivityExpanded(skill.id, 0)"><div class="skill-expand-inner"><h5>วิธีพัฒนาที่เลือก</h5><div class="skill-method-list"><button v-for="(method, methodIndex) in (skill.methods || [])" :key="`${method.name}-${methodIndex}`" type="button" class="method-open" @click="openMethod(method, $event)"><strong>{{ method.name }}</strong><span class="method-type">{{ method.type }}</span><span class="method-filled" :class="method.filled ? 'is-filled' : 'is-empty'">{{ method.filled ? 'กรอกแล้ว' : 'ยังไม่กรอก' }}</span><span class="method-arrow">›</span></button><p v-if="!skill.methods?.length" class="no-method">{{ skill.sharedMethodsNote || 'ยังไม่ได้เลือกวิธีพัฒนา' }}</p></div><h5>พฤติกรรมที่ต้องพัฒนา</h5><ul class="behavior-brief-list"><li v-for="(behavior, index) in skill.behaviors" :key="behavior.id"><strong>ระดับ {{ behavior.level }} · ข้อ {{ index + 1 }}</strong><span>{{ behavior.text }}</span></li></ul></div></div>
            </template>
            <template v-else>
              <h5>พฤติกรรมที่พัฒนา</h5><div v-for="(behavior, index) in skill.behaviors" :key="behavior.id" class="behavior-detail"><p><strong>ระดับ {{ behavior.level }} · ข้อ {{ index + 1 }}</strong>{{ behavior.text }}</p><div class="method-chips"><span v-for="method in behavior.methods" :key="method">{{ method }}</span></div></div>
            </template>
          </article>
        </section>
        <section class="drawer-section timeline-section"><h3>ไทม์ไลน์อนุมัติ</h3><p v-if="!selectedPlan.approvals.length" class="timeline-empty">ยังไม่มีรายการส่งอนุมัติ</p><ol v-else class="approval-timeline"><li v-for="(approval, index) in selectedPlan.approvals" :key="index"><span class="timeline-dot" :class="{ pending: approval.status === 'รออนุมัติ' }"></span><div><strong>{{ approval.approver }}</strong><span class="timeline-status">{{ approval.status }}</span><time>{{ formatDate(approval.date) }}</time><p v-if="approval.comment">{{ approval.comment }}</p></div></li></ol></section>
      </aside>
      <div v-if="selectedMethod" class="method-modal-backdrop" @click.self="closeMethod">
        <section ref="methodModal" class="method-modal" role="dialog" aria-modal="true" aria-labelledby="method-modal-title" tabindex="-1">
          <header class="method-modal-head"><h2 id="method-modal-title">รายละเอียดฟอร์มกิจกรรม</h2><button ref="methodCloseButton" type="button" aria-label="ปิดรายละเอียดฟอร์ม" @click="closeMethod">×</button></header>
          <div class="method-modal-content"><header class="form-title-banner"><h3>{{ selectedMethod.formTitle }}</h3></header>
            <section v-for="(section, sectionIndex) in selectedMethod.sections" :key="sectionIndex" class="form-data-section"><header><h3>{{ section.title }}</h3><span v-if="section.badge">{{ section.badge }}</span></header>
              <dl v-if="section.fields.length" class="form-value-grid"><div v-for="field in section.fields" :key="field.label"><dt>{{ field.label }}</dt><dd>{{ selectedMethod.filled ? (field.value || '-') : '-' }}</dd></div></dl>
              <article v-for="(repeat, repeatIndex) in (section.repeatable || [])" :key="repeatIndex" class="repeatable-form-row"><h4>{{ repeat.title }}</h4><dl class="form-value-grid"><div v-for="field in repeat.fields" :key="field.label"><dt>{{ field.label }}</dt><dd>{{ selectedMethod.filled ? (field.value || '-') : '-' }}</dd></div></dl></article>
            </section>
          </div>
          <footer class="method-modal-footer"><button type="button" @click="closeMethod">ปิด</button></footer>
        </section>
      </div>
    </div>
  </section>
</template>

<style scoped>
.history-page { --history-ink: #263a32; --history-muted: #63736c; --history-line: #dce5e1; color: var(--history-ink); }
.plan-badges { display:inline-flex; align-items:center; flex-wrap:wrap; justify-content:flex-end; gap:6px; }.form-badge { display:inline-flex; align-items:center; border:1px solid #b7d8f2; border-radius:999px; background:#eff8ff; padding:4px 9px; color:#175d90; font-size:14px; font-weight:700; white-space:nowrap; }
.form-goals { margin:16px 22px 0; border:1px solid var(--history-line); border-radius:9px; background:#fff; padding:15px; }.form-goals h3 { margin:0 0 11px; font-size:17px; }.goal-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px; }.goal-grid > div { min-width:0; border:1px solid #eee6e2; border-radius:7px; background:#faf7f5; padding:12px; }.goal-grid strong { font-size:14px; }.goal-grid p { margin:6px 0 0; color:#3f4f47; font-size:16px; line-height:1.55; white-space:pre-wrap; overflow-wrap:anywhere; }.drawer-section:has(.activity-heading) { padding-top:15px; }.activity-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 8px; }.activity-heading h5 { margin:0; color:#4d5d55; font-size:16px; }.activity-heading > span { border:1px solid #b9dfc5; border-radius:999px; background:#edf8f0; padding:4px 9px; color:#187243; font-size:14px; font-weight:800; }.form-activity { margin:8px 0; border:1px solid #e0e7e3; border-radius:8px; background:#fff; }.activity-toggle { display:grid; grid-template-columns:28px minmax(0,1fr) auto 18px; align-items:center; gap:9px; width:100%; min-height:48px; border:0; border-radius:8px; background:#fff; padding:8px 11px; color:var(--history-ink); text-align:left; cursor:pointer; }.activity-index { display:grid; place-items:center; width:26px; height:26px; border-radius:50%; background:#edf5f1; color:#27674f; font-size:14px; font-weight:800; }.activity-toggle strong { font-size:16px; font-weight:600; }.activity-weight { border:1px solid #d9e2dd; border-radius:999px; background:#f6f8f7; padding:3px 8px; font-size:14px; font-weight:800; }.activity-chevron { color:#627169; font-size:20px; }.activity-chevron.expanded { transform:rotate(180deg); }.activity-collapse { display:grid; grid-template-rows:0fr; }.activity-collapse.expanded { grid-template-rows:1fr; }.activity-collapse-inner { overflow:hidden; }.activity-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 12px; margin:0; padding:0 12px; }.activity-fields > div { min-width:0; border-top:1px solid #edf0ee; padding:9px 0; }.activity-fields dt { color:var(--history-muted); font-size:14px; }.activity-fields dd { margin:3px 0 0; color:var(--history-ink); font-size:16px; line-height:1.5; overflow-wrap:anywhere; }
.page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin:0 0 18px; }.page-head h1 { margin:0; font-size:22px; line-height:1.4; }.page-head p { margin:5px 0 0; color:var(--history-muted); font-size:16px; }.test-mode { flex:none; border:1px solid #e5cf9d; border-radius:999px; background:#fff8e8; padding:7px 12px; color:#805817; font-size:14px; font-weight:800; }
.toolbar { display:flex; align-items:end; gap:12px; margin:0 0 12px; border:1px solid var(--history-line); border-radius:10px; background:#fff; padding:13px 16px; }.round-control,.person-control { display:grid; flex:0 1 auto; gap:6px; color:#495b53; font-size:14px; font-weight:800; }.round-control { position:relative; width:min(420px,100%); }.round-trigger { display:flex; align-items:center; justify-content:space-between; gap:16px; width:min(420px,100%); min-width:min(320px,100%); min-height:46px; border:1px solid #cbd8d2; border-radius:7px; background:#fff; padding:9px 13px; color:var(--history-ink); font:inherit; font-size:16px; font-weight:600; text-align:left; cursor:pointer; }.round-trigger > span:first-child { min-width:0; overflow-wrap:anywhere; }.round-chevron { width:9px; height:9px; flex:none; margin:0 2px 4px 0; transform:rotate(45deg); border-right:2px solid #586960; border-bottom:2px solid #586960; }.round-options { position:absolute; z-index:30; top:calc(100% + 5px); left:0; width:100%; max-height:320px; overflow-y:auto; overflow-x:hidden; border:1px solid #d5ded9; border-radius:10px; background:#fff; box-shadow:0 10px 24px rgba(30,55,44,.16); padding:4px; }.round-option { display:flex; align-items:flex-start; gap:9px; width:100%; min-height:44px; border:0; border-radius:6px; background:#fff; padding:10px 14px; color:var(--history-ink); font:inherit; font-size:16px; font-weight:500; text-align:left; cursor:pointer; }.round-option:hover,.round-option.highlighted { background:#faf7f5; }.round-option.selected { background:var(--brand-soft,var(--color-primary-soft,#edf5f1)); }.option-label { flex:1; min-width:0; overflow-wrap:anywhere; line-height:1.45; }.option-current { flex:none; border-radius:999px; background:#edf8f0; padding:2px 7px; color:#187243; font-size:14px; white-space:nowrap; }.option-check { flex:none; color:var(--brand,var(--color-primary,#37866e)); font-size:18px; font-weight:900; line-height:1.1; }.person-control { flex:1; }.person-control select { width:100%; min-height:44px; border:1px solid #cbd8d2; border-radius:7px; background:#fff; padding:8px 11px; color:var(--history-ink); font:inherit; font-size:16px; }
.viewing-banner { margin:0 0 12px; border:1px solid #b7d8f2; border-radius:8px; background:#eff8ff; padding:10px 14px; color:#175d90; font-size:16px; font-weight:800; }.status-filters { display:flex; flex-wrap:wrap; gap:7px; margin:13px 0 16px; }.status-filters button { min-height:34px; border:1px solid #d2ddd8; border-radius:999px; background:#f7faf8; padding:5px 12px; color:#52645b; font:inherit; font-size:14px; font-weight:800; cursor:pointer; }.status-filters button.active { border-color:var(--color-primary,#37866e); background:var(--color-primary-soft,#e8f4ef); color:var(--green-deep,#17634d); }
.round-groups { display:grid; gap:17px; }.round-group { display:grid; gap:9px; }.round-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid var(--history-line); padding:0 2px 9px; }.round-heading h2 { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:0; font-size:18px; }.round-heading p { margin:3px 0 0; color:var(--history-muted); font-size:14px; }.round-heading > span { color:var(--history-muted); font-size:14px; }.current-tag { border-radius:999px; background:#edf8f0; padding:4px 8px; color:#187243; font-size:14px; }.round-empty { margin:0; border:1px dashed #d5ded9; border-radius:8px; background:#f8faf9; padding:15px; color:#737f79; font-size:15px; }
.plan-list { display:grid; gap:9px; }.plan-card { display:grid; gap:8px; width:100%; border:1px solid var(--history-line); border-radius:10px; background:#fff; padding:14px 16px; color:inherit; text-align:left; cursor:pointer; }.plan-card:hover { border-color:#9abdaf; background:#fbfefc; }.plan-top { display:flex; align-items:center; justify-content:space-between; gap:12px; }.plan-top > strong { font-size:17px; }.status-badge { display:inline-flex; align-items:center; border:1px solid; border-radius:999px; padding:5px 10px; font-size:14px; font-weight:800; white-space:nowrap; }.status-draft { border-color:#d4d9dd; background:#f3f4f6; color:#56616b; }.status-pending { border-color:#f1d59a; background:#fff7e5; color:#925a08; }.status-approved { border-color:#b9dfc5; background:#edf8f0; color:#187243; }.status-rejected { border-color:#efc3bf; background:#fff1f0; color:#ad352b; }.plan-meta { display:flex; gap:7px; color:var(--history-muted); font-size:14px; }.plan-meta strong { color:var(--history-ink); font-weight:700; }.skill-caption { margin-top:2px; color:#46574f; font-size:14px; font-weight:800; }.skill-chips,.method-chips { display:flex; flex-wrap:wrap; gap:6px; }.skill-chip,.more-chip,.method-chips span { border:1px solid #d4e1da; border-radius:999px; background:#f3f8f5; padding:4px 9px; color:#3f6251; font-size:14px; }.more-chip { border-color:#cbd8d2; background:#f4f6f5; color:#53615a; font-weight:800; }.behavior-count { color:var(--history-muted); font-size:14px; }
.message-state { display:grid; justify-items:center; gap:8px; min-height:210px; border:1px dashed #c9d8d0; border-radius:10px; background:#fbfdfc; padding:30px; text-align:center; }.message-state strong { font-size:18px; }.message-state span { color:var(--history-muted); font-size:16px; }.message-state button { margin-top:5px; min-height:40px; border:1px solid var(--color-primary,#37866e); border-radius:7px; background:var(--color-primary,#37866e); padding:8px 14px; color:#fff; font:inherit; font-size:15px; font-weight:800; cursor:pointer; }.plan-skeleton { display:grid; grid-template-columns:1fr; gap:12px; min-height:112px; border:1px solid var(--history-line); border-radius:10px; background:#fff; padding:15px; }.plan-skeleton > div { display:grid; gap:8px; }.plan-skeleton i { width:36%; height:13px; border-radius:6px; background:#edf1ef; }.plan-skeleton i:first-child { width:24%; height:17px; }
.drawer-backdrop { position:fixed; z-index:20; inset:0; display:flex; justify-content:flex-end; background:rgba(22,37,30,.48); }.detail-drawer { width:min(720px,100%); height:100%; overflow-y:auto; border-left:1px solid var(--history-line); background:#fff; box-shadow:-12px 0 38px rgba(22,37,30,.15); outline:none; }.drawer-head { position:sticky; z-index:1; top:0; display:flex; justify-content:space-between; gap:14px; border-bottom:1px solid var(--history-line); background:#fff; padding:19px 22px; }.drawer-head small,.drawer-plan-meta small { color:var(--history-muted); font-size:14px; }.drawer-head h2 { margin:3px 0 0; font-size:21px; }.drawer-head p { margin:3px 0 0; color:var(--history-muted); font-size:15px; }.drawer-head button { width:42px; height:42px; border:1px solid var(--history-line); border-radius:8px; background:#fff; color:var(--history-ink); font-size:25px; cursor:pointer; }.drawer-plan-meta { display:grid; grid-template-columns:1fr auto; align-items:center; gap:10px; border-bottom:1px solid var(--history-line); background:#f8fbf9; padding:15px 22px; }.drawer-plan-meta > strong { font-size:17px; }.drawer-plan-meta > div { display:grid; grid-column:1/-1; gap:3px; }.drawer-plan-meta b { font-size:15px; }.drawer-section { padding:19px 22px; border-bottom:1px solid var(--history-line); }.drawer-section > h3 { margin:0 0 13px; font-size:18px; }.skill-detail { border:1px solid var(--history-line); border-radius:9px; padding:14px; }.skill-detail + .skill-detail { margin-top:11px; }.skill-detail > header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }.skill-detail h4 { margin:0; font-size:17px; }.skill-detail > header span { flex:none; border-radius:6px; background:#edf5f1; padding:5px 8px; color:#27674f; font-size:14px; font-weight:800; }.skill-detail h5 { margin:15px 0 5px; color:#4d5d55; font-size:15px; }.behavior-detail { padding:9px 0; }.behavior-detail + .behavior-detail { border-top:1px solid #e8eeeb; }.behavior-detail p { display:grid; gap:3px; margin:0 0 8px; font-size:15px; line-height:1.55; }.behavior-detail p strong { font-size:14px; }.method-chips span { background:#f7f9f8; color:#4e5c55; }.timeline-empty { color:var(--history-muted); font-size:15px; }.approval-timeline { display:grid; gap:0; margin:0; padding:0; list-style:none; }.approval-timeline li { position:relative; display:grid; grid-template-columns:18px minmax(0,1fr); gap:12px; padding:0 0 22px; }.approval-timeline li:not(:last-child)::after { position:absolute; top:15px; bottom:0; left:6px; width:2px; background:#d9e5df; content:''; }.timeline-dot { z-index:1; width:14px; height:14px; margin-top:3px; border:3px solid #6ba889; border-radius:50%; background:#fff; }.timeline-dot.pending { border-color:#d29a36; }.approval-timeline li > div { display:grid; grid-template-columns:1fr auto; gap:5px 10px; }.approval-timeline strong { font-size:16px; }.timeline-status { color:#28734e; font-size:14px; font-weight:800; }.timeline-status:has(+ time) {}.approval-timeline time { grid-column:1/-1; color:var(--history-muted); font-size:14px; }.approval-timeline p { grid-column:1/-1; margin:3px 0 0; border-radius:7px; background:#f6f8f7; padding:9px 11px; color:#4d5b54; font-size:15px; line-height:1.5; }
@media (max-width:700px) { .page-head { flex-direction:column; }.toolbar { align-items:stretch; flex-direction:column; }.drawer-backdrop { display:block; }.detail-drawer { width:100%; }.drawer-head,.drawer-section,.drawer-plan-meta { padding-right:16px; padding-left:16px; }.form-goals { margin-right:16px; margin-left:16px; }.goal-grid,.activity-fields { grid-template-columns:1fr; }.skill-detail > header { flex-direction:column; } }
@media print { .drawer-backdrop { position:static; display:block; background:#fff; }.detail-drawer { width:100%; height:auto; max-height:none; overflow:visible; border:0; box-shadow:none; }.detail-drawer .activity-collapse { grid-template-rows:1fr !important; }.detail-drawer .activity-collapse-inner { overflow:visible; }.detail-drawer .drawer-head button { display:none; } }
.form-skill-row { border-bottom:1px solid var(--history-line); padding:12px 2px; }.form-skill-row > header { display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:12px; }.form-skill-row h4 { margin:0; font-size:16px; }.form-skill-row > header > span { border:1px solid #d4e1da; border-radius:999px; background:#f3f8f5; padding:5px 10px; color:#3f6251; font-size:14px; font-weight:800; white-space:nowrap; }.skill-expand-button { border:0; background:transparent; padding:6px 4px; color:var(--green-deep,#17634d); font:inherit; font-size:14px; font-weight:800; white-space:nowrap; cursor:pointer; }.skill-expand-collapse { display:grid; grid-template-rows:0fr; }.skill-expand-collapse.expanded { grid-template-rows:1fr; }.skill-expand-inner { min-height:0; overflow:hidden; }.skill-expand-inner h5 { margin:13px 0 7px; color:#4d5d55; font-size:15px; }.skill-method-list { display:grid; gap:6px; }.method-open { display:grid; grid-template-columns:minmax(0,1fr) auto auto 20px; align-items:center; gap:9px; width:100%; min-height:46px; border:1px solid #e0e7e3; border-radius:7px; background:#fff; padding:8px 11px; color:var(--history-ink); text-align:left; cursor:pointer; }.method-open:hover { background:#faf7f5; }.method-open strong { font-size:16px; font-weight:600; }.method-type,.method-filled { border:1px solid #d4e1da; border-radius:999px; background:#f3f8f5; padding:4px 8px; color:#456153; font-size:14px; white-space:nowrap; }.method-filled.is-filled { border-color:#b9dfc5; background:#edf8f0; color:#187243; }.method-filled.is-empty { border-color:#e2d9c8; background:#faf7f1; color:#80633a; }.method-arrow { justify-self:end; color:#52645b; font-size:23px; }.no-method { margin:0; color:var(--history-muted); font-size:15px; }.behavior-brief-list { display:grid; gap:7px; margin:0; padding:0; list-style:none; }.behavior-brief-list li { display:grid; gap:2px; border-left:3px solid #d8e5dd; padding:6px 10px; }.behavior-brief-list strong { color:#53635b; font-size:14px; }.behavior-brief-list span { font-size:15px; line-height:1.5; }
.method-modal-backdrop { position:fixed; z-index:40; inset:0; display:grid; place-items:center; background:rgba(22,37,30,.58); padding:2vh 2vw; }.method-modal { display:grid; grid-template-rows:auto minmax(0,1fr) auto; width:min(960px,96vw); max-height:96vh; overflow:hidden; border:1px solid var(--history-line); border-radius:12px; background:#f7f9f8; box-shadow:0 20px 60px rgba(15,30,22,.3); outline:none; }.method-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid var(--history-line); background:#fff; padding:13px 18px; }.method-modal-head h2 { margin:0; font-size:19px; }.method-modal-head button { width:40px; height:40px; border:1px solid var(--history-line); border-radius:8px; background:#fff; color:var(--history-ink); font-size:24px; cursor:pointer; }.method-modal-content { overflow:auto; padding:15px 18px; }.form-title-banner { border:1px solid #efc995; border-radius:9px; background:#fff3e3; padding:14px 16px; }.form-title-banner h3 { margin:0; color:#784b1b; font-size:18px; }.form-data-section { margin-top:12px; border:1px solid var(--history-line); border-radius:9px; background:#fff; padding:14px 16px; }.form-data-section > header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:11px; }.form-data-section > header h3 { margin:0; font-size:16px; }.form-data-section > header span { border-radius:999px; background:#edf5f1; padding:4px 8px; color:#43614f; font-size:14px; }.form-value-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; margin:0; }.form-value-grid > div { min-width:0; }.form-value-grid dt { margin-bottom:4px; color:var(--history-muted); font-size:14px; }.form-value-grid dd { min-height:42px; margin:0; border:1px solid #eee6e2; border-radius:6px; background:#faf7f5; padding:9px 10px; color:var(--history-ink); font-size:16px; line-height:1.45; overflow-wrap:anywhere; white-space:pre-wrap; }.repeatable-form-row { margin-top:11px; border:1px solid #e4e9e6; border-radius:7px; background:#fcfdfc; padding:11px; }.repeatable-form-row h4 { margin:0 0 9px; font-size:15px; }.method-modal-footer { display:flex; justify-content:flex-end; border-top:1px solid var(--history-line); background:#fff; padding:11px 18px; }.method-modal-footer button { min-width:86px; min-height:40px; border:1px solid var(--color-primary,#37866e); border-radius:7px; background:var(--color-primary,#37866e); padding:7px 14px; color:#fff; font:inherit; font-size:15px; font-weight:800; cursor:pointer; }
@media (max-width:700px) { .form-skill-row > header { grid-template-columns:minmax(0,1fr) auto; }.skill-expand-button { grid-column:2; }.method-open { grid-template-columns:minmax(0,1fr) auto 18px; }.method-type { display:none; }.method-modal-backdrop { display:block; padding:0; }.method-modal { width:100%; height:100%; max-height:100%; border-radius:0; }.method-modal-content { padding:12px; }.method-modal-head,.method-modal-footer { padding-right:14px; padding-left:14px; }.form-data-section { padding:12px; }.form-value-grid { grid-template-columns:1fr; } }
@media print { .skill-expand-collapse { grid-template-rows:1fr !important; }.skill-expand-inner { overflow:visible; }.method-modal-backdrop { display:none !important; } }
</style>
