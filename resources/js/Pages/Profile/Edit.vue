<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
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
const photoInput = ref(null);
const normalizeRoleKey = (role) => role === 'manager_dept' ? 'dept_head' : role;

const roleKey = computed(() => normalizeRoleKey(props.profileUser?.r || 'employee'));
const roleData = computed(() => ROLES_CONFIG[roleKey.value] || ROLES_CONFIG.employee);
const navSections = computed(() => {
    if (roleKey.value === 'dept_head') return NAV_CONFIG.dept_head || NAV_CONFIG.manager_dept || [];

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
    email: props.profileUser?.em || '',
    phone: props.profileUser?.ph || '',
    workline: props.profileUser?.w || '',
    department: props.profileUser?.d || '',
    position: props.profileUser?.p || '',
    level: props.profileUser?.l || '',
    supervisor: props.profileUser?.sup || '',
    evaluator2: props.profileUser?.evaluator2 || '',
    profile_photo: props.profileUser?.photo || '',
});

const fieldGroups = computed(() => [
    { key: 'sso', label: 'ID', readonly: true },
    { key: 'title', label: 'คำนำหน้า', required: true },
    { key: 'first_name_th', label: 'ชื่อ (ภาษาไทย)', required: true },
    { key: 'last_name_th', label: 'นามสกุล (ภาษาไทย)', required: true },
    { key: 'first_name_en', label: 'First Name', required: true },
    { key: 'last_name_en', label: 'Last Name', required: true },
    { key: 'email', label: 'อีเมล', type: 'email' },
    { key: 'phone', label: 'เบอร์โทรศัพท์' },
    { key: 'department', label: 'สังกัด/หน่วยงาน' },
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

const choosePhoto = () => {
    photoInput.value?.click();
};

const updatePhoto = (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file || !file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.onload = () => {
        form.profile_photo = String(reader.result || '');
    };
    reader.readAsDataURL(file);
};

const removePhoto = () => {
    form.profile_photo = '';
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
            <SidebarBrand />

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
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <form class="content profile-page" @submit.prevent="saveProfile">
                <section class="profile-hero card">
                    <div class="profile-identity">
                        <div class="profile-photo-block">
                        <div class="profile-avatar">
                            <img
                                v-if="form.profile_photo"
                                :src="form.profile_photo"
                                :alt="fullName"
                            />
                            <span v-else>{{ fullName[0] || roleData.av }}</span>
                        </div>
                        <div v-if="isEditing" class="photo-actions">
                            <input
                                ref="photoInput"
                                type="file"
                                accept="image/*"
                                class="photo-input"
                                @change="updatePhoto"
                            />
                            <button class="btn btn-s btn-sm" type="button" @click="choosePhoto">
                                {{ form.profile_photo ? 'เปลี่ยนรูป' : 'เพิ่มรูป' }}
                            </button>
                        </div>
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
                            <button class="profile-primary-btn" type="submit" :disabled="form.processing">
                                {{ form.processing ? 'กำลังบันทึก...' : 'บันทึกโปรไฟล์' }}
                            </button>
                        </template>
                        <button v-else class="profile-primary-btn" type="button" @click="isEditing = true">
                            แก้ไขโปรไฟล์
                        </button>
                    </div>
                </section>

                <section class="profile-section card">
                    <div class="fw8 fs14">ข้อมูลบุคลากร</div>
                    <div v-if="status" class="profile-status">{{ status }}</div>

                    <div class="profile-grid" :class="{ editing: isEditing }">
                        <div v-for="field in fieldGroups" :key="field.key" class="profile-field">
                            <label class="lbl">
                                {{ field.label }} <span v-if="field.required" class="required">*</span>
                            </label>

                            <template v-if="isEditing">
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
    gap: 14px;
}

.profile-hero {
    min-height: 214px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
    padding: 34px 40px;
}

.profile-identity {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 30px;
}

.profile-avatar {
    width: 144px;
    height: 144px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 5px solid var(--bg);
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    font-size: 52px;
    font-weight: 900;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.profile-photo-block {
    display: grid;
    justify-items: center;
    gap: 12px;
}

.photo-input {
    display: none;
}

.photo-actions {
    width: 132px;
    display: flex;
    justify-content: center;
}

.profile-heading {
    min-width: 0;
}

.profile-heading h1 {
    margin: 6px 0 4px;
    color: var(--navy);
    font-size: 26px;
    line-height: 1.18;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.profile-heading p {
    margin: 0;
    color: var(--text2);
    font-size: 13px;
    font-weight: 600;
}

.profile-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.profile-tags span {
    min-height: 26px;
    display: inline-flex;
    align-items: center;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--bg);
    color: var(--text2);
    font-size: 12px;
    font-weight: 800;
}

.profile-actions {
    flex: 0 0 auto;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    max-width: 480px;
}

.profile-primary-btn {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 18px;
    border: 1px solid #1d4ed8;
    border-radius: var(--r);
    background: var(--blue);
    color: #fff;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.24);
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.2;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.15s;
    white-space: nowrap;
}

.profile-primary-btn:hover {
    border-color: #1e40af;
    background: #1d4ed8;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.32);
    transform: translateY(-1px);
}

.profile-primary-btn:disabled {
    opacity: 0.68;
    cursor: not-allowed;
    transform: none;
}

.profile-section {
    padding: 20px 24px;
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
    gap: 10px 14px;
    margin-top: 14px;
}

.profile-grid.editing {
    gap: 24px 16px;
}

.profile-field {
    min-height: 72px;
    display: grid;
    align-content: center;
    gap: 6px;
    padding: 10px 14px;
    border-left: 3px solid var(--blue);
    border-radius: 0 var(--r) var(--r) 0;
    background: var(--bg);
}

.profile-grid.editing .profile-field {
    min-height: 0;
    display: block;
    padding: 0;
    border-left: 0;
    border-radius: 0;
    background: transparent;
}

.profile-value {
    color: var(--text);
    font-size: 14px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.profile-field :deep(.inp),
.profile-field :deep(.sel) {
    min-height: 36px;
    padding-top: 6px;
    padding-bottom: 6px;
}

.profile-grid.editing :deep(.inp),
.profile-grid.editing :deep(.sel) {
    min-height: 48px;
    padding: 10px 14px;
    font-size: 14px;
}

.required {
    color: var(--red);
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
        padding: 24px;
    }

    .profile-avatar {
        width: 112px;
        height: 112px;
        font-size: 40px;
    }

    .profile-heading h1 {
        font-size: 21px;
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
