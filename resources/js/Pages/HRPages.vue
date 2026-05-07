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
};export const HRCycle = defineComponent({ name: "HRCycle", props: Object as PropType<{onGoTemplate?: () => void;}>, setup(__props) {const { onGoTemplate } = __props as any;const [modal, setModal] = useState<"new" | "edit" | null>(null);const [editingCycle, setEditingCycle] = useState<any>(null);const [form, setForm] = useState({ n: "", y: "", ss: "", se: "", sup: "" });const [errors, setErrors] = useState({ se: "", sup: "" });const cycles = [{ id: "c1", n: "รอบประเมิน 2568", y: "2568", ss: "2025-04-01", se: "2025-06-30", sup: "2025-07-31", sent: "189/247", act: true }, { id: "c2", n: "รอบประเมิน 2567", y: "2567", ss: "2024-04-01", se: "2024-06-30", sup: "2024-07-31", sent: "240/240", act: false }, { id: "c3", n: "รอบประเมิน 2566", y: "2566", ss: "2023-04-01", se: "2023-06-30", sup: "2023-07-31", sent: "235/235", act: false }];const thM = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];const fmtD = (date: string) => {if (!date) return "";const [y, m, d] = date.split("-");return `${Number(d)} ${thM[Number(m)]} ${Number(y) + 543 - 2500}`;};const openNewCycleModal = () => {
      setForm({ n: "", y: "", ss: "", se: "", sup: "" });
      setErrors({ se: "", sup: "" });
      setEditingCycle(null);
      setModal("new");
    };

    const openEditCycleModal = (cycle: typeof cycles[number]) => {
      setForm({ n: cycle.n, y: cycle.y, ss: cycle.ss, se: cycle.se, sup: cycle.sup });
      setErrors({ se: "", sup: "" });
      setEditingCycle(cycle);
      setModal("edit");
    };

    const validateCycleDates = (next = form.value) => {
      const nextErrors = { se: "", sup: "" };
      if (next.ss && next.se && next.se <= next.ss) {
        nextErrors.se = "⚠ วันปิดรอบต้องอยู่หลังวันเปิดรอบ";
      }
      if (next.ss && next.sup && next.sup < next.ss) {
        nextErrors.sup = "⚠ เวลาสิ้นสุดหัวหน้างานต้องไม่ต่ำกว่าวันเปิดรอบประเมินตนเอง";
      }
      setErrors(nextErrors);
      return !nextErrors.se && !nextErrors.sup;
    };

    const updateForm = (field: keyof typeof form, value: string) => {
      const next = { ...form.value, [field]: value };
      setForm(next);
      if (field === "ss" || field === "se" || field === "sup") validateCycleDates(next);
    };

    const submitCycle = () => {
      if (!form.value.n.trim() || !form.value.y.trim()) {
        alert("กรุณากรอกชื่อรอบประเมินและปีงบประมาณ");
        return;
      }
      if (!validateCycleDates()) return;
      alert(modal.value === "new" ? `เปิดรอบประเมิน "${form.value.n}" แล้ว ✓` : "บันทึกการแก้ไขแล้ว ✓");
      setModal(null);
    };return () =>


    <>
            <div class="flex ic jb mb20">
                <div>
                    <div class="sec-t">รอบการประเมิน 🗓️</div>
                    <div class="sec-s">เปิด-ปิดรอบ กำหนดช่วงเวลา ตรวจสอบสถานะ</div>
                </div>
                <button class="btn btn-p" onClick={openNewCycleModal}>+ เปิดรอบใหม่</button>
            </div>

            <div class="g2 mb14">
                <div class="sc">
                    <div class="sl">รอบที่กำลังเปิดอยู่</div>
                    <div class="sv tc">1</div>
                    <div class="ss muted">ปีงบประมาณ 2568</div>
                </div>
                <div class="sc">
                    <div class="sl">ส่งแบบประเมินแล้ว</div>
                    <div class="sv gcc">189<span style={{ fontSize: '14px', color: 'var(--text3)' }}>/247</span></div>
                    <div class="ss rc">⚠ เหลือ 58 คน</div>
                </div>
            </div>

            <div class="card mb14">
                <div class="ch"><div class="ct">รอบประเมินทั้งหมด</div></div>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>รอบประเมิน</th>
                            <th>ปี</th>
                            <th>รอบประเมินตนเอง</th>
                            <th>เวลาสิ้นสุดหัวหน้างาน</th>
                            <th>ส่งแล้ว</th>
                            <th>สถานะ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {cycles.map((c, i) =>
            <tr key={i}>
                                <td class="fw7">{c.n}</td>
                                <td class="muted">{c.y}</td>
                                <td class="muted fs12">{fmtD(c.ss)} – {fmtD(c.se)}</td>
                                <td class="muted fs12">{fmtD(c.sup)}</td>
                                <td class={`fw6 ${c.act ? 'bc' : 'gcc'}`}>{c.sent}</td>
                                <td><span class={`b ${c.act ? 'bg' : 'bgr'}`}>{c.act ? 'เปิดอยู่' : 'ปิดแล้ว'}</span></td>
                                <td>
                                    <div class="flex g4">
                                        {c.act &&
                  <>
                                                <button class="btn btn-s btn-xs" onClick={() => openEditCycleModal(c)}>แก้ไข</button>
                                                <button class="btn btn-t btn-xs" onClick={onGoTemplate}>🎯 ไปกำหนดความคาดหวัง</button>
                                            </>
                  }
                                    </div>
                                </td>
                            </tr>
            )}
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="ch"><div class="ct">🔔 ส่งการแจ้งเตือน</div></div>
                <div class="cb">
                    <p class="muted fs13 mb16">แจ้งเตือนบุคลากรที่ยังไม่ส่งแบบประเมิน</p>
                    <button class="btn btn-p btn-sm" onClick={() => alert("ส่งแจ้งเตือนไปยัง 58 คนแล้ว ✓")}>🔔 ส่งแจ้งเตือน</button>
                </div>
            </div>

            {modal.value &&
      <div class="cycle-modal">
                    <div class="cycle-modal-box">
                        <div class="cycle-modal-head">
                            <div class="fw7 fs15">{modal.value === "new" ? "เปิดรอบประเมินใหม่" : "แก้ไขรอบประเมิน"}</div>
                            <button onClick={() => setModal(null)} class="cycle-modal-close">✕</button>
                        </div>
                        <div class="cycle-modal-body">
                            <div class="fg mb12">
                                <label class="lbl">ชื่อรอบประเมิน <span style={{ color: "var(--red)" }}>*</span></label>
                                <input class="inp" placeholder="เช่น รอบประเมิน 2569" value={form.value.n} onChange={(e) => updateForm("n", e.target.value)} style={{ marginTop: 4 }} />
                            </div>
                            <div class="fg mb12">
                                <label class="lbl">ปีงบประมาณ <span style={{ color: "var(--red)" }}>*</span></label>
                                <input class="inp" type="number" placeholder="เช่น 2569" value={form.value.y} onChange={(e) => updateForm("y", e.target.value)} style={{ marginTop: 4 }} />
                            </div>
                            <div class="g2 mb12">
                                <div class="fg" style={{ margin: 0 }}>
                                    <label class="lbl">เปิดรอบประเมินตนเอง</label>
                                    <input class="inp" type="date" value={form.value.ss} onChange={(e) => updateForm("ss", e.target.value)} style={{ marginTop: 4 }} />
                                </div>
                                <div class="fg" style={{ margin: 0 }}>
                                    <label class="lbl">ปิดรอบประเมินตนเอง</label>
                                    <input class="inp" type="date" value={form.value.se} onChange={(e) => updateForm("se", e.target.value)} style={{ marginTop: 4 }} />
                                    {errors.value.se && <div class="cycle-field-error">{errors.value.se}</div>}
                                </div>
                            </div>
                            <div class="fg mb20">
                                <label class="lbl">เวลาสิ้นสุดหัวหน้างาน</label>
                                <input class="inp" type="date" value={form.value.sup} onChange={(e) => updateForm("sup", e.target.value)} style={{ marginTop: 4 }} />
                                {errors.value.sup && <div class="cycle-field-error">{errors.value.sup}</div>}
                            </div>
                            <div class="cycle-modal-actions">
                                <button class="btn btn-s btn-sm" onClick={() => setModal(null)}>ยกเลิก</button>
                                <button class="btn btn-p btn-sm" onClick={submitCycle}>{modal.value === "new" ? "✓ เปิดรอบ" : "💾 บันทึก"}</button>
                            </div>
                        </div>
                    </div>
                </div>
      }

            <style>{`
                .cycle-modal { position: fixed; inset: 0; z-index: 900; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.45); }
                .cycle-modal-box { width: 480px; max-width: calc(100vw - 32px); overflow: hidden; border-radius: 16px; background: #fff; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
                .cycle-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid var(--border); }
                .cycle-modal-close { border: 0; background: none; color: var(--text3); cursor: pointer; font-size: 20px; line-height: 1; }
                .cycle-modal-body { padding: 22px; }
                .cycle-field-error { display: block; color: var(--red); font-size: 11px; margin-top: 3px; }
                .cycle-modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
            `}</style>
        </>;} });



