<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { NAV_CONFIG, PAGE_TITLES, ROLES_CONFIG } from '../../data';

const props = defineProps({
    profileUser: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        default: '',
    },
});

const isEditing = ref(false);
const showSidebar = ref(true);
const normalizeRoleKey = (role) => role === 'dept_head' ? 'manager_dept' : role;

const roleKey = computed(() => normalizeRoleKey(props.profileUser?.r || 'employee'));
const roleData = computed(() => ROLES_CONFIG[roleKey.value] || ROLES_CONFIG.employee);
const navSections = computed(() => {
    if (roleKey.value === 'manager_dept') return NAV_CONFIG.supervisor || [];

    return NAV_CONFIG[roleKey.value] || NAV_CONFIG.employee || [];
});
const fullName = computed(() =>
    `${form.title || ''}${form.first_name_th || ''} ${form.last_name_th || ''}`.trim()
    || props.profileUser?.n
    || 'โปรไฟล์บุคลากร',
);
const roleLine = computed(() =>
    [form.position, form.level, form.department].filter(Boolean).join(' · ') || 'ยังไม่ได้ระบุข้อมูลตำแหน่ง',
);

const form = useForm({
    sso: props.profileUser?.sso || '',
    title: props.profileUser?.t || '',
    name: props.profileUser?.n || '',
    first_name_th: props.profileUser?.fn || '',
    last_name_th: props.profileUser?.ln || '',
    first_name_en: props.profileUser?.fe || '',
    last_name_en: props.profileUser?.le || '',
    gender: props.profileUser?.g || '',
    email: props.profileUser?.em || '',
    phone: props.profileUser?.ph || '',
    workline: props.profileUser?.w || '',
    department: props.profileUser?.d || '',
    position: props.profileUser?.p || '',
    level: props.profileUser?.l || '',
    supervisor: props.profileUser?.sup || '',
    evaluator2: props.profileUser?.evaluator2 || '',
});

const fieldGroups = computed(() => [
    [
        { key: 'sso', label: 'ID', readonly: true },
        { key: 'display_name', label: 'ชื่อ-นามสกุล', value: fullName.value },
        { key: 'first_name_en', label: 'First Name' },
        { key: 'last_name_en', label: 'Last Name' },
        { key: 'gender', label: 'เพศ', type: 'select', options: ['', 'ชาย', 'หญิง', 'อื่นๆ'] },
        { key: 'email', label: 'อีเมล', type: 'email' },
        { key: 'phone', label: 'เบอร์โทรศัพท์' },
        { key: 'department', label: 'สังกัด/หน่วยงาน' },
    ],
    [
        { key: 'title', label: 'คำนำหน้า' },
        { key: 'first_name_th', label: 'ชื่อ (ภาษาไทย)' },
        { key: 'last_name_th', label: 'นามสกุล (ภาษาไทย)' },
        { key: 'workline', label: 'สายงาน' },
        { key: 'position', label: 'ตำแหน่ง' },
        { key: 'level', label: 'ระดับตำแหน่ง' },
        { key: 'supervisor', label: 'หัวหน้างาน' },
        { key: 'evaluator2', label: 'ผู้บังคับบัญชา' },
    ],
]);

const displayValue = (field) => field.value ?? form[field.key] ?? '';
const goDashboard = (pageId) => {
    router.visit(`${route('dashboard')}?page=${encodeURIComponent(pageId)}`);
};
const logout = () => router.post(route('logout'));

const cancelEdit = () => {
    form.reset();
    form.clearErrors();
    isEditing.value = false;
};

const saveProfile = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};
</script>

