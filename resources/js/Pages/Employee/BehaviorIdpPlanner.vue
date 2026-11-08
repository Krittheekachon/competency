<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import IdpMissingIndicators from './IdpMissingIndicators.vue';

const props = defineProps<{ gaps?: any[]; idp?: any; user?: any; learningMethods?: any[]; idpLearningMethods?: any[]; learningCatalogs?: any[] }>();
const page = usePage();
type Behavior = { key: string; gapId: number; level: number; code: string; description: string };
const gaps = computed(() => (props.gaps || []).filter(gap => gap.requiresIdp && ['approved', 'dean_approved'].includes(gap.status)));
const faculty = computed(() => gaps.value.some(gap => gap.assessmentMethod === 'faculty'));
const selections = ref<Record<string, string[]>>({});
const savedItems = computed(() => props.idp?.items || []);
const legacyActivities = computed(() => savedItems.value.flatMap((item: any) => (item.activities || []).filter((activity: any) => !activity.formDetails?._behaviorPlan)));
const planFor = (gapId: number) => faculty.value ? savedItems.value[0] : savedItems.value.find((item: any) => item.competencyGapId === gapId);
const locked = (gapId: number) => /^(approved|review_step_\d+)$/.test(planFor(gapId)?.status || '');
// One selectable topic per failed competency; keep the existing payload container
// so previously saved indicator selections can be grouped without a data migration.
const behaviors = computed<Behavior[]>(() => gaps.value.map(gap => ({
  key: `${gap.id}:skill`, gapId: gap.id, level: 0, code: gap.cd || '', description: gap.n,
})));
const groups = computed(() => gaps.value.map(gap => ({ gap, rows: behaviors.value.filter(row => row.gapId === gap.id) })));
const steps = computed(() => props.user?.idpReviewerSteps || []);
const readiness = computed(() => page.props.facultyIdpReadiness as any);
const modalRows = ref<Behavior[]>([]);
const modalMethods = ref<string[]>([]);
const methodSearch = ref('');
const methodType = ref<string | null>(null);
const modal = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const error = ref('');
const saving = ref(false);
const submitting = ref(false);
const saved = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let savePromise: Promise<boolean> | null = null;
let revision = 0;
let previousFocus: HTMLElement | null = null;
let previousOverflow = '';
const optionKey = (activity: any) => activity.learningCatalogId ? `catalog:${activity.learningCatalogId}` : `tool:${activity.developmentToolId}`;
const hydrate = () => {
  const next: Record<string, string[]> = {};
  for (const item of savedItems.value) for (const activity of item.activities || []) {
    for (const row of activity.formDetails?.behaviors || []) {
      const key = `${row.gapId}:skill`;
      next[key] = [...new Set([...(next[key] || []), optionKey(activity)])];
    }
  }
  selections.value = next;
};
watch(() => props.idp, hydrate, { immediate: true });
const tools = computed(() => (props.idpLearningMethods || []).filter(tool => tool.isActive !== false && ['experiential', 'social'].includes(tool.focusType)).map(tool => ({
  key: `tool:${tool.id}`, name: tool.title, type: tool.focusType === 'social' ? 'Social' : 'Experiential',
  methodKey: tool.focusType === 'social' ? 'social-learning' : 'experiential-learning', developmentToolId: tool.id, learningCatalogId: null, formCode: tool.formCode || '',
})));
const catalogs = computed(() => (props.learningCatalogs || []).filter(catalog => catalog.isActive !== false).map(catalog => ({
  key: `catalog:${catalog.id}`, name: catalog.name, type: 'Formal', methodKey: 'formal-learning', developmentToolId: null, learningCatalogId: catalog.id, formCode: catalog.formCode || 'form_10_training', catalog,
})));
const options = computed(() => [...tools.value, ...catalogs.value]);
const availableOptions = computed(() => options.value.filter(option => {
  if (!option.learningCatalogId) return true;
  const catalog = (option as any).catalog;
  return modalRows.value.every(row => {
    const gap = gaps.value.find(gap => gap.id === row.gapId);
    return gap && (catalog.competencyIds || []).includes(gap.competencyId)
      && (!(catalog.expectedLevels || []).length || catalog.expectedLevels.includes(Number(gap.expected)));
  });
}));
const methodTypeFilters = computed(() => [...new Set(availableOptions.value.map(option => option.type))].map(type => ({
  type, count: availableOptions.value.filter(option => option.type === type).length,
})));
const hasMethodFilters = computed(() => Boolean(methodSearch.value.trim()) || methodType.value !== null);
const filteredOptions = computed(() => availableOptions.value.filter(option =>
  (methodType.value === null || option.type === methodType.value)
  && option.name.toLocaleLowerCase('th').includes(methodSearch.value.trim().toLocaleLowerCase('th'))));
