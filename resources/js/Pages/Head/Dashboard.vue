<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import { NAV_CONFIG, PAGE_TITLES, ROLES_CONFIG } from '../../data';
import EmployeeAssess from '../Employee/EmployeeAssess.vue';
import EmployeeGap from '../Employee/EmployeeGap.vue';
import EmployeeIDP from '../Employee/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Employee/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Employee/EmployeeProgress.vue';
const selectedEmployee = ref(null);
const props = defineProps({ roleKey: { type: String, default: null } });

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const normalizeRoleKey = (role) => ({
    manager_dept: 'dept_head',
    manager: 'dean',
}[role] || role || 'dept_head');
const defaultPageForRole = (role) => normalizeRoleKey(role) === 'supervisor' ? 'dh-assess' : 'sup-gap';

const page = usePage();
const users = ref(clone(page.props.users || []));
const requestedPage = ref(typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null);
const initialRoleKey = normalizeRoleKey(props.roleKey || page.props.roleKey || page.props.currentUser?.r || page.props.auth?.user?.role_key || 'dept_head');
const rememberedHeadState = useRemember({
    showSidebar: true,
    activePage: requestedPage.value || defaultPageForRole(initialRoleKey),
}, 'HeadDashboardStable');

const showSidebar = computed({
    get: () => rememberedHeadState.value.showSidebar !== false,
    set: (value) => {
        rememberedHeadState.value.showSidebar = value;
    },
});
const activePage = computed({
    get: () => rememberedHeadState.value.activePage || defaultPageForRole(initialRoleKey),
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

const authRoleKey = computed(() => normalizeRoleKey(props.roleKey || page.props.roleKey || page.props.currentUser?.r || page.props.auth?.user?.role_key || initialRoleKey));
const currentRoleData = computed(() => ROLES_CONFIG[authRoleKey.value] || ROLES_CONFIG.dept_head || ROLES_CONFIG.supervisor);
const navSections = computed(() => {
    const sections = [
    {
        sec: 'ของฉัน (บุคลากร)',
        items: [
            { id: 'emp-assess', ic: '', lb: 'ประเมินตนเอง' },
            { id: 'emp-gap', ic: '', lb: 'ผล Competency Gap' },
            { id: 'emp-idp', ic: '', lb: 'IDP ของฉัน' },
            { id: 'emp-progress', ic: '', lb: 'อัปเดตความก้าวหน้า' },
            { id: 'emp-idp-detail', ic: '', lb: 'รายละเอียด IDP' },
        ],
    },
    {
        sec: 'จัดการทีม',
        items: [
            { id: 'sup-gap', ic: '', lb: 'ผลการประเมินของทีม' },
            { id: 'dh-idp', ic: '', lb: 'IDP & ติดตามทีม' },
        ],
    },
    ];

    if (!['dept_head', 'supervisor'].includes(authRoleKey.value)) return sections;

    return sections.map((section, index) => index === 1
        ? {
            ...section,
            items: [
                { id: 'dh-assess', ic: '', lb: 'อนุมัติผลการประเมิน' },
                ...section.items,
            ],
        }
        : section);
});
const pageTitle = computed(() => PAGE_TITLES[activePage.value] || activePage.value);
const authUserId = computed(() => page.props.auth?.user?.id ? String(page.props.auth.user.id) : '');
const authUserName = computed(() => page.props.auth?.user?.name || '');
const implementedPages = new Set([
    'emp-assess',
    'emp-gap',
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
    'sup-gap',
    'dh-idp',
    'dh-assess',
]);

const currentUser = computed(() =>
    page.props.currentUser
    || users.value.find((user) => String(user.db_id || '') === authUserId.value)
    || users.value.find((user) => user.sso === authUserId.value)
    || users.value.find((user) => user.n === authUserName.value)
    || users.value.find((user) => normalizeRoleKey(user.r) === authRoleKey.value)
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
const selfAssessmentBlockReasons = computed(() => {
    const user = currentUser.value || {};
    const reasons = Array.isArray(user.structureIssues) ? [...user.structureIssues] : [];
    const hasAssignedHeadOrSupervisor = [user.supervisor_id_1, user.supervisor_id_2]
        .some((id) => Number(id) > 0);

    if (!hasAssignedHeadOrSupervisor) {
        reasons.push('ยังไม่ได้กำหนดหัวหน้างานหรือผู้บังคับบัญชา');
    }

    if (user.structureStatus === 'invalid' && reasons.length === 0) {
        reasons.push('ข้อมูลโครงสร้างยังต้องตรวจสอบ');
    }

    return Array.from(new Set(reasons.filter(Boolean)));
});
const isSelfAssessmentBlocked = computed(() => selfAssessmentBlockReasons.value.length > 0);

const MOCK_TEAM = [
    {
        sso: 'mock001',
        t: 'รศ.ดร.',
        n: 'เมธา ศิริกุล',
        p: 'อาจารย์',
        d: 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
        r: 'employee',
        act: true,
        evalStatus: 'self_submitted',
        idpPhase: 'notsent',
        gaps: ['การบริการที่ดี', 'การวิเคราะห์ข้อมูล'],
    },
    {
        sso: 'mock002',
        t: 'ผศ.ดร.',
        n: 'อรพรรณ ศรีสวัสดิ์',
        p: 'อาจารย์',
        d: 'ภาควิชาวิศวกรรมไฟฟ้า',
        r: 'employee',
        act: true,
        evalStatus: 'unit_evaluated',
        idpPhase: 'pending',
        sentAt: 'ส่งแผน 20 พ.ค. 68',
        gaps: ['การใช้เทคโนโลยีดิจิทัล'],
        competencyResults: [
            { id: 'digital', group: 'FC', title: 'การใช้เทคโนโลยีดิจิทัล', code: 'FC-021', expected: 4, selfScore: 2, headScore: 2, gap: -2 },
        ],
        idpPlans: [
            {
                id: 'digital',
                goal: 'ใช้เครื่องมือดิจิทัลจัดทำ dashboard ติดตามแผนงานได้',
                activities: ['อบรม Data Dashboard', 'OJT สรุปข้อมูลผู้บริหาร'],
            },
        ],
    },
    {
        sso: 'mock003',
        t: 'นาย',
        n: 'สมชาย มีสุข',
        p: 'นักวิชาการศึกษา',
        d: 'หน่วยวิชาการและหลักสูตร',
        r: 'employee',
        act: true,
        evalStatus: 'self_submitted',
        idpPhase: 'rejected',
        sentAt: 'ส่งแผน 20 พ.ค. 68',
        gaps: ['การทำงานเป็นทีม', 'การใช้เทคโนโลยีดิจิทัล'],
        competencyResults: [
            { id: 'teamwork', group: 'CC', title: 'การทำงานเป็นทีม', code: 'CC-003', expected: 4, selfScore: 2, headScore: 2, gap: -2, feedback: 'พหกพหก' },
            { id: 'digital', group: 'FC', title: 'การใช้เทคโนโลยีดิจิทัล', code: 'FC-021', expected: 4, selfScore: 2, headScore: 2, gap: -2, feedback: 'พหกพหก' },
        ],
        idpPlans: [
            { id: 'teamwork', goal: 'ปรับรูปแบบการทำงานร่วมกับทีมโครงการให้ชัดเจนขึ้น', activities: ['Team Coaching', 'สรุปบทเรียนงานกลุ่ม'] },
            { id: 'digital', goal: 'ฝึกใช้เครื่องมือดิจิทัลเพื่อสรุปรายงานงานประจำ', activities: ['อบรม Data Dashboard', 'OJT รายงานดิจิทัล'] },
        ],
    },
    {
        sso: 'mock004',
        t: 'นางสาว',
        n: 'พิมพ์ใจ ทองดี',
        p: 'นักวิเคราะห์นโยบายและแผน',
        d: 'หน่วยแผนยุทธศาสตร์',
        r: 'employee',
        act: true,
        evalStatus: 'dept_evaluated',
        idpPhase: 'inprogress',
        gaps: ['การทำงานเป็นทีม'],
        competencyResults: [
            { id: 'teamwork', group: 'CC', title: 'การทำงานเป็นทีม', code: 'CC-003', expected: 3, selfScore: 2, headScore: 2, gap: -1 },
        ],
        idpPlans: [
            {
                id: 'teamwork',
                activities: [
                    {
                        title: 'Team Activity Program',
                        method: 'Social Learning',
                        status: 'ผ่านแล้ว',
                        events: [
                            { date: '5 พ.ค.', text: 'เริ่มกิจกรรมกลุ่ม - โครงการพัฒนาระบบงานร่วมกัน 4 คน', file: 'แผนงาน_Team_Activity_2566.pdf', by: 'นายบุญอยู่ มีสุข' },
                            { date: '10 มิ.ย.', text: 'ส่งรายงานความก้าวหน้าครึ่งทาง', file: 'รายงานความก้าวหน้า_มิ.ย.66.pdf', by: 'นายบุญอยู่ มีสุข' },
                            { date: '30 มิ.ย.', text: 'ประเมินผ่านเกณฑ์ - หัวหน้าประเมินระดับ 3/5', file: 'แบบประเมิน_TeamActivity_2566.pdf', by: 'รศ.ดร.วิไล โชติ' },
                        ],
                    },
                    {
                        title: 'อ่านและสรุปหนังสือ Teamwork 101',
                        method: 'Formal Learning',
                        status: 'ผ่านแล้ว',
                        events: [
                            { date: '1 ก.ค.', text: 'เริ่มอ่านและจดบันทึกสรุปบทที่ 1-5', file: 'สรุปหนังสือ_บทที่1-5.docx', by: 'นายบุญอยู่ มีสุข' },
                            { date: '31 ส.ค.', text: 'ส่งสรุปหนังสือครบ 12 บท - ผ่านการตรวจ', file: 'สรุปหนังสือ_Teamwork101_ฉบับสมบูรณ์.pdf', by: 'นายบุญอยู่ มีสุข' },
                        ],
                    },
                ],
            },
        ],
    },
    {
        sso: 'mock005',
        t: 'นาย',
        n: 'เก่งกาจ พัฒนา',
        p: 'เจ้าหน้าที่บริหารงานทั่วไป',
        d: 'งานบริหารและธุรการ',
        r: 'employee',
        act: true,
        evalStatus: 'dean_approved',
        idpPhase: 'done',
        gaps: ['การบริการที่ดี'],
    },
];

const personNames = (user) => [
    user?.n,
    `${user?.t || ''}${user?.n || ''}`,
].map((name) => String(name || '').trim()).filter(Boolean);
const isSamePersonName = (storedName, user) => personNames(user).includes(String(storedName || '').trim());
const isAssignedReviewer = (user, reviewer) => {
    const reviewerId = Number(reviewer?.db_id);
    const reviewerRole = normalizeRoleKey(reviewer?.r || authRoleKey.value);

    if (reviewerRole === 'dept_head') {
        return (
            (reviewerId > 0 && Number(user.supervisor_id_1) === reviewerId)
            || isSamePersonName(user.sup, reviewer)
        );
    }

    if (reviewerRole === 'supervisor') {
        return (
            (reviewerId > 0 && Number(user.supervisor_id_2) === reviewerId)
            || isSamePersonName(user.evaluator2, reviewer)
        );
    }

    if (reviewerRole === 'dean') {
        return (
            (reviewerId > 0 && Number(user.supervisor_id_3) === reviewerId)
            || isSamePersonName(user.evaluator3, reviewer)
        );
    }

    return (
        (reviewerId > 0 && [user.supervisor_id_1, user.supervisor_id_2, user.supervisor_id_3].some((id) => Number(id) === reviewerId))
        || isSamePersonName(user.sup, reviewer)
        || isSamePersonName(user.evaluator2, reviewer)
        || isSamePersonName(user.evaluator3, reviewer)
    );
};
const isReviewerRole = (role) => ['supervisor', 'manager_dept', 'dept_head', 'manager', 'dean'].includes(role);

const teamMembers = computed(() => {
    return users.value.filter((user) =>
        user.sso !== currentUser.value.sso
        && isAssignedReviewer(user, currentUser.value)
        && !isReviewerRole(user.r)
        && user.act !== false,
    );
});
// const teamMembers = computed(() => {
//     return users.value.filter((user) =>
//         user.sso !== currentUser.value.sso
//         && (user.sup === currentUser.value.n || user.evaluator2 === currentUser.value.n)
//         && !['manager_dept', 'dept_head', 'manager'].includes(user.r)
//         && user.act !== false,
//     );
// });

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
    phase: idpPhaseOverrides.value[user.sso] || normalizeIdpPhase(user),
})));

