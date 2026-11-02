<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    rounds: { type: Array, default: () => [] },
});

const selectedMethods = reactive({});
const savingId = ref(null);
const errors = reactive({});

const syncSelections = (rounds) => {
    for (const round of rounds || []) {
        selectedMethods[round.id] = round.assessmentMethod || 'standard';
    }
};

watch(() => props.rounds, syncSelections, { immediate: true, deep: true });

const save = (round) => {
    if (round.assessmentMethodLocked || savingId.value !== null) return;

    savingId.value = round.id;
    errors[round.id] = '';
    router.patch(route('admin.assessment-rounds.method.update', round.id), {
        assessment_method: selectedMethods[round.id],
    }, {
        preserveScroll: true,
        onError: (responseErrors) => {
            errors[round.id] = responseErrors.assessment_method || 'ไม่สามารถบันทึกวิธีการประเมินได้';
        },
        onFinish: () => {
            savingId.value = null;
        },
    });
};
</script>

<template>
    <section class="method-page">
        <header class="method-heading">
            <div>
                <h1>กำหนดวิธีการประเมิน</h1>
                <p>เลือกวิธีที่ใช้ในแต่ละรอบก่อนบุคลากรส่งแบบประเมิน</p>
            </div>
        </header>

        <div v-if="rounds.length" class="method-list">
            <article v-for="round in rounds" :key="round.id" class="method-card">
                <div class="round-info">
                    <div class="round-title-row">
                        <h2>{{ round.name }}</h2>
                        <span v-if="round.isActive" class="active-badge">กำลังเปิดใช้</span>
                    </div>
                    <p>ปี {{ round.year }} · ส่งแล้ว {{ Number(round.submittedUserCount || 0).toLocaleString('th-TH') }} คน</p>
                </div>

                <div class="method-control">
                    <label :for="`assessment-method-${round.id}`">วิธีการประเมิน</label>
                    <div class="control-row">
                        <select
                            :id="`assessment-method-${round.id}`"
                            v-model="selectedMethods[round.id]"
                            class="sel"
                            :disabled="round.assessmentMethodLocked || savingId === round.id"
                        >
                            <option value="standard">วิธีประเมินเดิม</option>
                            <option value="faculty">วิธีคณะวิศวกรรมศาสตร์</option>
                        </select>
                        <button
                            type="button"
                            class="btn btn-p"
                            :disabled="round.assessmentMethodLocked || savingId !== null || selectedMethods[round.id] === round.assessmentMethod"
                            @click="save(round)"
                        >
                            {{ savingId === round.id ? 'กำลังบันทึก…' : 'บันทึก' }}
                        </button>
                    </div>
                    <p v-if="round.assessmentMethodLocked" class="locked-note">
                        ล็อกแล้ว เนื่องจากมีบุคลากรส่งแบบประเมินในรอบนี้
                    </p>
                    <p v-else class="editable-note">เปลี่ยนได้จนกว่าจะมีการส่งแบบประเมินครั้งแรก</p>
                    <p v-if="errors[round.id]" class="error-note">{{ errors[round.id] }}</p>
                </div>
            </article>
        </div>

        <div v-else class="empty-state">ยังไม่มีรอบการประเมิน ให้ HR สร้างรอบก่อนกำหนดวิธี</div>
    </section>
</template>

<style scoped>
.method-page { display: grid; gap: 18px; }
.method-heading h1 { margin: 0; font-size: 24px; color: #1f2937; }
.method-heading p { margin: 6px 0 0; color: #6b7280; }
.method-list { display: grid; gap: 12px; }
.method-card { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(320px, 1.25fr); gap: 24px; align-items: center; padding: 20px; border: 1px solid #e5e7eb; border-radius: 14px; background: #fff; }
.round-title-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.round-info h2 { margin: 0; font-size: 17px; color: #1f2937; }
.round-info p { margin: 7px 0 0; color: #6b7280; font-size: 13px; }
.active-badge { padding: 3px 9px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; }
.method-control label { display: block; margin-bottom: 7px; font-size: 13px; font-weight: 700; color: #374151; }
.control-row { display: flex; gap: 10px; }
.control-row .sel { flex: 1; min-width: 0; }
.control-row .btn { white-space: nowrap; }
.locked-note, .editable-note, .error-note { margin: 7px 0 0; font-size: 12px; }
.locked-note { color: #92400e; }
.editable-note { color: #6b7280; }
.error-note { color: #b91c1c; }
.empty-state { padding: 32px; border: 1px dashed #d1d5db; border-radius: 14px; background: #fff; text-align: center; color: #6b7280; }
@media (max-width: 900px) {
    .method-card { grid-template-columns: 1fr; gap: 16px; }
    .control-row { align-items: stretch; }
}
</style>
