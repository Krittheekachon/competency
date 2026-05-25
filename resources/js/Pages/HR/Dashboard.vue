<script setup>
import { router } from '@inertiajs/vue3';

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-blue-50 px-6 text-center font-kanit">
        <div class="max-w-2xl space-y-5 rounded-lg bg-white p-10 shadow-sm ring-1 ring-blue-100">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-blue-700">HR</p>
            <h1 class="text-4xl font-black text-slate-900 md:text-6xl">ส่วนของงานทรัพยากรบุคคล</h1>
            <p class="text-lg text-slate-600">
                ยินดีต้อนรับ:
                <span class="font-bold text-slate-900">{{ $page.props.auth.user.name }}</span>
            </p>
            <button class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700" type="button" @click="logout">
                ออกจากระบบ
            </button>
        </div>
    </div>
</template>
