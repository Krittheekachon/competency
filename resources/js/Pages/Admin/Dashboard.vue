<template>
    <div class="flex min-h-screen items-center justify-center bg-red-50 text-center font-kanit">
        <div class="space-y-4">
            <h1 class="text-8xl font-black text-[#8C1515] animate-bounce">
                แอดมินนะจ๊ะ
            </h1>
            <p class="text-2xl text-gray-700">
                ยินดีต้อนรับคุณ: <span class="font-bold">{{ $page.props.auth.user.name }}</span>
            </p>
            <div class="mt-8">
                <button @click="logout" class="px-6 py-2 bg-gray-800 text-white rounded-full">ออกจากระบบ</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
const logout = () => router.post(route('logout'));
</script>