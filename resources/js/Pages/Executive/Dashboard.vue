<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    managerSummary: {
        type: Object,
        required: true,
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    ManagerAssessmentApproval,
    ManagerGap,
    ManagerIDP,
    ManagerIDPApproval,
} from '../ManagerPages.vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    activeCycleName: {
        type: String,
        default: '',
    },
    departmentRows: {
        type: Array,
        default: () => [],
    },
    problemCompetencyRows: {
        type: Array,
        default: () => [],
    },
    idpProgressRows: {
        type: Array,
        default: () => [],
    },
    idpNoProgressRows: {
        type: Array,
        default: () => [],
    },
    trainingNeedRows: {
        type: Array,
        default: () => [],
    },
    assessmentApprovals: {
        type: Array,
        default: () => [],
    },
    idpApprovals: {
        type: Array,
        default: () => [],
    },
});

const logout = () => router.post(route('logout'));
const page = usePage();
const isSidebarOpen = ref(true);
const activePage = ref('manager-competency-overview');

const sections = [
    {
        title: 'ของฉัน (บุคลากร)',
        items: [
            { id: 'employee-assess', icon: '📋', label: 'ประเมินตนเอง' },
            { id: 'employee-gap', icon: '📊', label: 'ผล Competency Gap' },
            { id: 'employee-idp', icon: '📝', label: 'IDP ของฉัน' },
            { id: 'employee-progress', icon: '🔄', label: 'อัปเดตความก้าวหน้า' },
            { id: 'employee-idp-detail', icon: '📁', label: 'รายละเอียด IDP' },
        ],
    },
    {
        title: 'ภาพรวมคณะ',
        items: [
            { id: 'manager-competency-overview', icon: '📈', label: 'Competency Gap คณะ' },
            { id: 'manager-idp-overview', icon: '📉', label: 'ติดตาม IDP ภาพรวม' },
});

const page = usePage();
const isSidebarOpen = ref(true);
const activePage = ref('mgr-gap');

const sections = [
    {
        title: 'ภาพรวมคณะ',
        items: [
            { id: 'mgr-gap', icon: '📈', label: 'Competency Gap คณะ' },
            { id: 'mgr-idp', icon: '📉', label: 'ติดตาม IDP ภาพรวม' },
        ],
    },
    {
        title: 'การอนุมัติ',
        items: [
            { id: 'manager-assessment-approval', icon: '✅', label: 'อนุมัติผลการประเมิน' },
            { id: 'manager-idp-approval', icon: '🗂️', label: 'อนุมัติแผน IDP' },
            { id: 'mgr-assessment-approval', icon: '✅', label: 'อนุมัติผลการประเมิน' },
            { id: 'mgr-idp-approval', icon: '🗂️', label: 'อนุมัติแผน IDP' },
        ],
    },
];

const pageTitles = {
    'employee-assess': 'ประเมินตนเอง',
    'employee-gap': 'ผล Competency Gap',
    'employee-idp': 'IDP ของฉัน',
    'employee-progress': 'อัปเดตความก้าวหน้า',
    'employee-idp-detail': 'รายละเอียด IDP',
    'manager-competency-overview': 'Competency Gap คณะ',
    'manager-idp-overview': 'ติดตาม IDP ภาพรวม',
    'manager-assessment-approval': 'อนุมัติผลการประเมิน',
    'manager-idp-approval': 'อนุมัติแผน IDP',
};

const emptyStates = {
    'manager-competency-overview': {
        title: 'ยังไม่มีผล Competency สำหรับภาพรวม',
        body: 'รอผลการประเมินจริงก่อน หน้านี้จึงจะแสดงสถานะรายหน่วยงานและความเสี่ยงของคณะ',
    },
    'manager-idp-overview': {
        title: 'ยังไม่มีข้อมูล IDP สำหรับภาพรวม',
        body: 'เมื่อมีแผนพัฒนารายบุคคลจากระบบจริง จะแสดงสถานะและ Training Need ในหน้านี้',
    },
    'manager-assessment-approval': {
        title: 'ยังไม่มีรายการรออนุมัติผลการประเมิน',
        body: 'รายการจะปรากฏเมื่อหัวหน้างานหรือผู้บังคับบัญชาส่งผลประเมินขึ้นมาตามลำดับ',
    },
    'manager-idp-approval': {
        title: 'ยังไม่มีรายการรออนุมัติแผน IDP',
        body: 'รายการจะปรากฏเมื่อมีแผน IDP ที่ผ่านขั้นตอนก่อนหน้าและรออนุมัติระดับคณะ',
    },
    default: {
        title: 'ส่วนนี้รอหน้ากลางของบุคลากร',
        body: 'เมนูของฉันเป็นส่วนที่ใช้ร่วมกันทุกบทบาท และจะเชื่อมกับหน้าของบุคลากรเมื่อ shared page พร้อมใช้งาน',
    },
};

const currentPageTitle = computed(() => pageTitles[activePage.value]);
const currentEmptyState = computed(() => emptyStates[activePage.value] || emptyStates.default);
const userInitial = computed(() => page.props.auth.user.name?.[0] || 'ผ');
const cycleBadge = computed(() => props.activeCycleName || 'ยังไม่มีรอบประเมิน');
const totalUsers = computed(() => props.managerSummary.totalUsers ?? 0);
const evaluatedUsers = computed(() => props.managerSummary.evaluatedUsers ?? 0);
const passedUsers = computed(() => props.managerSummary.passedUsers ?? 0);
const failedUsers = computed(() => props.managerSummary.failedUsers ?? 0);
const passPercent = computed(() => (evaluatedUsers.value ? Math.round((passedUsers.value / evaluatedUsers.value) * 100) : 0));
const hasDepartmentRows = computed(() => props.departmentRows.length > 0);
const hasProblemCompetencies = computed(() => props.problemCompetencyRows.length > 0);
const hasIdpProgressRows = computed(() => props.idpProgressRows.length > 0);
const hasIdpNoProgressRows = computed(() => props.idpNoProgressRows.length > 0);
const hasTrainingNeeds = computed(() => props.trainingNeedRows.length > 0);
const hasAssessmentApprovals = computed(() => props.assessmentApprovals.length > 0);
const hasIdpApprovals = computed(() => props.idpApprovals.length > 0);
</script>

<template>
    'mgr-gap': 'Competency Gap คณะ',
    'mgr-idp': 'ติดตาม IDP ภาพรวม',
    'mgr-assessment-approval': 'อนุมัติผลการประเมิน',
    'mgr-idp-approval': 'อนุมัติแผน IDP',
};