export const HRCatalog = defineComponent({ name: "HRCatalog", props: Object as PropType<{openModal: (type: string) => void;}>, setup(__props) {const { openModal } = __props as any;
    const catalog = [
    { n: "OJT / มอบหมายโครงการพิเศษ", t: "experiential", tc: "bo", prov: "หัวหน้างาน", cost: 0, act: true, desc: "มอบหมายงานหรือโครงการจริงให้บุคลากรฝึกปฏิบัติ พร้อมติดตามผลจากหัวหน้างาน" },
    { n: "Job Rotation", t: "experiential", tc: "bo", prov: "ฝ่ายงาน", cost: 0, act: true, desc: "หมุนเวียนงานเพื่อเพิ่มประสบการณ์ข้ามภารกิจและเข้าใจกระบวนการทำงานของหน่วยงาน" },
    { n: "Mentoring Program", t: "social", tc: "bg", prov: "ภายใน", cost: 0, act: true, desc: "จับคู่ผู้มีประสบการณ์กับผู้เรียนรู้ เพื่อแลกเปลี่ยนแนวทางการทำงานและให้คำแนะนำต่อเนื่อง" },
    { n: "Coaching by Supervisor", t: "social", tc: "bg", prov: "ผู้บังคับบัญชา", cost: 0, act: true, desc: "หัวหน้างานให้คำแนะนำเฉพาะจุดจากงานจริง พร้อมสะท้อนผลเพื่อพัฒนาพฤติกรรมการทำงาน" },
    { n: "Peer Learning / Group Activity", t: "social", tc: "bg", prov: "ภายใน", cost: 0, act: true, desc: "เรียนรู้ร่วมกับเพื่อนร่วมงานผ่านกิจกรรมแลกเปลี่ยนประสบการณ์หรือชุมชนนักปฏิบัติ" },
    { n: "อบรม AI & Data Analytics", t: "formal", tc: "bb", prov: "ศูนย์คอมพิวเตอร์", cost: 4500, act: true, desc: "หลักสูตรพัฒนาทักษะการใช้ AI และการวิเคราะห์ข้อมูลเพื่อสนับสนุนการทำงาน" },
    { n: "Workshop การสื่อสาร", t: "formal", tc: "bb", prov: "ภายนอก", cost: 1500, act: true, desc: "เวิร์กชอปฝึกทักษะการสื่อสาร การนำเสนอ และการประสานงานอย่างมีประสิทธิภาพ" },
    { n: "e-Learning ภาษาอังกฤษ", t: "formal", tc: "bb", prov: "KKU Online", cost: 0, act: false, desc: "บทเรียนออนไลน์สำหรับพัฒนาทักษะภาษาอังกฤษในการทำงานและการสื่อสารพื้นฐาน" }];


    const [selectedDesc, setSelectedDesc] = useState<number | null>(null);
    const [selectedEdit, setSelectedEdit] = useState<number | null>(null);
    const fileInputRef = ref<HTMLInputElement>(null);

    const handleDownloadTemplate = () => {
      const rows = [
      ["รหัสสมรรถนะ", "รหัสหลักสูตร", "ชื่อหลักสูตร/บทเรียน", "หมวดหมู่", "ระดับคาดหวัง(จาก)", "ระดับคาดหวัง(ถึง)", "ค่าใช้จ่าย(บาท)", "แหล่งหลักสูตร/ผู้จัด", "คำอธิบาย", "สถานะ"],
      ["CC-001", "FL-001", "หลักสูตรการบริการที่เป็นเลิศ", "formal", "2", "4", "2500", "สถาบันพัฒนาข้าราชการ", "เรียนรู้แนวทางการให้บริการสาธารณะ", "active"],
      ["FC2-061", "EL-015", "โครงการพัฒนาระบบสารสนเทศ", "experiential", "3", "5", "0", "ภายในคณะ", "ฝึกปฏิบัติจริงในงาน", "active"]];

      const csv = rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(",")).join("\n");
      const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "template_learning_catalog.csv";
      link.click();
      URL.revokeObjectURL(url);
    };

    const handleImportClick = () => {
      fileInputRef.current?.click();
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (file) {
        alert(`นำเข้าไฟล์ "${file.name}" เรียบร้อยแล้ว! (Mock Import)`);
      }
    };

    const getMethodLabel = (type: string) => type === 'experiential' ? 'Experiential' : type === 'social' ? 'Social' : 'Formal';
    const getMethodTone = (type: string) => type === 'experiential' ?
    { bg: "var(--orange-bg)", color: "var(--orange)", border: "#fed7aa" } :
    type === 'social' ?
    { bg: "var(--green-bg)", color: "var(--green)", border: "#bbf7d0" } :
    { bg: "var(--blue-lt)", color: "var(--blue)", border: "var(--blue-md)" };
    const activeDesc = selectedDesc.value === null ? null : catalog[selectedDesc.value];
    const activeEdit = selectedEdit.value === null ? null : catalog[selectedEdit.value];return () =>


    <>
            <div class="flex ic jb mb20">
                <div>
                    <div class="sec-t">Learning Catalog 📚</div>
                    <div class="sec-s">ทะเบียนกิจกรรมพัฒนา · บุคลากรเลือกกิจกรรมจาก Catalog นี้เมื่อทำ IDP</div>
                </div>
                <div class="flex" style={{ gap: "8px" }}>
                    <input
            type="file"
            ref={fileInputRef}
            style={{ display: 'none' }}
            accept=".xlsx, .xls, .csv"
            onChange={handleFileChange} />
          
                    <button class="btn btn-s" onClick={handleDownloadTemplate}>📄 ดาวน์โหลด Template</button>
                    <button class="btn btn-s" onClick={handleImportClick}>📥 Import Excel</button>
                    <button class="btn btn-p" onClick={() => openModal("modal-catalog")}>+ เพิ่มกิจกรรม</button>
                </div>
            </div>

            <div class="g3 mb14">
                <div class="sc">
                    <div class="sl">Experiential Learning</div>
                    <div class="sv" style={{ color: "var(--orange)" }}>14</div>
                    <div class="ss muted">กิจกรรม</div>
                </div>
                <div class="sc">
                    <div class="sl">Social Learning</div>
                    <div class="sv gcc">10</div>
                    <div class="ss muted">กิจกรรม</div>
                </div>
                <div class="sc">
                    <div class="sl">Formal Training</div>
                    <div class="sv bc">18</div>
                    <div class="ss muted">หลักสูตร</div>
                </div>
            </div>

            <div class="card">
                <div class="ch">
                    <div class="ct">Learning Catalog</div>
                    <span class="muted fs12" style={{ marginLeft: "auto" }}>{catalog.length} รายการ</span>
                </div>
                <div style={{ overflowX: "auto", borderRadius: "0 0 var(--r) var(--r)" }}>
                    <table class="tbl" style={{ minWidth: 820 }}>
                        <thead>
                            <tr>
                                <th>ชื่อกิจกรรม </th>
                                <th>ประเภท </th>
                                <th>ผู้จัด </th>
                                <th>ค่าใช้จ่าย </th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {catalog.map((c, i) =>
              <tr key={i}>
                                    <td class="fw6 fs13">{c.n}</td>
                                    <td>
                                        <span class={`b ${c.tc}`}>
                                            {getMethodLabel(c.t)}
                                        </span>
                                    </td>
                                    <td class="muted fs12">{c.prov}</td>
                                    <td class="muted fs12">{c.cost === 0 ? 'ฟรี' : c.cost.toLocaleString() + ' ฿'}</td>
                                    <td><span class={`b ${c.act ? 'bg' : 'bgr'}`}>{c.act ? 'เปิดใช้' : 'ปิด'}</span></td>
                                    <td>
                                        <div class="flex g4" style={{ flexWrap: "wrap" }}>
                                            <button class="btn btn-s btn-xs" onClick={() => setSelectedDesc(i)}>📄 คำอธิบาย</button>
                                            <button class="btn btn-s btn-xs" onClick={() => setSelectedEdit(i)}>แก้ไข</button>
                                        </div>
                                    </td>
                                </tr>
              )}
                        </tbody>
                    </table>
                </div>
            </div>
            {activeDesc &&
      <div class="mo" onClick={() => setSelectedDesc(null)}>
                    <div class="mo-box" style={{ width: 620, maxWidth: "calc(100vw - 32px)", overflow: "hidden" }} onClick={(e) => e.stopPropagation()}>
                        <div class="mo-h" style={{ alignItems: "flex-start", gap: 16, background: "linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)" }}>
                            <div style={{ display: "flex", gap: 12, minWidth: 0 }}>
                                <span style={{ width: 42, height: 42, borderRadius: 12, display: "inline-flex", alignItems: "center", justifyContent: "center", background: "var(--blue-lt)", color: "var(--blue)", fontSize: 22, flexShrink: 0 }}>📄</span>
                                <div style={{ minWidth: 0 }}>
                                    <div style={{ fontSize: 18, fontWeight: 900, color: "var(--text)", lineHeight: 1.35 }}>{activeDesc.n}</div>
                                    <div class="muted fs12" style={{ marginTop: 3 }}>รายละเอียดกิจกรรมสำหรับใช้ประกอบการทำ IDP</div>
                                </div>
                            </div>
                            <button class="btn btn-s btn-sm" onClick={() => setSelectedDesc(null)}>✕</button>
                        </div>
                        <div class="mo-b" style={{ padding: 22 }}>
                            <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 16 }}>
                                <span class="b" style={{ background: getMethodTone(activeDesc.t).bg, color: getMethodTone(activeDesc.t).color, border: `1px solid ${getMethodTone(activeDesc.t).border}` }}>
                                    {getMethodLabel(activeDesc.t)}
                                </span>
                                <span class={`b ${activeDesc.act ? 'bg' : 'bgr'}`}>{activeDesc.act ? 'เปิดใช้งาน' : 'ปิดใช้งาน'}</span>
                            </div>
                            <div style={{ padding: "16px 18px", borderRadius: 10, background: "#f8fafc", border: "1px solid var(--border)", borderLeft: "4px solid var(--blue)", color: "var(--text2)", fontSize: 13, lineHeight: 1.8, marginBottom: 14 }}>
                                <div class="fw8" style={{ color: "var(--text)", marginBottom: 6 }}>คำอธิบายหลักสูตร</div>
                                {activeDesc.desc}
                            </div>
                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                                <div style={{ padding: "13px 14px", border: "1px solid var(--border)", borderRadius: 9, background: "#fff" }}>
                                    <div class="muted fs11 fw7" style={{ marginBottom: 6 }}>ผู้จัด / ผู้ให้บริการ</div>
                                    <div class="fw8 fs13" style={{ color: "var(--text2)" }}>{activeDesc.prov}</div>
                                </div>
                                <div style={{ padding: "13px 14px", border: "1px solid var(--border)", borderRadius: 9, background: "#fff" }}>
                                    <div class="muted fs11 fw7" style={{ marginBottom: 6 }}>ค่าใช้จ่าย</div>
                                    <div class="fw8 fs13" style={{ color: activeDesc.cost === 0 ? "var(--green)" : "var(--text2)" }}>{activeDesc.cost === 0 ? 'ฟรี' : activeDesc.cost.toLocaleString() + ' ฿'}</div>
                                </div>
                            </div>
                            <div style={{ display: "flex", justifyContent: "flex-end", marginTop: 18 }}>
                                <button class="btn btn-p" onClick={() => setSelectedDesc(null)}>ปิด</button>
                            </div>
                        </div>
                    </div>
                </div>
      }
            {activeEdit &&
      <div class="mo" onClick={() => setSelectedEdit(null)}>
                    <div class="mo-box" style={{ width: 560, maxWidth: "calc(100vw - 32px)", overflow: "hidden" }} onClick={(e) => e.stopPropagation()}>
                        <div class="mo-h" style={{ background: "linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)" }}>
                            <div>
                                <div style={{ fontSize: 18, fontWeight: 900, color: "var(--text)" }}>แก้ไขกิจกรรม Learning Catalog</div>
                                <div class="muted fs12" style={{ marginTop: 3 }}>ปรับข้อมูลกิจกรรมพัฒนาที่บุคลากรเลือกใช้ใน IDP</div>
                            </div>
                            <button class="btn btn-s btn-sm" onClick={() => setSelectedEdit(null)}>✕</button>
                        </div>
                        <div class="mo-b" style={{ padding: 22 }}>
                            <div class="fg">
                                <label class="lbl">ชื่อกิจกรรม / หลักสูตร</label>
                                <input class="inp" defaultValue={activeEdit.n} />
                            </div>
                            <div class="fg">
                                <label class="lbl">ประเภทกิจกรรม</label>
                                <select class="sel" defaultValue={activeEdit.t}>
                                    <option value="experiential">Experiential Learning</option>
                                    <option value="social">Social Learning</option>
                                    <option value="formal">Formal Training</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label class="lbl">ผู้จัด / ผู้ให้บริการ</label>
                                <input class="inp" defaultValue={activeEdit.prov} />
                            </div>
                            <div class="fg">
                                <label class="lbl">ค่าใช้จ่าย (บาท)</label>
                                <input class="inp" type="number" min="0" defaultValue={activeEdit.cost} />
                            </div>
                            <div class="fg">
                                <label class="lbl">คำอธิบายหลักสูตร</label>
                                <textarea class="ta" style={{ fontSize: 12, minHeight: 82, marginTop: 4 }} defaultValue={activeEdit.desc} />
                            </div>
                            <div class="fg" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <label class="lbl" style={{ margin: 0 }}>สถานะ</label>
                                <input type="checkbox" defaultChecked={activeEdit.act} style={{ width: 16, height: 16, cursor: 'pointer' }} />
                                <span class="fs12 muted">เปิดใช้งาน</span>
                            </div>
                            <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", paddingTop: 14, borderTop: "1px solid var(--border)" }}>
                                <button class="btn btn-s" onClick={() => setSelectedEdit(null)}>ยกเลิก</button>
                                <button class="btn btn-p" onClick={() => {setSelectedEdit(null);alert("บันทึกการแก้ไขเรียบร้อยแล้ว!");}}>💾 บันทึก</button>
                            </div>
                        </div>
                    </div>
                </div>
      }
        </>;} });



