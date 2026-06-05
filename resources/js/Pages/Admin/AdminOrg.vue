<template><div class="flex ic jb mb20"><div><div class="sec-t">จัดการโครงสร้างองค์กร </div><div class="sec-s">
กำหนดกลุ่มงานและสายการบังคับบัญชา ·
{{ viewModel === 'dept' ? 'มุมมองรายกลุ่มงาน' : 'มุมมองสายงานบังคับบัญชา' }}
 </div></div><div class="flex g8"><button
class="btn btn-s"
:class="{ 'btn-p': viewModel === 'dept' }"
style="border-radius: 12px"
@click="setDeptView"
>
รายกลุ่มงาน
 </button><button
class="btn btn-s"
:class="{ 'btn-p': viewModel === 'hierarchy' }"
style="border-radius: 12px"
@click="setHierarchyView"
>
สายการบังคับบัญชา
 </button></div></div><div v-if="viewModel === 'dept'" class="org-layout"><div class="org-side"><div class="p12 org-search-box"><input
v-model="deptSearch"
class="inp inp-sm org-search-input"
placeholder=" ค้นหาสังกัด/กลุ่มงาน..."
/></div><div class="org-side-h flex ic jb"><span>กลุ่มงาน</span><div class="filter-wrap"><button
class="btn btn-xs filter-btn"
:class="filterType !== ALL_WORKLINES ? 'btn-p' : 'btn-s'"
@click="showFilter = !showFilter"
>
{{ filterType }}
 </button><div v-if="showFilter" class="filter-menu"><div
v-for="workline in filterOptions"
:key="workline"
class="filter-opt"
:class="{ active: filterType === workline }"
@click="selectFilter(workline)"
>
{{ workline }}
 </div></div><div v-if="showFilter" class="filter-backdrop" @click="showFilter = false" /></div></div><div class="org-side-list"><div
v-for="dept in filteredDepts"
:key="dept"
class="org-side-item"
:class="{ active: selectedDept === dept && !isSearchActive }"
@click="selectDept(dept)"
><span class="dept-n">{{ dept }}</span><span class="dept-c">{{ deptUserCount(dept) }}</span></div><div v-if="filteredDepts.length === 0" class="empty-side"><div class="empty-icon-sm"></div>
ไม่พบข้อมูลในหมวดนี้
 </div></div></div><div class="org-main"><div class="card mb16 p12 flex ic g12"><div class="muted fs16"></div><input
v-model="search"
class="inp org-main-search"
placeholder="ค้นหาชื่อบุคลากร (ค้นหาข้ามกลุ่มงานได้ที่นี่)..."
/><button v-if="search" class="btn btn-s btn-xs" @click="search = ''">ล้าง</button></div><div v-if="selectedDept || isSearchActive" class="card org-transparent-card"><div class="flex ic jb mb12"><div class="fw8 fs16 org-heading">
{{ isSearchActive ? 'ผลการค้นหา' : selectedDept }}
 <span class="muted fw4 fs13 ml8">({{ listUsers.length }} คน)</span></div></div><div class="card overflow-hidden"><div class="table-scroll"><table class="tbl tbl-clean tbl-org"><thead><tr><th>บุคลากร</th><th>สายงาน</th><th style="min-width: 200px">กลุ่มงาน / สังกัด</th><th>ตำแหน่ง</th><th>ผู้ประเมินคนที่ 1 (หัวหน้างาน)</th><th>ผู้ประเมินคนที่ 2 (ผู้บังคับบัญชา)</th><th>บทบาท</th><th style="width: 130px"></th></tr></thead><tbody><tr v-for="(user, index) in listUsers" :key="user.sso || index"><td style="min-width: 200px"><div class="flex ic g10"><div class="av s36 user-avatar">{{ avatarInitial(user) }}</div><div class="flex col"><span class="fw7 fs14 user-name">{{ user.t }}{{ user.n }}</span></div></div></td><td><span class="b workline-badge" :class="user.w === 'สายวิชาการ' ? 'bb' : 'bg'">
{{ user.w }}
 </span></td><td><div
