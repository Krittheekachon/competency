<script lang="tsx">
// @ts-nocheck
import { defineComponent, ref } from "vue";

const useState = (initial: any) => {
  const state = ref(typeof initial === "function" ? initial() : initial);
  const setState = (next: any) => {
    state.value = typeof next === "function" ? next(state.value) : next;
  };
  return [state, setState] as const;
};

export default defineComponent({
  name: "ManagerIDP",
  props: {
    users: { type: Array, default: () => [] },
    disableMockData: { type: Boolean, default: false },
  },
  setup(__props) {
    const { users, disableMockData = false } = __props as any;

    const [selectedGroup, setSelectedGroup] = useState<string | null>(null);

    const idpStats = disableMockData
      ? { completed: 0, inProgress: 0, submitted: 0, draft: 0, noIdp: 0 }
      : { completed: 18, inProgress: 22, submitted: 13, draft: 10, noIdp: 10 };

    const groupProgress = disableMockData
      ? []
      : [
          {
            d: "สำนักงานคณะฯ",
            total: 12,
            hasIDP: 10,
            idpList: [
              { n: "สมชาย มีสุข", pos: "นักวิชาการ", topic: "ดิจิทัล & AI", status: "in_progress" },
              { n: "วรรณา เพชรดี", pos: "เจ้าหน้าที่", topic: "การสื่อสาร", status: "completed" },
              { n: "มานิตย์ แสง", pos: "นักวิชาการ", topic: "วิเคราะห์ข้อมูล", status: "draft" },
            ],
          },
          {
            d: "ภาควิชาวิศวฯ คอม",
            total: 20,
            hasIDP: 18,
            idpList: [
              { n: "รศ.ดร.วิไล ใจดี", pos: "อาจารย์", topic: "AI Literacy", status: "submitted" },
              { n: "นภัสสร ทองดี", pos: "นักวิชาการ", topic: "AI & Data", status: "in_progress" },
            ],
          },
          {
            d: "ภาควิชาวิศวฯ ไฟฟ้า",
            total: 16,
            hasIDP: 14,
            idpList: [
              { n: "ธนาวุฒิ สว่างใจ", pos: "นักวิเคราะห์", topic: "ดิจิทัล", status: "draft" },
            ],
          },
          {
            d: "ภาควิชาวิศวฯ โยธา",
            total: 18,
            hasIDP: 15,
            idpList: [
              { n: "อรจิรา พรม", pos: "อาจารย์", topic: "AI Literacy", status: "submitted" },
            ],
          },
          {
            d: "ภาควิชาวิศวฯ อุตสาหการ",
            total: 7,
            hasIDP: 6,
            idpList: [
              { n: "สุภาพร แก้วมะณี", pos: "เจ้าหน้าที่", topic: "ดิจิทัล", status: "completed" },
            ],
          },
        ];

    const noProgress = disableMockData
      ? []
      : [
          { n: "มานิตย์ แสง", pos: "นักวิชาการ", d: "สำนักงานคณะฯ", reason: "draft" },
          { n: "ชาญวิทย์ ดีงาม", pos: "เจ้าหน้าที่", d: "สำนักงานคณะฯ", reason: "no_idp" },
          { n: "ธนาวุฒิ สว่างใจ", pos: "นักวิเคราะห์", d: "ภาควิชาวิศวฯ ไฟฟ้า", reason: "draft" },
          { n: "สุมาลี วงศ์ทอง", pos: "นักวิชาการ", d: "ภาควิชาวิศวฯ ไฟฟ้า", reason: "no_idp" },
          { n: "วรรณา แสงทอง", pos: "เจ้าหน้าที่", d: "ภาควิชาวิศวฯ โยธา", reason: "no_idp" },
          { n: "ประภาส ศรีสุข", pos: "อาจารย์", d: "ภาควิชาวิศวฯ โยธา", reason: "draft" },
          { n: "กิตติพงษ์ ทองมา", pos: "อาจารย์", d: "ภาควิชาวิศวฯ อุตสาหการ", reason: "no_idp" },
          { n: "รัตนา พรมมา", pos: "เจ้าหน้าที่", d: "ภาควิชาวิศวฯ อุตสาหการ", reason: "draft" },
        ];

    const userByName = new Map<string, any>(
      (users as any[]).map((user) => [user.n, user])
    );

    const statusMeta: Record<string, { label: string; badge: string }> = {
      completed: { label: "เสร็จสิ้น", badge: "bg" },
      in_progress: { label: "กำลังดำเนินการ", badge: "bt" },
      submitted: { label: "รออนุมัติ", badge: "by" },
      draft: { label: "Draft", badge: "bgr" },
    };

    const noProgressMeta: Record<string, { label: string; badge: string }> = {
      draft: { label: "ยังไม่ส่งแผน (Draft)", badge: "by" },
      no_idp: { label: "ยังไม่มีการทำ IDP", badge: "bgr" },
      rejected: { label: "แผนไม่ผ่านการอนุมัติ", badge: "br" },
    };

    const statLegend = [
      { label: "เสร็จสิ้น", value: idpStats.completed, color: "#16A34A" },
      { label: "กำลังดำเนินการ", value: idpStats.inProgress, color: "#0EA5A0" },
      { label: "รออนุมัติ", value: idpStats.submitted, color: "#FCD34D" },
      { label: "Draft", value: idpStats.draft, color: "#FB923C" },
      { label: "ไม่มี IDP", value: idpStats.noIdp, color: "#EF4444" },
    ];

    const totalFail = groupProgress.reduce((total, group) => total + group.total, 0);
    const totalHasIDP =
      idpStats.completed + idpStats.inProgress + idpStats.submitted + idpStats.draft;
    const pctIDP = totalFail ? Math.round((totalHasIDP / totalFail) * 100) : 0;

    return () => {
      const selectedDetail = groupProgress.find(
        (group) => group.d === selectedGroup.value
      );

      return (
        <>
          <div class="mb20">
            <div class="sec-t">ภาพรวม IDP คณะ 📋</div>
            <div class="sec-s">
              สถานะ IDP ของบุคลากรทั้งคณะวิศวกรรมศาสตร์ · รอบ 2568
            </div>
          </div>

          {/* Summary card */}
          <div
            class="card mb14"
            style={{
              borderLeft: "4px solid var(--teal)",
              background: "linear-gradient(135deg,#fff 60%,var(--teal-lt))",
            }}
          >
            <div class="cb">
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "20px",
                  flexWrap: "wrap",
                }}
              >
                <div style={{ flex: 1, minWidth: "190px" }}>
                  <div class="fw7 fs12 muted mb6">บุคลากรไม่ผ่านเกณฑ์ที่มี IDP แล้ว</div>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "baseline",
                      gap: "8px",
                      flexWrap: "wrap",
                    }}
                  >
                    <span
                      style={{
                        color: "var(--teal)",
                        fontSize: "36px",
                        fontWeight: 800,
                        lineHeight: 1,
                      }}
                    >
                      {totalHasIDP}
                    </span>
                    <span
                      style={{ color: "var(--text3)", fontSize: "16px", fontWeight: 600 }}
                    >
                      / {totalFail} คน
                    </span>
                    <span
                      style={{
                        background: "var(--teal)",
                        borderRadius: "20px",
                        color: "#fff",
                        fontSize: "13px",
                        fontWeight: 800,
                        padding: "3px 10px",
                      }}
                    >
                      {pctIDP}%
                    </span>
                  </div>
                  <div class="fs12 muted mt4">
                    ยังไม่ได้ทำ IDP อีก{" "}
                    <span class="fw7 rc">{idpStats.noIdp} คน</span>
                  </div>
                </div>

                <div style={{ flex: 2, minWidth: "280px" }}>
                  <div class="fs11 fw7 muted mb6">สัดส่วนตามสถานะ</div>
                  <div
                    style={{
                      height: "20px",
                      borderRadius: "6px",
                      overflow: "hidden",
                      display: "flex",
                      background: "var(--border)",
                    }}
                  >
                    {statLegend.map((item) => (
                      <div
                        key={item.label}
                        title={`${item.label} ${item.value} คน`}
                        style={{
                          width: `${totalFail ? (item.value / totalFail) * 100 : 0}%`,
                          background: item.color,
                          transition: ".2s",
                        }}
                      />
                    ))}
                  </div>
                  <div
                    style={{
                      display: "flex",
                      flexWrap: "wrap",
                      gap: "10px",
                      marginTop: "8px",
                    }}
                  >
                    {statLegend.map((item) => (
                      <div key={item.label} class="flex ic g4">
                        <span
                          style={{
                            width: "10px",
                            height: "10px",
                            borderRadius: "3px",
                            background: item.color,
                          }}
                        />
                        <span class={`fs11 ${item.label === "ไม่มี IDP" ? "rc" : ""}`}>
                          {item.label} <b>{item.value}</b>
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Stat cards */}
          <div class="g4 mb14">
            <div class="sc">
              <div class="sl">เสร็จสิ้น</div>
              <div class="sv gcc">{idpStats.completed}</div>
              <div class="ss muted">คน</div>
            </div>
            <div class="sc">
              <div class="sl">กำลังดำเนินการ</div>
              <div class="sv bc">{idpStats.inProgress}</div>
              <div class="ss muted">คน</div>
            </div>
            <div class="sc">
              <div class="sl">รออนุมัติ</div>
              <div class="sv yc">{idpStats.submitted}</div>
              <div class="ss muted">คน</div>
            </div>
            <div class="sc">
              <div class="sl">Draft / ยังไม่มี IDP</div>
              <div class="sv rc">{idpStats.draft + idpStats.noIdp}</div>
              <div class="ss muted">
                Draft {idpStats.draft} · ไม่มี IDP {idpStats.noIdp}
              </div>
            </div>
          </div>

          {/* Two-column section */}
          <div class="g2 mb14">
            {/* Progress by dept */}
            <div class="card">
              <div class="ch">
                <div class="ct">ความคืบหน้า IDP รายหน่วยงาน</div>
              </div>
              <div class="cb" style={{ padding: 0 }}>
                {groupProgress.map((group) => {
                  const pct = Math.round((group.hasIDP / group.total) * 100);
                  const barColor =
                    pct >= 90
                      ? "var(--green)"
                      : pct >= 70
                      ? "var(--teal)"
                      : pct >= 50
                      ? "var(--yellow)"
                      : "var(--red)";
                  return (
                    <div
                      key={group.d}
                      style={{ padding: "12px 16px", borderBottom: "1px solid var(--border)" }}
                    >
                      <div class="flex ic g8 mb8">
                        <span class="fw6 fs13" style={{ flex: 1 }}>
                          {group.d}
                        </span>
                        <span class="fw7 fs12" style={{ color: barColor }}>
                          {group.hasIDP}/{group.total} คน
                        </span>
                        <button
                          class="btn btn-s btn-xs"
                          onClick={() => setSelectedGroup(group.d)}
                        >
                          ดูรายละเอียด
                        </button>
                      </div>
                      <div class="flex ic g8">
                        <div class="pw" style={{ flex: 1, height: "7px", overflow: "hidden" }}>
                          <div
                            class="pb"
                            style={{ width: `${pct}%`, background: barColor }}
                          />
                        </div>
                        <span
                          class="fs11 fw7"
                          style={{ color: barColor, textAlign: "right", width: "36px" }}
                        >
                          {pct}%
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* No progress list */}
            <div class="card">
              <div class="ch">
                <div>
                  <div class="ct">บุคลากรที่ยังไม่มีความคืบหน้า IDP</div>
                  <div class="cs">{noProgress.length} คน</div>
                </div>
                <button
                  class="btn btn-p btn-sm"
                  disabled={disableMockData || noProgress.length === 0}
                  style={{ marginLeft: "auto" }}
                  onClick={() => alert(`ส่งแจ้งเตือนไปยัง ${noProgress.length} คนแล้ว`)}
                >
                  แจ้งเตือนทั้งหมด
                </button>
              </div>
              <div class="cb" style={{ padding: 0 }}>
                {noProgress.map((item) => {
                  const user = userByName.get(item.n);
                  const meta = noProgressMeta[item.reason];
                  return (
                    <div
                      key={item.n}
                      style={{
                        padding: "10px 16px",
                        borderBottom: "1px solid var(--border)",
                        display: "flex",
                        alignItems: "center",
                        gap: "8px",
                      }}
                    >
                      <div
                        class="av"
                        style={{
                          width: "30px",
                          height: "30px",
                          fontSize: "12px",
                          background: "var(--navy)",
                        }}
                      >
                        {item.n[0]}
                      </div>
                      <div style={{ flex: 1 }}>
                        <div class="fw6 fs12">
                          {user ? `${user.t}${user.n}` : item.n}
                        </div>
                        <div class="muted fs11">
                          {item.pos} · {item.d}
                        </div>
                      </div>
                      <span
                        class={`b ${meta.badge}`}
                        style={{
                          maxWidth: "132px",
                          textAlign: "right",
                          whiteSpace: "normal",
                          lineHeight: 1.3,
                        }}
                      >
                        {meta.label}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Detail modal */}
          {selectedDetail && (
            <div
              class="mo"
              style={{ zIndex: 300 }}
              onMousedown={() => setSelectedGroup(null)}
            >
              <div
                class="mo-box"
                style={{ width: "520px" }}
                onMousedown={(e: MouseEvent) => e.stopPropagation()}
              >
                <div class="mo-h">
                  <div class="fw8 fs14">
                    {selectedDetail.d} · รายละเอียด IDP
                  </div>
                  <button
                    class="btn btn-s btn-sm"
                    onClick={() => setSelectedGroup(null)}
                  >
                    ปิด
                  </button>
                </div>
                <div class="mo-b">
                  {selectedDetail.idpList.map((item) => {
                    const meta = statusMeta[item.status];
                    const user = userByName.get(item.n);
                    return (
                      <div
                        key={item.n}
                        style={{
                          padding: "12px 0",
                          borderBottom: "1px solid var(--border)",
                          display: "flex",
                          alignItems: "center",
                          gap: "12px",
                        }}
                      >
                        <div
                          class="av"
                          style={{
                            width: "34px",
                            height: "34px",
                            fontSize: "13px",
                            background: "var(--navy)",
                          }}
                        >
                          {item.n[0]}
                        </div>
                        <div style={{ flex: 1 }}>
                          <div class="fw6 fs13">
                            {user ? `${user.t}${user.n}` : item.n}
                          </div>
                          <div class="muted fs11">
                            {item.pos} · เรื่อง: {item.topic}
                          </div>
                        </div>
                        <span class={`b ${meta.badge}`}>{meta.label}</span>
                      </div>
                    );
                  })}
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