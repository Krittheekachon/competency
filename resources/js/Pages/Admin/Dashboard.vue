<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import {
    INITIAL_USERS,
    NAV_CONFIG,
    PAGE_TITLES,
    ROLES_CONFIG,
} from '../../data';
import AdminDict from './AdminDict.vue';
import AdminOrg from './AdminOrg.vue';
import AdminOrgStructure from './AdminOrgStructure.vue';
import AdminUsers from './AdminUsers.vue';
import EmployeeAssess from '../Staff/EmployeeAssess.vue';
import EmployeeGap from '../Staff/EmployeeGap.vue';
import EmployeeIDP from '../Staff/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Staff/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Staff/EmployeeProgress.vue';

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const requestedPage = ref(typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null);

const rememberedAdminState = useRemember({
    showSidebar: true,
    activePage: requestedPage.value || 'emp-assess',
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
const requestedAdminPage = ref(page.props.adminPage || null);
const competencies = ref(clone(page.props.competencies || []));
const users = ref(clone(page.props.users?.length ? page.props.users : INITIAL_USERS));
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
    g: 'ชาย',
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
    sup: '',
    evaluator2: '',
    act: true,
});

const worklines = ref(clone(page.props.worklines || []));
const jobFamiliesByWorkline = ref(clone(page.props.jobFamiliesByWorkline || {}));
const academicPositions = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายวิชาการ'] || {})));
const adminDepts = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายบริหาร'] || jobFamiliesByWorkline.value['สายงานบริหาร'] || {})));
const competencyTypes = ref(clone(page.props.competencyTypes || []));
const supportOrg = ref(clone(page.props.supportOrg || {}));
const supportPositionGroups = ref(clone(jobFamiliesByWorkline.value['สายสนับสนุน'] || page.props.supportPositionGroups || {}));
const supportPositions = ref([]);
const adminPositions = ref(clone(page.props.adminJobFamilies || []));
const levelsByWorkline = ref(clone(page.props.levelsByWorkline || {}));
const academicRanks = ref(clone(levelsByWorkline.value['สายวิชาการ'] || []));
const supportRanks = ref(clone(levelsByWorkline.value['สายสนับสนุน'] || []));
const learningMethods = ref(clone(page.props.learningMethods || []));
const orgSups = ref({});

const supportDeptsList = computed(() => Object.keys(supportOrg.value));
const supportJobFamilies = computed(() => Object.keys(supportPositionGroups.value));
const normalizeWorklineName = (name = '') => name.replace(/^สายงาน\s*/, '').replace(/^สาย\s*/, '').trim();
const selectedWorklineKind = computed(() => normalizeWorklineName(userForm.value.w));
const selectedWorklineGroups = computed(() => jobFamiliesByWorkline.value[userForm.value.w] || {});
const levelOptionsFromDatabase = computed(() => {
    const directLevels = levelsByWorkline.value[userForm.value.w] || [];
    if (directLevels.length) return directLevels;
    if (userForm.value.p) return [userForm.value.p];

    return positionOptions.value;
});
const isAcademicWorkline = computed(() => selectedWorklineKind.value === 'วิชาการ');
const isSupportWorkline = computed(() => selectedWorklineKind.value === 'สนับสนุน');
const isAdminWorkline = computed(() => selectedWorklineKind.value === 'บริหาร');
const selectedDeptWorks = computed(() => supportOrg.value[userForm.value.dept] || []);
const jobOptions = computed(() => {
    if (!userForm.value.w) return [];

    return Object.keys(selectedWorklineGroups.value);
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
    return positions.length ? positions : [userForm.value.job];
});
const levelOptions = computed(() => {
    if (!userForm.value.w) return [];

    return levelOptionsFromDatabase.value;
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
                { id: 'emp-idp-detail', ic: '📁', lb: 'รายละเอียด IDP' },
            ],
        };
    });
});
const adminPageStorageKey = 'admin-active-page';
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
]);
watchEffect(() => {
    if (requestedAdminPage.value && implementedAdminPages.has(requestedAdminPage.value)) {
        activePage.value = requestedAdminPage.value;
        requestedAdminPage.value = null;
    }

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
const evaluatorOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .map((user) => ({
            key: user.sso || `${user.t || ''}${user.n}`,
            value: `${user.t || ''}${user.n}`,
            label: `${user.t || ''}${user.n}${user.p ? ` · ${user.p}` : ''}`,
        })),
);

const requestPageChange = (page) => {
    activePage.value = page;
};

const keepAdminPage = (page = 'admin-org-structure') => {
    activePage.value = page;

    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(adminPageStorageKey, page);
    }
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
    if (!orgEditMode.value) syncOrgSupervisors();
};

const findUserName = (predicate) => {
    const found = users.value.find(predicate);
    return found ? `${found.t || ''}${found.n}` : '';
};

const syncOrgSupervisors = () => {
    const form = userForm.value;
    const deptKey = form.job || form.dept || adminDepts.value[0];
    const orgHead = orgSups.value[deptKey] || orgSups.value[adminDepts.value[0]] || '';

    if (isSupportWorkline.value) {
        form.sup = findUserName((user) =>
            user.r === 'supervisor'
            && user.d
            && form.d
            && (
                user.d === form.d
                || user.d.startsWith(form.job)
                || user.d === form.job
            ),
        );
        form.evaluator2 = findUserName((user) =>
            user.r === 'manager_dept'
            && user.d
            && form.job
            && user.d.startsWith(form.job),
        ) || orgHead;
        return;
    }

    if (isAcademicWorkline.value) {
        form.sup = findUserName((user) =>
            user.r === 'supervisor'
            && user.w === form.w
            && (user.p === form.job || user.d === form.job),
        );
        form.evaluator2 = orgHead;
        return;
    }

    if (isAdminWorkline.value) {
        form.sup = orgHead;
        form.evaluator2 = orgHead;
        return;
    }

    form.sup = '';
    form.evaluator2 = '';
};

