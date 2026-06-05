<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ManagerGap, ManagerIDP } from '../ManagerPages.vue';
import EmployeeAssess from '../Staff/EmployeeAssess.vue';
import EmployeeGap from '../Staff/EmployeeGap.vue';
import EmployeeIDP from '../Staff/EmployeeIDP.vue';
import EmployeeProgress from '../Staff/EmployeeProgress.vue';
import EmployeeIDPDetail from '../Staff/EmployeeIDPDetail.vue';

const props = defineProps({
hrSummary: {
type: Object,
required: true,
},
hrWorklines: {
type: Array,
default: () => [],
},
activeCycleName: {
type: String,
default: '',
},
hrCycles: {
type: Array,
default: () => [],
},
hrExpectationSets: {
type: Array,
default: () => [],
},
hrCatalogItems: {
type: Array,
default: () => [],
},
competencyTypes: {
type: Array,
default: () => [],
},
competencies: {
type: Array,
default: () => [],
},
learningMethods: {
type: Array,
default: () => [],
},
overviewUsers: {
type: Array,
default: () => [],
},
});

const logout = () => router.post(route('logout'));

const isSidebarOpen = ref(true);

const sections = [
{
title: 'การประเมินตนเอง',
items: [
{ id: 'emp-assess', icon: '', label: 'ประเมินตนเอง' },
{ id: 'emp-gap', icon: '', label: 'สรุปผลสมรรถนะ' },
{ id: 'emp-idp', icon: '', label: 'แผนพัฒนา IDP' },
{ id: 'emp-progress', icon: '', label: 'ความก้าวหน้า' },
{ id: 'emp-idp-detail', icon: '', label: 'รายละเอียด IDP' },
],
},
{
title: 'HR',
items: [
{ id: 'hr-position-competencies', icon: '', label: 'กำหนดสมรรถนะประจำตำแหน่ง' },
{ id: 'hr-cycle', icon: '', label: 'รอบการประเมิน' },
{ id: 'hr-template', icon: '', label: 'กำหนดความคาดหวัง' },
{ id: 'hr-catalog', icon: '', label: 'Learning Catalog' },
],
},
{
title: 'ภาพรวมคณะ',
items: [
{ id: 'hr-comp-overview', icon: '', label: 'ภาพรวม Competency คณะ' },
{ id: 'hr-idp-overview', icon: '', label: 'ภาพรวม IDP คณะ' },
],
},
];

const pageTitles = {
'hr-position-competencies': 'กำหนดสมรรถนะประจำตำแหน่ง',
'hr-cycle': 'รอบการประเมิน',
'hr-template': 'กำหนดความคาดหวัง',
'hr-catalog': 'Learning Catalog',
'hr-comp-overview': 'ภาพรวม Competency คณะ',
'hr-idp-overview': 'ภาพรวม IDP คณะ',
};

const emptyStates = {
'hr-position-competencies': {
title: 'ยังไม่มีข้อมูลสมรรถนะประจำตำแหน่ง',
body: 'เมื่อเชื่อมข้อมูลตำแหน่งและสมรรถนะจริงแล้ว รายการกำหนดสมรรถนะจะแสดงในหน้านี้',
},
'hr-cycle': {
title: 'ยังไม่มีรอบการประเมินในฐานข้อมูล',
body: 'เพิ่มตารางและ API สำหรับรอบการประเมินก่อน หน้านี้จึงจะแสดงรอบที่เปิดใช้งานจริง',
},
'hr-template': {
title: 'ยังไม่มีข้อมูลความคาดหวัง',
body: 'พื้นที่นี้จะใช้กำหนดระดับคาดหวังเมื่อมีข้อมูล competency และตำแหน่งจากระบบจริง',
},
'hr-catalog': {
title: 'ยังไม่มี Learning Catalog',
body: 'เมื่อมีตารางกิจกรรมพัฒนาแล้ว รายการหลักสูตรและกิจกรรมจะถูกดึงจากฐานข้อมูลมาแสดง',
},
'hr-comp-overview': {
title: 'ยังไม่มีผล Competency สำหรับภาพรวม',
body: 'รอผลการประเมินจริงก่อน หน้านี้จึงจะแสดงสถานะรายหน่วยงานและความเสี่ยงของคณะ',
},
'hr-idp-overview': {
title: 'ยังไม่มีข้อมูล IDP สำหรับภาพรวม',
body: 'รอแผน IDP และสถานะความคืบหน้าจริงก่อน หน้านี้จึงจะแสดงภาพรวมของคณะ',
},
};

