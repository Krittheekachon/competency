<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
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

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'จัดการผู้ใช้งาน',
    },
});

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
const supervisorSearch = ref('');
const evaluator2Search = ref('');
const showReviewerModal = ref(false);
const activeReviewerTemplateModal = ref('');
const showAssessmentTemplateCreate = ref(false);
const activeAssessmentTemplateId = ref(null);
const editingAssessmentTemplateId = ref(null);
const assessmentTemplateMemberPick = ref('');
const reviewerSearchTerms = ref({});
const activeReviewerDropdown = ref(null);
const assessmentTemplateForm = ref({
    name: '',
    description: '',
    reviewer_ids: [],
    assignment_user_ids: [],
    reviewer_pick: '',
    user_pick: '',
});
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
    reviewer_template_id: '',
    idp_reviewer_template_id: '',
    reviewer_ids: [],
    idp_reviewer_ids: [],
    act: true,
    structureStatus: 'ok',
    structureIssues: [],
});

const worklines = ref(clone(page.props.worklines || []));
const jobFamiliesByWorkline = ref(clone(page.props.jobFamiliesByWorkline || {}));
const academicPositions = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายวิชาการ'] || {})));
const adminDepts = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายงานบริหาร'] || {})));
const competencyTypes = ref(clone(page.props.competencyTypes || []));
const reviewerChainTemplates = computed(() => page.props.reviewerChainTemplates || []);
const assessmentReviewerTemplates = computed(() =>
    reviewerChainTemplates.value.filter((template) => (template.chainType || 'assessment') === 'assessment'),
);
const idpReviewerTemplates = computed(() =>
    reviewerChainTemplates.value.filter((template) => template.chainType === 'idp'),
);
const activeReviewerTemplateType = computed(() =>
    activeReviewerTemplateModal.value === 'idp' ? 'idp' : 'assessment',
);
const activeReviewerTemplateList = computed(() =>
    activeReviewerTemplateType.value === 'idp'
        ? idpReviewerTemplates.value
        : assessmentReviewerTemplates.value,
);
const selectedAssessmentTemplate = computed(() =>
    activeReviewerTemplateList.value.find((template) => Number(template.id) === selectedEvaluatorId(activeAssessmentTemplateId.value))
    || null,
);
const editingAssessmentTemplate = computed(() =>
    activeReviewerTemplateList.value.find((template) => Number(template.id) === selectedEvaluatorId(editingAssessmentTemplateId.value))
    || null,
);
const isEditingAssessmentTemplate = computed(() => Boolean(editingAssessmentTemplate.value));
const isEditingSelectedAssessmentTemplate = computed(() =>
    Boolean(selectedAssessmentTemplate.value)
    && Number(selectedAssessmentTemplate.value.id) === selectedEvaluatorId(editingAssessmentTemplateId.value),
);
const activeReviewerTemplateTitle = computed(() =>
    activeReviewerTemplateType.value === 'idp'
        ? 'ลำดับการทำ IDP'
        : 'ลำดับในการประเมิน',
);
const activeReviewerTemplateSubtitle = computed(() =>
    activeReviewerTemplateType.value === 'idp'
        ? 'กำหนดลำดับสำหรับตอนส่งแผน IDP'
        : 'กำหนดลำดับสำหรับ workflow การประเมินสมรรถนะ',
);
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
const idpDeliveryTypeSettings = computed(() => page.props.idpDeliveryTypeSettings || []);
const roleLabelsByKey = {
    admin: 'ผู้ดูแลระบบ',
    supervisor: 'หัวหน้าหน่วย',
    dept_head: 'หัวหน้างาน',
    employee: 'บุคลากร',
    hr: 'งานทรัพยากรบุคคล',
    dean: 'ผู้บริหารคณะ',
};
const roleOptions = computed(() => (page.props.roles || [
    { id: 0, key: 'admin', label: 'ผู้ดูแลระบบ' },
    { id: 1, key: 'supervisor', label: 'หัวหน้าหน่วย' },
    { id: 2, key: 'dept_head', label: 'หัวหน้างาน' },
    { id: 3, key: 'employee', label: 'บุคลากร' },
    { id: 4, key: 'hr', label: 'งานทรัพยากรบุคคล' },
    { id: 5, key: 'dean', label: 'ผู้บริหารคณะ' },
]).map((role) => ({
    ...role,
    key: normalizeUserRoleKey(role.key),
    label: roleLabelsByKey[normalizeUserRoleKey(role.key)] || role.label,
})));
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
const currentPageTitle = computed(() => PAGE_TITLES[activePage.value] || props.pageTitle);
const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const visibleAdminPageIds = new Set([
    'admin-users',
    'admin-org',
    'admin-org-structure',
    'admin-dict',
    'admin-idp-tools',
]);
const currentNavConfig = computed(() => {
    const sections = NAV_CONFIG[currentRole.value] || [];

    if (currentRole.value !== 'admin') return sections;

    return sections
        .map((section) => ({
            ...section,
            items: (section.items || []).filter((item) => visibleAdminPageIds.has(item.id)),
        }))
        .filter((section) => section.items.length > 0);
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
    if (Array.isArray(page.props.worklines)) {
        worklines.value = clone(page.props.worklines);
    }

    if (page.props.jobFamiliesByWorkline && typeof page.props.jobFamiliesByWorkline === 'object') {
        jobFamiliesByWorkline.value = clone(page.props.jobFamiliesByWorkline);
    }

    if (Array.isArray(page.props.academicJobFamilies)) {
        academicPositions.value = clone(page.props.academicJobFamilies);
    }

    if (Array.isArray(page.props.adminJobFamilies)) {
        adminDepts.value = clone(page.props.adminJobFamilies);
        adminPositions.value = clone(page.props.adminJobFamilies);
    }

    if (page.props.supportPositionGroups && typeof page.props.supportPositionGroups === 'object') {
        supportPositionGroups.value = clone(page.props.supportPositionGroups);
    }

    if (page.props.supportOrg && typeof page.props.supportOrg === 'object') {
        supportOrg.value = clone(page.props.supportOrg);
    } else {
        supportOrg.value = supportOrgFromGroups(supportPositionGroups.value);
    }

    if (page.props.levelsByWorkline && typeof page.props.levelsByWorkline === 'object') {
        levelsByWorkline.value = clone(page.props.levelsByWorkline);
    }

    if (page.props.levelExpectationsByWorkline && typeof page.props.levelExpectationsByWorkline === 'object') {
        levelExpectationsByWorkline.value = clone(page.props.levelExpectationsByWorkline);
    }

    if (page.props.levelsByJobFamily && typeof page.props.levelsByJobFamily === 'object') {
        levelsByJobFamily.value = clone(page.props.levelsByJobFamily);
    }

    if (page.props.levelExpectationsByJobFamily && typeof page.props.levelExpectationsByJobFamily === 'object') {
        levelExpectationsByJobFamily.value = clone(page.props.levelExpectationsByJobFamily);
    }
});