const resetOrgSelection = () => {
    userForm.value.dept = '';
    userForm.value.job = '';
    userForm.value.unit = '';
    userForm.value.d = '';
    userForm.value.p = '';
    userForm.value.l = '';
    userForm.value.sup = '';
    userForm.value.evaluator2 = '';
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
    const directLevels = levelsByWorkline.value[userForm.value.w] || [];
    if (!directLevels.length && userForm.value.p) {
        userForm.value.l = userForm.value.p;
    }
    syncOrgSupervisors();
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
        g: data?.g || 'ชาย',
        em: data?.em || '',
        ph: data?.ph || '',
        w: data?.w || worklines.value[0] || '',
        d: data?.d || '',
        dept: '',
        job: org.job || org.dept,
        unit: org.unit,
        p: data?.p || '',
        l: data?.l || '',
        r: data?.r || 'employee',
        sup: data?.sup || '',
        evaluator2: data?.evaluator2 || '',
        act: data?.act !== false,
    };
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
    if (!form.l && form.p && !(levelsByWorkline.value[form.w] || []).length) {
        form.l = form.p;
    }
    const thaiName = [form.fn.trim(), form.ln.trim()].filter(Boolean).join(' ');

    if (!form.sso.trim() || !thaiName) {
        alert('กรุณากรอก ID และชื่อผู้ใช้');
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
        sup: form.sup.trim(),
        evaluator2: form.evaluator2.trim(),
        act: Boolean(form.act),
    };

    const onSuccess = (responsePage) => {
        activePage.value = modalReturnPage.value;
        if (typeof window !== 'undefined') {
            window.sessionStorage.setItem(adminPageStorageKey, modalReturnPage.value);
        }

        if (responsePage.props.users?.length) {
            users.value = clone(responsePage.props.users);
            closeModal();
            return;
        }

        if (editingUserKey.value) {
            users.value = users.value.map((user) => user.sso === editingUserKey.value ? { ...user, ...nextUser } : user);
        } else {
            users.value = [nextUser, ...users.value];
        }

        closeModal();
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
        router.put(route('admin.users.update', nextUser.db_id), nextUser, options);
        return;
    }

    router.post(route('admin.users.store'), nextUser, options);
};

const submitUserModalOnEnter = (event) => {
    if (event.isComposing || isSavingUser.value) return;

    const tagName = event.target?.tagName?.toLowerCase();
    if (['button', 'textarea'].includes(tagName)) return;

    event.preventDefault();
    saveUser();
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
                    :keep-admin-page="keepAdminPage"
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

            <div class="mo-b admin-user-modal-body" @keydown.enter="submitUserModalOnEnter">
                <div v-if="!orgEditMode" class="admin-user-note">
                    💡 ระบบจะ map ID ที่กรอกนี้เข้ากับข้อมูลที่ส่งมาจาก KKU SSO โดยอัตโนมัติ
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
                            <option v-for="job in jobOptions" :key="job" :value="job">
                                {{ job }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="!orgEditMode && userForm.job" class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">ตำแหน่ง</label>
                        <select v-model="userForm.p" class="sel modal-input" @change="handlePositionChange">
                            <option value="">— เลือกตำแหน่ง —</option>
                            <option v-for="position in positionOptions" :key="position" :value="position">
                                {{ position }}
                            </option>
                        </select>
                    </div>
                    <div v-if="userForm.p" class="fg">
                        <label class="lbl req">ระดับตำแหน่ง</label>
                        <select v-model="userForm.l" class="sel modal-input">
                            <option value="">— เลือกระดับตำแหน่ง —</option>
                            <option v-for="level in levelOptions" :key="level" :value="level">
                                {{ level }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="modal-divider"></div>

                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">บทบาทในระบบ</label>
                        <select v-model="userForm.r" class="sel modal-input">
                            <option value="user">user</option>
                            <option value="supervisor">supervisor</option>
                            <option value="head">head</option>
                            <option value="dean">dean</option>
                            <option value="hr">hr</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="lbl req">หัวหน้างาน</label>
                        <select
                            v-if="orgEditMode"
                            v-model="userForm.sup"
                            class="sel modal-input"
                        >
                            <option value="">— เลือกหัวหน้างาน —</option>
                            <option
                                v-for="person in evaluatorOptions"
                                :key="`sup-${person.key}`"
                                :value="person.value"
                            >
                                {{ person.label }}
                            </option>
                        </select>
                        <input
                            v-else
                            v-model="userForm.sup"
                            class="inp modal-input"
                            disabled
                            placeholder="ยึดตามโครงสร้างองค์กร (หัวหน้างาน)"
                        />
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">หัวหน้าฝ่าย (ผู้บังคับบัญชา)</label>
                        <select
                            v-if="orgEditMode"
                            v-model="userForm.evaluator2"
                            class="sel modal-input"
                        >
                            <option value="">— เลือกผู้บังคับบัญชา —</option>
                            <option
                                v-for="person in evaluatorOptions"
                                :key="`evaluator2-${person.key}`"
                                :value="person.value"
                            >
                                {{ person.label }}
                            </option>
                        </select>
                        <input
                            v-else
                            v-model="userForm.evaluator2"
                            class="inp modal-input"
                            disabled
                            placeholder="ยึดตามโครงสร้างองค์กร (ผู้บังคับบัญชา)"
                        />
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
                        💾 บันทึก
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
}
</style>