const activePage = ref('hr-position-competencies');
const activeModal = ref('');
const page = usePage();
const currentPageTitle = computed(() => pageTitles[activePage.value]);
const currentEmptyState = computed(() => emptyStates[activePage.value]);
const userInitial = computed(() => page.props.auth.user.name?.[0] || 'ง');
const cycleBadge = computed(() => props.activeCycleName || 'ยังไม่มีรอบประเมิน');
const selectedWorkline = ref('');
const selectedJobFamily = ref('');
const selectedPosition = ref('');
const competencyQuery = ref('');
const competencyTypeFilter = ref('all');
const worklines = computed(() => props.hrWorklines);
const hasWorklines = computed(() => worklines.value.length > 0);
const hasCycles = computed(() => props.hrCycles.length > 0);
const hasExpectationSets = computed(() => props.hrExpectationSets.length > 0);
const hasCatalogItems = computed(() => props.hrCatalogItems.length > 0);
const competencyTypeOptions = computed(() => props.competencyTypes.map((type) => ({
code: typeof type === 'string' ? type : (type.code || type.name || ''),
label: typeof type === 'string'
? type
: (type.fullName || type.label ? `${type.code || type.name} - ${type.fullName || type.label}` : (type.code || type.name || '')),
})).filter((type) => type.code));
const competencyTypeCodes = computed(() => competencyTypeOptions.value.map((type) => type.code));
const getCompType = (competency) => competency.t || competency.type || competency.typeCode || String(competency.cd || '').split('-')[0] || '';
const getCompTag = (competency) => `tag-${String(getCompType(competency)).toLowerCase()}`;
const filteredCompetencies = computed(() => props.competencies.filter((competency) => {
const compType = getCompType(competency);
const query = competencyQuery.value.trim().toLowerCase();
const text = `${competency.cd || ''} ${competency.n || ''} ${competency.det || ''}`.toLowerCase();
const matchesType = competencyTypeFilter.value === 'all' || compType === competencyTypeFilter.value;
const matchesQuery = !query || text.includes(query);
return matchesType && matchesQuery;
}));
const coreTypeCode = computed(() => competencyTypeCodes.value.includes('CC') ? 'CC' : competencyTypeCodes.value[0] || 'CC');
const coreCompetencies = computed(() => props.competencies.filter((competency) => getCompType(competency) === coreTypeCode.value));
const learningMethodSummaries = computed(() => props.learningMethods.map((method) => {
const key = method.key || method.code || method.label;
const count = props.hrCatalogItems.filter((item) =>
item.methodKey === key || item.method_type_key === key || item.method === key || item.t === key
).length;
return {
key,
label: method.label || method.name || key,
desc: method.desc || method.description || '',
count,
};
}));

const positionLabel = computed(() => selectedPosition.value || 'ยังไม่มีข้อมูลตำแหน่ง');
const jobFamilyLabel = computed(() => {
if (!selectedWorkline.value) return 'ยังไม่มีข้อมูลกลุ่มงาน';
if (selectedWorkline.value === 'สายวิชาการ') return 'สายวิชาการ';
if (selectedWorkline.value === 'สายงานบริหาร') return 'คณะวิศวกรรมศาสตร์';
return selectedJobFamily.value || 'ยังไม่มีข้อมูลกลุ่มงาน';
});

const openModal = (modal) => {
activeModal.value = modal;
};

const closeModal = () => {
activeModal.value = '';
};
</script><template><div class="shell"><aside v-if="isSidebarOpen" class="sidebar"><div class="sb-logo"><div class="sb-mark">คณะวิศวกรรมศาสตร์</div><div class="sb-name">Competency &<br />IDP Management</div></div><button class="sb-user" type="button"><div class="av" style="background: var(--blue)">{{ userInitial }}</div><div style="min-width: 0"><div class="u-name">{{ $page.props.auth.user.name }}</div><div class="u-role">งานทรัพยากรบุคคล</div></div></button><nav class="sb-nav"><div v-for="section in sections" :key="section.title"><div class="nav-sec">{{ section.title }}</div><button
v-for="item in section.items"
:key="item.id"
class="nav-item"
:class="{ on: activePage === item.id }"
type="button"
@click="activePage = item.id"
><span class="nav-ic">{{ item.icon }}</span><span>{{ item.label }}</span></button></div></nav></aside><section class="main"><header class="topbar"><button
class="btn btn-s btn-sm hr-menu-button"
:title="isSidebarOpen ? 'ซ่อนเมนู' : 'แสดงเมนู'"
type="button"
@click="isSidebarOpen = !isSidebarOpen"
>
☰
 </button><div class="tb-title">{{ currentPageTitle }}</div><span class="tb-badge">{{ cycleBadge }}</span><button class="btn btn-s btn-sm" type="button" @click="logout">ออกจากระบบ</button></header><main class="content"><template v-if="activePage === 'hr-position-competencies'"><div class="position-hero mb14"><div><div class="position-kicker">competency setup</div><div class="sec-t position-title">กำหนดสมรรถนะประจำตำแหน่ง</div><div class="sec-s position-sub">
เลือกตำแหน่ง แล้วกำหนดชุดสมรรถนะที่ต้องใช้ประเมิน ก่อนนำไปตั้งระดับความคาดหวังในรอบประเมิน
 </div></div><div class="position-hero-metrics"><div><span>0</span><small>ตำแหน่งที่กำหนดแล้ว</small></div><div><span>0</span><small>ยังไม่กำหนด</small></div></div></div><div class="position-scope mb14"><div class="position-workline"><div class="position-scope-label">สายงาน</div><div v-if="hasWorklines" class="position-segments"><button
