<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    hrSummary: {
        type: Object,
        required: true,
    },
    worklines: {
        type: Array,
        default: () => [],
    },
    jobFamiliesByWorkline: {
        type: Object,
        default: () => ({}),
    },
    levelsByWorkline: {
        type: Object,
        default: () => ({}),
    },
    competencies: {
        type: Array,
        default: () => [],
    },
    learningMethods: {
        type: Array,
        default: () => [],
    },
    hrCatalogItems: {
        type: Array,
        default: () => [],
    },
    activeCycleName: {
        type: String,
        default: '',
    },
});

const page = usePage();
const logout = () => router.post(route('logout'));

const isSidebarOpen = ref(true);
const activePage = ref('hr-position-competencies');
const activeModal = ref('');
const selectedWorkline = ref('');
const selectedJobFamily = ref('');
const selectedPosition = ref('');
const dictionarySearch = ref('');
const dictionaryType = ref('all');
const assignedByScope = ref({});
const selectedDetailCompetency = ref(null);

const sections = [
    {
        title: 'HR',
        items: [
            { id: 'hr-position-competencies', label: 'กำหนดสมรรถนะประจำตำแหน่ง' },
            { id: 'hr-catalog', label: 'Learning Catalog' },
        ],
    },
];

const pageTitles = {
    'hr-position-competencies': 'กำหนดสมรรถนะประจำตำแหน่ง',
    'hr-catalog': 'Learning Catalog',
};

const userInitial = computed(() => page.props.auth.user.name?.[0] || 'H');
const currentPageTitle = computed(() => pageTitles[activePage.value] || 'HR');
const cycleBadge = computed(() => props.activeCycleName || 'ยังไม่มีรอบประเมิน');
const worklineOptions = computed(() => props.worklines || []);

const familiesForSelectedWorkline = computed(() => {
    const groups = props.jobFamiliesByWorkline?.[selectedWorkline.value] || {};
    return Object.keys(groups);
});

const rawPositionsForSelectedFamily = computed(() => {
    const groups = props.jobFamiliesByWorkline?.[selectedWorkline.value] || {};
    return groups[selectedJobFamily.value] || [];
});

const levelsForSelectedWorkline = computed(() => {
    return props.levelsByWorkline?.[selectedWorkline.value] || [];
});

const positionOptions = computed(() => {
    if (levelsForSelectedWorkline.value.length) return levelsForSelectedWorkline.value;
    if (rawPositionsForSelectedFamily.value.length) return rawPositionsForSelectedFamily.value;
    return selectedJobFamily.value ? [selectedJobFamily.value] : [];
});

const allPositionCount = computed(() => {
    return worklineOptions.value.reduce((total, workline) => {
        const levels = props.levelsByWorkline?.[workline] || [];
        if (levels.length) return total + levels.length;

        const families = props.jobFamiliesByWorkline?.[workline] || {};
        return total + Object.entries(families || {}).reduce((sum, [familyName, positions]) => {
            const count = Array.isArray(positions) && positions.length ? positions.length : 1;
            return sum + (familyName ? count : 0);
        }, 0);
    }, 0);
});

const configuredPositionCount = computed(() => 0);
const unconfiguredPositionCount = computed(() => Math.max(allPositionCount.value - configuredPositionCount.value, 0));
const positionLabel = computed(() => selectedPosition.value || 'ยังไม่มีข้อมูลตำแหน่ง/ระดับตำแหน่ง');
const jobFamilyLabel = computed(() => selectedJobFamily.value || 'ยังไม่มีข้อมูลกลุ่มงาน');
const assignmentScopeKey = computed(() => [
    selectedWorkline.value || '-',
    selectedJobFamily.value || '-',
    selectedPosition.value || '-',
].join('|'));

