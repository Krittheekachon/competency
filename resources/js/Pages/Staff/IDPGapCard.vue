<template><div class="idp-gap" style="margin-bottom: 20px; scroll-margin-top: 110px; overflow: hidden"><!-- ── Header ─────────────────────────────────────────────────────────── --><div class="idp-gap-h" style="padding: 14px 18px"><div style="display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap"><span :class="`b ${gap.pri === 'high' ? 'br' : 'by'}`">
{{ gap.pri === 'high' ? 'เร่งด่วน' : 'ต้องพัฒนา' }}
 </span><span :class="gapTagClass">{{ gap.t }}</span><span class="fw8 fs14">{{ gap.n }}</span><span class="muted fs12" style="margin-left: 4px">
คาดหวัง {{ gap.exp }} ปัจจุบัน {{ gap.actual }}
 </span></div><div style="display: flex; align-items: center; gap: 8px"><button
v-if="gap.status === 'draft'"
class="btn btn-t btn-sm"
@click="emit('submit-gap', gap.cd)"
>
ส่งให้หัวหน้า
 </button><span v-else :class="`b ${statusMeta.cls}`">{{ statusMeta.badge }}</span></div></div><!-- ── Rejected banner ────────────────────────────────────────────────── --><div
v-if="gap.status === 'rejected'"
style="background: #FEF2F2; border-bottom: 1px solid #FECACA; padding: 12px 18px; display: flex; gap: 14px; align-items: flex-start"
><div style="font-size: 20px; flex-shrink: 0">!</div><div style="flex: 1"><div class="fw7 fs13" style="color: var(--red); margin-bottom: 3px">
แผนนี้ไม่ผ่านการอนุมัติ
 </div><div class="fs12" style="color: #991B1B; margin-bottom: 6px"><span class="fw6">{{ gap.rejectedBy }}</span> {{ gap.rejectedDate }}
 </div><div
class="fs12"
style="background: #fff; border: 1px solid #FECACA; border-radius: 6px; padding: 8px 10px; color: var(--text2)"
>
{{ gap.rejectComment }}
 </div></div><button class="btn btn-r btn-sm" style="flex-shrink: 0">แก้ไขแผน</button></div><!-- ── Goal textarea ──────────────────────────────────────────────────── --><div
style="padding: 14px 18px; border-bottom: 1px solid var(--blue-md); background: #fff"
><label class="lbl">
เป้าหมายการพัฒนา <span style="color: var(--red)">*</span></label><textarea
class="ta"
style="min-height: 52px; margin-top: 5px"
placeholder="ระบุเป้าหมายการพัฒนา..."
:value="goal"
@input="e => emit('update:goal', (e.target as HTMLTextAreaElement).value)"
/></div><!-- ── Activities list ────────────────────────────────────────────────── --><div style="background: #fff"><div
v-for="(act, aIdx) in activities"
:key="aIdx"
style="padding: 12px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px"
><div
style="width: 8px; height: 8px; border-radius: 50%; background: var(--blue); flex-shrink: 0"
/><div style="flex: 1"><div class="fw6 fs13">{{ act.t }}</div><div class="muted fs11">{{ act.m }} ครบ {{ act.due }}</div></div><span :class="`b ${act.stC}`">{{ act.st }}</span></div><div
v-if="activities.length === 0"
style="padding: 24px; text-align: center; color: var(--text3); font-size: 13px; border-bottom: 1px solid var(--border)"
><div style="font-size: 28px; margin-bottom: 8px">+</div><div class="fw6 fs13" style="color: var(--text2)">ยังไม่มีกิจกรรมพัฒนา</div></div></div><!-- ── Add activity toggle ────────────────────────────────────────────── --><div
style="padding: 12px 18px; border-top: 1px solid var(--border); background: #fff"
><button class="btn btn-s btn-sm" type="button" @click="emit('toggle-form')">
{{ isFormOpen ? '−' : '+' }} เพิ่มกิจกรรม
 </button></div><!-- ── Activity form (child component) ───────────────────────────────── --><IDPActivityForm
v-if="isFormOpen"
:form="activityForm"
:methods="methods"
@update:form="patch => emit('update:activity-form', patch)"
@cancel="emit('toggle-form')"
@save-draft="emit('save-draft-activity')"
@add="emit('add-activity')"
/></div></template><script setup lang="ts">
import { computed } from 'vue'
import IDPActivityForm from './IDPForm.vue'
import type { LearningMethodOption, ActivityForm } from './IDPForm.vue'

// ── Types ────────────────────────────────────────────────────────────────────

export type Gap = {
cd: string
n: string
t: string
pri: string
exp: number
actual: number
status: 'draft' | 'submitted' | 'approved' | 'rejected'
rejectedBy?: string
rejectedDate?: string
rejectComment?: string
}

export type Activity = {
ic: string
bg: string
t: string
m: string
due: string
weight: string
note: string
st: string
stC: string
result: string | null
logs: any[]
}

// ── Props & Emits ─────────────────────────────────────────────────────────────

const props = defineProps<{
gap: Gap
activities: Activity[]
goal: string
isFormOpen: boolean
activityForm: ActivityForm
methods: LearningMethodOption[]
}>()

const emit = defineEmits<{
'submit-gap': [cd: string]
'toggle-form': []
'add-activity': []
'save-draft-activity': []
'update:goal': [val: string]
'update:activity-form': [patch: Partial<ActivityForm>]
}>()

// ── Computed ──────────────────────────────────────────────────────────────────

const gapTagClass = computed(() => {
const t = props.gap.t
if (t === 'CC') return 'tag-cc'
if (t === 'MC') return 'tag-mc'
if (t === 'FC1') return 'tag-fc1'
if (t === 'FC2') return 'tag-fc2'
return 'tag-fc'
})

const statusMeta = computed(() => {
if (props.gap.status === 'submitted') return { badge: 'ส่งแล้ว รออนุมัติ', cls: 'by' }
if (props.gap.status === 'rejected') return { badge: 'ไม่ผ่าน', cls: 'br' }
return { badge: '', cls: '' }
})
</script>
