<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ modelValue: string; periods: any[]; label?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const open = ref(false);
const activeIndex = ref(0);
const selectedLabel = computed(() => {
  const period = props.periods.find(item => item.id === props.modelValue);
  return period ? (period.id === 'all' ? 'ทุกรอบ' : `${period.label} (${period.range})`) : '';
});
function toggle() { open.value = !open.value; activeIndex.value = Math.max(0, props.periods.findIndex(item => item.id === props.modelValue)); }
function choose(index: number) { emit('update:modelValue', props.periods[index].id); open.value = false; trigger.value?.focus(); }
function onKeydown(event: KeyboardEvent) {
  if (!open.value && ['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) { event.preventDefault(); toggle(); return; }
  if (!open.value) return;
  if (event.key === 'ArrowDown') { event.preventDefault(); activeIndex.value = (activeIndex.value + 1) % props.periods.length; }
  else if (event.key === 'ArrowUp') { event.preventDefault(); activeIndex.value = (activeIndex.value - 1 + props.periods.length) % props.periods.length; }
  else if (event.key === 'Enter') { event.preventDefault(); choose(activeIndex.value); }
  else if (event.key === 'Escape') { event.preventDefault(); open.value = false; trigger.value?.focus(); }
}
function onOutside(event: PointerEvent) { if (open.value && !root.value?.contains(event.target as Node)) open.value = false; }
onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>

<template>
  <div ref="root" class="round-control">
    <span>{{ label || 'รอบการประเมิน' }}</span>
    <button ref="trigger" class="round-trigger" type="button" aria-haspopup="listbox" :aria-expanded="open" @click="toggle" @keydown="onKeydown"><span>{{ selectedLabel }}</span><span class="round-chevron" aria-hidden="true"></span></button>
    <div v-if="open" class="round-options" role="listbox" :aria-label="label || 'รอบการประเมิน'"><button v-for="(period, index) in periods" :key="period.id" type="button" role="option" class="round-option" :class="{ selected: modelValue === period.id, highlighted: activeIndex === index }" :aria-selected="modelValue === period.id" @mouseenter="activeIndex = index" @click="choose(index)"><span class="option-label">{{ period.id === 'all' ? 'ทุกรอบ' : `${period.label} (${period.range})` }}</span><span v-if="period.current" class="option-current">ปัจจุบัน</span><span v-if="modelValue === period.id" class="option-check" aria-hidden="true">✓</span></button></div>
  </div>
</template>

<style scoped>
.round-control { position:relative; display:grid; flex:0 1 auto; width:min(420px,100%); gap:6px; color:#495b53; font-size:14px; font-weight:800; }
.round-trigger { display:flex; align-items:center; justify-content:space-between; gap:16px; width:min(420px,100%); min-width:min(320px,100%); min-height:46px; border:1px solid #cbd8d2; border-radius:7px; background:#fff; padding:9px 13px; color:var(--history-ink,#263a32); font:inherit; font-size:16px; font-weight:600; text-align:left; cursor:pointer; }
.round-trigger > span:first-child { min-width:0; overflow-wrap:anywhere; }.round-chevron { width:9px; height:9px; flex:none; margin:0 2px 4px 0; transform:rotate(45deg); border-right:2px solid #586960; border-bottom:2px solid #586960; }
.round-options { position:absolute; z-index:30; top:calc(100% + 5px); left:0; width:100%; max-height:320px; overflow-y:auto; overflow-x:hidden; border:1px solid #d5ded9; border-radius:10px; background:#fff; box-shadow:0 10px 24px rgba(30,55,44,.16); padding:4px; }
.round-option { display:flex; align-items:flex-start; gap:9px; width:100%; min-height:44px; border:0; border-radius:6px; background:#fff; padding:10px 14px; color:#263a32; font:inherit; font-size:16px; font-weight:500; text-align:left; cursor:pointer; }.round-option:hover,.round-option.highlighted { background:#faf7f5; }.round-option.selected { background:var(--brand-soft,var(--color-primary-soft,#edf5f1)); }.option-label { flex:1; min-width:0; overflow-wrap:anywhere; line-height:1.45; }.option-current { flex:none; border-radius:999px; background:#edf8f0; padding:2px 7px; color:#187243; font-size:14px; white-space:nowrap; }.option-check { flex:none; color:var(--brand,var(--color-primary,#37866e)); font-size:18px; font-weight:900; line-height:1.1; }
</style>
