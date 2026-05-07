<script setup>
import { Head } from '@inertiajs/vue3';

const loginWithKKU = () => {
    window.location.href = route('login');
};
</script>

<template>
    <Head title="เข้าสู่ระบบ - CIDP" />

    <main class="login-page">
        <div class="login-bg" aria-hidden="true">
            <img src="/images/gear_encompetency.png" alt="" />
        </div>

        <section class="login-shell">
            <div class="brand-panel">
                <div class="faculty-logo">
                    <img
                        src="/images/Logo-web-6.png"
                        alt="Faculty of Engineering KKU"
                    />
                </div>

                <div class="brand-copy">
                    <p class="eyebrow">Faculty of Engineering · Khon Kaen University</p>
                    <h1>ระบบบริหารสมรรถนะและแผนพัฒนารายบุคคล</h1>
                    <p class="subtitle">
                        Competency & Individual Development Plan Management System
                    </p>
                </div>

                <div class="cidp-logo">
                    <img
                        src="/images/CIDP_encompetency-nobg.png"
                        alt="CIDP Competency and IDP System"
                    />
                </div>
            </div>

            <div class="login-panel">
                <div>
                    <p class="panel-kicker">KKU Account</p>
                    <h2>เข้าสู่ระบบ</h2>
                    <p class="panel-desc">
                        ใช้บัญชีมหาวิทยาลัยขอนแก่นของคุณเพื่อเข้าใช้งานระบบ CIDP
                    </p>
                </div>

                <button class="login-button" type="button" @click="loginWithKKU">
                    <span class="login-icon" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path
                                d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M10 17l5-5-5-5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M15 12H3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span>เข้าสู่ระบบด้วย KKU Account</span>
                </button>

                <div class="support-box">
                    <span>พบปัญหาการเข้าใช้งาน</span>
                    <strong>ติดต่อหน่วยงานทรัพยากรบุคคลคณะวิศวกรรมศาสตร์</strong>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
.login-page {
    position: relative;
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background:
        radial-gradient(circle at 16% 12%, rgba(255, 255, 255, 0.08), transparent 28%),
        linear-gradient(135deg, #101010 0%, #211313 44%, #0f172a 100%);
    color: #fff;
    font-family: 'Kanit', 'Noto Sans Thai', system-ui, sans-serif;
    padding: 32px 18px;
}

.login-bg {
    position: absolute;
    inset: auto -18% -32% auto;
    width: min(72vw, 760px);
    opacity: 0.08;
    pointer-events: none;
}

.login-bg img {
    width: 100%;
    height: auto;
    transform: rotate(14deg);
}

.login-shell {
    position: relative;
    z-index: 1;
    display: grid;
    width: min(1080px, 100%);
    grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
    gap: 22px;
    align-items: stretch;
}

.brand-panel,
.login-panel {
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 28px 70px rgba(0, 0, 0, 0.34);
    backdrop-filter: blur(18px);
}

.brand-panel {
    display: grid;
    min-height: 560px;
    align-content: space-between;
    gap: 32px;
    border-radius: 8px;
    padding: clamp(28px, 5vw, 54px);
}

.faculty-logo {
    display: inline-flex;
    width: clamp(92px, 12vw, 138px);
    aspect-ratio: 1;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    padding: 14px;
}

.faculty-logo img,
.cidp-logo img {
    display: block;
    max-width: 100%;
    height: auto;
}

.eyebrow,
.panel-kicker {
    margin: 0 0 12px;
    color: #fecaca;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: uppercase;
}

.brand-copy h1 {
    max-width: 720px;
    margin: 0;
    font-size: clamp(34px, 5.4vw, 64px);
    font-weight: 800;
    line-height: 1.12;
}

.subtitle {
    max-width: 560px;
    margin: 18px 0 0;
    color: rgba(255, 255, 255, 0.78);
    font-size: clamp(15px, 2vw, 20px);
    line-height: 1.7;
}

.cidp-logo {
    max-width: min(520px, 92%);
}

.login-panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 28px;
    border-radius: 8px;
    padding: clamp(28px, 4vw, 44px);
}

.login-panel h2 {
    margin: 0;
    font-size: clamp(28px, 4vw, 42px);
    font-weight: 800;
    line-height: 1.2;
}

.panel-desc {
    margin: 12px 0 0;
    color: rgba(255, 255, 255, 0.72);
    font-size: 15px;
    line-height: 1.75;
}

.login-button {
    display: flex;
    min-height: 58px;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 12px;
    border: 0;
    border-radius: 8px;
    background: #8c1515;
    color: #fff;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.35;
    padding: 14px 18px;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

.login-button:hover {
    filter: brightness(1.08);
    box-shadow: 0 18px 34px rgba(140, 21, 21, 0.36);
    transform: translateY(-1px);
}

.login-icon {
    display: inline-flex;
    width: 24px;
    height: 24px;
    flex: 0 0 24px;
}

.support-box {
    display: grid;
    gap: 4px;
    border-left: 3px solid #fecaca;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.72);
    font-size: 13px;
    line-height: 1.6;
    padding: 12px 14px;
}

.support-box strong {
    color: #fff;
    font-weight: 700;
}

@media (max-width: 860px) {
    .login-page {
        align-items: flex-start;
        padding-top: 22px;
    }

    .login-shell {
        grid-template-columns: 1fr;
    }

    .brand-panel {
        min-height: auto;
        gap: 24px;
    }

    .cidp-logo {
        max-width: 360px;
    }
}

@media (max-width: 520px) {
    .login-page {
        padding: 14px;
    }

    .brand-panel,
    .login-panel {
        padding: 22px;
    }

    .faculty-logo {
        width: 84px;
        padding: 10px;
    }

    .brand-copy h1 {
        font-size: 30px;
    }

    .login-button {
        align-items: flex-start;
        font-size: 15px;
    }
}
</style>