v-for="workline in worklines"
:key="workline"
:class="{ active: selectedWorkline === workline }"
type="button"
@click="selectedWorkline = workline"
>
{{ workline }}
 </button></div><div v-else class="position-empty-inline">ยังไม่มีสายงาน</div></div><div class="position-picker"><div v-if="selectedWorkline === 'สายสนับสนุน'" class="fg mb0"><label class="lbl">กลุ่มงาน / Job Family</label><select v-model="selectedJobFamily" class="sel"><option value="">ยังไม่มีข้อมูลกลุ่มงาน</option></select></div><div class="fg mb0"><label class="lbl">ตำแหน่ง</label><select v-model="selectedPosition" class="sel"><option value="">ยังไม่มีข้อมูลตำแหน่ง</option></select></div></div></div><div class="position-board mb14"><div class="position-card selected"><div class="position-card-label">ตำแหน่งที่กำลังกำหนด</div><div class="position-card-title">{{ positionLabel }}</div><div class="position-card-sub">{{ selectedWorkline || 'ยังไม่มีสายงาน' }} · {{ jobFamilyLabel }}</div></div><div class="position-card"><div class="position-card-label">สมรรถนะของตำแหน่งนี้</div><div class="position-card-title">{{ props.competencies.length }}</div><div class="position-card-sub">จากพจนานุกรมสมรรถนะ</div></div><div class="position-card"><div class="position-card-label">{{ coreTypeCode }} พื้นฐาน</div><div class="position-card-title" :class="{ warn: !coreCompetencies.length, ok: coreCompetencies.length }">{{ coreCompetencies.length }}</div><div class="position-card-sub">อ้างอิงจากประเภทสมรรถนะหลัก</div></div></div><div class="position-layout"><section class="position-panel assigned"><div class="position-panel-head"><div><div class="ct">ชุดสมรรถนะประจำตำแหน่ง</div><div class="cs">รายการนี้จะถูกใช้เป็นฐานสำหรับกำหนด Expected Level</div></div><button class="btn btn-t btn-sm" disabled type="button">เพิ่ม CC ทั้งหมด</button></div><div class="assigned-list"><div class="assigned-empty"><div class="assigned-empty-icon"></div><div class="fw8">ยังไม่ได้กำหนดสมรรถนะให้ตำแหน่งนี้</div><div class="muted fs12">รอข้อมูลพจนานุกรมและตำแหน่งจากฐานข้อมูลจริง</div></div></div></section><aside class="position-panel dictionary"><div class="position-panel-head"><div><div class="ct">พจนานุกรมสมรรถนะ</div><div class="cs">เลือกจากรายการที่ Admin กำหนดไว้</div></div></div><div class="dictionary-tools"><input v-model="competencyQuery" class="inp" placeholder="ค้นหารหัส / ชื่อ / คำอธิบาย" /><select v-model="competencyTypeFilter" class="sel"><option value="all">ทั้งหมด</option><option
v-for="type in competencyTypeOptions"
:key="type.code"
:value="type.code"
>
{{ type.label }}
 </option></select></div><div class="dictionary-list"><div