class="fs12 fw6 text-gray-700 whitespace-nowrap overflow-hidden truncate dept-cell"
:title="user.d || ''"
>
{{ formatDept(user.d) }}
 </div></td><td style="min-width: 150px; max-width: 180px"><div
class="fw6 fs13 whitespace-nowrap overflow-hidden truncate position-cell"
:title="user.p || ''"
>
{{ user.p || '—' }}
 </div><div
class="muted fs11 whitespace-nowrap overflow-hidden truncate level-cell"
:title="getDisplayLevel(user) || ''"
>
{{ getDisplayLevel(user) || '—' }}
 </div></td><td style="min-width: 140px"><div v-if="user.sup" class="flex ic g6"><span class="fs11 evaluator-icon"></span><span class="fs12 evaluator-name">{{ user.sup }}</span></div><span v-else class="fs11 muted">—</span></td><td style="min-width: 140px"><div v-if="user.evaluator2" class="flex ic g6"><span class="fs11 evaluator-icon"></span><span class="fs12 evaluator-name">{{ user.evaluator2 }}</span></div><span v-else class="fs11 muted">—</span></td><td><span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
{{ roleBadge(user.r).label }}
 </span></td><td><button class="btn btn-s btn-xs w-full edit-user-btn" @click="openModal('modal-org', user)">
แก้ไขกลุ่มงาน
 </button></td></tr></tbody></table></div></div><div v-if="listUsers.length === 0" class="empty-results"><div class="empty-icon"></div>
{{ isSearchActive ? 'ไม่พบบุคลากรที่ค้นหา' : 'กลุ่มงานนี้ยังไม่มีบุคลากร' }}
 </div></div><div v-else class="card no-dept-card"><div class="no-dept-content"><div class="no-dept-icon"></div><div class="fw7 fs16 mb4 no-dept-title">ยังไม่ได้เลือกกลุ่มงาน</div><div class="muted fs13 no-dept-text">
กรุณาเลือกกลุ่มงานจากรายการด้านซ้ายมือ หรือค้นหาชื่อบุคลากรด้านบน
 </div></div></div></div></div><div v-else class="anim-fade-in"><div class="card shadow-sm overflow-hidden hierarchy-card"><div class="p32 hierarchy-header"><div class="flex ic g12 mb16 wrap"><div
class="breadcrumb-item"
:class="{ active: drillPath.length === 0 }"
@click="popDrillPath(-1)"
><span class="breadcrumb-icon"></span> คณะวิศวกรรมศาสตร์
 </div><template v-for="(item, index) in drillPath" :key="`${item.sso || item.n}-${index}`"><div class="breadcrumb-separator">›</div><div
class="breadcrumb-item"
:class="{ active: index === drillPath.length - 1 }"
@click="popDrillPath(index)"
>
{{ item.n }}
 </div></template></div><div class="flex ic jb"><div class="fs13 fw5 hierarchy-context">
{{
drillPath.length === 0
? ' ระดับผู้บริหารคณะ'
: ` รายชื่อผู้ที่มีหัวหน้างานเป็น: ${drillPath[drillPath.length - 1].n}`
}}
 </div></div></div><div class="hierarchy-body"><div class="table-scroll"><table class="tbl tbl-clean tbl-org tbl-explorer"><thead><tr><th style="padding-left: 32px; width: 40%">ชื่อ-นามสกุล / สังกัด</th><th>ตำแหน่งสายงานหลัก</th><th>บทบาท / ผู้ประเมิน</th><th style="width: 140px; text-align: right; padding-right: 32px"></th></tr></thead><tbody><tr
