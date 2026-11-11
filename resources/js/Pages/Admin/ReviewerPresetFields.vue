<script setup>
import UserMultiPicker from '../../Components/UserMultiPicker.vue';
// Extracted from the existing Admin reviewer-template modal, shared with the mock prototype.
defineProps(['hideUserAssignment', 'assessmentTemplateForm', 'activeReviewerTemplateTitle', 'activeReviewerTemplateType', 'templateReviewerOptions', 'selectedTemplateReviewerIds', 'templatePersonLabel', 'addTemplateReviewer', 'removeTemplateReviewer', 'isEditingAssessmentTemplate', 'templateAssignmentUserOptions', 'addTemplateAssignmentUser', 'selectedTemplateUserIds', 'removeTemplateAssignmentUser', 'assessmentTemplateError']);
</script>
<template>                <div class="assessment-template-builder">
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

                    <div v-if="!isEditingAssessmentTemplate && !hideUserAssignment" class="template-builder-section">
                        <div class="template-builder-head">
                            <div>
                                <h4>ผู้ใช้ที่จะใช้ลำดับนี้</h4>
                                <p>เลือกได้ภายหลัง หากต้องการสร้างลำดับเก็บไว้ก่อน</p>
                            </div>
                        </div>

                        <UserMultiPicker v-model="assessmentTemplateForm.user_picks" :options="templateAssignmentUserOptions">
                            <template #actions>
                                <button class="btn btn-p" type="button" :disabled="!assessmentTemplateForm.user_picks?.length" @click="addTemplateAssignmentUser">
                                    + เพิ่มผู้ใช้{{ assessmentTemplateForm.user_picks?.length ? ` (${assessmentTemplateForm.user_picks.length})` : '' }}
                                </button>
                            </template>
                        </UserMultiPicker>

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

                    <p v-if="assessmentTemplateError" class="reviewer-template-form-error" role="alert">
                        {{ assessmentTemplateError }}
                    </p>
                </div></template>
<style scoped>
.assessment-template-builder{display:grid;gap:14px;font-size:16px}.template-builder-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.template-builder-section{display:grid;gap:12px;padding:16px;border:1px solid var(--color-border);border-radius:8px;background:#fff}.template-builder-head h4{margin:0;font-size:16px}.template-builder-head p{margin:4px 0 0;color:#5f6673;font-size:14px}.template-picker-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px}.template-chain-list{display:grid;gap:8px}.template-chain-item{display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:10px;padding:10px;border:1px solid var(--color-border);border-radius:8px;background:#fbfdff}.template-chain-no{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:var(--color-primary-soft);color:var(--color-primary)}.template-user-list{display:flex;flex-wrap:wrap;gap:8px}.template-user-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--color-border);border-radius:999px}.template-user-chip button{background:none;border:0;color:#b42318;cursor:pointer}.reviewer-template-empty{padding:14px;color:#5f6673;font-size:14px;background:#faf8f5;border-radius:8px}.reviewer-template-form-error{color:#b42318;font-size:14px}.lbl{font-size:14px}.inp,.sel,.btn{font-size:14px;min-height:40px}@media(max-width:768px){.template-builder-grid,.template-picker-row{grid-template-columns:1fr}}
</style>