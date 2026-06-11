<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import {
    NAV_CONFIG,
    PAGE_TITLES,
    ROLES_CONFIG,
} from '../../data';
import AdminDict from './AdminDict.vue';
import AdminIdpTools from './AdminIdpTools.vue';
import AdminOrg from './AdminOrg.vue';
import AdminOrgStructure from './AdminOrgStructure.vue';
import AdminUsers from './AdminUsers.vue';
import EmployeeAssess from '../Employee/EmployeeAssess.vue';
import EmployeeGap from '../Employee/EmployeeGap.vue';
import EmployeeIDP from '../Employee/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Employee/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Employee/EmployeeProgress.vue';

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const supportOrgFromGroups = (groups = {}) => Object.fromEntries(
    Object.entries(groups || {}).map(([dept, works]) => [
        dept,
        (Array.isArray(works) ? works : []).map((work) => ({ work, units: [] })),
    ]),
);
const adminPageStorageKey = 'admin-active-page';
const requestedPage = ref(typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null);
const savedAdminPage = typeof window !== 'undefined'
    ? window.sessionStorage.getItem(adminPageStorageKey)
        || window.sessionStorage.getItem('cidp.admin.activePage')
    : null;

const rememberedAdminState = useRemember({
    showSidebar: true,
    activePage: requestedPage.value || savedAdminPage || 'admin-users',
}, 'AdminDashboard');
const showSidebar = computed({
    get: () => rememberedAdminState.value.showSidebar !== false,
    set: (value) => {
        rememberedAdminState.value.showSidebar = value;
    },
});
const activePage = computed({
    get: () => rememberedAdminState.value.activePage,
    set: (value) => {
        rememberedAdminState.value.activePage = value;
    },
});
const currentRole = ref('admin');
const page = usePage();
const competencies = ref(clone(page.props.competencies || []));
const users = ref(clone(page.props.users || []));
const activeModal = ref(null);
const editingUserKey = ref(null);
const isSavingUser = ref(false);
const orgEditMode = ref(false);
const modalReturnPage = ref('admin-users');
const userForm = ref({
    db_id: null,
    sso: '',
    t: '',
    n: '',
    fn: '',
    ln: '',
    fe: '',
    le: '',
    em: '',
    ph: '',
    w: '',
    d: '',
    dept: '',
    job: '',
    unit: '',
    p: '',
    l: '',
    r: 'employee',
    supervisor_id_1: '',
    supervisor_id_2: '',
    supervisor_id_3: '',
    act: true,
    structureStatus: 'ok',
    structureIssues: [],
});

const worklines = ref(clone(page.props.worklines || []));
const jobFamiliesByWorkline = ref(clone(page.props.jobFamiliesByWorkline || {}));
const academicPositions = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายวิชาการ'] || {})));
const adminDepts = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายงานบริหาร'] || {})));
const competencyTypes = ref(clone(page.props.competencyTypes || []));
const supportPositionGroups = ref(clone(jobFamiliesByWorkline.value['สายสนับสนุน'] || page.props.supportPositionGroups || {}));
const supportOrg = ref(clone(page.props.supportOrg || supportOrgFromGroups(supportPositionGroups.value)));
const supportPositions = ref([]);
const adminPositions = ref(clone(page.props.adminJobFamilies || []));
const levelsByWorkline = ref(clone(page.props.levelsByWorkline || {}));
const levelExpectationsByWorkline = ref(clone(page.props.levelExpectationsByWorkline || {}));
const levelsByJobFamily = ref(clone(page.props.levelsByJobFamily || {}));
const levelExpectationsByJobFamily = ref(clone(page.props.levelExpectationsByJobFamily || {}));
const academicRanks = ref(clone(levelsByWorkline.value['สายวิชาการ'] || []));
const supportRanks = ref(clone(levelsByWorkline.value['สายสนับสนุน'] || []));
const learningMethods = ref(clone(page.props.learningMethods || []));
const hrCatalogItems = computed(() => page.props.hrCatalogItems || []);
const idpLearningMethods = computed(() => page.props.idpLearningMethods || []);
const roleOptions = computed(() => page.props.roles || [
    { id: 0, key: 'admin', label: 'ผู้ดูแลระบบ' },
    { id: 1, key: 'supervisor', label: 'หัวหน้างาน' },
    { id: 2, key: 'dept_head', label: 'ผู้บังคับบัญชา' },
    { id: 3, key: 'employee', label: 'บุคลากร' },
    { id: 4, key: 'hr', label: 'งานทรัพยากรบุคคล' },
    { id: 5, key: 'dean', label: 'ผู้บริหารคณะ' },
]);
const orgSups = ref({});

