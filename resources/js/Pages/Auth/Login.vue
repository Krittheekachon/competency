<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',    // หรือเปลี่ยนเป็น username ตามโครงสร้าง DB ของคุณ
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login - EduCompetency" />

    <div class="bg-on-background text-on-surface font-body-md overflow-hidden h-screen flex flex-col relative">
        <div class="fixed inset-0 gear-watermark pointer-events-none w-full h-full"></div>

        <main class="relative z-10 flex-grow flex items-center justify-center px-gutter">
            <div class="w-full max-w-[440px] flex flex-col items-center">
                
                <div class="mb-xl text-center">
                    <img alt="EduCompetency Logo" class="h-20 w-auto mx-auto mb-md brightness-0 invert opacity-90" src="https://lh3.googleusercontent.com/aida/ADBb0ugfIzuT9W6gy_7YUD9q4vRNAX9MBUlp60CuDt0NXh0M_Z3iNaHJL9yW5VZYZAlTvV8G4qpF9eWG2dmyEimEjdiAcgMP2BFRk7Bhx0Dwpw9r0ZSoZbpy7jZ-kSpb1viCBtpJN47FqkXxPmgwywmRaFsLom1mIqOsDJHnyu3_CYIybHkYyqoykt6mM22Kj7EyoZjWXqxwOS9u5IU0C7CPJ_oSE7eX8yCJUUBLMmllrTlmJDMw2gFFdYfOlu1lEaoQAex63-qFqYMZ"/>
                    <h1 class="font-h2 text-h2 text-primary-fixed-dim tracking-tight uppercase">CIDP COMPETENCY & IDP SYSTEM</h1>
                    <p class="font-body-md text-secondary-fixed-dim opacity-70">Faculty of Engineering | Khon Kaen University</p>
                </div>

                <div class="w-full bg-surface-container-lowest/5 backdrop-blur-md rounded-xl p-xl shadow-2xl border border-outline/20">
                    <form @submit.prevent="submit" class="space-y-lg">
                        <div class="space-y-xs">
                            <label class="font-label-sm text-surface-variant px-xs" for="email">Username</label>
                            <div class="relative flex items-center">
                                <span class="material-symbols-outlined absolute left-md text-outline-variant pointer-events-none">person</span>
                                <input 
                                    v-model="form.email"
                                    type="text"
                                    id="email" 
                                    placeholder="Enter your username"
                                    class="w-full bg-on-background/50 border border-outline/30 rounded-lg py-md pl-[48px] pr-md text-on-primary font-body-md placeholder:text-secondary focus:ring-2 focus:ring-primary-container focus:border-primary-container transition-all outline-none" 
                                    required
                                />
                            </div>
                            <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-xs">
                            <label class="font-label-sm text-surface-variant px-xs" for="password">Password</label>
                            <div class="relative flex items-center">
                                <span class="material-symbols-outlined absolute left-md text-outline-variant pointer-events-none">lock</span>
                                <input 
                                    v-model="form.password"
                                    type="password"
                                    id="password" 
                                    placeholder="Enter your password"
                                    class="w-full bg-on-background/50 border border-outline/30 rounded-lg py-md pl-[48px] pr-md text-on-primary font-body-md placeholder:text-secondary focus:ring-2 focus:ring-primary-container focus:border-primary-container transition-all outline-none" 
                                    required
                                />
                            </div>
                            <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
                        </div>

                        <div class="pt-sm">
                            <button 
                                type="submit"
                                :disabled="form.processing"
                                class="w-full bg-primary hover:bg-primary-container text-on-primary font-button text-button py-md rounded-lg shadow-lg active:scale-[0.98] transition-all flex items-center justify-center gap-sm"
                                :class="{ 'opacity-50 pointer-events-none': form.processing }"
                            >
                                Login (Prototype)
                                <span class="material-symbols-outlined text-[20px]">login</span>
                            </button>
                        </div>
                    </form>

                    <div class="relative my-xl flex items-center">
                        <div class="flex-grow border-t border-outline/20"></div>
                        <span class="mx-md text-secondary-fixed-dim font-label-sm opacity-50">OR</span>
                        <div class="flex-grow border-t border-outline/20"></div>
                    </div>

                    <button class="w-full bg-transparent border border-outline/30 hover:bg-surface-variant/10 text-on-primary font-button text-button py-md rounded-lg flex items-center justify-center gap-md active:opacity-80 transition-all">
                        <img alt="KKU" class="h-6 w-auto" src="https://lh3.googleusercontent.com/aida/ADBb0ugfIzuT9W6gy_7YUD9q4vRNAX9MBUlp60CuDt0NXh0M_Z3iNaHJL9yW5VZYZAlTvV8G4qpF9eWG2dmyEimEjdiAcgMP2BFRk7Bhx0Dwpw9r0ZSoZbpy7jZ-kSpb1viCBtpJN47FqkXxPmgwywmRaFsLom1mIqOsDJHnyu3_CYIybHkYyqoykt6mM22Kj7EyoZjWXqxwOS9u5IU0C7CPJ_oSE7eX8yCJUUBLMmllrTlmJDMw2gFFdYfOlu1lEaoQAex63-qFqYMZ"/>
                        Login with KKU Account
                    </button>
                </div>

                <div class="mt-xl flex flex-col items-center gap-sm">
                    <Link :href="route('password.request')" class="font-label-sm text-primary-fixed-dim hover:text-on-primary-container transition-colors opacity-80 underline underline-offset-4">
                        Forgot Password?
                    </Link>
                    <p class="font-label-sm text-secondary-fixed-dim opacity-40 mt-md">System Environment: Prototype-V1.0</p>
                </div>
            </div>
        </main>

        <footer class="relative z-10 w-full flex flex-col md:flex-row justify-between items-center px-gutter py-md mt-auto bg-transparent">
            <div class="font-label-sm text-label-sm text-secondary-fixed-dim opacity-50 mb-sm md:mb-0">
                © 2024 EduCompetency. All rights reserved.
            </div>
            <div class="flex gap-lg">
                <a class="font-label-sm text-label-sm text-secondary-fixed-dim hover:text-primary-fixed-dim transition-all opacity-60" href="#">Support</a>
                <a class="font-label-sm text-label-sm text-secondary-fixed-dim hover:text-primary-fixed-dim transition-all opacity-60" href="#">Privacy Policy</a>
                <a class="font-label-sm text-label-sm text-secondary-fixed-dim hover:text-primary-fixed-dim transition-all opacity-60" href="#">User Manual</a>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.material-symbols-outlined {
    font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
}

.gear-watermark {
    /* ใช้สีที่แปรผันตามตัวแปร Tailwind เดิมที่คุณส่งมา */
    background-image: linear-gradient(rgba(27, 28, 28, 0.85), rgba(27, 28, 28, 0.85)), 
                      url('https://lh3.googleusercontent.com/aida/ADBb0uj2ayWHUZ9tMvaJKkRLHIQTVhKrsIVYTLKugBPpiUW-mzvarTOj5Xb0KYN-5BgPhalAPRLpvIaUbdT1EdOITNzpNnELLyhrWKTAMRZSXmXNBA5Oc9gRaX3r0C36Cvo5CMSGujssl599LfCmDnICkqzh10bGhQEwtpd_hoix318MioYiRsw7gLQ8XmRzHUbNw-Eu1L72_JSbI4Qbk-ZUDOw_mRKTcgRPh5k2FJm7l0TzGb6xtJrYDMmhXzddmulYqLvyUdEngqe0');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.4;
}
</style>