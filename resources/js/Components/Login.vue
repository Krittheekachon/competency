<script lang="tsx">
import { defineComponent, ref, watchEffect, type PropType } from "vue";
const useState = (initial: any) => {
  const state = ref(typeof initial === "function" ? initial() : initial);
  const setState = (next: any) => {
    state.value = typeof next === "function" ? next(state.value) : next;
  };
  return [state, setState] as const;
};

const useEffect = (effect: any) => {
  watchEffect((onCleanup) => {
    const cleanup = effect();
    if (typeof cleanup === "function") onCleanup(cleanup);
  });
};

interface LoginProps {onLogin: () => void;}const Login = defineComponent({ name: "Login", props: Object as PropType<LoginProps>, setup(__props) {const { onLogin } = __props as any;return () => <div id="pg-login">
      <div class="lw">
        <div class="ll">
          <div class="ll-in">
            <div class="ll-ico">🎓</div>
            <div class="ll-fac">Faculty of Engineering · KKU HR System</div>
            <div class="ll-title">ระบบบริหารสมรรถนะ<br />และแผนพัฒนารายบุคคล</div>
            <div class="ll-sub">Competency & IDP Management System</div>
          </div>
        </div>
        <div class="lr">
          <div class="lr-title">เข้าสู่ระบบ</div>
          <div class="lr-sub">ใช้บัญชี KKU Account ของท่าน</div>
          <div class="fg">
            <label class="lbl">รหัสผู้ใช้งาน</label>
            <input class="inp" defaultValue="somchai@kku.ac.th" />
          </div>
          <div class="fg">
            <label class="lbl">รหัสผ่าน</label>
            <input class="inp" type="password" defaultValue="••••••••" />
          </div>
          <button class="sso-btn" onClick={onLogin}>เข้าสู่ระบบ ด้วย KKU SSO →</button>
          <div class="hint">
            ระบบเชื่อมต่อ Single Sign-On มหาวิทยาลัยขอนแก่น<br />
            ปัญหาการเข้าใช้งาน ติดต่อ งานทรัพยากรบุคคลคณะ
          </div>
        </div>
      </div>
    </div>;} });export default Login;
</script>
