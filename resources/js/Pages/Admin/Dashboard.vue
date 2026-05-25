<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    DEPT_STRUCTURE,
    INITIAL_COMPETENCIES,
    INITIAL_USERS,
    NAV_CONFIG,
    PAGE_TITLES,
    ROLES_CONFIG,
} from '../../data';
import AdminDict from './AdminDict.vue';
import AdminOrg from './AdminOrg.vue';
import AdminOrgStructure from './AdminOrgStructure.vue';
import AdminUsers from './AdminUsers.vue';

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};

const showSidebar = ref(true);
const activePage = ref('admin-users');
const currentRole = ref('admin');
const users = ref(clone(INITIAL_USERS));
const competencies = ref(clone(INITIAL_COMPETENCIES));

const worklines = ref(['สายวิชาการ', 'สายสนับสนุน', 'สายงานบริหาร']);
const academicPositions = ref(['อาจารย์', 'นักวิจัย']);
const adminDepts = ref(['คณะวิศวกรรมศาสตร์']);
const competencyTypes = ref(['CC', 'MC', 'FC1', 'FC2']);
const supportOrg = ref(clone(DEPT_STRUCTURE));
const supportPositionGroups = ref({
    'สนับสนุนการศึกษาและวิชาการ': ['นักวิชาการศึกษา', 'นักวิชาการคอมพิวเตอร์', 'บรรณารักษ์'],
    'ทรัพยากรบุคคล': ['นักทรัพยากรบุคคล', 'เจ้าหน้าที่บริหารงานทั่วไป'],
    'บริหารยุทธศาสตร์': ['นักวิเคราะห์นโยบายและแผน', 'นักจัดการงานทั่วไป'],
});
const supportPositions = ref([
    'ปฏิบัติการ',
    'ปฏิบัติงาน',
    'ชำนาญการ',
    'ชำนาญงาน',
    'ชำนาญการพิเศษ',
    'เชี่ยวชาญ',
]);
const adminPositions = ref(['คณบดี', 'รองคณบดีฝ่ายบริหาร', 'รองคณบดีฝ่ายวิชาการ', 'ผู้ช่วยคณบดี']);
const academicRanks = ref(['อาจารย์', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์']);
const supportRanks = ref(['ปฏิบัติการ', 'ชำนาญการ', 'ชำนาญการพิเศษ', 'เชี่ยวชาญ']);
const learningMethods = ref([
    {
        key: 'experiential',
        label: 'Experiential Learning',
        desc: 'การเรียนรู้ผ่านประสบการณ์จากการทำงานจริง',
    },
    {
        key: 'social',
        label: 'Social Learning',
        desc: 'การเรียนรู้ผ่านบุคคลอื่นและการแลกเปลี่ยนประสบการณ์',
    },
    {
        key: 'formal',
        label: 'Formal Learning',
        desc: 'การเรียนรู้อย่างเป็นทางการผ่านหลักสูตรหรือการอบรม',
    },
]);
const orgSups = ref({
    'คณะวิศวกรรมศาสตร์': 'Manager User',
});

const supportDeptsList = computed(() => Object.keys(supportOrg.value));
const supportJobFamilies = computed(() => Object.keys(supportPositionGroups.value));
const pageTitle = computed(() => PAGE_TITLES[activePage.value] || activePage.value);
const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const currentProfileUser = computed(() => users.value.find((user) => user.r === currentRole.value) || users.value[0]);

const requestPageChange = (page) => {
    activePage.value = page;
};

const changeRoleView = (role) => {
    currentRole.value = role;
    if (role === 'admin') {
        activePage.value = 'admin-users';
        return;
    }

    const firstPage = NAV_CONFIG[role]?.[0]?.items?.[0]?.id;
    if (firstPage) activePage.value = firstPage;
};

const openModal = (type, data = null) => {
    console.info('Admin modal requested:', type, data);
};

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Admin - CIDP" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <div v-if="showSidebar" class="sidebar">
            <div class="sb-logo">
                <div class="sb-mark">คณะวิศวกรรมศาสตร์</div>
                <div class="sb-name">
                    Competency &<br />
                    IDP Management
                </div>
            </div>

            <button class="sb-user" type="button" @click="requestPageChange('admin-users')">
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
                <div v-for="(section, sectionIndex) in NAV_CONFIG[currentRole]" :key="sectionIndex">
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
                <div class="rs">
                    <span class="rs-lbl">ดูมุมมอง:</span>
                    <button
                        v-for="(role, key) in ROLES_CONFIG"
                        :key="key"
                        class="rb"
                        :class="{ on: currentRole === key }"
                        type="button"
                        @click="changeRoleView(key)"
                    >
                        {{ role.lbl }}
                    </button>
                </div>

                <AdminUsers
                    v-if="activePage === 'admin-users'"
                    :open-modal="openModal"
                    :users="users"
                    :set-users="setRef(users)"
                    :academic-depts="academicPositions"
                    :support-depts="supportDeptsList"
                    :admin-depts="adminDepts"
                    :worklines="worklines"
                />

                <AdminOrg
                    v-else-if="activePage === 'admin-org'"
                    :open-modal="openModal"
                    :users="users"
                    :set-users="setRef(users)"
                    :academic-depts="academicPositions"
                    :support-depts="supportJobFamilies"
                    :worklines="worklines"
                />

                <AdminOrgStructure
                    v-else-if="activePage === 'admin-org-structure'"
                    :academic-depts="academicPositions"
                    :set-academic-depts="setRef(academicPositions)"
                    :support-depts="supportJobFamilies"
                    :support-position-groups="supportPositionGroups"
                    :set-support-position-groups="setRef(supportPositionGroups)"
                    :admin-depts="adminDepts"
                    :set-admin-depts="setRef(adminDepts)"
                    :support-org="supportOrg"
                    :set-support-org="setRef(supportOrg)"
                    :users="users"
                    :org-sups="orgSups"
                    :set-org-sups="setRef(orgSups)"
                    :academic-pos="academicPositions"
                    :set-academic-pos="setRef(academicPositions)"
                    :support-pos="supportPositions"
                    :set-support-pos="setRef(supportPositions)"
                    :admin-pos="adminPositions"
                    :set-admin-pos="setRef(adminPositions)"
                    :academic-rank="academicRanks"
                    :set-academic-rank="setRef(academicRanks)"
                    :support-rank="supportRanks"
                    :set-support-rank="setRef(supportRanks)"
                    :worklines="worklines"
                    :set-worklines="setRef(worklines)"
                    :competency-types="competencyTypes"
                    :set-competency-types="setRef(competencyTypes)"
                    :learning-methods="learningMethods"
                    :set-learning-methods="setRef(learningMethods)"
                />

                <AdminDict
                    v-else-if="activePage === 'admin-dict'"
                    :competencies="competencies"
                    :set-competencies="setRef(competencies)"
                    :competency-types="competencyTypes"
                    :on-dirty-change="() => {}"
                />

                <div v-else class="p-20 text-center text-text3">กำลังพัฒนา</div>
            </div>
        </div>
    </div>
</template>
