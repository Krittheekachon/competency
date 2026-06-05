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
      w: "สายสนับสนุน",
      sup: "กัญญารัตน์ ศรีวิชา",
      evaluator2: "ธนพล ไชยรักษ์",
    },
    {
      n: "มาลี ดีเสมอ",
      t: "นางสาว",
      p: "นักทรัพยากรบุคคล",
      d: "ทรัพยากรบุคคล",
      w: "สายสนับสนุน",
      sup: "พรพิมล บุคคลดี",
      evaluator2: "ธนพล ไชยรักษ์",
    },
    {
      n: "วิชัย ระบบดี",
      t: "นาย",
      p: "นักวิชาการคอมพิวเตอร์",
      d: "เทคโนโลยีสารสนเทศ",
      w: "สายสนับสนุน",
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
    score: 3 + (index % 3),
    submittedAt: `${18 + (index % 6)} พ.ค. 2568`,
    competencyDetails: [
      {
        n: "AI Literacy",
        expected: 4,
        actual: index % 2 ? 3 : 4,
        note: index % 2 ? "ควรพัฒนาเพิ่มเติม" : "ผ่านตามเกณฑ์",
      },
      {
        n: "การใช้เทคโนโลยีดิจิทัล",
        expected: 4,
        actual: index % 3 ? 3 : 4,
        note: index % 3 ? "มีช่องว่างระดับสมรรถนะ" : "ผ่านตามเกณฑ์",
      },
      { n: "การทำงานเป็นทีม", expected: 3, actual: 3, note: "อยู่ในเกณฑ์" },
    ],
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
  name: "ManagerAssessmentApproval",
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
      // FIX: ดึง .value ใน render function เพื่อให้ reactive ถูกต้อง
      const selected = approvalRows.find((row) => row.id === selectedId.value);

      return (
        <>
          <div class="mb20">
            <div class="sec-t">อนุมัติผลการประเมินรายบุคคล</div>
            <div class="sec-s">
              ตรวจสอบผู้ถูกประเมิน หัวหน้างาน และผู้บังคับบัญชา ก่อนยืนยันผลการประเมิน
            </div>
          </div>

          <div class="card">
            <div class="ch">
              <div class="ct">รายการผลการประเมินที่รอยืนยัน</div>
            </div>
            <div class="cb" style={{ padding: 0 }}>
              <table class="tbl">
                <thead>
                  <tr>
                    <th>ผู้ถูกประเมิน</th>
                    <th>หัวหน้างาน</th>
                    <th>ผู้บังคับบัญชา</th>
                    <th style={{ width: "100px", textAlign: "center" }}>คะแนน</th>
                    <th style={{ width: "118px" }}>สถานะ</th>
                    <th style={{ width: "190px" }}>จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  {approvalRows.map((row) => {
                    // FIX: ใช้ approvedIds.value (ไม่ใช่ approvedIds)
                    const approved = approvedIds.value.includes(row.id);
                    return (
                      <tr key={row.id}>
                        <td>
                          <div class="fw7 fs13">{row.employee}</div>
                          <div class="muted fs11">
                            {row.position} · {row.dept}
                          </div>
                        </td>
                        <td>
                          <div class="fw6 fs12">{row.evaluator1 || "—"}</div>
                        </td>
                        <td>
                          <div class="fw6 fs12">{row.evaluator2}</div>
                        </td>
                        <td style={{ textAlign: "center" }}>
                          <span class="fw8" style={{ color: "var(--blue)" }}>
                            {row.score}
                          </span>
                          <span class="muted fs10"> / 5</span>
                        </td>
                        <td>
                          <span class={`b ${approved ? "bg" : "by"}`}>
                            {approved ? "ยืนยันแล้ว" : "รอยืนยัน"}
                          </span>
                        </td>
                        <td>
                          <div class="flex ic g6">
                            <button
                              class="btn btn-s btn-xs"
                              onClick={() => setSelectedId(row.id)}
                            >
                              ดูรายละเอียด
                            </button>
                            <button
                              class={`btn ${approved ? "btn-g" : "btn-p"} btn-xs`}
                              disabled={approved}
                              onClick={() => approve(row.id)}
                            >
                              {approved ? "ยืนยันแล้ว" : "ยืนยัน"}
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>

          {/* Modal */}
          {selected && (
            <div
              class="mo"
              style={{ zIndex: 300 }}
              onMousedown={() => setSelectedId(null)}
            >
              <div
                class="mo-box"
                style={{ width: "720px" }}
                onMousedown={(e: MouseEvent) => e.stopPropagation()}
              >
                <div class="mo-h">
                  <div>
                    <div class="fw8 fs14">{selected.employee}</div>
                    <div class="muted fs11">
                      {selected.evaluator1 || "ไม่มีหัวหน้างาน"} · {selected.evaluator2}
                    </div>
                  </div>
                  <button
                    class="btn btn-s btn-sm"
                    onClick={() => setSelectedId(null)}
                  >
                    ปิด
                  </button>
                </div>
                <div class="mo-b">
                  {selected.competencyDetails.map((item) => (
                    <div
                      key={item.n}
                      style={{
                        display: "grid",
                        gridTemplateColumns: "1fr 90px 90px 150px",
                        gap: "10px",
                        padding: "10px 0",
                        borderBottom: "1px solid var(--border)",
                        alignItems: "center",
                      }}
                    >
                      <div class="fw7 fs13">
                        {item.n}
                        <div class="muted fs11">{item.note}</div>
                      </div>
                      <span class="b bgr" style={{ justifyContent: "center" }}>
                        คาดหวัง {item.expected}
                      </span>
                      <span
                        class={`b ${item.actual >= item.expected ? "bg" : "br"}`}
                        style={{ justifyContent: "center" }}
                      >
                        ได้ {item.actual}
                      </span>
                      <span class="muted fs11">ส่งเมื่อ {selected.submittedAt}</span>
                    </div>
                  ))}
                  <button
                    class={`btn ${
                      approvedIds.value.includes(selected.id) ? "btn-g" : "btn-p"
                    } btn-sm mt12`}
                    disabled={approvedIds.value.includes(selected.id)}
                    onClick={() => approve(selected.id)}
                    style={{ width: "100%", justifyContent: "center" }}
                  >
                    {approvedIds.value.includes(selected.id)
                      ? "ยืนยันผลการประเมินแล้ว"
                      : "ยืนยันผลการประเมิน"}
                  </button>
                </div>
              </div>
            </div>
          )}
        </>
      );
    };
  },
});
</script>