<template>
    <Head title="โปรไฟล์" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <aside v-if="showSidebar" class="sidebar">
            <div class="sb-logo">
                <div class="sb-mark">คณะวิศวกรรมศาสตร์</div>
                <div class="sb-name">
                    Competency &<br />
                    IDP Management
                </div>
            </div>

            <button class="sb-user on" type="button">
                <div class="av" :style="{ background: roleData.col }">
                    {{ fullName[0] || roleData.av }}
                </div>
                <div style="overflow: hidden; min-width: 0">
                    <div class="u-name">{{ fullName }}</div>
                    <div class="u-role">{{ form.position || roleData.pos }}</div>
                </div>
            </button>

            <div class="sb-nav">
                <div v-for="(section, sectionIndex) in navSections" :key="sectionIndex">
                    <div class="nav-sec">{{ section.sec }}</div>
                    <button
                        v-for="item in section.items"
                        :key="item.id"
                        class="nav-item nav-button"
                        type="button"
                        @click="goDashboard(item.id)"
                    >
                        <span class="nav-ic">{{ item.ic }}</span>
                        {{ item.lb }}
                    </button>
                </div>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <button class="menu-btn" type="button" @click="showSidebar = !showSidebar">☰</button>
                <div class="tb-title">โปรไฟล์</div>
                <span class="tb-badge">รอบประเมิน 2568</span>
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <form class="content profile-page" @submit.prevent="saveProfile">
                <section class="profile-hero card">
                    <div class="profile-identity">
                        <div class="profile-avatar">
                            {{ fullName[0] || roleData.av }}
                        </div>
                        <div class="profile-heading">
                            <div class="sec-t">โปรไฟล์บุคลากร</div>
                            <h1>{{ fullName }}</h1>
                            <p>{{ roleLine }}</p>
                            <div class="profile-tags">
                                <span>ID {{ form.sso || '—' }}</span>
                                <span>{{ form.workline || 'ยังไม่ได้ระบุสายงาน' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <template v-if="isEditing">
                            <button class="btn btn-s" type="button" @click="cancelEdit">ยกเลิก</button>
                            <button class="btn btn-p" type="submit" :disabled="form.processing">
                                {{ form.processing ? 'กำลังบันทึก...' : 'บันทึกโปรไฟล์' }}
                            </button>
                        </template>
                        <button v-else class="btn btn-p" type="button" @click="isEditing = true">
                            แก้ไขโปรไฟล์
                        </button>
                    </div>
                </section>

                <section class="profile-section card">
                    <div class="fw8 fs14">ข้อมูลบุคลากร</div>
                    <div v-if="status" class="profile-status">{{ status }}</div>

                    <div class="profile-grid">
                        <template v-for="group in fieldGroups" :key="group[0].key">
                            <div v-for="field in group" :key="field.key" class="profile-field">
                                <label class="lbl">{{ field.label }}</label>

                                <template v-if="isEditing && !field.value">
                                    <select
                                        v-if="field.type === 'select'"
                                        v-model="form[field.key]"
                                        class="sel"
                                        :disabled="field.readonly"
                                    >
                                        <option
                                            v-for="option in field.options"
                                            :key="option || 'empty'"
                                            :value="option"
                                        >
                                            {{ option || '— เลือก —' }}
                                        </option>
                                    </select>
                                    <input
                                        v-else
                                        v-model="form[field.key]"
                                        class="inp"
                                        :class="{ readonly: field.readonly }"
                                        :readonly="field.readonly"
                                        :type="field.type || 'text'"
                                        placeholder="-"
                                    />
                                    <div v-if="form.errors[field.key]" class="field-error">
                                        {{ form.errors[field.key] }}
                                    </div>
                                </template>

                                <div v-else class="profile-value">
                                    {{ displayValue(field) || '—' }}
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </form>
        </main>
    </div>
</template>

<style scoped>
.nav-button {
    width: 100%;
}

.menu-btn {
    padding: 8px;
    min-width: 40px;
    border: none;
    background: transparent;
    color: var(--text2);
    font-size: 22px;
    cursor: pointer;
}

.profile-page {
    display: grid;
    gap: 18px;
}

.profile-hero {
    min-height: 246px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 36px;
}

.profile-identity {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 28px;
}

.profile-avatar {
    width: 156px;
    height: 156px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 7px solid var(--bg);
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    font-size: 54px;
    font-weight: 900;
}

.profile-heading {
    min-width: 0;
}

.profile-heading h1 {
    margin: 10px 0 4px;
    color: var(--navy);
    font-size: 30px;
    line-height: 1.18;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.profile-heading p {
    margin: 0;
    color: var(--text2);
    font-size: 14px;
    font-weight: 600;
}

.profile-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
}

.profile-tags span {
    min-height: 30px;
    display: inline-flex;
    align-items: center;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--bg);
    color: var(--text2);
    font-size: 13px;
    font-weight: 800;
}

.profile-actions {
    flex: 0 0 auto;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.profile-section {
    padding: 26px;
}

.profile-status {
    margin-top: 10px;
    color: var(--green);
    font-size: 13px;
    font-weight: 700;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
    margin-top: 18px;
}

.profile-field {
    min-height: 92px;
    display: grid;
    align-content: center;
    gap: 9px;
    padding: 14px 16px;
    border-left: 3px solid var(--blue);
    border-radius: 0 var(--r) var(--r) 0;
    background: var(--bg);
}

.profile-value {
    color: var(--text);
    font-size: 15px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.readonly {
    border-color: #dbe4f0;
    background: #eef2f7;
    cursor: not-allowed;
}

.field-error {
    color: var(--red);
    font-size: 12px;
    font-weight: 700;
}

@media (max-width: 900px) {
    .profile-hero,
    .profile-identity {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile-hero {
        padding: 22px;
    }

    .profile-avatar {
        width: 118px;
        height: 118px;
        font-size: 42px;
    }

    .profile-heading h1 {
        font-size: 24px;
    }

    .profile-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }
}
</style>
