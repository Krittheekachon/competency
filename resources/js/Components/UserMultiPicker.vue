<script setup>
import { computed, ref } from 'vue';

// Searchable checkbox list for picking several users; the parent puts its add button in the #actions slot.
// options: [{ value, label, displayName?, meta?: [{ label, value }], org?, p? }] · v-model: array of selected values
const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'ค้นหาชื่อ ตำแหน่ง หรือหน่วยงาน' },
    emptyText: { type: String, default: 'ไม่มีผู้ใช้ให้เลือก' },
});
const emit = defineEmits(['update:modelValue']);

const search = ref('');
const selected = computed(() => new Set(props.modelValue));
const metaOf = (person) => (person.meta?.length
    ? person.meta
    : [{ label: 'สังกัด', value: person.org || '-' }, { label: 'ตำแหน่ง', value: person.p || '-' }]);
const filtered = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.options;
    return props.options.filter((person) => [person.label, person.org, ...metaOf(person).map((item) => item.value)]
        .filter(Boolean).join(' ').toLowerCase().includes(keyword));
});
const allFilteredSelected = computed(() => filtered.value.length > 0 && filtered.value.every((person) => selected.value.has(person.value)));
const someFilteredSelected = computed(() => !allFilteredSelected.value && filtered.value.some((person) => selected.value.has(person.value)));
const initialOf = (person) => String(person.name || person.displayName || person.label || '?').trim().slice(0, 1);

const toggle = (value) => {
    emit('update:modelValue', selected.value.has(value)
        ? props.modelValue.filter((item) => item !== value)
        : [...props.modelValue, value]);
};
const toggleAllFiltered = () => {
    const filteredValues = new Set(filtered.value.map((person) => person.value));
    emit('update:modelValue', allFilteredSelected.value
        ? props.modelValue.filter((item) => !filteredValues.has(item))
        : [...new Set([...props.modelValue, ...filteredValues])]);
};
const clear = () => emit('update:modelValue', []);
</script>

<template>
    <div class="ump">
        <label class="ump-search">
            <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="9" cy="9" r="6" /><path d="m13.5 13.5 4 4" /></svg>
            <input v-model="search" type="search" :placeholder="placeholder" aria-label="ค้นหาผู้ใช้" />
        </label>

        <div class="ump-panel">
            <label class="ump-head">
                <input
                    type="checkbox"
                    :checked="allFilteredSelected"
                    :indeterminate.prop="someFilteredSelected"
                    :disabled="!filtered.length"
                    @change="toggleAllFiltered"
                />
                <span>เลือกทั้งหมดที่แสดง</span>
                <span class="ump-head-count">{{ filtered.length }} คน</span>
            </label>

            <div class="ump-list" role="group" aria-label="รายชื่อผู้ใช้">
                <label v-for="person in filtered" :key="`ump-${person.value}`" class="ump-row" :class="{ active: selected.has(person.value) }">
                    <input type="checkbox" :checked="selected.has(person.value)" @change="toggle(person.value)" />
                    <span class="ump-avatar" aria-hidden="true">{{ initialOf(person) }}</span>
                    <span class="ump-body">
                        <span class="ump-name">{{ person.displayName || person.label }}</span>
                        <span class="ump-tags">
                            <span v-for="item in metaOf(person)" :key="item.label" class="ump-tag">
                                <em>{{ item.label }}</em>{{ item.value }}
                            </span>
                        </span>
                    </span>
                </label>
                <p v-if="!filtered.length" class="ump-empty">{{ search ? 'ไม่พบผู้ใช้ที่ค้นหา' : emptyText }}</p>
            </div>

            <div class="ump-footer">
                <span class="ump-selected">
                    เลือกแล้ว <strong>{{ modelValue.length }}</strong> คน
                    <button v-if="modelValue.length" type="button" class="ump-clear" @click="clear">ล้าง</button>
                </span>
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.ump { display: grid; gap: 10px; min-width: 0; font-size: 14px; }

.ump-search { position: relative; display: block; }
.ump-search svg { position: absolute; top: 50%; left: 12px; width: 16px; height: 16px; transform: translateY(-50%); fill: none; stroke: #8a909a; stroke-width: 1.8; stroke-linecap: round; pointer-events: none; }
.ump-search input { width: 100%; height: 40px; box-sizing: border-box; padding: 0 12px 0 36px; border: 1px solid var(--color-border, #e5e7eb); border-radius: 8px; background: #fff; color: inherit; font: inherit; outline: 0; }
.ump-search input:focus-visible { border-color: var(--color-primary, #2563eb); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #2563eb) 18%, transparent); }

.ump-panel { overflow: hidden; border: 1px solid var(--color-border, #e5e7eb); border-radius: 10px; background: #fff; }
.ump-head { display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-bottom: 1px solid var(--color-border, #e5e7eb); background: #fafafa; color: #4b5563; font-size: 13px; cursor: pointer; }
.ump-head-count { margin-left: auto; color: #8a909a; }

.ump-list { max-height: 300px; overflow-y: auto; overscroll-behavior: contain; }
.ump-row { position: relative; display: grid; grid-template-columns: 18px 32px minmax(0, 1fr); align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid #f1f1f3; cursor: pointer; transition: background-color .12s ease; }
.ump-row:last-of-type { border-bottom: 0; }
.ump-row:hover { background: #fafafa; }
.ump-row.active { background: var(--color-primary-soft, #eff6ff); }
.ump-row.active::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 3px; background: var(--color-primary, #2563eb); }

.ump input[type='checkbox'] { width: 18px; height: 18px; margin: 0; accent-color: var(--color-primary, #2563eb); cursor: pointer; }
.ump-avatar { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: #1f2937; color: #fff; font-size: 13px; font-weight: 700; }
.ump-row.active .ump-avatar { background: var(--color-primary, #2563eb); }

.ump-body { display: grid; gap: 5px; min-width: 0; }
.ump-name { overflow: hidden; color: #111827; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.ump-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.ump-tag { display: inline-flex; align-items: baseline; gap: 6px; max-width: 100%; padding: 2px 8px; border-radius: 6px; background: #f3f4f6; color: #374151; font-size: 12.5px; line-height: 1.6; overflow-wrap: anywhere; }
.ump-row.active .ump-tag { background: #fff; }
.ump-tag em { color: #8a909a; font-style: normal; white-space: nowrap; }

.ump-empty { margin: 0; padding: 24px 14px; color: #8a909a; text-align: center; }

.ump-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; padding: 10px 14px; border-top: 1px solid var(--color-border, #e5e7eb); background: #fafafa; }
.ump-selected { display: inline-flex; align-items: center; gap: 6px; color: #4b5563; font-size: 13px; }
.ump-selected strong { color: #111827; }
.ump-clear { padding: 0 0 0 4px; border: 0; background: none; color: var(--color-primary, #2563eb); cursor: pointer; font: inherit; }
.ump-clear:hover { text-decoration: underline; }
.ump-footer :slotted(.btn) { min-height: 36px; }

@media (max-width: 600px) {
    .ump-row { grid-template-columns: 18px minmax(0, 1fr); }
    .ump-avatar { display: none; }
    .ump-footer :slotted(.btn) { width: 100%; }
}
</style>
