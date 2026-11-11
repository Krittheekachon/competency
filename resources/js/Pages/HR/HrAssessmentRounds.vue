<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    rounds: { type: Array, default: () => [] },
});

const nameInput = ref(null);
const editorElement = ref(null);
const editingId = ref(null);
const isEditorOpen = ref(props.rounds.length === 0);
const form = useForm({
    name: '',
    self_assess_start: '',
    self_assess_end: '',
    supervisor_assess_end: '',
    is_active: true,
    copy_from_round_id: props.rounds[0]?.id || '',
    assessment_method: 'standard',
    expected_level_mode: 'by_level',
    expected_level_fixed: 4,
});

const activeRound = computed(() => props.rounds.find((round) => round.isActive) || null);
const isEditing = computed(() => editingId.value !== null);
const editingRound = computed(() => props.rounds.find((round) => round.id === editingId.value) || null);
const activeSubmittedCount = computed(() => Number(activeRound.value?.submittedUserCount || 0));
const activeEligibleCount = computed(() => Number(activeRound.value?.eligibleUserCount || 0));
const activeRemainingCount = computed(() => Math.max(
    activeEligibleCount.value - activeSubmittedCount.value,
    0,
));
const dateOrderInvalid = computed(() => Boolean(
    (form.self_assess_start && form.self_assess_end && form.self_assess_end < form.self_assess_start)
    || (form.self_assess_end && form.supervisor_assess_end && form.supervisor_assess_end < form.self_assess_end),
));
const canSubmitEditor = computed(() => Boolean(form.name.trim()) && !dateOrderInvalid.value && !form.processing);
const dateDuration = (start, end) => {
    if (!start || !end || end < start) return null;
    return Math.floor((new Date(`${end}T00:00:00`) - new Date(`${start}T00:00:00`)) / 86400000) + 1;
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.is_active = !activeRound.value;
    form.copy_from_round_id = props.rounds[0]?.id || '';
    form.clearErrors();
};

const showEditor = async () => {
    isEditorOpen.value = true;
    await nextTick();
    nameInput.value?.focus();
};

const openCreate = () => {
    resetForm();
    showEditor();
};

const closeEditor = () => {
    resetForm();
    isEditorOpen.value = false;
};

const handleEditorEscape = (event) => {
    event.preventDefault();
    if (form.isDirty && !window.confirm('มีข้อมูลที่ยังไม่ได้บันทึก ต้องการปิดหน้าต่างหรือไม่?')) return;
    closeEditor();
};

