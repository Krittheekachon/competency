<script lang="tsx">
// @ts-nocheck
import { defineComponent, ref } from "vue";
import type { PropType } from "vue";

const useState = (initial: any) => {
const state = ref(typeof initial === "function" ? initial() : initial);
const setState = (next: any) => {
state.value = typeof next === "function" ? next(state.value) : next;
};
return [state, setState] as const;
};

const getApprovalRows = (users: any[]) => {
const activeStaff = users
.filter((user) => user.act !== false && !["dean", "manager"].includes(user.r))
.slice(0, 12);

const fallback = [
{
n: "สมชาย มีสุข",
t: "นาย",
p: "นักวิชาการศึกษา",
d: "สนับสนุนการศึกษาและวิชาการ",
sup: "กัญญารัตน์ ศรีวิชา",
evaluator2: "ธนพล ไชยรักษ์",
},
{
n: "มาลี ดีเสมอ",
t: "นางสาว",
p: "นักทรัพยากรบุคคล",
d: "ทรัพยากรบุคคล",
sup: "พรพิมล บุคคลดี",
evaluator2: "ธนพล ไชยรักษ์",
},
{
n: "วิชัย ระบบดี",
t: "นาย",
p: "นักวิชาการคอมพิวเตอร์",
d: "เทคโนโลยีสารสนเทศ",
sup: "ปกรณ์ ศิริวัฒน์",
evaluator2: "ธนพล ไชยรักษ์",
},
];

const source = activeStaff.length ? activeStaff : fallback;

return source.map((user, index) => ({
id: user.sso || `mock-${index}`,
employee: `${user.t || ""}${user.n}`,
position: user.p || "บุคลากร",
dept: user.d || "ไม่ระบุหน่วยงาน",
evaluator1: user.sup || "",
evaluator2: user.evaluator2 || "กิตติพงศ์ แสงทอง",
idpDetails: [
{
topic: "AI Literacy",
method: "Workshop + OJT",
due: "ก.ค. 2568",
outcome: "ใช้ AI ช่วยงานประจำได้",
},
{
topic: "การวิเคราะห์ข้อมูล",
method: "Online course",
due: "ส.ค. 2568",
outcome: "ทำ dashboard สรุปงานได้",
},
],
}));
};

export default defineComponent({
name: "ManagerIDPApproval",
props: {
users: { type: Array as PropType<any[]>, default: () => [] },
},
setup(__props) {
const { users } = __props as any;

const [approvedIds, setApprovedIds] = useState<string[]>([]);
const [selectedId, setSelectedId] = useState<string | null>(null);

const approvalRows = getApprovalRows(users);

const approve = (id: string) =>
setApprovedIds((prev: string[]) =>
prev.includes(id) ? prev : [...prev, id]
);

return () => {
// FIX: ดึง .value ใน render function
const selected = approvalRows.find((row) => row.id === selectedId.value);

return (
 <><div class="mb20"><div class="sec-t">อนุมัติแผน IDP รายบุคคล</div><div class="sec-s">
ตรวจสอบแผน IDP จากหัวหน้างานและผู้บังคับบัญชา ก่อนยืนยันแผนพัฒนารายบุคคล
 </div></div><div class="card"><div class="ch"><div class="ct">รายการแผน IDP ที่รอยืนยัน</div></div><div class="cb" style={{ padding: 0 }}><table class="tbl"><thead><tr><th>ผู้ถูกประเมิน</th><th>หัวหน้างาน</th><th>ผู้บังคับบัญชา</th><th>หัวข้อ IDP</th><th style={{ width: "118px" }}>สถานะ</th><th style={{ width: "190px" }}>จัดการ</th></tr></thead><tbody>
{approvalRows.map((row) => {
// FIX: ใช้ approvedIds.value ให้สม่ำเสมอ
const approved = approvedIds.value.includes(row.id);
return (
 <tr key={row.id}><td><div class="fw7 fs13">{row.employee}</div><div class="muted fs11">
{row.position} · {row.dept}
 </div></td><td><div class="fw6 fs12">{row.evaluator1 || "—"}</div></td><td><div class="fw6 fs12">{row.evaluator2}</div></td><td><div class="flex ic g4" style={{ flexWrap: "wrap" }}>
{row.idpDetails.map((item) => (
 <span key={item.topic} class="b bt">
{item.topic}
 </span>
))}
 </div></td><td><span class={`b ${approved ? "bg" : "by"}`}>
{approved ? "ยืนยันแล้ว" : "รอยืนยัน"}
 </span></td><td><div class="flex ic g6"><button
class="btn btn-s btn-xs"
onClick={() => setSelectedId(row.id)}
>
ดูรายละเอียด
 </button><button
class={`btn ${approved ? "btn-g" : "btn-t"} btn-xs`}
disabled={approved}
onClick={() => approve(row.id)}
>
{approved ? "ยืนยันแล้ว" : "ยืนยัน"}
 </button></div></td></tr>
);
})}
 </tbody></table></div></div>

{/* Modal */}
{selected && (
 <div
class="mo"
style={{ zIndex: 300 }}
onMousedown={() => setSelectedId(null)}
><div
class="mo-box"
style={{ width: "720px" }}
onMousedown={(e: MouseEvent) => e.stopPropagation()}
><div class="mo-h"><div><div class="fw8 fs14">{selected.employee}</div><div class="muted fs11">
{selected.evaluator1 || "ไม่มีหัวหน้างาน"} · {selected.evaluator2}
 </div></div><button
class="btn btn-s btn-sm"
onClick={() => setSelectedId(null)}
>
ปิด
 </button></div><div class="mo-b">
{selected.idpDetails.map((item) => (
 <div
key={item.topic}
style={{
display: "grid",
gridTemplateColumns: "150px 150px 100px 1fr",
gap: "10px",
padding: "10px 0",
borderBottom: "1px solid var(--border)",
alignItems: "center",
}}
><span class="b bt" style={{ justifyContent: "center" }}>
{item.topic}
 </span><span class="fw6 fs12">{item.method}</span><span class="b bgr" style={{ justifyContent: "center" }}>
{item.due}
 </span><span class="muted fs12">{item.outcome}</span></div>
))}
 <button
class={`btn ${
approvedIds.value.includes(selected.id) ? "btn-g" : "btn-t"
} btn-sm mt12`}
disabled={approvedIds.value.includes(selected.id)}
onClick={() => approve(selected.id)}
style={{ width: "100%", justifyContent: "center" }}
>
{approvedIds.value.includes(selected.id)
? "ยืนยันแผน IDP แล้ว"
: "ยืนยันแผน IDP"}
 </button></div></div></div>
)}
 </>
);
};
},
});
</script>
