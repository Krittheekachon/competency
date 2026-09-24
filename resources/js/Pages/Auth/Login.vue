<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
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
    <Head :title="$page.props.pageTitle || 'เข้าสู่ระบบ'" />

    <main class="login-page">
        <header class="top-bar" aria-label="Faculty branding">
            <a class="top-brand" href="/" aria-label="EN-IDP home">
                <svg class="top-brand-mark" viewBox="40 58 470 210" aria-hidden="true" focusable="false">
                    <g fill="#8f3036">
                        <path d="M185 65 47 127v135h153v-29H79v-24l106-47v-31L79 178v-29l106-47z" />
                        <path d="M205 65h23l105 122V109l28-13v166h-30L233 149v113h-28z" />
                    </g>
                    <g fill="none" stroke="#8f3036" stroke-width="4" stroke-linejoin="miter" stroke-linecap="square">
                        <path d="M375 261V119l53-25v168m-38-8V130l22-10v135m16-95 22 10v92m-22-50 72 41v9" />
                    </g>
                </svg>

                <span class="top-brand-divider" aria-hidden="true"></span>

                <span class="top-brand-text">
                    <strong>EN-IDP</strong>
                    <span>Competency &amp; IDP System</span>
                </span>
            </a>

            <p>Faculty of Engineering <span>|</span> Khon Kaen University</p>
        </header>

        <div class="login-content">
            <section class="login-card" aria-labelledby="login-title">
                <div class="brand-panel">
                    <div class="brand-logo" aria-label="EN-IDP Competency and IDP System">
                        <svg viewBox="40 58 470 210" aria-hidden="true" focusable="false">
                            <g fill="#8f3036">
                                <path d="M185 65 47 127v135h153v-29H79v-24l106-47v-31L79 178v-29l106-47z" />
                                <path d="M205 65h23l105 122V109l28-13v166h-30L233 149v113h-28z" />
                            </g>
                            <g fill="none" stroke="#8f3036" stroke-width="4" stroke-linejoin="miter" stroke-linecap="square">
                                <path d="M375 261V119l53-25v168m-38-8V130l22-10v135m16-95 22 10v92m-22-50 72 41v9" />
                            </g>
                        </svg>
                        <strong>EN-IDP</strong>
                        <span>Competency &amp; IDP System</span>
                    </div>

                    <div class="faculty-name">
                        Faculty of Engineering <span>|</span> Khon Kaen University
                    </div>
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
                            placeholder="Password"
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

                    <p class="password-help">
                        หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ (Admin)
                    </p>
                </form>
            </section>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    min-height: 100svh;
    overflow: hidden;
    background:
        radial-gradient(circle at 50% 47%, rgba(255, 255, 255, 0.025), transparent 36%),
        linear-gradient(180deg, #101719 0%, #0f1517 100%);
    color: #ffffff;
    font-family: 'Kanit', 'Noto Sans Thai', system-ui, sans-serif;
}

.top-bar {
    display: flex;
    box-sizing: border-box;
    height: 74px;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.22);
    padding: 10px clamp(24px, 4.8vw, 80px);
}

.top-brand {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    gap: 13px;
    color: #ffffff;
    text-decoration: none;
}

.top-brand-mark {
    display: block;
    width: 82px;
    height: 40px;
    overflow: visible;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.18));
}

.top-brand-divider {
    width: 1px;
    height: 38px;
    background: rgba(255, 255, 255, 0.78);
}

.top-brand-text {
    display: grid;
    gap: 4px;
    color: #ffffff;
    text-align: left;
    text-transform: uppercase;
}

.top-brand-text strong {
    font-size: 27px;
    font-weight: 800;
    line-height: 0.9;
}

.top-brand-text span {
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
}

.top-bar > p {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: clamp(10px, 0.8vw, 14px);
    font-weight: 500;
    text-transform: uppercase;
    white-space: nowrap;
}

.top-bar > p span {
    display: inline-block;
    margin-inline: 12px;
    color: rgba(255, 255, 255, 0.72);
}

.login-content {
    display: flex;
    box-sizing: border-box;
    min-height: calc(100svh - 74px);
    align-items: flex-start;
    justify-content: center;
    padding: clamp(40px, 6.5vh, 62px) 24px 30px;
}

.login-card {
    display: grid;
    width: min(1060px, 100%);
    height: min(606px, calc(100svh - 144px));
    min-height: 540px;
    grid-template-columns: 499px minmax(0, 1fr);
    overflow: hidden;
    border: 1px solid rgba(202, 211, 214, 0.38);
    border-radius: 8px;
    background: rgba(16, 23, 25, 0.7);
    box-shadow: 0 28px 80px rgba(0, 0, 0, 0.24);
}

.brand-panel {
    position: relative;
    display: flex;
    min-width: 0;
    align-items: center;
    flex-direction: column;
    border-right: 1px solid rgba(202, 211, 214, 0.3);
    background:
        url('/images/en-idp-bg.png') center bottom / 100% 112% no-repeat,
        linear-gradient(180deg, rgba(16, 23, 25, 0.2), rgba(16, 23, 25, 0.08));
    padding: 67px 34px 250px;
}

.brand-logo {
    display: grid;
    width: min(320px, 100%);
    justify-items: center;
    color: #ffffff;
    text-align: center;
    text-transform: uppercase;
}

.brand-logo svg {
    display: block;
    width: 230px;
    height: 110px;
    overflow: visible;
}

.brand-logo strong {
    margin-top: 0;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 58px;
    font-weight: 800;
    line-height: 0.92;
}

