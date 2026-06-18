<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
import ManagerGap from './ManagerGap.vue';
import ManagerIDP from './ManagerIDP.vue';
import ManagerAssessmentApproval from './ManagerAssessmentApproval.vue';
import ManagerIDPApproval from './ManagerIDPApproval.vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    currentUser: {
        type: Object,
        default: () => ({}),
    },
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
    pageTitle: {
        type: String,
        default: 'ภาพรวมองค์กร',
    },
});

const page = usePage();
const isSidebarOpen = ref(true);
const activePage = ref('manager-competency-overview');

const sections = [
    {
        title: 'ภาพรวมคณะ',
        items: [
            { id: 'manager-competency-overview', icon: '', label: 'Competency Gap คณะ' },
            { id: 'manager-idp-overview', icon: '', label: 'ติดตาม IDP ภาพรวม' },
        ],
    },
    {
        title: 'การอนุมัติ',
        items: [
            { id: 'manager-assessment-approval', icon: '', label: 'อนุมัติผลการประเมิน' },
            { id: 'manager-idp-approval', icon: '', label: 'อนุมัติแผน IDP' },
        ],
    },
];

const pageTitles = {
    'manager-competency-overview': 'ภาพรวมองค์กร',
    'manager-idp-overview': 'แผนพัฒนารายบุคคล',
    'manager-assessment-approval': 'อนุมัติผลการประเมิน',
    'manager-idp-approval': 'อนุมัติแผน IDP',
};

const currentPageTitle = computed(() => pageTitles[activePage.value] || props.pageTitle);
const userName = computed(() => props.currentUser?.n || page.props.auth?.user?.name || 'Manager User');
const userInitial = computed(() => userName.value?.[0] || 'M');
const approvalUsers = computed(() => {
    const currentUserId = Number(page.props.auth?.user?.id || props.currentUser?.db_id || 0);
    if (!currentUserId) return [];

    return props.users.filter((user) =>
        user?.act !== false
        && Number(user?.db_id || 0) !== currentUserId
        && [
            user?.supervisor_id_1,
            user?.supervisor_id_2,
            user?.supervisor_id_3,
        ].some((evaluatorId) => Number(evaluatorId) === currentUserId)
    );
});

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="currentPageTitle" />

    <div class="shell manager-shell">
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
                    :title="isSidebarOpen ? 'ซ่อนเมนู' : 'แสดงเมนู'"
                    type="button"
                    @click="isSidebarOpen = !isSidebarOpen"
                >
                    ☰
                </button>
                <PageTitleBlock :page-title="currentPageTitle" />
                <button class="btn btn-s btn-sm" type="button" @click="logout">ออกจากระบบ</button>
            </header>

            <main class="content">
                <ManagerGap
                    v-if="activePage === 'manager-competency-overview'"
                    :users="props.users"
                    :can-send-reminders="false"
                />
                <ManagerIDP
                    v-else-if="activePage === 'manager-idp-overview'"
                    :users="props.users"
                    :disable-mock-data="false"
                />
                <ManagerAssessmentApproval
                    v-else-if="activePage === 'manager-assessment-approval'"
                    :users="approvalUsers"
                    :current-user-id="Number(page.props.auth?.user?.id || props.currentUser?.db_id || 0)"
                />
                <ManagerIDPApproval
                    v-else-if="activePage === 'manager-idp-approval'"
                    :users="approvalUsers"
                    :current-user-id="Number(page.props.auth?.user?.id || props.currentUser?.db_id || 0)"
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