v-for="(user, index) in currentHierarchyUsers"
:key="user.sso || index"
:class="{ 'row-drill': hasSubordinates(user) }"
@click="pushDrillPath(user)"
><td style="padding-left: 32px; padding-top: 24px; padding-bottom: 24px"><div class="flex ic g12"><div class="av s36 hierarchy-avatar">{{ avatarInitial(user) }}</div><div class="flex col"><div class="flex ic g6"><span class="fw8 fs16 hierarchy-name">{{ user.t }}{{ user.n }}</span><span
v-if="user.d"
class="fs13 fw5 text-gray-400 whitespace-nowrap overflow-hidden truncate hierarchy-dept"
:title="user.d"
>
({{ formatDept(user.d) }})
 </span></div></div></div></td><td style="padding-top: 24px; padding-bottom: 24px; max-width: 200px"><div
class="fw7 fs14 whitespace-nowrap overflow-hidden truncate hierarchy-position"
:title="user.p || ''"
>
{{ user.p || '—' }}
 </div><div
class="muted fs12 whitespace-nowrap overflow-hidden truncate hierarchy-level"
:title="getDisplayLevel(user) || ''"
>
{{ getDisplayLevel(user) || '—' }}
 </div></td><td style="padding-top: 24px; padding-bottom: 24px"><div class="flex ic g10"><span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
{{ roleBadge(user.r).label }}
 </span><template v-if="hasSubordinates(user)"><span class="badge-sub">ผู้รับการประเมิน {{ subordinateCount(user) }} คน</span><span class="drill-arrow">›</span></template></div><div class="muted fs12 evaluator-summary"><span class="fw6">คนที่ 1 (หัวหน้างาน):</span> {{ user.sup || '—' }}
 <span v-if="user.evaluator2">
· <span class="fw6">คนที่ 2 (ผู้บังคับบัญชา):</span> {{ user.evaluator2 }}
 </span></div></td><td
style="padding-right: 40px; text-align: right; padding-top: 24px; padding-bottom: 24px"
@click.stop
><button class="btn-settings" title="แก้ไขสายการบังคับบัญชา" @click="openModal('modal-org', user)">⚙</button></td></tr></tbody></table></div><div v-if="currentHierarchyUsers.length === 0" class="flex col ic jc empty-hierarchy"><div class="empty-hierarchy-icon"></div><div class="fw8 fs18 mb6 empty-hierarchy-title">ยังไม่มีผู้รับการประเมินถัดไป</div><div class="muted fs14 empty-hierarchy-text">
ไม่พบผู้ที่มีหัวหน้างานเป็น {{ drillPath[drillPath.length - 1]?.n }}
 </div><button class="btn btn-s mt24" @click="popDrillPath(drillPath.length - 2)">ย้อนกลับหนึ่งระดับ</button></div></div></div></div></template><script setup lang="ts">
import { computed, ref, watch } from 'vue';

type User = {
sso?: string;
t?: string;
n: string;
w?: string;
d?: string;
p?: string;
l?: string;
r?: string;
sup?: string;
evaluator2?: string;
act?: boolean;
};

type RoleBadge = {
label: string;
className: string;
style?: Record<string, string>;
};

const props = defineProps<{
openModal: (type: string, data?: unknown) => void;
users: User[];
setUsers?: unknown;
academicDepts: string[];
supportDepts: string[];
worklines: string[];
}>();

const ALL_WORKLINES = 'ทั้งหมด';

const viewModel = ref<'dept' | 'hierarchy'>('dept');
const showFilter = ref(false);
const filterType = ref(props.worklines[0] || 'สายวิชาการ');
const search = ref('');
const deptSearch = ref('');
const selectedDept = ref('');
const drillPath = ref<User[]>([]);

const filterOptions = computed(() => [ALL_WORKLINES, ...props.worklines]);
const isSearchActive = computed(() => search.value.trim().length > 0);

const getTopDept = (user: User) => {
if (user.w === 'สายสนับสนุน' && user.d?.includes(' > ')) {
return user.d.split(' > ')[0];
}

return user.d || '';
};

