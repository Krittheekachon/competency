<script setup>
import { computed, nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import UserMultiPicker from '../../Components/UserMultiPicker.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    setUsers: { type: Function, required: true },
});

const scoringTypes = ['CC', 'FC', 'MC'];
const typeTabs = [
    { key: 'assessment', label: 'ลำดับการประเมิน' },
    { key: 'idp', label: 'ลำดับ IDP' },
];
const reloadOnly = ['users', 'reviewerChainTemplates'];

const chainType = ref('assessment');
const search = ref('');
const selectedId = ref(null);
const draft = ref(null);
const snapshot = ref('');
const busy = ref(false);
const errorText = ref('');
const message = ref('');
const stepSearch = ref('');
const addOpen = ref(false);
const addInput = ref(null);
const memberPicks = ref([]);
const memberFilter = ref('');
const showMemberPicker = ref(false);
let toastTimer;

const activeUsers = computed(() => props.users.filter((user) => user.act !== false && user.db_id));
const userLabel = (id) => {
    const user = props.users.find((item) => item.db_id === id);
    return user ? `${user.t || ''}${user.n}` : '';
};
const userPosition = (id) => props.users.find((item) => item.db_id === id)?.p || '';
const userOption = (user) => ({ value: user.db_id, label: `${user.t || ''}${user.n}`, p: user.p, org: user.displayOrganization || user.d });

const typeOf = (template) => template.chainType || 'assessment';
const isSystem = (template) => Boolean(template.isDefault) || (template.steps || []).some((step) => step.resolverType !== 'fixed_user');
const memberIds = (template) => [...new Set((template.assignments || []).filter((item) => item.userId).map((item) => item.userId))];
const countByType = (type) => props.templates.filter((template) => typeOf(template) === type).length;
const listed = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return props.templates
        .filter((template) => typeOf(template) === chainType.value)
        .filter((template) => !needle || `${template.name} ${template.description}`.toLowerCase().includes(needle));
});
const selectedTemplate = computed(() => props.templates.find((template) => template.id === selectedId.value) || null);
const readOnly = computed(() => Boolean(draft.value?.id && selectedTemplate.value && isSystem(selectedTemplate.value)));

const serialize = (value) => JSON.stringify({
    name: value.name.trim(),
    description: value.description.trim(),
    reviewers: value.reviewers,
    scoring: value.type === 'assessment' ? value.reviewers.map((id) => [...(value.scoring[id] || [])].sort()) : [],
    members: [...value.members].sort((a, b) => a - b),
});
const dirty = computed(() => Boolean(draft.value) && serialize(draft.value) !== snapshot.value);

const notify = (text) => {
    clearTimeout(toastTimer);
    message.value = text;
    toastTimer = setTimeout(() => { message.value = ''; }, 5000);
};
const firstError = (errors) => Object.values(errors || {})[0] || 'บันทึกไม่สำเร็จ';
const refreshUsers = (responsePage) => {
    if (Array.isArray(responsePage.props.users)) props.setUsers(JSON.parse(JSON.stringify(responsePage.props.users)));
};

const loadDraft = (template) => {
    const steps = template?.steps || [];
    const fixed = steps.filter((step) => step.reviewerId);
    draft.value = {
        id: template?.id || null,
        type: template ? typeOf(template) : chainType.value,
        name: template?.name || '',
        description: template?.description || '',
        reviewers: fixed.map((step) => step.reviewerId),
        scoring: Object.fromEntries(fixed.map((step) => [step.reviewerId, [...(step.scoringTypes || [])]])),
        members: template ? memberIds(template) : [],
        lostSteps: steps.filter((step) => step.resolverType === 'fixed_user' && !step.reviewerId).length,
    };
    snapshot.value = serialize(draft.value);
    stepSearch.value = '';
    memberPicks.value = [];
    memberFilter.value = '';
    showMemberPicker.value = false;
    errorText.value = '';
};
const confirmLeave = () => !dirty.value || window.confirm('มีการแก้ไขที่ยังไม่ได้บันทึก ต้องการทิ้งการแก้ไขนี้หรือไม่?');
const select = (template) => {
    if (template.id === selectedId.value || !confirmLeave()) return;
    selectedId.value = template.id;
    loadDraft(template);
};
const startCreate = () => {
    if (!confirmLeave()) return;
    selectedId.value = null;
    loadDraft(null);
};
const switchType = (type) => {
    if (type === chainType.value || !confirmLeave()) return;
    chainType.value = type;
    selectedId.value = null;
    draft.value = null;
};
const discard = () => {
    if (!dirty.value) return;
    loadDraft(selectedTemplate.value);
};