export const HRMonitor = defineComponent({ name: "HRMonitor", props: Object as PropType<any>, setup(__props) {
    const deptStats = [
    { d: "สำนักงานคณะฯ", tot: 45, ass: 41, idp: 38, p: 91 },
    { d: "ฝ่ายแผนยุทธศาสตร์ฯ", tot: 32, ass: 28, idp: 25, p: 88 },
    { d: "ฝ่ายการศึกษาฯ", tot: 28, ass: 18, idp: 14, p: 64 },
    { d: "ฝ่ายวิจัยฯ", tot: 24, ass: 12, idp: 10, p: 50 },
    { d: "ฝ่ายบริหาร", tot: 20, ass: 20, idp: 20, p: 100 }];


    const topNeeds = [
    { n: "AI Literacy", c: 89 },
    { n: "การวิเคราะห์ข้อมูล", c: 72 },
    { n: "ภาษาอังกฤษ", c: 58 },
    { n: "การจัดการโครงการ", c: 45 },
    { n: "การสื่อสาร", c: 38 }];return () =>



    <>
            <div class="mb20">
                <div class="sec-t">ติดตามภาพรวม 📡</div>
                <div class="sec-s">คณะวิศวกรรมศาสตร์ · รอบประเมิน 2568</div>
            </div>

            <div class="g4 mb14">
                <div class="sc">
                    <div class="sl">บุคลากรทั้งหมด</div>
                    <div class="sv">247</div>
                    <div class="ss muted">วิชาการ 148 · สนับสนุน 99</div>
                </div>
                <div class="sc">
                    <div class="sl">ส่งแบบประเมินแล้ว</div>
                    <div class="sv gcc">189<span style={{ fontSize: '14px', color: 'var(--text3)' }}>/247</span></div>
                    <div class="ss rc">เหลือ 58 คน</div>
                </div>
                <div class="sc">
                    <div class="sl">IDP อนุมัติแล้ว</div>
                    <div class="sv bc">153</div>
                    <div class="ss muted">62%</div>
                </div>
                <div class="sc">
                    <div class="sl">IDP รออนุมัติ</div>
                    <div class="sv yc">14</div>
                    <div class="ss muted">รอ Supervisor</div>
                </div>
            </div>

            <div class="g2 mb14">
                <div class="card">
                    <div class="ch"><div class="ct">สถานะรายหน่วยงาน</div></div>
                    <div class="cb" style={{ paddingTop: '10px' }}>
                        {deptStats.map((d, i) =>
            <div key={i} style={{ marginBottom: '12px' }}>
                                <div class="flex ic jb mb4">
                                    <span class="fs12 fw6">{d.d}</span>
                                    <span class="muted fs11">ประเมิน {d.ass}/{d.tot} · IDP {d.idp}/{d.tot}</span>
                                </div>
                                <div class="pw" style={{ height: '7px' }}>
                                    <div class="pb" style={{ width: `${d.p}%`, background: d.p >= 80 ? 'var(--green)' : d.p >= 60 ? 'var(--blue)' : 'var(--yellow)' }}></div>
                                </div>
                            </div>
            )}
                    </div>
                </div>

                <div class="card">
                    <div class="ch"><div class="ct">Top 5 Training Need (TNA)</div></div>
                    <div class="cb">
                        {topNeeds.map((n, i) =>
            <div key={i} class="flex ic g8 mb12">
                                <div style={{ width: "22px", height: "22px", borderRadius: "50%", background: "var(--navy)", color: "#fff", fontSize: "10px", fontWeight: 800, display: "flex", alignItems: "center", justifyCenter: "center", justifyContent: "center", flexShrink: 0 }}>{i + 1}</div>
                                <div style={{ flex: 1 }}>
                                    <div class="fw6 fs12 mb4">{n.n}</div>
                                    <div class="pw" style={{ height: '5px' }}>
                                        <div class="pb" style={{ width: `${n.c}%`, background: 'var(--red)' }}></div>
                                    </div>
                                </div>
                                <span class="fs12 fw8 rc" style={{ width: "28px", textAlign: "right" }}>{n.c}</span>
                            </div>
            )}
                    </div>
                </div>
            </div>
        </>;} });



type HRPositionScope = {
  workline: string;
  jobFamily: string;
  position: string;
};

type ExpectedTemplate = {
  cd: string;
  n: string;
  t: string;
  tg: string;
  lv: number;
};

type ImportConflict = {
  key: string;
  current: ExpectedTemplate[];
  imported: ExpectedTemplate[];
};

