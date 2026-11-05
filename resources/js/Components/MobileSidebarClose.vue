<script setup>
defineEmits(['close']);
</script>

<template>
    <button class="mobile-sidebar-close btn btn-s" type="button" aria-label="ปิดเมนู" @click="$emit('close')">×</button>
</template>

<style scoped>
.mobile-sidebar-close { display: none; }
@media (max-width: 900px) {
    .mobile-sidebar-close {
        display: block;
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
        width: 36px;
        height: 36px;
        padding: 0;
        font-size: 24px;
    }
}
</style>