const supportDeptsList = computed(() => Object.keys(supportOrg.value));
const supportJobFamilies = computed(() => Object.keys(supportPositionGroups.value));
const normalizeWorklineName = (name = '') => name.replace(/^สายงาน\s*/, '').replace(/^สาย\s*/, '').trim();
const selectedWorklineKind = computed(() => normalizeWorklineName(userForm.value.w));
const selectedWorklineGroups = computed(() => jobFamiliesByWorkline.value[userForm.value.w] || {});
const levelOptionsFromDatabase = computed(() => {
    const scopedLevels = levelsByJobFamily.value[userForm.value.w]?.[userForm.value.job] || [];
    if (scopedLevels.length) return scopedLevels;

    const directLevels = levelsByWorkline.value[userForm.value.w] || [];
    if (directLevels.length) return directLevels;

    return [];
});
const isAcademicWorkline = computed(() => selectedWorklineKind.value === 'วิชาการ');
const isSupportWorkline = computed(() => selectedWorklineKind.value === 'สนับสนุน');
const isAdminWorkline = computed(() => selectedWorklineKind.value === 'บริหาร');
const selectedDeptWorks = computed(() => supportOrg.value[userForm.value.dept] || []);
const jobOptions = computed(() => {
    if (!userForm.value.w) return [];

    return Object.keys(selectedWorklineGroups.value);
});
const legacyJobOption = computed(() => {
    const job = userForm.value.job;

    return job && !jobOptions.value.includes(job) ? job : '';
});
const selectedSupportWork = computed(() =>
    selectedDeptWorks.value.find((item) => item.work === userForm.value.job),
);
const unitOptions = computed(() => {
    if (isSupportWorkline.value) return selectedSupportWork.value?.units || [];

    return [];
});
const positionOptions = computed(() => {
    if (!userForm.value.job) return [];

    const positions = selectedWorklineGroups.value[userForm.value.job] || [];
    return positions;
});
const legacyPositionOption = computed(() => {
    const position = userForm.value.p;

    return position && !positionOptions.value.includes(position) ? position : '';
});
const levelOptions = computed(() => {
    if (!userForm.value.w) return [];

    return levelOptionsFromDatabase.value;
});
const legacyLevelOption = computed(() => {
    const level = userForm.value.l;

    return level && !levelOptions.value.includes(level) ? level : '';
});
const pageTitle = computed(() => PAGE_TITLES[activePage.value] || activePage.value);
const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const currentNavConfig = computed(() => {
    const sections = NAV_CONFIG[currentRole.value] || [];

    if (currentRole.value !== 'admin') return sections;

    return sections.map((section) => {
        const hasIdpDetail = section.items?.some((item) => item.id === 'emp-idp-detail');
        const hasEmployeeIdp = section.items?.some((item) => item.id === 'emp-idp');

        if (!hasEmployeeIdp || hasIdpDetail) return section;

        return {
            ...section,
            items: [
                ...section.items,
                { id: 'emp-idp-detail', ic: '', lb: 'รายละเอียด IDP' },
            ],
        };
    });
});
const implementedAdminPages = new Set([
    'emp-assess',
    'emp-gap', 
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
    'admin-users',
    'admin-org',
    'admin-org-structure',
    'admin-dict',
    'admin-idp-tools',
]);

watchEffect(() => {
    if (Array.isArray(page.props.users)) {
        users.value = clone(page.props.users);
    }
});