const assessCounts = computed(() => ({
    total: teamMembers.value.length,
    notSent: teamMembers.value.filter((user) => user.evalStatus === 'draft' || !user.evalStatus).length,
    pending: teamMembers.value.filter((user) => user.evalStatus === 'self_submitted').length,
    forwarded: teamMembers.value.filter((user) => ['unit_evaluated', 'dept_evaluated'].includes(user.evalStatus)).length,
    done: teamMembers.value.filter((user) => user.evalStatus === 'dean_approved').length,
}));
const supervisorApprovalStatus = (person) => {
    if (!person?.evalStatus || person.evalStatus === 'draft') return { label: 'ยังไม่ประเมิน', cls: 'bgr' };
    if (person.evalStatus === 'self_submitted') return { label: authRoleKey.value === 'dept_head' ? 'รอหัวหน้างานตรวจ' : 'รอหัวหน้างานอนุมัติ', cls: 'by' };
    if (person.evalStatus === 'unit_evaluated') return { label: authRoleKey.value === 'supervisor' ? 'รอผู้บังคับบัญชาตรวจ' : 'ส่งต่อผู้บังคับบัญชาแล้ว', cls: 'bt' };
    if (person.evalStatus === 'revision_required') return { label: 'ส่งกลับแก้ไข', cls: 'br' };
    if (person.evalStatus === 'dept_evaluated') return { label: 'ผู้บังคับบัญชาอนุมัติแล้ว', cls: 'bb' };
    if (person.evalStatus === 'dean_approved') return { label: 'ปิดรอบแล้ว', cls: 'bg' };

    return { label: person.evalStatus, cls: 'bgr' };
};
const supervisorApprovalRows = computed(() => teamMembers.value.map((person) => {
    const results = gapResultRows(person);
    const hasSubmittedAssessment = results.some((row) => row.hasAssessment) || (person.evalStatus && person.evalStatus !== 'draft');
    const hasReviewableCompetencies = results.length > 0;

    return {
        ...person,
        results,
        hasSubmittedAssessment,
        hasReviewableCompetencies,
        statusMeta: supervisorApprovalStatus(person),
        submittedAt: results.find((row) => row.hasAssessment)?.updatedAt || person.updatedAt || '-',
    };
}));
const approvalExpectedStatus = computed(() => authRoleKey.value === 'dept_head' ? 'self_submitted' : 'unit_evaluated');
const approvalNextStatus = computed(() => authRoleKey.value === 'dept_head' ? 'unit_evaluated' : 'dept_evaluated');
const approvalRoleLabel = computed(() => authRoleKey.value === 'dept_head' ? 'หัวหน้างาน' : 'ผู้บังคับบัญชา');
const approvalForwardLabel = computed(() => authRoleKey.value === 'dept_head' ? 'ส่งต่อผู้บังคับบัญชาแล้ว' : 'อนุมัติแล้ว');
const supervisorPendingRows = computed(() => supervisorApprovalRows.value.filter((person) => person.evalStatus === approvalExpectedStatus.value));
const supervisorForwardedRows = computed(() => supervisorApprovalRows.value.filter((person) => person.evalStatus === approvalNextStatus.value));
const supervisorApprovedRows = computed(() => supervisorApprovalRows.value.filter((person) =>
    (authRoleKey.value === 'dept_head' ? ['dept_evaluated', 'dean_approved'] : ['dean_approved']).includes(person.evalStatus),
));

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
            const comp = assessmentComps.find((item) => item.title === name);
            items[name] = items[name] || { type: comp?.group || '-', name, count: 0 };
            items[name].count += 1;
            return items;
        }, {}),
));

const activeIdpTab = ref('notsent');
const idpStatusMeta = {
    notsent: { label: 'ยังไม่ส่งแผน', cls: 'bgr', title: 'ยังไม่ส่งแผน IDP' },
    pending: { label: 'รอการตรวจ/ยืนยัน', cls: 'by', title: 'แผน IDP รอการตรวจ/ยืนยัน' },
    rejected: { label: 'แผนไม่ผ่าน', cls: 'br', title: 'แผน IDP ไม่ผ่าน' },
    inprogress: { label: 'ระหว่างดำเนินการ', cls: 'bb', title: 'แผน IDP ระหว่างดำเนินการ' },
    done: { label: 'เสร็จสิ้น', cls: 'bg', title: 'แผน IDP เสร็จสิ้น' },
};
const idpTabs = computed(() => [
    { id: 'notsent', label: idpStatusMeta.notsent.label, count: idpRows.value.filter((row) => row.phase === 'notsent').length, cls: '' },
    { id: 'pending', label: idpStatusMeta.pending.label, count: idpRows.value.filter((row) => row.phase === 'pending').length, cls: 'yc' },
    { id: 'rejected', label: idpStatusMeta.rejected.label, count: idpRows.value.filter((row) => row.phase === 'rejected').length, cls: 'rc' },
    { id: 'inprogress', label: idpStatusMeta.inprogress.label, count: idpRows.value.filter((row) => row.phase === 'inprogress').length, cls: 'bc' },
    { id: 'done', label: idpStatusMeta.done.label, count: idpRows.value.filter((row) => row.phase === 'done').length, cls: 'gcc' },
]);
const visibleIdpRows = computed(() => idpRows.value.filter((row) => row.phase === activeIdpTab.value));

const scoreLabels = ['ต่ำมาก', 'ต่ำ', 'พอใช้', 'ดี', 'ดีมาก'];
const selectedGroup = ref('all');

const compGroups = computed(() => {
    const groups = [...new Set(assessmentComps.map((c) => c.group))];
    return [{ value: 'all', label: 'ทุกประเภท' }, ...groups.map((g) => ({ value: g, label: g }))];
});

const filteredComps = computed(() =>
    selectedGroup.value === 'all'
        ? assessmentComps
        : assessmentComps.filter((c) => c.group === selectedGroup.value),
);
const assessmentDrafts = ref({});
const assessmentSavedAt = ref('');
const draftTimer = ref(null);
const selectedGapEmployee = ref(null);
const selectedIdpEmployee = ref(null);
const selectedSupervisorApprovalSso = ref(null);
const openedSupervisorCompetencyId = ref(null);
const approvalDecision = ref(null);
const isSubmittingApprovalDecision = ref(false);
const allowIdpReview = false;
const idpPhaseOverrides = ref({});
const idpReviewDecisions = ref({});
const idpReviewFeedbacks = ref({});

const assessmentComps = [
    {
        id: 'service',
        group: 'CC',
        title: 'การบริการที่ดี',
        selfScore: 2,
        evidenceUrl: 'https://kku.ac.th/service-mind-2568',
        evidenceDesc: 'อบรมหลักสูตรการบริการเชิงรุกและแนบสรุปการนำไปใช้ในงานประจำ',
        behaviors: {
            1: ['รับเรื่องจากผู้รับบริการได้เมื่อมีคำแนะนำ', 'ตอบคำถามพื้นฐานตามข้อมูลที่มี', 'รักษามารยาทในการให้บริการ'],
            2: ['ตอบสนองความต้องการผู้รับบริการได้ทันท่วงที', 'ให้ข้อมูลที่ถูกต้องและครบถ้วนแก่ผู้รับบริการ', 'แสดงความเป็นมิตร ยิ้มแย้ม ให้บริการด้วยใจ'],
            3: ['ติดตามเรื่องจนผู้รับบริการได้รับคำตอบ', 'ปรับวิธีสื่อสารให้เหมาะกับผู้รับบริการ', 'ประสานงานข้ามหน่วยเพื่อแก้ปัญหาได้'],
            4: ['คาดการณ์ปัญหาการบริการล่วงหน้า', 'ออกแบบวิธีบริการที่ลดความผิดพลาดซ้ำ', 'เป็นที่ปรึกษาให้ทีมในกรณีบริการซับซ้อน'],
            5: ['ยกระดับมาตรฐานการบริการของหน่วยงาน', 'ใช้เสียงสะท้อนผู้รับบริการพัฒนาระบบงาน', 'สร้างวัฒนธรรมบริการที่ดีให้ทีมอย่างต่อเนื่อง'],
        },
    },
    {
        id: 'data',
        group: 'FC',
        title: 'การวิเคราะห์ข้อมูล',
        selfScore: 3,
        evidenceUrl: 'https://kku.ac.th/data-dashboard-2568',
        evidenceDesc: 'ตัวอย่าง dashboard สรุปข้อมูลการให้บริการรายเดือน',
        behaviors: {
            1: ['รวบรวมข้อมูลจากแหล่งที่กำหนดได้', 'ตรวจสอบข้อมูลเบื้องต้นตามแบบฟอร์ม', 'สรุปข้อเท็จจริงง่าย ๆ จากข้อมูลที่ได้รับ'],
            2: ['จัดหมวดหมู่ข้อมูลและตรวจความครบถ้วนได้', 'เปรียบเทียบข้อมูลพื้นฐานเพื่อหาความต่าง', 'จัดทำตารางหรือกราฟพื้นฐานประกอบรายงาน'],
            3: ['เลือกวิธีวิเคราะห์ให้เหมาะกับโจทย์งาน', 'อธิบายแนวโน้มและประเด็นสำคัญจากข้อมูล', 'เชื่อมผลวิเคราะห์กับข้อเสนอแนะในการทำงาน'],
            4: ['วิเคราะห์ข้อมูลหลายมิติและระบุปัจจัยที่เกี่ยวข้อง', 'ตรวจสอบความน่าเชื่อถือของผลวิเคราะห์', 'นำเสนอ insight ให้ผู้เกี่ยวข้องตัดสินใจได้'],
            5: ['ออกแบบกรอบวิเคราะห์ข้อมูลให้หน่วยงานใช้ร่วมกัน', 'คาดการณ์ผลกระทบจากข้อมูลเชิงลึก', 'พัฒนาระบบติดตามข้อมูลเพื่อยกระดับการตัดสินใจ'],
        },
    },
];

