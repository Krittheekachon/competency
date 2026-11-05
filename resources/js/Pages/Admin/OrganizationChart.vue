<script setup lang="ts">
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import OrganizationChartBranch from './OrganizationChartBranch.vue';

const props = defineProps<{data: {nodes: any[]; people: any[]}}>();
const nodes = computed(() => props.data?.nodes || []);
const roots = computed(() => nodes.value.filter(n => !n.parentKey || !nodes.value.some(p => p.key === n.parentKey)));
const collapsed = ref(new Set<string>());
const zoom = ref(1);
const viewport = ref<HTMLElement>();
const tree = ref<HTMLElement>();
const dimensions = ref({width: 0, height: 0});
const selectedKey = ref('');
const activeKey = ref('');
const panel = ref<HTMLElement>();
let returnFocus: HTMLElement | null = null;
const treeWidth = ref(1000);
let resizeObserver: ResizeObserver | undefined;
const selected = computed(() => nodes.value.find(n => n.key === selectedKey.value));
const search = ref('');
const people = computed(() => (props.data?.people || []).filter(p => `${p.name} ${p.position || ''}`.toLowerCase().includes(search.value.toLowerCase())));
const form = useForm({node_key: '', head_user_id: '' as string | number, use_profile_members: true, member_ids: [] as number[]});
const headSearch = ref('');
const selectedHead = computed(() => (props.data?.people || []).find(person => String(person.id) === String(form.head_user_id)));
const headResults = computed(() => {
  const query = headSearch.value.trim().toLocaleLowerCase();
  return query ? (props.data?.people || []).filter(person => `${person.name} ${person.position || ''}`.toLocaleLowerCase().includes(query)) : [];
});
function chooseHead(id: number | '') {
  form.head_user_id = id;
  headSearch.value = '';
  form.clearErrors('head_user_id');
}
const edit = useForm({name: ''});
const add = useForm({name: ''});
const showAdd = ref(false);
const deleteConfirm = ref(false);
const labels: Record<string, string> = {workline: 'สายงาน', family: 'ภาควิชา/หน่วยงาน', department: 'ฝ่าย', work: 'งาน', unit: 'หน่วย'};
const resources: Record<string, string> = {workline: 'worklines', family: 'job-families', department: 'support-departments', work: 'support-works', unit: 'support-units'};
const childType = computed(() => !selected.value ? 'workline' : selected.value.type === 'workline' ? (['สายสนับสนุน', 'สายงานสนับสนุน'].includes(selected.value.name) ? 'department' : 'family') : ({department: 'work', work: 'unit'}[selected.value.type] || ''));

