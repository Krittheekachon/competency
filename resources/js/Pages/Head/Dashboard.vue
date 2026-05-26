<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import { NAV_CONFIG, PAGE_TITLES, ROLES_CONFIG } from '../../data';
import EmployeeAssess from '../Staff/EmployeeAssess.vue';
import EmployeeGap from '../Staff/EmployeeGap.vue';
import EmployeeIDP from '../Staff/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Staff/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Staff/EmployeeProgress.vue';

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const normalizeRoleKey = (role) => role === 'dept_head' ? 'manager_dept' : role;

const page = usePage();
const users = ref(clone(page.props.users || []));
const requestedPage = ref(typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null);
const rememberedHeadState = useRemember({
    showSidebar: true,
    activePage: requestedPage.value || 'dh-idp',
}, 'HeadDashboardStable');

const showSidebar = computed({
    get: () => rememberedHeadState.value.showSidebar !== false,
    set: (value) => {
        rememberedHeadState.value.showSidebar = value;
    },
});
const activePage = computed({
    get: () => rememberedHeadState.value.activePage || 'dh-idp',
    set: (value) => {
        rememberedHeadState.value.activePage = value;
    },
});

const learningMethods = ref([
    {
        key: 'experiential',
        label: 'Experiential Learning',
        desc: 'การเรียนรู้ผ่านประสบการณ์จากการทำงานจริง',
        color: '#ea580c',
    },
    {
        key: 'social',
        label: 'Social Learning',
        desc: 'การเรียนรู้ผ่านบุคคลอื่นและการแลกเปลี่ยนประสบการณ์',
        color: '#16a34a',
    },
    {
        key: 'formal',
        label: 'Formal Learning',
        desc: 'การเรียนรู้อย่างเป็นทางการผ่านหลักสูตรหรือการอบรม',
        color: '#2563eb',
    },
]);

const authRoleKey = computed(() => normalizeRoleKey(page.props.auth?.user?.role_key || 'manager_dept'));
const currentRoleData = computed(() => ROLES_CONFIG[authRoleKey.value] || ROLES_CONFIG.manager_dept || ROLES_CONFIG.supervisor);
const navSections = computed(() => NAV_CONFIG.supervisor || []);
const pageTitle = computed(() => PAGE_TITLES[activePage.value] || activePage.value);
const authUserId = computed(() => page.props.auth?.user?.id ? String(page.props.auth.user.id) : '');
const authUserName = computed(() => page.props.auth?.user?.name || '');
const implementedPages = new Set([
    'emp-assess',
    'emp-gap',
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
    'dh-assess',
    'sup-gap',
    'dh-idp',
]);

const currentUser = computed(() =>
    users.value.find((user) => user.sso === authUserId.value)
    || users.value.find((user) => user.n === authUserName.value)
    || users.value.find((user) => ['supervisor', 'manager_dept', 'dept_head'].includes(user.r))
    || {
        sso: authUserId.value || 'current-head',
        t: '',
        n: authUserName.value || currentRoleData.value.name,
        p: currentRoleData.value.pos,
        d: '',
        r: authRoleKey.value,
        act: true,
    },
);

const teamMembers = computed(() => {
    return users.value.filter((user) =>
        user.sso !== currentUser.value.sso
        && (user.sup === currentUser.value.n || user.evaluator2 === currentUser.value.n)
        && !['manager_dept', 'dept_head', 'manager'].includes(user.r)
        && user.act !== false,
    );
});

const normalizeIdpPhase = (user) => user.idpPhase || user.idp_status || user.idpStatus || 'notsent';
const normalizeGaps = (user) => {
    const gaps = user.gaps || user.failedCompetencies || user.competencyGaps || [];
    if (!Array.isArray(gaps)) return [];

    return gaps
        .map((gap) => typeof gap === 'string' ? gap : (gap.n || gap.name || gap.title || gap.competency_name || ''))
        .filter(Boolean);
};

const idpRows = computed(() => teamMembers.value.map((user) => ({
    ...user,
    phase: normalizeIdpPhase(user),
})));

const assessCounts = computed(() => ({
    total: teamMembers.value.length,
    notSent: teamMembers.value.filter((user) => user.evalStatus === 'draft' || !user.evalStatus).length,
    pending: teamMembers.value.filter((user) => user.evalStatus === 'self_submitted').length,
    forwarded: teamMembers.value.filter((user) => ['unit_evaluated', 'dept_evaluated'].includes(user.evalStatus)).length,
    done: teamMembers.value.filter((user) => user.evalStatus === 'dean_approved').length,
}));