const selectedAssessment = computed(() => selectedEmployee.value || teamMembers.value.find((user) => user.evalStatus === 'self_submitted') || teamMembers.value[0] || null);
const assessmentName = computed(() => selectedAssessment.value ? `${selectedAssessment.value.t || ''}${selectedAssessment.value.n}` : '');
const selectedSupervisorApproval = computed(() =>
    supervisorApprovalRows.value.find((person) => person.sso === selectedSupervisorApprovalSso.value) || null,
);
const supervisorApprovalName = computed(() => selectedSupervisorApproval.value ? `${selectedSupervisorApproval.value.t || ''}${selectedSupervisorApproval.value.n}` : '');
const canDecideSupervisorApproval = computed(() =>
    Boolean(selectedSupervisorApproval.value?.hasSubmittedAssessment)
    && selectedSupervisorApproval.value?.evalStatus === approvalExpectedStatus.value,
);
const approvalDecisionTitle = computed(() => approvalDecision.value === 'approve'
    ? `ยืนยันผลการประเมินของ ${supervisorApprovalName.value}`
    : 'ยืนยันการส่งกลับผลการประเมิน');
const approvalDecisionMessage = computed(() => approvalDecision.value === 'approve'
    ? `ต้องการยืนยันผลการประเมินของ ${supervisorApprovalName.value} และส่งต่อไปยังขั้นตอนถัดไปหรือไม่`
    : `ต้องการส่งผลการประเมินของ ${supervisorApprovalName.value} กลับไปให้บุคลากรแก้ไขหรือไม่`);
const assessmentDraftKey = computed(() => selectedAssessment.value ? `${currentUser.value.sso || currentUser.value.n}:${selectedAssessment.value.sso || selectedAssessment.value.n}` : '');
const activeDraft = computed(() => assessmentDrafts.value[assessmentDraftKey.value] || { scores: {}, feedback: {}, submitted: false });

const getHeadScore = (comp) => activeDraft.value.scores?.[comp.id] || comp.selfScore;
const getHeadFeedback = (comp) => activeDraft.value.feedback?.[comp.id] || '';

const gapScoreFor = (person, comp, key, fallback) => {
    const values = person?.competencyScores || person?.scores || person?.assessmentScores || {};
    return Number(values?.[comp.id]?.[key] ?? values?.[`${comp.id}_${key}`] ?? comp[key] ?? fallback);
};

const gapResultRows = (person) => {
    if (!person) return [];
    const assignedRows = Array.isArray(person.assignedCompetencies) ? person.assignedCompetencies.map((row) => ({
        id: row.id || row.competency_id || row.name || row.title,
        title: row.title || row.name || row.n || row.competency_name || '-',
        group: row.group || row.type || row.t || '-',
        code: row.code || row.cd || '',
        expected: Number(row.expected ?? row.expectedScore ?? row.expectedLevel ?? row.target ?? 3),
        selfScore: null,
        headScore: null,
        gap: null,
        feedback: '',
        note: '',
        levels: row.levels || [],
        checkedIndicatorKeys: [],
        checkedIndicatorCount: 0,
        updatedAt: '',
        failed: false,
        hasAssessment: false,
    })) : [];
    const rawRows = person.competencyGaps || person.competencyResults || person.assessmentResults || person.evaluationResults;
    if (Array.isArray(rawRows) && rawRows.length) {
        const assessedRows = rawRows.map((row) => {
            const expected = Number(row.expected ?? row.expectedScore ?? row.target ?? 3);
            const selfScore = Number(row.selfScore ?? row.self ?? row.actual ?? row.actualLevel ?? 0);
            const headScore = Number(row.headScore ?? row.supervisorScore ?? row.evaluatorScore ?? row.score ?? row.actual ?? row.actualLevel ?? selfScore);
            const gap = Number(row.gap ?? headScore - expected);
            return {
                id: row.id || row.competency_id || row.name || row.title,
                title: row.title || row.name || row.n || row.competency_name || '-',
                group: row.group || row.type || row.t || '-',
                code: row.code || row.cd || '',
                expected,
                selfScore,
                headScore,
                gap,
                feedback: row.feedback || row.note || '',
                note: row.note || row.feedback || '',
                levels: row.levels || [],
                checkedIndicatorKeys: row.checkedIndicatorKeys || [],
                checkedIndicatorCount: Number(row.checkedIndicatorCount || 0),
                updatedAt: row.updatedAt || row.updated_at || '',
                failed: row.failed ?? row.requiresIdp ?? gap < 0,
                hasAssessment: true,
            };
        });

        const assessedKeys = new Set(assessedRows.map((row) => row.id || row.code || row.title));
        const missingAssignedRows = assignedRows.filter((row) => !assessedKeys.has(row.id || row.code || row.title));

        return [...assessedRows, ...missingAssignedRows];
    }

    if (assignedRows.length) return assignedRows;

    const explicitGaps = normalizeGaps(person);
    return assessmentComps.map((comp, index) => {
        const expected = gapScoreFor(person, comp, 'expected', 3);
        const selfScore = gapScoreFor(person, comp, 'selfScore', index === 1 ? 1 : comp.selfScore);
        const headScore = gapScoreFor(person, comp, 'headScore', index === 1 ? 2 : comp.selfScore);
        const isExplicitGap = explicitGaps.includes(comp.title);
        const failed = isExplicitGap || headScore < expected;
        return {
            id: comp.id,
            title: comp.title,
            group: comp.group,
            code: comp.code || '',
            expected,
            selfScore,
            headScore,
            gap: headScore - expected,
            failed,
        };
    }).filter((row) => row.failed);
};

const selectedGapPerson = computed(() => selectedGapEmployee.value);
const selectedGapRows = computed(() => gapResultRows(selectedGapPerson.value));
const selectedGapFailedCount = computed(() => selectedGapRows.value.filter((row) => row.failed).length);
const teamHeatmapCompetencies = computed(() => {
    const byCode = new Map();

    teamMembers.value
        .flatMap((person) => gapResultRows(person))
        .forEach((row) => {
            const code = row.code || row.title;
            if (!code || byCode.has(code)) return;
            byCode.set(code, { code, title: row.title });
        });

    return Array.from(byCode.values()).sort((left, right) => left.code.localeCompare(right.code, 'th'));
});
const formatTeamGap = (value) => {
    const numberValue = Number(value);
    if (!Number.isFinite(numberValue)) return '-';
    if (numberValue === 0) return '0';

    const formatted = Math.abs(numberValue)
        .toFixed(2)
        .replace(/\.00$/, '')
        .replace(/0$/, '')
        .replace(/\.$/, '');

    return `${numberValue > 0 ? '+' : '-'}${formatted}`;
};
const teamHeatmapRows = computed(() => teamMembers.value.map((person) => {
    const resultRows = gapResultRows(person);
    const scores = teamHeatmapCompetencies.value.map((comp) => {
        const matching = resultRows.find((row) => row.code === comp.code || row.title === comp.title);
        return matching ? Number(matching.gap ?? 0) : 0;
    });
    const missingCount = scores.filter((score) => score < 0).length;
    const assessed = resultRows.length > 0;

    return {
        ...person,
        scores,
        assessed,
        missingCount,
        summary: missingCount ? `ต้องพัฒนา ${missingCount} สมรรถนะ` : 'บุคลากรศักยภาพสูง',
    };
}));
const teamAssessedRows = computed(() => teamHeatmapRows.value.filter((row) => row.assessed));
const teamTalentRows = computed(() => teamAssessedRows.value.filter((row) => row.missingCount === 0));
const teamMetricStats = computed(() => teamHeatmapCompetencies.value.map((comp, index) => {
    const values = teamAssessedRows.value.map((row) => Number(row.scores[index] ?? 0));
    const average = values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : 0;
    return { ...comp, average };
}));
const teamStrongest = computed(() => [...teamMetricStats.value].sort((a, b) => b.average - a.average)[0]);
const teamWeakest = computed(() => [...teamMetricStats.value].sort((a, b) => a.average - b.average)[0]);
const idpOverviewRows = computed(() => idpRows.value.map((person, index) => {
    const gapCount = gapResultRows(person).filter((row) => row.failed).length || normalizeGaps(person).length;
    const phaseMeta = idpStatusFor(person.phase);
    return {
        ...person,
        missingCount: gapCount,
        statusLabel: person.phase === 'notsent' ? 'ร่าง' : phaseMeta.label,
        statusClass: person.phase === 'done' ? 'bg' : person.phase === 'rejected' ? 'br' : person.phase === 'inprogress' ? 'bb' : 'bgr',
        updatedAt: person.updatedAt || person.sentDate || `2026-06-${String(10 + index).padStart(2, '0')}`,
    };
}));
const idpRequiredCount = computed(() => idpOverviewRows.value.filter((row) => row.missingCount > 0).length);

const openGapDetail = (person) => {
    if (person.pending) return;
    selectedGapEmployee.value = person;
};

const closeGapDetail = () => {
    selectedGapEmployee.value = null;
};

const moveGapDetail = (step) => {
    const assessed = gapRows.value.filter((person) => !person.pending);
    const currentIndex = assessed.findIndex((person) => person.sso === selectedGapPerson.value?.sso);
    if (currentIndex < 0 || assessed.length === 0) return;
    selectedGapEmployee.value = assessed[(currentIndex + step + assessed.length) % assessed.length];
};

