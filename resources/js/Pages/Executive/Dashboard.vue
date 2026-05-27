<script setup>
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
            { id: 'mgr-assessment-approval', icon: '✅', label: 'อนุมัติผลการประเมิน' },
            { id: 'mgr-idp-approval', icon: '🗂️', label: 'อนุมัติแผน IDP' },
        ],
    },
];

const pageTitles = {
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