const filteredDepts = computed(() => {
return Array.from(
new Set([
...props.academicDepts,
...props.supportDepts,
...props.users.map((user) => getTopDept(user)),
].filter(Boolean)),
)
.sort((a, b) => a.localeCompare(b, 'th'))
.filter((dept) => {
if (deptSearch.value && !dept.toLowerCase().includes(deptSearch.value.toLowerCase())) {
return false;
}

if (filterType.value === ALL_WORKLINES) {
return true;
}

const hasUserOfWorkline = props.users.some((user) => getTopDept(user) === dept && user.w === filterType.value);

if (filterType.value === 'สายวิชาการ') {
return props.academicDepts.includes(dept) || hasUserOfWorkline;
}

if (filterType.value === 'สายสนับสนุน') {
return props.supportDepts.includes(dept) || hasUserOfWorkline;
}

return hasUserOfWorkline;
});
});

const listUsers = computed(() => {
if (isSearchActive.value) {
const keyword = search.value.toLowerCase();

return props.users.filter((user) => {
return user.n.toLowerCase().includes(keyword) || user.sso?.toLowerCase().includes(keyword);
});
}

return props.users.filter((user) => {
const matchesDept = getTopDept(user) === selectedDept.value;
const matchesWorkline = filterType.value === ALL_WORKLINES || user.w === filterType.value;

return matchesDept && matchesWorkline;
});
});

const currentHierarchyUsers = computed(() => {
if (drillPath.value.length === 0) {
return props.users.filter((user) => !user.sup);
}

const leader = drillPath.value[drillPath.value.length - 1];
return props.users.filter((user) => user.sup === leader.n);
});

watch(
filteredDepts,
(depts) => {
if (selectedDept.value && depts.includes(selectedDept.value)) {
return;
}

selectedDept.value = depts[0] || '';
},
{ immediate: true },
);

const openModal = (type: string, data?: unknown) => props.openModal(type, data);

const setDeptView = () => {
viewModel.value = 'dept';
};

const setHierarchyView = () => {
viewModel.value = 'hierarchy';
drillPath.value = [];
};

const selectFilter = (workline: string) => {
filterType.value = workline;
showFilter.value = false;
};

const selectDept = (dept: string) => {
selectedDept.value = dept;
search.value = '';
};

const getDisplayLevel = (user: User) => (['สายบริหาร', 'สายงานบริหาร'].includes(user.w || '') ? user.p : user.l);

const deptUserCount = (dept: string) => {
return props.users.filter((user) => {
const matchesDept = getTopDept(user) === dept;
const matchesWorkline = filterType.value === ALL_WORKLINES || user.w === filterType.value;

return matchesDept && matchesWorkline;
}).length;
};

const formatDept = (dept?: string) => (dept ? dept.split(' > ').join(' > ') : '—');

const avatarInitial = (user: User) => user.n?.[0] || '?';

const normalizeRole = (role?: string) => {
if (role === 'manager') return 'dean';
if (role === 'dept_head') return 'manager_dept';
return role || 'employee';
};

const roleBadge = (role?: string): RoleBadge => {
switch (normalizeRole(role)) {
case 'admin':
return { label: 'ผู้ดูแลระบบ', className: 'bp' };
case 'hr':
return { label: 'งานทรัพยากรบุคคล', className: 'bb' };
case 'dean':
return {
label: 'ผู้บริหารคณะ',
className: 'bg',
style: { background: '#e0f2fe', color: '#0369a1' },
};
case 'manager_dept':
return {
label: 'ผู้บังคับบัญชา',
className: 'bg',
style: { background: '#f0f9ff', color: '#0284c7' },
};
case 'supervisor':
return {
label: 'หัวหน้างาน',
className: 'bg',
style: { background: '#fff7ed', color: '#c2410c' },
};
default:
return { label: 'บุคลากร', className: 'bgr' };
}
};