export const HRTemplate = defineComponent({ name: "HRTemplate", props: Object as PropType<{
    worklines: string[];
    academicPositions: string[];
    supportPositionGroups: Record<string, string[]>;
    adminPositions: string[];
    academicLevels: string[];
    supportLevels: string[];
    onOpenPositionBinding: (scope: HRPositionScope) => void;
  }>, setup(__props) {const {
      worklines,
      academicPositions,
      supportPositionGroups,
      adminPositions,
      academicLevels,
      supportLevels,
      onOpenPositionBinding
    } = __props as any;
    const supportFamilies = Object.keys(supportPositionGroups);
    const firstSupportFamily = supportFamilies[0] || "";
    const [selectedWorkline, setSelectedWorkline] = useState("สายสนับสนุน");
    const [selectedFamily, setSelectedFamily] = useState(firstSupportFamily);
    const [selectedPosition, setSelectedPosition] = useState(supportPositionGroups[firstSupportFamily]?.[0] || "");
    const [selectedLevel, setSelectedLevel] = useState(supportLevels[0] || "");
    const [selectedCycle, setSelectedCycle] = useState("รอบประเมิน 2567");
    const [activeTab, setActiveTab] = useState<"edit" | "all">("all");
    const [status, setStatus] = useState<string | null>(null);
    const [showImportModal, setShowImportModal] = useState(false);
    const [showConflictModal, setShowConflictModal] = useState(false);
    const [importSourceCycle, setImportSourceCycle] = useState("");
    const [importConflicts, setImportConflicts] = useState<ImportConflict[]>([]);
    const [importChoices, setImportChoices] = useState<Record<string, "current" | "imported" | null>>({});
    const [importNonConflicts, setImportNonConflicts] = useState<Record<string, ExpectedTemplate[]>>({});
    const [pendingImportMerge, setPendingImportMerge] = useState<Record<string, ExpectedTemplate[]> | null>(null);
    const [importMode, setImportMode] = useState<"confirm" | "conflict">("confirm");
    const cycles = ["รอบประเมิน 2568", "รอบประเมิน 2567", "รอบประเมิน 2566"];
    const activeCycle = "รอบประเมิน 2568";
    const isClosedCycle = selectedCycle.value !== activeCycle;
    const baseTemplates: ExpectedTemplate[] = [
    { cd: "CC-001", n: "การบริการที่ดี", t: "CC", tg: "tag-cc", lv: 3 },
    { cd: "CC-002", n: "การมุ่งผลสัมฤทธิ์", t: "CC", tg: "tag-cc", lv: 3 },
    { cd: "CC-005", n: "AI Literacy", t: "CC", tg: "tag-cc", lv: 3 },
    { cd: "FC2-061", n: "การใช้เทคโนโลยีดิจิทัล", t: "FC", tg: "tag-fc", lv: 3 },
    { cd: "FC2-062", n: "การวิเคราะห์ข้อมูล", t: "FC", tg: "tag-fc", lv: 2 },
    { cd: "FC2-063", n: "การสื่อสาร", t: "FC", tg: "tag-fc", lv: 3 }];


    const makeScopeKey = (workline = selectedWorkline, family = getScopeFamily(), position = selectedPosition, level = workline === "สายงานบริหาร" ? position : selectedLevel.value) =>
    `${workline}|${family}|${position}|${level}`;

    const makeRows = (rows = baseTemplates) => rows.map((row) => ({ ...row }));

    const [savedExpectations, setSavedExpectations] = useState<Record<string, Record<string, ExpectedTemplate[]>>>({
      "รอบประเมิน 2567": {
        "สายงานบริหาร|คณะวิศวกรรมศาสตร์|คณบดี|คณบดี": [
        { cd: "CC-001", n: "การบริการที่ดี", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-002", n: "การมุ่งผลสัมฤทธิ์", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "MC-001", n: "Visionary Leadership", t: "MC", tg: "tag-mc", lv: 3 },
        { cd: "MC-002", n: "การบริหารทีม", t: "MC", tg: "tag-mc", lv: 3 }],

        "สายวิชาการ|สายวิชาการ|นักวิจัย|นักวิชาการวิจัยระดับ 1": [
        { cd: "CC-001", n: "การบริการที่ดี", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-002", n: "การมุ่งผลสัมฤทธิ์", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-005", n: "AI Literacy", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "FC1-012", n: "การวิจัยและนวัตกรรม", t: "FC", tg: "tag-fc", lv: 3 },
        { cd: "FC2-062", n: "การวิเคราะห์ข้อมูล", t: "FC", tg: "tag-fc", lv: 3 }],

        "สายวิชาการ|สายวิชาการ|อาจารย์|อาจารย์": [
        { cd: "CC-001", n: "การบริการที่ดี", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-002", n: "การมุ่งผลสัมฤทธิ์", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-005", n: "AI Literacy", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "FC1-011", n: "การสอนและถ่ายทอด", t: "FC", tg: "tag-fc", lv: 3 },
        { cd: "FC1-012", n: "การวิจัยและนวัตกรรม", t: "FC", tg: "tag-fc", lv: 3 }],

        "สายสนับสนุน|สนับสนุนเทคโนโลยีดิจิทัล|นักวิชาการคอมพิวเตอร์|ปฏิบัติการ": [
        { cd: "CC-001", n: "การบริการที่ดี", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-005", n: "AI Literacy", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "FC2-061", n: "การใช้เทคโนโลยีดิจิทัล", t: "FC", tg: "tag-fc", lv: 3 },
        { cd: "FC2-062", n: "การวิเคราะห์ข้อมูล", t: "FC", tg: "tag-fc", lv: 3 },
        { cd: "FC2-063", n: "การสื่อสารองค์กร", t: "FC", tg: "tag-fc", lv: 3 }],

        "สายสนับสนุน|สนับสนุนการศึกษาและวิชาการ|นักวิชาการศึกษา|ปฏิบัติการ": [
        { cd: "CC-001", n: "การบริการที่ดี", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-002", n: "การมุ่งผลสัมฤทธิ์", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "CC-005", n: "AI Literacy", t: "CC", tg: "tag-cc", lv: 3 },
        { cd: "FC2-061", n: "การใช้เทคโนโลยีดิจิทัล", t: "FC", tg: "tag-fc", lv: 3 },
        { cd: "FC2-063", n: "การสื่อสารองค์กร", t: "FC", tg: "tag-fc", lv: 3 }]

      }
    });
    const [templates, setTemplates] = useState<ExpectedTemplate[]>(makeRows());

    const getPositionOptions = () => {
      if (selectedWorkline === "สายวิชาการ") return academicPositions;
      if (selectedWorkline === "สายงานบริหาร") return adminPositions;
      return supportPositionGroups[selectedFamily] || [];
    };

    const getLevelOptions = () => {
      if (selectedWorkline === "สายวิชาการ") return academicLevels;
      return supportLevels;
    };

    const getScopeFamily = () => {
      if (selectedWorkline === "สายวิชาการ") return "สายวิชาการ";
      if (selectedWorkline === "สายงานบริหาร") return "คณะวิศวกรรมศาสตร์";
      return selectedFamily;
    };

    const isAdminWorkline = selectedWorkline === "สายงานบริหาร";
    const currentScopeLevel = isAdminWorkline ? selectedPosition : selectedLevel.value;
    const currentScopeKey = makeScopeKey();
    const savedRows = savedExpectations.value[selectedCycle.value]?.[currentScopeKey];
    const isSaved = Boolean(savedRows);
    const cycleSummary = (Object.entries(savedExpectations.value[selectedCycle.value] || {}) as [string, ExpectedTemplate[]][]).map(([key, rows]) => {
      const [workline, jobFamily, position, level] = key.split("|");
      return { key, workline, jobFamily, position, level, rows };
    });
    const lineOrder = ["สายบริหาร", "สายวิชาการ", "สายสนับสนุน"];
    const groupedCycleSummary = lineOrder.
    map((workline) => ({
      workline,
      rows: cycleSummary.
      filter((item) => item.workline === workline || workline === "สายบริหาร" && item.workline === "สายงานบริหาร").
      sort((a, b) => {
        const positionCompare = a.position.localeCompare(b.position, "th");
        if (positionCompare !== 0) return positionCompare;
        return b.level.localeCompare(a.level, "th");
      })
    })).
    filter((group) => group.rows.length > 0);
    const rowsByType = (rows: ExpectedTemplate[]) => {
      const order = ["CC", "MC", "FC"];
      return order.
      map((type) => ({ type, rows: rows.filter((row) => row.t === type) })).
      filter((group) => group.rows.length > 0);
    };

    useEffect(() => {
      setTemplates(makeRows(savedExpectations.value[selectedCycle.value]?.[currentScopeKey] || baseTemplates));
    }, [selectedCycle.value, currentScopeKey]);

    const setWorklineScope = (value: string) => {
      setSelectedWorkline(value);
      if (value === "สายวิชาการ") {
        setSelectedFamily("สายวิชาการ");
        setSelectedPosition(academicPositions[0] || "");
        setSelectedLevel(academicLevels[0] || "");
      } else if (value === "สายงานบริหาร") {
        const nextPosition = adminPositions[0] || "";
        setSelectedFamily("คณะวิศวกรรมศาสตร์");
        setSelectedPosition(nextPosition);
        setSelectedLevel(nextPosition);
      } else {
        const nextFamily = supportFamilies[0] || "";
        setSelectedFamily(nextFamily);
        setSelectedPosition(supportPositionGroups[nextFamily]?.[0] || "");
        setSelectedLevel(supportLevels[0] || "");
      }
    };

    const setFamilyScope = (value: string) => {
      const nextPositions = supportPositionGroups[value] || [];
      setSelectedFamily(value);
      setSelectedPosition(nextPositions[0] || "");
    };

    const setExpectedLevel = (index: number, level: number) => {
      setTemplates((current) => current.map((template, idx) =>
      idx === index ? { ...template, lv: level } : template
      ));
    };

    const saveExpectations = () => {
      if (!selectedWorkline || !selectedPosition || !isAdminWorkline && !selectedLevel.value) {
        setStatus("กรุณาเลือกสายงาน ตำแหน่ง และระดับให้ครบก่อนบันทึก");
        return;
      }
      setSavedExpectations((current) => ({
        ...current,
        [selectedCycle]: {
          ...(current[selectedCycle.value] || {}),
          [currentScopeKey]: makeRows(templates.value)
        }
      }));
      setStatus(`บันทึกความคาดหวังของ ${selectedPosition} แล้ว`);
    };

    const deleteExpectations = () => {
      setSavedExpectations((current) => {
        const nextCycle = { ...(current[selectedCycle.value] || {}) };
        delete nextCycle[currentScopeKey];
        return { ...current, [selectedCycle]: nextCycle };
      });
      setTemplates(makeRows(baseTemplates));
      setStatus(`ลบชุดความคาดหวังของ ${selectedPosition} แล้ว`);
    };

    const jumpToSummary = (key: string) => {
      const [workline, jobFamily, position, level] = key.split("|");
      setSelectedWorkline(workline);
      setSelectedFamily(jobFamily);
      setSelectedPosition(position);
      setSelectedLevel(level);
      setActiveTab("edit");
    };

    const getScopeLabel = (key: string) => {
      const [workline, jobFamily, position, level] = key.split("|");
      return { workline, jobFamily, position, level };
    };

    const isSameRows = (a: ExpectedTemplate[], b: ExpectedTemplate[]) =>
    JSON.stringify(a.map((row) => ({ cd: row.cd, lv: row.lv }))) ===
    JSON.stringify(b.map((row) => ({ cd: row.cd, lv: row.lv })));

    const openImportModal = () => {
      setShowImportModal(true);
    };

    const analyzeImportCycle = (sourceCycle: string) => {
      const source: Record<string, ExpectedTemplate[]> = savedExpectations.value[sourceCycle] || {};
      const current: Record<string, ExpectedTemplate[]> = savedExpectations.value[selectedCycle.value] || {};
      const sourceEntries = Object.entries(source) as [string, ExpectedTemplate[]][];
      if (sourceEntries.length === 0) {
        setStatus(`${sourceCycle} ยังไม่มีข้อมูลความคาดหวังให้ดึงมาใช้`);
        return;
      }
      const conflicts: ImportConflict[] = [];
      const nonConflicts: Record<string, ExpectedTemplate[]> = {};

      sourceEntries.forEach(([key, importedRows]) => {
        const currentRows = current[key];
        if (!currentRows || isSameRows(currentRows, importedRows)) {
          nonConflicts[key] = makeRows(currentRows || importedRows);
        } else {
          conflicts.push({ key, current: makeRows(currentRows), imported: makeRows(importedRows) });
        }
      });
      (Object.entries(current) as [string, ExpectedTemplate[]][]).forEach(([key, currentRows]) => {
        if (!source[key]) nonConflicts[key] = makeRows(currentRows);
      });

      setShowImportModal(false);
      setImportSourceCycle(sourceCycle);
      setImportNonConflicts(nonConflicts);
      setImportConflicts(conflicts);
      setImportChoices(Object.fromEntries(conflicts.map((conflict) => [conflict.key, null])));

      if (conflicts.length === 0) {
        setPendingImportMerge(Object.fromEntries(Object.entries(nonConflicts).map(([key, rows]) => [key, makeRows(rows)])));
        setImportMode("confirm");
      } else {
        setPendingImportMerge(null);
        setImportMode("conflict");
      }
      setShowConflictModal(true);
    };

    const closeImportReview = () => {
      setShowConflictModal(false);
      setPendingImportMerge(null);
      setImportConflicts([]);
      setImportChoices({});
      setImportNonConflicts({});
      setImportSourceCycle("");
    };

    const chooseImportConflict = (key: string, choice: "current" | "imported") => {
      setImportChoices((current) => ({ ...current, [key]: choice }));
    };

    const chooseAllImportConflicts = (choice: "current" | "imported") => {
      setImportChoices(Object.fromEntries(importConflicts.value.map((conflict) => [conflict.key, choice])));
    };

    const allConflictsChosen = importConflicts.value.every((conflict) => importChoices.value[conflict.key]);

    const applyNoConflictImport = () => {
      if (!pendingImportMerge.value) return;
      setSavedExpectations((current) => ({
        ...current,
        [selectedCycle]: Object.fromEntries((Object.entries(pendingImportMerge.value) as [string, ExpectedTemplate[]][]).map(([key, rows]) => [key, makeRows(rows)]))
      }));
      setActiveTab("all");
      setStatus(`นำเข้าสำเร็จ ${Object.keys(importNonConflicts.value).length} ชุดจาก ${importSourceCycle.value}`);
      closeImportReview();
    };

    const applyConflictImport = () => {
      if (!allConflictsChosen) {
        setStatus("กรุณาเลือกว่าจะยึดข้อมูลชุดไหนให้ครบก่อนนำเข้า");
        return;
      }
      const merged: Record<string, ExpectedTemplate[]> = Object.fromEntries(
        (Object.entries(importNonConflicts.value) as [string, ExpectedTemplate[]][]).map(([key, rows]) => [key, makeRows(rows)])
      );
      importConflicts.value.forEach((conflict) => {
        const choice = importChoices.value[conflict.key];
        merged[conflict.key] = makeRows(choice === "current" ? conflict.current : conflict.imported);
      });
      const currentCount = importConflicts.value.filter((conflict) => importChoices.value[conflict.key] === "current").length;
      const importedCount = importConflicts.value.length - currentCount;
      setSavedExpectations((current) => ({ ...current, [selectedCycle]: merged }));
      setActiveTab("all");
      setStatus(`นำเข้าสำเร็จ: ยึดของเดิม ${currentCount} ชุด · ยึดที่นำเข้า ${importedCount} ชุด · อัตโนมัติ ${Object.keys(importNonConflicts.value).length} ชุด`);
      closeImportReview();
    };

    const openPositionBinding = () => {
      onOpenPositionBinding({
        workline: selectedWorkline,
        jobFamily: getScopeFamily(),
        position: selectedPosition
      });
    };return () =>


    <>
            <div class="flex ic jb mb20">
                <div>
                    <div class="sec-t">กำหนดความคาดหวังการประเมิน 🎯</div>
                    <div class="sec-s">ตั้งค่า Expected Level ของแต่ละประเภทบุคลากรในแต่ละรอบการประเมิน</div>
                </div>
            </div>

            {status &&
      <div class="status-msg anim-fade-in" style={{ marginBottom: "14px", padding: "10px 14px", borderRadius: "var(--r)", background: "var(--green-bg)", border: "1px solid var(--green-md)", color: "var(--green)", fontWeight: 700, display: "flex", justifyContent: "space-between", gap: "12px" }}>
                    <span>{status}</span>
                    <button class="btn-link" style={{ color: "var(--green)" }} onClick={() => setStatus(null)}>ปิด</button>
                </div>
      }

            <div class="card mb14">
                <div class="ch"><div class="ct">① เลือกรอบการประเมิน</div></div>
                <div class="cb cycle-picker">
                    {cycles.map((cycle) => {
            const isActive = selectedCycle.value === cycle;
            const savedCount = Object.keys(savedExpectations.value[cycle] || {}).length;
            return (
              <button
                key={cycle}
                type="button"
                class={`cycle-chip ${isActive ? "on" : ""}`}
                onClick={() => {
                  setSelectedCycle(cycle);
                  if (cycle !== activeCycle) setActiveTab("all");
                }}>
                
                                <span>{cycle}</span>
                                <small>{savedCount} ชุดที่กำหนดแล้ว</small>
                            </button>);

          })}
                </div>
            </div>

            <div class="expect-tabs mb14">
                {!isClosedCycle &&
        <button class={`expect-tab ${activeTab.value === "edit" ? "on" : ""}`} onClick={() => setActiveTab("edit")}>กำหนด / แก้ไข</button>
        }
                <button class={`expect-tab ${activeTab.value === "all" ? "on" : ""}`} onClick={() => setActiveTab("all")}>
                    ดูความคาดหวังทั้งหมด <span class={`b ${cycleSummary.length ? "bt" : "bgr"}`}>{cycleSummary.length} ชุด</span>
                </button>
                {isClosedCycle ?
        <div class="expect-locked">🔒 รอบนี้ปิดแล้ว ไม่สามารถแก้ไขได้</div> :

        <button class="btn btn-p btn-sm ml-auto" onClick={openImportModal}>นำเข้าความคาดหวัง</button>
        }
            </div>

            {activeTab.value === "all" ?
      <div class="expect-all-list">
                    {groupedCycleSummary.map((group) =>
        <div class="card expect-all-card" key={group.workline}>
                            <div class="ch expect-all-head">
                                <span class="b bt expect-workline-pill">{group.workline}</span>
                                <span class="muted fs12" style={{ marginLeft: 6 }}>{group.rows.length} ตำแหน่ง / ระดับที่กำหนดแล้ว</span>
                            </div>
                            {group.rows.map((item) =>
          <div key={item.key} class="expect-all-row">
                                    <div class="expect-all-meta">
                                        <span class="expect-position-name">{item.position}</span>
                                        <span class="b bgr">{item.level}</span>
                                        <span class="expect-comp-count">{item.rows.length} สมรรถนะ</span>
                                        {!isClosedCycle &&
              <button class="btn btn-s btn-xs expect-edit-btn" onClick={() => jumpToSummary(item.key)}>แก้ไข</button>
              }
                                    </div>
                                    <div class="expect-comp-lines">
                                        {rowsByType(item.rows).map((groupedRows) =>
              <div key={`${item.key}-${groupedRows.type}`} class="expect-comp-line">
                                                <span class={`expect-type-badge ${groupedRows.type.toLowerCase()}`}>{groupedRows.type}</span>
                                                <div class="expect-comp-chips">
                                                    {groupedRows.rows.map((row) =>
                  <span key={row.cd} class="expect-comp-chip">
                                                            <span>{row.n}</span>
                                                            <b>{row.lv}</b>
                                                        </span>
                  )}
                                                </div>
                                            </div>
              )}
                                    </div>
                                </div>
          )}
                        </div>
        )}
                    {cycleSummary.length === 0 &&
        <div class="card">
                            <div class="muted ac" style={{ padding: "48px 16px" }}>ยังไม่มีการกำหนดในรอบนี้</div>
                        </div>
        }
                </div> :

      <div class="expect-layout mb14">
                <div class="card">
                    <div class="ch">
                        <div>
                            <div class="ct">กรองดูตามประเภทบุคลากร</div>
                            <div class="cs">เลือกกลุ่มบุคลากรที่ต้องการกำหนด Expected Level</div>
                        </div>
                    </div>
                    <div class="cb">
                        <div class="fg">
                            <label class="lbl">สายงาน</label>
                            <select class="sel" value={selectedWorkline} onChange={(e) => setWorklineScope(e.target.value)}>
                                {worklines.map((workline) => <option key={workline} value={workline}>{workline === "สายงานบริหาร" ? "สายบริหาร" : workline}</option>)}
                            </select>
                        </div>
                        {selectedWorkline === "สายสนับสนุน" &&
            <div class="fg">
                                <label class="lbl">กลุ่มงาน / Job Family</label>
                                <select class="sel" value={selectedFamily} onChange={(e) => setFamilyScope(e.target.value)}>
                                    {supportFamilies.map((family) => <option key={family} value={family}>{family}</option>)}
                                </select>
                            </div>
            }
                        <div class="fg">
                            <label class="lbl">ตำแหน่ง</label>
                            <select class="sel" value={selectedPosition} onChange={(e) => {
                setSelectedPosition(e.target.value);
                if (isAdminWorkline) setSelectedLevel(e.target.value);
              }}>
                                {getPositionOptions().map((position) => <option key={position} value={position}>{position}</option>)}
                            </select>
                        </div>
                        {isAdminWorkline ?
            <div class="fg">
                                <label class="lbl">ระดับตำแหน่ง</label>
                                <input class="inp" value={selectedPosition} readOnly />
                            </div> :

            <div class="fg">
                                <label class="lbl">ระดับ</label>
                                <select class="sel" value={selectedLevel.value} onChange={(e) => setSelectedLevel(e.target.value)}>
                                    {getLevelOptions().map((level) => <option key={level} value={level}>{level}</option>)}
                                </select>
                            </div>
            }
                        <div class={`expect-saved-box ${isSaved ? "saved" : ""}`}>
                            <span class={`b ${isSaved ? "bg" : "bgr"}`}>{isSaved ? "บันทึกแล้ว" : "ยังไม่ได้บันทึก"}</span>
                            <div class="muted fs11 mt4">{isSaved ? "มีชุดความคาดหวังในรอบนี้" : "แสดงค่าตั้งต้นจากระบบ"}</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="ch">
                        <div>
                            <div class="ct">{selectedWorkline} · {getScopeFamily()} · {selectedPosition}</div>
                            <div class="cs">{isAdminWorkline ? "ระดับตำแหน่ง" : "ระดับ"}: {currentScopeLevel || "ยังไม่ได้เลือกระดับ"}</div>
                        </div>
                        <div class="ml-auto flex g8">
                            <span class={`b ${isSaved ? "bg" : "by"}`}>{isSaved ? "✓ บันทึกแล้ว" : "ยังไม่บันทึก"}</span>
                        </div>
                    </div>
                    <div style={{ overflowX: "auto" }}>
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>รหัส</th>
                                    <th>สมรรถนะ</th>
                                    <th>ประเภท</th>
                                    <th>ความคาดหวัง</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {templates.value.map((t, i) =>
                <tr key={i}>
                                        <td class="muted fs12 fw7">{t.cd}</td>
                                        <td class="fw6">{t.n}</td>
                                        <td><span class={t.tg}>{t.t}</span></td>
                                        <td>
                                            <div class="lg" style={{ gap: "4px", margin: 0 }}>
                                                {[1, 2, 3, 4, 5].map((lv) =>
                      <button
                        key={lv}
                        type="button"
                        class={`lbtn${lv === t.lv ? ' sel' : ''}`}
                        style={{ padding: '4px 2px', border: 0, cursor: "pointer" }}
                        onClick={() => setExpectedLevel(i, lv)}>
                        
                                                        <span class="lnum" style={{ fontSize: '14px' }}>{lv}</span>
                                                    </button>
                      )}
                                            </div>
                                        </td>
                                        <td><button class="btn btn-r btn-xs">ลบ</button></td>
                                    </tr>
                )}
                            </tbody>
                        </table>
                    </div>
                    <div style={{ padding: '12px 16px', background: 'var(--bg)', borderTop: '1px solid var(--border)', display: 'flex', gap: '8px', flexWrap: "wrap" }}>
                        <button class="btn btn-p btn-sm" onClick={openPositionBinding}>+ เพิ่มสมรรถนะ</button>
                        <button class="btn btn-t btn-sm" onClick={saveExpectations}>บันทึก Expected Level</button>
                        {isSaved && <button class="btn btn-r btn-sm" onClick={deleteExpectations}>ลบชุดนี้</button>}
                    </div>
                </div>
            </div>
      }

            {showImportModal.value &&
      <div class="expect-modal">
                    <div class="expect-modal-box">
                        <div class="expect-modal-head">
                            <div>
                                <div class="ct">นำเข้าความคาดหวัง</div>
                                <div class="cs">เลือกรอบต้นทางเพื่อนำข้อมูลเข้า {selectedCycle.value}</div>
                            </div>
                            <button class="btn btn-s btn-sm" onClick={() => setShowImportModal(false)}>ปิด</button>
                        </div>
                        <div class="expect-modal-body">
                            <div class="muted fs12 mb14">ระบบจะตรวจสอบข้อมูลทับซ้อนก่อนนำเข้า ถ้ามีรายการชนกันจะให้เลือกว่าจะยึดของเดิมหรือข้อมูลที่นำเข้า</div>
                            <div class="import-cycle-list">
                                {cycles.filter((cycle) => cycle !== selectedCycle.value).map((cycle) => {
                const count = Object.keys(savedExpectations.value[cycle] || {}).length;
                return (
                  <div key={cycle} class="import-cycle-item">
                                            <div>
                                                <div class="fw8 fs14">{cycle}</div>
                                                <div class="muted fs11">{count} ชุดความคาดหวัง</div>
                                            </div>
                                            {count === 0 ?
                    <span class="muted fs12">ไม่มีข้อมูล</span> :
                    <button class="btn btn-p btn-sm" onClick={() => analyzeImportCycle(cycle)}>นำเข้าทั้งหมด</button>}
                                        </div>);

              })}
                            </div>
                        </div>
                    </div>
                </div>
      }

            {showConflictModal.value &&
      <div class="expect-modal expect-modal-top">
                    <div class="expect-modal-box conflict">
                        <div class="expect-modal-head">
                            <div>
                                <div class="ct">{importMode.value === "conflict" ? "พบข้อมูลทับซ้อน" : "ยืนยันการนำเข้า"}</div>
                                <div class="cs">{importMode.value === "conflict" ? "เลือกว่าจะยึดความคาดหวังชุดไหนสำหรับแต่ละรายการ" : "ตรวจสอบรายการที่จะนำเข้าก่อนยืนยัน"}</div>
                            </div>
                            <button class="btn btn-s btn-sm" onClick={closeImportReview}>ปิด</button>
                        </div>
                        <div class="expect-modal-body">
                            {importMode.value === "confirm" ?
            <>
                                    <div class="import-ok">
                                        <b>ไม่พบข้อมูลทับซ้อน</b>
                                        <span>จะนำเข้า {Object.keys(importNonConflicts.value).length} ชุดจาก {importSourceCycle.value}</span>
                                    </div>
                                    <div class="import-confirm-list">
                                        {Object.keys(importNonConflicts.value).map((key) => {
                  const scope = getScopeLabel(key);
                  return (
                    <div key={key} class="import-confirm-item">
                                                    <span class="b bgr">{scope.workline}</span>
                                                    <b>{scope.position}</b>
                                                    <span class="muted fs12">{scope.jobFamily} · {scope.level}</span>
                                                </div>);

                })}
                                    </div>
                                </> :

            <>
                                    <div class="import-warning">
                                        <div>
                                            <b>พบข้อมูลทับซ้อน {importConflicts.value.length} รายการ</b>
                                            <span>เลือกแล้ว {Object.values(importChoices.value).filter(Boolean).length}/{importConflicts.value.length} รายการ · จาก {importSourceCycle.value}</span>
                                        </div>
                                        <div class="flex g6">
                                            <button class="btn btn-s btn-xs" onClick={() => chooseAllImportConflicts("current")}>ยึดของเดิมทั้งหมด</button>
                                            <button class="btn btn-p btn-xs" onClick={() => chooseAllImportConflicts("imported")}>ยึดที่นำเข้าทั้งหมด</button>
                                        </div>
                                    </div>
                                    {importConflicts.value.map((conflict) => {
                const scope = getScopeLabel(conflict.key);
                const choice = importChoices.value[conflict.key];
                return (
                  <div key={conflict.key} class={`import-conflict-card ${choice ? "chosen" : ""}`}>
                                                <div class="import-conflict-title">
                                                    <span class="b bgr">{scope.workline}</span>
                                                    <b>{scope.position}</b>
                                                    <span class="muted fs12">{scope.jobFamily} · {scope.level}</span>
                                                    <span class={`import-choice-label ${choice || ""}`}>{choice === "current" ? "ยึดของเดิม" : choice === "imported" ? "ยึดที่นำเข้า" : "ยังไม่ได้เลือก"}</span>
                                                </div>
                                                <div class="import-compare-grid">
                                                    <div class={`import-compare-side ${choice === "current" ? "selected current" : ""}`}>
                                                        <div class="import-side-title current">ของเดิม ({selectedCycle.value})</div>
                                                        {conflict.current.map((row) =>
                        <div key={`${row.cd}-${row.lv}`} class="import-row">
                                                                <span>{row.cd}</span><b>Level {row.lv}</b>
                                                            </div>
                        )}
                                                        <button class="btn btn-s btn-sm w100" onClick={() => chooseImportConflict(conflict.key, "current")}>{choice === "current" ? "เลือกแล้ว" : "ยึดของเดิม"}</button>
                                                    </div>
                                                    <div class={`import-compare-side ${choice === "imported" ? "selected imported" : ""}`}>
                                                        <div class="import-side-title imported">ที่นำเข้า ({importSourceCycle.value})</div>
                                                        {conflict.imported.map((row) => {
                          const changed = !conflict.current.some((current) => current.cd === row.cd && current.lv === row.lv);
                          return (
                            <div key={`${row.cd}-${row.lv}`} class={`import-row ${changed ? "changed" : ""}`}>
                                                                    <span>{row.cd}</span><b>Level {row.lv}{changed ? " ←" : ""}</b>
                                                                </div>);

                        })}
                                                        <button class="btn btn-p btn-sm w100" onClick={() => chooseImportConflict(conflict.key, "imported")}>{choice === "imported" ? "เลือกแล้ว" : "ยึดที่นำเข้า"}</button>
                                                    </div>
                                                </div>
                                            </div>);

              })}
                                    {Object.keys(importNonConflicts.value).length > 0 &&
              <div class="import-ok compact">อีก {Object.keys(importNonConflicts.value).length} ชุดไม่มีข้อมูลทับซ้อน จะถูกนำเข้าอัตโนมัติ</div>
              }
                                </>
            }
                        </div>
                        <div class="expect-modal-foot">
                            <button class="btn btn-s" onClick={closeImportReview}>ยกเลิก</button>
                            <button
              class="btn btn-t"
              disabled={importMode.value === "conflict" && !allConflictsChosen}
              onClick={importMode.value === "conflict" ? applyConflictImport : applyNoConflictImport}
              style={{ opacity: importMode.value === "conflict" && !allConflictsChosen ? 0.5 : 1 }}>
              
                                ยืนยันและนำเข้า
                            </button>
                        </div>
                    </div>
                </div>
      }

            <style>{`
                .cycle-picker { display: flex; align-items: stretch; gap: 10px; flex-wrap: wrap; }
                .cycle-chip { min-width: 150px; display: grid; gap: 2px; text-align: left; padding: 9px 16px; border-radius: var(--r); border: 2px solid var(--border); background: #fff; cursor: pointer; font-family: inherit; }
                .cycle-chip span { font-size: 13px; font-weight: 800; color: var(--text); }
                .cycle-chip small { font-size: 11px; color: var(--text3); }
                .cycle-chip.on { border-color: var(--teal); background: var(--teal-lt); }
                .cycle-chip.on span { color: var(--teal); }
                .expect-tabs { display: flex; align-items: center; gap: 0; border-bottom: 2px solid var(--border); }
                .expect-tab { display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; border: 0; border-bottom: 3px solid transparent; margin-bottom: -2px; background: transparent; color: var(--text3); font-family: inherit; font-size: 13px; font-weight: 800; cursor: pointer; }
                .expect-tab.on { color: var(--teal); border-bottom-color: var(--teal); }
                .expect-locked { margin-left: auto; padding-bottom: 8px; display: flex; align-items: center; gap: 6px; color: var(--text3); font-size: 11px; }
                .ml-auto { margin-left: auto; }
                .expect-layout { display: grid; grid-template-columns: 310px minmax(0, 1fr); gap: 14px; align-items: start; }
                .expect-saved-box { padding: 12px; border: 1px solid var(--border); border-radius: var(--r); background: var(--bg); }
                .expect-saved-box.saved { background: var(--green-bg); border-color: var(--green-md); }
                .expect-all-list { display: grid; gap: 14px; }
                .expect-all-card { overflow: hidden; }
                .expect-all-head { min-height: 49px; }
                .expect-workline-pill { font-size: 13px; }
                .expect-all-row { padding: 12px 16px; border-bottom: 1px solid var(--border); }
                .expect-all-row:last-child { border-bottom: 0; }
                .expect-all-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
                .expect-position-name { font-size: 13px; font-weight: 700; color: var(--text); }
                .expect-comp-count { font-size: 11px; color: var(--text3); }
                .expect-edit-btn { margin-left: auto; }
                .expect-comp-lines { display: flex; flex-direction: column; gap: 6px; }
                .expect-comp-line { display: flex; align-items: flex-start; gap: 10px; }
                .expect-type-badge { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 36px; margin-top: 2px; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
                .expect-type-badge.cc { background: var(--blue-lt); color: var(--blue); }
                .expect-type-badge.mc { background: var(--purple-bg); color: var(--purple); }
                .expect-type-badge.fc { background: var(--green-bg); color: var(--green); }
                .expect-comp-chips { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; gap: 6px; }
                .expect-comp-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border: 1px solid var(--border); border-radius: 20px; background: var(--bg); color: var(--text2); font-size: 12px; }
                .expect-comp-chip b { display: inline-flex; align-items: center; min-height: 19px; padding: 1px 7px; border-radius: 12px; background: var(--teal-lt); color: var(--teal); font-size: 13px; font-weight: 800; }
                .expect-modal { position: fixed; inset: 0; z-index: 260; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 45, 91, 0.36); }
                .expect-modal-top { align-items: flex-start; overflow-y: auto; }
                .expect-modal-box { width: min(540px, 94vw); max-height: 88vh; overflow: hidden; background: #fff; border: 1px solid var(--border); border-radius: var(--r-lg); box-shadow: var(--sh-md); display: flex; flex-direction: column; }
                .expect-modal-box.conflict { width: min(860px, 94vw); margin: 24px 0; }
                .expect-modal-head, .expect-modal-foot { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #fff; }
                .expect-modal-foot { border-top: 1px solid var(--border); border-bottom: 0; justify-content: flex-end; }
                .expect-modal-body { padding: 16px 18px; overflow-y: auto; }
                .import-cycle-list { display: grid; gap: 8px; }
                .import-cycle-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--r); background: var(--bg); }
                .import-warning { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 14px; border: 1px solid #F59E0B; border-radius: var(--r); background: #FEF9EC; color: #92400E; margin-bottom: 14px; }
                .import-warning span, .import-ok span { display: block; font-size: 11px; margin-top: 2px; }
                .import-ok { padding: 12px 14px; border: 1px solid var(--green-md); border-radius: var(--r); background: var(--green-bg); color: var(--green); margin-bottom: 14px; }
                .import-ok.compact { margin-top: 8px; margin-bottom: 0; font-size: 12px; }
                .import-confirm-list { border: 1px solid var(--border); border-radius: var(--r); overflow: hidden; }
                .import-confirm-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
                .import-confirm-item:last-child { border-bottom: 0; }
                .import-conflict-card { border: 2px solid #FCA5A5; border-radius: var(--r); overflow: hidden; margin-bottom: 12px; }
                .import-conflict-card.chosen { border-color: var(--border); }
                .import-conflict-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 10px 14px; background: var(--red-bg); }
                .import-conflict-card.chosen .import-conflict-title { background: var(--bg); }
                .import-choice-label { margin-left: auto; font-size: 11px; font-weight: 800; color: var(--red); }
                .import-choice-label.current { color: var(--yellow); }
                .import-choice-label.imported { color: var(--blue); }
                .import-compare-grid { display: grid; grid-template-columns: 1fr 1fr; }
                .import-compare-side { padding: 12px 14px; border-right: 1px solid var(--border); background: #fff; }
                .import-compare-side:last-child { border-right: 0; }
                .import-compare-side.selected.current { background: var(--yellow-bg); outline: 2px solid var(--yellow); outline-offset: -2px; }
                .import-compare-side.selected.imported { background: var(--blue-lt); outline: 2px solid var(--blue); outline-offset: -2px; }
                .import-side-title { font-size: 11px; font-weight: 800; margin-bottom: 8px; }
                .import-side-title.current { color: var(--yellow); }
                .import-side-title.imported { color: var(--blue); }
                .import-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 7px; border-radius: 5px; background: #FAFAFA; font-size: 11px; margin-bottom: 4px; }
                .import-row.changed { background: var(--blue-md); }
                .import-row b { color: var(--blue); }
                .w100 { width: 100%; justify-content: center; margin-top: 8px; }
                @media (max-width: 980px) {
                    .expect-layout { grid-template-columns: 1fr; }
                    .ml-auto { margin-left: 0; }
                    .import-compare-grid { grid-template-columns: 1fr; }
                    .import-compare-side { border-right: 0; border-bottom: 1px solid var(--border); }
                }
                @media (max-width: 700px) {
                    .expect-tabs { align-items: flex-start; flex-direction: column; padding-bottom: 10px; }
                    .expect-locked { margin-left: 20px; padding-bottom: 0; }
                    .expect-edit-btn { margin-left: 0; }
                }
            `}</style>
        </>;} });



type PositionBinding = {
  id: string;
  workline: string;
  jobFamily: string;
  position: string;
  compCode: string;
  source: "default" | "round";
};

export const HRPositionCompetencies = defineComponent({ name: "HRPositionCompetencies", props: Object as PropType<{
    competencies: any[];
    worklines: string[];
    academicPositions: string[];
    supportPositionGroups: Record<string, string[]>;
    adminPositions: string[];
    initialScope?: HRPositionScope | null;
  }>, setup(__props) {const {
      competencies,
      worklines,
      academicPositions,
      supportPositionGroups,
      adminPositions,
      initialScope
    } = __props as any;
    const supportFamilies = Object.keys(supportPositionGroups);
    const firstSupportFamily = supportFamilies[0] || "";
    const [selectedWorkline, setSelectedWorkline] = useState(initialScope?.workline || "สายสนับสนุน");
    const [selectedFamily, setSelectedFamily] = useState(initialScope?.jobFamily || firstSupportFamily);
    const [selectedPosition, setSelectedPosition] = useState(initialScope?.position || supportPositionGroups[firstSupportFamily]?.[0] || "นักวิชาการศึกษา");
    const [query, setQuery] = useState("");
    const [typeFilter, setTypeFilter] = useState("ทั้งหมด");
    const [status, setStatus] = useState<string | null>(null);
    const [bindings, setBindings] = useState<PositionBinding[]>([]);

    const getCompType = (comp: any) => {
      if (comp.t === "FC" && comp.cd?.startsWith("FC1-")) return "FC1";
      if (comp.t === "FC" && comp.cd?.startsWith("FC2-")) return "FC2";
      return comp.t;
    };

    const getCompTag = (comp: any) => `tag-${String(getCompType(comp)).toLowerCase()}`;
    const findComp = (code: string) => competencies.find((c) => c.cd === code);

    const getPositionOptions = () => {
      if (selectedWorkline.value === "สายวิชาการ") return academicPositions;
      if (selectedWorkline.value === "สายงานบริหาร") return adminPositions;
      return supportPositionGroups[selectedFamily.value] || [];
    };

    const getScopeFamily = () => {
      if (selectedWorkline.value === "สายวิชาการ") return "สายวิชาการ";
      if (selectedWorkline.value === "สายงานบริหาร") return "คณะวิศวกรรมศาสตร์";
      return selectedFamily.value;
    };

    const scopeBindings = bindings.value.filter((b) =>
    b.workline === selectedWorkline.value &&
    b.jobFamily === getScopeFamily() &&
    b.position === selectedPosition.value
    );

    const boundCodes = new Set(scopeBindings.map((b) => b.compCode));
    const allPositionScopes = [
    ...academicPositions.map((position) => ({ workline: "สายวิชาการ", jobFamily: "สายวิชาการ", position })),
    ...adminPositions.map((position) => ({ workline: "สายงานบริหาร", jobFamily: "คณะวิศวกรรมศาสตร์", position })),
    ...(Object.entries(supportPositionGroups) as [string, string[]][]).flatMap(([jobFamily, positions]) =>
    positions.map((position) => ({ workline: "สายสนับสนุน", jobFamily, position }))
    )];

    const totalPositionCount = allPositionScopes.length;
    const boundPositionCount = new Set(bindings.value.map((binding) => `${binding.workline}|${binding.jobFamily}|${binding.position}`)).size;
    const typeCounts = scopeBindings.reduce((acc: Record<string, number>, binding) => {
      const comp = findComp(binding.compCode);
      const type = comp ? getCompType(comp) : "อื่น ๆ";
      acc[type] = (acc[type] || 0) + 1;
      return acc;
    }, {});
    const coreCompetencies = competencies.filter((comp) => getCompType(comp) === "CC");
    const boundCoreCount = scopeBindings.filter((binding) => {
      const comp = findComp(binding.compCode);
      return comp && getCompType(comp) === "CC";
    }).length;

    const setWorklineScope = (value: string) => {
      setSelectedWorkline(value);
      if (value === "สายวิชาการ") {
        setSelectedFamily("สายวิชาการ");
        setSelectedPosition(academicPositions[0] || "");
      } else if (value === "สายงานบริหาร") {
        setSelectedFamily("คณะวิศวกรรมศาสตร์");
        setSelectedPosition(adminPositions[0] || "");
      } else {
        const nextFamily = supportFamilies[0] || "";
        setSelectedFamily(nextFamily);
        setSelectedPosition(supportPositionGroups[nextFamily]?.[0] || "");
      }
    };

    const setFamilyScope = (value: string) => {
      setSelectedFamily(value);
      setSelectedPosition((supportPositionGroups[value] || [])[0] || "");
    };

    const removeBinding = (id: string) => {
      setBindings((current) => current.filter((binding) => binding.id !== id));
    };

    const addBinding = (compCode: string) => {
      if (boundCodes.has(compCode)) {
        setStatus("สมรรถนะนี้ถูกผูกกับตำแหน่งนี้แล้ว");
        return;
      }
      setBindings((current) => [
      ...current,
      {
        id: `bind-${Date.now()}-${compCode}`,
        workline: selectedWorkline.value,
        jobFamily: getScopeFamily(),
        position: selectedPosition.value,
        compCode,
        source: "round"
      }]
      );
      setStatus(`เพิ่ม ${compCode} ให้ตำแหน่งนี้แล้ว`);
      setQuery("");
    };

    const addAllCoreCompetencies = () => {
      const missingCoreCompetencies = coreCompetencies.filter((comp) => !boundCodes.has(comp.cd));
      if (missingCoreCompetencies.length === 0) {
        setStatus("CC ทั้งหมดถูกผูกกับตำแหน่งนี้แล้ว");
        return;
      }
      const now = Date.now();
      setBindings((current) => [
      ...current,
      ...missingCoreCompetencies.map((comp, idx) => ({
        id: `bind-${now}-${idx}-${comp.cd}`,
        workline: selectedWorkline.value,
        jobFamily: getScopeFamily(),
        position: selectedPosition.value,
        compCode: comp.cd,
        source: "default" as const
      }))]
      );
      setStatus(`เพิ่ม CC ทั้งหมดที่ยังขาด ${missingCoreCompetencies.length} รายการแล้ว`);
    };

    const filteredCompetencies = competencies.filter((comp) => {
      const compType = getCompType(comp);
      const text = `${comp.cd} ${comp.n} ${comp.det}`.toLowerCase();
      const matchesQuery = !query.value || text.includes(query.value.trim().toLowerCase());
      const matchesType = typeFilter.value === "ทั้งหมด" || compType === typeFilter.value;
      return matchesQuery && matchesType;
    });return () =>


    <>
            <div class="position-hero mb14">
                <div>
                    <div class="position-kicker">competency setup</div>
                    <div class="sec-t position-title">กำหนดสมรรถนะประจำตำแหน่ง</div>
                    <div class="sec-s position-sub">เลือกตำแหน่ง แล้วกำหนดชุดสมรรถนะที่ต้องใช้ประเมิน ก่อนนำไปตั้งระดับความคาดหวังในรอบประเมิน</div>
                </div>
                <div class="position-hero-metrics">
                    <div>
                        <span>{boundPositionCount}</span>
                        <small>ตำแหน่งที่กำหนดแล้ว</small>
                    </div>
                    <div>
                        <span>{totalPositionCount - boundPositionCount}</span>
                        <small>ยังไม่กำหนด</small>
                    </div>
                </div>
            </div>

            {status.value &&
      <div class="status-msg anim-fade-in" style={{ marginBottom: "14px", padding: "10px 14px", borderRadius: "var(--r)", background: "var(--green-bg)", border: "1px solid var(--green-md)", color: "var(--green)", fontWeight: 700, display: "flex", justifyContent: "space-between", gap: "12px" }}>
                    <span>{status.value}</span>
                    <button class="btn-link" style={{ color: "var(--green)" }} onClick={() => setStatus(null)}>ปิด</button>
                </div>
      }

            <div class="position-scope mb14">
                <div class="position-workline">
                    <div class="position-scope-label">สายงาน</div>
                    <div class="position-segments">
                        {worklines.map((w) =>
            <button key={w} type="button" class={selectedWorkline.value === w ? "active" : ""} onClick={() => setWorklineScope(w)}>
                                {w}
                            </button>
            )}
                    </div>
                </div>
                <div class="position-picker">
                    {selectedWorkline.value === "สายสนับสนุน" &&
          <div class="fg mb0">
                            <label class="lbl">กลุ่มงาน / Job Family</label>
                            <select class="sel" value={selectedFamily.value} onChange={(e) => setFamilyScope(e.target.value)}>
                                {supportFamilies.map((group) => <option key={group} value={group}>{group}</option>)}
                            </select>
                        </div>
          }
                    <div class="fg mb0">
                        <label class="lbl">ตำแหน่ง</label>
                        <select class="sel" value={selectedPosition.value} onChange={(e) => setSelectedPosition(e.target.value)}>
                            {getPositionOptions().map((pos) => <option key={pos} value={pos}>{pos}</option>)}
                        </select>
                    </div>
                </div>
            </div>

            <div class="position-board mb14">
                <div class="position-card selected">
                    <div class="position-card-label">ตำแหน่งที่กำลังกำหนด</div>
                    <div class="position-card-title">{selectedPosition.value}</div>
                    <div class="position-card-sub">{selectedWorkline.value} · {getScopeFamily()}</div>
                </div>
                <div class="position-card">
                    <div class="position-card-label">สมรรถนะของตำแหน่งนี้</div>
                    <div class="position-card-title">{scopeBindings.length}</div>
                    <div class="position-card-sub">{Object.entries(typeCounts).map(([type, count]) => `${type} ${count}`).join(" · ") || "ยังไม่มีรายการ"}</div>
                </div>
                <div class="position-card">
                    <div class="position-card-label">CC พื้นฐาน</div>
                    <div class={`position-card-title ${boundCoreCount === coreCompetencies.length ? "ok" : "warn"}`}>{boundCoreCount}/{coreCompetencies.length}</div>
                    <div class="position-card-sub">{boundCoreCount === coreCompetencies.length ? "ครบทุกตำแหน่งต้องมี" : "ยังขาด CC พื้นฐาน"}</div>
                </div>
            </div>

            <div class="position-layout">
                <section class="position-panel assigned">
                    <div class="position-panel-head">
                        <div>
                            <div class="ct">ชุดสมรรถนะประจำตำแหน่ง</div>
                            <div class="cs">รายการนี้จะถูกใช้เป็นฐานสำหรับกำหนด Expected Level</div>
                        </div>
                        <button class="btn btn-t btn-sm" onClick={addAllCoreCompetencies}>เพิ่ม CC ทั้งหมด</button>
                    </div>
                    <div class="assigned-list">
                        {scopeBindings.length === 0 &&
            <div class="assigned-empty">
                                <div class="assigned-empty-icon">🔗</div>
                                <div class="fw8">ยังไม่ได้กำหนดสมรรถนะให้ตำแหน่งนี้</div>
                                <div class="muted fs12">ค้นหาจากพจนานุกรมด้านขวา หรือกดเพิ่ม CC ทั้งหมดเพื่อเริ่มต้น</div>
                            </div>
            }
                        {scopeBindings.map((binding) => {
              const comp = findComp(binding.compCode);
              if (!comp) return null;
              return (
                <div key={binding.id} class="assigned-item">
                                    <div class="assigned-code">{binding.compCode}</div>
                                    <div class="assigned-main">
                                        <div class="flex ic g8 mb4">
                                            <span class={getCompTag(comp)}>{getCompType(comp)}</span>
                                            <span class="assigned-source">{binding.source === "default" ? "พื้นฐาน" : "กำหนดเอง"}</span>
                                        </div>
                                        <div class="fw8 fs14">{comp.n}</div>
                                        <div class="muted fs12 truncate-2">{comp.det || "ยังไม่มีคำอธิบายจากพจนานุกรม"}</div>
                                    </div>
                                    <button class="btn btn-r btn-xs" onClick={() => removeBinding(binding.id)}>ลบ</button>
                                </div>);

            })}
                    </div>
                </section>

                <aside class="position-panel dictionary">
                    <div class="position-panel-head">
                        <div>
                            <div class="ct">พจนานุกรมสมรรถนะ</div>
                            <div class="cs">เลือกจากรายการที่ Admin กำหนดไว้</div>
                        </div>
                    </div>
                    <div class="dictionary-tools">
                        <input class="inp" value={query.value} onChange={(e) => setQuery(e.target.value)} placeholder="ค้นหารหัส / ชื่อ / คำอธิบาย" />
                        <select class="sel" value={typeFilter.value} onChange={(e) => setTypeFilter(e.target.value)}>
                            <option>ทั้งหมด</option>
                            <option>CC</option>
                            <option>MC</option>
                            <option>FC1</option>
                            <option>FC2</option>
                        </select>
                    </div>
                    <div class="dictionary-list">
                        {filteredCompetencies.map((comp) => {
              const isBound = boundCodes.has(comp.cd);
              return (
                <div key={comp.cd} class={`dictionary-item ${isBound ? "disabled" : ""}`}>
                                    <div>
                                        <div class="flex ic g8 mb4">
                                            <span class="dictionary-code">{comp.cd}</span>
                                            <span class={getCompTag(comp)}>{getCompType(comp)}</span>
                                        </div>
                                        <div class="fw8 fs13">{comp.n}</div>
                                        <div class="muted fs11 truncate-2">{comp.det || "ยังไม่มีคำอธิบาย"}</div>
                                        <div class="dictionary-meta">
                                            {comp.levels?.length ? `${comp.levels.length} ระดับ` : "ไม่มีระดับ"} · {isBound ? "อยู่ในชุดนี้แล้ว" : "พร้อมเพิ่ม"}
                                        </div>
                                    </div>
                                    <button class={`btn btn-xs ${isBound ? "btn-s" : "btn-p"}`} disabled={isBound} onClick={() => addBinding(comp.cd)}>
                                        {isBound ? "เพิ่มแล้ว" : "เพิ่ม"}
                                    </button>
                                </div>);

            })}
                        {filteredCompetencies.length === 0 && <div class="muted fs12 ac py8">ไม่พบสมรรถนะที่ค้นหา</div>}
                    </div>
                </aside>
            </div>

            <style>{`
                .position-hero { min-height: 138px; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 20px; align-items: end; padding: 24px 26px; border-radius: 14px; background: #102f5d; color: #fff; box-shadow: 0 16px 34px rgba(15,45,91,.18); }
                .position-kicker { color: rgba(255,255,255,.58); font-size: 11px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 8px; }
                .position-title { color: #fff; font-size: 26px; }
                .position-sub { color: rgba(255,255,255,.68); max-width: 760px; line-height: 1.65; }
                .position-hero-metrics { display: grid; grid-template-columns: repeat(2, 120px); gap: 10px; }
                .position-hero-metrics div { padding: 14px; border: 1px solid rgba(255,255,255,.16); border-radius: 10px; background: rgba(255,255,255,.08); }
                .position-hero-metrics span { display: block; font-size: 30px; font-weight: 900; line-height: 1; }
                .position-hero-metrics small { display: block; color: rgba(255,255,255,.64); font-size: 11px; font-weight: 700; margin-top: 6px; }
                .position-scope { display: grid; grid-template-columns: minmax(320px, 1fr) minmax(360px, 1fr); gap: 14px; padding: 14px; border: 1px solid var(--border); border-radius: 12px; background: #fff; box-shadow: var(--sh); }
                .position-scope-label { color: var(--text3); font-size: 11px; font-weight: 800; margin-bottom: 8px; }
                .position-segments { display: flex; gap: 8px; flex-wrap: wrap; }
                .position-segments button { min-height: 36px; border: 1px solid var(--border); border-radius: 999px; background: #fff; color: var(--text2); padding: 0 14px; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; }
                .position-segments button.active { background: var(--navy); border-color: var(--navy); color: #fff; }
                .position-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: end; }
                .position-picker .fg:only-child { grid-column: 1 / -1; }
                .position-board { display: grid; grid-template-columns: 1.35fr repeat(2, minmax(180px, .65fr)); gap: 14px; }
                .position-card { min-height: 104px; padding: 16px; border: 1px solid var(--border); border-radius: 12px; background: #fff; box-shadow: var(--sh); }
                .position-card.selected { border-color: var(--blue-md); background: linear-gradient(180deg, #fff 0%, var(--blue-lt) 100%); }
                .position-card-label { color: var(--text3); font-size: 11px; font-weight: 800; margin-bottom: 8px; }
                .position-card-title { color: var(--text); font-size: 24px; font-weight: 900; line-height: 1.2; }
                .position-card-title.ok { color: var(--green); }
                .position-card-title.warn { color: var(--yellow); }
                .position-card-sub { color: var(--text3); font-size: 12px; font-weight: 600; margin-top: 7px; }
                .position-layout { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(360px, .75fr); gap: 14px; align-items: start; }
                .position-panel { border: 1px solid var(--border); border-radius: 12px; background: #fff; box-shadow: var(--sh); overflow: hidden; }
                .position-panel-head { min-height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--border); background: #fff; }
                .assigned-list { display: grid; gap: 10px; padding: 14px; }
                .assigned-item { display: grid; grid-template-columns: 92px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 13px; border: 1px solid var(--border); border-radius: 10px; background: #fff; }
                .assigned-item:hover { border-color: var(--blue-md); background: #fbfdff; }
                .assigned-code { width: 76px; min-height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; background: var(--bg); color: var(--text2); font-size: 12px; font-weight: 900; }
                .assigned-source { color: var(--text3); font-size: 10px; font-weight: 800; }
                .assigned-empty { min-height: 220px; display: grid; place-items: center; align-content: center; gap: 8px; border: 1px dashed var(--border); border-radius: 10px; background: var(--bg); text-align: center; }
                .assigned-empty-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 12px; background: #fff; box-shadow: var(--sh); font-size: 22px; }
                .dictionary { position: sticky; top: 14px; }
                .dictionary-tools { display: grid; grid-template-columns: minmax(0, 1fr) 132px; gap: 10px; padding: 14px; border-bottom: 1px solid var(--border); background: var(--bg); }
                .dictionary-list { display: grid; gap: 8px; max-height: 560px; overflow-y: auto; padding: 14px; }
                .dictionary-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 12px; border: 1px solid var(--border); border-radius: 10px; background: #fff; }
                .dictionary-item:hover { border-color: var(--blue-md); background: var(--blue-lt); }
                .dictionary-item.disabled { opacity: .62; background: var(--bg); }
                .dictionary-code { color: var(--text3); font-size: 11px; font-weight: 900; }
                .dictionary-meta { color: var(--text3); font-size: 10px; font-weight: 700; margin-top: 5px; }
                .truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
                @media (max-width: 1100px) {
                    .position-layout, .position-scope, .position-board, .position-hero { grid-template-columns: 1fr; }
                    .dictionary { position: static; }
                    .position-hero-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                }
                @media (max-width: 700px) {
                    .position-picker, .dictionary-tools, .assigned-item { grid-template-columns: 1fr; }
                    .position-hero { padding: 20px; }
                    .position-title { font-size: 22px; }
                }
            `}</style>
        </>;} });

export default defineComponent({ name: "HRPages" });
</script>
