<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const demoRoles = [
    { role: 'admin', label: 'Admin' },
    { role: 'hr', label: 'HR' },
    { role: 'supervisor', label: 'Supervisor' },
    { role: 'dept_head', label: 'Dept Head' },
    { role: 'employee', label: 'Employee' },
    { role: 'dean', label: 'Dean' },
];

const selectDemoRole = (role) => {
    form.email = role;
    form.password = 'password';
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="เข้าสู่ระบบ - CIDP" />

    <main class="login-page">
        <section class="login-card" aria-labelledby="login-title">
            <div class="brand">
                <img
                    class="brand-logo"
                    src="/images/CIDP_encompetency-nobg.png"
                    alt="CIDP Competency and IDP System"
                />
                <p>Faculty of Engineering | Khon Kaen University</p>
            </div>

            <form class="login-form" @submit.prevent="submit">
                <div class="heading">
                    <h1 id="login-title">เข้าสู่ระบบ</h1>
                    <p>กรอก username และ password เพื่อเข้าใช้งานระบบ</p>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input
                        id="username"
                        v-model="form.email"
                        autocomplete="username"
                        class="input"
                        placeholder="Username"
                        required
                        type="text"
                    />
                    <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        autocomplete="current-password"
                        class="input"
                        placeholder="password"
                        required
                        type="password"
                    />
                    <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
                </div>

                <button
                    class="submit-button"
                    :disabled="form.processing"
                    type="submit"
                >
                    {{ form.processing ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
                </button>
            </form>
        </section>
    </main>
</template>

<style scoped>
.login-page {
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at 82% 10%, rgba(140, 21, 21, 0.16), transparent 28%),
        linear-gradient(145deg, #151819 0%, #0f1314 100%);
    color: #f8fafc;
    font-family: 'Kanit', 'Noto Sans Thai', system-ui, sans-serif;
    padding: 24px;
}

.login-card {
    display: grid;
    width: min(980px, 100%);
    grid-template-columns: 0.9fr 1.1fr;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.38);
}

.brand {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 18px;
    min-height: 560px;
    background:
        linear-gradient(rgba(9, 12, 13, 0.82), rgba(9, 12, 13, 0.82)),
        url('/images/gear_encompetency.png');
    background-position: center;
    background-size: 140%;
    padding: 42px;
}

.brand-logo {
    width: min(360px, 100%);
    height: auto;
}

.brand p {
    margin: 0;
    color: rgba(226, 232, 240, 0.72);
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.login-form {
    display: grid;
    gap: 18px;
    background: #ffffff;
    color: #0f172a;
    padding: 42px;
}

.heading h1 {
    margin: 0;
    color: #0b1f4d;
    font-size: 30px;
    font-weight: 900;
}

.heading p {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
}

.field {
    display: grid;
    gap: 7px;
}

.field label {
    color: #172033;
    font-size: 13px;
    font-weight: 800;
}

.input {
    min-height: 42px;
    width: 100%;
    border: 1px solid #d7dfeb;
    border-radius: 5px;
    color: #0f172a;
    font-size: 14px;
    outline: none;
    padding: 9px 12px;
}

.input:focus {
    border-color: #8c1515;
    box-shadow: 0 0 0 3px rgba(140, 21, 21, 0.12);
}

.error {
    margin: 0;
    color: #b91c1c;
    font-size: 12px;
}

.submit-button {
    min-height: 46px;
    border: 0;
    border-radius: 5px;
    background: #8c1515;
    color: #ffffff;
    cursor: pointer;
    font-size: 15px;
    font-weight: 900;
    margin-top: 4px;
    transition: background 0.16s ease, transform 0.16s ease;
}

.submit-button:hover:not(:disabled) {
    background: #741111;
    transform: translateY(-1px);
}

.submit-button:disabled {
    cursor: wait;
    opacity: 0.68;
}

@media (max-width: 840px) {
    .login-card {
        grid-template-columns: 1fr;
    }

    .brand {
        min-height: auto;
        align-items: center;
        padding: 30px 24px;
        text-align: center;
    }

    .brand-logo {
        width: min(310px, 82vw);
    }

    .login-form {
        padding: 30px 22px;
    }
}

@media (max-width: 520px) {
    .login-page {
        padding: 14px;
    }

    .brand p {
        white-space: normal;
    }
}
</style>