v-for="competency in filteredCompetencies"
:key="competency.cd"
class="dictionary-item"
><div><div class="flex ic g8 mb4"><span class="dictionary-code">{{ competency.cd }}</span><span :class="getCompTag(competency)">{{ getCompType(competency) }}</span></div><div class="fw8 fs13">{{ competency.n }}</div><div class="muted fs11 truncate-2">{{ competency.det || 'ยังไม่มีคำอธิบาย' }}</div><div class="dictionary-meta">
{{ competency.levels?.length || 0 }} ระดับ · จากพจนานุกรมสมรรถนะ
 </div></div><button class="btn btn-s btn-xs" disabled type="button">พร้อมใช้</button></div><div v-if="filteredCompetencies.length === 0" class="assigned-empty dictionary-empty"><div class="assigned-empty-icon"></div><div class="fw8">ยังไม่มีข้อมูลสมรรถนะ</div><div class="muted fs12">เพิ่มประเภทและพจนานุกรมสมรรถนะจากหน้า Admin เพื่อให้ HR เลือกใช้</div></div></div></aside></div></template><template v-else-if="activePage === 'emp-assess'"><EmployeeAssess :user="{ sso: $page.props.auth.user.id }" :set-users="() => {}" /></template><template v-else-if="activePage === 'emp-gap'"><EmployeeGap :set-page="(p) => activePage = p" /></template><template v-else-if="activePage === 'emp-idp'"><EmployeeIDP /></template><template v-else-if="activePage === 'emp-progress'"><EmployeeProgress /></template><template v-else-if="activePage === 'emp-idp-detail'"><EmployeeIDPDetail /></template><template v-else-if="activePage === 'hr-cycle'"><div class="hr-page-head mb20"><div><div class="sec-t">รอบการประเมิน </div><div class="sec-s">เปิด-ปิดรอบ กำหนดช่วงเวลา และตรวจสอบสถานะจากข้อมูลจริง</div></div><button class="btn btn-p" type="button" @click="openModal('cycle')">+ เปิดรอบใหม่</button></div><div class="g2 mb14"><div class="sc"><div class="sl">รอบที่กำลังเปิดอยู่</div><div class="sv tc">0</div><div class="ss muted">ยังไม่มีรอบประเมิน</div></div><div class="sc"><div class="sl">ส่งแบบประเมินแล้ว</div><div class="sv gcc">0<span class="sv-tail">/0</span></div><div class="ss muted">รอข้อมูลการประเมิน</div></div></div><div class="card mb14"><div class="ch"><div class="ct">รอบประเมินทั้งหมด</div><span class="muted fs12 ml-auto">{{ props.hrCycles.length }} รายการ</span></div><div class="hr-table-wrap"><table class="tbl hr-fixed-table"><thead><tr><th>รอบประเมิน</th><th>ปี</th><th>รอบประเมินตนเอง</th><th>เวลาสิ้นสุดหัวหน้างาน</th><th>ส่งแล้ว</th><th>สถานะ</th></tr></thead><tbody v-if="!hasCycles"><tr><td colspan="6"><div class="table-empty-cell"><div class="hr-empty-icon">∅</div><div><div class="fw8 fs14">{{ currentEmptyState.title }}</div><p class="muted fs13 mb0">{{ currentEmptyState.body }}</p></div></div></td></tr></tbody></table></div></div><div class="card"><div class="ch"><div class="ct"> ส่งการแจ้งเตือน</div></div><div class="cb"><p class="muted fs13 mb16">แจ้งเตือนบุคลากรที่ยังไม่ส่งแบบประเมิน</p><button class="btn btn-p btn-sm" disabled type="button"> ส่งแจ้งเตือน</button></div></div></template><template v-else-if="activePage === 'hr-template'"><div class="hr-page-head mb20"><div><div class="sec-t">กำหนดความคาดหวังการประเมิน </div><div class="sec-s">ตั้งค่า Expected Level ของแต่ละประเภทบุคลากรในแต่ละรอบการประเมิน</div></div></div><div class="card mb14"><div class="ch"><div class="ct">① เลือกรอบการประเมิน</div></div><div class="cb cycle-picker"><div v-if="hasCycles" class="cycle-picker"></div><div v-else class="cycle-empty"><div class="hr-empty-icon">∅</div><div><div class="fw8 fs14">ยังไม่มีรอบการประเมิน</div><p class="muted fs13 mb0">ต้องมีรอบประเมินก่อนจึงจะกำหนด Expected Level ได้</p></div></div></div></div><div class="expect-tabs mb14"><button class="expect-tab on" type="button">กำหนด / แก้ไข</button><button class="expect-tab" type="button">ดูความคาดหวังทั้งหมด <span class="b bgr">0 ชุด</span></button><button class="btn btn-p btn-sm ml-auto" type="button" @click="openModal('expectation-import')">
นำเข้าความคาดหวัง
 </button></div><div class="expect-layout mb14"><div class="card"><div class="ch"><div><div class="ct">กรองดูตามประเภทบุคลากร</div><div class="cs">ตัวเลือกจะมาจากข้อมูลที่ Admin ตั้งไว้</div></div></div><div class="cb"><div class="fg"><label class="lbl">สายงาน</label><select class="sel"><option>ยังไม่มีสายงาน</option></select></div><div class="fg"><label class="lbl">ตำแหน่ง</label><select class="sel"><option>ยังไม่มีข้อมูลตำแหน่ง</option></select></div><div class="fg mb0"><label class="lbl">ระดับ</label><select class="sel"><option>ยังไม่มีข้อมูลระดับ</option></select></div></div></div><div class="card"><div class="ch"><div><div class="ct">ชุดความคาดหวัง</div><div class="cs">รอข้อมูลสมรรถนะประจำตำแหน่งและรอบประเมินจริง</div></div><span class="b bgr ml-auto">ยังไม่บันทึก</span></div><div class="hr-table-wrap"><table class="tbl hr-fixed-table"><thead><tr><th>รหัส</th><th>สมรรถนะ</th><th>ประเภท</th><th>ความคาดหวัง</th></tr></thead><tbody v-if="!hasExpectationSets"><tr><td colspan="4"><div class="table-empty-cell"><div class="hr-empty-icon">∅</div><div><div class="fw8 fs14">{{ currentEmptyState.title }}</div><p class="muted fs13 mb0">{{ currentEmptyState.body }}</p></div></div></td></tr></tbody></table></div><div class="hr-card-actions"><button class="btn btn-p btn-sm" type="button" @click="openModal('competency')">เพิ่มสมรรถนะ</button><button class="btn btn-t btn-sm" disabled type="button">บันทึก Expected Level</button></div></div></div></template><template v-else-if="activePage === 'hr-catalog'"><div class="hr-page-head mb20"><div><div class="sec-t">Learning Catalog </div><div class="sec-s">ทะเบียนกิจกรรมพัฒนา · บุคลากรเลือกกิจกรรมจาก Catalog นี้เมื่อทำ IDP</div></div><div class="hr-actions"><button class="btn btn-s" disabled type="button"> ดาวน์โหลด Template</button><button class="btn btn-s" type="button" @click="openModal('catalog-import')"> Import Excel</button><button class="btn btn-p" type="button" @click="openModal('catalog')">+ เพิ่มกิจกรรม</button></div></div><div class="g3 mb14"><div
