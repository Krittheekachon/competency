<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ node: any; nodes: any[]; collapsed: Set<string>; selectedKey: string }>();
const emit = defineEmits(['select', 'toggle']);
const children = computed(() => props.nodes.filter(n => n.parentKey === props.node.key));
const labels: Record<string, string> = {workline: 'สายงาน', family: 'ภาควิชา / กลุ่มงาน', department: 'ฝ่าย', work: 'งาน', unit: 'หน่วย'};
</script>

<template>
  <li class="org-branch">
    <article class="org-node" :class="[`org-${node.type}`, { 'org-selected': selectedKey === node.key }]" :data-node-key="node.key">
      <button class="org-node-content" :aria-pressed="selectedKey === node.key" @click="emit('select', node.key)">
        <span class="org-node-level">{{ labels[node.type] }}</span>
        <strong>{{ node.name }}</strong>
        <span class="org-head">หัวหน้า: {{ node.head?.name || 'ยังไม่ได้กำหนด' }}</span>
        <span class="org-member-count">บุคลากร {{ node.members.length }} คน · ดูรายละเอียด</span>
      </button>
      <button v-if="children.length" class="org-fold" :aria-expanded="!collapsed.has(node.key)" :aria-label="`${collapsed.has(node.key) ? 'ขยาย' : 'พับ'} ${node.name}`" @click="emit('toggle', node.key)">{{ collapsed.has(node.key) ? '+' : '−' }} {{ children.length }} หน่วยงานย่อย</button>
    </article>
    <ul v-if="children.length && !collapsed.has(node.key)" class="org-children">
      <OrganizationChartBranch v-for="child in children" :key="child.key" :node="child" :nodes="nodes" :collapsed="collapsed" :selected-key="selectedKey" @select="emit('select', $event)" @toggle="emit('toggle', $event)" />
    </ul>
  </li>
</template>