const reviewerCandidates = computed(() => {
    if (!draft.value) return [];
    const needle = stepSearch.value.trim().toLowerCase();
    const taken = new Set([...draft.value.reviewers, ...draft.value.members]);
    return activeUsers.value
        .filter((user) => !taken.has(user.db_id))
        .filter((user) => !needle || `${user.t || ''}${user.n} ${user.p || ''}`.toLowerCase().includes(needle));
});
const openAdd = async () => {
    stepSearch.value = '';
    addOpen.value = true;
    await nextTick();
    addInput.value?.focus();
};
const closeAdd = () => {
    addOpen.value = false;
    stepSearch.value = '';
};
const pickReviewer = (id) => {
    addReviewer(id);
    closeAdd();
};
const addReviewer = (id) => {
    if (!id) return;
    draft.value.reviewers = [...draft.value.reviewers, Number(id)];
    stepSearch.value = '';
};
const removeReviewer = (index) => {
    draft.value.reviewers = draft.value.reviewers.filter((_, i) => i !== index);
};
const dragIndex = ref(null);
const overIndex = ref(null);
const onDragStart = (index, event) => {
    dragIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};
const onDragOver = (index) => {
    if (dragIndex.value !== null) overIndex.value = index;
};
const onDrop = (index) => {
    const from = dragIndex.value;
    if (from !== null && from !== index) {
        const next = [...draft.value.reviewers];
        const [moved] = next.splice(from, 1);
        next.splice(index, 0, moved);
        draft.value.reviewers = next;
    }
    onDragEnd();
};
const onDragEnd = () => {
    dragIndex.value = null;
    overIndex.value = null;
};
const hasType = (id, type) => (draft.value.scoring[id] || []).includes(type);
const toggleType = (id, type) => {
    const current = draft.value.scoring[id] || [];
    draft.value.scoring = { ...draft.value.scoring, [id]: current.includes(type) ? current.filter((item) => item !== type) : [...current, type] };
};

const memberOptions = computed(() => {
    if (!draft.value) return [];
    const blocked = new Set([...draft.value.reviewers, ...draft.value.members]);
    return activeUsers.value.filter((user) => !blocked.has(user.db_id)).map(userOption);
});
const addMembers = () => {
    draft.value.members = [...new Set([...draft.value.members, ...memberPicks.value])];
    memberPicks.value = [];
    showMemberPicker.value = false;
};
const memberRows = computed(() => {
    if (!draft.value) return [];
    const needle = memberFilter.value.trim().toLowerCase();
    const original = selectedTemplate.value ? memberIds(selectedTemplate.value) : [];
    return draft.value.members
        .map((id) => {
            const user = props.users.find((item) => item.db_id === id);
            const name = user ? `${user.t || ''}${user.n}` : `#${id}`;
            return {
                id,
                name,
                initial: String(user?.n || '?').trim().slice(0, 1),
                detail: [user?.p, user?.displayOrganization || user?.d].filter(Boolean).join(' · ') || '-',
                isNew: Boolean(draft.value.id) && !original.includes(id),
            };
        })
        .filter((row) => !needle || `${row.name} ${row.detail}`.toLowerCase().includes(needle));
});
const clearMembers = () => {
    if (draft.value.members.length && window.confirm(`เอาผู้ใช้ทั้ง ${draft.value.members.length} คนออกจากสายนี้?`)) draft.value.members = [];
};
const removeMember = (id) => {
    draft.value.members = draft.value.members.filter((item) => item !== id);
};

