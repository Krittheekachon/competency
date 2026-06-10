<script setup lang="ts">
defineProps<{ setPage: (p: string) => void }>();
</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>ผล Competency Gap</h1>
        <p>รอเชื่อมข้อมูลผลการประเมินจริง</p>
      </div>
    </div>

    <div class="empty-card">
      <div class="empty-title">ยังไม่มีผล Competency Gap</div>
      <div class="empty-copy">เมื่อผลประเมินได้รับอนุมัติแล้ว ข้อมูล Gap จะแสดงในหน้านี้</div>
    </div>
  </section>
</template>

<style scoped>
.employee-page { display: grid; gap: 20px; }
.page-head h1 { margin: 0; color: var(--text); font-size: 22px; font-weight: 900; }
.page-head p { margin: 8px 0 0; color: var(--text3); font-size: 13px; }
.empty-card {
  display: grid;
  place-items: center;
  min-height: 260px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 32px;
  text-align: center;
}
.empty-title { color: var(--text); font-size: 16px; font-weight: 900; }
.empty-copy { color: var(--text3); font-size: 13px; margin-top: 8px; }
</style>