const currentPageTitle = computed(() => pageTitles[activePage.value]);
const userName = computed(() => page.props.auth?.user?.name || 'Manager User');
const userInitial = computed(() => userName.value?.[0] || 'M');
const cycleBadge = computed(() => props.activeCycleName || 'ยังไม่มีรอบประเมิน');

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Manager - CIDP" />

    <div class="shell manager-shell">
        <aside v-if="isSidebarOpen" class="sidebar">
            <div class="sb-logo">
                <div class="sb-mark">คณะวิศวกรรมศาสตร์</div>
                <div class="sb-name">Competency &<br />IDP Management</div>
            </div>

            <button class="sb-user" type="button">
                <div class="av" style="background: var(--navy2)">{{ userInitial }}</div>
                <div style="min-width: 0">
                    <div class="u-name">{{ $page.props.auth.user.name }}</div>
            <button class="sb-user on" type="button">
                <div class="av manager-avatar">{{ userInitial }}</div>
                <div style="min-width: 0">
                    <div class="u-name">{{ userName }}</div>
                    <div class="u-role">ผู้บริหารคณะ</div>
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
                        <span class="nav-ic">{{ item.icon }}</span>
                        <span>{{ item.label }}</span>
                    </button>
                </div>
            </nav>
        </aside>

        <section class="main">
            <header class="topbar">
                <button
                    class="btn btn-s btn-sm manager-menu-button"
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
                <template v-if="activePage === 'manager-competency-overview'">
                    <div class="mb20">
                        <div>
                            <div class="sec-t manager-title">ภาพรวม Competency คณะ</div>
                            <div class="sec-s">คณะวิศวกรรมศาสตร์ · {{ cycleBadge }}</div>
                        </div>
                    </div>

                    <section class="manager-hero mb20">
                        <div class="manager-hero-grid">
                            <div class="manager-metric">
                                <div class="manager-label">บุคลากรทั้งหมด</div>
                                <div class="manager-value">{{ totalUsers }}</div>
                                <div class="manager-sub">จากตาราง users</div>
                            </div>
                            <div class="manager-metric">
                                <div class="manager-label">ประเมินแล้ว</div>
                                <div class="manager-value">{{ evaluatedUsers }}</div>
                                <div class="manager-sub">รอ {{ Math.max(totalUsers - evaluatedUsers, 0) }} คน</div>
                            </div>
                            <div class="manager-metric">
                                <div class="manager-label">ผ่านเกณฑ์</div>
                                <div class="manager-value green">{{ passedUsers }}</div>
                                <div class="manager-sub">{{ passPercent }}% ของที่ประเมิน</div>
                            </div>
                            <div class="manager-metric">
                                <div class="manager-label">ไม่ผ่านเกณฑ์</div>
                                <div class="manager-value red">{{ failedUsers }}</div>
                                <div class="manager-sub">{{ evaluatedUsers ? Math.max(100 - passPercent, 0) : 0 }}% ของที่ประเมิน</div>
                            </div>
                        </div>
                        <div class="manager-donut" :style="{ '--pct': `${passPercent * 3.6}deg` }">
                            <div class="manager-donut-inner">
                                <strong>{{ passPercent }}%</strong>
                                <span>ผ่านเกณฑ์</span>
                            </div>
                        </div>
                    </section>

                    <section class="card mb16">
                        <div class="ch manager-toolbar">
                            <div>
                                <div class="ct">ผลรายหน่วยงาน</div>
                                <div class="cs">กดที่หน่วยงานเพื่อดูรายสายงาน</div>
                            </div>
                            <div class="flex ic g6 manager-filter-block">
                                <span class="fs11 fw7 muted">สายงาน:</span>
                                <select class="sel" disabled>
                                    <option>ทุกสายงาน</option>
                                </select>
                            </div>
                            <div class="flex ic g6 manager-filter-block">
                                <span class="fs11 fw7 muted">ผลประเมิน:</span>
                                <select class="sel" disabled>
                                    <option>มีผลการประเมิน</option>
                                </select>
                            </div>
                            <div class="flex ic g6 manager-filter-block">
                                <span class="fs11 fw7 muted">เรียงตาม:</span>
                                <select class="sel" disabled>
                                    <option>ทั้งหมด</option>
                                </select>
                                <button class="btn btn-s btn-sm" type="button" disabled>⇅</button>
                            </div>
                            <div class="manager-meta">
                                <div class="manager-legend">
                                    <span>สีแถบผลประเมิน:</span>
                                    <b class="legend-dot pass"></b><span>ผ่าน</span>
                                    <b class="legend-dot fail"></b><span>ไม่ผ่าน</span>
                                    <b class="legend-dot none"></b><span>ยังไม่มีการประเมิน</span>
                                </div>
                                <div class="risk-legend">
                                    <span>เกณฑ์ความเสี่ยง:</span>
                                    <b class="risk high">ไม่ผ่าน &gt; 40% = สูง</b>
                                    <b class="risk mid">20-40% = เฝ้าระวัง</b>
                                    <b class="risk low">&lt; 20% = อยู่ในเกณฑ์ดี</b>
                                    <span class="muted">0 คนประเมิน = ยังไม่มีการประเมิน</span>
                                </div>
                            </div>
                        </div>
                        <div class="manager-dept-list">
                            <div class="manager-dept-head">
                                <span></span>
                                <span>หน่วยงาน</span>
                                <span>ผลประเมิน</span>
                                <span>สัดส่วนผ่าน / ไม่ผ่าน</span>
                                <span>สถานะ</span>
                                <span></span>
                            </div>
                            <div v-if="!hasDepartmentRows" class="manager-empty-row">
                                <div class="manager-empty-message">
                                    <div class="manager-empty-icon">∅</div>
                                    <div>
                                        <div class="fw8 fs14">{{ currentEmptyState.title }}</div>
                                        <p class="muted fs13 mb0">{{ currentEmptyState.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card">
                        <div class="ch">
                            <div>
                                <div class="ct">สมรรถนะที่มีปัญหา แยกตามสายงาน</div>
                                <div class="cs">CC = สมรรถนะหลัก · MC = สมรรถนะการบริหาร · FC = สมรรถนะตามสายงาน · กดที่รายการเพื่อดูว่ามาจากหน่วยงานใดบ้าง</div>
                            </div>
                        </div>
                        <div class="problem-body">
                            <div class="problem-warning">⚠ บุคลากร 1 คนสามารถไม่ผ่านได้หลายสมรรถนะ ผลรวมอาจสูงกว่าจำนวนจริง</div>
                            <div v-if="!hasProblemCompetencies" class="problem-empty">
                                <div class="manager-empty-icon">∅</div>
                                <div>
                                    <div class="fw8 fs14">ยังไม่มีสมรรถนะที่มีปัญหา</div>
                                    <p class="muted fs13 mb0">รอผลการประเมินจริงก่อนจึงจะแยกปัญหาตามสายงานได้</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>

                <template v-else-if="activePage === 'manager-idp-overview'">
                    <div class="mb20">
                        <div class="sec-t">ภาพรวม IDP คณะ 📋</div>
                        <div class="sec-s">สถานะ IDP ของบุคลากรทั้งคณะวิศวกรรมศาสตร์ · {{ cycleBadge }}</div>
                    </div>

                    <section class="card idp-summary-card mb14">
                        <div class="cb">
                            <div class="idp-summary-row">
                                <div class="idp-summary-main">
                                    <div class="fw7 fs12 muted mb6">บุคลากรไม่ผ่านเกณฑ์ที่มี IDP แล้ว</div>
                                    <div class="idp-summary-number">
                                        <span>0</span>
                                        <em>/ {{ failedUsers }} คน</em>
                                        <b>0%</b>
                                    </div>
                                    <div class="fs12 muted mt4">ยังไม่ได้ทำ IDP อีก <span class="fw7 rc">{{ failedUsers }} คน</span></div>
                                </div>

                                <div class="idp-summary-status">
                                    <div class="fs11 fw7 muted mb6">สัดส่วนตามสถานะ</div>
                                    <div class="idp-status-bar">
                                        <span class="completed"></span>
                                        <span class="in-progress"></span>
                                        <span class="submitted"></span>
                                        <span class="draft"></span>
                                        <span class="missing"></span>
                                    </div>
                                    <div class="idp-legend">
                                        <span><i class="completed"></i>เสร็จสิ้น <b>0</b></span>
                                        <span><i class="in-progress"></i>กำลังดำเนินการ <b>0</b></span>
                                        <span><i class="submitted"></i>รออนุมัติ <b>0</b></span>
                                        <span><i class="draft"></i>Draft <b>0</b></span>
                                        <span class="rc"><i class="missing"></i>ไม่มี IDP <b>{{ failedUsers }}</b></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="g4 mb14">
                        <div class="sc"><div class="sl">เสร็จสิ้น</div><div class="sv gcc">0</div><div class="ss muted">คน</div></div>
                        <div class="sc"><div class="sl">กำลังดำเนินการ</div><div class="sv bc">0</div><div class="ss muted">คน</div></div>
                        <div class="sc"><div class="sl">รออนุมัติ</div><div class="sv yc">{{ props.managerSummary.pendingIdpApprovals ?? 0 }}</div><div class="ss muted">คน</div></div>
                        <div class="sc">
                            <div class="sl">Draft / ยังไม่มี IDP</div>
                            <div class="sv rc">{{ failedUsers }}</div>
                            <div class="ss muted">Draft 0 · ไม่มี IDP {{ failedUsers }}</div>
                        </div>
                    </div>

                    <div class="g2 mb14">
                        <div class="card">
                            <div class="ch"><div class="ct">ความคืบหน้า IDP รายหน่วยงาน</div></div>
                            <div class="cb idp-panel-body">
                                <div v-if="!hasIdpProgressRows" class="manager-empty compact">
                                    <div class="manager-empty-icon">∅</div>
                                    <div>
                                        <div class="fw8 fs14">{{ currentEmptyState.title }}</div>
                                        <p class="muted fs13 mb0">{{ currentEmptyState.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="ch">
                                <div>
                                    <div class="ct">บุคลากรที่ยังไม่มีความคืบหน้า IDP</div>
                                    <div class="cs">0 คน</div>
                                </div>
                                <button class="btn btn-p btn-sm ml-auto" type="button" disabled>แจ้งเตือนทั้งหมด</button>
                            </div>
                            <div class="cb idp-panel-body">
                                <div v-if="!hasIdpNoProgressRows" class="manager-empty compact">
                                    <div class="manager-empty-icon">∅</div>
                                    <div>
                                        <div class="fw8 fs14">ยังไม่มีรายการที่ต้องติดตาม</div>
                                        <p class="muted fs13 mb0">เมื่อมีบุคลากรที่ยังไม่เริ่มหรือไม่มีความคืบหน้า IDP รายการจะแสดงที่นี่</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="activePage === 'manager-assessment-approval'">
                    <div class="mb20">
                        <div class="sec-t">อนุมัติผลการประเมินรายบุคคล</div>
                        <div class="sec-s">ตรวจสอบผู้ถูกประเมิน หัวหน้างาน และผู้บังคับบัญชา ก่อนยืนยันผลการประเมิน</div>
                    </div>

                    <div class="card">
                        <div class="ch">
                            <div class="ct">รายการผลการประเมินที่รอยืนยัน</div>
                            <span class="b bgr ml-auto">{{ props.managerSummary.pendingAssessmentApprovals ?? 0 }} รายการ</span>
                        </div>
                        <div class="cb approval-table-body">
                            <table class="tbl manager-table">
                                <thead>
                                    <tr>
                                        <th>ผู้ถูกประเมิน</th>
                                        <th>หัวหน้างาน</th>
                                        <th>ผู้บังคับบัญชา</th>
                                        <th class="score-col">คะแนน</th>
                                        <th class="status-col">สถานะ</th>
                                        <th class="action-col">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody v-if="!hasAssessmentApprovals">
                                    <tr>
                                        <td colspan="6">
                                            <div class="table-empty-cell">
                                                <div class="manager-empty-icon">∅</div>
                                                <div>
                                                    <div class="fw8 fs14">{{ currentEmptyState.title }}</div>
                                                    <p class="muted fs13 mb0">{{ currentEmptyState.body }}</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <template v-else-if="activePage === 'manager-idp-approval'">
                    <div class="mb20">
                        <div class="sec-t">อนุมัติแผน IDP รายบุคคล</div>
                        <div class="sec-s">ตรวจสอบแผน IDP จากหัวหน้างานและผู้บังคับบัญชา ก่อนยืนยันแผนพัฒนารายบุคคล</div>
                    </div>

                    <div class="card">
                        <div class="ch">
                            <div class="ct">รายการแผน IDP ที่รอยืนยัน</div>
                            <span class="b bgr ml-auto">{{ props.managerSummary.pendingIdpApprovals ?? 0 }} รายการ</span>
                        </div>
                        <div class="cb approval-table-body">
                            <table class="tbl manager-table">
                                <thead>
                                    <tr>
                                        <th>ผู้ถูกประเมิน</th>
                                        <th>หัวหน้างาน</th>
                                        <th>ผู้บังคับบัญชา</th>
                                        <th>หัวข้อ IDP</th>
                                        <th class="status-col">สถานะ</th>
                                        <th class="action-col">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody v-if="!hasIdpApprovals">
                                    <tr>
                                        <td colspan="6">
                                            <div class="table-empty-cell">
                                                <div class="manager-empty-icon">∅</div>
                                                <div>
                                                    <div class="fw8 fs14">{{ currentEmptyState.title }}</div>
                                                    <p class="muted fs13 mb0">{{ currentEmptyState.body }}</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="card">
                        <div class="ch"><div class="ct">{{ currentPageTitle }}</div></div>
                        <div class="cb">
                            <div class="manager-empty">
                                <div class="manager-empty-icon">∅</div>
                                <div>
                                    <div class="fw8 fs14">{{ currentEmptyState.title }}</div>
                                    <p class="muted fs13 mb0">{{ currentEmptyState.body }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <ManagerGap v-if="activePage === 'mgr-gap'" :users="props.users" />
                <ManagerIDP v-else-if="activePage === 'mgr-idp'" :users="props.users" />
                <ManagerAssessmentApproval
                    v-else-if="activePage === 'mgr-assessment-approval'"
                    :users="props.users"
                />
                <ManagerIDPApproval v-else :users="props.users" />
            </main>
        </section>
    </div>
</template>

<style scoped>
.manager-shell {
    font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
    font-synthesis-weight: none;
    letter-spacing: 0;
}

.manager-avatar {
    background: var(--navy);
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

.manager-menu-button {
    min-width: 40px;
    justify-content: center;
    border: 0;
    background: transparent;
    padding: 8px;
}

@media (max-width: 768px) {
    .manager-shell {
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
}
</style>