watchEffect(() => {
    if (requestedPage.value && implementedAdminPages.has(requestedPage.value)) {
        activePage.value = requestedPage.value;
        requestedPage.value = null;
    }

    if (!implementedAdminPages.has(activePage.value)) {
        activePage.value = 'admin-users';
    }

    if (typeof rememberedAdminState.value.showSidebar !== 'boolean') {
        rememberedAdminState.value.showSidebar = true;
    }

    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(adminPageStorageKey, activePage.value);
    }
});
const currentProfileUser = computed(() =>
    users.value.find((user) => user.r === currentRole.value)
    || users.value[0]
    || {
        n: page.props.auth?.user?.name || currentRoleData.value.name,
        t: '',
        sso: page.props.auth?.user?.id || 'current-user',
        p: currentRoleData.value.pos,
        r: currentRole.value,
        act: true,
    },
);
const normalizeUserRoleKey = (role = '') => ({
    manager_dept: 'dept_head',
    manager: 'dean',
}[role] || role);
const roleLabel = (role = '') => {
    const normalizedRole = normalizeUserRoleKey(role);
    return roleOptions.value.find((option) => option.key === normalizedRole)?.label
        || normalizedRole
        || 'ไม่ระบุบทบาท';
};
const primaryJobFamily = (department = '') => department.split(' > ')[0]?.trim() || 'ไม่มีกลุ่มงาน';
const personOption = (user) => ({
    key: user.db_id || user.sso || `${user.t || ''}${user.n}`,
    value: user.db_id,
    label: `${user.t || ''}${user.n} · ${primaryJobFamily(user.d)} · ${roleLabel(user.r)}`,
});

const supervisorOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'supervisor')
        .map(personOption),
);

const managerDeptOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'dept_head')
        .map(personOption),
);

const deanOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'dean')
        .map(personOption),
);