watchEffect(() => {
    if (requestedPage.value && implementedAdminPages.has(requestedPage.value) && visibleAdminPageIds.has(requestedPage.value)) {
        activePage.value = requestedPage.value;
        requestedPage.value = null;
    }

    if (!implementedAdminPages.has(activePage.value) || !visibleAdminPageIds.has(activePage.value)) {
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

const evaluatorOptions = computed(() =>
    users.value
        .filter((user) => user.db_id)
        .map((user) => ({
            key: user.db_id,
            value: Number(user.db_id),
            name: user.n,
            displayName: `${user.t || ''}${user.n}`,
            p: user.p || '',
            w: user.w || '',
            d: user.d || '',
            r: normalizeUserRoleKey(user.r || ''),
            label: `${user.t || ''}${user.n}${user.p ? ` · ${user.p}` : ''}`,
            searchText: [
                user.db_id,
                user.sso,
                user.t,
                user.n,
                user.p,
                user.w,
                user.d,
                user.r,
            ].filter(Boolean).join(' ').toLowerCase(),
        })),
);
const selectedEvaluatorId = (value) => {
    const id = Number(value);

    return Number.isFinite(id) && id > 0 ? id : '';
};
const selectedReviewerIds = computed(() =>
    (userForm.value.reviewer_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedIdpReviewerIds = computed(() =>
    (userForm.value.idp_reviewer_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedTemplateReviewerIds = computed(() =>
    (assessmentTemplateForm.value.reviewer_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedTemplateUserIds = computed(() =>
    (assessmentTemplateForm.value.assignment_user_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedReviewerTemplate = computed(() =>
    assessmentReviewerTemplates.value.find((template) => Number(template.id) === selectedEvaluatorId(userForm.value.reviewer_template_id))
    || null,
);
const selectedIdpReviewerTemplate = computed(() =>
    idpReviewerTemplates.value.find((template) => Number(template.id) === selectedEvaluatorId(userForm.value.idp_reviewer_template_id))
    || null,
);
const jobFamilyFromDepartment = (department = '') => department.split(' > ')[0]?.trim() || '';
const templateAssignmentLabel = (assignment) => {
    const scopeLabels = {
        default: 'ค่าเริ่มต้น',
        workline: 'สายงาน',
        job_family: 'กลุ่มงาน',
        position: 'ตำแหน่ง',
        user: 'รายคน',
    };

    return assignment.scopeType === 'default'
        ? scopeLabels.default
        : `${scopeLabels[assignment.scopeType] || assignment.scopeType}: ${assignment.scopeValue || assignment.userId || '-'}`;
};
const templateStepLabel = (step) => {
    if (step.reviewerId) {
        return evaluatorFromId(step.reviewerId)?.label || `ผู้ใช้ #${step.reviewerId}`;
    }

    return step.label || 'ผู้ประเมิน';
};
const templateChainSummary = (template) => {
    const steps = template.steps || [];
    if (!steps.length) return 'ยังไม่ได้กำหนดผู้ประเมิน';

    return steps.map(templateStepLabel).join(' -> ');
};
const templateAssignmentSummary = (template) => {
    const assignments = (template.assignments || [])
        .filter((assignment) => assignment.scopeType === 'user' && assignment.userId);
    if (!assignments.length) return 'ยังไม่ได้ผูกผู้ใช้';

    return assignments.map((assignment) => {
        return evaluatorFromId(assignment.userId)?.label || `ผู้ใช้ #${assignment.userId}`;
    }).join(' · ');
};
const templateAssignedUserIds = (template) =>
    (template?.assignments || [])
        .filter((assignment) => assignment.scopeType === 'user' && assignment.userId)
        .map((assignment) => Number(assignment.userId))
        .filter(Boolean);
const templateAssignedUsers = (template) => {
    const ids = new Set(templateAssignedUserIds(template));

    return users.value
        .filter((user) => ids.has(Number(user.db_id)))
        .map((user) => ({
            id: Number(user.db_id),
            name: `${user.t || ''}${user.n}`,
            position: user.p || 'ไม่ระบุตำแหน่ง',
            department: primaryJobFamily(user.d || ''),
        }));
};
const selectedAssessmentTemplateUserOptions = computed(() => {
    const template = selectedAssessmentTemplate.value;
    if (!template) return [];

    const blocked = new Set([
        ...templateAssignedUserIds(template),
        ...(template.steps || [])
            .map((step) => selectedEvaluatorId(step.reviewerId))
            .filter(Boolean),
    ]);

    return evaluatorOptions.value.filter((person) => !blocked.has(person.value));
});
const reviewerChainTypeLabel = (chainType = 'assessment') => ({
    assessment: 'ลำดับในการประเมิน',
    idp: 'ลำดับการทำ IDP',
}[chainType] || chainType);
const reviewerTemplateDescription = computed(() => {
    if (!selectedReviewerTemplate.value) return 'ยังไม่ได้เลือก template';

    const stepText = (selectedReviewerTemplate.value.steps || [])
        .map((step) => `${step.step}. ${templateStepLabel(step)}`)
        .join(' -> ');

    return stepText || 'ยังไม่ได้กำหนดผู้ประเมิน';
});
const reviewerSummary = computed(() => {
    if (!selectedReviewerIds.value.length) return 'ยังไม่ได้กำหนดลำดับการประเมิน';

    return selectedReviewerIds.value
        .map((id, index) => {
            const person = evaluatorFromId(id);
            return `${index + 1}. ${person?.displayName || person?.label || id}`;
        })
        .join(' · ');
});
const idpReviewerSummary = computed(() => {
    if (!selectedIdpReviewerIds.value.length) return 'ยังไม่ได้กำหนดลำดับการทำ IDP';

    return selectedIdpReviewerIds.value
        .map((id, index) => {
            const person = evaluatorFromId(id);
            return `${index + 1}. ${person?.displayName || person?.label || id}`;
        })
        .join(' · ');
});
const userWorkflowIssues = computed(() => {
    if (normalizeUserRoleKey(userForm.value.r) === 'admin') return [];

    const issues = [];
    if (!selectedReviewerIds.value.length) {
        issues.push('ยังไม่ได้กำหนดลำดับการประเมิน');
    }
    if (!selectedIdpReviewerIds.value.length) {
        issues.push('ยังไม่ได้กำหนดลำดับ IDP');
    }

    return issues;
});
const templatePersonLabel = (id) => evaluatorFromId(id)?.label || id;
const templateReviewerOptions = computed(() => {
    const blocked = new Set(selectedTemplateReviewerIds.value);

    return evaluatorOptions.value.filter((person) => !blocked.has(person.value));
});
const templateAssignmentUserOptions = computed(() => {
    const blocked = new Set([
        ...selectedTemplateUserIds.value,
        ...selectedTemplateReviewerIds.value,
    ]);

    return evaluatorOptions.value.filter((person) => !blocked.has(person.value));
});
const addTemplateReviewer = () => {
    const reviewerId = selectedEvaluatorId(assessmentTemplateForm.value.reviewer_pick);
    if (!reviewerId || selectedTemplateReviewerIds.value.includes(reviewerId)) return;

    assessmentTemplateForm.value.reviewer_ids = [...(assessmentTemplateForm.value.reviewer_ids || []), reviewerId];
    assessmentTemplateForm.value.reviewer_pick = '';
};
const removeTemplateReviewer = (index) => {
    assessmentTemplateForm.value.reviewer_ids = (assessmentTemplateForm.value.reviewer_ids || []).filter((_, itemIndex) => itemIndex !== index);
};
const addTemplateAssignmentUser = () => {
    const userId = selectedEvaluatorId(assessmentTemplateForm.value.user_pick);
    if (
        !userId
        || selectedTemplateUserIds.value.includes(userId)
        || selectedTemplateReviewerIds.value.includes(userId)
    ) return;

    assessmentTemplateForm.value.assignment_user_ids = [...selectedTemplateUserIds.value, userId];
    assessmentTemplateForm.value.user_pick = '';
};
const removeTemplateAssignmentUser = (index) => {
    assessmentTemplateForm.value.assignment_user_ids = selectedTemplateUserIds.value.filter((_, itemIndex) => itemIndex !== index);
};
const userForTemplateEditContext = (template) => {
    const assignedIds = new Set(templateAssignedUserIds(template));
    const templateColumn = (template?.chainType || 'assessment') === 'idp'
        ? 'idp_reviewer_template_id'
        : 'reviewer_template_id';

    return users.value.find((user) =>
        Number(user[templateColumn] || 0) === Number(template?.id || 0)
        || assignedIds.has(Number(user.db_id || 0))
    ) || null;
};
const reviewerIdsFromAssignedTemplateUser = (template) => {
    const user = userForTemplateEditContext(template);
    if (!user) return [];

    const steps = (template?.chainType || 'assessment') === 'idp'
        ? (user.idpReviewerSteps || [])
        : (user.reviewerSteps || user.supervisorChain || []);

    return steps
        .map((step) => selectedEvaluatorId(step.id || step.reviewerId || step.reviewer_id))
        .filter(Boolean);
};
const reviewerIdsFromTemplateSteps = (template) => {
    const ids = [];
    const contextUser = userForTemplateEditContext(template);

    (template.steps || []).forEach((step) => {
        const reviewerId = resolveReviewerForTemplateStep(step, ids, contextUser);

        if (reviewerId && !ids.includes(reviewerId)) {
            ids.push(reviewerId);
        }
    });

    return ids;
};
const editableReviewerIdsForTemplate = (template) => {
    const assignedUserReviewerIds = reviewerIdsFromAssignedTemplateUser(template);
    if (assignedUserReviewerIds.length) return assignedUserReviewerIds;

    return reviewerIdsFromTemplateSteps(template);
};
const startCreateAssessmentTemplate = () => {
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
    showAssessmentTemplateCreate.value = true;
};
const startEditAssessmentTemplate = (template) => {
    if (!template?.id) return;

    const editableReviewerIds = editableReviewerIdsForTemplate(template);
    editingAssessmentTemplateId.value = template.id;
    assessmentTemplateForm.value = {
        name: template.name || '',
        description: template.description || '',
        reviewer_ids: editableReviewerIds.length
            ? editableReviewerIds
            : (template.steps || []).map((step) => selectedEvaluatorId(step.reviewerId) || ''),
        assignment_user_ids: [],
        reviewer_pick: '',
        user_pick: '',
    };
    showAssessmentTemplateCreate.value = false;
};
const openAssessmentTemplateDetail = (template) => {
    resetAssessmentTemplateForm();
    activeAssessmentTemplateId.value = template.id;
    assessmentTemplateMemberPick.value = '';
};
const closeAssessmentTemplateDetail = () => {
    activeAssessmentTemplateId.value = null;
    assessmentTemplateMemberPick.value = '';
};
const cancelAssessmentTemplateEdit = () => {
    resetAssessmentTemplateForm();
};
const returnToAssessmentTemplateList = () => {
    showAssessmentTemplateCreate.value = false;
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
};
const resetAssessmentTemplateForm = () => {
    editingAssessmentTemplateId.value = null;
    assessmentTemplateForm.value = {
        name: '',
        description: '',
        reviewer_ids: [],
        assignment_user_ids: [],
        reviewer_pick: '',
        user_pick: '',
    };
};
const addAssessmentTemplateMember = () => {
    const template = selectedAssessmentTemplate.value;
    const userId = selectedEvaluatorId(assessmentTemplateMemberPick.value);
    if (!template || !userId) return;

    router.post(route('admin.reviewer-chain-templates.users.store', template.id), {
        user_ids: [userId],
    }, {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
            assessmentTemplateMemberPick.value = '';
        },
    });
};
const removeAssessmentTemplateMember = (member) => {
    const template = selectedAssessmentTemplate.value;
    if (!template || !member?.id) return;

    if (!confirm(`ลบ ${member.name} ออกจากลำดับการประเมินนี้?`)) return;

    router.delete(route('admin.reviewer-chain-templates.users.destroy', [template.id, member.id]), {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
        },
    });
};
const deleteAssessmentTemplate = (template) => {
    if (!template?.id) return;

    if (!confirm(`ลบลำดับการประเมิน "${template.name}"? ผู้ใช้ที่ผูกกับลำดับนี้จะถูกถอดออกด้วย`)) return;

    router.delete(route('admin.reviewer-chain-templates.destroy', template.id), {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
            if (selectedAssessmentTemplate.value?.id === template.id) {
                closeAssessmentTemplateDetail();
            }
        },
    });
};
const saveAssessmentTemplate = () => {
    const form = assessmentTemplateForm.value;

    if (!form.name.trim()) {
        alert(`กรุณากรอกชื่อ${activeReviewerTemplateTitle.value}`);
        return;
    }

    if (!selectedTemplateReviewerIds.value.length) {
        alert('กรุณาเพิ่มผู้ประเมินอย่างน้อย 1 คน');
        return;
    }

    const payload = {
        name: form.name.trim(),
        description: form.description.trim(),
        reviewer_ids: selectedTemplateReviewerIds.value,
    };
    const endpoint = isEditingAssessmentTemplate.value
        ? route('admin.reviewer-chain-templates.update', editingAssessmentTemplateId.value)
        : route('admin.reviewer-chain-templates.store');

    if (!isEditingAssessmentTemplate.value) {
        payload.chain_type = activeReviewerTemplateType.value;
        payload.assignment_user_ids = selectedTemplateUserIds.value;
    }

    const submitOptions = {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
            resetAssessmentTemplateForm();
            showAssessmentTemplateCreate.value = false;
        },
    };

    if (isEditingAssessmentTemplate.value) {
        router.patch(endpoint, payload, submitOptions);
    } else {
        router.post(endpoint, payload, submitOptions);
    }
};
const templateCandidateQuery = (roleKey, blockedIds = [], contextUser = null) => {
    const blocked = new Set(blockedIds.map((id) => Number(id)));
    const currentUserId = Number(contextUser?.db_id || userForm.value.db_id || 0);

    return evaluatorOptions.value
        .filter((person) => Number(person.value) !== currentUserId)
        .filter((person) => !blocked.has(Number(person.value)))
        .filter((person) => person.r === normalizeUserRoleKey(roleKey))
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'th'));
};
const resolveReviewerForTemplateStep = (step, blockedIds = [], contextUser = null) => {
    if (step.resolverType === 'fixed_user') {
        return selectedEvaluatorId(step.reviewerId);
    }

    const contextDepartment = contextUser?.d || userForm.value.d;
    const contextWorkline = contextUser?.w || userForm.value.w;
    const userJobFamily = jobFamilyFromDepartment(contextDepartment);
    const sameDepartment = templateCandidateQuery(step.roleKey, blockedIds, contextUser)
        .find((person) => userJobFamily && jobFamilyFromDepartment(person.d) === userJobFamily);

    if (step.resolverType === 'role_same_department' && sameDepartment) {
        return selectedEvaluatorId(sameDepartment.value);
    }

    const sameWorkline = templateCandidateQuery(step.roleKey, blockedIds, contextUser)
        .find((person) => contextWorkline && person.w === contextWorkline);

    if (['role_same_department', 'role_same_workline'].includes(step.resolverType) && sameWorkline) {
        return selectedEvaluatorId(sameWorkline.value);
    }

    return selectedEvaluatorId(templateCandidateQuery(step.roleKey, blockedIds, contextUser)[0]?.value);
};
const applyReviewerTemplate = () => {
    const template = selectedReviewerTemplate.value;

    if (!template) return;

    syncOrgPath();
    const ids = [];
    (template.steps || []).forEach((step) => {
        const reviewerId = resolveReviewerForTemplateStep(step, ids);

        if (reviewerId && !ids.includes(reviewerId)) {
            ids.push(reviewerId);
        }
    });

    userForm.value.reviewer_ids = ids.length ? ids : [''];
    clearReviewerSearch();
};
const applyIdpReviewerTemplate = () => {
    const template = selectedIdpReviewerTemplate.value;

    if (!template) return;

    syncOrgPath();
    const ids = [];
    (template.steps || []).forEach((step) => {
        const reviewerId = resolveReviewerForTemplateStep(step, ids);

        if (reviewerId && !ids.includes(reviewerId)) {
            ids.push(reviewerId);
        }
    });

    userForm.value.idp_reviewer_ids = ids;
};
const reviewerChoicesForStep = (stepIndex) => {
    const selectedInOtherSteps = new Set(
        (userForm.value.reviewer_ids || [])
            .map((id, index) => index === stepIndex ? '' : selectedEvaluatorId(id))
            .filter(Boolean),
    );

    return evaluatorOptions.value.filter((person) =>
        person.value !== Number(userForm.value.db_id || 0)
        && !selectedInOtherSteps.has(person.value),
    );
};
const normalizeReviewerList = () => {
    userForm.value.reviewer_ids = selectedReviewerIds.value;
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const addReviewerStep = () => {
    userForm.value.reviewer_ids = [...(userForm.value.reviewer_ids || []), ''];
};
const removeReviewerStep = (index) => {
    userForm.value.reviewer_ids = (userForm.value.reviewer_ids || []).filter((_, itemIndex) => itemIndex !== index);
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const updateReviewerStep = (index, value) => {
    const next = [...(userForm.value.reviewer_ids || [])];
    next[index] = selectedEvaluatorId(value);
    userForm.value.reviewer_ids = next;
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const reviewerInputValue = (index, reviewerId) => {
    if (Object.prototype.hasOwnProperty.call(reviewerSearchTerms.value, index)) {
        return reviewerSearchTerms.value[index];
    }

    return evaluatorFromId(reviewerId)?.label || '';
};
const updateReviewerSearch = (index, value) => {
    activeReviewerDropdown.value = index;
    reviewerSearchTerms.value = {
        ...reviewerSearchTerms.value,
        [index]: value,
    };

    const exactMatch = reviewerChoicesForStep(index).find((person) =>
        person.label === value
        || person.displayName === value
        || person.name === value,
    );

    if (exactMatch) {
        updateReviewerStep(index, exactMatch.value);
    }
};
const filteredReviewerChoicesForStep = (index) => {
    const term = String(reviewerSearchTerms.value[index] || '').trim().toLowerCase();
    const choices = reviewerChoicesForStep(index);

    if (!term) return choices;

    return choices.filter((person) => person.searchText.includes(term));
};
const openReviewerDropdown = (index, reviewerId) => {
    activeReviewerDropdown.value = index;
    reviewerSearchTerms.value = {
        ...reviewerSearchTerms.value,
        [index]: Object.prototype.hasOwnProperty.call(reviewerSearchTerms.value, index)
            ? reviewerSearchTerms.value[index]
            : reviewerInputValue(index, reviewerId),
    };
};
const isReviewerDropdownOpen = (index) => activeReviewerDropdown.value === index;
const chooseReviewerStep = (index, value) => {
    updateReviewerStep(index, value);
};
const clearReviewerSearch = () => {
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const closeReviewerDropdown = () => {
    activeReviewerDropdown.value = null;
};
const openReviewerModal = () => {
    showReviewerModal.value = true;
    if (!selectedReviewerIds.value.length) {
        userForm.value.reviewer_ids = [''];
    }
};
const closeReviewerModal = () => {
    normalizeReviewerList();
    showReviewerModal.value = false;
};
const openReviewerTemplateModal = (chainType = 'assessment') => {
    activeReviewerTemplateModal.value = chainType === 'idp' ? 'idp' : 'assessment';
    showAssessmentTemplateCreate.value = false;
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
};
const closeReviewerTemplateModal = () => {
    activeReviewerTemplateModal.value = '';
    showAssessmentTemplateCreate.value = false;
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
};
const supervisorIdFromUser = (user, idKey, nameKey) => {
    const explicitId = selectedEvaluatorId(user?.[idKey]);
    if (explicitId) return explicitId;

    const storedName = (user?.[nameKey] || '').trim();
    if (!storedName) return '';

    return evaluatorOptions.value.find((person) =>
        person.name === storedName
        || person.label === storedName
        || person.label.startsWith(storedName),
    )?.value || '';
};
const evaluatorFromId = (id) =>
    evaluatorOptions.value.find((person) => person.value === selectedEvaluatorId(id)) || null;
const reviewerOption = (user) =>
    evaluatorOptions.value.find((person) => person.value === Number(user.db_id))
    || {
        key: user.db_id,
        value: Number(user.db_id),
        name: user.n,
        displayName: `${user.t || ''}${user.n}`,
        label: `${user.t || ''}${user.n}${user.p ? ` · ${user.p}` : ''}`,
    };
const filteredEvaluatorOptions = (query, selectedValue, blockedValue) => {
    const needle = query.trim().toLowerCase();
    const selectedId = selectedEvaluatorId(selectedValue);
    const blockedId = selectedEvaluatorId(blockedValue);

    return evaluatorOptions.value.filter((person) =>
        (person.value === selectedId || person.value !== blockedId)
        && (
            person.value === selectedId
            || !needle
            || person.searchText.includes(needle)
            || person.label.toLowerCase().includes(needle)
        ),
    );
};
const deptHeadOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'dept_head')
        .map(reviewerOption),
);

const supervisorOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'supervisor')
        .map(reviewerOption),
);

const deanOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'dean')
        .map(reviewerOption),
);

const canPickEvaluator1 = computed(() => !['admin', 'supervisor', 'dept_head', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const canPickEvaluator2 = computed(() => !['admin', 'dept_head', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const canPickEvaluator3 = computed(() => !['admin', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const isDeanRole = computed(() => normalizeUserRoleKey(userForm.value.r) === 'dean');

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
    form.reviewer_template_id = '';
    form.idp_reviewer_template_id = '';
    form.reviewer_ids = [];
    form.idp_reviewer_ids = [];
};

const resetOrgSelection = () => {
    userForm.value.dept = '';
    userForm.value.job = '';
    userForm.value.unit = '';
    userForm.value.d = '';
    userForm.value.p = '';
    userForm.value.l = '';
    userForm.value.reviewer_template_id = '';
    userForm.value.idp_reviewer_template_id = '';
    userForm.value.reviewer_ids = [];
    userForm.value.idp_reviewer_ids = [];
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
    userForm.value.p = isDeanRole.value ? userForm.value.job : '';
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
    const scopedLevels = levelsByJobFamily.value[userForm.value.w]?.[userForm.value.job] || [];
    const directLevels = levelsByWorkline.value[userForm.value.w] || [];
    if (!scopedLevels.length && !directLevels.length && userForm.value.p) {
        userForm.value.l = userForm.value.p;
    }
};

const handleRoleChange = () => {
    if (isDeanRole.value && userForm.value.job) {
        userForm.value.p = userForm.value.job;
    }

    normalizeReviewerList();
};

const resetUserForm = (data = null) => {
    const org = parseOrgPath(data?.d || '');
    const [firstName = '', ...lastNameParts] = (data?.n || '').split(' ');

    editingUserKey.value = data?.sso || null;
    supervisorSearch.value = '';
    evaluator2Search.value = '';
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
        reviewer_template_id: data?.reviewer_template_id || '',
        idp_reviewer_template_id: data?.idp_reviewer_template_id || '',
        reviewer_ids: (data?.reviewerSteps || [])
            .map((step) => selectedEvaluatorId(step?.id))
            .filter(Boolean),
        idp_reviewer_ids: (data?.idpReviewerSteps || [])
            .map((step) => selectedEvaluatorId(step?.id))
            .filter(Boolean),
        act: data?.act !== false,
        structureStatus: data?.structureStatus || 'ok',
        structureIssues: Array.isArray(data?.structureIssues) ? data.structureIssues : [],
    };
    normalizeReviewerList();
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
    if (isDeanRole.value && form.job) {
        form.p = form.job;
    }
    const thaiName = [form.fn.trim(), form.ln.trim()].filter(Boolean).join(' ');

    if (!form.sso.trim() || !thaiName) {
        alert('กรุณากรอก ID และชื่อผู้ใช้');
        return;
    }

    if (!form.w || !form.job || (!isDeanRole.value && !form.p) || !form.l) {
        alert('กรุณาเลือกสายงาน กลุ่มงาน ตำแหน่ง และระดับตำแหน่งให้ครบถ้วน');
        return;
    }

    if (!isDeanRole.value && !positionOptions.value.includes(form.p)) {
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
        p: (isDeanRole.value ? form.job : form.p).trim(),
        l: form.l.trim(),
        reviewer_template_id: selectedEvaluatorId(form.reviewer_template_id) || null,
        idp_reviewer_template_id: selectedEvaluatorId(form.idp_reviewer_template_id) || null,
        reviewer_ids: selectedReviewerIds.value,
        idp_reviewer_ids: selectedIdpReviewerIds.value,
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
    <Head :title="currentPageTitle" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <div v-if="showSidebar" class="sidebar">
            <SidebarBrand />

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
                    :open-reviewer-template-modal="openReviewerTemplateModal"
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
                    :delivery-type-settings="idpDeliveryTypeSettings"
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

                <div v-if="userWorkflowIssues.length" class="admin-user-warning">
                    <div class="admin-user-warning-title">ต้องตรวจสอบข้อมูลผู้ใช้นี้</div>
                    <ul>
                        <li v-for="issue in userWorkflowIssues" :key="issue">{{ issue }}</li>
                    </ul>
                </div>

                <section class="workflow-role-panel user-role-top-panel">
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
                </section>

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
                    <div v-if="!isDeanRole" class="fg">
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
                    <div v-else class="fg">
                        <label class="lbl">ตำแหน่ง</label>
                        <input :value="userForm.job" class="inp modal-input" disabled />
                        <div class="modal-help">
                            บทบาทคณบดีใช้กลุ่มงานเป็นตำแหน่งโดยอัตโนมัติ
                        </div>
                    </div>
                    <div v-if="userForm.p || isDeanRole" class="fg">
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
                    <section class="workflow-group">
                        <div class="workflow-group-head">
                            <div class="workflow-title-row">
                                <span class="workflow-number">1</span>
                                <div class="workflow-copy">
                                    <h4>ลำดับในการประเมิน</h4>
                                    <p>{{ reviewerSummary }}</p>
                                </div>
                            </div>
                            <button class="btn btn-s reviewer-config-btn" type="button" @click="openReviewerModal">
                                จัดการลำดับ
                            </button>
                        </div>

                        <div v-if="assessmentReviewerTemplates.length" class="workflow-template-row">
                            <div class="fg">
                                <label class="lbl">Template ลำดับในการประเมิน</label>
                                <select v-model="userForm.reviewer_template_id" class="sel modal-input">
                                    <option value="">— ไม่ใช้ template —</option>
                                    <option
                                        v-for="template in assessmentReviewerTemplates"
                                        :key="`reviewer-template-${template.id}`"
                                        :value="template.id"
                                    >
                                        {{ template.name }}
                                    </option>
                                </select>
                            </div>
                            <button
                                class="btn btn-s workflow-template-btn"
                                type="button"
                                :disabled="!selectedReviewerTemplate"
                                @click="applyReviewerTemplate"
                            >
                                ใช้ template นี้
                            </button>
                            <div class="modal-help workflow-template-help">
                                {{ reviewerTemplateDescription }}
                            </div>
                        </div>
                    </section>

                    <section class="workflow-group">
                        <div class="workflow-group-head">
                            <div class="workflow-title-row">
                                <span class="workflow-number">2</span>
                                <div class="workflow-copy">
                                    <h4>ลำดับการทำ IDP</h4>
                                    <p>{{ idpReviewerSummary }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="idpReviewerTemplates.length" class="workflow-template-row">
                            <div class="fg">
                                <label class="lbl">Template ลำดับการทำ IDP</label>
                                <select v-model="userForm.idp_reviewer_template_id" class="sel modal-input">
                                    <option value="">— ใช้ลำดับประเมินเป็น fallback —</option>
                                    <option
                                        v-for="template in idpReviewerTemplates"
                                        :key="`idp-reviewer-template-${template.id}`"
                                        :value="template.id"
                                    >
                                        {{ template.name }}
                                    </option>
                                </select>
                            </div>
                            <button
                                class="btn btn-s workflow-template-btn"
                                type="button"
                                :disabled="!selectedIdpReviewerTemplate"
                                @click="applyIdpReviewerTemplate"
                            >
                                ใช้ template IDP
                            </button>
                            <div class="modal-help workflow-template-help">
                                ถ้ายังไม่กำหนดลำดับ IDP ระบบจะใช้ลำดับในการประเมินเป็นค่า fallback
                            </div>
                        </div>
                    </section>
                </div>

                <div v-if="showReviewerModal" class="reviewer-modal-backdrop" @click.self="closeReviewerModal">
                    <div class="reviewer-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-modal-title" @click="closeReviewerDropdown">
                        <div class="reviewer-modal-head">
                            <div>
                                <h3 id="reviewer-modal-title">จัดการลำดับการประเมิน</h3>
                                <p>เพิ่มหรือลดผู้ประเมินได้อิสระ ระบบจะส่งงานตามลำดับที่กำหนด</p>
                            </div>
                            <button class="modal-close-btn" type="button" aria-label="ปิดหน้าต่าง" @click="closeReviewerModal">
                                ×
                            </button>
                        </div>

                        <div class="reviewer-step-list">
                            <div
                                v-for="(reviewerId, index) in userForm.reviewer_ids"
                                :key="`reviewer-step-${index}`"
                                class="reviewer-step-row"
                            >
                                <div class="reviewer-step-badge">{{ index + 1 }}</div>
                                <div class="reviewer-step-main">
                                    <label class="lbl">ลำดับที่ {{ index + 1 }}</label>
                                    <input
                                        class="inp modal-input reviewer-search-input"
                                        type="text"
                                        placeholder="พิมพ์ชื่อ / ตำแหน่ง / หน่วยงาน"
                                        :value="reviewerInputValue(index, reviewerId)"
                                        @click.stop="openReviewerDropdown(index, reviewerId)"
                                        @focus="openReviewerDropdown(index, reviewerId)"
                                        @input="updateReviewerSearch(index, $event.target.value)"
                                        @keydown.escape="clearReviewerSearch"
                                    />
                                    <div v-if="isReviewerDropdownOpen(index)" class="reviewer-choice-list" @click.stop>
                                        <button
                                            v-for="person in filteredReviewerChoicesForStep(index)"
                                            :key="`reviewer-choice-${index}-${person.key}`"
                                            class="reviewer-choice-item"
                                            type="button"
                                            @mousedown.prevent="chooseReviewerStep(index, person.value)"
                                        >
                                            <span>{{ person.displayName }}</span>
                                            <small>{{ person.p || '-' }}</small>
                                        </button>
                                        <div v-if="filteredReviewerChoicesForStep(index).length === 0" class="reviewer-choice-empty">
                                            ไม่พบรายชื่อที่ค้นหา
                                        </div>
                                    </div>
                                </div>
                                <button
                                    class="btn btn-s reviewer-remove-btn"
                                    type="button"
                                    :disabled="(userForm.reviewer_ids || []).length <= 1"
                                    @click="removeReviewerStep(index)"
                                >
                                    ลบ
                                </button>
                            </div>
                        </div>

                        <div class="reviewer-modal-actions">
                            <button class="btn btn-s" type="button" @click="addReviewerStep">
                                + เพิ่มผู้ประเมิน
                            </button>
                            <button class="btn btn-p reviewer-done-btn" type="button" @click="closeReviewerModal">
                                เสร็จสิ้น
                            </button>
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

    <div v-if="activeReviewerTemplateModal" class="reviewer-modal-backdrop" @click.self="closeReviewerTemplateModal">
        <div class="reviewer-template-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-template-modal-title">
            <div class="reviewer-modal-head">
                <div>
                    <h3 id="reviewer-template-modal-title">{{ activeReviewerTemplateTitle }}</h3>
                    <p>{{ activeReviewerTemplateSubtitle }}</p>
                </div>
                <button class="modal-close-btn" type="button" aria-label="ปิดหน้าต่าง" @click="closeReviewerTemplateModal">
                    ×
                </button>
            </div>

            <div class="reviewer-template-modal-body">
                <div v-if="!showAssessmentTemplateCreate && !selectedAssessmentTemplate" class="assessment-template-list-view">
                    <div class="assessment-template-list-head">
                        <div>
                            <h4>{{ activeReviewerTemplateTitle }} ทั้งหมด</h4>
                            <p>รายการ workflow ที่บันทึกไว้ในระบบ</p>
                        </div>
                        <button class="btn btn-p" type="button" @click="startCreateAssessmentTemplate">
                            + เพิ่ม{{ activeReviewerTemplateTitle }}
                        </button>
                    </div>

                    <div v-if="!activeReviewerTemplateList.length" class="reviewer-template-empty">
                        ยังไม่มี{{ activeReviewerTemplateTitle }}
                    </div>

                    <div v-else class="assessment-template-list">
                        <div
                            v-for="template in activeReviewerTemplateList"
                            :key="`${activeReviewerTemplateType}-template-${template.id}`"
                            class="assessment-template-row"
                            role="button"
                            tabindex="0"
                            @click="openAssessmentTemplateDetail(template)"
                            @keydown.enter.prevent="openAssessmentTemplateDetail(template)"
                        >
                            <div class="assessment-template-row-main">
                                <div class="assessment-template-title-line">
                                    <div>
                                        <div class="reviewer-template-name">{{ template.name }}</div>
                                        <div class="reviewer-template-desc">{{ template.description || 'ไม่มีคำอธิบาย' }}</div>
                                    </div>
                                </div>

                                <div v-if="(template.steps || []).length" class="assessment-step-track">
                                    <template
                                        v-for="(step, stepIndex) in template.steps"
                                        :key="`${activeReviewerTemplateType}-template-${template.id}-step-${step.step}`"
                                    >
                                        <div class="assessment-step-pill">
                                            <span>{{ stepIndex + 1 }}</span>
                                            <strong>{{ templateStepLabel(step) }}</strong>
                                        </div>
                                        <div
                                            v-if="stepIndex < (template.steps || []).length - 1"
                                            class="assessment-step-arrow"
                                        >
                                            ->
                                        </div>
                                    </template>
                                </div>
                                <div v-else class="assessment-template-chain-text">
                                    ยังไม่ได้กำหนดผู้ประเมิน
                                </div>
                            </div>
                            <div class="assessment-template-row-meta">
                                <div class="assessment-template-stat wide">
                                    <b>{{ templateAssignedUserIds(template).length }}</b>
                                    <span>ผู้ใช้</span>
                                </div>
                                <button class="assessment-template-detail-btn" type="button" @click.stop="openAssessmentTemplateDetail(template)">
                                    ดู
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="!showAssessmentTemplateCreate && selectedAssessmentTemplate" class="assessment-template-detail-view">
                    <div class="assessment-template-detail-head">
                        <div>
                            <template v-if="isEditingSelectedAssessmentTemplate">
                                <input
                                    v-model="assessmentTemplateForm.name"
                                    class="assessment-template-inline-name"
                                    :placeholder="`ชื่อ${activeReviewerTemplateTitle}`"
                                />
                                <textarea
                                    v-model="assessmentTemplateForm.description"
                                    class="assessment-template-inline-desc"
                                    placeholder="คำอธิบาย"
                                    rows="1"
                                ></textarea>
                            </template>
                            <template v-else>
                                <h4>{{ selectedAssessmentTemplate.name }}</h4>
                                <p>{{ selectedAssessmentTemplate.description || 'ไม่มีคำอธิบาย' }}</p>
                            </template>
                        </div>
                        <div class="assessment-template-detail-actions">
                            <button
                                v-if="!isEditingSelectedAssessmentTemplate"
                                class="assessment-template-detail-btn"
                                type="button"
                                @click="startEditAssessmentTemplate(selectedAssessmentTemplate)"
                            >
                                แก้ไข
                            </button>
                            <button
                                v-if="isEditingSelectedAssessmentTemplate"
                                class="assessment-template-detail-btn"
                                type="button"
                                @click="cancelAssessmentTemplateEdit"
                            >
                                ยกเลิก
                            </button>
                            <button
                                v-if="isEditingSelectedAssessmentTemplate"
                                class="btn btn-p"
                                type="button"
                                @click="saveAssessmentTemplate"
                            >
                                ยืนยัน
                            </button>
                            <button
                                v-if="!isEditingSelectedAssessmentTemplate"
                                class="assessment-template-delete-btn"
                                type="button"
                                @click="deleteAssessmentTemplate(selectedAssessmentTemplate)"
                            >
                                ลบลำดับ
                            </button>
                        </div>
                        <div v-if="isEditingSelectedAssessmentTemplate" class="assessment-step-track detail editable">
                            <template
                                v-for="(reviewerId, index) in assessmentTemplateForm.reviewer_ids"
                                :key="`detail-template-selected-reviewer-${reviewerId}-${index}`"
                            >
                                <div class="assessment-step-pill editable">
                                    <span>{{ index + 1 }}</span>
                                    <select
                                        :value="reviewerId"
                                        class="assessment-step-inline-select"
                                        @change="assessmentTemplateForm.reviewer_ids = assessmentTemplateForm.reviewer_ids.map((id, itemIndex) => itemIndex === index ? selectedEvaluatorId($event.target.value) : id)"
                                    >
                                        <option value="">เลือกผู้ประเมิน</option>
                                        <option
                                            v-for="person in evaluatorOptions"
                                            :key="`detail-template-reviewer-option-${index}-${person.value}`"
                                            :value="person.value"
                                            :disabled="selectedTemplateReviewerIds.includes(selectedEvaluatorId(person.value)) && selectedEvaluatorId(person.value) !== reviewerId"
                                        >
                                            {{ person.label }}
                                        </option>
                                    </select>
                                    <button class="assessment-step-inline-remove" type="button" @click="removeTemplateReviewer(index)">×</button>
                                </div>
                                <div
                                    v-if="index < assessmentTemplateForm.reviewer_ids.length - 1"
                                    class="assessment-step-arrow"
                                >
                                    ->
                                </div>
                            </template>
                            <div class="assessment-step-pill add">
                                <span>+</span>
                                <select v-model="assessmentTemplateForm.reviewer_pick" class="assessment-step-inline-select" @change="addTemplateReviewer">
                                    <option value="">เพิ่มผู้ประเมิน</option>
                                    <option
                                        v-for="person in templateReviewerOptions"
                                        :key="`detail-template-reviewer-add-${person.value}`"
                                        :value="person.value"
                                    >
                                        {{ person.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div v-else-if="(selectedAssessmentTemplate.steps || []).length" class="assessment-step-track detail">
                            <template
                                v-for="(step, stepIndex) in selectedAssessmentTemplate.steps"
                                :key="`selected-assessment-template-step-${step.step}`"
                            >
                                <div class="assessment-step-pill">
                                    <span>{{ stepIndex + 1 }}</span>
                                    <strong>{{ templateStepLabel(step) }}</strong>
                                </div>
                                <div
                                    v-if="stepIndex < (selectedAssessmentTemplate.steps || []).length - 1"
                                    class="assessment-step-arrow"
                                >
                                    ->
                                </div>
                            </template>
                        </div>
                        <div v-else class="assessment-template-chain-text">
                            ยังไม่ได้กำหนดผู้ประเมิน
                        </div>
                    </div>

                    <div class="assessment-template-member-panel">
                        <div class="template-builder-head">
                            <div>
                                <h4>ผู้ใช้ที่ใช้ลำดับนี้ {{ templateAssignedUsers(selectedAssessmentTemplate).length }} คน</h4>
                            </div>
                        </div>

                        <div class="template-picker-row">
                            <select v-model="assessmentTemplateMemberPick" class="sel modal-input">
                                <option value="">เพิ่มผู้ใช้{{ activeReviewerTemplateTitle }}</option>
                                <option
                                    v-for="person in selectedAssessmentTemplateUserOptions"
                                    :key="`assessment-template-member-${person.value}`"
                                    :value="person.value"
                                >
                                    {{ person.label }}
                                </option>
                            </select>
                            <button class="btn btn-p" type="button" @click="addAssessmentTemplateMember">
                                + เพิ่มสมาชิก
                            </button>
                        </div>

                        <div v-if="templateAssignedUsers(selectedAssessmentTemplate).length" class="assessment-template-member-list">
                            <div
                                v-for="member in templateAssignedUsers(selectedAssessmentTemplate)"
                                :key="`assessment-template-member-row-${member.id}`"
                                class="assessment-template-member-row"
                            >
                                <div class="assessment-template-member-avatar">
                                    {{ member.name.slice(0, 1) }}
                                </div>
                                <div>
                                    <strong>{{ member.name }}</strong>
                                    <span>{{ member.position }} · {{ member.department }}</span>
                                </div>
                                <button class="assessment-template-member-remove" type="button" @click="removeAssessmentTemplateMember(member)">
                                    ลบ
                                </button>
                            </div>
                        </div>
                        <div v-else class="reviewer-template-empty compact">
                            ยังไม่มีผู้ใช้ในลำดับนี้
                        </div>
                    </div>
                </div>

                <div v-else class="assessment-template-builder">
                    <div class="template-builder-grid">
                        <div class="fg">
                            <label class="lbl req">ชื่อ{{ activeReviewerTemplateTitle }}</label>
                            <input
                                v-model="assessmentTemplateForm.name"
                                class="inp modal-input"
                                :placeholder="activeReviewerTemplateType === 'idp' ? 'เช่น ลำดับ IDP สายสนับสนุน' : 'เช่น ลำดับประเมินสายสนับสนุน'"
                            />
                        </div>
                        <div class="fg">
                            <label class="lbl">คำอธิบาย</label>
                            <input
                                v-model="assessmentTemplateForm.description"
                                class="inp modal-input"
                                placeholder="อธิบายว่าใช้กับกลุ่มไหน"
                            />
                        </div>
                    </div>

                    <div class="template-builder-section">
                        <div class="template-builder-head">
                            <div>
                                <h4>ลำดับผู้ประเมิน</h4>
                                <p>เลือกเป็นชื่อคนจริงตามลำดับ เช่น นาย A -> นาย B -> นาย C</p>
                            </div>
                        </div>

                        <div class="template-picker-row">
                            <select v-model="assessmentTemplateForm.reviewer_pick" class="sel modal-input">
                                <option value="">เลือกผู้ประเมิน</option>
                                <option
                                    v-for="person in templateReviewerOptions"
                                    :key="`template-reviewer-${person.value}`"
                                    :value="person.value"
                                >
                                    {{ person.label }}
                                </option>
                            </select>
                            <button class="btn btn-s" type="button" @click="addTemplateReviewer">+ เพิ่มลำดับ</button>
                        </div>

                        <div v-if="selectedTemplateReviewerIds.length" class="template-chain-list">
                            <div
                                v-for="(reviewerId, index) in selectedTemplateReviewerIds"
                                :key="`template-selected-reviewer-${reviewerId}`"
                                class="template-chain-item"
                            >
                                <span class="template-chain-no">{{ index + 1 }}</span>
                                <span>{{ templatePersonLabel(reviewerId) }}</span>
                                <button class="btn btn-s" type="button" @click="removeTemplateReviewer(index)">ลบ</button>
                            </div>
                        </div>
                        <div v-else class="reviewer-template-empty compact">
                            ยังไม่ได้เพิ่มผู้ประเมิน
                        </div>
                    </div>

                    <div v-if="!isEditingAssessmentTemplate" class="template-builder-section">
                        <div class="template-builder-head">
                            <div>
                                <h4>ผู้ใช้ที่จะใช้ลำดับนี้</h4>
                                <p>เลือกได้ภายหลัง หากต้องการสร้างลำดับเก็บไว้ก่อน</p>
                            </div>
                        </div>

                        <div class="template-picker-row">
                            <select v-model="assessmentTemplateForm.user_pick" class="sel modal-input">
                                <option value="">เลือกผู้ใช้</option>
                                <option
                                    v-for="person in templateAssignmentUserOptions"
                                    :key="`template-user-${person.value}`"
                                    :value="person.value"
                                >
                                    {{ person.label }}
                                </option>
                            </select>
                            <button class="btn btn-s" type="button" @click="addTemplateAssignmentUser">+ เพิ่มผู้ใช้</button>
                        </div>

                        <div v-if="selectedTemplateUserIds.length" class="template-user-list">
                            <div
                                v-for="(userId, index) in selectedTemplateUserIds"
                                :key="`template-selected-user-${userId}`"
                                class="template-user-chip"
                            >
                                <span>{{ templatePersonLabel(userId) }}</span>
                                <button type="button" @click="removeTemplateAssignmentUser(index)">×</button>
                            </div>
                        </div>
                        <div v-else class="reviewer-template-empty compact">
                            ยังไม่ได้เพิ่มผู้ใช้
                        </div>
                    </div>
                </div>

            </div>

            <div class="reviewer-template-modal-actions">
                <button
                    v-if="showAssessmentTemplateCreate || selectedAssessmentTemplate"
                    class="btn btn-s"
                    type="button"
                    @click="returnToAssessmentTemplateList"
                >
                    กลับไปหน้ารายการ
                </button>
                <button class="btn btn-s" type="button" @click="closeReviewerTemplateModal">ปิด</button>
                <button
                    v-if="showAssessmentTemplateCreate"
                    class="btn btn-p"
                    type="button"
                    @click="saveAssessmentTemplate"
                >
                    {{ isEditingAssessmentTemplate ? 'ยืนยัน' : `บันทึก${activeReviewerTemplateTitle}` }}
                </button>
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
    width: min(800px, calc(100vw - 32px));
    max-height: min(88vh, 760px);
    margin: 14px 0;
    border-radius: 14px;
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
    column-gap: 20px;
    row-gap: 20px;
}

.modal-grid.single-col {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.modal-input {
    min-height: 44px;
    border-radius: 8px;
    border-color: var(--border);
    font-size: 14px;
    padding-top: 8px;
    padding-bottom: 8px;
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
    margin: 4px 0 16px;
    display: grid;
    gap: 12px;
}

.workflow-role-panel,
.workflow-group {
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.workflow-role-panel {
    padding: 16px;
}

.user-role-top-panel {
    margin-bottom: 14px;
}

.workflow-group {
    display: grid;
    gap: 14px;
    padding: 16px;
}

.evaluator-role-field {
    max-width: 420px;
}

.evaluator-role-field .lbl {
    margin-bottom: 7px;
    color: var(--text3);
    font-size: 14px;
    font-weight: 800;
}

.evaluator-role-field .modal-input {
    min-height: 47px;
    padding-left: 14px;
    padding-right: 14px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 500;
}

.workflow-group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.workflow-title-row {
    min-width: 0;
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
}

.workflow-number {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: #fff2ea;
    color: var(--accent);
    font-size: 18px;
    font-weight: 900;
}

.workflow-copy {
    min-width: 0;
}

.workflow-copy h4 {
    margin: 0;
    color: var(--text);
    font-size: 17px;
    font-weight: 900;
}

.workflow-copy p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
}

.workflow-template-row {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) auto;
    gap: 12px;
    align-items: end;
    padding: 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
}

.workflow-template-help {
    grid-column: 1 / -1;
    margin-top: 0;
}

.workflow-template-btn {
    min-height: 44px;
}

.reviewer-config-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
}

.reviewer-template-card {
    display: grid;
    gap: 8px;
    margin-top: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #f8fafc;
}

.reviewer-template-grid {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.reviewer-template-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.reviewer-config-copy {
    min-width: 0;
    display: grid;
    gap: 5px;
}

.reviewer-config-title {
    color: var(--text);
    font-size: 15px;
    font-weight: 800;
}

.reviewer-config-summary {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.45;
}

.reviewer-config-btn {
    flex: 0 0 auto;
}

.reviewer-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.42);
}

.reviewer-modal {
    width: min(680px, 100%);
    max-height: min(82vh, 720px);
    overflow-y: auto;
    border: 1px solid #dbe3ef;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
}

.reviewer-template-modal {
    width: min(900px, 100%);
    max-height: min(84vh, 760px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #dbe3ef;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
}

.reviewer-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.reviewer-modal-head h3 {
    margin: 0 0 4px;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
}

.reviewer-modal-head p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
}

.reviewer-template-modal-body {
    display: grid;
    gap: 14px;
    overflow-y: auto;
    padding: 18px 20px;
    background: #f8fafc;
}

.reviewer-template-empty {
    padding: 28px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #64748b;
    text-align: center;
    font-weight: 700;
}

.reviewer-template-overview-card {
    display: grid;
    gap: 14px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.reviewer-template-overview-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.reviewer-template-name {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    color: var(--text);
    font-size: 17px;
    font-weight: 900;
}

.reviewer-template-chain-badge,
.reviewer-template-default-badge,
.reviewer-template-scope-pill {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 4px 9px;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 12px;
    font-weight: 800;
}

.reviewer-template-chain-badge {
    background: #e0f2fe;
    color: #0369a1;
}

.reviewer-template-desc {
    margin-top: 4px;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.reviewer-template-step-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.reviewer-template-step-card {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fbfdff;
}

.reviewer-template-step-no {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-weight: 900;
}

.reviewer-template-step-title {
    color: var(--text);
    font-size: 13px;
    font-weight: 900;
    line-height: 1.35;
}

.reviewer-template-step-meta {
    margin-top: 3px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 700;
}

.reviewer-template-scope-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.reviewer-template-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
}

.assessment-template-builder {
    display: grid;
    gap: 14px;
}

.assessment-template-list-view {
    display: grid;
    gap: 18px;
}

.assessment-template-list-head,
.assessment-template-builder-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.assessment-template-list-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
}

.assessment-template-list-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.assessment-template-list {
    display: grid;
    gap: 12px;
}

.assessment-template-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 190px;
    gap: 18px;
    padding: 18px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.assessment-template-row:hover {
    border-color: #f2b7a3;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

.assessment-template-row-main {
    min-width: 0;
}

.assessment-template-title-line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.assessment-template-chain-text {
    margin-top: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.5;
}

.assessment-step-track {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
    padding: 12px;
    border-radius: 8px;
    background: #f8fafc;
}

.assessment-step-track.detail {
    margin-top: 12px;
}

.assessment-step-pill {
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    max-width: 100%;
    gap: 8px;
    padding: 7px 10px 7px 7px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 900;
}

.assessment-step-pill span {
    width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-size: 12px;
}

.assessment-step-pill strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-step-arrow {
    color: #cbd5e1;
    font-weight: 900;
}

.assessment-template-row-meta {
    display: grid;
    grid-template-columns: 1fr;
    align-content: stretch;
    gap: 10px;
    padding: 12px;
    border-radius: 8px;
    background: #fff7ed;
}

.assessment-template-stat {
    display: grid;
    gap: 2px;
    padding: 10px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #fed7aa;
}

.assessment-template-stat.wide {
    min-height: 86px;
}

.assessment-template-stat b {
    color: #cf3c23;
    font-size: 24px;
    line-height: 1;
}

.assessment-template-stat span {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
}

.assessment-template-users {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.45;
}

.assessment-template-detail-btn {
    min-height: 36px;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fff;
    color: #cf3c23;
    font-weight: 900;
    cursor: pointer;
}

.assessment-template-delete-btn {
    min-height: 36px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff;
    color: #dc2626;
    font-weight: 900;
    cursor: pointer;
}

.assessment-template-delete-btn:hover {
    background: #fef2f2;
    border-color: #fca5a5;
}

.assessment-template-detail-view {
    display: grid;
    gap: 14px;
}

.assessment-template-detail-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.assessment-template-detail-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
}

.assessment-template-detail-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}

.assessment-template-inline-name {
    display: block;
    width: min(100%, 560px);
    max-width: 560px;
    padding: 0 2px 4px;
    border: 0;
    border-bottom: 2px solid transparent;
    outline: 0;
    background: transparent;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
    line-height: 1.25;
}

.assessment-template-inline-desc {
    display: block;
    width: min(100%, 760px);
    max-width: 760px;
    min-height: 30px;
    margin-top: 4px;
    padding: 0 2px 4px;
    resize: none;
    overflow: hidden;
    border: 0;
    border-bottom: 2px solid transparent;
    outline: 0;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.5;
    font-family: inherit;
}

.assessment-template-inline-name:hover,
.assessment-template-inline-desc:hover {
    border-bottom-color: #e2e8f0;
}

.assessment-template-inline-name:focus,
.assessment-template-inline-desc:focus {
    border-bottom-color: #cf3c23;
}

.assessment-template-detail-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.assessment-template-detail-head .assessment-step-track,
.assessment-template-detail-head .assessment-template-chain-text {
    grid-column: 1 / -1;
}

.assessment-step-track.editable {
    align-items: center;
    border: 1px dashed #dbe4ef;
}

.assessment-step-pill.editable,
.assessment-step-pill.add {
    padding-right: 8px;
}

.assessment-step-pill.add {
    border-style: dashed;
    color: #64748b;
}

.assessment-step-inline-select {
    min-width: 160px;
    max-width: 320px;
    border: 0;
    outline: 0;
    background: transparent;
    color: #334155;
    font: inherit;
    font-weight: 900;
    cursor: pointer;
}

.assessment-step-inline-remove {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border: 0;
    border-radius: 999px;
    background: #fff1f2;
    color: #dc2626;
    font-size: 16px;
    font-weight: 900;
    line-height: 1;
    cursor: pointer;
}

.assessment-template-member-panel {
    display: grid;
    gap: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.assessment-template-member-list {
    display: grid;
    gap: 10px;
}

.assessment-template-member-row {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fbfdff;
}

.assessment-template-member-avatar {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-weight: 900;
}

.assessment-template-member-row strong,
.assessment-template-member-row span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-template-member-row strong {
    color: var(--text);
    font-size: 14px;
    font-weight: 900;
}

.assessment-template-member-row span {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.assessment-template-member-remove {
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff;
    color: #dc2626;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
}

.assessment-template-member-remove:hover {
    background: #fef2f2;
    border-color: #fca5a5;
}

.template-builder-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
    gap: 12px;
}

.template-builder-section {
    display: grid;
    gap: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.template-builder-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.template-builder-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 16px;
    font-weight: 900;
}

.template-builder-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.template-picker-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
}

.template-chain-list {
    display: grid;
    gap: 8px;
}

.template-chain-item {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fbfdff;
}

.template-chain-no {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-weight: 900;
}

.template-user-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.template-user-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    padding: 8px 10px;
    border: 1px solid #dbe4ef;
    border-radius: 999px;
    background: #f8fafc;
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
}

.template-user-chip button {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border: 0;
    border-radius: 999px;
    background: #e2e8f0;
    color: #64748b;
    cursor: pointer;
}

.reviewer-template-empty.compact {
    padding: 16px;
    text-align: left;
}

.modal-close-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

.reviewer-step-list {
    display: grid;
    gap: 10px;
    padding: 18px 20px;
}

.reviewer-step-row {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto;
    align-items: end;
    gap: 12px;
    padding: 14px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #f8fafc;
}

.reviewer-step-badge {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: #fff1e8;
    color: #c93616;
    font-size: 15px;
    font-weight: 900;
}

.reviewer-step-main {
    position: relative;
    min-width: 0;
}

.reviewer-step-main .lbl {
    margin-bottom: 7px;
    color: #64748b;
    font-size: 13px;
    font-weight: 800;
}

.reviewer-step-main .modal-input {
    width: 100%;
    min-height: 44px;
    border-radius: 8px;
}

.reviewer-search-input {
    padding: 0 13px;
    background: #fff;
}

.reviewer-choice-list {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 3;
    display: grid;
    gap: 6px;
    max-height: 220px;
    padding: 8px;
    overflow-y: auto;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.16);
}

.reviewer-choice-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 9px 11px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: var(--text);
    text-align: left;
    cursor: pointer;
}

.reviewer-choice-item:hover {
    border-color: #c93616;
    background: #fff7ed;
}

.reviewer-choice-item span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
    font-weight: 800;
}

.reviewer-choice-item small {
    flex: 0 1 auto;
    min-width: 120px;
    overflow: hidden;
    color: #64748b;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 700;
}

.reviewer-choice-empty {
    padding: 10px 12px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #94a3b8;
    font-size: 13px;
    font-weight: 700;
}

.reviewer-remove-btn {
    min-height: 38px;
}

.reviewer-remove-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.reviewer-modal-actions {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 16px 20px 20px;
    border-top: 1px solid #e2e8f0;
}

.reviewer-done-btn {
    min-width: 96px;
    justify-content: center;
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

    .evaluator-role-field {
        max-width: none;
    }

    .workflow-group-head,
    .reviewer-config-card,
    .reviewer-modal-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .workflow-template-row,
    .reviewer-template-grid {
        grid-template-columns: 1fr;
    }

    .workflow-title-row {
        grid-template-columns: 34px minmax(0, 1fr);
    }

    .workflow-number {
        width: 34px;
        height: 34px;
        font-size: 16px;
    }

    .reviewer-template-actions {
        justify-content: flex-start;
    }

    .reviewer-template-step-grid {
        grid-template-columns: 1fr;
    }

    .reviewer-template-modal-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .assessment-template-list-head,
    .assessment-template-builder-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .assessment-template-row,
    .template-builder-grid {
        grid-template-columns: 1fr;
    }

    .assessment-template-row-meta {
        grid-template-columns: 1fr;
    }

    .assessment-template-detail-head,
    .template-picker-row {
        grid-template-columns: 1fr;
    }

    .reviewer-step-row {
        grid-template-columns: 34px minmax(0, 1fr);
    }

    .reviewer-remove-btn {
        grid-column: 2;
        justify-self: start;
    }

    .reviewer-choice-item {
        align-items: flex-start;
        flex-direction: column;
    }

    .reviewer-choice-item small {
        min-width: 0;
        text-align: left;
    }
}
</style>
