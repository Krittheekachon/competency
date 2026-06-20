<script setup>
import HeadDashboard from '../Head/Dashboard.vue';

defineProps({
    idpReviewItems: { type: Array, default: () => [] },
});
</script>

<template>
    <HeadDashboard role-key="supervisor" :idp-review-items="idpReviewItems"/>
</template>