const canPickEvaluator1 = computed(() => !['admin', 'supervisor', 'dept_head', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const canPickEvaluator2 = computed(() => !['admin', 'dept_head', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const canPickEvaluator3 = computed(() => !['admin', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));

const requestPageChange = (page) => {
    activePage.value = page;
};

const parseOrgPath = (path = '') => {
    const parts = path.split(' > ').map((part) => part.trim()).filter(Boolean);

    if (parts.length >= 3) return { dept: parts[0], job: parts[1], unit: parts.slice(2).join(' > ') };
    if (parts.length === 2) return { dept: parts[0], job: parts[1], unit: '' };
    if (parts.length === 1) return { dept: parts[0], job: '', unit: '' };

    return { dept: '', job: '', unit: '' };
};

const syncOrgPath = () => {
    const form = userForm.value;

    form.d = [form.job, form.unit].filter(Boolean).join(' > ');
};

const findUserName = (predicate) => {
    const found = users.value.find(predicate);
    return found ? `${found.t || ''}${found.n}` : '';
};

const syncOrgSupervisors = () => {
    const form = userForm.value;
    form.supervisor_id_1 = '';
    form.supervisor_id_2 = '';
    form.supervisor_id_3 = '';
};

const resetOrgSelection = () => {
    userForm.value.dept = '';
    userForm.value.job = '';
    userForm.value.unit = '';
    userForm.value.d = '';
    userForm.value.p = '';
    userForm.value.l = '';
    userForm.value.supervisor_id_1 = '';
    userForm.value.supervisor_id_2 = '';
    userForm.value.supervisor_id_3 = '';
};

const handleWorklineChange = () => {
    resetOrgSelection();
};

const handleDeptChange = () => {
    userForm.value.job = '';
    userForm.value.unit = '';
    userForm.value.p = '';
    userForm.value.l = '';
    syncOrgPath();
};

const handleJobChange = () => {
    userForm.value.unit = '';
    userForm.value.p = '';
    userForm.value.l = '';
    syncOrgPath();
};

const handleUnitChange = () => {
    userForm.value.p = '';
    userForm.value.l = '';
    syncOrgPath();
};

const handlePositionChange = () => {
    userForm.value.l = '';
};

const handleRoleChange = () => {
    if (!canPickEvaluator1.value) userForm.value.supervisor_id_1 = '';
    if (!canPickEvaluator2.value) userForm.value.supervisor_id_2 = '';
    if (!canPickEvaluator3.value) userForm.value.supervisor_id_3 = '';
};

const resetUserForm = (data = null) => {
    const org = parseOrgPath(data?.d || '');
    const [firstName = '', ...lastNameParts] = (data?.n || '').split(' ');

    editingUserKey.value = data?.sso || null;
    userForm.value = {
        db_id: data?.db_id || null,
        sso: data?.sso || '',
        t: data?.t || '',
        n: data?.n || '',
        fn: data?.fn || firstName,
        ln: data?.ln || lastNameParts.join(' '),
        fe: data?.fe || '',
        le: data?.le || '',
        em: data?.em || '',
        ph: data?.ph || '',
        w: data?.w || worklines.value[0] || '',
        d: data?.d || '',
        dept: '',
        job: org.job || org.dept,
        unit: org.unit,
        p: data?.p || '',
        l: data?.l || '',
        r: normalizeUserRoleKey(data?.r || 'employee'),
        supervisor_id_1: data?.supervisor_id_1 || '',
        supervisor_id_2: data?.supervisor_id_2 || '',
        supervisor_id_3: data?.supervisor_id_3 || '',
        act: data?.act !== false,
        structureStatus: data?.structureStatus || 'ok',
        structureIssues: Array.isArray(data?.structureIssues) ? data.structureIssues : [],
    };
    handleRoleChange();
};

const openModal = (type, data = null) => {
    if (!['modal-user', 'modal-org'].includes(type)) return;

    orgEditMode.value = type === 'modal-org';
    modalReturnPage.value = orgEditMode.value ? 'admin-org' : 'admin-users';
    resetUserForm(data);
    activeModal.value = 'modal-user';
};

const closeModal = () => {
    activeModal.value = null;
    editingUserKey.value = null;
    orgEditMode.value = false;
    modalReturnPage.value = 'admin-users';
};

const saveUser = () => {
    if (isSavingUser.value) return;

    activePage.value = modalReturnPage.value;
    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(adminPageStorageKey, modalReturnPage.value);
    }
    const form = userForm.value;
    syncOrgPath();
    const thaiName = [form.fn.trim(), form.ln.trim()].filter(Boolean).join(' ');

    if (!form.sso.trim() || !thaiName) {
        alert('กรุณากรอก ID และชื่อผู้ใช้');
        return;
    }

    if (!form.w || !form.job || !form.p || !form.l) {
        alert('กรุณาเลือกสายงาน กลุ่มงาน ตำแหน่ง และระดับตำแหน่งให้ครบถ้วน');
        return;
    }

    if (!positionOptions.value.includes(form.p)) {
        alert('กรุณาให้ Admin เพิ่มตำแหน่งงานในกลุ่มงานนี้ก่อนบันทึกผู้ใช้');
        return;
    }

    if (!levelOptions.value.includes(form.l)) {
        alert('กรุณาให้ Admin เพิ่มระดับตำแหน่งในสายงานหรือกลุ่มงานนี้ก่อนบันทึกผู้ใช้');
        return;
    }

    const duplicate = users.value.some((user) => user.sso === form.sso && user.sso !== editingUserKey.value);
    if (duplicate) {
        alert(`ID ${form.sso} มีอยู่ในระบบแล้ว`);
        return;
    }

    const nextUser = {
        ...form,
        db_id: form.db_id,
        sso: form.sso.trim(),
        n: thaiName,
        fn: form.fn.trim(),
        ln: form.ln.trim(),
        fe: form.fe.trim(),
        le: form.le.trim(),
        em: form.em.trim(),
        ph: form.ph.trim(),
        t: form.t.trim(),
        w: form.w.trim(),
        d: form.d.trim(),
        dept: form.dept.trim(),
        job: form.job.trim(),
        unit: form.unit.trim(),
        p: form.p.trim(),
        l: form.l.trim(),
        supervisor_id_1: form.supervisor_id_1 || null,
        supervisor_id_2: form.supervisor_id_2 || null,
        supervisor_id_3: form.supervisor_id_3 || null,
        act: Boolean(form.act),
    };

    const onSuccess = (responsePage) => {
        activePage.value = modalReturnPage.value;
        if (typeof window !== 'undefined') {
            window.sessionStorage.setItem(adminPageStorageKey, modalReturnPage.value);
        }

        if (Array.isArray(responsePage.props.users)) {
            users.value = clone(responsePage.props.users);
            closeModal();
            return;
        }

        router.reload({
            only: ['users'],
            preserveScroll: true,
            onSuccess: (page) => {
                users.value = clone(page.props.users || []);
                closeModal();
            },
        });
    };

    const onError = (errors) => {
        const firstError = Object.values(errors)[0];
        alert(firstError || 'ไม่สามารถบันทึกข้อมูลผู้ใช้ได้');
    };

    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            isSavingUser.value = true;
        },
        onFinish: () => {
            isSavingUser.value = false;
        },
        onSuccess,
        onError,
    };

    if (nextUser.db_id) {
        router.put(`/admin/users/${nextUser.db_id}`, nextUser, options);
        return;
    }

    router.post('/admin/users', nextUser, options);
};

const goProfile = () => router.visit(route('profile.edit'));
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

            <button class="sb-user" type="button" @click="goProfile">
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
                <div v-for="(section, sectionIndex) in currentNavConfig" :key="sectionIndex">
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

                <AdminUsers
                    v-else-if="activePage === 'admin-users'"
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
                    :job-families-by-workline="jobFamiliesByWorkline"
                    :set-job-families-by-workline="setRef(jobFamiliesByWorkline)"
                    :levels-by-workline="levelsByWorkline"
                    :set-levels-by-workline="setRef(levelsByWorkline)"
                    :level-expectations-by-workline="levelExpectationsByWorkline"
                    :set-level-expectations-by-workline="setRef(levelExpectationsByWorkline)"
                    :levels-by-job-family="levelsByJobFamily"
                    :set-levels-by-job-family="setRef(levelsByJobFamily)"
                    :level-expectations-by-job-family="levelExpectationsByJobFamily"
                    :set-level-expectations-by-job-family="setRef(levelExpectationsByJobFamily)"
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

                <AdminIdpTools
                    v-else-if="activePage === 'admin-idp-tools'"
                    :competencies="competencies"
                    :idp-learning-methods="idpLearningMethods"
                    :learning-catalogs="hrCatalogItems"
                    :learning-methods="learningMethods"
                />

                <div v-else class="p-20 text-center text-text3">กำลังพัฒนา</div>
            </div>
        </div>
    </div>

    <div v-if="activeModal === 'modal-user'" class="mo admin-user-modal">
        <div class="mo-box admin-user-modal-box">
            <div class="mo-h admin-user-modal-head">
                <div>
                    <div class="fw8 fs18">
                        {{ orgEditMode ? 'แก้ไขการบังคับบัญชา' : 'จัดการผู้ใช้งาน' }}
                    </div>
                    <div class="muted fs12">
                        {{ orgEditMode ? 'ปรับสายงาน หน่วยงาน บทบาท และผู้ประเมิน' : 'กรอกข้อมูลให้ครบตามตาราง users ในฐานข้อมูล' }}
                    </div>
                </div>
                <button class="btn btn-s btn-sm" type="button" @click="closeModal">× ปิด</button>
            </div>

            <div class="mo-b admin-user-modal-body">
                <div v-if="!orgEditMode" class="admin-user-note">
                     ระบบจะ map ID ที่กรอกนี้เข้ากับข้อมูลที่ส่งมาจาก KKU SSO โดยอัตโนมัติ
                </div>

                <div v-if="userForm.structureStatus === 'invalid' && userForm.structureIssues.length" class="admin-user-warning">
                    <div class="admin-user-warning-title">ต้องตรวจสอบข้อมูลผู้ใช้นี้</div>
                    <ul>
                        <li v-for="issue in userForm.structureIssues" :key="issue">{{ issue }}</li>
                    </ul>
                </div>

                <div v-if="!orgEditMode" class="fg">
                    <label class="lbl req">ID</label>
                    <input v-model="userForm.sso" class="inp modal-input" placeholder="เช่น 64XXXX หรือ stu_XXXXXXX" />
                </div>

                <div v-if="!orgEditMode" class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">คำนำหน้า</label>
                        <select v-model="userForm.t" class="sel modal-input">
                            <option value="">— เลือกคำนำหน้า —</option>
                            <option value="นาย">นาย</option>
                            <option value="นาง">นาง</option>
                            <option value="ดร.">ดร.</option>
                            <option value="ผศ.">ผศ.</option>
                            <option value="รศ.">รศ.</option>
                            <option value="ศ.">ศ.</option>
                        </select>
                    </div>
                </div>

                <div v-if="!orgEditMode" class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">ชื่อ (ภาษาไทย)</label>
                        <input v-model="userForm.fn" class="inp modal-input" placeholder="ชื่อจริง" />
                    </div>
                    <div class="fg">
                        <label class="lbl req">นามสกุล (ภาษาไทย)</label>
                        <input v-model="userForm.ln" class="inp modal-input" placeholder="นามสกุล" />
                    </div>
                </div>

                <div v-if="!orgEditMode" class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">First Name (English)</label>
                        <input v-model="userForm.fe" class="inp modal-input" placeholder="First name in English" />
                    </div>
                    <div class="fg">
                        <label class="lbl req">Last Name (English)</label>
                        <input v-model="userForm.le" class="inp modal-input" placeholder="Last name in English" />
                    </div>
                </div>

                <div v-if="!orgEditMode" class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">Email</label>
                        <input v-model="userForm.em" class="inp modal-input" placeholder="name@example.com" type="email" />
                    </div>
                </div>

                <div v-if="orgEditMode" class="org-edit-summary">
                    <div class="fw8">{{ userForm.t }}{{ userForm.n || `${userForm.fn} ${userForm.ln}` }}</div>
                    <div class="muted fs12">{{ userForm.sso || '—' }}</div>
                </div>

                <div class="modal-grid" :class="{ 'single-col': !userForm.w }">
                    <div class="fg">
                        <label class="lbl req">สายงาน</label>
                        <select v-model="userForm.w" class="sel modal-input" @change="handleWorklineChange">
                            <option value="">— เลือกสายงาน —</option>
                            <option v-for="workline in worklines" :key="workline" :value="workline">
                                {{ workline }}
                            </option>
                        </select>
                    </div>

                    <div v-if="userForm.w" class="fg">
                        <label class="lbl req">กลุ่มงาน</label>
                        <select v-model="userForm.job" class="sel modal-input" @change="handleJobChange">
                            <option value="">— เลือกกลุ่มงาน —</option>
                            <option v-if="legacyJobOption" :value="legacyJobOption">
                                {{ legacyJobOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="job in jobOptions" :key="job" :value="job">
                                {{ job }}
                            </option>
                        </select>
                        <div v-if="legacyJobOption" class="modal-help warning">
                            กลุ่มงานนี้ไม่มีในโครงสร้างปัจจุบัน กรุณาเลือกกลุ่มงานใหม่ก่อนบันทึก
                        </div>
                    </div>
                </div>

                <div v-if="!orgEditMode && userForm.job" class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">ตำแหน่ง</label>
                        <select
                            v-model="userForm.p"
                            class="sel modal-input"
                            :disabled="!positionOptions.length && !legacyPositionOption"
                            @change="handlePositionChange"
                        >
                            <option v-if="positionOptions.length" value="">— เลือกตำแหน่ง —</option>
                            <option v-else value="">ยังไม่มีตำแหน่งในกลุ่มงาน</option>
                            <option v-if="legacyPositionOption" :value="legacyPositionOption">
                                {{ legacyPositionOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="position in positionOptions" :key="position" :value="position">
                                {{ position }}
                            </option>
                        </select>
                        <div v-if="legacyPositionOption" class="modal-help warning">
                            ตำแหน่งนี้ไม่มีในกลุ่มงานปัจจุบัน กรุณาเลือกตำแหน่งใหม่ก่อนบันทึก
                        </div>
                        <div v-if="!positionOptions.length" class="modal-help">
                            กรุณาให้ Admin เพิ่มตำแหน่งงานก่อนกำหนดผู้ใช้
                        </div>
                    </div>
                    <div v-if="userForm.p" class="fg">
                        <label class="lbl req">ระดับตำแหน่ง</label>
                        <select v-model="userForm.l" class="sel modal-input" :disabled="!levelOptions.length && !legacyLevelOption">
                            <option v-if="levelOptions.length" value="">— เลือกระดับตำแหน่ง —</option>
                            <option v-else value="">ยังไม่มีระดับตำแหน่งในสายงานหรือกลุ่มงาน</option>
                            <option v-if="legacyLevelOption" :value="legacyLevelOption">
                                {{ legacyLevelOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="level in levelOptions" :key="level" :value="level">
                                {{ level }}
                            </option>
                        </select>
                        <div v-if="legacyLevelOption" class="modal-help warning">
                            ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน กรุณาเลือกระดับใหม่ก่อนบันทึก
                        </div>
                        <div v-if="!levelOptions.length" class="modal-help">
                            กรุณาให้ Admin เพิ่มระดับตำแหน่งก่อนกำหนดผู้ใช้
                        </div>
                    </div>
                </div>

                <div class="modal-divider"></div>

                <div class="evaluator-section">
                    <div class="fg evaluator-role-field">
                        <label class="lbl req">บทบาทในระบบ</label>
                        <select v-model="userForm.r" class="sel modal-input" @change="handleRoleChange">
                            <option
                                v-for="role in roleOptions"
                                :key="`role-${role.key}`"
                                :value="role.key"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                    </div>

                    <div class="evaluator-grid">
                    <div class="fg evaluator-card" :class="{ disabled: !canPickEvaluator1 }">
                        <div class="evaluator-card-head">
                            <div>
                                <span class="evaluator-step">ลำดับที่ 1</span>
                                <label class="lbl">เช่น หัวหน้างาน</label>
                            </div>
                            <span class="evaluator-state">{{ canPickEvaluator1 ? 'ข้ามได้' : 'ปิด' }}</span>
                        </div>
                        <select
                            v-model="userForm.supervisor_id_1"
                            class="sel modal-input"
                            :disabled="!canPickEvaluator1"
                        >
                            <option value="">
                                {{ canPickEvaluator1 ? '— ไม่ผ่านผู้ประเมินลำดับนี้ —' : 'ไม่ต้องเลือกสำหรับบทบาทนี้' }}
                            </option>
                            <option
                                v-for="person in supervisorOptions"
                                :key="`sup-${person.key}`"
                                :value="person.value"
                            >
                                {{ person.label }}
                            </option>
                        </select>
                        <div v-if="!supervisorOptions.length" class="modal-help">
                            ยังไม่มีผู้ใช้ role หัวหน้างาน
                        </div>
                    </div>

                    <div class="fg evaluator-card" :class="{ disabled: !canPickEvaluator2 }">
                        <div class="evaluator-card-head">
                            <div>
                                <span class="evaluator-step">ลำดับที่ 2</span>
                                <label class="lbl">เช่น หัวหน้าฝ่าย / ผู้บังคับบัญชา</label>
                            </div>
                            <span class="evaluator-state">{{ canPickEvaluator2 ? 'ข้ามได้' : 'ปิด' }}</span>
                        </div>
                        <select
                            v-model="userForm.supervisor_id_2"
                            class="sel modal-input"
                            :disabled="!canPickEvaluator2"
                        >
                            <option value="">
                                {{ canPickEvaluator2 ? '— ไม่ผ่านผู้ประเมินลำดับนี้ —' : 'ไม่ต้องเลือกสำหรับบทบาทนี้' }}
                            </option>
                            <option
                                v-for="person in managerDeptOptions"
                                :key="`evaluator2-${person.key}`"
                                :value="person.value"
                            >
                                {{ person.label }}
                            </option>
                        </select>
                        <div v-if="!managerDeptOptions.length" class="modal-help">
                            ยังไม่มีผู้ใช้ role ผู้บังคับบัญชา
                        </div>
                    </div>

                    <div class="fg evaluator-card" :class="{ disabled: !canPickEvaluator3 }">
                        <div class="evaluator-card-head">
                            <div>
                                <span class="evaluator-step">ลำดับที่ 3</span>
                                <label class="lbl">เช่น คณบดี</label>
                            </div>
                            <span class="evaluator-state">{{ canPickEvaluator3 ? 'ข้ามได้' : 'ปิด' }}</span>
                        </div>
                        <select
                            v-model="userForm.supervisor_id_3"
                            class="sel modal-input"
                            :disabled="!canPickEvaluator3"
                        >
                            <option value="">
                                {{ canPickEvaluator3 ? '— ไม่ผ่านผู้ประเมินลำดับนี้ —' : 'ไม่ต้องเลือกสำหรับบทบาทนี้' }}
                            </option>
                            <option
                                v-for="person in deanOptions"
                                :key="`dean-${person.key}`"
                                :value="person.value"
                            >
                                {{ person.label }}
                            </option>
                        </select>
                        <div v-if="!deanOptions.length" class="modal-help">
                            ยังไม่มีผู้ใช้ role คณบดี
                        </div>
                    </div>
                    </div>
                </div>

                <label v-if="!orgEditMode" class="modal-checkbox">
                    <span>สถานะบัญชี</span>
                    <input v-model="userForm.act" type="checkbox" />
                    <span>ใช้งานได้</span>
                </label>

                <div class="modal-actions">
                    <button class="btn btn-s modal-action-btn" type="button" @click="closeModal">ยกเลิก</button>
                    <button class="btn btn-p modal-action-btn modal-save-btn" type="button" @click="saveUser">
                         บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-user-modal {
    align-items: center;
    overflow-y: auto;
}

.admin-user-modal-box {
    width: min(720px, calc(100vw - 28px));
    max-height: min(88vh, 760px);
    margin: 14px 0;
    border-radius: 8px;
}

.admin-user-modal-head {
    padding: 16px 18px;
}

.admin-user-modal-body {
    padding: 18px;
}

.admin-user-note {
    margin-bottom: 16px;
    padding: 10px 12px;
    border-radius: 6px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 13px;
}

.admin-user-warning {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
}

.admin-user-warning-title {
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 900;
}

.admin-user-warning ul {
    margin: 0;
    padding-left: 18px;
}

.admin-user-warning li {
    margin: 3px 0;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.45;
}

.org-edit-summary {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #dbeafe;
    border-radius: 6px;
    background: #eff6ff;
    color: var(--navy);
}

.modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 14px;
}

.modal-grid.single-col {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.modal-input {
    min-height: 40px;
    border-radius: 6px;
    border-color: #dbe3ef;
    font-size: 14px;
    padding-top: 7px;
    padding-bottom: 7px;
}

.modal-input:disabled {
    background: #eef2f7;
    color: #94a3b8;
}

.modal-help {
    margin-top: 7px;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
}

.modal-help.warning {
    color: #c2410c;
}

.evaluator-section {
    margin: 2px 0 16px;
    padding: 14px;
    border: 1px solid #dbe5f1;
    border-radius: 8px;
    background: #fbfdff;
}

.evaluator-role-field {
    margin-bottom: 14px;
    max-width: 360px;
}

.evaluator-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.evaluator-card {
    min-width: 0;
    padding: 12px;
    border: 1px solid #dbe5f1;
    border-radius: 8px;
    background: #fff;
}

.evaluator-card.disabled {
    background: #f8fafc;
}

.evaluator-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    min-height: 44px;
    margin-bottom: 8px;
}

.evaluator-step {
    display: block;
    margin-bottom: 3px;
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
}

.evaluator-card .lbl {
    margin-bottom: 0;
    color: var(--text);
    line-height: 1.3;
}

.evaluator-state {
    flex: 0 0 auto;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 10px;
    font-weight: 900;
    line-height: 1;
    padding: 5px 8px;
}

.evaluator-card.disabled .evaluator-state {
    background: #e2e8f0;
    color: #64748b;
}

.evaluator-card.disabled .lbl,
.evaluator-card.disabled .evaluator-step {
    color: #94a3b8;
}

.req::after {
    content: ' *';
    color: #ef4444;
}

.modal-divider {
    height: 1px;
    margin: 12px 0;
    background: #dbe3ef;
}

.modal-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 20px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text2);
}

.modal-checkbox input {
    width: 18px;
    height: 18px;
    accent-color: #1d70d6;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-action-btn {
    min-width: 82px;
    min-height: 38px;
    justify-content: center;
    font-size: 14px;
}

.modal-save-btn {
    background: #2563eb;
    color: #fff;
}

.modal-save-btn:hover {
    background: #1d4ed8;
}

@media (max-width: 720px) {
    .modal-grid,
    .modal-grid.single-col {
        grid-template-columns: 1fr;
    }

    .evaluator-grid {
        grid-template-columns: 1fr;
    }

    .evaluator-role-field {
        max-width: none;
    }
}
</style>