const competencyItems = computed(() => props.competencies || []);
const competencyTypes = computed(() => {
    return [...new Set(competencyItems.value.map((item) => item.t).filter(Boolean))];
});
const coreCompetencyCount = computed(() => competencyItems.value.filter((item) => item.t === 'CC').length);
const assignedCompetencies = computed(() => assignedByScope.value[assignmentScopeKey.value] || []);
const assignedCompetencyIds = computed(() => new Set(assignedCompetencies.value.map((item) => item.id)));
const assignedCoreCompetencyCount = computed(() => assignedCompetencies.value.filter((item) => item.t === 'CC').length);
const filteredCompetencies = computed(() => {
    const keyword = dictionarySearch.value.trim().toLowerCase();

    return competencyItems.value.filter((item) => {
        const matchesType = dictionaryType.value === 'all' || item.t === dictionaryType.value;
        const haystack = `${item.cd || ''} ${item.n || ''} ${item.det || ''}`.toLowerCase();
        return matchesType && (!keyword || haystack.includes(keyword));
    });
});
const availableCoreCompetencies = computed(() => {
    return competencyItems.value.filter((item) => item.t === 'CC' && !assignedCompetencyIds.value.has(item.id));
});

const catalogItems = computed(() => props.hrCatalogItems || []);
const catalogMethodStats = computed(() => {
    const methods = props.learningMethods?.length
        ? props.learningMethods
        : [...new Map(catalogItems.value.map((item) => [item.methodKey, {
            key: item.methodKey,
            label: item.methodLabel || item.methodKey,
        }])).values()].filter((item) => item.key);

    return methods.map((method) => ({
        key: method.key,
        label: method.label,
        count: catalogItems.value.filter((item) => item.methodKey === method.key).length,
    }));
});

watch(worklineOptions, (next) => {
    if (!selectedWorkline.value && next.length) selectedWorkline.value = next[0];
}, { immediate: true });

watch(familiesForSelectedWorkline, (next) => {
    if (!next.includes(selectedJobFamily.value)) selectedJobFamily.value = next[0] || '';
}, { immediate: true });

watch(positionOptions, (next) => {
    if (!next.includes(selectedPosition.value)) selectedPosition.value = next[0] || '';
}, { immediate: true });

const openModal = (modal) => {
    activeModal.value = modal;
};

const closeModal = () => {
    activeModal.value = '';
    selectedDetailCompetency.value = null;
};

const setAssignedForCurrentScope = (items) => {
    assignedByScope.value = {
        ...assignedByScope.value,
        [assignmentScopeKey.value]: items,
    };
};

const addCompetency = (item) => {
    if (!selectedPosition.value || assignedCompetencyIds.value.has(item.id)) return;
    setAssignedForCurrentScope([...assignedCompetencies.value, item]);
};

const addAllCoreCompetencies = () => {
    if (!selectedPosition.value || !availableCoreCompetencies.value.length) return;
    setAssignedForCurrentScope([...assignedCompetencies.value, ...availableCoreCompetencies.value]);
};

const removeCompetency = (itemId) => {
    setAssignedForCurrentScope(assignedCompetencies.value.filter((item) => item.id !== itemId));
};

const openCompetencyDetail = (item) => {
    selectedDetailCompetency.value = item;
    openModal('competency-detail');
};

const formatCost = (cost) => {
    if (cost === null || cost === undefined || cost === '') return 'ฟรี';
    const number = Number(cost);
    if (Number.isNaN(number) || number === 0) return 'ฟรี';
    return `${number.toLocaleString('th-TH')} บาท`;
};
</script>

