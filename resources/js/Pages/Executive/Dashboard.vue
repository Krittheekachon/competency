<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import FacultyOverview from '../Analytics/FacultyOverview.vue';
import HeadDashboard from '../Head/Dashboard.vue';

const props = defineProps({
    activeCycleName: {
        type: String,
        default: '',
    },
    managerSummary: {
        type: Object,
        default: () => ({
            totalUsers: 0,
            evaluatedUsers: 0,
            passedUsers: 0,
            failedUsers: 0,
            pendingAssessmentApprovals: 0,
            pendingIdpApprovals: 0,
        }),
    },
    facultyAnalytics: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const isSidebarOpen = ref(true);
const activePage = ref('faculty-assessment-overview');
const fcTopicApprovalModule = computed(() => page.props.fcTopicApprovalModule || { enabled: false, items: [] });
const assessmentApprovalModule = computed(() => page.props.assessmentApprovalModule || { enabled: false, items: [] });
const idpReviewModule = computed(() => page.props.idpReviewModule || { enabled: false, assignmentCount: 0 });

const sections = computed(() => {
    const result = [
    {
        title: 'ภาพรวมคณะ',
        items: [
            { id: 'faculty-assessment-overview', icon: '', label: 'ภาพรวมการประเมิน' },
            { id: 'faculty-idp-tracking', icon: '', label: 'การติดตาม IDP' },
        ],
    },
    ];
    const assignedItems = [
        ...((fcTopicApprovalModule.value.enabled || assessmentApprovalModule.value.enabled) ? [{ id: 'manager-assessment-approval', icon: '', label: 'อนุมัติการประเมิน' }] : []),
        ...(assessmentApprovalModule.value.enabled ? [{ id: 'manager-team-assessment', icon: '', label: 'ผลการประเมินของทีม' }] : []),
        ...(idpReviewModule.value.enabled ? [{ id: 'manager-idp-approval', icon: '', label: 'อนุมัติแผนและผล IDP' }] : []),
    ];

    if (assignedItems.length) {
        result.push({ title: 'รายการที่ต้องพิจารณา', items: assignedItems });
    }

    return result;
});

const pageTitles = {
    'faculty-assessment-overview': 'ภาพรวมการประเมิน',
    'faculty-idp-tracking': 'การติดตาม IDP',
    'manager-fc-topic-approval': 'พิจารณาหัวข้อการประเมิน',
    'manager-assessment-approval': 'อนุมัติการประเมิน',
    'manager-team-assessment': 'ผลการประเมินของทีม',
    'manager-idp-approval': 'อนุมัติแผนและผล IDP',
};

const currentPageTitle = computed(() => pageTitles[activePage.value]);
const userName = computed(() => page.props.auth?.user?.name || 'Manager User');
const userInitial = computed(() => userName.value?.[0] || 'M');

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Manager - EN-IDP" />

    <div class="shell manager-shell" :class="{ 'sidebar-hidden': !isSidebarOpen }">
        <button v-if="isSidebarOpen" class="sidebar-backdrop" type="button" aria-label="ปิดเมนู" @click="isSidebarOpen = false" />
        <aside v-if="isSidebarOpen" class="sidebar">
            <SidebarBrand />

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
                    :aria-label="isSidebarOpen ? 'ซ่อนเมนู' : 'แสดงเมนู'"
                    :aria-expanded="isSidebarOpen"
                    :title="isSidebarOpen ? 'ซ่อนเมนู' : 'แสดงเมนู'"
                    type="button"
                    @click="isSidebarOpen = !isSidebarOpen"
                >
                    <span aria-hidden="true">{{ isSidebarOpen ? '‹' : '☰' }}</span>
                </button>
                <div class="tb-title">{{ currentPageTitle }}</div>
                <button class="btn btn-s btn-sm" type="button" @click="logout">ออกจากระบบ</button>
            </header>

            <main class="content">
                <FacultyOverview
                    v-if="activePage === 'faculty-assessment-overview'"
                    :analytics="props.facultyAnalytics"
                    module="assessment"
                />
                <FacultyOverview
                    v-else-if="activePage === 'faculty-idp-tracking'"
                    :analytics="props.facultyAnalytics"
                    module="idp"
                />
                <HeadDashboard
                    v-else-if="activePage === 'manager-fc-topic-approval' && fcTopicApprovalModule.enabled"
                    embedded
                    embedded-page="dh-fc-topic-approval"
                    role-key="dean"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <HeadDashboard
                    v-else-if="activePage === 'manager-assessment-approval' && (fcTopicApprovalModule.enabled || assessmentApprovalModule.enabled)"
                    embedded
                    embedded-page="dh-assess"
                    role-key="dean"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <HeadDashboard
                    v-else-if="activePage === 'manager-team-assessment' && assessmentApprovalModule.enabled"
                    embedded
                    embedded-page="sup-gap"
                    role-key="dean"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <HeadDashboard
                    v-else-if="activePage === 'manager-idp-approval' && idpReviewModule.enabled"
                    embedded
                    embedded-page="dh-idp"
                    role-key="dean"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
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
    width: calc(100% - 20px);
    max-width: calc(100% - 20px);
    box-sizing: border-box;
    border-top: 0;
    border-right: 0;
    border-bottom: 0;
    background: transparent;
    font-family: inherit;
    text-align: left;
}

.manager-menu-button {
    position: relative;
    z-index: 60;
    display: inline-grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    padding: 0;
    border: 1px solid var(--border, #cbd5e1) !important;
    border-radius: 10px;
    background: var(--bg, #fff) !important;
    color: var(--text, #172033) !important;
    box-shadow: 0 2px 7px rgba(15, 23, 42, .16);
    font-size: 23px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
}
.manager-menu-button:hover { background: var(--border, #e2e8f0) !important; box-shadow: 0 3px 10px rgba(15, 23, 42, .22); }
.manager-menu-button:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }
.sidebar-backdrop { display: none; }
.manager-shell > .sidebar { z-index: 70; }

@media (max-width: 768px) {
    .manager-shell {
        height: 100vh;
        min-height: 0;
        flex-direction: row;
        overflow: hidden;
    }

    .sidebar {
        position: fixed;
        z-index: 70;
        inset: 0 auto 0 0;
        width: min(320px, 86vw);
        max-width: none;
        height: 100dvh;
        overflow-y: auto;
        box-shadow: 12px 0 32px rgba(15, 23, 42, .24);
    }

    .sidebar-backdrop {
        position: fixed;
        z-index: 65;
        inset: 0;
        display: block;
        border: 0;
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(1px);
    }

    .main {
        width: 100%;
        flex: 1 1 100%;
        min-height: 0;
    }

    .content {
        overflow: visible;
    }
}
</style>