const hasSubordinates = (user: User) => props.users.some((subordinate) => subordinate.sup === user.n);
const subordinateCount = (user: User) => props.users.filter((subordinate) => subordinate.sup === user.n).length;

const popDrillPath = (index: number) => {
drillPath.value = index === -1 ? [] : drillPath.value.slice(0, index + 1);
};

const pushDrillPath = (user: User) => {
if (hasSubordinates(user)) {
drillPath.value = [...drillPath.value, user];
}
};
</script><style scoped>
.org-layout {
display: grid;
grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
gap: 24px;
align-items: start;
width: 100%;
max-width: 100%;
}

.org-side {
background: #fff;
border: 1px solid var(--border);
border-radius: 16px;
overflow: visible;
position: sticky;
top: 20px;
}

.org-side > :first-child {
border-radius: 16px 16px 0 0;
}

.org-side-list {
border-radius: 0 0 16px 16px;
max-height: calc(100vh - 200px);
overflow-y: auto;
}

.org-side-h {
padding: 16px;
background: #f8fafc;
border-bottom: 1px solid var(--border);
font-weight: 800;
font-size: 13px;
color: var(--navy);
}

.org-side-item {
padding: 12px 16px;
border-bottom: 1px solid #f1f5f9;
cursor: pointer;
display: flex;
align-items: center;
justify-content: space-between;
transition: 0.1s;
}

.org-side-item:hover {
background: #f8fafc;
}

.org-side-item.active {
background: var(--blue-lt);
border-right: 3px solid var(--blue);
}

.dept-n {
font-size: 13px;
font-weight: 600;
color: var(--text2);
}

.org-side-item.active .dept-n {
color: var(--blue);
font-weight: 800;
}

.dept-c {
font-size: 10px;
background: #f1f5f9;
padding: 2px 6px;
border-radius: 6px;
color: var(--text3);
font-weight: 700;
}

.org-main {
min-width: 0;
max-width: 100%;
}

.org-search-box {
border-bottom: 1px solid var(--border);
background: #fff;
}

.org-search-input {
font-size: 12px;
}

.filter-wrap {
position: relative;
}

.filter-btn {
font-size: 10px;
padding: 4px 8px;
}

.filter-menu {
position: absolute;
top: 100%;
right: 0;
z-index: 50;
background: #fff;
border: 1px solid var(--border);
border-radius: 12px;
margin-top: 8px;
box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
width: 240px;
padding: 6px;
max-height: 300px;
overflow-y: auto;
}

.filter-opt {
padding: 10px 12px;
font-size: 13px;
cursor: pointer;
border-radius: 8px;
color: var(--text2);
font-weight: 400;
}

.filter-opt.active {
background: var(--blue-lt);
color: var(--blue);
font-weight: 700;
}

.filter-backdrop {
position: fixed;
inset: 0;
z-index: 45;
}

.org-main-search {
border: none;
background: transparent;
padding: 4px 0;
font-size: 14px;
}

.org-transparent-card {
border: none;
box-shadow: none;
background: transparent;
}

.org-heading,
.user-name,
.no-dept-title,
.hierarchy-name,
.empty-hierarchy-title {
color: var(--navy);
}

.table-scroll {
overflow-x: auto;
}

.user-avatar {
background: var(--navy);
color: #fff;
}

.workline-badge {
font-size: 11px;
}

.dept-cell {
max-width: 300px;
}

.position-cell,
.evaluator-name,
.hierarchy-position {
color: var(--text2);
}

.level-cell,
.hierarchy-level {
margin-top: 2px;
}

.evaluator-icon {
opacity: 0.6;
}

.edit-user-btn {
padding: 6px;
font-size: 11px;
border-radius: 6px;
}

.empty-side {
padding: 32px 16px;
text-align: center;
font-size: 12px;
color: var(--text3);
}

