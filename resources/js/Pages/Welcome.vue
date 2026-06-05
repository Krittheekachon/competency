<script setup>
import { Head } from '@inertiajs/vue3';

const loginWithKKU = () => {
window.location.href = route('login');
};
</script><template><Head title="เข้าสู่ระบบ - CIDP" /><main class="welcome-page"><img class="watermark watermark-right" src="/images/gear_encompetency.png" alt="" aria-hidden="true" /><img class="watermark watermark-center" src="/images/gear_encompetency.png" alt="" aria-hidden="true" /><section class="welcome-content" aria-labelledby="welcome-title"><div class="brand-text"><img
class="cidp-mark"
src="/images/CIDP_encompetency-nobg.png"
alt="CIDP Competency and IDP System"
/><h1 id="welcome-title">ระบบบริหารสมรรถนะและแผนพัฒนารายบุคคล</h1><p class="subtitle">Competency & Individual Development Plan Management System</p></div><button class="login-button" type="button" @click="loginWithKKU">
เข้าสู่ระบบด้วย KKU account
 </button><p class="support-text">
พบปัญหาการเข้าใช้งาน ติดต่อหน่วยงานทรัพยากรบุคคลคณะวิศวกรรมศาสตร์
 </p></section></main></template><style scoped>
.welcome-page {
position: relative;
display: flex;
min-height: 100vh;
align-items: center;
justify-content: center;
overflow: hidden;
background: #161a1b;
color: #f8fafc;
font-family: 'Kanit', 'Noto Sans Thai', system-ui, sans-serif;
padding: 32px 20px;
}

.welcome-page::after {
position: absolute;
inset: auto 0 0;
height: 1px;
background: rgba(148, 163, 184, 0.45);
content: '';
}

.watermark {
position: absolute;
display: block;
height: auto;
pointer-events: none;
user-select: none;
}

.watermark-right {
right: -300px;
top: -180px;
width: min(980px, 68vw);
opacity: 0.08;
transform: rotate(12deg);
}

.watermark-center {
right: 18%;
bottom: -300px;
width: min(780px, 54vw);
opacity: 0.055;
transform: rotate(-8deg);
}

.welcome-content {
position: relative;
z-index: 1;
display: flex;
width: min(620px, calc(100vw - 40px));
flex-direction: column;
align-items: center;
justify-content: center;
gap: clamp(24px, 4vh, 42px);
text-align: center;
}

.brand-text {
min-width: 0;
}

.cidp-mark {
display: block;
width: min(500px, 74vw);
height: auto;
margin-inline: auto;
filter: drop-shadow(0 12px 22px rgba(0, 0, 0, 0.26));
}

.brand-text h1 {
margin: 14px auto 0;
max-width: 520px;
color: rgba(255, 255, 255, 0.72);
font-size: clamp(12px, 1vw, 14px);
font-weight: 500;
line-height: 1.5;
white-space: nowrap;
}

.subtitle {
margin: 11px auto 0;
padding-top: 11px;
width: fit-content;
color: rgba(226, 232, 240, 0.78);
font-size: clamp(10px, 0.82vw, 12px);
font-weight: 500;
letter-spacing: 0.08em;
line-height: 1.4;
text-transform: uppercase;
white-space: nowrap;
border-top: 1px solid rgba(226, 232, 240, 0.56);
}

.login-button {
position: relative;
min-width: 196px;
min-height: 38px;
border: 0;
border-radius: 4px;
background: #b73a24;
color: #ffffff;
cursor: pointer;
font-size: 13px;
font-weight: 800;
line-height: 1;
padding: 10px 18px;
box-shadow: 0 12px 26px rgba(0, 0, 0, 0.22);
transition: background 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}

.login-button:hover {
background: #c9482f;
box-shadow: 0 16px 34px rgba(0, 0, 0, 0.3);
transform: translateY(-1px);
}

.support-text {
width: min(680px, calc(100vw - 40px));
margin: 0;
color: rgba(203, 213, 225, 0.74);
font-size: clamp(11px, 0.9vw, 13px);
line-height: 1.5;
text-align: center;
white-space: nowrap;
}

@media (max-width: 720px) {
.welcome-content {
gap: 22px;
}

.cidp-mark {
width: min(390px, 82vw);
}

.brand-text h1,
.subtitle,
.support-text {
white-space: normal;
}
}

@media (max-width: 420px) {
.brand-text h1 {
font-size: 13px;
}

.subtitle,
.support-text {
font-size: 10px;
}

.login-button {
min-width: 180px;
font-size: 12px;
}
}
</style>
