<script setup lang="ts">
withDefaults(defineProps<{
  groups?: Array<{ level: number; indicators: Array<{ code: string; description: string }> }>;
}>(), { groups: () => [] });
</script>

<template>
  <section class="missing-indicator-details" aria-label="พฤติกรรมบ่งชี้ที่ยังไม่ผ่าน">
    <h4>พฤติกรรมบ่งชี้ที่ยังไม่ผ่าน</h4>
    <template v-for="(group, groupIndex) in groups" :key="`${group.level}-${groupIndex}`">
      <ul v-if="group.indicators.length">
        <li v-for="(indicator, index) in group.indicators" :key="`${indicator.code}-${index}`">
          <span class="indicator-ref">ระดับ {{ group.level }} · ข้อ {{ indicator.code }}</span>
          <span class="indicator-description">{{ indicator.description }}</span>
        </li>
      </ul>
    </template>
    <p v-if="!groups.some(group => group.indicators.length)">ไม่มีรายละเอียดพฤติกรรมบ่งชี้ในผลประเมินนี้</p>
  </section>
</template>

<style scoped>
.missing-indicator-details { padding: 14px 20px 18px; border-top: 1px solid #ece8e3; background: #fcfaf8; }
h4 { margin: 0 0 12px; color: #625d58; font-size: 14px; font-weight: 600; }
ul { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
ul + ul { margin-top: 10px; }
li { display: flex; align-items: baseline; gap: 12px; line-height: 1.6; }
.indicator-ref { flex: 0 0 auto; padding: 2px 8px; border: 1px solid #e8e1d9; border-radius: 6px; background: #fff; color: #625d58; font-size: 14px; }
.indicator-description { min-width: 0; color: #45413d; font-size: 14px; overflow-wrap: anywhere; }
p { margin: 0; color: #625d58; font-size: 14px; }
@media (max-width: 768px) { .missing-indicator-details { padding: 12px 14px 16px; } li { flex-direction: column; align-items: flex-start; gap: 4px; } }
</style>