const requestOptions = (onSuccess) => ({
    preserveScroll: true,
    only: reloadOnly,
    onError: (errors) => { busy.value = false; errorText.value = firstError(errors); },
    onSuccess,
});

const save = () => {
    const current = draft.value;
    if (!current || busy.value || readOnly.value) return;
    if (!current.name.trim()) { errorText.value = 'กรุณากรอกชื่อสาย'; return; }
    if (!current.reviewers.length) { errorText.value = 'กรุณาเพิ่มผู้ประเมินอย่างน้อย 1 คน'; return; }
    busy.value = true;
    errorText.value = '';

    const body = {
        name: current.name.trim(),
        description: current.description.trim(),
        reviewer_ids: current.reviewers,
        scoring: current.type === 'assessment' ? Object.fromEntries(current.reviewers.map((id) => [id, current.scoring[id] || []])) : {},
    };
    const done = (responsePage) => {
        refreshUsers(responsePage);
        busy.value = false;
        const fresh = responsePage.props.reviewerChainTemplates || [];
        const saved = current.id
            ? fresh.find((template) => template.id === current.id)
            : fresh.filter((template) => template.name === body.name && typeOf(template) === current.type).sort((a, b) => b.id - a.id)[0];
        if (saved) { selectedId.value = saved.id; loadDraft(saved); }
        notify(`บันทึก “${body.name}” แล้ว`);
    };

    if (!current.id) {
        router.post(route('admin.reviewer-chain-templates.store'), { ...body, chain_type: current.type, assignment_user_ids: current.members }, requestOptions(done));
        return;
    }

    const original = memberIds(selectedTemplate.value);
    const toAdd = current.members.filter((id) => !original.includes(id));
    const toRemove = original.filter((id) => !current.members.includes(id));
    const steps = [() => router.patch(route('admin.reviewer-chain-templates.update', current.id), body, requestOptions(next))];
    if (toAdd.length) steps.push(() => router.post(route('admin.reviewer-chain-templates.users.store', current.id), { user_ids: toAdd }, requestOptions(next)));
    toRemove.forEach((id) => steps.push(() => router.delete(route('admin.reviewer-chain-templates.users.destroy', { template: current.id, user: id }), requestOptions(next))));
    let index = 0;
    function next(responsePage) {
        index += 1;
        if (index < steps.length) { steps[index](); return; }
        done(responsePage);
    }
    steps[0]();
};

const removeTemplate = () => {
    const template = selectedTemplate.value;
    if (!template || busy.value) return;
    const members = memberIds(template).length;
    const warning = members ? ` ผู้ใช้ ${members} คนที่ใช้สายนี้จะถูกล้างสายออก` : '';
    if (!window.confirm(`ลบ “${template.name}”?${warning}`)) return;
    router.delete(route('admin.reviewer-chain-templates.destroy', template.id), {
        preserveScroll: true,
        only: reloadOnly,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onError: (errors) => { errorText.value = firstError(errors); },
        onSuccess: (responsePage) => {
            refreshUsers(responsePage);
            selectedId.value = null;
            draft.value = null;
            notify(`ลบ “${template.name}” แล้ว`);
        },
    });
};

const stepName = (step) => (step.reviewerId ? (userLabel(step.reviewerId) || `#${step.reviewerId}`) : (step.resolverType === 'fixed_user' ? 'ไม่พบผู้ประเมิน (ถูกลบ)' : step.label));
const lostStep = (step) => step.resolverType === 'fixed_user' && !step.reviewerId;
</script>