const methodTypeTone = (type: string) => ({ Experiential: 'tone-experiential', Social: 'tone-social', Formal: 'tone-formal' }[type] || 'tone-other');
const resetMethodFilters = () => { methodSearch.value = ''; methodType.value = null; };
const selectedRows = computed(() => behaviors.value.filter(row => selections.value[row.key]?.length));
const editableRows = computed(() => behaviors.value.filter(row => !locked(row.gapId)));
const summaryFor = (row: Behavior) => {
  const keys = selections.value[row.key] || [];
  const first = options.value.find(option => option.key === keys[0]);
  const stored = savedItems.value.flatMap((item: any) => item.activities || []).find((activity: any) => optionKey(activity) === keys[0]);
  return `${first?.name || stored?.activityName || 'วิธีที่บันทึกไว้'}${keys.length > 1 ? ` +${keys.length - 1}` : ''}`;
};
const closeModal = async () => {
  modalRows.value = [];
  resetMethodFilters();
  document.body.style.overflow = previousOverflow;
  await nextTick();
  previousFocus?.focus();
};
const openMethods = async (rows: Behavior[]) => {
  if (saving.value || submitting.value || !rows.length) return;
  previousFocus = document.activeElement as HTMLElement;
  previousOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';
  modalRows.value = rows;
  modalMethods.value = rows.length === 1 ? [...(selections.value[rows[0].key] || [])] : [];
  resetMethodFilters();
  await nextTick();
  searchInput.value?.focus();
};
const modalKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') { event.preventDefault(); void closeModal(); }
  if (event.key !== 'Tab') return;
  const elements = [...(modal.value?.querySelectorAll<HTMLElement>('button:not(:disabled), input:not(:disabled), [tabindex="0"]') || [])];
  const first = elements[0], last = elements[elements.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
};
const planPayloads = () => {
  const editable = selectedRows.value.filter(row => !locked(row.gapId));
  const legacyGapIds = savedItems.value.filter((item: any) => !locked(item.competencyGapId) && gaps.value.some(gap => gap.id === item.competencyGapId) && (item.activities || []).some((activity: any) => !activity.formDetails?._behaviorPlan)).map((item: any) => item.competencyGapId);
  const anchors = faculty.value ? [savedItems.value[0]?.competencyGapId || gaps.value[0]?.id] : [...new Set([...editable.map(row => row.gapId), ...legacyGapIds])];
  return anchors.filter(Boolean).map(anchor => {
    const rows = editable.filter(row => faculty.value || row.gapId === anchor);
    const keys = [...new Set(rows.flatMap(row => selections.value[row.key] || []))];
    const descriptions = rows.map(row => row.description).join('\n');
    const previous = planFor(anchor);
    const item = {
      competencyGapId: anchor, goal: previous?.goal || (descriptions ? `พัฒนา: ${descriptions}` : ''), successCriteria: previous?.successCriteria || (descriptions ? `พัฒนาทักษะต่อไปนี้ได้:\n${descriptions}` : ''),
      activities: keys.map((key, index) => {
        const option = options.value.find(option => option.key === key);
        const stored = (planFor(anchor)?.activities || []).find((activity: any) => optionKey(activity) === key);
        const related = rows.filter(row => selections.value[row.key]?.includes(key));
        return {
          methodKey: option?.methodKey || stored?.methodKey, developmentToolId: option?.developmentToolId ?? stored?.developmentToolId ?? null,
          learningCatalogId: option?.learningCatalogId ?? stored?.learningCatalogId ?? null, activityName: option?.name || stored?.activityName,
          activityDescription: related.map(row => row.description).join('\n'), weightPercent: Math.floor(100 / keys.length) + (index === 0 ? 100 % keys.length : 0),
          startDate: null, endDate: null, documentReferenceNumber: '', formCode: option?.formCode || stored?.formCode || '',
          formDetails: { _behaviorPlan: true, _saved: true, behaviors: related, detail: { developmentGoal: related.map(row => row.description).join('\n') }, planRows: [{ developmentGoal: related.map(row => row.description).join('\n'), developmentApproach: option?.name || stored?.activityName }] },
        };
      }),
    };
    // Existing activities have no behavior mapping. Preserve their complete saved forms;
    // never assign them to a behavior by guessing or discard them when editing new rows.
    const legacy = (previous?.activities || []).filter((activity: any) => !activity.formDetails?._behaviorPlan);
    item.activities = [...legacy, ...item.activities].map((activity: any, index: number, all: any[]) => ({
      ...activity, weightPercent: Math.floor(100 / all.length) + (index === 0 ? 100 % all.length : 0),
    }));
    return item;
  });
};
// Include empty editable plans when drafting so unchecking the final row removes its activities.
const draftPayload = () => {
  const items = planPayloads();
  const anchors = faculty.value ? [savedItems.value[0]?.competencyGapId || gaps.value[0]?.id] : gaps.value.map(gap => gap.id);
  for (const anchor of anchors) if (anchor && !locked(anchor) && !items.some(item => item.competencyGapId === anchor)) items.push({ competencyGapId: anchor, goal: '', successCriteria: '', activities: [] });
  return { items };
};
const saveDraft = (): Promise<boolean> => {
  if (savePromise) return savePromise;
  const version = revision;
  saving.value = true;
  const payload = draftPayload();
  if (!payload.items.length) { saving.value = false; return Promise.resolve(true); }
  savePromise = (async () => {
    try {
      const response = await fetch(route('employee.idp.draft'), { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify(payload) });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(String(Object.values(body.errors || {}).flat()[0] || body.message || 'บันทึกร่างไม่สำเร็จ'));
      error.value = ''; saved.value = true; return true;
    } catch (failure) { error.value = failure instanceof Error ? failure.message : 'บันทึกร่างไม่สำเร็จ'; return false; }
    finally { saving.value = false; savePromise = null; if (version !== revision) scheduleSave(); }
  })();
  return savePromise;
};
const scheduleSave = () => { clearTimeout(timer); saved.value = false; timer = setTimeout(() => { void saveDraft(); }, 500); };
const changed = () => { revision++; scheduleSave(); };
const confirmMethods = () => {
  if (!modalMethods.value.length) return;
  const next = { ...selections.value };
  for (const row of modalRows.value) next[row.key] = [...modalMethods.value];
  const maxMethods = new Set(Object.values(next).flat()).size + legacyActivities.value.length;
  if (maxMethods > 100) { error.value = 'เลือกวิธีพัฒนาได้ไม่เกิน 100 วิธีต่อแผน'; return; }
  selections.value = next;
  void closeModal(); changed();
};
const toggleBehavior = (row: Behavior) => {
  if (locked(row.gapId) || saving.value || submitting.value || !steps.value.length) return;
  if (selections.value[row.key]?.length) { const next = { ...selections.value }; delete next[row.key]; selections.value = next; changed(); }
  else void openMethods([row]);
};
const blockReason = computed(() => {
  if (readiness.value && !readiness.value.ready) return readiness.value.message;
  if (!steps.value.length) return 'ยังไม่ได้กำหนดผู้อนุมัติ IDP กรุณาติดต่อผู้ดูแลระบบ';
  if (!editableRows.value.length) return 'แผนอยู่ระหว่างอนุมัติหรืออนุมัติแล้ว';
  if (!selectedRows.value.some(row => !locked(row.gapId))) return 'เลือกทักษะและวิธีพัฒนาอย่างน้อย 1 ทักษะ';
  return '';
});
const submit = async () => {
  if (blockReason.value || saving.value || submitting.value) return;
  submitting.value = true; clearTimeout(timer);
  if (!await saveDraft()) { submitting.value = false; return; }
  router.post(route('employee.idp.submit'), { items: planPayloads().filter(item => item.activities.length) }, {
    preserveScroll: true,
    onError: errors => { error.value = String(Object.values(errors).flat()[0] || 'ส่งแผนไม่สำเร็จ'); },
    onFinish: () => { submitting.value = false; },
  });
};
onBeforeUnmount(() => { clearTimeout(timer); if (modalRows.value.length) document.body.style.overflow = previousOverflow; });
</script>