<template>
    <div class="shell">
        <aside v-if="isSidebarOpen" class="sidebar">
            <div class="sb-logo">
                <div class="sb-mark">คณะวิศวกรรมศาสตร์</div>
                <div class="sb-name">Competency &<br />IDP Management</div>
            </div>

            <button class="sb-user" type="button">
                <div class="av" style="background: var(--blue)">{{ userInitial }}</div>
                <div style="min-width: 0">
                    <div class="u-name">{{ $page.props.auth.user.name }}</div>
                    <div class="u-role">งานทรัพยากรบุคคล</div>
                </div>
            </button>

            <nav class="sb-nav">
                <div v-for="section in sections" :key="section.title">
                    <div class="nav-sec">{{ section.title }}</div>
                    <button
                        v-for="item in section.items"
                        :key="item.id"
                        class="nav-item"
                        :class="{ on: activePage === item.id }"
                        type="button"
                        @click="activePage = item.id"
                    >
                        <span>{{ item.label }}</span>
                    </button>
                </div>
            </nav>
        </aside>

        <section class="main">
            <header class="topbar">
                <button
                    class="btn btn-s btn-sm hr-menu-button"
                    :title="isSidebarOpen ? 'ซ่อนเมนู' : 'แสดงเมนู'"
                    type="button"
                    @click="isSidebarOpen = !isSidebarOpen"
                >
                    ☰
                </button>
                <div class="tb-title">{{ currentPageTitle }}</div>
                <span class="tb-badge">{{ cycleBadge }}</span>
                <button class="btn btn-s btn-sm" type="button" @click="logout">ออกจากระบบ</button>
            </header>

            <main class="content">
                <template v-if="activePage === 'hr-position-competencies'">
                    <div class="position-hero mb14">
                        <div>
                            <div class="position-kicker">COMPETENCY SETUP</div>
                            <div class="sec-t position-title">กำหนดสมรรถนะประจำตำแหน่ง</div>
                            <div class="sec-s position-sub">
                                เลือกตำแหน่ง แล้วกำหนดชุดสมรรถนะที่ต้องใช้ประเมิน ก่อนนำไปตั้งระดับความคาดหวังในรอบประเมิน
                            </div>
                        </div>
                        <div class="position-hero-metrics">
                            <div>
                                <span>{{ configuredPositionCount }}</span>
                                <small>ตำแหน่งที่กำหนดแล้ว</small>
                            </div>
                            <div>
                                <span>{{ unconfiguredPositionCount }}</span>
                                <small>ยังไม่กำหนด</small>
                            </div>
                        </div>
                    </div>

                    <div class="position-scope mb14">
                        <div class="position-workline">
                            <div class="position-scope-label">สายงาน</div>
                            <div v-if="worklineOptions.length" class="position-segments">
                                <button
                                    v-for="workline in worklineOptions"
                                    :key="workline"
                                    :class="{ active: selectedWorkline === workline }"
                                    type="button"
                                    @click="selectedWorkline = workline"
                                >
                                    {{ workline }}
                                </button>
                            </div>
                            <div v-else class="position-empty-inline">ยังไม่มีสายงาน</div>
                        </div>
                        <div class="position-picker">
                            <div class="fg mb0">
                                <label class="lbl">กลุ่มงาน / Job Family</label>
                                <select v-model="selectedJobFamily" class="sel">
                                    <option value="">ยังไม่มีข้อมูลกลุ่มงาน</option>
                                    <option v-for="family in familiesForSelectedWorkline" :key="family" :value="family">
                                        {{ family }}
                                    </option>
                                </select>
                            </div>
                            <div class="fg mb0">
                                <label class="lbl">ตำแหน่ง</label>
                                <select v-model="selectedPosition" class="sel">
                                    <option value="">ยังไม่มีข้อมูลตำแหน่ง/ระดับตำแหน่ง</option>
                                    <option v-for="position in positionOptions" :key="position" :value="position">
                                        {{ position }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="position-board mb14">
                        <div class="position-card selected">
                            <div class="position-card-label">ตำแหน่งที่กำลังกำหนด</div>
                            <div class="position-card-title">{{ positionLabel }}</div>
                            <div class="position-card-sub">{{ selectedWorkline || 'ยังไม่มีสายงาน' }} · {{ jobFamilyLabel }}</div>
                        </div>
                        <div class="position-card">
                            <div class="position-card-label">สมรรถนะของตำแหน่งนี้</div>
                            <div class="position-card-title">{{ assignedCompetencies.length }}</div>
                            <div class="position-card-sub">{{ assignedCompetencies.length ? 'เลือกไว้ในหน้านี้' : 'ยังไม่มีรายการ' }}</div>
                        </div>
                        <div class="position-card">
                            <div class="position-card-label">CC พื้นฐาน</div>
                            <div class="position-card-title warn">{{ assignedCoreCompetencyCount }}/{{ coreCompetencyCount }}</div>
                            <div class="position-card-sub">{{ coreCompetencyCount ? 'รอเลือกจากพจนานุกรม' : 'ยังไม่มีข้อมูล CC พื้นฐาน' }}</div>
                        </div>
                    </div>

                    <div class="position-layout">
                        <section class="position-panel assigned">
                            <div class="position-panel-head">
                                <div>
                                    <div class="ct">ชุดสมรรถนะประจำตำแหน่ง</div>
                                    <div class="cs">รายการนี้จะถูกใช้เป็นฐานสำหรับกำหนด Expected Level</div>
                                </div>
                                <button
                                    class="btn btn-t btn-sm"
                                    :disabled="!selectedPosition || !availableCoreCompetencies.length"
                                    type="button"
                                    @click="addAllCoreCompetencies"
                                >
                                    เพิ่ม CC ทั้งหมด
                                </button>
                            </div>
                            <div class="assigned-list">
                                <div v-if="!assignedCompetencies.length" class="assigned-empty">
                                    <div class="empty-symbol">ไม่มีข้อมูล</div>
                                    <div class="fw8">ยังไม่ได้กำหนดสมรรถนะให้ตำแหน่งนี้</div>
                                    <div class="muted fs12">เลือกจากพจนานุกรมด้านขวาเพื่อเพิ่มเข้าชุดนี้</div>
                                </div>
                                <div v-for="item in assignedCompetencies" v-else :key="item.id" class="assigned-row">
                                    <div>
                                        <div class="dictionary-code">{{ item.cd }} <span class="b bgr">{{ item.t }}</span></div>
                                        <div class="dictionary-name">{{ item.n }}</div>
                                        <div class="dictionary-detail truncate-2">{{ item.det || 'ไม่มีคำอธิบาย' }}</div>
                                    </div>
                                    <div class="assigned-actions">
                                        <button class="btn btn-s btn-sm" type="button" @click="openCompetencyDetail(item)">รายละเอียด</button>
                                        <button class="btn btn-s btn-sm danger-text" type="button" @click="removeCompetency(item.id)">ลบ</button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <aside class="position-panel dictionary">
                            <div class="position-panel-head">
                                <div>
                                    <div class="ct">พจนานุกรมสมรรถนะ</div>
                                    <div class="cs">เลือกจากรายการที่ Admin กำหนดไว้</div>
                                </div>
                            </div>
                            <div class="dictionary-tools">
                                <input v-model="dictionarySearch" class="inp" placeholder="ค้นหารหัส / ชื่อ / คำอธิบาย" />
                                <select v-model="dictionaryType" class="sel">
                                    <option value="all">ทั้งหมด</option>
                                    <option v-for="type in competencyTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                            </div>
                            <div class="dictionary-list">
                                <div v-if="!filteredCompetencies.length" class="assigned-empty dictionary-empty">
                                    <div class="empty-symbol">ไม่มีข้อมูล</div>
                                    <div class="fw8">ยังไม่มีข้อมูลสมรรถนะ</div>
                                    <div class="muted fs12">เมื่อ Admin เพิ่มพจนานุกรมแล้ว รายการจะแสดงที่นี่</div>
                                </div>
                                <div v-for="item in filteredCompetencies" v-else :key="item.id" class="dictionary-row">
                                    <div>
                                        <div class="dictionary-code">{{ item.cd }} <span class="b bgr">{{ item.t }}</span></div>
                                        <div class="dictionary-name">{{ item.n }}</div>
                                        <div class="dictionary-detail truncate-2">{{ item.det || 'ไม่มีคำอธิบาย' }}</div>
                                        <div class="muted fs12">{{ item.lv || 0 }} ระดับ</div>
                                    </div>
                                    <button
                                        class="btn btn-p btn-sm"
                                        :disabled="!selectedPosition || assignedCompetencyIds.has(item.id)"
                                        type="button"
                                        @click="addCompetency(item)"
                                    >
                                        {{ assignedCompetencyIds.has(item.id) ? 'เพิ่มแล้ว' : 'เพิ่ม' }}
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </div>
                </template>

                <template v-else-if="activePage === 'hr-catalog'">
                    <div class="hr-page-head mb20">
                        <div>
                            <div class="sec-t">Learning Catalog</div>
                            <div class="sec-s">ทะเบียนกิจกรรมพัฒนา · บุคลากรเลือกกิจกรรมจาก Catalog นี้เมื่อทำ IDP</div>
                        </div>
                        <div class="hr-actions">
                            <button class="btn btn-s" disabled type="button">ดาวน์โหลด Template</button>
                            <button class="btn btn-s" type="button" @click="openModal('catalog-import')">Import Excel</button>
                            <button class="btn btn-p" type="button" @click="openModal('catalog')">เพิ่มกิจกรรม</button>
                        </div>
                    </div>

                    <div v-if="catalogMethodStats.length" class="g3 mb14">
                        <div v-for="method in catalogMethodStats" :key="method.key" class="sc">
                            <div class="sl">{{ method.label }}</div>
                            <div class="sv bc">{{ method.count }}</div>
                            <div class="ss muted">รายการ</div>
                        </div>
                    </div>
                    <div v-else class="hr-empty compact mb14">
                        <div>
                            <div class="fw8 fs14">ยังไม่มีประเภทการเรียนรู้</div>
                            <p class="muted fs13 mb0">เพิ่มประเภทการเรียนรู้จากฝั่ง Admin ก่อน แล้วหน้านี้จะแสดงสถิติ Catalog</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="ch">
                            <div class="ct">Learning Catalog</div>
                            <span class="muted fs12 ml-auto">{{ catalogItems.length }} รายการ</span>
                        </div>
                        <div class="hr-table-wrap">
                            <table class="tbl catalog-table">
                                <thead>
                                    <tr>
                                        <th>ชื่อกิจกรรม</th>
                                        <th>ประเภท</th>
                                        <th>ผู้จัด</th>
                                        <th>ค่าใช้จ่าย</th>
                                        <th>สถานะ</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody v-if="!catalogItems.length">
                                    <tr>
                                        <td colspan="6">
                                            <div class="table-empty-cell">
                                                <div>
                                                    <div class="fw8 fs14">ยังไม่มี Learning Catalog</div>
                                                    <p class="muted fs13 mb0">เมื่อมีตารางกิจกรรมพัฒนาแล้ว รายการหลักสูตรและกิจกรรมจะถูกดึงจากฐานข้อมูลมาแสดง</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr v-for="item in catalogItems" :key="item.id">
                                        <td>
                                            <div class="fw8">{{ item.name }}</div>
                                            <div class="muted fs12 truncate-2">{{ item.description || 'ไม่มีคำอธิบาย' }}</div>
                                        </td>
                                        <td><span class="b bgr">{{ item.methodLabel || '-' }}</span></td>
                                        <td>{{ item.provider || '-' }}</td>
                                        <td>{{ formatCost(item.cost) }}</td>
                                        <td>
                                            <span class="b" :class="item.isActive ? 'bgr' : 'bgy'">
                                                {{ item.isActive ? 'เปิดใช้' : 'ปิด' }}
                                            </span>
                                        </td>
                                        <td><button class="btn btn-s btn-sm" disabled type="button">แก้ไข</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </main>
        </section>

        <div v-if="activeModal" class="mo hr-modal" @click.self="closeModal">
            <div class="mo-box hr-modal-box">
                <div class="mo-h">
                    <div>
                        <template v-if="activeModal === 'competency-detail' && selectedDetailCompetency">
                            <div class="ct">{{ selectedDetailCompetency.cd }} · {{ selectedDetailCompetency.n }}</div>
                            <div class="cs">รายละเอียดสมรรถนะจากข้อมูลที่ Admin กำหนดไว้</div>
                        </template>
                        <template v-else>
                            <div v-if="activeModal === 'catalog-import'" class="ct">Import Learning Catalog</div>
                            <div v-else class="ct">เพิ่มกิจกรรม Learning Catalog</div>
                            <div class="cs">ฟอร์มนี้เตรียมไว้รอเชื่อม API และฐานข้อมูลจริง</div>
                        </template>
                    </div>
                    <button class="modal-close" type="button" @click="closeModal">×</button>
                </div>

                <form class="mo-b" @submit.prevent>
                    <template v-if="activeModal === 'competency-detail' && selectedDetailCompetency">
                        <div class="competency-detail">
                            <div class="detail-block">
                                <div class="dictionary-code">
                                    {{ selectedDetailCompetency.cd }}
                                    <span class="b bgr">{{ selectedDetailCompetency.t }}</span>
                                    <span class="muted">{{ selectedDetailCompetency.lv || 0 }} ระดับ</span>
                                </div>
                                <div class="detail-title">{{ selectedDetailCompetency.n }}</div>
                                <div class="detail-copy">{{ selectedDetailCompetency.det || 'ไม่มีคำอธิบาย' }}</div>
                            </div>

                            <div class="detail-levels">
                                <div v-if="!(selectedDetailCompetency.levels || []).length" class="assigned-empty detail-empty">
                                    <div class="empty-symbol">ไม่มีข้อมูล</div>
                                    <div class="fw8">ยังไม่มีข้อมูลระดับสมรรถนะ</div>
                                </div>
                                <div
                                    v-for="level in selectedDetailCompetency.levels"
                                    v-else
                                    :key="level.id || level.lvl"
                                    class="detail-level"
                                >
                                    <div class="detail-level-head">
                                        <div>
                                            <div class="detail-level-title">ระดับที่ {{ level.lvl }}</div>
                                            <div v-if="level.description" class="detail-copy">{{ level.description }}</div>
                                        </div>
                                    </div>
                                    <div v-if="level.indicators?.length" class="detail-indicators">
                                        <div
                                            v-for="(indicator, index) in level.indicators"
                                            :key="`${level.lvl}-${index}`"
                                            class="detail-indicator"
                                        >
                                            <div class="detail-weight">
                                                <span>น้ำหนัก</span>
                                                <strong>{{ level.weights?.[index] ?? '-' }}</strong>
                                            </div>
                                            <div class="detail-copy">{{ indicator }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="detail-copy muted">ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="activeModal === 'catalog-import'">
                        <div class="modal-grid">
                            <div class="fg">
                                <label class="lbl">ประเภทข้อมูล</label>
                                <select class="sel">
                                    <option>Learning Catalog</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label class="lbl">วิธีนำเข้า</label>
                                <select class="sel">
                                    <option>ตรวจสอบก่อนบันทึก</option>
                                    <option>นำเข้าเป็นแบบร่าง</option>
                                </select>
                            </div>
                        </div>
                        <div class="upload-box">
                            <div>
                                <div class="fw8 fs14">เลือกไฟล์ Catalog</div>
                                <div class="muted fs12">รอ API สำหรับ validate และ import ไฟล์ Excel</div>
                            </div>
                            <input class="inp" type="file" accept=".xlsx,.xls" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="modal-grid">
                            <div class="fg modal-span">
                                <label class="lbl">ชื่อกิจกรรม / หลักสูตร</label>
                                <input class="inp" placeholder="ชื่อกิจกรรมพัฒนา" />
                            </div>
                            <div class="fg">
                                <label class="lbl">ประเภท</label>
                                <select class="sel">
                                    <option v-if="!learningMethods.length">ยังไม่มีประเภทการเรียนรู้</option>
                                    <option v-for="method in learningMethods" v-else :key="method.key">{{ method.label }}</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label class="lbl">ผู้จัด</label>
                                <input class="inp" placeholder="หน่วยงาน / ผู้จัด" />
                            </div>
                            <div class="fg">
                                <label class="lbl">ค่าใช้จ่าย</label>
                                <input class="inp" placeholder="ฟรี / จำนวนเงิน" />
                            </div>
                            <div class="fg">
                                <label class="lbl">สถานะ</label>
                                <select class="sel">
                                    <option>แบบร่าง</option>
                                    <option>เปิดใช้</option>
                                    <option>ปิด</option>
                                </select>
                            </div>
                        </div>
                        <div class="fg mb0">
                            <label class="lbl">คำอธิบาย</label>
                            <textarea class="ta" placeholder="รายละเอียดกิจกรรมที่บุคลากรจะเห็นตอนทำ IDP"></textarea>
                        </div>
                    </template>

                    <div class="modal-actions">
                        <button class="btn btn-s" type="button" @click="closeModal">ยกเลิก</button>
                        <button class="btn btn-p" disabled type="submit">บันทึกเมื่อเชื่อม backend</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
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

.content .btn.btn-t {
    background: var(--teal);
    border: none;
    color: #fff;
}

.content .btn.btn-s {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text2);
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

.content .ct,
.content .sl,
.content .lbl,
.content .tbl th,
.content .fw8 {
    font-weight: 700;
}

.content .tbl td {
    font-weight: 400;
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

.hr-empty.compact {
    min-height: 96px;
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

.catalog-table {
    min-width: 820px;
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

.ml-auto {
    margin-left: auto;
}

.btn:disabled,
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

.assigned-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: #fff;
}

.assigned-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.danger-text {
    color: var(--red);
}

.empty-symbol {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 76px;
    min-height: 32px;
    border-radius: 999px;
    background: #fff;
    color: var(--blue);
    box-shadow: var(--sh);
    font-size: 12px;
    font-weight: 800;
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

.dictionary-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: #fff;
}

.dictionary-code {
    display: flex;
    gap: 6px;
    align-items: center;
    color: var(--text3);
    font-size: 12px;
    font-weight: 800;
}

.dictionary-name {
    color: var(--text);
    font-size: 14px;
    font-weight: 800;
    margin-top: 3px;
}

.dictionary-detail {
    color: var(--text3);
    font-size: 12px;
    line-height: 1.55;
    margin-top: 4px;
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

.competency-detail {
    display: grid;
    gap: 14px;
}

.detail-block {
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: var(--bg);
}

.detail-title {
    margin-top: 8px;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
    line-height: 1.35;
}

.detail-copy {
    color: var(--text3);
    font-size: 13px;
    line-height: 1.65;
}

.detail-levels {
    display: grid;
    gap: 10px;
    max-height: 56vh;
    overflow: auto;
    padding-right: 4px;
}

.detail-level {
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: #fff;
    overflow: hidden;
}

.detail-level-head {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    background: #f8fbff;
}

.detail-level-title {
    color: var(--blue);
    font-size: 14px;
    font-weight: 900;
}

.detail-indicators {
    display: grid;
}

.detail-indicator {
    display: grid;
    grid-template-columns: 84px minmax(0, 1fr);
    gap: 14px;
    padding: 12px 14px;
    border-top: 1px solid #eef3f9;
}

.detail-indicator:first-child {
    border-top: 0;
}

.detail-weight {
    color: var(--text3);
    font-size: 11px;
    font-weight: 800;
    line-height: 1.3;
}

.detail-weight strong {
    display: block;
    color: var(--blue);
    font-size: 13px;
}

.detail-empty {
    min-height: 160px;
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
    gap: 12px;
    align-items: center;
    padding: 16px;
    border: 1px dashed var(--border);
    border-radius: var(--r-lg);
    background: var(--bg);
}

.upload-box .inp {
    background: #fff;
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
    .position-hero {
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
