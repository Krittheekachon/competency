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

const React = {
  useState,
  useEffect,
  useRef: ref
};import { ExcelImportModal } from "../../Components/SharedUI.vue";interface AdminUsersProps {openModal: (type: string, data?: any) => void;users: any[];setUsers: React.Dispatch<React.SetStateAction<any[]>>;academicDepts: string[];supportDepts: string[];adminDepts: string[];worklines: string[];}const AdminUsers = defineComponent({ name: "AdminUsers", props: Object as PropType<AdminUsersProps>, setup(__props) {const { openModal, users, setUsers, academicDepts, supportDepts, adminDepts, worklines } = __props as any;const [showImport, setShowImport] = useState(false);const [search, setSearch] = useState("");const [worklineFilter, setWorklineFilter] = useState("ทุกสายงาน");const [roleFilter, setRoleFilter] = useState("ทุกบทบาท (Role)");const [statusFilter, setStatusFilter] = useState("ทุกสถานะ");const getDisplayLevel = (user: any) => user.w === "สายงานบริหาร" ? user.p : user.l;
    const toggleStatus = (sso: string) => {
      const next = users.map((u) => {
        if (u.sso === sso) {
          return { ...u, act: !u.act };
        }
        return u;
      });
      setUsers(next);
    };

    const getRoleBadge = (role: string) => {
      switch (role) {
        case "admin":return <span class="b bp">ผู้ดูแลระบบ</span>;
        case "hr":return <span class="b bb">งานทรัพยากรบุคคล</span>;
        case "manager":return <span class="b bg" style={{ background: "#e0f2fe", color: "#0369a1" }}>ผู้บริหารคณะ</span>;
        case "manager_dept":return <span class="b bg" style={{ background: "#fef3c7", color: "#92400e" }}>ผู้บังคับบัญชา</span>;
        case "supervisor":return <span class="b bg" style={{ background: "#f0f9ff", color: "#0284c7" }}>หัวหน้างาน</span>;
        default:return <span class="b bgr">บุคลากร</span>;
      }
    };

    const filtered = users.filter((u) => {
      const matchesSearch = u.n.toLowerCase().includes(search.value.toLowerCase()) || u.sso && u.sso.toLowerCase().includes(search.value.toLowerCase());
      const matchesWorkline = worklineFilter.value === "ทุกสายงาน" || u.w === worklineFilter.value;

      let roleName = "บุคลากร";
      if (u.r === "admin") roleName = "ผู้ดูแลระบบ";else
      if (u.r === "hr") roleName = "งานทรัพยากรบุคคล";else
      if (u.r === "manager") roleName = "ผู้บริหารคณะ";else
      if (u.r === "manager_dept") roleName = "ผู้บังคับบัญชา";else
      if (u.r === "supervisor") roleName = "หัวหน้างาน";

      const matchesRole = roleFilter.value === "ทุกบทบาท (Role)" || roleName === roleFilter.value;
      const matchesStatus = statusFilter.value === "ทุกสถานะ" || (statusFilter.value === "ปกติ / ใช้งาน" ? u.act === true : u.act === false);

      return matchesSearch && matchesWorkline && matchesRole && matchesStatus;
    });return () =>


    <>
      <div class="flex ic jb mb20">
        <div>
          <div class="sec-t">จัดการผู้ใช้งาน 👤</div>
          <div class="sec-s">รายชื่อบุคลากรทั้งหมด · กำหนด Role และข้อมูลตามโครงสร้างองค์กร</div>
        </div>
        <div class="flex" style={{ gap: "8px" }}>
          <button class="btn btn-s" onClick={() => setShowImport(true)}>📥 Import Excel</button>
          <button class="btn btn-p" onClick={() => openModal("modal-user")}>+ เพิ่มผู้ใช้</button>
        </div>
      </div>

      {showImport.value &&
      <ExcelImportModal
        title="นำเข้าข้อมูลผู้ใช้งาน (User Import)"
        templateName="User_Template.xlsx"
        onClose={() => setShowImport(false)} />

      }

      <div class="card mb14">
        <div class="ch" style={{ flexWrap: "wrap", gap: "8px" }}>
          <input class="inp" style={{ maxWidth: "260px" }} placeholder="🔍 ค้นหาชื่อ / ID..." value={search.value} onChange={(e) => setSearch(e.target.value)} />
          <select class="sel" style={{ width: "160px" }} value={worklineFilter.value} onChange={(e) => setWorklineFilter(e.target.value)}>
            <option>ทุกสายงาน</option>
            {worklines.map((w) => <option key={w} value={w}>{w}</option>)}
          </select>
          <select class="sel" style={{ width: "180px" }} value={roleFilter.value} onChange={(e) => setRoleFilter(e.target.value)}>
            <option>ทุกบทบาท (Role)</option>
            <option>บุคลากร</option>
            <option>หัวหน้างาน</option>
            <option>ผู้บังคับบัญชา</option>
            <option>ผู้บริหารคณะ</option>
            <option>งานทรัพยากรบุคคล</option>
            <option>ผู้ดูแลระบบ</option>
          </select>
          <select class="sel" style={{ width: "130px" }} value={statusFilter.value} onChange={(e) => setStatusFilter(e.target.value)}>
            <option>ทุกสถานะ</option>
            <option>ปกติ / ใช้งาน</option>
            <option>ระงับการใช้งาน</option>
          </select>
        </div>
        <div style={{ overflowX: "auto" }}>
          <table class="tbl">
            <thead>
              <tr>
                <th>ID</th>
                <th style={{ minWidth: "180px" }}>ชื่อ-นามสกุล</th>
                <th>สายงาน</th>
                <th style={{ minWidth: "200px" }}>หน่วยงาน / สังกัด</th>
                <th>ตำแหน่ง</th>
                <th>ระดับตำแหน่ง</th>
                <th>หัวหน้างาน</th>
                <th>ผู้บังคับบัญชา</th>
                <th style={{ minWidth: "160px" }}>บทบาทในระบบ</th>
                <th>สถานะ</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((u, i) => {
                const isHierarchical = u.d && u.d.includes(" > ");
                const depts = isHierarchical ? u.d.split(" > ") : [u.d];
                return (
                  <tr key={u.sso || i}>
                    <td style={{ fontFamily: "monospace", fontSize: "11px", color: "var(--text3)" }}>{u.sso || "—"}</td>
                    <td>
                      <div class="flex ic g8">
                        <div class="av" style={{ width: "32px", height: "32px", fontSize: "12px", background: "var(--navy)" }}>
                          {u.photo ? <img class="avatar-photo" src={u.photo} alt={u.n} /> : u.n[0]}
                        </div>
                        <div class="flex col">
                          <span class="fw6 fs13">{u.t}{u.n}</span>
                        </div>
                      </div>
                    </td>
                    <td><span class={`b ${u.w === "สายวิชาการ" ? "bb" : "bg"}`} style={{ fontSize: "11px" }}>{u.w}</span></td>
                    <td>
                      <div class="fs12 fw6 text-gray-700 whitespace-nowrap overflow-hidden truncate" style={{ maxWidth: "300px" }} title={u.d || ""}>
                        {u.d ? u.d.split(" > ").join(" > ") : "—"}
                      </div>
                    </td>
                    <td class="fs12" style={{ maxWidth: "140px" }}>
                      <div class="whitespace-nowrap overflow-hidden truncate" style={{ width: "100%" }} title={u.p || ""}>
                        {u.p || "—"}
                      </div>
                    </td>
                    <td class="muted fs11">{getDisplayLevel(u) || "—"}</td>
                    <td class="muted fs12" style={{ maxWidth: "140px" }}>{u.sup || "—"}</td>
                    <td class="muted fs12" style={{ maxWidth: "140px" }}>{u.evaluator2 || "—"}</td>
                    <td>{getRoleBadge(u.r)}</td>
                    <td>
                      <span class={`b ${u.act ? "bg" : "br"}`}>
                        {u.act ? "ปกติ" : "ระงับ"}
                      </span>
                    </td>
                    <td>
                      <div class="flex g4">
                        <button class="btn btn-s btn-xs" onClick={() => openModal("modal-user", u)}>แก้ไข</button>
                        <button
                          class="btn btn-r btn-xs"
                          style={{ background: u.act ? "#fee2e2" : "#dcfce7", color: u.act ? "#b91c1c" : "#15803d" }}
                          onClick={() => toggleStatus(u.sso)}>
                          
                          {u.act ? "ระงับ" : "เปิด"}
                        </button>
                      </div>
                    </td>
                  </tr>);

              })}
            </tbody>
          </table>
          {filtered.length === 0 &&
          <div style={{ padding: "40px", textAlign: "center", color: "var(--text3)" }}>
              ไม่พบข้อมูลที่คุณค้นหา 🔍
            </div>
          }
        </div>
      </div>
    </>;} });



export default AdminUsers;
</script>