v-for="method in learningMethodSummaries"
:key="method.key"
class="sc"
><div class="sl">{{ method.label }}</div><div class="sv bc">{{ method.count }}</div><div class="ss muted">{{ method.desc || 'ประเภทการเรียนรู้จากโครงสร้าง' }}</div></div><div v-if="learningMethodSummaries.length === 0" class="sc"><div class="sl">Learning Methods</div><div class="sv bc">0</div><div class="ss muted">ยังไม่มีประเภทการเรียนรู้</div></div></div><div class="card"><div class="ch"><div class="ct">Learning Catalog</div><span class="muted fs12 ml-auto">{{ props.hrCatalogItems.length }} รายการ</span></div><div class="hr-table-wrap"><table class="tbl catalog-table"><thead><tr><th>ชื่อกิจกรรม</th><th>ประเภท</th><th>ผู้จัด</th><th>ค่าใช้จ่าย</th><th>สถานะ</th><th></th></tr></thead><tbody v-if="!hasCatalogItems"><tr><td colspan="6"><div class="table-empty-cell"><div class="hr-empty-icon">∅</div><div><div class="fw8 fs14">{{ currentEmptyState.title }}</div><p class="muted fs13 mb0">{{ currentEmptyState.body }}</p></div></div></td></tr></tbody></table></div></div></template><template v-else-if="activePage === 'hr-comp-overview'"><ManagerGap :users="props.overviewUsers" :disable-mock-data="true" /></template><template v-else-if="activePage === 'hr-idp-overview'"><ManagerIDP :users="props.overviewUsers" :disable-mock-data="true" /></template><template v-else><div class="card"><div class="ch"><div class="ct">{{ currentPageTitle }}</div></div><div class="cb"><div class="hr-empty"><div class="hr-empty-icon">∅</div><div><div class="fw8 fs14">{{ currentEmptyState.title }}</div><p class="muted fs13 mb0">{{ currentEmptyState.body }}</p></div></div></div></div></template></main></section><div v-if="activeModal" class="mo hr-modal" @click.self="closeModal"><div class="mo-box hr-modal-box"><div class="mo-h"><div><div v-if="activeModal === 'cycle'" class="ct">เปิดรอบการประเมินใหม่</div><div v-else-if="activeModal === 'expectation-import'" class="ct">นำเข้าความคาดหวัง</div><div v-else-if="activeModal === 'competency'" class="ct">เพิ่มสมรรถนะในชุดความคาดหวัง</div><div v-else-if="activeModal === 'catalog-import'" class="ct">Import Learning Catalog</div><div v-else class="ct">เพิ่มกิจกรรม Learning Catalog</div><div class="cs">ฟอร์มนี้เตรียมไว้รอเชื่อม API และฐานข้อมูลจริง</div></div><button class="modal-close" type="button" @click="closeModal">×</button></div><form class="mo-b" @submit.prevent><template v-if="activeModal === 'cycle'"><div class="modal-grid"><div class="fg"><label class="lbl">ชื่อรอบประเมิน</label><input class="inp" placeholder="เช่น รอบประเมิน 2568" /></div><div class="fg"><label class="lbl">ปีงบประมาณ</label><input class="inp" inputmode="numeric" placeholder="2568" /></div><div class="fg"><label class="lbl">เริ่มประเมินตนเอง</label><input class="inp" type="date" /></div><div class="fg"><label class="lbl">สิ้นสุดประเมินตนเอง</label><input class="inp" type="date" /></div><div class="fg"><label class="lbl">เวลาสิ้นสุดหัวหน้างาน</label><input class="inp" type="date" /></div><div class="fg"><label class="lbl">สถานะ</label><select class="sel"><option>แบบร่าง</option><option>เปิดใช้งาน</option></select></div></div><div class="fg mb0"><label class="lbl">หมายเหตุ</label><textarea class="ta" placeholder="รายละเอียดเพิ่มเติมของรอบประเมิน"></textarea></div></template><template v-else-if="activeModal === 'expectation-import'"><div class="modal-grid"><div class="fg"><label class="lbl">รอบการประเมิน</label><select class="sel"><option>ยังไม่มีรอบการประเมิน</option></select></div><div class="fg"><label class="lbl">รูปแบบการนำเข้า</label><select class="sel"><option>แทนที่ข้อมูลเดิม</option><option>เพิ่มต่อจากข้อมูลเดิม</option></select></div></div><div class="upload-box"><div class="upload-ic"></div><div><div class="fw8 fs14">เลือกไฟล์ Expected Level</div><div class="muted fs12">รองรับไฟล์ .xlsx เมื่อเชื่อม backend แล้ว</div></div><input class="inp" type="file" accept=".xlsx,.xls" /></div></template><template v-else-if="activeModal === 'competency'"><div class="modal-grid"><div class="fg"><label class="lbl">สมรรถนะ</label><select class="sel"><option>ยังไม่มีข้อมูลสมรรถนะ</option></select></div><div class="fg"><label class="lbl">ประเภท</label><select class="sel"><option>CC</option><option>FC</option><option>MC</option></select></div></div><div class="fg"><label class="lbl">Expected Level</label><div class="level-picker"><button v-for="level in 5" :key="level" class="level-choice" type="button">{{ level }}</button></div></div><div class="fg mb0"><label class="lbl">หมายเหตุ</label><textarea class="ta" placeholder="เงื่อนไขหรือคำอธิบายของระดับความคาดหวัง"></textarea></div></template><template v-else-if="activeModal === 'catalog-import'"><div class="modal-grid"><div class="fg"><label class="lbl">ประเภทข้อมูล</label><select class="sel"><option>Learning Catalog</option></select></div><div class="fg"><label class="lbl">วิธีนำเข้า</label><select class="sel"><option>ตรวจสอบก่อนบันทึก</option><option>นำเข้าเป็นแบบร่าง</option></select></div></div><div class="upload-box"><div class="upload-ic"></div><div><div class="fw8 fs14">เลือกไฟล์ Catalog</div><div class="muted fs12">รอ API สำหรับ validate และ import ไฟล์ Excel</div></div><input class="inp" type="file" accept=".xlsx,.xls" /></div></template><template v-else><div class="modal-grid"><div class="fg modal-span"><label class="lbl">ชื่อกิจกรรม / หลักสูตร</label><input class="inp" placeholder="ชื่อกิจกรรมพัฒนา" /></div><div class="fg"><label class="lbl">ประเภท</label><select class="sel"><option>Experiential Learning</option><option>Social Learning</option><option>Formal Training</option></select></div><div class="fg"><label class="lbl">ผู้จัด</label><input class="inp" placeholder="หน่วยงาน / ผู้จัด" /></div><div class="fg"><label class="lbl">ค่าใช้จ่าย</label><input class="inp" placeholder="ฟรี / จำนวนเงิน" /></div><div class="fg"><label class="lbl">สถานะ</label><select class="sel"><option>แบบร่าง</option><option>เปิดใช้</option><option>ปิด</option></select></div></div><div class="fg mb0"><label class="lbl">คำอธิบาย</label><textarea class="ta" placeholder="รายละเอียดกิจกรรมที่บุคลากรจะเห็นตอนทำ IDP"></textarea></div></template><div class="modal-actions"><button class="btn btn-s" type="button" @click="closeModal">ยกเลิก</button><button class="btn btn-p" disabled type="submit">บันทึกเมื่อเชื่อม backend</button></div></form></div></div></div></template><style scoped>
.shell {
font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
font-synthesis-weight: none;
-webkit-font-smoothing: antialiased;
-moz-osx-font-smoothing: grayscale;
letter-spacing: 0;
}