const trapEditorFocus = (event) => {
    if (event.key !== 'Tab' || !editorElement.value) return;
    const focusable = [...editorElement.value.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
};

const editRound = (round) => {
    editingId.value = round.id;
    form.name = round.name;
    form.self_assess_start = round.selfAssessStart || '';
    form.self_assess_end = round.selfAssessEnd || '';
    form.supervisor_assess_end = round.supervisorAssessEnd || '';
    form.is_active = round.isActive;
    form.copy_from_round_id = '';
    form.assessment_method = round.assessmentMethod || 'standard';
    form.expected_level_mode = round.expectedLevelMode || 'by_level';
    form.expected_level_fixed = round.expectedLevelFixed || 4;
    form.clearErrors();
    showEditor();
};

const submit = () => {
    form.clearErrors();
    const labels = {
        self_assess_start: 'วันเริ่มการประเมิน',
        self_assess_end: 'วันสิ้นสุดการประเมิน',
        supervisor_assess_end: 'วันสิ้นสุดการตรวจของหัวหน้า',
    };

    for (const field of Object.keys(labels)) {
        if (!form[field] && form.is_active) {
            form.setError(field, `กรุณาระบุ${labels[field]}`);
        }
    }

    if (Object.keys(form.errors).length) return;
    if (form.self_assess_start && form.self_assess_end && form.self_assess_end < form.self_assess_start) {
        form.setError('self_assess_end', 'วันสิ้นสุดการประเมินต้องไม่ก่อนวันเริ่มการประเมิน');
        return;
    }
    if (form.self_assess_end && form.supervisor_assess_end && form.supervisor_assess_end < form.self_assess_end) {
        form.setError('supervisor_assess_end', 'วันสิ้นสุดการตรวจของหัวหน้าต้องไม่ก่อนวันสิ้นสุดการประเมิน');
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: closeEditor,
    };

    if (isEditing.value) {
        form.put(route('hr.assessment-rounds.update', editingId.value), options);
        return;
    }

    form.post(route('hr.assessment-rounds.store'), options);
};

const activate = (round) => {
    if (round.isActive) return;
    if (round.readiness && !round.readiness.ready) return;
    if (!window.confirm(`เปิดใช้ “${round.name}” เป็นรอบปัจจุบัน? รอบที่เปิดใช้อยู่จะถูกปิดทันที`)) return;
    router.patch(route('hr.assessment-rounds.activate', round.id), {}, { preserveScroll: true });
};

const closeRound = (round) => {
    if (!round.isActive) return;
    if (!window.confirm(`สิ้นสุด “${round.name}” ตอนนี้? ผู้ใช้จะเริ่ม Assessment ใหม่ไม่ได้ แต่ IDP และงานที่เริ่มไว้จะยังทำต่อได้`)) return;
    router.patch(route('hr.assessment-rounds.close', round.id), {}, { preserveScroll: true });
};

const readinessMessage = (round) => (round.readiness?.issues || [])
    .map((issue) => issue.message)
    .join(' · ');

const dateLabel = (value) => value
    ? new Intl.DateTimeFormat('th-TH-u-ca-gregory', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Bangkok' }).format(new Date(`${value}T00:00:00+07:00`))
    : 'ยังไม่กำหนด';
</script>

<template>
    <section class="round-page">
        <header class="page-heading">
            <div>
                <h1>รอบการประเมิน <span aria-hidden="true">▣</span></h1>
                <p>เปิด-ปิดรอบ กำหนดช่วงเวลา และตรวจสอบสถานะ</p>
            </div>
            <button type="button" class="create-button" @click="openCreate">
                <span aria-hidden="true">+</span> เปิดรอบใหม่
            </button>
        </header>

        <div class="summary-grid">
            <section class="summary-card active-card">
                <span class="summary-label">รอบที่กำลังเปิดอยู่</span>
                <template v-if="activeRound">
                    <strong class="active-name">{{ activeRound.name }}</strong>
                    <span class="active-method">{{ activeRound.assessmentMethod === 'faculty' ? 'รูปแบบตามกำหนดคณะ' : activeRound.assessmentMethodLabel }}</span>
                </template>
                <template v-else>
                    <strong class="active-name empty">ยังไม่มีรอบที่เปิดใช้งาน</strong>
                    <span class="active-hint">กด “เปิดรอบใหม่” เพื่อเริ่มต้น</span>
                </template>
            </section>

            <section class="summary-card count-card">
                <span class="summary-label">ส่งแบบประเมินแล้ว</span>
                <strong class="assessment-total">
                    {{ activeSubmittedCount.toLocaleString('th-TH') }}<small>/{{ activeEligibleCount.toLocaleString('th-TH') }}</small>
                </strong>
                <span v-if="activeRound && activeRemainingCount" class="assessment-caption pending">เหลือ {{ activeRemainingCount.toLocaleString('th-TH') }} คน</span>
                <span v-else-if="activeRound" class="assessment-caption complete">ส่งแบบประเมินครบแล้ว</span>
                <span v-else class="assessment-caption">ยังไม่มีรอบที่เปิดใช้งาน</span>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="isEditorOpen"
                class="round-modal-backdrop"
                role="presentation"
                @click.self="closeEditor"
                @keydown.esc="handleEditorEscape"
            >
                <form
                    ref="editorElement"
                    class="round-editor"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="round-editor-title"
                    @submit.prevent="submit"
                    @keydown="trapEditorFocus"
                >
                    <header class="editor-heading">
                        <div>
                            <span>{{ isEditing ? 'แก้ไขข้อมูล' : 'เปิดรอบใหม่' }}</span>
                            <h2 id="round-editor-title">{{ isEditing ? form.name || 'แก้ไขรอบการประเมิน' : 'กำหนดรอบการประเมิน' }}</h2>
                            <p>ระบุชื่อและช่วงเวลาที่บุคลากรกับหัวหน้าดำเนินการได้</p>
                        </div>
                        <button type="button" class="icon-close" aria-label="ปิดหน้าต่าง" @click="closeEditor">×</button>
                    </header>

                    <div class="editor-body">
                    <div class="editor-grid">
                        <label class="field name-field">
                            <span>ชื่อรอบการประเมิน <b>*</b></span>
                            <input ref="nameInput" v-model="form.name" type="text" autocomplete="off" placeholder="เช่น รอบประเมินครั้งที่ 1">
                            <em v-if="form.errors.name">{{ form.errors.name }}</em>
                        </label>
                        <div class="date-table" role="group" aria-label="กำหนดช่วงวันที่">
                            <div class="date-head"><span class="hint date-global-hint"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>เลือกวันที่จากปฏิทิน (ค.ศ.)</span><span class="head head-start">เริ่ม</span><span class="head head-end">สิ้นสุด</span></div>
                            <div class="date-row">
                            <span class="row-label"><span class="row-name">ช่วงการประเมิน</span><b class="need-tag is-required">จำเป็นก่อนเปิดรอบ</b><span class="info-tip row-tip" tabindex="0" role="note" :data-tip="`พนักงานประเมินตนเองและส่งผลได้เฉพาะในช่วงนี้`" aria-label="พนักงานประเมินตนเองและส่งผลได้เฉพาะในช่วงนี้">ⓘ</span></span>
                            <label class="field date-cell" :class="{ 'is-error': form.self_assess_start && form.self_assess_end && form.self_assess_end < form.self_assess_start }"><span class="sr-only">เริ่มช่วงการประเมิน</span><input v-model="form.self_assess_start" aria-label="เริ่มช่วงการประเมิน" type="date" lang="en-GB"><em v-if="form.errors.self_assess_start">{{ form.errors.self_assess_start }}</em></label><span class="arrow" aria-hidden="true"></span>
                            <label class="field date-cell" :class="{ 'is-error': form.self_assess_start && form.self_assess_end && form.self_assess_end < form.self_assess_start }"><span class="sr-only">สิ้นสุดช่วงการประเมิน</span><input v-model="form.self_assess_end" aria-label="สิ้นสุดช่วงการประเมิน" type="date" lang="en-GB" :min="form.self_assess_start || undefined"><em v-if="form.self_assess_end && form.self_assess_start && form.self_assess_end < form.self_assess_start">วันสิ้นสุดต้องไม่ก่อนวันเริ่ม</em><em v-else-if="form.errors.self_assess_end">{{ form.errors.self_assess_end }}</em></label>
                            <span class="days">{{ dateDuration(form.self_assess_start, form.supervisor_assess_end || form.self_assess_end) ? `${dateDuration(form.self_assess_start, form.supervisor_assess_end || form.self_assess_end)} วัน` : '' }}</span>
                            </div>
                            <div class="date-row">
                            <span class="row-label"><span class="row-name">หัวหน้าตรวจผลได้ถึง</span><b class="need-tag is-required">จำเป็นก่อนเปิดรอบ</b><span class="info-tip row-tip" tabindex="0" role="note" :data-tip="`ผู้ประเมินตรวจและอนุมัติได้ทันทีที่พนักงานส่ง จนถึงวันนี้ ต้องไม่ก่อนวันสิ้นสุดการประเมิน`" aria-label="ผู้ประเมินตรวจและอนุมัติได้ทันทีที่พนักงานส่ง จนถึงวันนี้ ต้องไม่ก่อนวันสิ้นสุดการประเมิน">ⓘ</span></span><span class="dash-cell">—</span><span></span>
                            <label class="field date-cell" :class="{ 'is-error': form.self_assess_end && form.supervisor_assess_end && form.supervisor_assess_end < form.self_assess_end }"><span class="sr-only">หัวหน้าตรวจผลได้ถึง</span><input v-model="form.supervisor_assess_end" aria-label="หัวหน้าตรวจผลได้ถึง" type="date" lang="en-GB" :min="form.self_assess_end || form.self_assess_start || undefined"><em v-if="form.supervisor_assess_end && form.self_assess_end && form.supervisor_assess_end < form.self_assess_end">วันสิ้นสุดต้องไม่ก่อนวันเริ่ม</em><em v-else-if="form.errors.supervisor_assess_end">{{ form.errors.supervisor_assess_end }}</em></label><span class="days"></span>
                            </div>
                        </div>
                        <div class="setting-pair">
                        <label class="field copy-field">
                            <span>วิธีการประเมิน <span class="info-tip" :title="editingRound?.assessmentMethodLocked ? 'ล็อกแล้ว เนื่องจากมีบุคลากรส่งแบบประเมินในรอบนี้' : 'เปลี่ยนได้จนกว่าจะมีการส่งแบบประเมินครั้งแรก'" tabindex="0" :aria-label="editingRound?.assessmentMethodLocked ? 'ล็อกแล้ว เนื่องจากมีบุคลากรส่งแบบประเมินในรอบนี้' : 'เปลี่ยนได้จนกว่าจะมีการส่งแบบประเมินครั้งแรก'">ⓘ</span></span>
                            <select v-model="form.assessment_method" :disabled="editingRound?.assessmentMethodLocked">
                                <option value="standard">รูปแบบตามกำหนดมหาวิทยาลัย</option>
                                <option value="faculty">รูปแบบตามกำหนดคณะ</option>
                            </select>
                            <em v-if="form.errors.assessment_method">{{ form.errors.assessment_method }}</em>
                        </label>
                        <label class="field copy-field">
                            <span>ระดับความคาดหวัง <span class="info-tip" tabindex="0" :title="editingRound?.expectedLevelLocked ? 'ล็อกแล้ว เนื่องจากรอบนี้เริ่มประเมินแล้ว' : 'ตามระดับตำแหน่ง: ใช้ค่าที่ตั้งไว้ในหน้าโครงสร้าง / ค่าคงที่: ทุกคนในรอบนี้คาดหวังระดับเดียวกัน เปลี่ยนไม่ได้เมื่อเริ่มประเมินแล้ว'">ⓘ</span></span>
                            <span class="expect-row">
                                <select v-model="form.expected_level_mode" :disabled="editingRound?.expectedLevelLocked">
                                    <option value="by_level">ตามระดับตำแหน่ง</option>
                                    <option value="fixed">ค่าคงที่ทุกคน</option>
                                </select>
                                <select v-if="form.expected_level_mode === 'fixed'" v-model.number="form.expected_level_fixed" :disabled="editingRound?.expectedLevelLocked" aria-label="ระดับความคาดหวังคงที่">
                                    <option v-for="level in [1, 2, 3, 4, 5]" :key="level" :value="level">ระดับ {{ level }}</option>
                                </select>
                            </span>
                            <em v-if="form.errors.expected_level_mode">{{ form.errors.expected_level_mode }}</em>
                            <em v-else-if="form.errors.expected_level_fixed">{{ form.errors.expected_level_fixed }}</em>
                        </label>
                        <label v-if="!isEditing && rounds.length" class="field copy-field">
                            <span>คัดลอกชุดสมรรถนะจาก <span class="info-tip" tabindex="0" title="คัดลอกสมรรถนะจากรอบที่เลือก" aria-label="คัดลอกสมรรถนะจากรอบที่เลือก">ⓘ</span></span>
                            <select v-model="form.copy_from_round_id">
                                <option value="">ไม่คัดลอกข้อมูล</option>
                                <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.name }}</option>
                            </select>
                            <em v-if="form.errors.copy_from_round_id">{{ form.errors.copy_from_round_id }}</em>
                        </label>
                        </div>
                    </div>
                    <div v-if="form.errors.round" class="round-readiness-error">
                        <strong>ยังเปิดใช้รอบนี้ไม่ได้</strong>
                        <span>{{ form.errors.round }}</span>
                    </div>
                    </div>

                    <footer class="editor-footer">
                        <label class="active-toggle">
                            <input v-model="form.is_active" type="checkbox">
                            <span class="custom-check" aria-hidden="true"></span>
                            <span class="toggle-copy"><b>เปิดใช้งานรอบนี้ทันที <span class="info-tip" tabindex="0" title="รอบเดิมจะถูกปิดอัตโนมัติ" aria-label="รอบเดิมจะถูกปิดอัตโนมัติ">ⓘ</span></b><small v-if="form.is_active" class="active-warning">เมื่อเปิดรอบนี้ รอบเดิมจะถูกปิดทันที</small></span>
                        </label>
                        <div>
                            <button type="button" class="secondary-button" @click="closeEditor">ยกเลิก</button>
                            <button type="submit" class="save-button" :disabled="!canSubmitEditor" :title="!canSubmitEditor && !form.processing ? 'กรุณาระบุชื่อและตรวจสอบวันที่' : ''">
                                {{ form.processing ? 'กำลังบันทึก…' : (isEditing ? 'บันทึกการแก้ไข' : 'เปิดรอบการประเมิน') }}
                            </button>
                        </div>
                    </footer>
                </form>
            </div>
        </Teleport>

        <section class="round-table-card">
            <header><h2>รอบประเมินทั้งหมด</h2><span>{{ rounds.length }} รอบ</span></header>

            <div v-if="rounds.length" class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>รอบประเมิน</th>
                            <th>วิธีการประเมิน</th>
                            <th>ระยะเวลาประเมิน</th>
                            <th class="number-column">ประเมินแล้ว</th>
                            <th>สถานะ</th>
                            <th><span class="sr-only">การทำงาน</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="round in rounds" :key="round.id" :class="{ current: round.isActive }">
                            <td>
                                <strong>{{ round.name }}</strong>
                                <span
                                    v-if="round.readiness && !round.readiness.ready"
                                    class="readiness-badge"
                                    :title="readinessMessage(round)"
                                >ยังไม่พร้อม</span>
                            </td>
                            <td><span class="method-badge" :class="round.assessmentMethod">{{ round.assessmentMethod === 'faculty' ? 'รูปแบบตามกำหนดคณะ' : round.assessmentMethodLabel }}</span></td>
                            <td>{{ dateLabel(round.selfAssessStart) }}<span class="date-separator">ถึง {{ dateLabel(round.supervisorAssessEnd || round.selfAssessEnd) }}</span></td>
                            <td class="number-column assessment-cell">
                                {{ Number(round.submittedUserCount || 0).toLocaleString('th-TH') }}
                                <span>/ {{ Number(round.eligibleUserCount || 0).toLocaleString('th-TH') }} คน</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="round.isActive ? 'open' : 'closed'">
                                    <i v-if="round.isActive" aria-hidden="true"></i>{{ round.isActive ? 'เปิดอยู่' : 'ปิดแล้ว' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="edit-button" @click="editRound(round)">แก้ไข</button>
                                    <button
                                        v-if="round.isActive"
                                        type="button"
                                        class="close-round-button"
                                        @click="closeRound(round)"
                                    >สิ้นสุดรอบ</button>
                                    <button
                                        v-if="!round.isActive"
                                        type="button"
                                        class="activate-button"
                                        :disabled="round.readiness && !round.readiness.ready"
                                        :title="round.readiness && !round.readiness.ready ? readinessMessage(round) : 'เปิดใช้รอบนี้'"
                                        @click="activate(round)"
                                    >เปิดใช้</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="empty-state">
                <span aria-hidden="true">▣</span>
                <strong>ยังไม่มีรอบการประเมิน</strong>
                <p>สร้างรอบแรกเพื่อกำหนดช่วงเวลาประเมินของคณะ</p>
                <button type="button" class="create-button" @click="openCreate">+ เปิดรอบใหม่</button>
            </div>
        </section>
    </section>
</template>

<style scoped>
.field .expect-row { display: flex !important; gap: 8px; }
.field .expect-row select { flex: 1; min-width: 0; }
.round-page { --ink: oklch(29% .035 253); --muted: oklch(61% .035 253); --line: oklch(89% .018 253); --surface: oklch(99% .005 253); --canvas: oklch(97% .012 253); --accent: oklch(53% .145 165); --accent-dark: oklch(43% .12 165); --accent-soft: oklch(95% .045 165); width: 100%; color: var(--ink); font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; font-synthesis-weight: none; }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 24px; }.page-heading h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -.025em; }.page-heading h1 span { margin-left: 5px; color: oklch(59% .16 35); font-size: 16px; }.page-heading p { margin: 5px 0 0; color: var(--muted); font-size: 13px; }
.create-button,.save-button,.activate-button { border: 1px solid var(--accent); border-radius: 8px; background: var(--accent); color: oklch(98% .008 165); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }.create-button { min-height: 42px; padding: 9px 17px; }.create-button span { margin-right: 3px; font-size: 16px; line-height: 0; }.create-button:hover,.save-button:hover,.activate-button:hover { border-color: var(--accent-dark); background: var(--accent-dark); }
.summary-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px; margin-bottom: 20px; }.summary-card { display: grid; align-content: center; min-height: 138px; box-sizing: border-box; border: 1px solid var(--line); border-radius: 13px; background: var(--surface); padding: 24px 28px; box-shadow: 0 4px 10px oklch(32% .03 253 / .055); }.summary-label { margin-bottom: 12px; color: var(--muted); font-size: 12px; font-weight: 700; }.active-name { overflow: hidden; color: var(--accent-dark); font-size: 23px; font-weight: 800; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }.active-method { margin-top: 5px; color: var(--muted); font-size: 12px; font-weight: 600; }.active-name.empty { color: var(--ink); font-size: 16px; }.active-hint,.assessment-caption { margin-top: 5px; color: var(--muted); font-size: 12px; font-weight: 600; }.assessment-total { color: var(--accent); font-size: 32px; font-weight: 800; line-height: 1; }.assessment-total small { margin-left: 3px; color: var(--muted); font-size: 16px; font-weight: 700; }.assessment-caption.pending { color: oklch(54% .17 31); }.assessment-caption.complete { color: var(--accent-dark); }
.round-modal-backdrop { --ink: oklch(29% .035 253); --muted: oklch(61% .035 253); --line: oklch(89% .018 253); --surface: oklch(99% .005 253); --canvas: oklch(97% .012 253); --accent: oklch(53% .145 165); --accent-dark: oklch(43% .12 165); position: fixed; z-index: 1000; inset: 0; display: grid; align-items: center; overflow-y: auto; background: oklch(20% .025 253 / .52); padding: 28px; font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; font-synthesis-weight: none; }
.round-editor { width: min(960px,100%); max-height: calc(100vh - 56px); margin: auto; overflow-y: auto; border: 1px solid oklch(84% .045 165); border-radius: 14px; background: var(--surface); box-shadow: 0 24px 70px oklch(18% .025 253 / .28); animation: modal-in .18s cubic-bezier(.22,1,.36,1); }.editor-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; border-bottom: 1px solid var(--line); padding: 18px 22px; }.editor-heading span { color: var(--accent-dark); font-size: 11px; font-weight: 700; letter-spacing: .04em; }.editor-heading h2 { margin: 3px 0 0; font-size: 16px; font-weight: 800; }.editor-heading p { margin: 3px 0 0; color: var(--muted); font-size: 12px; }.icon-close { width: 34px; height: 34px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); color: var(--muted); font: inherit; font-size: 19px; cursor: pointer; }.icon-close:hover { background: var(--canvas); color: var(--ink); }
.editor-grid { display: grid; grid-template-columns: minmax(0,1fr); row-gap: 22px; padding: 20px 22px 24px; }.field { display: grid; align-content: start; gap: 6px; min-width: 0; }.field > span { font-size: 12px; font-weight: 700; }.field input,.field select { width: 100%; height: 40px; box-sizing: border-box; border: 1px solid oklch(83% .025 253); border-radius: 7px; padding: 0 10px; background: var(--surface); color: var(--ink); font: inherit; font-size: 13px; outline: 0; }.field input:hover,.field select:hover { border-color: oklch(72% .045 253); }.field input:focus-visible,.field select:focus-visible,.round-page button:focus-visible { border-color: var(--accent); outline: 0; box-shadow: 0 0 0 3px oklch(72% .09 165 / .22); }.field em { color: oklch(52% .18 28); font-size: 11px; font-style: normal; }.date-fields { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 14px; }.date-hint { grid-column: 1 / -1; margin: 0 0 -4px; color: var(--muted); font-size: 11px; }.copy-field { max-width: 460px; }.field-note { color: var(--muted); font-size: 11px; }
.editor-footer { display: flex; align-items: center; justify-content: space-between; gap: 18px; border-top: 1px solid var(--line); background: var(--canvas); padding: 13px 22px; }.active-toggle { display: flex; align-items: center; gap: 9px; cursor: pointer; }.active-toggle input { width: 16px; height: 16px; accent-color: var(--accent); }.active-toggle > span { display: grid; gap: 1px; }.active-toggle b { font-size: 12px; }.active-toggle small { color: var(--muted); font-size: 11px; }.editor-footer > div { display: flex; gap: 8px; }.secondary-button,.save-button { min-height: 38px; border-radius: 7px; padding: 8px 14px; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }.secondary-button { border: 1px solid oklch(83% .025 253); background: var(--surface); color: var(--ink); }.secondary-button:hover { background: var(--canvas); }.save-button:disabled { opacity: .55; cursor: wait; }
.round-readiness-error { display: grid; gap: 3px; margin: 0 22px 18px; border: 1px solid oklch(78% .11 29); border-radius: 8px; background: oklch(96% .035 29); padding: 10px 12px; color: oklch(46% .16 29); font-size: 12px; }.round-readiness-error strong { font-weight: 800; }
.round-table-card { overflow: hidden; border: 1px solid var(--line); border-radius: 13px; background: var(--surface); box-shadow: 0 4px 10px oklch(32% .03 253 / .055); }.round-table-card > header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; }.round-table-card > header h2 { margin: 0; font-size: 14px; font-weight: 700; }.round-table-card > header span { color: var(--muted); font-size: 12px; }.table-scroll { overflow-x: auto; border-top: 1px solid var(--line); }table { width: 100%; min-width: 0; border-collapse: collapse; }th,td { padding: 14px 16px; border-bottom: 1px solid var(--line); text-align: left; white-space: nowrap; }th { background: var(--canvas); color: var(--muted); font-size: 11px; font-weight: 700; }td { color: oklch(49% .03 253); font-size: 13px; }tbody tr:last-child td { border-bottom: 0; }tbody tr:hover { background: oklch(98% .008 253); }tbody tr.current { background: var(--accent-soft); }td strong { color: var(--ink); font-size: 13px; font-weight: 700; }.date-separator { margin: 0 3px; color: oklch(72% .025 253); }.number-column { text-align: center; }.assessment-cell { color: var(--accent-dark); font-weight: 800; }.assessment-cell span { color: var(--muted); font-weight: 600; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 700; }.status-badge.open { background: oklch(93% .06 145); color: oklch(42% .13 145); }.status-badge.closed { background: oklch(94% .012 253); color: var(--muted); }.status-badge i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }.row-actions { display: flex; justify-content: flex-end; gap: 6px; }.edit-button,.activate-button { min-height: 32px; padding: 5px 10px; }.edit-button { border: 1px solid oklch(83% .025 253); border-radius: 7px; background: var(--surface); color: var(--ink); font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }.edit-button:hover { border-color: oklch(70% .045 253); background: var(--canvas); }.activate-button { font-size: 12px; }
.method-badge { display: inline-flex; border-radius: 999px; padding: 4px 8px; background: oklch(94% .012 253); color: var(--muted); font-size: 11px; font-weight: 700; }.method-badge.faculty { background: oklch(94% .045 55); color: oklch(45% .12 45); }
.readiness-badge { display: inline-flex; margin-left: 8px; border-radius: 999px; background: oklch(94% .045 29); padding: 3px 7px; color: oklch(49% .17 29); font-size: 10px; font-weight: 700; }.activate-button:disabled { border-color: var(--line); background: var(--canvas); color: var(--muted); cursor: not-allowed; }
.close-round-button { min-height: 32px; padding: 5px 10px; border: 1px solid oklch(78% .09 29); border-radius: 7px; background: oklch(97% .025 29); color: oklch(48% .16 29); font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }.close-round-button:hover { background: oklch(94% .045 29); }
.empty-state { display: grid; justify-items: center; border-top: 1px solid var(--line); padding: 54px 20px; text-align: center; }.empty-state > span { margin-bottom: 10px; color: var(--muted); font-size: 24px; }.empty-state strong { font-size: 14px; }.empty-state p { margin: 4px 0 14px; color: var(--muted); font-size: 12px; }.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
@media (max-width: 760px) { .page-heading { align-items: flex-start; }.page-heading h1 { font-size: 22px; }.page-heading p { max-width: 34ch; line-height: 1.5; }.create-button { min-height: 40px; padding: 8px 12px; white-space: nowrap; }.summary-grid { grid-template-columns: 1fr; gap: 12px; }.summary-card { min-height: 118px; padding: 20px; }.active-name { font-size: 19px; }.editor-grid { row-gap: 16px; padding: 18px; }.date-fields { grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; }.date-hint { grid-column: 1/-1; margin-bottom: -4px; }.editor-footer { align-items: stretch; flex-direction: column; }.editor-footer > div { justify-content: flex-end; }.round-table-card > header { padding: 16px 18px; } }
@media (max-width: 430px) { .page-heading { flex-direction: column; }.create-button { width: 100%; }.round-modal-backdrop { align-items: end; padding: 12px; }.round-editor { max-height: calc(100vh - 24px); border-radius: 14px 14px 10px 10px; }.editor-footer > div { display: grid; grid-template-columns: 1fr 1fr; }.empty-state .create-button { width: auto; } }
@keyframes modal-in { from { opacity: 0; transform: translateY(10px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
@media (prefers-reduced-motion: reduce) { .round-page *,.round-modal-backdrop * { scroll-behavior: auto!important; animation: none!important; transition-duration: .01ms!important; } }
/* Assessment round editor UI refresh */
.round-modal-backdrop { --brand: var(--color-primary,#c7432b); --brand-soft: #fff1e8; align-items:center; overflow:hidden; padding:2vh 2vw; }
.round-editor { display:grid; grid-template-rows:auto minmax(0,1fr) auto; width:min(820px,96vw); max-height:96vh; overflow:hidden; border-color:var(--line); border-radius:14px; animation:none; font-size:16px; }
.editor-heading { min-height:82px; align-items:center; background:#fff; padding:16px 22px; }
.editor-heading > div > span { color:var(--brand); font-size:14px; letter-spacing:0; }
.editor-heading h2 { font-size:20px; }
.editor-heading p { color:#5f6673; font-size:14px; }
.icon-close { width:42px; height:42px; flex:none; font-size:22px; }
.editor-body { min-height:0; overflow-y:auto; overscroll-behavior:contain; }
.editor-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:0; padding:16px 22px 20px; }
.field { gap:6px; }
.field > span { display:block; margin-bottom:0; color:var(--ink); font-size:14px; font-weight:600; }
.field > span b { color:#b42318; }
.field input,.field select { height:46px; border-color:#e5dfd8; border-radius:10px; padding:9px 12px; font-size:16px; outline:none; transition:border-color .15s,box-shadow .15s; }
.round-editor .field input:focus,.round-editor .field select:focus,.round-editor .field input:focus-visible,.round-editor .field select:focus-visible { border-color:var(--brand); outline:none; box-shadow:0 0 0 3px var(--brand-soft); }
.round-editor button:focus-visible { outline:2px solid var(--brand); outline-offset:2px; box-shadow:none; }
.name-field { margin-bottom:14px; }
.name-field input { font-size:16px; }
.date-global-hint { margin:0 0 10px; color:#5f6673; font-size:14px; }
.date-group { margin:0 0 14px; border:1px solid var(--line); border-radius:12px; background:#fff; padding:16px 18px; }
.date-group h4 { display:flex; align-items:center; gap:10px; margin:0 0 12px; color:var(--ink); font-size:16px; font-weight:600; }
.date-group .num { display:grid; place-items:center; width:26px; height:26px; border-radius:50%; background:var(--brand); color:#fff; font-size:14px; }
.date-pair { display:grid; grid-template-columns:minmax(0,1fr) auto minmax(0,1fr); align-items:end; gap:12px; }
.date-pair .field > span,.supervisor-field > span { margin-bottom:6px; font-size:14px; }
.date-pair .arrow::before { display:block; padding-bottom:10px; color:#9a9086; content:'→'; }
.date-pair .field input,.supervisor-field input { font-size:16px; }
.date-pair .field.is-error input,.supervisor-field.is-error input { border-color:#b42318; }
.field em { margin-top:4px; color:#b42318; font-size:14px; font-style:normal; line-height:1.4; }
.supervisor-field { width:calc((100% - 24px) / 2); margin-top:12px; }
.hint,.date-hint { color:#5f6673; font-size:14px; }
.duration { margin:10px 0 0; color:#5f6673; font-size:14px; }
.warn-box { margin:10px 0 0; border:1px solid #f5c98f; border-radius:8px; background:#fff1e0; padding:8px 12px; color:#92400e; font-size:14px; }
.setting-pair { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:2px; }
.setting-pair .copy-field { max-width:none; min-width:0; }
.field-note { color:#5f6673; font-size:14px; line-height:1.45; }
.round-readiness-error { font-size:14px; }
.editor-footer { position:relative; z-index:1; flex:none; min-height:82px; border-top:1px solid var(--line); background:#fff; padding:13px 22px; }
.active-toggle { position:relative; min-height:44px; gap:11px; }
.active-toggle input { position:absolute; width:24px; height:24px; margin:0; opacity:0; cursor:pointer; }
.custom-check { display:grid; place-items:center; width:24px; height:24px; flex:none; border:2px solid #b8b1aa; border-radius:50%; background:#fff; color:#fff; }
.active-toggle input:checked + .custom-check { border-color:var(--brand); background:var(--brand); }
.active-toggle input:checked + .custom-check::after { width:6px; height:10px; border:solid #fff; border-width:0 2px 2px 0; content:''; transform:rotate(45deg) translate(-1px,-1px); }
.active-toggle input:focus-visible + .custom-check { outline:3px solid var(--brand-soft); }
.toggle-copy { display:grid; gap:3px; }
.active-toggle b { font-size:16px; }
.active-toggle small { color:#5f6673; font-size:14px; }
.active-toggle small.active-warning { display:inline-block; width:fit-content; border:1px solid #f5c98f; border-radius:7px; background:#fff1e0; padding:4px 8px; color:#92400e; }
.editor-footer > div { align-items:center; flex-wrap:wrap; justify-content:flex-end; gap:8px; }
.secondary-button,.save-button { min-height:44px; border-radius:10px; padding:9px 18px; font-size:16px; }
.secondary-button { border-color:#d6d0ca; }
.save-button { border:0; background:var(--brand); color:#fff; }
.save-button:hover:not(:disabled) { border:0; background:#a93423; }
.save-button:disabled { border:0; background:#e5e7eb; color:#9ca3af; cursor:not-allowed; opacity:1; }
.submit-hint { color:#5f6673; font-size:14px; }
@media (max-width:768px) {
  .round-modal-backdrop { display:block; overflow:hidden; padding:0; }
  .round-editor { width:100%; height:100dvh; max-height:100dvh; margin:0; border:0; border-radius:0; }
  .editor-heading { padding:12px 16px; }
  .editor-body { padding-bottom:8px; }
  .editor-grid { padding:14px 14px 18px; }
  .date-pair { grid-template-columns:1fr; gap:9px; }
  .date-pair .arrow { display:none; }
  .supervisor-field { width:100%; }
  .setting-pair { grid-template-columns:1fr; }
  .editor-footer { align-items:stretch; flex-direction:column; padding:12px 14px calc(12px + env(safe-area-inset-bottom)); }
  .editor-footer > div { display:grid; grid-template-columns:1fr 1fr; }
  .submit-hint { grid-column:1/-1; }
}
.round-editor { width:min(860px,96vw); max-height:92vh; }
.editor-heading { padding:16px 24px; }
.editor-body { min-height:0; overflow-y:auto; }
.editor-grid { gap:0; padding:16px 24px; }
.name-field { margin-bottom:12px; }
.name-field input { height:38px; border-radius:8px; padding:6px 10px; font-size:15px; background:#fff; }
.date-global-hint { grid-column:1/-1; margin:0 0 2px; color:#5f6673; font-size:14px; text-align:left; }
.date-table { display:grid; grid-template-columns:230px minmax(0,1fr) auto minmax(0,1fr) 64px; column-gap:10px; row-gap:8px; align-items:center; }
.date-table { padding:14px 16px; border:1px solid #e5e0da; border-radius:10px; background:#fff; }
.date-table > .head { color:#5f6673; font-size:14px; font-weight:500; }
.date-table .row-label { display:flex; flex-wrap:wrap; align-items:center; gap:4px 6px; color:var(--ink); font-size:15px; font-weight:600; }
.date-table .row-name { white-space:nowrap; }
.date-table .need-tag { padding:1px 7px; border:1px solid #d9dee6; border-radius:999px; background:#f6f7f9; color:#5f6673; font-size:11px; font-weight:700; line-height:1.5; }
.date-table .need-tag.is-required { border-color:#f3c7bd; background:#fff4f0; color:#b4472f; }
.date-table .row-tip { position:relative; margin:0; }
.date-table .row-tip::after { content:attr(data-tip); position:absolute; left:50%; bottom:calc(100% + 8px); z-index:30; width:max-content; max-width:260px; padding:8px 10px; border-radius:8px; background:#1f2937; color:#fff; font-size:12px; font-weight:500; line-height:1.5; white-space:normal; text-align:left; box-shadow:0 8px 20px rgba(15,23,42,.18); opacity:0; visibility:hidden; transform:translate(-30%, 4px); transition:opacity .12s ease, transform .12s ease; pointer-events:none; }
.date-table .row-tip:hover::after,.date-table .row-tip:focus-visible::after { opacity:1; visibility:visible; transform:translate(-30%, 0); }
.date-table .arrow::before { color:#9a9086; content:'→'; }
.date-table .days { color:#5f6673; font-size:14px; text-align:right; white-space:nowrap; }
.date-table .divider { grid-column:1/-1; height:0; margin:1px 0; border-top:1px solid #f0ece7; }
.date-table .field { gap:2px; }
.date-table .field > span { margin:0; }
.date-table .field input { width:100%; height:38px; border:1px solid #e5dfd8; border-radius:8px; padding:6px 10px; background:#fff; font-size:15px; }
.date-table .field input:focus { border-color:var(--brand); outline:none; box-shadow:0 0 0 3px var(--brand-soft); }
.date-table .field.is-error input { border-color:#b42318; }
.date-table .field em { margin-top:1px; font-size:14px; line-height:1.25; }
.date-table .dash-cell { color:#9a9086; font-size:15px; text-align:center; }
.setting-pair { grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:12px; }
.setting-pair .field { gap:5px; }
.setting-pair .field > span { font-size:15px; }
.setting-pair .field select { height:38px; border-radius:8px; padding:6px 10px; background:#fff; font-size:15px; }
.info-tip { display:inline-flex; align-items:center; justify-content:center; width:17px; height:17px; margin:0 0 0 3px; border:0; border-radius:0; color:#5f6673; font-size:13px; line-height:1; vertical-align:baseline; cursor:help; }
.field > span .info-tip { vertical-align:baseline; }
.editor-footer { min-height:62px; padding:10px 24px; }
.active-toggle { min-height:40px; }
.active-toggle b { font-size:15px; }
.active-toggle small.active-warning { margin-top:2px; font-size:14px; }
.secondary-button,.save-button { min-height:38px; padding:7px 14px; font-size:15px; }
.submit-hint { display:none; }
@media (max-width:768px) {
  .editor-heading { padding:12px 16px; }
  .editor-grid { padding:12px 16px; }
  .date-table { grid-template-columns:minmax(0,1fr) minmax(0,1fr); column-gap:10px; row-gap:6px; }
  .date-table > .head { display:none; }
  .date-table > .head + span { display:none; }
  .date-table .row-label { grid-column:1/-1; margin-top:3px; font-size:14px; }
  .date-table .arrow,.date-table .days { display:none; }
  .date-table .divider { margin:3px 0; }
  .date-table .dash-cell { display:none; }
  .date-table .date-global-hint { grid-column:1/-1; display:block; }
  .setting-pair { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-top:10px; }
  .editor-footer { align-items:center; flex-direction:row; gap:8px; padding:9px 12px calc(9px + env(safe-area-inset-bottom)); }
  .editor-footer > div { display:flex; gap:6px; }
  .secondary-button,.save-button { padding:7px 9px; font-size:14px; }
  .active-toggle { gap:7px; }
  .active-toggle b { font-size:14px; }
}
@media (max-width:430px) {
  .round-modal-backdrop { padding:0; }
  .round-editor { width:100%; height:100dvh; max-height:100dvh; border-radius:0; }
  .setting-pair { grid-template-columns:1fr; }
}

/* Compact editor, card rows (matches the approved mock-up) */
.round-editor { width:min(780px,96vw); font-size:14px; }
.editor-heading { min-height:0; padding:14px 22px 12px; align-items:flex-start; background:linear-gradient(#fff,#fbfaf9); }
.editor-heading > div > span { display:inline-flex; align-items:center; gap:6px; color:var(--brand); font-size:12px; font-weight:700; }
.editor-heading > div > span::before { width:6px; height:6px; border-radius:50%; background:var(--brand); content:''; }
.editor-heading h2 { margin:2px 0 0; font-size:17px; font-weight:800; }
.editor-heading p { margin:1px 0 0; font-size:12.5px; }
.icon-close { width:28px; height:28px; border:0; background:transparent; font-size:20px; }
.editor-grid { padding:14px 22px 14px; }
.name-field { margin-bottom:12px; }
.field > span { font-size:13px; font-weight:700; }
.name-field input,.date-table .field input,.setting-pair .field select { height:34px; border-radius:8px; padding:4px 10px; font-size:14px; }
.date-table { display:block; padding:10px 12px 12px; border:1px solid #ece7e1; border-radius:12px; background:#faf8f6; }
.date-head,.date-row { display:grid; grid-template-columns:262px minmax(0,1fr) 14px minmax(0,1fr) 52px; align-items:center; column-gap:8px; }
.date-head { padding:0 12px 6px; }
.date-head .date-global-hint { grid-column:1; grid-row:1; display:flex; align-items:center; gap:6px; margin:0; font-size:12.5px; color:#6b7280; }
.date-head .head { color:#6b7280; font-size:12.5px; font-weight:600; text-align:center; }
.date-head .head-start { grid-column:2; grid-row:1; }
.date-head .head-end { grid-column:4; grid-row:1; }
.date-row { margin-top:8px; padding:9px 12px; border:1px solid #ece7e1; border-radius:10px; background:#fff; box-shadow:0 1px 2px rgb(31 41 55 / .04); }
.date-row:first-of-type { margin-top:0; }
.date-table .row-label { flex-wrap:nowrap; gap:4px 6px; font-size:14px; }
.date-table .row-label .info-tip { flex:none; }
.date-table .need-tag { border-color:#c9d8ee; background:#eef4fc; color:#2f5f9e; }
.date-table .need-tag.is-required { border-color:#f3c7bd; background:#fff4f0; color:#b4472f; }
.date-table .days { justify-self:end; padding:1px 8px; border-radius:999px; background:#f1f2f4; color:#4b5563; font-size:12px; font-weight:600; }
.date-table .days:empty { display:none; }
.setting-pair { margin-top:12px; gap:12px; }
.setting-pair .field > span { font-size:13px; }
.field .expect-row { gap:6px; }
.editor-footer { min-height:0; padding:10px 22px; background:#fbfaf9; }
.active-toggle .toggle-copy { display:flex !important; flex-wrap:nowrap; align-items:center; gap:4px 10px; }
.active-toggle b { font-size:14px; }
.active-toggle small.active-warning { white-space:nowrap; display:inline-flex; align-items:center; gap:4px; margin:0; padding:2px 8px; font-size:12px; }
.active-toggle small.active-warning::before { content:'\26A0'; font-size:11px; }
.secondary-button,.save-button { min-height:36px; padding:6px 16px; font-size:14px; border-radius:8px; }
.round-editor { font-size:13px; }
.editor-heading h2 { font-size:16px; }
.editor-heading p,.editor-heading > div > span { font-size:12px; }
.field > span,.setting-pair .field > span { font-size:12px; }
.name-field input,.date-table .field input,.setting-pair .field select { height:32px; font-size:13px; }
.date-head .date-global-hint,.date-head .head { font-size:12px; }
.date-table .row-label { font-size:13px; }
.date-table .days { font-size:11px; }
.date-table .need-tag { font-size:10.5px; }
.active-toggle b { font-size:13px; }
.active-toggle small.active-warning { font-size:11px; }
.secondary-button,.save-button { min-height:34px; font-size:13px; }
.round-editor .setting-pair .field select { font-weight:400; }
@media (max-width:768px) {
  .date-head { display:none; }
  .date-row { grid-template-columns:minmax(0,1fr) minmax(0,1fr); row-gap:6px; }
  .date-row .row-label { grid-column:1/-1; }
  .date-row .arrow,.date-row .days,.date-row .dash-cell { display:none; }
  .date-row > span:empty { display:none; }
}
.round-table-card th,.round-table-card td { padding: 12px 8px; white-space: nowrap; font-size: 13px; vertical-align: middle; }
.round-table-card th,.round-table-card th:first-child,.round-table-card td:first-child,.round-table-card td:nth-child(2) { white-space: normal; }
.round-table-card .method-badge { white-space: nowrap; }
.round-table-card td:first-child strong { display: block; min-width: 9em; }
.round-table-card .date-separator { display: inline; margin: 0 0 0 6px; color: var(--muted); }
</style>
