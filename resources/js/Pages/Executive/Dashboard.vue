<template>
    <div class="flex min-h-screen items-center justify-center bg-blue-50 text-center font-kanit">
        <div class="space-y-4">
            <h1 class="text-8xl font-black text-blue-600">
                CEO of CoE
            </h1>
            <p class="text-2xl text-gray-700">
                สวัสดีนี่กบเอง: <span class="font-bold">{{ $page.props.auth.user.name }}</span>
            </p>
            <div class="mt-8">
                <button @click="logout" class="px-6 py-2 bg-gray-800 text-white rounded-full">พักผ่อน (Logout)</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
const logout = () => router.post(route('logout'));
</script>