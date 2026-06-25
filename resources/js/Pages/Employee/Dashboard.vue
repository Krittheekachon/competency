<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
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

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'ประเมินตนเอง',
    },
});

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

const defaultLearningMethods = [
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
        color: '#c7432b',
    },
];

const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const currentPageTitle = computed(() => PAGE_TITLES[activePage.value] || props.pageTitle);
const serverCurrentUser = computed(() => page.props.currentUser || null);
const currentProfileUser = computed(() =>
    serverCurrentUser.value
    || users.value.find((user) => user.r === 'employee')
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
const assignedCompetencies = computed(() => page.props.currentUserCompetencies || []);
const competencyGaps = computed(() => page.props.currentUserCompetencyGaps || []);
const learningMethods = computed(() => page.props.learningMethods?.length ? page.props.learningMethods : defaultLearningMethods);
const learningCatalogs = computed(() => page.props.hrCatalogItems || []);
const idpLearningMethods = computed(() => page.props.idpLearningMethods || []);
const currentUserIdp = computed(() => page.props.currentUserIdp || null);
const selfAssessmentBlockReasons = computed(() => {
    const user = currentProfileUser.value || {};
    const reasons = Array.isArray(user.structureIssues) ? [...user.structureIssues] : [];
    const hasAssignedEvaluator = [user.supervisor_id_1, user.supervisor_id_2, user.supervisor_id_3]
        .some((id) => Number(id) > 0);

    if (!hasAssignedEvaluator) {
        reasons.push('ยังไม่ได้กำหนดผู้ประเมินอย่างน้อย 1 ลำดับ');
    }

    if (user.structureStatus === 'invalid' && reasons.length === 0) {
        reasons.push('ข้อมูลโครงสร้างยังต้องตรวจสอบ');
    }

    return Array.from(new Set(reasons.filter(Boolean)));
});
const isSelfAssessmentBlocked = computed(() => selfAssessmentBlockReasons.value.length > 0);

const requestPageChange = (page) => {
    activePage.value = page;
};

const goProfile = () => router.visit(route('profile.edit'));
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="currentPageTitle" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <div v-if="showSidebar" class="sidebar">
            <SidebarBrand />

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
                <PageTitleBlock :page-title="currentPageTitle" />
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <div class="content">
                <EmployeeAssess
                    v-if="activePage === 'emp-assess'"
                    :user="currentProfileUser"
                    :set-users="setRef(users)"
                    :competencies="assignedCompetencies"
                    :blocked="isSelfAssessmentBlocked"
                    :block-reasons="selfAssessmentBlockReasons"
                />

                <EmployeeGap
                    v-else-if="activePage === 'emp-gap'"
                    :set-page="requestPageChange"
                    :gaps="competencyGaps"
                    :eval-status="currentProfileUser?.evalStatus"
                />

                <EmployeeIDP
                    v-else-if="activePage === 'emp-idp'"
                    :learning-methods="learningMethods"
                    :idp-learning-methods="idpLearningMethods"
                    :learning-catalogs="learningCatalogs"
                    :gaps="competencyGaps"
                    :idp="currentUserIdp"
                    :user="currentProfileUser"
                />

                <EmployeeProgress
                    v-else-if="activePage === 'emp-progress'"
                    :activities="page.props.currentUserApprovedIdpActivities || []"
                />

                <EmployeeIDPDetail v-else-if="activePage === 'emp-idp-detail'" />

                <div v-else class="p-20 text-center text-text3">กำลังพัฒนา</div>
            </div>
        </div>
    </div>
</template>