.nav-item {
width: 100%;
border-top: 0;
border-right: 0;
border-bottom: 0;
background: transparent;
font-family: inherit;
text-align: left;
}

.hr-menu-button {
min-width: 40px;
justify-content: center;
border: 0;
background: transparent;
padding: 8px;
}

.content .btn.btn-p {
background: var(--blue);
border: none;
color: #fff;
}

.content .btn.btn-p:hover {
background: #1d4ed8;
}

.content .btn.btn-t {
background: var(--teal);
border: none;
color: #fff;
}

.content .btn.btn-t:hover {
background: #0c8a85;
}

.content .btn.btn-s {
background: var(--bg);
border: 1px solid var(--border);
color: var(--text2);
}

.content .btn.btn-s:hover {
background: var(--border);
}

.content .sec-t {
font-weight: 800;
}

.content .sec-s,
.content .muted,
.content .cs,
.content .ss {
font-weight: 400;
}

.content .ct {
font-weight: 700;
}

.content .sl,
.content .lbl,
.content .tbl th {
font-weight: 700;
}

.content .tbl td {
font-weight: 400;
}

.content .fw8 {
font-weight: 700;
}

.hr-empty {
display: flex;
gap: 14px;
align-items: center;
min-height: 150px;
border: 1px dashed var(--border);
border-radius: var(--r-lg);
background: var(--bg);
padding: 22px;
}

.hr-empty-icon {
display: flex;
align-items: center;
justify-content: center;
width: 44px;
height: 44px;
border-radius: 50%;
background: var(--blue-lt);
color: var(--blue);
font-size: 22px;
font-weight: 800;
flex-shrink: 0;
}

.hr-empty.compact {
min-height: 118px;
}

.cycle-picker {
display: flex;
align-items: stretch;
gap: 10px;
flex-wrap: wrap;
}

.cycle-empty {
min-height: 62px;
display: flex;
align-items: center;
gap: 14px;
padding: 6px 0;
}

.hr-page-head {
display: flex;
align-items: center;
justify-content: space-between;
gap: 14px;
}

.hr-actions {
display: flex;
flex-wrap: wrap;
gap: 8px;
justify-content: flex-end;
}

