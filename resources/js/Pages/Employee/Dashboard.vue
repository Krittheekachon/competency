<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    INITIAL_USERS,
    NAV_CONFIG,
    PAGE_TITLES,
    ROLES_CONFIG,
} from '../../data';
import EmployeeAssess from './EmployeeAssess.vue';
import EmployeeGap from './EmployeeGap.vue';
import EmployeeIDP from './EmployeeIDP.vue';
import EmployeeIDPDetail from './EmployeeIDPDetail.vue';
import EmployeeProgress from './EmployeeProgress.vue';

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const requestedPage = typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null;
const implementedEmployeePages = new Set([
    'emp-assess',
    'emp-gap',
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
]);

const showSidebar = ref(true);
const activePage = ref(implementedEmployeePages.has(requestedPage) ? requestedPage : 'emp-assess');
const currentRole = ref('employee');
const users = ref(clone(INITIAL_USERS));
const page = usePage();

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

const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const pageTitle = computed(() => PAGE_TITLES[activePage.value] || activePage.value);
const currentProfileUser = computed(() =>
    users.value.find((user) => user.r === 'employee')
    || users.value.find((user) => user.sso === '64020')
    || {
        n: page.props.auth?.user?.name || 'Employee User',
        t: '',
        sso: page.props.auth?.user?.id || 'current-user',
        p: '',
        l: '',
        w: '',
        r: 'employee',
        act: true,
    },
);

const requestPageChange = (page) => {
    activePage.value = page;
};

const goProfile = () => router.visit(route('profile.edit'));
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Employee - CIDP" />

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
                    {{ currentProfileUser?.n?.[0] || currentRoleData.av }}
                </div>
                <div style="overflow: hidden; min-width: 0">
                    <div class="u-name">
                        {{ currentProfileUser ? `${currentProfileUser.t}${currentProfileUser.n}` : currentRoleData.name }}
                    </div>
                    <div class="u-role">{{ currentProfileUser?.p || currentRoleData.pos }}</div>
                </div>
            </button>

            <div class="sb-nav">
                <div v-for="(section, sectionIndex) in NAV_CONFIG.employee" :key="sectionIndex">
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
                <button
                    class="btn btn-s btn-sm"
                    style="padding: 8px; min-width: 40px; justify-content: center; border: none; background: transparent"
                    type="button"
                    @click="showSidebar = !showSidebar"
                >
                    ☰
                </button>
                <div class="tb-title">{{ pageTitle }}</div>
                <span class="tb-badge">รอบประเมิน 2568</span>
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <div class="content">
                <EmployeeAssess
                    v-if="activePage === 'emp-assess'"
                    :user="currentProfileUser"
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

                <div v-else class="p-20 text-center text-text3">กำลังพัฒนา</div>
            </div>
        </div>
    </div>
</template>