const gapRows = computed(() => teamMembers.value.map((user) => ({
    ...user,
    pending: user.evalStatus === 'draft' || !user.evalStatus,
    gaps: normalizeGaps(user),
})));
const assessedGapRows = computed(() => gapRows.value.filter((user) => !user.pending));
const foundGapRows = computed(() => assessedGapRows.value.filter((user) => user.gaps.length));
const gapCompetencies = computed(() => Object.values(
    foundGapRows.value
        .flatMap((user) => user.gaps)
        .reduce((items, name) => {
            items[name] = items[name] || { type: '-', name, count: 0 };
            items[name].count += 1;
            return items;
        }, {}),
));

const activeIdpTab = ref('notsent');
const idpTabs = computed(() => [
    { id: 'notsent', label: 'ยังไม่ส่งแผน', count: idpRows.value.filter((row) => row.phase === 'notsent').length, cls: '' },
    { id: 'pending', label: 'รอการตรวจ/ยืนยัน', count: idpRows.value.filter((row) => row.phase === 'pending').length, cls: 'yc' },
    { id: 'rejected', label: 'แผนไม่ผ่าน', count: 0, cls: 'rc' },
    { id: 'inprogress', label: 'ระหว่างดำเนินการ', count: 0, cls: 'bc' },
    { id: 'done', label: 'เสร็จสิ้น', count: 0, cls: 'gcc' },
]);
const visibleIdpRows = computed(() => idpRows.value.filter((row) => row.phase === activeIdpTab.value));

watchEffect(() => {
    if (requestedPage.value && implementedPages.has(requestedPage.value)) {
        activePage.value = requestedPage.value;
        requestedPage.value = null;
    }

    if (!implementedPages.has(activePage.value)) {
        activePage.value = 'dh-idp';
    }

    if (page.props.users?.length) {
        users.value = clone(page.props.users);
    }
});

const requestPageChange = (nextPage) => {
    activePage.value = nextPage;
};