.hr-table-wrap {
overflow-x: auto;
}

.hr-fixed-table {
min-width: 760px;
}

.catalog-table {
min-width: 820px;
}

.oc {
color: var(--orange);
}

.table-empty-cell {
min-height: 118px;
display: flex;
align-items: center;
gap: 14px;
background: var(--bg);
padding: 20px;
}

.tbl td:has(.table-empty-cell) {
padding: 0;
}

.hr-card-actions {
display: flex;
flex-wrap: wrap;
gap: 8px;
padding: 12px 16px;
border-top: 1px solid var(--border);
background: var(--bg);
}

.sv-tail {
color: var(--text3);
font-size: 14px;
}

.expect-tabs {
display: flex;
align-items: center;
gap: 0;
flex-wrap: wrap;
border-bottom: 2px solid var(--border);
}

.expect-tab {
display: inline-flex;
align-items: center;
gap: 8px;
padding: 9px 20px;
border: 0;
border-bottom: 3px solid transparent;
border-radius: 0;
margin-bottom: -2px;
background: transparent;
color: var(--text3);
font: inherit;
font-size: 13px;
font-weight: 800;
cursor: pointer;
}

.expect-tab.on {
border-bottom-color: var(--teal);
color: var(--teal);
}

.expect-layout {
display: grid;
grid-template-columns: 310px minmax(0, 1fr);
gap: 14px;
align-items: start;
}

.ml-auto {
margin-left: auto;
}

.btn:disabled,
.expect-tab:disabled,
.sel:disabled,
.inp:disabled {
cursor: not-allowed;
opacity: 1;
}

.btn-p:disabled {
border: 1px solid var(--blue-md);
background: var(--blue-lt);
color: var(--blue);
}

.btn-t:disabled {
border: 1px solid var(--teal-md);
background: var(--teal-lt);
color: var(--teal);
}

.btn-s:disabled,
.expect-tab:disabled,
.sel:disabled,
.inp:disabled {
background-color: #f8fafc;
color: var(--text3);
border-color: var(--border);
}

.position-hero {
min-height: 138px;
display: grid;
grid-template-columns: minmax(0, 1fr) auto;
gap: 20px;
align-items: end;
padding: 24px 26px;
border-radius: 14px;
background: #102f5d;
color: #f8fbff;
box-shadow: 0 16px 34px rgba(15, 45, 91, 0.18);
}

.position-kicker {
color: rgba(248, 251, 255, 0.58);
font-size: 11px;
font-weight: 800;
letter-spacing: 0.12em;
text-transform: uppercase;
margin-bottom: 8px;
}

.position-title {
color: #f8fbff;
font-size: 26px;
}

.position-sub {
color: rgba(248, 251, 255, 0.68);
max-width: 760px;
line-height: 1.65;
}

.position-hero-metrics {
display: grid;
grid-template-columns: repeat(2, 120px);
gap: 10px;
}

.position-hero-metrics div {
padding: 14px;
border: 1px solid rgba(248, 251, 255, 0.16);
border-radius: 10px;
background: rgba(248, 251, 255, 0.08);
}

.position-hero-metrics span {
display: block;
font-size: 30px;
font-weight: 800;
line-height: 1;
}

.position-hero-metrics small {
display: block;
color: rgba(248, 251, 255, 0.64);
font-size: 11px;
font-weight: 700;
margin-top: 6px;
}

.position-scope {
display: grid;
grid-template-columns: minmax(320px, 1fr) minmax(360px, 1fr);
gap: 14px;
padding: 14px;
border: 1px solid var(--border);
border-radius: 12px;
background: #fff;
box-shadow: var(--sh);
}

.position-scope-label {
color: var(--text3);
font-size: 11px;
font-weight: 700;
margin-bottom: 8px;
}

.position-segments {
display: flex;
gap: 8px;
flex-wrap: wrap;
}

.position-segments button {
min-height: 36px;
border: 1px solid var(--border);
border-radius: 999px;
background: #fff;
color: var(--text2);
padding: 0 14px;
font: inherit;
font-size: 12px;
font-weight: 700;
cursor: pointer;
}

.position-segments button.active {
background: var(--navy);
border-color: var(--navy);
color: #fff;
}

.position-empty-inline {
min-height: 36px;
display: inline-flex;
align-items: center;
border: 1px dashed var(--border);
border-radius: 999px;
background: var(--bg);
color: var(--text3);
padding: 0 14px;
font-size: 12px;
font-weight: 700;
}

.position-picker {
display: grid;
grid-template-columns: repeat(2, minmax(0, 1fr));
gap: 12px;
align-items: end;
}

.position-picker .fg:only-child {
grid-column: 1 / -1;
}

.position-board {
display: grid;
grid-template-columns: 1.35fr repeat(2, minmax(180px, 0.65fr));
gap: 14px;
}

.position-card {
min-height: 104px;
padding: 16px;
border: 1px solid var(--border);
border-radius: 12px;
background: #fff;
box-shadow: var(--sh);
}