<template>
  <section class="behavior-planner">
    <div v-if="error || (readiness && !readiness.ready) || !steps.length" class="planner-alert" role="alert">{{ error || readiness?.message || 'ยังไม่ได้กำหนดผู้อนุมัติ IDP กรุณาติดต่อผู้ดูแลระบบ' }}</div>
    <header class="planner-person">
      <div class="planner-person-copy"><h1>แผนพัฒนา IDP</h1><div class="planner-person-meta"><strong>{{ props.user?.t }}{{ props.user?.n || '-' }}</strong><span aria-hidden="true">·</span><span>{{ props.user?.p || '-' }}</span><span aria-hidden="true">·</span><span>รหัส {{ props.user?.sso || '-' }}</span><template v-if="props.idp?.year"><span aria-hidden="true">·</span><span>ปีแผน {{ props.idp.year }}</span></template></div></div>
      <nav v-if="steps.length" class="approve-flow" aria-label="ลำดับอนุมัติ IDP"><span>ลำดับอนุมัติ</span><ol><li v-for="(step, index) in steps" :key="step.id"><span v-if="index" class="approval-connector" aria-hidden="true"></span><span class="approver-chip" tabindex="0" :title="`${step.name || step.label || ''}${step.position ? ` · ${step.position}` : ''}`" :aria-label="`ลำดับ ${step.step}: ${step.name || step.label || ''}${step.position ? ` · ${step.position}` : ''}`"><span class="step-dot">{{ step.step }}</span></span></li></ol></nav>
    </header>
    <div class="planner-list-head"><div><h2>เลือกทักษะที่ต้องการพัฒนา</h2><p><span class="hint-icon" aria-hidden="true">ⓘ</span> เลือกทักษะ แล้วกำหนดวิธีพัฒนา</p></div><button type="button" class="btn btn-s" :disabled="!editableRows.length || saving || submitting || !steps.length" @click="openMethods(editableRows)">เลือกทั้งหมด</button></div>
    <details v-if="legacyActivities.length" class="legacy-activities"><summary>กิจกรรมเดิมที่ยังเก็บไว้ {{ legacyActivities.length }} รายการ</summary><ul><li v-for="(activity, index) in legacyActivities" :key="index">{{ activity.activityName }}</li></ul></details>
    <p v-if="!gaps.length" class="planner-empty">ยังไม่มีสมรรถนะที่ต้องจัดทำ IDP</p>
    <section v-for="(group, groupIndex) in groups" :key="group.gap.id" class="behavior-group">
      <div class="skill-body">
      <p v-if="planFor(group.gap.id)?.rejectComment" class="planner-alert">ส่งกลับแก้ไข: {{ planFor(group.gap.id)?.rejectComment }}</p>
      <div v-for="row in group.rows" :key="row.key" class="beh-row" :class="{ 'is-checked': selections[row.key]?.length, 'is-locked': locked(row.gapId) }" @click="toggleBehavior(row)">
        <label class="behavior-select" @click.stop>
          <span class="chk"><input type="checkbox" :checked="Boolean(selections[row.key]?.length)" :disabled="locked(row.gapId) || saving || submitting || !steps.length" :aria-label="row.description" @click.stop @change="toggleBehavior(row)" /><span class="box" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8" /></svg></span></span>
          <span class="behavior-copy">
            <span class="behavior-copy-top"><span class="skill-num">{{ groupIndex + 1 }}</span><span class="beh-text">{{ row.description }}</span></span>
            <span v-if="group.gap.actual != null && group.gap.expected != null" class="behavior-meta">ผลประเมินระดับ {{ group.gap.actual }} / คาดหวังระดับ {{ group.gap.expected }}</span>
          </span>
        </label>
        <button v-if="selections[row.key]?.length && !locked(row.gapId)" class="btn-edit" type="button" :disabled="saving || submitting" :aria-label="`แก้ไขวิธีพัฒนา: ${row.description}`" @click.stop="openMethods([row])"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15z" /></svg>แก้ไข</button>
        <span v-else-if="!locked(row.gapId)" class="beh-arrow" aria-hidden="true">›</span>
      </div>
      <IdpMissingIndicators :groups="group.gap.missingIndicators || []" />
      <p v-if="!group.rows.length" class="planner-empty">ไม่มีทักษะที่ต้องพัฒนาในผลประเมินนี้</p>
      </div>
    </section>
    <footer class="planner-sticky"><div class="sticky-summary"><div class="selection-progress" role="progressbar" aria-label="ทักษะที่กำหนดวิธีพัฒนาแล้ว" :aria-valuenow="selectedRows.length" :aria-valuemin="0" :aria-valuemax="behaviors.length || 1"><span :style="{ width: `${behaviors.length ? selectedRows.length / behaviors.length * 100 : 0}%` }"></span></div><span>เลือก <strong>{{ selectedRows.length }}/{{ behaviors.length }}</strong> ทักษะ · กำหนดวิธีแล้ว {{ selectedRows.length }} ทักษะ</span><small v-if="saving">กำลังบันทึก</small><small v-else-if="saved">บันทึกร่างแล้ว</small></div><div class="sticky-submit"><small v-if="blockReason && steps.length && (!readiness || readiness.ready)">{{ blockReason }}</small><button type="button" class="btn btn-p" :disabled="Boolean(blockReason) || saving || submitting" @click="submit">{{ submitting ? 'กำลังส่ง…' : 'ส่งแผนให้ผู้อนุมัติ' }}</button></div></footer>
    <Teleport to="body">
      <div v-if="modalRows.length" class="behavior-modal-backdrop" @click.self="closeModal" @keydown="modalKeydown">
        <section ref="modal" class="behavior-modal" role="dialog" aria-modal="true" aria-labelledby="behavior-method-title">
          <header><div><h2 id="behavior-method-title">เลือกวิธีพัฒนา</h2><p>{{ modalRows.length === 1 ? modalRows[0].description : `ใช้วิธีเดียวกันกับทักษะที่เลือก ${modalRows.length} ทักษะ` }}</p></div><button type="button" aria-label="ปิด" @click="closeModal">×</button></header>
          <div class="method-search">
            <div class="method-search-box">
              <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></svg>
              <input ref="searchInput" v-model="methodSearch" placeholder="ค้นหาเครื่องมือ / แนวทาง / หลักสูตร" aria-label="ค้นหาวิธีพัฒนา" />
              <button v-if="methodSearch" type="button" class="search-clear" aria-label="ล้างคำค้นหา" @click="methodSearch = ''; searchInput?.focus()">×</button>
            </div>
            <div class="filter-chips" role="group" aria-label="กรองตามประเภทวิธีพัฒนา">
              <button type="button" class="fchip" :class="{ 'is-active': methodType === null }" :aria-pressed="methodType === null" @click="methodType = null">ทั้งหมด <span class="count">{{ availableOptions.length }}</span></button>
              <button v-for="filter in methodTypeFilters" :key="filter.type" type="button" class="fchip" :class="[methodTypeTone(filter.type), { 'is-active': methodType === filter.type }]" :aria-pressed="methodType === filter.type" @click="methodType = filter.type"><span class="filter-dot" aria-hidden="true"></span>{{ filter.type }} <span class="count">{{ filter.count }}</span></button>
            </div>
            <p v-if="hasMethodFilters" class="result-note" role="status" aria-live="polite">แสดง {{ filteredOptions.length }} จาก {{ availableOptions.length }} รายการ</p>
          </div>
          <div class="behavior-modal-body">
            <label v-for="option in filteredOptions" :key="option.key" class="method-row" :class="{ 'is-checked': modalMethods.includes(option.key) }">
              <span class="chk"><input v-model="modalMethods" type="checkbox" :value="option.key" /><span class="box" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8" /></svg></span></span>
              <span class="method-name">{{ option.name }}</span><small class="planner-chip method-type" :class="methodTypeTone(option.type)">{{ option.type }}</small>
            </label>
            <div v-if="!filteredOptions.length" class="method-empty"><p>ไม่พบวิธีที่ตรงกับเงื่อนไข</p><button type="button" @click="resetMethodFilters">ล้างตัวกรอง</button></div>
          </div>
          <footer><button type="button" class="btn btn-s" @click="closeModal">ยกเลิก</button><button type="button" class="btn btn-p" :disabled="!modalMethods.length" @click="confirmMethods">ยืนยัน (เลือก {{ modalMethods.length }})</button></footer>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.behavior-planner { --planner-line: var(--color-border, #dde2e6); width: 100%; max-width: 1200px; box-sizing: border-box; margin-inline: auto; font-size: 16px; color: var(--color-text); display: grid; gap: 20px; min-width: 0; padding-bottom: 24px; }
.planner-person { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; border-bottom: 1px solid var(--planner-line); padding: 4px 0 20px; }
.planner-person-copy { min-width: 0; }.planner-person h1 { margin: 0 0 10px; font-size: 24px; font-weight: 700; line-height: 1.4; }
.planner-person-meta { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 10px; color: #555d66; font-size: 14px; }.planner-person-meta strong { color: var(--color-text); font-size: 16px; font-weight: 600; }
.planner-alert { padding: 12px 16px; background: #fff5eb; color: #9a3412; border: 1px solid #fed7aa; border-radius: 10px; font-size: 14px; }
.planner-list-head, .behavior-group > header { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }.planner-list-head { justify-content: space-between; }.planner-list-head p { margin: 6px 0 0; font-size: 14px; color: #555d66; }
h2, h3 { margin: 0; font-size: 18px; font-weight: 600; }
.behavior-group { min-width: 0; overflow: hidden; border: 1px solid var(--planner-line); border-radius: 12px; background: #fff; }
.behavior-group > header { justify-content: space-between; padding: 14px 18px; background: #faf7f5; border-bottom: 1px solid var(--planner-line); }
.behavior-group > header h3 { flex: 1; font-size: 17px; font-weight: 600; line-height: 1.5; }
.skill-title { min-width: 0; flex: 1; }.skill-title h3 { overflow-wrap: anywhere; }.skill-title p { margin: 5px 0 0; color: #555d66; font-size: 14px; line-height: 1.5; }
.skill-head-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }.skill-counter { padding: 4px 10px; border-radius: 999px; background: #fff; border: 1px solid var(--planner-line); color: #555d66; font-size: 14px; white-space: nowrap; }
.beh-row { display: flex; align-items: flex-start; gap: 12px; min-height: 52px; padding: 16px 18px; border-top: 1px solid var(--planner-line); background: #fff; cursor: pointer; transition: background .15s ease; }
.beh-row:first-of-type { border-top: 0; }.beh-row:hover { background: #fffaf8; }.beh-row.is-checked { background: var(--color-primary-soft); }.beh-row.is-locked { cursor: default; }
.beh-row input, .method-row input { width: 22px; height: 22px; flex: 0 0 22px; accent-color: var(--color-primary); cursor: pointer; }.beh-row > input { margin: 5px 0 0; }.beh-text { flex: 0 1 auto; min-width: 0; font-size: 16px; line-height: 1.6; overflow-wrap: anywhere; }
.behavior-copy { flex: 1; min-width: 0; }.behavior-copy-top { display: flex; align-items: flex-start; gap: 16px; }.behavior-copy-top .choose-method { flex: 0 0 auto; white-space: nowrap; border-color: var(--color-primary-border); color: var(--color-primary); background: #fff; }.behavior-copy-top .choose-method:hover { background: var(--color-primary-soft); border-color: var(--color-primary); }
.behavior-meta { margin-top: 5px; color: #626b77; font-size: 14px; line-height: 1.5; }.method-caption { color: #555d66; font-size: 14px; }
.planner-chip { display: inline-flex; max-width: 100%; box-sizing: border-box; padding: 3px 10px; border-radius: 999px; background: #f3f4f6; color: #4b5563; font-size: 14px; line-height: 1.5; overflow-wrap: anywhere; }.behavior-methods { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--color-primary-border); }
.behavior-methods button { min-height: 40px; border: 0; background: transparent; color: var(--color-primary); text-decoration: underline; font-size: 14px; cursor: pointer; }
.approve-flow { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; color: #555d66; font-size: 14px; }.approve-flow ol { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 0; margin: 0; list-style: none; }.approve-flow li { display: flex; align-items: center; gap: 6px; }.approval-connector { color: #68717d; }.step-dot { display: inline-grid; place-items: center; width: 32px; height: 32px; border: 1px solid #e0e5ea; border-radius: 50%; background: #eef1f4; color: #4b5563; font-size: 14px; font-weight: 600; cursor: help; }.step-dot:hover, .step-dot:focus-visible { border-color: var(--color-primary-border); background: var(--color-primary-soft); outline: 2px solid var(--color-primary); outline-offset: 2px; }
.approver-chip { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding-right: 6px; font-size: 14px; overflow-wrap: anywhere; }.approver-chip:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 3px; border-radius: 20px; }.approver-chip .step-dot { flex: 0 0 32px; }
.approve-flow, .approve-flow ol, .approve-flow li { min-width: 0; max-width: 100%; }.planner-person-meta { overflow-wrap: anywhere; }.method-row > span { min-width: 0; overflow-wrap: anywhere; }
.planner-sticky { position: sticky; bottom: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; width: 100%; box-sizing: border-box; padding: 12px 24px; border-top: 1px solid var(--planner-line); background: #fff; box-shadow: 0 -4px 12px rgb(0 0 0 / .05); font-size: 14px; }.planner-sticky small { color: #555d66; font-size: 14px; }
.sticky-summary { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 14px; }.sticky-submit { display: flex; align-items: center; justify-content: flex-end; gap: 14px; }.sticky-submit > small { max-width: 280px; line-height: 1.5; }.sticky-submit .btn { flex-shrink: 0; }
.selection-progress { width: 120px; height: 6px; overflow: hidden; border-radius: 999px; background: #eceff3; }.selection-progress > span { display: block; height: 100%; background: var(--color-primary); transition: width .25s ease; }
.planner-sticky .btn-p:disabled { border-color: #e5e7eb; background: #e5e7eb; color: #59616d; opacity: 1; }
.behavior-planner .btn, .behavior-modal .btn { min-height: 40px; font-size: 14px; }.planner-empty { margin: 0; padding: 20px; color: #555d66; font-size: 15px; }
.behavior-modal-backdrop { position: fixed; inset: 0; display: grid; place-items: center; padding: 16px; z-index: 1400; background: rgb(0 0 0 / .4); }
.behavior-modal { width: min(640px, 94vw); max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; border-radius: 14px; background: #fff; }.behavior-modal > header { display: flex; gap: 16px; justify-content: space-between; padding: 20px; }.behavior-modal header p { margin: 8px 0 0; color: #555d66; font-size: 15px; line-height: 1.6; }.behavior-modal header button { align-self: flex-start; flex: 0 0 40px; height: 40px; border: 0; background: transparent; font-size: 24px; cursor: pointer; }
.method-search { padding: 0 20px 12px; }.behavior-modal-body { overflow: auto; min-height: 0; padding: 0 20px 8px; }.method-row { display: flex; align-items: center; gap: 12px; min-height: 48px; padding: 12px 8px; border-bottom: 1px solid #dde2e6; font-size: 16px; cursor: pointer; }.method-row > span { flex: 1; }.method-row:hover { background: #fafafa; }.behavior-modal > footer { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid #dde2e6; }
input:focus-visible, button:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 3px; }button:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 768px) { .behavior-planner { padding-bottom: 24px; }.planner-person { align-items: flex-start; }.approve-flow { width: 100%; }.approve-flow ol { min-width: 0; }.approve-flow li { min-width: 0; max-width: 100%; }.behavior-group > header { align-items: flex-start; }.skill-title { flex-basis: 100%; }.beh-row { gap: 10px; padding: 14px 12px; }.behavior-copy-top { flex-direction: column; gap: 10px; }.behavior-copy-top .choose-method { align-self: flex-start; max-width: 100%; white-space: normal; }.behavior-methods { margin-top: 10px; }.behavior-modal-backdrop { padding: 0; align-items: end; }.behavior-modal { width: 100%; max-width: 100vw; max-height: 90vh; border-radius: 14px 14px 0 0; }.planner-sticky { position: static; align-items: stretch; padding: 12px 16px; }.planner-sticky > div { width: 100%; }.sticky-submit { flex-wrap: wrap; justify-content: flex-start; }.sticky-submit > small { max-width: none; }.planner-sticky .btn { width: 100%; }.selection-progress { width: 100px; } }
.behavior-planner { --planner-line: #ece8e3; max-width: 1100px; padding: 24px 20px 120px; background: #f6f4f1; border-radius: 16px; }
.planner-person { border-bottom-color: #e5dfd8; }.planner-list-head p { color: #62605d; }.hint-icon { color: var(--color-primary); }
.approve-flow ol, .approve-flow li { gap: 0; }.approval-connector { display: block; width: 18px; height: 1px; margin-inline: 4px; background: #d8d1c9; }.approver-chip { padding: 0; }.step-dot { background: #fff; border-color: #e5dfd8; color: #625d58; box-shadow: 0 1px 3px rgb(0 0 0 / .04); }
.behavior-group { border: 1px solid #ece8e3; border-left: 4px solid var(--color-primary); border-radius: 14px; box-shadow: 0 1px 3px rgb(0 0 0 / .05); }
.behavior-group > header { justify-content: flex-start; gap: 14px; padding: 16px 20px; background: #fffaf6; border-bottom-color: #f0eae4; }.skill-title h3 { font-size: 18px !important; font-weight: 600; }
.skill-num { display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border-radius: 50%; background: var(--color-primary); color: #fff; font-size: 16px; font-weight: 700; }
.skill-title .skill-level { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; color: #625d58; font-size: 14px; }.level-steps { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 4px; }.level-steps i { width: 8px; height: 8px; border: 1px solid #a79c91; border-radius: 50%; background: transparent; }.level-steps i.filled { border-color: var(--color-primary); background: var(--color-primary); }
.skill-head-meta { margin-left: auto; }.skill-counter { background: #fff; border-color: #e5dfd8; }.skill-progress { display: inline-block; width: 60px; height: 5px; overflow: hidden; border-radius: 999px; background: #e9e3dd; }.skill-progress > span { display: block; height: 100%; background: var(--color-primary); transition: width .15s ease; }
.skill-body { display: grid; gap: 10px; padding: 12px 20px 16px; }.beh-row { align-items: center; min-width: 0; gap: 14px; padding: 14px 16px; border: 1px solid #ece8e3; border-radius: 10px; background: #fff; transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease; }.beh-row:first-of-type { border-top: 1px solid #ece8e3; }
.beh-row:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgb(0 0 0 / .07); border-color: var(--color-primary-border); }.beh-row.is-checked { background: var(--color-primary-soft); border-color: var(--color-primary); }.beh-row.is-checked:first-of-type { border-top-color: var(--color-primary); }.beh-row > input { margin: 0; }.beh-row.is-checked > input { animation: behavior-check-pop .15s ease-out; }
@keyframes behavior-check-pop { from { transform: scale(.9); } to { transform: scale(1); } }
.behavior-meta { margin-top: 8px; }.behavior-meta .planner-chip { background: #f6f3f0; border: 1px solid #eee7df; color: #625d58; font-size: 14px; }
.behavior-methods { flex: 0 1 250px; justify-content: flex-end; align-self: center; margin: 0 0 0 auto; padding: 0; border: 0; }.behavior-methods .planner-chip { background: #fff; border: 1px solid var(--color-primary-border); color: var(--color-primary-hover); }.beh-arrow { flex: 0 0 auto; margin-left: auto; color: #968a7f; font-size: 28px; line-height: 1; transition: color .15s ease, transform .15s ease; }.beh-row:hover .beh-arrow { color: var(--color-primary); transform: translateX(2px); }
.planner-empty, .legacy-activities { padding: 16px 20px; border: 1px solid #ece8e3; border-radius: 12px; background: #fff; color: #625d58; }.skill-body .planner-empty { margin: 0; background: #fbf9f7; }
.planner-sticky { bottom: 16px; width: auto; margin: 0 16px; padding: 14px 22px; border: 1px solid #ece8e3; border-radius: 14px; box-shadow: 0 8px 24px rgb(0 0 0 / .12); }.sticky-summary { flex: 1 1 320px; }.sticky-submit { flex: 1 1 300px; }.sticky-submit > small { max-width: 220px; }.selection-progress { width: 100px; background: #ede7e1; }
@media (max-width: 768px) {
  .behavior-planner { padding: 16px 12px 120px; border-radius: 12px; }.behavior-group > header { padding: 14px; }.skill-title { flex: 1 1 calc(100% - 48px); }.skill-head-meta { margin-left: 48px; }.skill-body { padding: 10px 12px 14px; }.beh-row { align-items: flex-start; flex-wrap: wrap; gap: 10px; padding: 12px; }.beh-row > input { margin-top: 3px; }.behavior-copy { flex-basis: calc(100% - 60px); }.behavior-methods { flex: 1 1 100%; justify-content: flex-start; margin-left: 32px; }.planner-sticky { position: sticky; bottom: 8px; margin: 0 8px; padding: 12px 14px; flex-wrap: wrap; }.sticky-summary, .sticky-submit { flex-basis: 100%; }.sticky-submit > small { max-width: none; }.sticky-submit .btn { min-height: 44px; }.approve-flow { width: auto; }
}
.behavior-select { display: flex; align-items: center; align-self: stretch; flex: 1; min-width: 0; gap: 14px; cursor: inherit; }
.behavior-select .behavior-copy, .behavior-select .behavior-meta { display: block; }
.chk { position: relative; flex: 0 0 24px; width: 24px; height: 24px; }
.beh-row .chk input, .method-row .chk input { position: absolute; inset: 0; z-index: 1; width: 24px; height: 24px; margin: 0; opacity: 0; cursor: pointer; }
.chk .box { display: grid; place-items: center; width: 24px; height: 24px; box-sizing: border-box; border: 2px solid #cfc7bd; border-radius: 50%; background: #fff; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
.chk .box svg { width: 14px; height: 14px; stroke: #fff; stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 20; stroke-dashoffset: 20; transition: stroke-dashoffset .2s ease .05s; }
.beh-row:hover .chk .box, .method-row:hover .chk .box { border-color: var(--color-primary); }
.chk input:checked + .box { background: var(--color-primary); border-color: var(--color-primary); animation: behavior-round-pop .15s ease; }
.chk input:checked + .box svg { stroke-dashoffset: 0; }
.chk input:focus-visible + .box { outline: 2px solid var(--color-primary); outline-offset: 2px; }
.chk input:disabled { cursor: not-allowed; }.chk input:disabled + .box { opacity: .6; }
@keyframes behavior-round-pop { from { transform: scale(.85); } to { transform: scale(1); } }
.beh-row .btn-edit { margin-left: auto; flex: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; padding: 6px 14px; font-size: 14px; color: var(--color-primary); background: #fff; border: 1px solid var(--color-primary); border-radius: 8px; text-decoration: none; cursor: pointer; transition: background .15s ease; }
.beh-row .btn-edit:hover { background: var(--color-primary-soft); }
@media (max-width: 768px) { .beh-row { flex-wrap: nowrap; }.behavior-select { gap: 10px; }.behavior-select .behavior-copy { flex-basis: auto; }.beh-row .btn-edit { min-width: 80px; min-height: 40px; padding: 6px 10px; } }
.behavior-modal > header, .behavior-modal > .method-search, .behavior-modal > footer { flex: 0 0 auto; }
.behavior-modal-body { flex: 1 1 auto; overscroll-behavior: contain; }
.method-search-box { position: relative; }.method-search-box input { display: block; box-sizing: border-box; width: 100%; min-height: 46px; padding: 12px 40px; border: 1px solid #e5dfd8; border-radius: 10px; background: #fff; color: var(--color-text); font: inherit; font-size: 16px; outline: none; transition: border-color .15s ease, box-shadow .15s ease; }.method-search-box input::placeholder { color: #756b61; font-size: 14px; }.method-search-box input:focus, .method-search-box input:focus-visible { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-soft); outline: none; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #8a7e72; pointer-events: none; }.method-search-box .search-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: grid; place-items: center; width: 32px; height: 32px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: #625d58; cursor: pointer; font-size: 22px; }.search-clear:hover { background: #f3f0ec; }
.filter-chips { display: flex; flex-wrap: nowrap; gap: 8px; margin: 12px 0 4px; padding: 3px 3px 5px; overflow-x: auto; scrollbar-width: none; }.filter-chips::-webkit-scrollbar { display: none; }
.fchip { display: inline-flex; align-items: center; gap: 6px; flex: none; min-height: 36px; padding: 6px 14px; border: 1px solid #e5dfd8; border-radius: 999px; background: #fff; color: #3b3b3b; font-size: 14px; white-space: nowrap; cursor: pointer; transition: background .15s ease, border-color .15s ease, color .15s ease; }.fchip:hover { border-color: var(--color-primary); }.fchip.is-active { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }.fchip .count { font-size: 13px; opacity: .8; }
.tone-experiential { --type-ink: #9a3412; --type-soft: #fff1e6; }.tone-social { --type-ink: #5b21b6; --type-soft: #f1eafe; }.tone-formal { --type-ink: #155e75; --type-soft: #e6f4f8; }.tone-other { --type-ink: #4b5563; --type-soft: #f3f4f6; }
.filter-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--type-ink); }.fchip.is-active .filter-dot { background: #fff; }.method-type { background: var(--type-soft); color: var(--type-ink); flex: 0 0 auto; }
.result-note { margin: 6px 0 0; font-size: 14px; line-height: 1.5; color: #625d58; }.method-row { gap: 12px; padding: 12px 10px; border-radius: 8px; }.method-row.is-checked { background: var(--color-primary-soft); }.method-row > .chk { flex: 0 0 24px; }.method-row .method-name { min-width: 0; flex: 1; overflow-wrap: anywhere; }
.method-empty { padding: 32px 16px; text-align: center; color: #625d58; font-size: 15px; }.method-empty p { margin: 0; }.method-empty button { min-height: 40px; margin-top: 8px; padding: 6px 12px; border: 0; border-radius: 8px; background: transparent; color: var(--color-primary); font-size: 14px; cursor: pointer; }.method-empty button:hover { background: var(--color-primary-soft); }
.behavior-modal > footer .btn-p:disabled { background: #e5e7eb; border-color: #e5e7eb; color: #667080; opacity: 1; }
@media (max-width: 768px) { .filter-chips { margin-inline: -3px; }.method-row { gap: 10px; }.method-type { font-size: 13px; padding: 3px 8px; } }
.skill-body { padding: 0; gap: 0; }.behavior-group .beh-row { padding: 18px 20px; border: 0; border-radius: 10px; }.behavior-group .beh-row:hover { transform: none; box-shadow: none; }.behavior-copy-top { align-items: center; }.behavior-copy-top .skill-num { flex: 0 0 30px; width: 30px; height: 30px; font-size: 14px; }.behavior-copy .beh-text { font-weight: 600; }.behavior-meta { padding-left: 44px; font-size: 14px; }
.behavior-modal { font-size: 14px; }.behavior-modal h2 { font-size: 16px; }.behavior-modal :is(p, input, label, .method-name, .btn) { font-size: 14px; }
@media (max-width: 768px) { .behavior-group .beh-row { padding: 14px 12px; }.behavior-meta { padding-left: 0; } }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
</style>