const goProfile = () => router.visit(route('profile.edit'));
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Head - CIDP" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <div v-if="showSidebar" class="sidebar">
            <div class="sb-logo">
                <div class="sb-mark">คณะวิศวกรรมศาสตร์</div>
                <div class="sb-name">
                    Competency &<br />
                    IDP Management
                </div>
            </div>

            <button class="sb-user on" type="button" @click="goProfile">
                <div class="av" :style="{ background: currentRoleData.col }">
                    {{ currentUser.n?.[0] || currentRoleData.av }}
                </div>
                <div style="overflow: hidden; min-width: 0">
                    <div class="u-name">{{ `${currentUser.t || ''}${currentUser.n}` }}</div>
                    <div class="u-role">{{ currentUser.p || currentRoleData.pos }}</div>
                </div>
            </button>

            <div class="sb-nav">
                <div v-for="(section, sectionIndex) in navSections" :key="sectionIndex">
                    <div class="nav-sec">{{ section.sec }}</div>
                    <div
                        v-for="item in section.items"
                        :key="item.id"
                        class="nav-item"
                        :class="{ on: activePage === item.id }"
                        @click="requestPageChange(item.id)"
                    >
                        <span class="nav-ic">{{ item.ic }}</span>
                        {{ item.lb }}
                    </div>
                </div>
            </div>
        </div>

        <div class="main">
            <div class="topbar">
                <button class="btn btn-s btn-sm menu-btn" type="button" @click="showSidebar = !showSidebar">☰</button>
                <div class="tb-title">{{ pageTitle }}</div>
                <span class="tb-badge">รอบประเมิน 2568</span>
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <div class="content">
                <EmployeeAssess
                    v-if="activePage === 'emp-assess'"
                    :user="currentUser"
                    :set-users="setRef(users)"
                />

                <EmployeeGap
                    v-else-if="activePage === 'emp-gap'"
                    :set-page="requestPageChange"
                />

                <EmployeeIDP
                    v-else-if="activePage === 'emp-idp'"
                    :learning-methods="learningMethods"
                />

                <EmployeeProgress v-else-if="activePage === 'emp-progress'" />

                <EmployeeIDPDetail v-else-if="activePage === 'emp-idp-detail'" />

                <template v-else-if="activePage === 'dh-idp'">
                    <div class="mb20">
                        <div class="sec-t">IDP & ติดตามทีม</div>
                        <div class="sec-s">ติดตามความก้าวหน้าแผนพัฒนาบุคลากรในมุมมองหัวหน้างาน</div>
                    </div>

                    <div class="g4 idp-summary mb20">
                        <button
                            v-for="tab in idpTabs"
                            :key="tab.id"
                            class="sc stat-button"
                            :class="{ selected: activeIdpTab === tab.id }"
                            type="button"
                            @click="activeIdpTab = tab.id"
                        >
                            <div class="sl">{{ tab.label }}</div>
                            <div class="sv" :class="tab.cls">{{ tab.count }}</div>
                            <div class="ss muted">คน</div>
                        </button>
                    </div>

                    <div class="tab-bar">
                        <div
                            v-for="tab in idpTabs"
                            :key="tab.id"
                            class="tab"
                            :class="{ on: activeIdpTab === tab.id }"
                            @click="activeIdpTab = tab.id"
                        >
                            {{ tab.label }} <span class="b" :class="tab.cls || 'bgr'">{{ tab.count }}</span>
                        </div>
                    </div>

                    <div v-for="row in visibleIdpRows" :key="row.sso" class="card team-row">
                        <div class="av row-avatar">{{ row.n[0] }}</div>
                        <div class="row-main">
                            <div class="fw8 fs14">{{ `${row.t || ''}${row.n}` }}</div>
                            <div class="muted fs12">{{ row.p }} · {{ row.d || row.p }}</div>
                        </div>
                        <span class="b" :class="row.phase === 'notsent' ? 'bgr' : 'by'">
                            {{ row.phase === 'notsent' ? 'ยังไม่ส่งแผน' : 'รอการตรวจ/ยืนยัน' }}
                        </span>
                        <button class="btn btn-s btn-sm" type="button">ดูข้อมูล</button>
                    </div>

                    <div v-if="visibleIdpRows.length === 0" class="card empty-card">
                        ไม่มีรายการในสถานะนี้
                    </div>
                </template>

                <template v-else-if="activePage === 'sup-gap'">
                    <div class="mb20">
                        <div class="sec-t">Competency Gap ทีม</div>
                        <div class="sec-s">วิเคราะห์ผลการประเมินและจุดอ่อนของทีม</div>
                    </div>

                    <div v-if="assessedGapRows.length === 0" class="card empty-card">
                        ยังไม่ได้รับผลการประเมินจากผู้ใต้บังคับบัญชา
                    </div>

                    <template v-else>
                    <div class="g3 mb20">
                        <div class="sc navy-top"><div class="sl">บุคลากรทั้งหมด</div><div class="sv">{{ gapRows.length }}</div><div class="ss muted">คน</div></div>
                        <div class="sc blue-top"><div class="sl">ประเมินแล้ว</div><div class="sv bc">{{ assessedGapRows.length }}</div><div class="ss muted">คน</div></div>
                        <div class="sc red-top"><div class="sl">พบ Gap</div><div class="sv rc">{{ foundGapRows.length }}</div><div class="ss muted">คน</div></div>
                    </div>

                    <div class="g2 team-grid">
                        <div class="card">
                            <div class="ch"><div class="ct">สมรรถนะที่ทีมต้องพัฒนา</div><div class="cs">เรียงตามจำนวนคนที่ไม่ผ่านเกณฑ์</div></div>
                            <div v-for="comp in gapCompetencies" :key="comp.name" class="gap-item">
                                <span class="tag-cc">{{ comp.type }}</span>
                                <div class="gap-main">
                                    <div class="gap-title">
                                        <span class="fw8 fs13">{{ comp.name }}</span>
                                        <span class="rc fw8 fs12">{{ comp.count }} คน</span>
                                    </div>
                                    <div class="pw mt8"><div class="pb red-bar" /></div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="ch"><div class="ct">รายชื่อและสถานะ Gap รายบุคคล</div></div>
                            <div v-for="person in gapRows" :key="person.sso" class="gap-person">
                                <div class="av row-avatar" :class="{ mutedAvatar: person.pending }">{{ person.n[0] }}</div>
                                <div class="row-main">
                                    <div class="fw8 fs13" :class="{ muted: person.pending }">{{ `${person.t || ''}${person.n}` }}</div>
                                    <div v-if="person.pending" class="muted fs11">รอการประเมิน</div>
                                    <div v-else-if="person.gaps.length" class="flex g5 mt6" style="flex-wrap: wrap">
                                        <span v-for="gap in person.gaps" :key="gap" class="b br">△ {{ gap }}</span>
                                    </div>
                                    <div v-else class="gcc fs11 mt4">ผ่านทุกข้อ</div>
                                </div>
                                <span v-if="!person.pending" class="bc fw8 fs13">ดู →</span>
                            </div>
                        </div>
                    </div>
                    </template>
                </template>

                <template v-else-if="activePage === 'dh-assess'">
                    <div v-if="teamMembers.length === 0" class="card empty-card">
                        ยังไม่มีผู้ใต้บังคับบัญชาในความดูแล
                    </div>

                    <template v-else>
                    <div class="g4 assess-summary mb20">
                        <div class="sc navy-top"><div class="sl">ทั้งหมด</div><div class="sv">{{ assessCounts.total }}</div><div class="ss muted">คนในความดูแล</div></div>
                        <div class="sc red-top"><div class="sl">ยังไม่ส่ง</div><div class="sv rc">{{ assessCounts.notSent }}</div><div class="ss muted">รอลูกน้องประเมินตนเอง</div></div>
                        <div class="sc yellow-top"><div class="sl">รอคุณประเมิน</div><div class="sv yc">{{ assessCounts.pending }}</div><div class="ss muted">คน</div></div>
                        <div class="sc green-top"><div class="sl">เสร็จสมบูรณ์</div><div class="sv gcc">{{ assessCounts.done }}</div><div class="ss muted">คน</div></div>
                    </div>

                    <div class="flex ic jb mb20">
                        <div>
                            <div class="sec-t">ประเมินลูกน้อง</div>
                            <div class="sec-s">พิจารณาข้อมูลที่บุคลากรส่งมา และให้คะแนนหัวหน้างาน</div>
                        </div>
                        <span class="b by">รอประเมิน {{ assessCounts.pending }} คน</span>
                    </div>

                    <div class="g2 assess-grid">
                        <div class="card">
                            <div class="ch"><div class="ct">ผู้ส่งแบบประเมินแล้ว</div></div>
                            <div v-for="person in teamMembers" :key="person.sso" class="team-row compact">
                                <div class="av row-avatar">{{ person.n[0] }}</div>
                                <div class="row-main">
                                    <div class="fw8 fs13">{{ `${person.t || ''}${person.n}` }}</div>
                                    <div class="muted fs11">{{ person.p }}</div>
                                </div>
                                <span class="b" :class="person.evalStatus === 'self_submitted' ? 'by' : 'br'">
                                    {{ person.evalStatus === 'self_submitted' ? 'รอหัวหน้าฝ่าย' : 'ยังไม่ส่ง' }}
                                </span>
                            </div>
                        </div>
                        <div class="card empty-panel">
                            <div class="target-icon">🎯</div>
                            <div class="muted fw8">เลือกบุคลากรจากรายการด้านซ้ายเพื่อเริ่มประเมิน</div>
                        </div>
                    </div>
                    </template>
                </template>

                <div v-else class="card empty-card">
                    หน้านี้กำลังเชื่อมต่อข้อมูลสำหรับหัวหน้างาน
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.menu-btn {
    padding: 8px;
    min-width: 40px;
    justify-content: center;
    border: none;
    background: transparent;
}