.empty-icon-sm {
font-size: 24px;
margin-bottom: 8px;
}

.empty-results {
padding: 60px;
text-align: center;
color: var(--text3);
}

.empty-icon {
font-size: 32px;
margin-bottom: 12px;
}

.no-dept-card {
display: grid;
min-height: 400px;
place-items: center;
padding: 32px;
background: #fff;
border-style: dashed;
border-width: 2px;
overflow: hidden;
text-align: center;
}

.no-dept-content {
display: grid;
justify-items: center;
max-width: min(520px, 100%);
min-width: 0;
}

.no-dept-icon {
font-size: 48px;
margin-bottom: 16px;
line-height: 1;
}

.no-dept-text {
max-width: 44ch;
line-height: 1.65;
overflow-wrap: anywhere;
}

.hierarchy-card {
border-radius: 20px;
}

.hierarchy-header {
background: #fff;
border-bottom: 1px solid #edf2f7;
}

.hierarchy-context {
color: var(--text3);
}

.hierarchy-body {
min-height: 520px;
background: #fff;
}

.breadcrumb-item {
cursor: pointer;
display: flex;
align-items: center;
gap: 6px;
font-weight: 700;
font-size: 15px;
color: var(--text3);
border-radius: 6px;
transition: 0.2s;
padding: 4px 8px;
margin-left: -8px;
}

.breadcrumb-item:hover {
background: #f1f5f9;
color: var(--blue);
}

.breadcrumb-item.active {
color: var(--navy);
}

.breadcrumb-icon {
font-size: 20px;
}

.breadcrumb-separator {
color: #cbd5e1;
font-weight: 200;
font-size: 18px;
margin: 0 2px;
}

.tbl-explorer th {
font-size: 11px;
text-transform: uppercase;
color: var(--text3);
letter-spacing: 0.1em;
padding: 16px 12px;
background: #fcfcfc;
border-bottom: 1px solid #edf2f7;
}

.tbl-explorer td {
padding: 16px 12px;
border-bottom: 1px solid #f8fafc;
vertical-align: middle;
}

.row-drill {
cursor: pointer;
transition: 0.15s;
}

.row-drill:hover td {
background: #fcfdfe !important;
}

.hierarchy-avatar {
background: var(--navy);
color: #fff;
border: none;
font-size: 14px;
font-weight: 700;
border-radius: 50%;
flex-shrink: 0;
}

.hierarchy-name {
white-space: nowrap;
}

.hierarchy-dept {
max-width: 240px;
}

.hierarchy-position {
line-height: 1.3;
}

.badge-sub {
font-size: 10px;
font-weight: 800;
background: #eff6ff;
color: var(--blue);
padding: 3px 10px;
border-radius: 20px;
transition: 0.2s;
}

.drill-arrow {
font-size: 16px;
color: #cbd5e1;
transition: 0.2s;
}

.row-drill:hover .drill-arrow {
color: var(--blue);
transform: translateX(3px);
}

.row-drill:hover .badge-sub {
background: var(--blue);
color: #fff;
}

.evaluator-summary {
margin-top: 8px;
}

.btn-settings {
width: 32px;
height: 32px;
border-radius: 8px;
border: 1px solid #e2e8f0;
background: #fff;
color: var(--text3);
font-size: 14px;
transition: 0.15s;
cursor: pointer;
}

.btn-settings:hover {
border-color: var(--blue);
background: var(--blue-lt);
color: var(--blue);
}

.empty-hierarchy {
padding: 120px 20px;
color: var(--text3);
}

.empty-hierarchy-icon {
font-size: 56px;
margin-bottom: 20px;
}

.empty-hierarchy-text {
max-width: 300px;
text-align: center;
line-height: 1.6;
}

.overflow-hidden {
overflow: hidden;
}

.ml8 {
margin-left: 8px;
}

.p32 {
padding: 32px;
}
</style>