const selectedIdpPerson = computed(() =>
    idpRows.value.find((person) => person.sso === selectedIdpEmployee.value?.sso) || selectedIdpEmployee.value,
);
const selectedIdpGapRows = computed(() => gapResultRows(selectedIdpPerson.value));
const selectedIdpName = computed(() => selectedIdpPerson.value ? `${selectedIdpPerson.value.t || ''}${selectedIdpPerson.value.n}` : '');
const idpStatusFor = (phase) => idpStatusMeta[phase] || idpStatusMeta.notsent;
const selectedIdpStatus = computed(() => idpStatusFor(selectedIdpPerson.value?.phase));
const idpPlanFor = (row) => selectedIdpPerson.value?.idpPlans?.find((plan) => plan.id === row.id) || {};
const idpReviewKey = (row) => `${selectedIdpPerson.value?.sso || ''}:${row.id}`;
const idpDecisionFor = (row) => idpReviewDecisions.value[idpReviewKey(row)] || '';
const idpFeedbackFor = (row) => idpReviewFeedbacks.value[idpReviewKey(row)] || row.feedback || '';
const setIdpFeedback = (row, value) => {
    idpReviewFeedbacks.value = {
        ...idpReviewFeedbacks.value,
        [idpReviewKey(row)]: value,
    };
};
const setIdpDecision = (row, decision) => {
    if (decision === 'rejected' && !idpFeedbackFor(row).trim()) {
        alert('กรุณาบันทึกข้อเสนอแนะ IDP ก่อนระบุว่าแผนไม่ผ่าน');
        return;
    }
    idpReviewDecisions.value = {
        ...idpReviewDecisions.value,
        [idpReviewKey(row)]: decision,
    };
};
const completeIdpReview = () => {
    if (!selectedIdpPerson.value) return;
    const decisions = selectedIdpGapRows.value.map((row) => idpDecisionFor(row));
    if (decisions.length === 0 || decisions.some((decision) => !decision)) {
        alert('กรุณาตรวจทุกแผนก่อนดำเนินการต่อ');
        return;
    }
    const nextPhase = decisions.includes('rejected') ? 'rejected' : 'inprogress';
    idpPhaseOverrides.value = {
        ...idpPhaseOverrides.value,
        [selectedIdpPerson.value.sso]: nextPhase,
    };
    activeIdpTab.value = nextPhase;
};

const openIdpDetail = (person) => {
    selectedIdpEmployee.value = person;
};

const closeIdpDetail = () => {
    selectedIdpEmployee.value = null;
};

const persistAssessmentDraft = (nextDraft, label = 'บันทึกร่างอัตโนมัติแล้ว') => {
    if (!assessmentDraftKey.value) return;
    assessmentDrafts.value = {
        ...assessmentDrafts.value,
        [assessmentDraftKey.value]: nextDraft,
    };
    assessmentSavedAt.value = `${label} ${new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })}`;
};

const scheduleAssessmentAutosave = (nextDraft) => {
    assessmentSavedAt.value = 'กำลังบันทึกร่าง...';
    if (assessmentDraftKey.value) {
        assessmentDrafts.value = {
            ...assessmentDrafts.value,
            [assessmentDraftKey.value]: nextDraft,
        };
    }
    if (draftTimer.value) clearTimeout(draftTimer.value);
    draftTimer.value = setTimeout(() => persistAssessmentDraft(nextDraft), 500);
};

const updateAssessmentScore = (comp, score) => {
    scheduleAssessmentAutosave({
        ...activeDraft.value,
        scores: { ...(activeDraft.value.scores || {}), [comp.id]: score },
        feedback: { ...(activeDraft.value.feedback || {}) },
    });
};

const updateAssessmentFeedback = (comp, value) => {
    scheduleAssessmentAutosave({
        ...activeDraft.value,
        scores: { ...(activeDraft.value.scores || {}) },
        feedback: { ...(activeDraft.value.feedback || {}), [comp.id]: value },
    });
};

const selectAssessmentEmployee = (person) => {
    selectedEmployee.value = person;
    assessmentSavedAt.value = '';
};

const openSupervisorApprovalModal = (person) => {
    if (!person.hasReviewableCompetencies) return;
    selectedSupervisorApprovalSso.value = person.sso;
    openedSupervisorCompetencyId.value = null;
};

const closeSupervisorApprovalModal = () => {
    selectedSupervisorApprovalSso.value = null;
    openedSupervisorCompetencyId.value = null;
    approvalDecision.value = null;
};

const supervisorCompetencyKey = (row) => `${row.id || row.code || row.title}`;
const isSupervisorCompetencyOpen = (row) => openedSupervisorCompetencyId.value === supervisorCompetencyKey(row);
const toggleSupervisorCompetency = (row) => {
    if (!row.hasAssessment) return;
    const key = supervisorCompetencyKey(row);
    openedSupervisorCompetencyId.value = openedSupervisorCompetencyId.value === key ? null : key;
};
const checkedIndicatorKey = (row, level, index) => `${row.id}:${level.id || level.lvl}:${index}`;
const checkedIndicatorSet = (row) => new Set(row.checkedIndicatorKeys || []);
const isIndicatorChecked = (row, level, index) => checkedIndicatorSet(row).has(checkedIndicatorKey(row, level, index));
const totalIndicatorCount = (row) => (row.levels || [])
    .filter((level) => level.lvl <= row.expected)
    .reduce((total, level) => total + (level.indicators?.length || 0), 0);
const checkedIndicatorsForLevel = (row, level) => (level.indicators || [])
    .map((indicator, index) => ({ indicator, index }))
    .filter(({ index }) => isIndicatorChecked(row, level, index));
const checkedLevelsForRow = (row) => (row.levels || [])
    .map((level) => ({
        ...level,
        checkedIndicators: checkedIndicatorsForLevel(row, level),
    }))
    .filter((level) => level.checkedIndicators.length > 0);

const submitAssessmentToManager = () => {
    if (!selectedAssessment.value) return;
    persistAssessmentDraft({ ...activeDraft.value, submitted: true }, 'ส่งต่อผู้บังคับบัญชาแล้ว');
    users.value = users.value.map((user) => user.sso === selectedAssessment.value.sso ? { ...user, evalStatus: 'unit_evaluated' } : user);
};
const requestApprovalDecision = (decision) => {
    if (!selectedSupervisorApproval.value || !canDecideSupervisorApproval.value) return;
    approvalDecision.value = decision;
};
const closeApprovalDecision = () => {
    if (isSubmittingApprovalDecision.value) return;
    approvalDecision.value = null;
};
const submitApprovalDecision = () => {
    if (!selectedSupervisorApproval.value || !approvalDecision.value || !canDecideSupervisorApproval.value) return;

    const decision = approvalDecision.value;
    isSubmittingApprovalDecision.value = true;
    router.post(route(decision === 'approve' ? 'assessments.approve' : 'assessments.reject'), {
        user_id: selectedSupervisorApproval.value.db_id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            users.value = users.value.map((user) =>
                user.sso === selectedSupervisorApproval.value.sso
                    ? { ...user, evalStatus: decision === 'approve' ? approvalNextStatus.value : 'revision_required' }
                    : user,
            );
            approvalDecision.value = null;
            closeSupervisorApprovalModal();
        },
        onFinish: () => {
            isSubmittingApprovalDecision.value = false;
        },
    });
};