.stat-button {
    text-align: left;
    cursor: pointer;
    font-family: inherit;
}

.stat-button.selected {
    border-color: var(--blue);
}

.idp-summary {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.assess-summary {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.team-row,
.gap-person {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    margin-bottom: 10px;
}

.team-row.compact {
    margin: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    box-shadow: none;
}

.row-avatar {
    width: 44px;
    height: 44px;
    background: var(--navy);
    font-size: 16px;
    flex-shrink: 0;
}

.mutedAvatar {
    background: var(--text3);
}

.row-main {
    flex: 1;
    min-width: 0;
}

.team-grid,
.assess-grid {
    grid-template-columns: 1fr 1fr;
    align-items: stretch;
}

.gap-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px;
    border-bottom: 1px solid var(--border);
}

.gap-main {
    flex: 1;
}

.gap-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.red-bar {
    width: 50%;
    background: var(--red);
}

.empty-card,
.empty-panel {
    text-align: center;
    padding: 42px 24px;
}

.empty-panel {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.target-icon {
    font-size: 46px;
    margin-bottom: 14px;
}

.navy-top { border-top: 3px solid var(--navy); }
.blue-top { border-top: 3px solid var(--blue); }
.red-top { border-top: 3px solid var(--red); }
.yellow-top { border-top: 3px solid var(--yellow); }
.green-top { border-top: 3px solid var(--green); }
</style>