.position-card.selected {
border-color: var(--blue-md);
background: linear-gradient(180deg, #fff 0%, var(--blue-lt) 100%);
}

.position-card-label {
color: var(--text3);
font-size: 11px;
font-weight: 700;
margin-bottom: 8px;
}

.position-card-title {
color: var(--text);
font-size: 24px;
font-weight: 800;
line-height: 1.2;
}

.position-card-title.ok {
color: var(--green);
}

.position-card-title.warn {
color: var(--yellow);
}

.position-card-sub {
color: var(--text3);
font-size: 12px;
font-weight: 600;
margin-top: 7px;
}

.position-layout {
display: grid;
grid-template-columns: minmax(0, 1.25fr) minmax(360px, 0.75fr);
gap: 14px;
align-items: start;
}

.position-panel {
border: 1px solid var(--border);
border-radius: 12px;
background: #fff;
box-shadow: var(--sh);
overflow: hidden;
}

.position-panel-head {
min-height: 64px;
display: flex;
align-items: center;
justify-content: space-between;
gap: 12px;
padding: 14px 16px;
border-bottom: 1px solid var(--border);
background: #fff;
}

.assigned-list {
display: grid;
gap: 10px;
padding: 14px;
}

.assigned-empty {
min-height: 220px;
display: grid;
place-items: center;
align-content: center;
gap: 8px;
border: 1px dashed var(--border);
border-radius: 10px;
background: var(--bg);
text-align: center;
}

.assigned-empty-icon {
width: 44px;
height: 44px;
display: grid;
place-items: center;
border-radius: 12px;
background: #fff;
box-shadow: var(--sh);
font-size: 22px;
}

.dictionary {
position: sticky;
top: 14px;
}

.dictionary-tools {
display: grid;
grid-template-columns: minmax(0, 1fr) 132px;
gap: 10px;
padding: 14px;
border-bottom: 1px solid var(--border);
background: var(--bg);
}

.dictionary-list {
display: grid;
gap: 8px;
max-height: 560px;
overflow-y: auto;
padding: 14px;
}

.dictionary-empty {
min-height: 340px;
}

.truncate-2 {
display: -webkit-box;
-webkit-box-orient: vertical;
-webkit-line-clamp: 2;
overflow: hidden;
}

.hr-modal {
font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
}

.hr-modal-box {
width: min(720px, calc(100vw - 36px));
}

.modal-close {
width: 32px;
height: 32px;
border: 1px solid var(--border);
border-radius: var(--r);
background: #fff;
color: var(--text3);
font: inherit;
font-size: 22px;
font-weight: 600;
line-height: 1;
cursor: pointer;
}

.modal-close:hover {
background: var(--bg);
color: var(--text);
}

.modal-grid {
display: grid;
grid-template-columns: repeat(2, minmax(0, 1fr));
gap: 14px;
}

.modal-span {
grid-column: 1 / -1;
}

.upload-box {
display: grid;
grid-template-columns: 44px minmax(0, 1fr);
gap: 12px;
align-items: center;
padding: 16px;
border: 1px dashed var(--border);
border-radius: var(--r-lg);
background: var(--bg);
}

.upload-box .inp {
grid-column: 1 / -1;
background: #fff;
}

.upload-ic {
width: 44px;
height: 44px;
display: grid;
place-items: center;
border-radius: 50%;
background: var(--blue-lt);
font-size: 20px;
}

.level-picker {
display: grid;
grid-template-columns: repeat(5, minmax(0, 1fr));
gap: 8px;
}

.level-choice {
height: 40px;
border: 1px solid var(--border);
border-radius: var(--r);
background: #fff;
color: var(--navy);
font: inherit;
font-weight: 800;
cursor: pointer;
}

.level-choice:hover,
.level-choice:focus {
border-color: var(--teal);
background: var(--teal-lt);
outline: none;
}

.modal-actions {
display: flex;
justify-content: flex-end;
gap: 8px;
margin-top: 20px;
padding-top: 14px;
border-top: 1px solid var(--border);
}

@media (max-width: 1100px) {
.position-layout,
.position-scope,
.position-board,
.position-hero,
.expect-layout {
grid-template-columns: 1fr;
}

.dictionary {
position: static;
}

.position-hero-metrics {
grid-template-columns: repeat(2, minmax(0, 1fr));
}

}

@media (max-width: 768px) {
.shell {
height: auto;
min-height: 100vh;
flex-direction: column;
overflow: visible;
}

.sidebar {
position: static;
width: 100%;
}

.main {
min-height: 0;
}

.content {
overflow: visible;
}

.g3 {
grid-template-columns: 1fr;
}

.g2,
.g4 {
grid-template-columns: 1fr;
}

.hr-page-head {
align-items: flex-start;
flex-direction: column;
}

.hr-actions {
justify-content: flex-start;
}

.position-picker,
.dictionary-tools,
.modal-grid {
grid-template-columns: 1fr;
}

.modal-actions {
justify-content: stretch;
}

.modal-actions .btn {
justify-content: center;
flex: 1;
}

.position-hero {
padding: 20px;
}

.position-title {
font-size: 22px;
}

}
</style>