watchEffect(() => {
    if (requestedPage.value && implementedPages.has(requestedPage.value)) {
        activePage.value = requestedPage.value;
        requestedPage.value = null;
    }

    if (!implementedPages.has(activePage.value)) {
        activePage.value = defaultPageForRole(authRoleKey.value);
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
            <SidebarBrand />

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
                    :blocked="isSelfAssessmentBlocked"
                    :block-reasons="selfAssessmentBlockReasons"
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
                    <template v-if="!selectedIdpPerson">
                        <div class="team-page-head mb20">
                            <div>
                                <div class="sec-t">ติดตาม IDP ทีม</div>
                                <div class="sec-s">ตารางรวม Direct Reports ที่มี Competency Gap พร้อมสถานะปัจจุบันและ mirror view แบบอ่านอย่างเดียว</div>
                            </div>
                            <span class="b bb">{{ idpRequiredCount }} คนต้องทำ IDP</span>
                        </div>

                        <div class="card team-table-card">
                            <div class="team-card-head">
                                <div>
                                    <div class="ct">Team Overview Table</div>
                                    <div class="cs">คลิกดูรายละเอียดเพื่อดูแผน IDP แบบ read-only และฝาก coaching comment</div>
                                </div>
                            </div>
                            <div class="team-table-wrap">
                                <table class="team-table">
                                    <thead>
                                        <tr>
                                            <th>บุคลากร</th>
                                            <th>ตำแหน่ง</th>
                                            <th>สมรรถนะที่ต้องทำ IDP</th>
                                            <th>Current Status</th>
                                            <th>อัปเดตล่าสุด</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in idpOverviewRows" :key="row.sso">
                                            <td>
                                                <div class="person-cell">
                                                    <strong>{{ `${row.t || ''}${row.n}` }}</strong>
                                                    <small>{{ row.d || row.p }}</small>
                                                </div>
                                            </td>
                                            <td>{{ row.p }}</td>
                                            <td>
                                                <span class="b br">{{ row.missingCount || 0 }} สมรรถนะ</span>
                                            </td>
                                            <td>
                                                <span class="b" :class="row.statusClass">{{ row.statusLabel }}</span>
                                            </td>
                                            <td>{{ row.updatedAt }}</td>
                                            <td class="tr">
                                                <button class="btn btn-s btn-sm" type="button" @click="openIdpDetail(row)">ดูรายละเอียด</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="idpOverviewRows.length === 0" class="empty-card">
                                ยังไม่มีข้อมูลบุคลากรในหน่วยงาน
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="idp-detail-nav mb20">
                            <div class="flex ic g10">
                                <button class="btn btn-s" type="button" @click="closeIdpDetail">กลับหน้าติดตามทีม</button>
                                <span class="muted">/</span>
                                <span class="fw8 fs15">{{ selectedIdpName }}</span>
                            </div>
                            <span class="b" :class="selectedIdpStatus.cls">{{ selectedIdpStatus.label }}</span>
                        </div>

                        <div class="card idp-profile-card mb18">
                            <div class="flex ic g12">
                                <div class="av idp-detail-avatar">{{ selectedIdpPerson.n[0] }}</div>
                                <div>
                                    <div class="fw8 fs16">{{ selectedIdpName }}</div>
                                    <div class="muted fs12">{{ selectedIdpPerson.p }} · {{ selectedIdpPerson.p }}</div>
                                </div>
                            </div>
                            <div v-if="selectedIdpPerson.sentAt" class="muted fs12">{{ selectedIdpPerson.sentAt }}</div>
                        </div>

                        <div v-if="allowIdpReview && selectedIdpPerson.phase === 'pending'" class="idp-review-space">
                            <div v-for="row in selectedIdpGapRows" :key="row.id" class="card idp-review-card">
                                <div class="idp-review-head">
                                    <div class="flex ic g8">
                                        <span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span>
                                        <span class="fw8 fs16">{{ row.title }}</span>
                                        <span class="muted fs12">คาดหวัง {{ row.expected }} · ได้ {{ row.headScore }} · <span class="rc fw8">Gap {{ row.gap }}</span></span>
                                    </div>
                                </div>
                                <div class="idp-review-body">
                                    <div class="fw8 fs13 mb8">เป้าหมายการพัฒนา</div>
                                    <div class="idp-goal-box">{{ idpPlanFor(row).goal || 'ยังไม่ได้ระบุเป้าหมายการพัฒนา' }}</div>
                                    <div class="flex g8 mt14" style="flex-wrap: wrap">
                                        <span v-for="activity in idpPlanFor(row).activities || []" :key="activity" class="b bb">{{ activity }}</span>
                                    </div>
                                    <textarea
                                        class="ta idp-feedback-input mt14"
                                        :value="idpFeedbackFor(row)"
                                        placeholder="ข้อเสนอแนะ / เหตุผลเมื่อแผนไม่ผ่าน"
                                        @input="setIdpFeedback(row, $event.target.value)"
                                    />
                                    <div class="flex g8 mt12">
                                        <button
                                            class="btn btn-g btn-sm"
                                            :class="{ selected: idpDecisionFor(row) === 'approved' }"
                                            type="button"
                                            @click="setIdpDecision(row, 'approved')"
                                        >
                                            ผ่านแผนนี้
                                        </button>
                                        <button
                                            class="btn btn-r btn-sm"
                                            :class="{ selected: idpDecisionFor(row) === 'rejected' }"
                                            type="button"
                                            @click="setIdpDecision(row, 'rejected')"
                                        >
                                            แผนไม่ผ่าน
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="idp-review-actions">
                                <button class="btn btn-p" type="button" @click="completeIdpReview">ดำเนินการต่อ</button>
                            </div>
                        </div>

                        <div v-else-if="selectedIdpPerson.phase === 'inprogress'" class="card idp-gap-detail-card">
                            <div v-for="row in selectedIdpGapRows" :key="row.id" class="idp-progress-section">
                                <div class="idp-progress-head">
                                    <div class="flex ic g8">
                                        <span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span>
                                        <span class="fw8 fs16">{{ row.title }}</span>
                                        <span class="muted fs12">{{ row.code }} · Gap {{ row.gap }}</span>
                                    </div>
                                    <span class="b bg">ระหว่างดำเนินการ</span>
                                </div>
                                <div
                                    v-for="activity in idpPlanFor(row).activities || []"
                                    :key="activity.title || activity"
                                    class="idp-activity-card"
                                >
                                    <div class="idp-activity-head">
                                        <div>
                                            <span class="fw8 fs14">{{ activity.title || activity }}</span>
                                            <span class="muted fs12 ml8">{{ activity.method }}</span>
                                        </div>
                                        <span class="b bg">{{ activity.status || 'รอดำเนินการ' }}</span>
                                    </div>
                                    <div v-for="event in activity.events || []" :key="`${activity.title}-${event.date}-${event.file}`" class="idp-event-row">
                                        <div class="muted fs12">{{ event.date }}</div>
                                        <div>
                                            <div class="fs13" :class="{ 'gcc fw8': event.text.includes('ผ่าน') }">{{ event.text }}</div>
                                            <span class="b bgr mt4">{{ event.file }}</span>
                                        </div>
                                        <div class="muted fs12 tr">by {{ event.by }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="card idp-gap-detail-card">
                            <div class="idp-gap-detail-head">
                                <span class="fw8 fs16">{{ selectedIdpStatus.title }}</span>
                                <span class="muted fs12">
                                    {{ selectedIdpPerson.phase === 'rejected' ? 'บุคลากรต้องกลับไปแก้แผนที่ไม่ผ่านก่อนส่งใหม่' : 'แสดงเฉพาะ Competency Gap ที่ทำไว้แล้ว' }}
                                </span>
                            </div>
                            <div class="idp-gap-list">
                                <div v-for="row in selectedIdpGapRows" :key="row.id" class="idp-gap-row">
                                    <span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span>
                                    <div class="row-main">
                                        <div class="fw8 fs14">{{ row.title }}</div>
                                        <div class="muted fs12">ระดับคาดหวัง {{ row.expected }} · ระดับที่ได้ {{ row.headScore }}</div>
                                        <div v-if="selectedIdpPerson.phase === 'rejected'" class="rc fs12 mt4">
                                            เหตุผล: {{ idpFeedbackFor(row) || 'ยังไม่มีข้อเสนอแนะ' }}
                                        </div>
                                    </div>
                                    <div class="rc fw8 fs14">Gap {{ row.gap }}</div>
                                </div>
                                <div v-if="selectedIdpGapRows.length === 0" class="empty-card">
                                    ไม่มี Competency Gap
                                </div>
                            </div>
                        </div>
                    </template>
                </template>

                <template v-else-if="activePage === 'sup-gap'">
                    <div class="team-page-head mb20">
                        <div>
                            <div class="sec-t">Competency Gap ทีม</div>
                            <div class="sec-s">Dashboard, Heatmap และการจัดกลุ่มพนักงานตามศักยภาพของ Direct Reports</div>
                        </div>
                        <div class="flex g8" style="flex-wrap: wrap">
                            <button class="btn btn-s btn-sm" type="button">Export PDF</button>
                            <button class="btn btn-s btn-sm" type="button">Export Excel</button>
                        </div>
                    </div>

                    <div v-if="teamAssessedRows.length === 0" class="card empty-card">
                        ยังไม่ได้รับผลการประเมินจากผู้ใต้บังคับบัญชา
                    </div>

                    <template v-else>
                        <template v-if="!selectedGapPerson">
                            <div class="g3 team-metrics mb20">
                                <div class="sc">
                                    <div class="sl">ประเมินเสร็จแล้ว</div>
                                    <div class="sv bc">{{ teamAssessedRows.length }}/{{ teamHeatmapRows.length }}</div>
                                    <div class="ss muted">เทียบกับลูกน้องทั้งหมด</div>
                                </div>
                                <div class="sc">
                                    <div class="sl">จุดแข็งของทีม</div>
                                    <div class="sv bc">{{ teamStrongest?.code || '-' }}</div>
                                    <div class="ss muted">{{ teamTalentRows.length }} คนผ่านเกณฑ์</div>
                                </div>
                                <div class="sc">
                                    <div class="sl">จุดอ่อนของทีม</div>
                                    <div class="sv rc">{{ teamWeakest?.code || '-' }}</div>
                                    <div class="ss muted">{{ idpRequiredCount }} คน Gap ติดลบ</div>
                                </div>
                            </div>

                            <div class="card team-table-card">
                                <div class="team-card-head">
                                    <div>
                                        <div class="ct">Team Gap Heatmap</div>
                                        <div class="cs">Gap = Actual Score - Expected Score, แดงคือต่ำกว่าความคาดหวัง เขียวคือผ่านเกณฑ์</div>
                                    </div>
                                </div>
                                <div class="team-table-wrap">
                                    <table class="team-table heatmap-table">
                                        <thead>
                                            <tr>
                                                <th>บุคลากร</th>
                                                <th v-for="comp in teamHeatmapCompetencies" :key="comp.code">{{ comp.code }}</th>
                                                <th>สรุป</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="row in teamHeatmapRows"
                                                :key="row.sso"
                                                :class="{ disabled: !row.assessed }"
                                                @click="row.assessed && openGapDetail(row)"
                                            >
                                                <td>
                                                    <div class="person-cell">
                                                        <strong>{{ `${row.t || ''}${row.n}` }}</strong>
                                                        <small>{{ row.p }}</small>
                                                    </div>
                                                </td>
                                                <td v-for="(score, scoreIndex) in row.scores" :key="`${row.sso}-${scoreIndex}`">
                                                    <span
                                                        class="gap-chip"
                                                        :class="{ ok: Number(score) >= 0, bad: Number(score) < 0 }"
                                                    >
                                                        {{ formatTeamGap(score) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="b" :class="row.missingCount ? 'br' : 'bb'">{{ row.summary }}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="card gap-detail-profile mb20">
                                <div class="flex ic g12">
                                    <div class="av gap-detail-avatar">{{ selectedGapPerson.n[0] }}</div>
                                    <div>
                                        <div class="fw8 fs16">{{ `${selectedGapPerson.t || ''}${selectedGapPerson.n}` }}</div>
                                        <div class="muted fs12">{{ selectedGapPerson.p }}</div>
                                        <div class="b br mt6">ไม่ผ่าน {{ selectedGapFailedCount }} สมรรถนะ</div>
                                    </div>
                                </div>
                                <div class="flex ic g8">
                                    <button class="btn btn-s btn-sm" type="button" @click="moveGapDetail(-1)">← ก่อนหน้า</button>
                                    <button class="btn btn-s btn-sm" type="button" @click="moveGapDetail(1)">ถัดไป →</button>
                                    <button class="btn btn-s btn-sm" type="button" @click="closeGapDetail">กลับรายการ</button>
                                </div>
                            </div>

                            <div class="card gap-result-card mb20">
                                <div class="ch"><div class="ct">ผลรายสมรรถนะ</div></div>
                                <div class="gap-result-table">
                                    <div class="gap-result-row gap-result-head">
                                        <div>สมรรถนะ</div>
                                        <div>ประเภท</div>
                                        <div>คาดหวัง</div>
                                        <div>ประเมินตนเอง</div>
                                        <div>หัวหน้างานประเมิน</div>
                                        <div>Gap</div>
                                        <div>สถานะ</div>
                                    </div>
                                    <div v-for="row in selectedGapRows" :key="row.id" class="gap-result-row gap-result-body">
                                        <div class="fw8">{{ row.title }}</div>
                                        <div><span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span></div>
                                        <div><span class="score-pill navy">{{ row.expected }}</span></div>
                                        <div><span class="score-pill blue">{{ row.selfScore }}</span></div>
                                        <div><span class="score-pill evaluator">{{ row.headScore }}</span></div>
                                        <div>
                                            <span class="gap-chip" :class="{ ok: Number(row.gap) >= 0, bad: Number(row.gap) < 0 }">
                                                {{ formatTeamGap(row.gap) }}
                                            </span>
                                        </div>
                                        <div><span class="b" :class="row.failed ? 'br' : 'bb'">{{ row.failed ? 'ไม่ผ่าน' : 'ผ่าน' }}</span></div>
                                    </div>
                                    <div v-if="selectedGapRows.length === 0" class="empty-card">
                                        ผ่านทุกสมรรถนะ
                                    </div>
                                </div>
                            </div>

                            <div class="card gap-suggestions">
                                <div class="ch"><div class="ct">ข้อเสนอแนะ</div></div>
                                <div class="suggestion-block head">
                                    <div class="bc fw8 fs13">• หัวหน้างาน</div>
                                    <div class="suggestion-note">ยังไม่มีข้อเสนอแนะ</div>
                                </div>
                                <div class="suggestion-block dept">
                                    <div class="tc fw8 fs13">• หัวหน้าฝ่าย</div>
                                    <div class="suggestion-note">ยังไม่มีข้อเสนอแนะ</div>
                                </div>
                            </div>
                        </template>
                    </template>
                </template>

                <template v-else-if="activePage === 'dh-assess'">
                    <template v-if="['dept_head', 'supervisor'].includes(authRoleKey)">
                        <div class="team-page-head mb20">
                            <div>
                                <div class="sec-t">ตรวจประเมินลูกน้อง</div>
                                <div class="sec-s">{{ approvalRoleLabel }}ตรวจผลแบบ read-only ก่อนอนุมัติและส่งต่อไปยังขั้นตอนถัดไป</div>
                            </div>
                            <div class="flex g8" style="flex-wrap: wrap">
                                <button class="btn btn-s btn-sm" type="button">Export PDF</button>
                                <button class="btn btn-s btn-sm" type="button">Export Excel</button>
                            </div>
                        </div>

                        <div class="g3 supervisor-approval-summary mb16">
                            <div class="sc">
                                <div class="sl">รอตรวจ</div>
                                <div class="sv yc">{{ supervisorPendingRows.length }}</div>
                                <div class="ss muted">Pending {{ approvalRoleLabel }}</div>
                            </div>
                            <div class="sc">
                                <div class="sl">{{ approvalForwardLabel }}</div>
                                <div class="sv bc">{{ supervisorForwardedRows.length }}</div>
                                <div class="ss muted">ดำเนินการในขั้นนี้แล้ว</div>
                            </div>
                            <div class="sc">
                                <div class="sl">อนุมัติแล้ว</div>
                                <div class="sv gcc">{{ supervisorApprovedRows.length }}</div>
                                <div class="ss muted">ปิดรอบประเมินแล้ว</div>
                            </div>
                        </div>

                        <div class="card supervisor-approval-card mb16">
                            <div class="team-card-head">
                                <div>
                                    <div class="ct">รายการรอดำเนินการ</div>
                                    <div class="cs">เฉพาะบุคลากรที่เชื่อมกับ {{ approvalRoleLabel }} และอยู่ในสถานะรอตรวจ</div>
                                </div>
                                <span class="b by">{{ supervisorPendingRows.length }} รายการ</span>
                            </div>
                            <div class="team-table-wrap approval-table-wrap">
                                <table class="team-table approval-table">
                                    <thead>
                                        <tr>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>ตำแหน่ง</th>
                                            <th>วันที่ส่งประเมิน</th>
                                            <th>สถานะ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="person in supervisorApprovalRows"
                                            :key="person.sso"
                                            :class="{ selected: selectedSupervisorApproval?.sso === person.sso, disabled: !person.hasReviewableCompetencies }"
                                            @click="openSupervisorApprovalModal(person)"
                                        >
                                            <td>
                                                <div class="person-cell">
                                                    <strong>{{ `${person.t || ''}${person.n}` }}</strong>
                                                    <small>{{ person.d || '-' }}</small>
                                                </div>
                                            </td>
                                            <td>{{ person.p || '-' }}</td>
                                            <td>{{ person.hasSubmittedAssessment ? person.submittedAt : '-' }}</td>
                                            <td><span class="b" :class="person.statusMeta.cls">{{ person.statusMeta.label }}</span></td>
                                        </tr>
                                        <tr v-if="supervisorApprovalRows.length === 0">
                                            <td colspan="4" class="muted ac py20">ไม่มีบุคลากรในความดูแล</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="selectedSupervisorApproval?.hasReviewableCompetencies" class="approval-modal-backdrop" @click.self="closeSupervisorApprovalModal">
                            <div class="approval-modal">
                                <div class="approval-modal-head">
                                    <div>
                                        <div class="sec-t">ตรวจสอบผลประเมิน · {{ supervisorApprovalName }}</div>
                                        <div class="sec-s">{{ selectedSupervisorApproval.p }} · Expected Level แสดงตามแต่ละสมรรถนะ · Checklist ถูกล็อก read-only</div>
                                    </div>
                                    <button class="btn btn-s btn-sm" type="button" @click="closeSupervisorApprovalModal">ปิด</button>
                                </div>

                                <div class="approval-modal-body">
                                    <div class="approval-score-grid">
                                        <div v-for="row in selectedSupervisorApproval.results" :key="`summary-${row.id}`" class="approval-score-card" :class="{ disabled: !row.hasAssessment }">
                                            <div class="muted fs12 fw8">{{ row.code || '-' }} · Expected {{ row.expected ?? '-' }}</div>
                                            <div v-if="row.hasAssessment" class="approval-score-gap" :class="{ bad: Number(row.gap) < 0, ok: Number(row.gap) >= 0 }">
                                                {{ formatTeamGap(row.gap) }}
                                            </div>
                                            <div v-else class="approval-score-pending">ยังไม่ประเมิน</div>
                                            <div class="muted fs12">Actual {{ row.hasAssessment ? row.headScore : '-' }}</div>
                                        </div>
                                    </div>

                                    <div v-for="row in selectedSupervisorApproval.results" :key="`detail-${row.id}`" class="approval-competency-card" :class="{ disabled: !row.hasAssessment }">
                                        <button
                                            class="approval-competency-head"
                                            :class="{ open: isSupervisorCompetencyOpen(row), disabled: !row.hasAssessment }"
                                            type="button"
                                            :disabled="!row.hasAssessment"
                                            :aria-expanded="isSupervisorCompetencyOpen(row)"
                                            @click="toggleSupervisorCompetency(row)"
                                        >
                                            <div class="flex ic g10">
                                                <span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span>
                                                <span>{{ row.code }} · {{ row.title }}</span>
                                                <span class="approval-accordion-icon">{{ row.hasAssessment ? (isSupervisorCompetencyOpen(row) ? 'ซ่อน' : 'ดูรายละเอียด') : 'ยังไม่ประเมิน' }}</span>
                                            </div>
                                            <div class="flex ic g8">
                                                <span class="b bgr">Expected Level {{ row.expected ?? '-' }}</span>
                                                <span class="b by">{{ row.checkedIndicatorCount || 0 }}/{{ totalIndicatorCount(row) }}</span>
                                            </div>
                                        </button>

                                        <div v-if="isSupervisorCompetencyOpen(row)" class="approval-accordion-body">
                                            <div v-for="level in checkedLevelsForRow(row)" :key="level.id || level.lvl" class="approval-level-card">
                                                <div class="approval-level-head">
                                                    <div>
                                                        <div class="fw8">ระดับที่ {{ level.lvl }}</div>
                                                        <div class="muted fs12">เลือกแล้ว {{ level.checkedIndicators.length }}/{{ level.indicators?.length || 0 }} พฤติกรรม</div>
                                                    </div>
                                                    <span
                                                        class="b"
                                                        :class="level.checkedIndicators.length === (level.indicators?.length || 0) && (level.indicators?.length || 0) > 0 ? 'bt' : 'bb'"
                                                    >
                                                        <!-- {{ level.checkedIndicators.length === (level.indicators?.length || 0) && (level.indicators?.length || 0) > 0 ? 'ครบระดับ' : 'กำลังประเมิน' }} -->
                                                    </span>
                                                </div>

                                                <div class="approval-checklist">
                                                    <label
                                                        v-for="{ indicator, index } in level.checkedIndicators"
                                                        :key="`${row.id}-${level.id || level.lvl}-${index}`"
                                                        class="approval-check-row"
                                                    >
                                                        <input checked disabled type="checkbox" />
                                                        <span>
                                                            <strong>ข้อ {{ level.lvl }}.{{ index + 1 }}</strong>
                                                            {{ indicator }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="approval-comment-box">
                                                <div class="fs12 fw8">Comment จากผู้ประเมินตนเอง</div>
                                                <div>{{ row.note || 'ไม่มี Note' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="selectedSupervisorApproval.results.length === 0" class="empty-card compact">
                                        ไม่มีผลคะแนนในรายการนี้
                                    </div>
                                </div>

                                <div class="approval-modal-actions">
                                    <button class="btn btn-r" type="button" :disabled="!canDecideSupervisorApproval" @click="requestApprovalDecision('reject')">
                                        ไม่อนุมัติ
                                    </button>
                                    <button class="btn btn-t" type="button" :disabled="!canDecideSupervisorApproval" @click="requestApprovalDecision('approve')">
                                        อนุมัติ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="approvalDecision" class="approval-decision-backdrop" @click.self="closeApprovalDecision">
                            <div class="approval-decision-modal">
                                <div class="approval-decision-title">{{ approvalDecisionTitle }}</div>
                                <div class="approval-decision-message">{{ approvalDecisionMessage }}</div>
                                <div class="approval-decision-actions">
                                    <button class="btn btn-s" type="button" :disabled="isSubmittingApprovalDecision" @click="closeApprovalDecision">
                                        ยกเลิก
                                    </button>
                                    <button
                                        class="btn"
                                        :class="approvalDecision === 'approve' ? 'approval-confirm-button' : 'approval-reject-button'"
                                        type="button"
                                        :disabled="isSubmittingApprovalDecision"
                                        @click="submitApprovalDecision"
                                    >
                                        {{ isSubmittingApprovalDecision ? 'กำลังดำเนินการ...' : 'ยืนยัน' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                    <div class="g5 assess-summary mb20">
                        <div class="sc navy-top">
                            <div class="sl">ทั้งหมด</div>
                            <div class="sv">{{ assessCounts.total }}</div>
                            <div class="ss muted">คนในความดูแล</div>
                        </div>
                        <div class="sc red-top">
                            <div class="sl">ยังไม่ส่ง</div>
                            <div class="sv rc">{{ assessCounts.notSent }}</div>
                            <div class="ss muted">รอลูกน้องประเมินตนเอง</div>
                        </div>
                        <div class="sc yellow-top">
                            <div class="sl">รอคุณประเมิน</div>
                            <div class="sv yc">{{ assessCounts.pending }}</div>
                            <div class="ss muted">คน</div>
                        </div>
                        <div class="sc green-top">
                            <div class="sl">หัวหน้าฝ่าย/คณบดี</div>
                            <div class="sv gcc">{{ assessCounts.forwarded }}</div>
                            <div class="ss muted">อยู่ระหว่างพิจารณา</div>
                        </div>
                        <div class="sc green-top">
                            <div class="sl">เสร็จสมบูรณ์</div>
                            <div class="sv gcc">{{ assessCounts.done }}</div>
                            <div class="ss muted">คน</div>
                        </div>
                    </div>
                    <div class="flex ic jb mb20">
                        <div>
                            <div class="sec-t">ประเมินลูกน้อง</div>
                            <div class="sec-s">เลือกบุคลากรเพื่อดูผลประเมินตนเอง หลักฐาน และให้คะแนนพร้อม feedback</div>
                        </div>
                        <span class="b by">รอคุณประเมิน {{ assessCounts.pending }} คน</span>
                    </div>

                    <div class="assessment-workspace">
                        <div class="card assess-list-card">
                            <div class="ch"><div class="ct">ผู้ส่งแบบประเมินแล้ว</div></div>
                            <button
                                v-for="person in teamMembers"
                                :key="person.sso"
                                class="assess-person"
                                :class="{ selected: selectedAssessment?.sso === person.sso }"
                                type="button"
                                @click="selectAssessmentEmployee(person)"
                            >
                                <div class="av row-avatar">{{ person.n[0] }}</div>
                                <div class="row-main">
                                    <div class="fw8 fs13">{{ `${person.t || ''}${person.n}` }}</div>
                                    <div class="muted fs11">{{ person.p }} · {{ person.d }}</div>
                                </div>
                                <span class="b" :class="person.evalStatus === 'unit_evaluated' ? 'bt' : person.evalStatus === 'self_submitted' ? 'by' : 'br'">
                                    {{ person.evalStatus === 'unit_evaluated' ? 'ส่งต่อแล้ว' : person.evalStatus === 'self_submitted' ? 'รอประเมิน' : 'ยังไม่ส่ง' }}
                                </span>
                            </button>
                        </div>

                        <div v-if="selectedAssessment" class="assessment-detail">
                            <div class="flex ic jb mb12">
                                <div>
                                    <div class="fw8 fs16">การประเมิน: {{ assessmentName }}</div>
                                    <div class="muted fs12">{{ selectedAssessment.p }} · {{ selectedAssessment.d }}</div>
                                </div>
                                <span class="b" :class="activeDraft.submitted ? 'bt' : 'bgr'">
                                    {{ activeDraft.submitted ? 'ส่งต่อผู้บังคับบัญชาแล้ว' : (assessmentSavedAt || 'ยังไม่มีการแก้ไขผลการประเมิน') }}
                                </span>
                            </div>

                            <div class="flex ic jb mb14">
                                <div class="assessment-group-title" style="margin: 0">
                                    {{ selectedGroup === 'all' ? 'ทุกประเภทสมรรถนะ' : `ประเภท ${selectedGroup}` }}
                                    — แสดง {{ filteredComps.length }} รายการ
                                </div>
                                <select class="filter-select" v-model="selectedGroup">
                                    <option v-for="g in compGroups" :key="g.value" :value="g.value">
                                        {{ g.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-for="(comp, index) in filteredComps" :key="comp.id" class="card assessment-card">
                                <div class="assessment-card-head">
                                    <div class="flex ic g10">
                                        <span class="tag-cc">{{ comp.group }}</span>
                                        <span class="fw8 fs14">{{ comp.title }}</span>
                                    </div>
                                    <div class="tc fw8 fs12">{{ getHeadScore(comp) }}/5 ✓</div>
                                </div>

                                <div class="assessment-columns">
                                    <div class="assessment-side self">
                                        <div class="muted fw7 fs11 mb10">พฤติกรรมบ่งชี้ (ใช้ประกอบการตัดสิน)</div>
                                        <ul class="behavior-list">
                                            <li v-for="behavior in comp.behaviors[comp.selfScore]" :key="behavior">{{ behavior }}</li>
                                        </ul>
                                        <div class="hint-box">แสดงพฤติกรรมบ่งชี้ตามคะแนนประเมินตนเองระดับ {{ comp.selfScore }}: {{ scoreLabels[comp.selfScore - 1] }}</div>

                                        <div class="muted fw7 fs11 mb8">คะแนนความสามารถของบุคลากร (Self-Score)</div>
                                        <div class="score-grid">
                                            <div v-for="score in [1, 2, 3, 4, 5]" :key="score" class="score-tile" :class="{ selected: comp.selfScore === score }">
                                                <span>{{ score }}</span>
                                                <small>{{ scoreLabels[score - 1] }}</small>
                                            </div>
                                        </div>

                                        <div class="muted fw7 fs11 mb8">หลักฐานประกอบ (Evidence)</div>
                                        <div class="evidence-grid">
                                            <div class="evidence-file">
                                                <div class="file-icon">▤</div>
                                                <div class="bc fw7 fs12">ยังไม่แนบไฟล์</div>
                                            </div>
                                            <div class="fs11">
                                                <div class="mb8"><span class="fw8">URL:</span> <span class="bc">{{ comp.evidenceUrl }}</span></div>
                                                <div><span class="fw8">คำอธิบาย:</span> <span class="muted">{{ comp.evidenceDesc }}</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="assessment-side reviewer">
                                        <div class="bc fw8 fs12 mb10">1. หัวหน้างาน (คุณ) *</div>
                                        <div class="muted fw7 fs11 mb6">พฤติกรรมบ่งชี้ (ใช้ประกอบการตัดสิน)</div>
                                        <ul class="behavior-list">
                                            <li v-for="behavior in comp.behaviors[getHeadScore(comp)]" :key="behavior">{{ behavior }}</li>
                                        </ul>
                                        <div class="hint-box">แสดงพฤติกรรมบ่งชี้ตามคะแนนที่หัวหน้างานเลือก ระดับ {{ getHeadScore(comp) }}: {{ scoreLabels[getHeadScore(comp) - 1] }}</div>

                                        <div class="muted fw7 fs11 mb8">คะแนนความสามารถของบุคลากรโดยหัวหน้างาน</div>
                                        <div class="score-grid">
                                            <button
                                                v-for="score in [1, 2, 3, 4, 5]"
                                                :key="score"
                                                class="score-tile score-button"
                                                :class="{ selected: getHeadScore(comp) === score }"
                                                type="button"
                                                @click="updateAssessmentScore(comp, score)"
                                            >
                                                <span>{{ score }}</span>
                                                <small>{{ scoreLabels[score - 1] }}</small>
                                            </button>
                                        </div>

                                        <textarea
                                            class="ta feedback-box"
                                            :value="getHeadFeedback(comp)"
                                            placeholder="ใส่คำเสนอแนะ (ถ้ามี)..."
                                            @input="updateAssessmentFeedback(comp, $event.target.value)"
                                        />

                                        <div v-if="index === filteredComps.length - 1" class="approval-flow">
                                            <div class="flow-step"><div class="av s24 flow-dot">2</div><div><div class="fw7 fs12">ผู้บังคับบัญชา</div><div class="muted fs11">รอดำเนินการ...</div></div></div>
                                            <div class="flow-step"><div class="av s24 flow-dot">3</div><div><div class="fw7 fs12">คณบดี</div><div class="muted fs11">รอยืนยัน</div></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex g8 mt16">
                                <button class="btn btn-s" type="button" style="flex: 1" disabled>
                                    {{ assessmentSavedAt || 'ระบบจะบันทึกร่างอัตโนมัติ' }}
                                </button>
                                <button class="btn btn-t" type="button" style="flex: 2" :disabled="activeDraft.submitted" @click="submitAssessmentToManager">
                                    {{ activeDraft.submitted ? 'ส่งผลการประเมินแล้ว' : 'ส่งผลการประเมินให้ผู้บังคับบัญชา' }}
                                </button>
                            </div>
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

.team-page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.team-metrics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.team-table-card {
    overflow: hidden;
}

.team-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
}

.team-table-wrap {
    overflow-x: auto;
    padding: 20px 22px;
}

.team-table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
}

.team-table th,
.team-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    font-size: 13px;
    text-align: left;
    vertical-align: middle;
}

.team-table th {
    background: #f8fafc;
    color: var(--text3);
    font-size: 12px;
    font-weight: 900;
}

.team-table tbody tr:last-child td {
    border-bottom: 0;
}

.heatmap-table tbody tr {
    cursor: pointer;
}

.heatmap-table tbody tr:hover {
    background: var(--blue-lt);
}

.heatmap-table tbody tr.disabled {
    cursor: default;
    opacity: 0.56;
}

.approval-table tbody tr {
    cursor: pointer;
}

.approval-table tbody tr:hover {
    background: var(--blue-lt);
}

.approval-table tbody tr.selected {
    background: #eff6ff;
}

.approval-table tbody tr.disabled {
    cursor: default;
    opacity: 0.62;
}

.approval-result-table {
    padding: 18px 22px;
}

.approval-result-row {
    display: grid;
    grid-template-columns: minmax(220px, 1.4fr) 90px 90px 90px 90px minmax(220px, 1.2fr);
    gap: 12px;
    align-items: center;
    min-height: 58px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.approval-result-head {
    min-height: 44px;
    background: #f8fafc;
    color: var(--text3);
    font-size: 12px;
    font-weight: 900;
}

.approval-note {
    color: var(--text2);
    line-height: 1.45;
    white-space: pre-wrap;
}

.approval-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 22px 20px;
    border-top: 1px solid var(--border);
}

.approval-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.34);
    overflow-y: auto;
}

.approval-modal {
    width: min(1080px, 100%);
    max-height: calc(100vh - 36px);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-direction: column;
}

.approval-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.approval-modal-body {
    padding: 20px;
    overflow-y: auto;
}

.approval-score-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}

.approval-score-card {
    min-height: 100px;
    padding: 16px 18px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #fff;
}

.approval-score-card.disabled {
    background: #f8fafc;
    color: var(--text3);
    opacity: 0.72;
}

.approval-score-gap {
    margin: 4px 0;
    color: var(--green);
    font-size: 25px;
    font-weight: 900;
    line-height: 1.15;
}

.approval-score-pending {
    margin: 8px 0 6px;
    color: var(--text3);
    font-size: 13px;
    font-weight: 800;
}

.approval-score-gap.bad {
    color: var(--red);
}

.approval-score-gap.ok {
    color: var(--green);
}

.approval-competency-card {
    overflow: hidden;
    margin-top: 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #fff;
}

.approval-competency-card.disabled {
    border-color: #e2e8f0;
    background: #f8fafc;
}

.approval-competency-head {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: #f8fafc;
    color: var(--text);
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    flex-wrap: wrap;
}

.approval-competency-head:hover,
.approval-competency-head.open {
    background: #eff6ff;
}

.approval-competency-head.disabled,
.approval-competency-head:disabled,
.approval-competency-head.disabled:hover {
    background: #f8fafc;
    color: var(--text3);
    cursor: not-allowed;
}

.approval-competency-head.disabled .approval-accordion-icon,
.approval-competency-head:disabled .approval-accordion-icon {
    background: #e2e8f0;
    color: var(--text3);
}

.approval-accordion-icon {
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: var(--blue);
    font-size: 11px;
    font-weight: 900;
}

.approval-accordion-body {
    display: grid;
    gap: 14px;
    padding: 16px;
}

.approval-level-card {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
}

.approval-level-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    background: #ecfeff;
}

.approval-checklist {
    display: grid;
    gap: 7px;
    padding: 14px 16px;
}

.approval-check-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    color: var(--text);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.45;
}

.approval-check-row input {
    width: 14px;
    height: 14px;
    margin-top: 2px;
    flex: 0 0 auto;
}

.approval-check-row.unchecked {
    color: var(--text3);
    font-weight: 600;
}

.approval-comment-box {
    margin: 0 16px 16px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #f8fafc;
}

.approval-accordion-body .approval-comment-box {
    margin: 0;
}

.approval-check-row strong {
    display: block;
    font-weight: 500;
    margin-bottom: 3px;
}

.approval-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px 18px;
    border-top: 1px solid var(--border);
    background: #fff;
    flex-shrink: 0;
}

.approval-decision-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: grid;
    place-items: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.46);
}

.approval-decision-modal {
    width: min(460px, 100%);
    padding: 22px 24px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.approval-decision-title {
    color: var(--text);
    font-size: 17px;
    font-weight: 900;
}

.approval-decision-message {
    margin-top: 10px;
    color: var(--text2);
    font-size: 13px;
    line-height: 1.65;
}

.approval-decision-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.approval-decision-actions .btn {
    min-width: 96px;
    opacity: 1;
    visibility: visible;
}

.approval-confirm-button {
    border: 1px solid #0faaa7;
    background: #0faaa7;
    color: #fff;
}

.approval-confirm-button:hover:not(:disabled) {
    border-color: #0f766e;
    background: #0f766e;
    color: #fff;
}

.approval-reject-button {
    border: 1px solid #dc2626;
    background: #dc2626;
    color: #fff;
}

.approval-reject-button:hover:not(:disabled) {
    border-color: #991b1b;
    background: #991b1b;
    color: #fff;
}

.approval-decision-actions .btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.person-cell {
    display: grid;
    gap: 4px;
}

.person-cell strong {
    color: var(--text);
    font-size: 13px;
    font-weight: 900;
}

.person-cell small {
    color: var(--text3);
    font-size: 12px;
}

.gap-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 22px;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 0 7px;
    font-size: 11px;
    font-weight: 900;
}

.gap-chip::before {
    margin-right: 3px;
    font-size: 9px;
    line-height: 1;
}

.gap-chip.bad {
    border-color: #b45309;
    background: #fff7ed;
    color: #9a3412;
}

/* .gap-chip.bad::before {
    content: '!';
} */

.gap-chip.ok {
    border-color: var(--blue);
    background: var(--blue-lt);
    color: var(--blue);
}

/* .gap-chip.ok::before {
    content: '=';
} */

.idp-row-action {
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--navy);
    font-weight: 800;
}

.idp-row-action:hover {
    border-color: var(--navy);
    background: var(--navy);
    color: #fff;
}

.idp-row-action.primary {
    border-color: var(--blue);
    background: #fff;
    color: var(--blue);
}

.idp-row-action.primary:hover {
    background: var(--blue);
    color: #fff;
}

.assess-summary {
    grid-template-columns: repeat(5, minmax(0, 1fr));
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

.assessment-workspace {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.idp-detail-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.idp-profile-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 20px 26px;
    border-left: 5px solid var(--red);
    flex-wrap: wrap;
}

.idp-detail-avatar {
    width: 56px;
    height: 56px;
    background: var(--navy);
    font-size: 16px;
}

.idp-gap-detail-card {
    overflow: hidden;
}

.idp-gap-detail-head {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.idp-gap-list {
    padding: 18px 22px;
}

.idp-gap-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px dashed var(--border);
}

.idp-gap-row:last-child {
    border-bottom: 0;
}

.idp-review-space {
    display: grid;
    gap: 14px;
}

.idp-review-card {
    overflow: hidden;
}

.idp-review-head,
.idp-progress-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.idp-review-body {
    padding: 20px 22px;
}

.idp-goal-box {
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg);
    color: var(--text3);
    font-size: 13px;
}

.idp-feedback-input {
    min-height: 74px;
}

.idp-review-actions {
    display: flex;
    justify-content: flex-end;
    padding: 4px 0 0;
}

.idp-review-body .btn.selected {
    box-shadow: 0 0 0 2px var(--blue-md);
}

.idp-progress-section {
    border-bottom: 1px solid var(--border);
}

.idp-progress-section:last-child {
    border-bottom: 0;
}

.idp-activity-card {
    margin: 14px 16px;
    padding: 16px 18px;
    border-left: 4px solid var(--green);
    background: #fbfdff;
}

.idp-activity-card + .idp-activity-card {
    margin-top: 0;
}

.idp-activity-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

.idp-event-row {
    display: grid;
    grid-template-columns: 70px minmax(0, 1fr) 190px;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.idp-event-row:last-child {
    border-bottom: 0;
}

.assess-list-card {
    position: sticky;
    top: 10px;
    overflow: hidden;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
}

.assess-person {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: #fff;
    text-align: left;
    font-family: inherit;
    cursor: pointer;
}

.assess-person:hover,
.assess-person.selected {
    background: var(--blue-lt);
}

.assess-person.selected {
    box-shadow: inset 4px 0 0 var(--blue);
}

.assessment-detail {
    min-width: 0;
}

.assessment-group-title {
    color: var(--blue);
    font-size: 13px;
    font-weight: 800;
    margin: 10px 0 14px;
}

.assessment-card {
    overflow: hidden;
    margin-bottom: 18px;
}

.assessment-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    background: #fff;
}

.assessment-columns {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
}

.assessment-side {
    padding: 22px 20px;
}

.assessment-side.self {
    border-right: 1px solid var(--border);
}

.behavior-list {
    margin: 0 0 18px 18px;
    padding: 0;
    font-size: 12px;
    line-height: 1.7;
}

.hint-box {
    padding: 10px 12px;
    margin-bottom: 18px;
    background: var(--blue-lt);
    border-radius: 8px;
    color: var(--blue);
    font-size: 11px;
}

.score-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 7px;
    margin-bottom: 18px;
}