<template>
    <section class="rc-page">
        <header class="rc-head">
            <div class="sec-t">ลำดับการประเมิน &amp; IDP</div>
            <div class="sec-s">สร้างและจัดการสายผู้ประเมิน กำหนดผู้ประเมินตามลำดับ และผู้ประเมินแต่ละคนให้คะแนนแทนพนักงานได้ในหัวข้อไหน (CC / FC / MC)</div>
        </header>

        <div class="rc-layout">
            <aside class="card rc-list">
                <div class="rc-seg" role="tablist">
                    <button v-for="tab in typeTabs" :key="tab.key" type="button" role="tab" :aria-selected="chainType === tab.key" :class="{ on: chainType === tab.key }" @click="switchType(tab.key)">
                        {{ tab.label }} <span class="rc-count">{{ countByType(tab.key) }}</span>
                    </button>
                </div>
                <div class="rc-list-tools">
                    <input v-model="search" class="inp" placeholder="ค้นหาสาย" aria-label="ค้นหาสาย" />
                    <button type="button" class="btn btn-p" @click="startCreate">+ สร้างสาย</button>
                </div>
                <ul v-if="listed.length" class="rc-items">
                    <li v-for="template in listed" :key="template.id">
                        <button type="button" class="rc-item" :class="{ on: template.id === selectedId }" @click="select(template)">
                            <span class="rc-item-name">{{ template.name }}</span>
                            <span class="rc-item-meta">
                                {{ template.steps.length }} ลำดับ · {{ memberIds(template).length }} คน
                                <span v-if="isSystem(template)" class="rc-badge off">ระบบ</span>
                            </span>
                        </button>
                    </li>
                </ul>
                <p v-else class="rc-none">{{ search ? 'ไม่พบสายที่ค้นหา' : 'ยังไม่มีสาย' }}</p>
            </aside>

            <main class="card rc-detail">
                <div v-if="message" class="rc-toast ok" role="status">{{ message }}</div>

                <div v-if="!draft" class="rc-placeholder">
                    <strong>เลือกสายทางซ้ายเพื่อดูหรือแก้ไข</strong>
                    <span>หรือกด “+ สร้างสาย” เพื่อสร้างสาย{{ chainType === 'idp' ? ' IDP' : 'ประเมิน' }}ใหม่</span>
                </div>

                <template v-else>
                    <div class="rc-detail-head">
                        <span class="rc-kind">{{ draft.type === 'idp' ? 'สายอนุมัติ IDP' : 'สายประเมิน' }}{{ draft.id ? '' : ' · สายใหม่' }}</span>
                        <span v-if="dirty" class="rc-dirty">มีการแก้ไขที่ยังไม่บันทึก</span>
                        <span v-if="readOnly" class="rc-badge off">สายของระบบ แก้ไขไม่ได้</span>
                        <span class="rc-spacer"></span>
                        <button v-if="dirty && !readOnly" type="button" class="btn btn-p rc-head-save" :disabled="busy" @click="save">{{ busy ? 'กำลังบันทึก...' : draft.id ? 'บันทึก' : 'สร้างสาย' }}</button>
                    </div>

                    <div class="rc-fields">
                        <div>
                            <label class="lbl req">ชื่อสาย</label>
                            <input v-model="draft.name" class="inp" :disabled="readOnly" :placeholder="draft.type === 'idp' ? 'เช่น ลำดับ IDP สายสนับสนุน' : 'เช่น ลำดับประเมินสายวิชาการ'" />
                        </div>
                        <div>
                            <label class="lbl">คำอธิบาย</label>
                            <input v-model="draft.description" class="inp" :disabled="readOnly" />
                        </div>
                    </div>

                    <section class="rc-section">
                        <div class="rc-section-head">
                            <h4>ผู้ประเมินตามลำดับ</h4>
                            <small v-if="draft.type === 'assessment' && !readOnly">ลากการ์ดเพื่อเปลี่ยนลำดับ · กดหัวข้อเพื่อให้ผู้ประเมินให้คะแนนแทนพนักงานได้ ไม่เลือก = อนุมัติ/ส่งกลับอย่างเดียว</small>
                        </div>

                        <div v-if="draft.lostSteps && !readOnly" class="rc-toast warn" role="alert">
                            สายนี้มีผู้ประเมิน {{ draft.lostSteps }} ลำดับที่ไม่พบแล้ว (ผู้ใช้ถูกลบ) เพิ่มผู้ประเมินใหม่แล้วกดบันทึกเพื่อแทนที่
                        </div>

                        <ol v-if="readOnly" class="rc-flow">
                            <li v-for="step in selectedTemplate.steps" :key="`s-${step.step}`" class="rc-step" :class="{ lost: lostStep(step) }">
                                <span class="rc-no">{{ step.step }}</span>
                                <strong>{{ stepName(step) }}</strong>
                            </li>
                        </ol>
                        <ol v-else class="rc-flow">
                            <li
                                v-for="(id, index) in draft.reviewers"
                                :key="`r-${id}`"
                                class="rc-step rc-draggable"
                                :class="{ dragging: dragIndex === index, 'drop-before': overIndex === index && dragIndex !== null && dragIndex > index, 'drop-after': overIndex === index && dragIndex !== null && dragIndex < index }"
                                draggable="true"
                                @dragstart="onDragStart(index, $event)"
                                @dragover.prevent="onDragOver(index)"
                                @drop.prevent="onDrop(index)"
                                @dragend="onDragEnd"
                            >
                                <div class="rc-step-top">
                                    <span class="rc-no">{{ index + 1 }}</span>
                                    <span class="rc-grip" title="ลากเพื่อเปลี่ยนลำดับ" aria-hidden="true">⠿</span>
                                    <button type="button" class="rc-step-x" :aria-label="`เอา ${userLabel(id)} ออก`" @click="removeReviewer(index)">×</button>
                                </div>
                                <strong>{{ userLabel(id) || `#${id}` }}</strong>
                                <small>{{ userPosition(id) || '-' }}</small>
                                <div v-if="draft.type === 'assessment'" class="rc-types">
                                    <button v-for="type in scoringTypes" :key="type" type="button" class="rc-type" :class="{ on: hasType(id, type) }" :aria-pressed="hasType(id, type)" @click="toggleType(id, type)">{{ type }}</button>
                                    <span v-if="!(draft.scoring[id] || []).length" class="rc-approve">อนุมัติอย่างเดียว</span>
                                </div>
                            </li>
                            <li class="rc-step rc-add" :class="{ open: addOpen }">
                                <button v-if="!addOpen" type="button" class="rc-add-btn" @click="openAdd">
                                    <span class="rc-plus">+</span>
                                    เพิ่มผู้ประเมิน
                                </button>
                                <template v-else>
                                    <input
                                        ref="addInput"
                                        v-model="stepSearch"
                                        class="inp"
                                        placeholder="พิมพ์ชื่อหรือตำแหน่ง"
                                        @keydown.escape="closeAdd"
                                        @blur="closeAdd"
                                    />
                                    <ul class="rc-pop" @mousedown.prevent>
                                        <li v-for="user in reviewerCandidates" :key="`c-${user.db_id}`">
                                            <button type="button" @click="pickReviewer(user.db_id)">
                                                <strong>{{ user.t }}{{ user.n }}</strong>
                                                <small>{{ user.p || '-' }}</small>
                                            </button>
                                        </li>
                                        <li v-if="!reviewerCandidates.length" class="rc-pop-empty">ไม่พบรายชื่อ</li>
                                    </ul>
                                </template>
                            </li>
                        </ol>
                    </section>

                    <section class="rc-section">
                        <div class="rc-section-head rc-members-head">
                            <h4>ผู้ใช้ที่ใช้สายนี้ <span class="rc-count">{{ draft.members.length }}</span></h4>
                            <small v-if="!readOnly">ได้สายนี้ทันทีที่บันทึก · แก้สายแล้วอัปเดตให้ทุกคน</small>
                            <span class="rc-spacer"></span>
                            <button v-if="!readOnly && !showMemberPicker && draft.members.length" type="button" class="btn btn-s" @click="showMemberPicker = true">+ เพิ่มผู้ใช้</button>
                        </div>

                        <template v-if="!readOnly && showMemberPicker">
                            <UserMultiPicker v-model="memberPicks" :options="memberOptions">
                                <template #actions>
                                    <span class="rc-picker-actions">
                                        <button class="btn btn-s" type="button" @click="showMemberPicker = false; memberPicks = []">ยกเลิก</button>
                                        <button class="btn btn-p" type="button" :disabled="!memberPicks.length" @click="addMembers">เพิ่ม{{ memberPicks.length ? ` (${memberPicks.length})` : '' }}</button>
                                    </span>
                                </template>
                            </UserMultiPicker>
                        </template>

                        <div v-if="draft.members.length" class="rc-members">
                            <div v-if="draft.members.length > 6 || memberFilter" class="rc-members-tools">
                                <input v-model="memberFilter" class="inp" placeholder="ค้นหาในรายชื่อ" aria-label="ค้นหาในรายชื่อ" />
                                <button v-if="!readOnly" type="button" class="rc-link" @click="clearMembers">เอาออกทั้งหมด</button>
                            </div>
                            <ul class="rc-member-list">
                                <li v-for="row in memberRows" :key="`m-${row.id}`" class="rc-member">
                                    <span class="rc-avatar" aria-hidden="true">{{ row.initial }}</span>
                                    <span class="rc-member-body">
                                        <strong>{{ row.name }}</strong>
                                        <small>{{ row.detail }}</small>
                                    </span>
                                    <span v-if="row.isNew" class="rc-badge new">เพิ่มใหม่</span>
                                    <button v-if="!readOnly" type="button" class="rc-member-x" :aria-label="`เอา ${row.name} ออก`" @click="removeMember(row.id)">×</button>
                                </li>
                                <li v-if="!memberRows.length" class="rc-none rc-member-empty">ไม่พบชื่อที่ค้นหา</li>
                            </ul>
                        </div>
                        <div v-else-if="!showMemberPicker" class="rc-members-empty">
                            <span class="rc-members-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2" /><path d="M2.8 19c.4-3.2 3-5 6.2-5s5.8 1.8 6.2 5" /><circle cx="17.2" cy="9" r="2.4" /><path d="M17.6 14.2c2.2.2 3.6 1.6 3.9 3.8" /></svg>
                            </span>
                            <strong>ยังไม่มีผู้ใช้ที่ใช้สายนี้</strong>
                            <span v-if="!readOnly">เพิ่มผู้ใช้เพื่อให้ได้สายนี้ทันทีที่บันทึก</span>
                            <button v-if="!readOnly" type="button" class="btn btn-p" @click="showMemberPicker = true">+ เพิ่มผู้ใช้</button>
                        </div>
                    </section>

                    <div v-if="errorText" class="rc-toast bad" role="alert">{{ errorText }}</div>

                    <footer v-if="!readOnly" class="rc-foot">
                        <button v-if="draft.id" type="button" class="btn btn-s rc-danger" :disabled="busy" @click="removeTemplate">ลบสายนี้</button>
                        <span class="rc-spacer"></span>
                        <button type="button" class="btn btn-s" :disabled="busy || !dirty" @click="discard">ยกเลิกการแก้ไข</button>
                        <button type="button" class="btn btn-p" :disabled="busy || (!dirty && !!draft.id)" @click="save">{{ busy ? 'กำลังบันทึก...' : draft.id ? 'บันทึก' : 'สร้างสาย' }}</button>
                    </footer>
                </template>
            </main>
        </div>
    </section>
