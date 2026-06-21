<script setup>
defineProps({
    pageTitle: {
        type: String,
        default: 'หน้าหลัก',
    },
});
</script>

<template>
    <div class="page-title-block">
        <h1 class="text-xl font-semibold text-gray-900">{{ pageTitle }}</h1>
        <p class="text-sm text-gray-400">ระบบประเมินสมรรถนะและจัดทำแผนพัฒนารายบุคคล</p>
    </div>
</template>