async function measure() {
  await nextTick();
  dimensions.value = {width: tree.value?.scrollWidth || 0, height: tree.value?.scrollHeight || 0};
}
async function fit() {
  await measure();
  zoom.value = Math.max(.01, Math.min(1,
    ((viewport.value?.clientWidth || 900) - 32) / Math.max(1, dimensions.value.width),
    ((viewport.value?.clientHeight || 600) - 32) / Math.max(1, dimensions.value.height),
  ));
  if (viewport.value) { viewport.value.scrollLeft = 0; viewport.value.scrollTop = 0; }
}
async function reveal(key: string) {
  await measure();
  const target = Array.from(tree.value?.querySelectorAll<HTMLElement>('[data-node-key]') || []).find(el => el.dataset.nodeKey === key);
  if (!target || !viewport.value) return;
  const bounds = target.getBoundingClientRect();
  const view = viewport.value.getBoundingClientRect();
  if (bounds.left < view.left || bounds.right > view.right) viewport.value.scrollLeft += bounds.left - view.left - 20;
  if (bounds.top < view.top || bounds.bottom > view.bottom) viewport.value.scrollTop += bounds.top - view.top - 20;
}
async function toggle(key: string) {
  const next = new Set(collapsed.value); next.has(key) ? next.delete(key) : next.add(key); collapsed.value = next;
  activeKey.value = key;
  await reveal(key);
}
function open(key: string) {
  returnFocus = document.activeElement as HTMLElement;
  activeKey.value = key;
  selectedKey.value = key; search.value = ''; headSearch.value = ''; showAdd.value = false; deleteConfirm.value = false;
  const node = selected.value;
  form.reset(); form.clearErrors(); edit.clearErrors(); add.clearErrors();
  form.node_key = key; form.head_user_id = node.head?.id || ''; form.use_profile_members = node.membershipSource === 'profile'; form.member_ids = node.members.map(p => p.id);
  edit.name = node.name; add.name = '';
}
function close() {
  selectedKey.value = ''; showAdd.value = false;
  nextTick(() => returnFocus?.isConnected && returnFocus.focus({preventScroll: true}));
}
function addRoot() {
  returnFocus = document.activeElement as HTMLElement;
  selectedKey.value = ''; showAdd.value = true; add.reset(); add.clearErrors();
}
function panelKeys(event: KeyboardEvent) {
  if (event.key === 'Escape') { event.preventDefault(); close(); }
  if (event.key !== 'Tab') return;
  const controls = Array.from(panel.value?.querySelectorAll<HTMLElement>('button:not(:disabled), input:not(:disabled), select:not(:disabled), summary, [tabindex="0"]') || []).filter(el => el.getClientRects().length);
  const first = controls[0], last = controls[controls.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
async function resetView() {
  const rootKeys = new Set(roots.value.map(node => node.key));
  collapsed.value = new Set(nodes.value.filter(node => !rootKeys.has(node.key)).map(node => node.key));
  zoom.value = 1; activeKey.value = '';
  await measure();
  if (viewport.value) { viewport.value.scrollLeft = 0; viewport.value.scrollTop = 0; }
}
async function expandAll() { collapsed.value = new Set(); await reveal(activeKey.value || roots.value[0]?.key); }
async function foldAll() { collapsed.value = new Set(nodes.value.map(node => node.key)); await reveal(roots.value[0]?.key); }
function context(node: any) {
  const path: Record<string, string> = {};
  while (node) { path[node.type] = node.name; node = nodes.value.find(p => p.key === node.parentKey); }
  return {workline_name: path.workline, division_name: path.department, work_name: path.work};
}
function savePeople() {
  form.transform(data => ({...data, head_user_id: data.head_user_id || null})).put(route('admin.organization-chart.people.update'), {preserveScroll: true});
}
function rename() {
  edit.transform(data => ({...context(selected.value), old_name: selected.value.name, name: data.name})).put(route(`admin.structure.${resources[selected.value.type]}.update`), {preserveScroll: true});
}
function create() {
  add.transform(data => ({...context(selected.value), name: data.name})).post(route(`admin.structure.${resources[childType.value]}.store`), {preserveScroll: true, onSuccess: () => {showAdd.value = false; add.reset();}});
}
function remove() {
  edit.transform(() => ({...context(selected.value), name: selected.value.name})).delete(route(`admin.structure.${resources[selected.value.type]}.destroy`), {preserveScroll: true, onSuccess: () => {selectedKey.value = '';}});
}
onMounted(async () => {
  resizeObserver = new ResizeObserver(() => { treeWidth.value = Math.max(260, (viewport.value?.clientWidth || 1000) - 32); measure(); });
  if (viewport.value) resizeObserver.observe(viewport.value);
  treeWidth.value = Math.max(260, (viewport.value?.clientWidth || 1000) - 32);
  await resetView();
});
onBeforeUnmount(() => resizeObserver?.disconnect());
watch([nodes, collapsed], measure);
watch(viewport, (current, previous) => {
  if (previous) resizeObserver?.unobserve(previous);
  if (current) resizeObserver?.observe(current);
});
watch(() => Boolean(selected.value || showAdd.value), async visible => {
  if (visible) { await nextTick(); panel.value?.querySelector<HTMLElement>('button')?.focus(); }
});
</script>

<template>
  <section class="org-chart">
    <div class="org-toolbar">
      <div><h3>แผนผังโครงสร้างองค์กร</h3><p>{{ nodes.length }} หน่วยงาน · ขยายแต่ละกิ่งเพื่อดูงานและหน่วย</p></div>
      <div class="org-tools">
        <button class="btn btn-s" aria-label="ย่อแผนผัง" @click="zoom = Math.max(.01, zoom - .1)">−</button>
        <span>{{ Math.round(zoom * 100) }}%</span>
        <button class="btn btn-s" aria-label="ขยายแผนผัง" @click="zoom = Math.min(2, zoom + .1)">+</button>
        <button class="btn btn-s" @click="fit" title="ย่อหรือขยายเพื่อดูภาพรวมของกิ่งที่เปิดอยู่">พอดีหน้าจอ</button>
        <button class="btn btn-s" @click="resetView" title="ขนาดอ่านจริง 100% เปิดเฉพาะระดับบน">มุมมองเริ่มต้น</button>
        <button class="btn btn-s" @click="expandAll">ขยายทุกกิ่ง</button>
        <button class="btn btn-s" @click="foldAll">พับทุกกิ่ง</button>
        <button class="btn btn-p" @click="addRoot">+ เพิ่มสายงาน</button>
      </div>
    </div>
    <p class="org-notice">หัวหน้ามาจากการกำหนดในแผนผังเท่านั้น · สมาชิกเริ่มจากข้อมูลสังกัดเดิม หรือกำหนดเฉพาะแผนผังได้ · ไม่เปลี่ยนสายประเมินและสายอนุมัติ IDP</p>
    <div v-if="!nodes.length" class="org-empty">ยังไม่มีข้อมูลโครงสร้างองค์กร เริ่มด้วยการเพิ่มสายงาน</div>
    <div v-else ref="viewport" class="org-viewport" tabindex="0" aria-label="แผนผังองค์กร เลื่อนแนวนอนและแนวตั้งเพื่อดูหน่วยงาน">
      <div :style="{width: `${dimensions.width * zoom + 32}px`, height: `${dimensions.height * zoom + 32}px`, minWidth: '100%'}">
        <div ref="tree" class="org-tree" :style="{transform: `scale(${zoom})`, width: `${treeWidth}px`}">
          <div class="org-root">โครงสร้างองค์กร</div>
          <ul class="org-children org-roots"><OrganizationChartBranch v-for="node in roots" :key="node.key" :node="node" :nodes="nodes" :collapsed="collapsed" :selected-key="activeKey" @select="open" @toggle="toggle" /></ul>
        </div>
      </div>
    </div>

    <div v-if="selected || showAdd" class="org-backdrop" @click.self="close">
      <section ref="panel" class="org-dialog" role="dialog" aria-modal="true" aria-labelledby="org-dialog-title" @keydown="panelKeys">
        <div class="org-dialog-head"><div><span class="org-panel-caption">รายละเอียดและการจัดการ</span><h3 id="org-dialog-title">{{ selected?.name || 'เพิ่มสายงาน' }}</h3></div><button class="btn btn-s" aria-label="ปิดรายละเอียด" @click="close">ปิด ×</button></div>
        <template v-if="selected">
          <p>หัวหน้า: <strong>{{ selected.head?.name || 'ยังไม่ได้กำหนด' }}</strong></p>
          <details open><summary>บุคลากร {{ selected.members.length }} คน ({{ selected.membershipSource === 'profile' ? 'ตามข้อมูลสังกัด' : 'กำหนดในแผนผัง' }})</summary>
            <p v-if="!selected.members.length" class="muted">ยังไม่มีบุคลากรในหน่วยงานนี้</p>
            <ul class="org-people"><li v-for="person in selected.members" :key="person.id"><strong>{{ person.name }}</strong><span>{{ person.position || 'ยังไม่ระบุตำแหน่ง' }}{{ person.active ? '' : ' · ระงับใช้งาน' }}</span></li></ul>
          </details>
          <form class="org-editor" @submit.prevent="savePeople">
            <label for="organization-head">กำหนดหัวหน้า</label>
            <div v-if="selectedHead" class="org-head-selection">
              <div><strong>{{ selectedHead.name }}</strong><span>{{ selectedHead.position || 'ยังไม่ระบุตำแหน่ง' }}{{ selectedHead.active ? '' : ' · ระงับใช้งาน' }}</span></div>
              <button type="button" class="btn btn-s" :disabled="form.processing" aria-label="ยกเลิกหัวหน้าที่เลือก" @click="chooseHead('')">ยกเลิก</button>
            </div>
            <p v-else class="muted org-head-hint">ยังไม่ได้กำหนดหัวหน้า</p>
            <input id="organization-head" v-model="headSearch" type="search" class="inp" placeholder="ค้นหาชื่อหรือตำแหน่งหัวหน้า" autocomplete="off" :disabled="form.processing" aria-describedby="organization-head-help" @keydown.enter.prevent>
            <p id="organization-head-help" class="muted org-head-hint">พิมพ์ชื่อหรือตำแหน่ง แล้วกดเลือกบุคลากรจากผลค้นหา</p>
            <ul v-if="headSearch.trim() && headResults.length" class="org-head-results" aria-label="ผลค้นหาหัวหน้า">
              <li v-for="person in headResults" :key="person.id">
                <button type="button" :disabled="form.processing" :aria-pressed="String(form.head_user_id) === String(person.id)" @click="chooseHead(person.id)">
                  <strong>{{ person.name }}</strong><span>{{ person.position || 'ยังไม่ระบุตำแหน่ง' }}{{ person.active ? '' : ' · ระงับใช้งาน' }}</span>
                </button>
              </li>
            </ul>
            <p v-else-if="headSearch.trim()" class="muted org-head-hint" role="status">ไม่พบบุคลากรที่ตรงกับคำค้น</p>
            <p v-if="form.errors.head_user_id" class="org-error">{{ form.errors.head_user_id }}</p>
            <label><input v-model="form.use_profile_members" type="checkbox"> ใช้สมาชิกตามข้อมูลสังกัดเดิม</label>
            <template v-if="!form.use_profile_members">
              <p class="muted">กำหนดเฉพาะแผนผังนี้ ไม่ย้ายสังกัดในโปรไฟล์และไม่เปลี่ยนสายประเมิน</p>
              <input v-model="search" class="inp" placeholder="ค้นหาชื่อ / ตำแหน่ง" aria-label="ค้นหาบุคลากร">
              <div class="org-member-picker"><label v-for="person in people" :key="person.id"><input v-model="form.member_ids" :value="person.id" type="checkbox"> {{ person.name }}</label></div>
              <span>เลือก {{ form.member_ids.length }} คน</span>
            </template>
            <p v-for="(error, field) in form.errors" :key="field" class="org-error">{{ error }}</p>
            <button class="btn btn-p" :disabled="form.processing">บันทึกหัวหน้าและสมาชิก</button>
          </form>
          <form class="org-editor" @submit.prevent="rename"><label>ชื่อหน่วยงาน<input v-model="edit.name" class="inp" required maxlength="255"></label><p v-for="(error, field) in edit.errors" :key="field" class="org-error">{{ error }}</p><button class="btn btn-s" :disabled="edit.processing">บันทึกชื่อ</button></form>
          <div class="org-tools"><button v-if="childType" class="btn btn-s" @click="showAdd = !showAdd">+ เพิ่ม{{ labels[childType] }}</button><button class="btn btn-s org-delete" @click="deleteConfirm = !deleteConfirm">ลบหน่วยงาน</button></div>
          <div v-if="deleteConfirm" class="org-editor"><p>ยืนยันลบ “{{ selected.name }}”? ระบบจะไม่อนุญาตหากมีหน่วยงานย่อย หัวหน้า สมาชิก หรือข้อมูลอ้างอิง</p><button class="btn btn-s org-delete" :disabled="edit.processing" @click="remove">ยืนยันลบ</button></div>
        </template>
        <form v-if="showAdd" class="org-editor" @submit.prevent="create"><label>ชื่อ{{ labels[childType] }}<input v-model="add.name" class="inp" required maxlength="255"></label><p v-for="(error, field) in add.errors" :key="field" class="org-error">{{ error }}</p><button class="btn btn-p" :disabled="add.processing">เพิ่ม{{ labels[childType] }}</button></form>
      </section>
    </div>
  </section>
</template>

<style src="./organization-chart.css"></style>