</template>

<style scoped>
.rc-page { display: grid; gap: 14px; }
.rc-layout { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 14px; align-items: start; }
.rc-list { display: grid; gap: 12px; padding: 14px; position: sticky; top: 12px; }
.rc-seg { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 4px; border-radius: 10px; background: #f1f5f9; }
.rc-seg button { padding: 8px 6px; border: 0; border-radius: 8px; background: none; font: inherit; font-weight: 800; font-size: 13px; color: #64748b; cursor: pointer; }
.rc-seg button.on { background: #fff; color: #c93616; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12); }
.rc-count { display: inline-block; min-width: 20px; padding: 0 6px; border-radius: 999px; background: #e2e8f0; color: #475569; font-size: 12px; text-align: center; }
.rc-list-tools { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; }
.rc-items { margin: 0; padding: 0; list-style: none; display: grid; gap: 6px; max-height: 60vh; overflow-y: auto; }
.rc-item { display: grid; gap: 3px; width: 100%; padding: 10px 12px; text-align: left; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; font: inherit; cursor: pointer; }
.rc-item:hover { border-color: #cbd5e1; }
.rc-item.on { border-color: #c93616; background: #fff7f4; box-shadow: 0 0 0 2px #fde4dc; }
.rc-item-name { font-weight: 800; color: #0f172a; }
.rc-item-meta { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #64748b; }
.rc-none { margin: 0; color: #94a3b8; font-size: 13px; }
.rc-detail { display: grid; gap: 16px; padding: 18px; min-height: 420px; align-content: start; }
.rc-placeholder { display: grid; gap: 6px; place-content: center; justify-items: center; min-height: 340px; color: #94a3b8; text-align: center; }
.rc-placeholder strong { color: #64748b; font-size: 16px; }
.rc-detail-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.rc-kind { padding: 3px 10px; border-radius: 999px; background: #fff1e8; color: #c93616; font-size: 13px; font-weight: 900; }
.rc-dirty { color: #c2410c; font-size: 13px; font-weight: 800; }
.rc-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.rc-section { display: grid; gap: 10px; padding-top: 14px; border-top: 1px solid #e2e8f0; }
.rc-section-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; }
.rc-section-head h4 { margin: 0; font-size: 15px; }
.rc-section-head small { color: #64748b; }
.rc-flow { display: flex; flex-wrap: wrap; gap: 26px 30px; margin: 0; padding: 0; list-style: none; }
.rc-step { position: relative; display: grid; gap: 4px; align-content: start; width: 210px; padding: 12px; border: 1px solid #dbe3ef; border-radius: 12px; background: #f8fafc; }
.rc-step:has(+ .rc-step:not(.rc-add))::after { content: '→'; position: absolute; right: -24px; top: 18px; color: #94a3b8; font-weight: 900; }
.rc-step.lost { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.rc-step-top { display: flex; align-items: center; justify-content: space-between; }
.rc-draggable { cursor: grab; transition: opacity 0.12s, box-shadow 0.12s; }
.rc-draggable:active { cursor: grabbing; }
.rc-draggable.dragging { opacity: 0.45; }
.rc-draggable.drop-before { box-shadow: -6px 0 0 0 #c93616; }
.rc-draggable.drop-after { box-shadow: 6px 0 0 0 #c93616; }
.rc-grip { flex: 1; text-align: center; color: #94a3b8; font-size: 18px; line-height: 1; letter-spacing: 1px; user-select: none; }
.rc-step-x { width: 24px; height: 24px; border: 1px solid #dbe3ef; border-radius: 6px; background: #fff; color: #b91c1c; cursor: pointer; font-size: 14px; line-height: 1; }
.rc-step-x:hover { background: #fee2e2; }
.rc-step strong { word-break: break-word; }
.rc-step small { color: #64748b; }
.rc-types { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 6px; }
.rc-type { padding: 2px 10px; border: 1px solid #cbd5e1; border-radius: 999px; background: #fff; color: #475569; font: inherit; font-size: 12.5px; font-weight: 900; cursor: pointer; }
.rc-type.on { border-color: #1d4ed8; background: #dbeafe; color: #1d4ed8; }
.rc-approve { color: #94a3b8; font-size: 12px; }
.rc-add { border-style: dashed; background: #fff; align-content: center; min-height: 92px; }
.rc-add.open { align-self: start; min-height: 0; padding: 8px; border-style: solid; border-color: #c93616; }
.rc-add.open .inp { margin: 0; }
.rc-add-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 66px; border: 0; background: none; font: inherit; font-weight: 800; color: #64748b; cursor: pointer; }
.rc-add-btn:hover { color: #c93616; }
.rc-plus { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 999px; background: #fff1e8; color: #c93616; font-size: 18px; line-height: 1; }
.rc-pop { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; width: 300px; max-width: calc(100vw - 48px); max-height: 304px; margin: 0; padding: 4px; overflow-y: auto; overscroll-behavior: contain; list-style: none; border: 1px solid #dbe3ef; border-radius: 10px; background: #fff; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18); }
.rc-pop button { display: grid; gap: 0; width: 100%; padding: 6px 10px; line-height: 1.35; text-align: left; border: 0; border-radius: 8px; background: none; font: inherit; cursor: pointer; }
.rc-pop button:hover { background: #fff1e8; }
.rc-pop small { color: #64748b; }
.rc-pop-empty { padding: 10px; color: #94a3b8; font-size: 13px; }
.rc-no { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 999px; background: #fff1e8; color: #c93616; font-size: 13px; font-weight: 900; }
.rc-badge { padding: 1px 7px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 900; }
.rc-badge.off { background: #f1f5f9; color: #64748b; font-weight: 700; }
/* members */
.rc-members-empty { display: grid; justify-items: center; gap: 6px; padding: 26px 16px; border: 1.5px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; text-align: center; color: #64748b; font-size: 13.5px; }
.rc-members-empty strong { color: #334155; font-size: 15px; }
.rc-members-empty .btn { margin-top: 8px; }
.rc-members-icon { display: grid; place-items: center; width: 48px; height: 48px; margin-bottom: 4px; border-radius: 999px; background: #fff1e8; }
.rc-members-icon svg { width: 26px; height: 26px; fill: none; stroke: #c93616; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.rc-members-head { align-items: center; }
.rc-members { display: grid; gap: 8px; }
.rc-members-tools { display: flex; align-items: center; gap: 12px; }
.rc-members-tools .inp { max-width: 280px; }
.rc-link { border: 0; background: none; color: #c93616; font: inherit; font-weight: 800; cursor: pointer; }
.rc-member-list { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 8px; max-height: 330px; overflow-y: auto; }
.rc-member { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
.rc-avatar { flex: none; display: inline-grid; place-items: center; width: 34px; height: 34px; border-radius: 999px; background: #c93616; color: #fff; font-weight: 900; }
.rc-member-body { display: grid; min-width: 0; flex: 1; }
.rc-member-body strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rc-member-body small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #64748b; }
.rc-badge.new { background: #dcfce7; color: #15803d; }
.rc-member-x { flex: none; width: 26px; height: 26px; border: 0; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 16px; line-height: 1; cursor: pointer; }
.rc-member-x:hover { background: #fee2e2; color: #b91c1c; }
.rc-member-empty { grid-column: 1 / -1; padding: 10px; }
.rc-picker-actions { display: inline-flex; gap: 8px; }
.rc-toast { padding: 10px 14px; border-radius: 8px; font-weight: 700; }
.rc-toast.ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.rc-toast.bad { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.rc-toast.warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.rc-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding-top: 14px; border-top: 1px solid #e2e8f0; }
.rc-spacer { flex: 1; }
.rc-danger { color: #b91c1c; }
@media (max-width: 900px) {
    .rc-layout { grid-template-columns: 1fr; }
    .rc-list { position: static; }
    .rc-items { max-height: 260px; }
    .rc-fields { grid-template-columns: 1fr; }
    .rc-step { width: 100%; }
    .rc-step:has(+ .rc-step:not(.rc-add))::after { content: '↓'; right: auto; left: 50%; top: auto; bottom: -22px; }
    .rc-flow { gap: 26px; }
}
</style>