.score-tile {
    min-height: 54px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: #fff;
    color: var(--text3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: inherit;
}

.score-tile.selected {
    border-color: var(--teal);
    background: var(--teal-lt);
    color: var(--teal);
}

.score-tile span {
    font-weight: 800;
    font-size: 15px;
}

.score-tile small {
    font-size: 10px;
}

.score-button {
    cursor: pointer;
}

.evidence-grid {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) 1fr;
    gap: 14px;
    align-items: center;
}

.evidence-file {
    height: 80px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.file-icon {
    font-size: 18px;
    opacity: 0.55;
}

.feedback-box {
    min-height: 80px;
    font-size: 13px;
    border-radius: 10px;
}

.approval-flow {
    border-top: 1px solid var(--border);
    margin-top: 24px;
    padding-top: 16px;
}

.flow-step {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.flow-dot {
    background: var(--blue-lt);
    color: var(--blue);
    font-size: 10px;
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

.gap-person-button {
    width: 100%;
    border: 0;
    background: #fff;
    text-align: left;
    font-family: inherit;
    cursor: pointer;
}

.gap-person-button:hover:not(:disabled) {
    background: var(--blue-lt);
}

.gap-person-button:disabled {
    cursor: not-allowed;
}

.gap-detail-profile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 34px 32px;
    min-height: 146px;
    flex-wrap: wrap;
}

.gap-detail-avatar {
    width: 74px;
    height: 74px;
    background: var(--navy);
    font-size: 18px;
    flex-shrink: 0;
}

.gap-result-card {
    overflow: hidden;
}

.gap-result-table {
    width: 100%;
    overflow-x: auto;
}

.gap-result-row {
    display: grid;
    grid-template-columns: minmax(220px, 1.6fr) minmax(90px, 0.7fr) repeat(5, minmax(120px, 1fr));
    align-items: center;
    min-width: 980px;
    border-top: 1px solid var(--border);
}

.gap-result-row > div {
    padding: 18px 16px;
}

.gap-result-head {
    background: #f8fafc;
    color: var(--text3);
    font-size: 12px;
    font-weight: 900;
}

.gap-result-body:hover {
    background: var(--blue-lt);
}

.score-pill {
    width: 52px;
    height: 52px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 900;
}

.score-pill.navy {
    background: var(--navy);
    color: #fff;
}

.score-pill.blue {
    background: var(--blue-lt);
    color: var(--blue);
}

.score-pill.evaluator {
    border: 1px solid #fed7aa;
    background: #fff7ed;
    color: #c2410c;
}

.tag-fc {
    background: var(--green-bg);
    color: var(--green);
}

.suggestion-block {
    padding: 22px;
    border-top: 1px solid var(--border);
}

.suggestion-note {
    margin-top: 8px;
    padding: 14px 16px;
    border-radius: 8px;
    background: var(--blue-lt);
    font-size: 13px;
}

.suggestion-block.dept .suggestion-note {
    background: var(--teal-lt);
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

.g5 {
    display: grid;
    gap: 14px;
}

.filter-select {
    padding: 7px 12px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    background: #fff;
    color: var(--text1);
    cursor: pointer;
    min-width: 140px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--blue);
}

.mb14 {
    margin-bottom: 14px;
}

.btn-t {
    background: var(--teal, #0d9488) !important;
    color: #fff !important;
    border: none;
}

.btn-t:hover:not(:disabled) {
    background: var(--navy, #1e3a5f) !important;
    color: #fff !important;
}

.btn-t:disabled {
    background: var(--border, #e2e8f0) !important;
    color: var(--text3, #94a3b8) !important;
    cursor: not-allowed;
    opacity: 1 !important;
}
.navy-top { border-top: 3px solid var(--navy); }
.blue-top { border-top: 3px solid var(--blue); }
.red-top { border-top: 3px solid var(--red); }
.yellow-top { border-top: 3px solid var(--yellow); }
.green-top { border-top: 3px solid var(--green); }
</style>