.brand-logo span {
    margin-top: 12px;
    font-size: 16px;
    font-weight: 500;
    line-height: 1;
}

.faculty-name {
    width: min(392px, 100%);
    margin-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.72);
    color: rgba(255, 255, 255, 0.92);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.2;
    padding-top: 16px;
    text-align: center;
    text-transform: uppercase;
    white-space: nowrap;
}

.faculty-name span {
    color: #b13a42;
    margin-inline: 5px;
}

.login-form {
    display: flex;
    min-width: 0;
    flex-direction: column;
    background: rgba(16, 23, 25, 0.34);
    color: #ffffff;
    padding: 63px 46px 50px 57px;
}

.heading {
    margin-bottom: 48px;
}

.heading h1 {
    margin: 0;
    color: #ffffff;
    font-size: 39px;
    font-weight: 900;
    line-height: 1.15;
    text-shadow: 0 5px 12px rgba(0, 0, 0, 0.28);
}

.heading p {
    margin: 14px 0 0;
    color: rgba(255, 255, 255, 0.78);
    font-size: 16px;
    line-height: 1.55;
}

.field {
    display: grid;
    gap: 17px;
    margin-bottom: 30px;
}

.field:last-of-type {
    margin-bottom: 35px;
}

.field label {
    color: rgba(255, 255, 255, 0.94);
    font-size: 16px;
    font-weight: 800;
}

.input {
    box-sizing: border-box;
    width: 100%;
    height: 68px;
    border: 1px solid rgba(211, 221, 224, 0.66);
    border-radius: 7px;
    background: rgba(13, 19, 21, 0.28);
    color: #ffffff;
    font: inherit;
    font-size: 16px;
    outline: none;
    padding: 0 21px;
    transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
}

.input::placeholder {
    color: rgba(255, 255, 255, 0.68);
}

.input:focus {
    border-color: rgba(192, 61, 70, 0.95);
    background: rgba(18, 25, 27, 0.72);
    box-shadow: 0 0 0 3px rgba(143, 48, 54, 0.22);
}

.error {
    margin: -5px 0 -16px;
    color: #ff9298;
    font-size: 12px;
}

.submit-button {
    width: 100%;
    height: 77px;
    margin-top: 0;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 7px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent),
        linear-gradient(90deg, #9a2730 0%, #bd2d39 54%, #a5232d 100%);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.24);
    color: #ffffff;
    cursor: pointer;
    font-family: inherit;
    font-size: 20px;
    font-weight: 900;
    transition: filter 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
}

.submit-button:hover:not(:disabled) {
    box-shadow: 0 18px 34px rgba(0, 0, 0, 0.32);
    filter: brightness(1.07);
    transform: translateY(-1px);
}

.submit-button:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.7);
    outline-offset: 4px;
}

.submit-button:disabled {
    cursor: wait;
    opacity: 0.68;
}

.password-help {
    margin: 14px 0 0;
    color: rgba(255, 255, 255, 0.68);
    font-size: 13px;
    line-height: 1.5;
    text-align: center;
}

@media (max-width: 900px) {
    .login-page {
        overflow: auto;
    }

    .top-bar {
        height: 64px;
    }

    .top-bar > p {
        display: none;
    }

    .login-content {
        min-height: calc(100svh - 64px);
        padding: 28px 20px 36px;
    }

    .login-card {
        height: auto;
        min-height: 0;
        grid-template-columns: 1fr;
    }

    .brand-panel {
        min-height: 310px;
        border-right: 0;
        border-bottom: 1px solid rgba(202, 211, 214, 0.3);
        background-size: 100% 112%;
        padding: 34px 24px 132px;
    }

    .brand-logo {
        width: min(255px, 72vw);
    }

    .brand-logo svg {
        width: 185px;
        height: 83px;
    }

    .brand-logo strong {
        font-size: 43px;
    }

    .brand-logo span {
        margin-top: 9px;
        font-size: 12px;
    }

    .faculty-name {
        width: min(360px, 100%);
        font-size: 11px;
        padding-top: 11px;
    }

    .login-form {
        padding: 38px clamp(24px, 7vw, 54px) 44px;
    }

    .heading {
        margin-bottom: 34px;
    }
}

@media (max-width: 520px) {
    .top-bar {
        padding-inline: 18px;
    }

    .top-brand {
        gap: 9px;
    }

    .top-brand-mark {
        width: 66px;
        height: 32px;
    }

    .top-brand-divider {
        height: 30px;
    }

    .top-brand-text strong {
        font-size: 20px;
    }

    .top-brand-text span {
        font-size: 8px;
    }

    .login-content {
        padding: 18px 14px 28px;
    }

    .brand-panel {
        min-height: 270px;
        padding: 28px 18px 112px;
    }

    .brand-logo {
        width: min(220px, 76vw);
    }

    .brand-logo svg {
        width: 160px;
        height: 72px;
    }

    .brand-logo strong {
        font-size: 37px;
    }

    .brand-logo span {
        font-size: 10px;
    }

    .faculty-name {
        font-size: 9px;
        white-space: normal;
    }

    .login-form {
        padding: 30px 20px 34px;
    }

    .heading {
        margin-bottom: 28px;
    }

    .heading h1 {
        font-size: 32px;
    }

    .heading p {
        margin-top: 10px;
        font-size: 14px;
    }

    .field {
        margin-bottom: 25px;
    }

    .input {
        height: 56px;
    }

    .submit-button {
        height: 60px;
        font-size: 18px;
    }
}
</style>
