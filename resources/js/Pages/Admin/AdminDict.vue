<script lang="tsx">
import { Fragment, defineComponent, ref, watchEffect, type PropType } from "vue";
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

import { ExcelImportModal } from "../../Components/SharedUI.vue";interface AdminDictProps {competencies: any[];setCompetencies: any;competencyTypes: string[];onDirtyChange?: (dirty: boolean) => void;}const AdminDict = defineComponent({ name: "AdminDict", props: ["competencies", "setCompetencies", "competencyTypes", "onDirtyChange"], setup(__props) {const { competencies, setCompetencies, competencyTypes, onDirtyChange } = __props as any;const [showImport, setShowImport] = useState(false);const [view, setView] = useState("list");const [isDirty, setIsDirty] = useState(false);const [status, setStatus] = useState<any>(null);const showStatus = (type: string, msg: string) => {setStatus({ type, msg });setTimeout(() => setStatus(null), 4000);};const [expandedCode, setExpandedCode] = useState<string | null>(null);
    const [expandedLevel, setExpandedLevel] = useState<number | null>(null);
    const [sortBy, setSortBy] = useState("newest");
    const [typeFilter, setTypeFilter] = useState("ทั้งหมด");

    useEffect(() => {
      onDirtyChange?.(isDirty.value);

      const warnBeforeUnload = (event: BeforeUnloadEvent) => {
        if (!isDirty.value) return;
        event.preventDefault();
        event.returnValue = "";
      };

      window.addEventListener("beforeunload", warnBeforeUnload);
      return () => {
        window.removeEventListener("beforeunload", warnBeforeUnload);
      };
    }, [isDirty.value, onDirtyChange]);

    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);

    const deleteComp = (code: string) => {
      setCompetencies(competencies.filter((c) => c.cd !== code));
      showStatus("s", "ลบข้อมูลสมรรถนะเรียบร้อยแล้ว");
    };

    const getCompType = (c: any) => {
      if (c.t === "FC") {
        if (c.cd.startsWith("FC1-")) return "FC1";
        if (c.cd.startsWith("FC2-")) return "FC2";
      }
      return c.t;
    };

    const getCompTag = (c: any) => {
      const t = getCompType(c);
      return `tag-${t.toLowerCase()}`;
    };

    const sorted = competencies.filter((c) => {
      const type = getCompType(c);
      return typeFilter.value === "ทั้งหมด" || type === typeFilter.value;
    }).sort((a, b) => {
      if (sortBy.value === "name") return a.n.localeCompare(b.n, 'th');
      if (sortBy.value === "code") return a.cd.localeCompare(b.cd);
      if (sortBy.value === "type") {
        const order: any = { CC: 1, MC: 2, FC1: 3, FC2: 4 };
        const typeA = getCompType(a);
        const typeB = getCompType(b);
        return (order[typeA] || 9) - (order[typeB] || 9);
      }
      return 0;
    });

    // Form states for Add/Edit
    const [editId, setEditId] = useState<string | null>(null);
    const [type, setType] = useState("CC");
    const [codeTail, setCodeTail] = useState("");
    const [name, setName] = useState("");
    const [desc, setDesc] = useState("");

    const generateLevel = (l: string) => ({ lvl: l, items: ["", "", "", ""], weights: ["0.25", "0.25", "0.25", "0.25"] });
    const [levels, setLevels] = useState([generateLevel("1"), generateLevel("2"), generateLevel("3"), generateLevel("4"), generateLevel("5")]);

    const clearForm = () => {
      setType("CC");setCodeTail("");setName("");setDesc("");setEditId(null);
      setLevels([generateLevel("1"), generateLevel("2"), generateLevel("3"), generateLevel("4"), generateLevel("5")]);
      setIsDirty(false);
    };

    const closeForm = () => {
      if (isDirty.value && !window.confirm("มีข้อมูลสมรรถนะที่ยังไม่ได้บันทึก ต้องการออกจากหน้านี้หรือไม่")) return;
      clearForm();
      setView("list");
    };

    const editComp = (c: any) => {
      const parts = c.cd.split("-");
      setEditId(c.cd);
      setType(parts[0]);
      setCodeTail(parts[1]);
      setName(c.n);
      setDesc(c.det);
      if (c.levels) {
        setLevels(c.levels.map((l: any) => ({
          lvl: String(l.lvl),
          items: l.indicators.length >= 4 ? l.indicators.slice(0, 4) : [...l.indicators, ...Array(4 - l.indicators.length).fill("")],
          weights: l.weights && l.weights.length >= 4 ? l.weights.map(String).slice(0, 4) : ["0.25", "0.25", "0.25", "0.25"]
        })));
      } else {
        setLevels([generateLevel("1"), generateLevel("2"), generateLevel("3"), generateLevel("4"), generateLevel("5")]);
      }
      setIsDirty(false);
      setView("edit");
    };

    const saveComp = () => {
      const code = `${type.value}-${codeTail.value}`;
      if (!codeTail.value.trim()) {showStatus("e", "กรุณากรอกรหัสสมรรถนะ");return;}
      if (!name.value.trim()) {showStatus("e", "กรุณากรอกชื่อสมรรถนะ");return;}
      if (!desc.value.trim()) {showStatus("e", "กรุณากรอกรายละเอียดสมรรถนะ");return;}

      for (let i = 0; i < levels.value.length; i++) {
        const lv = levels.value[i];
        for (let j = 0; j < lv.items.length; j++) {
          if (!lv.items[j].trim()) {
            showStatus("e", `กรุณากรอกพฤติกรรมบ่งชี้ให้ครบทุกข้อ (ระดับที่ ${lv.lvl} ข้อที่ ${j + 1} ยังว่างอยู่)`);
            return;
          }
        }
      }

      const alreadyExistsCode = competencies.some((c) => c.cd === code && c.cd !== editId.value);
      const alreadyExistsName = competencies.some((c) => c.n === name.value && c.cd !== editId.value);
      if (alreadyExistsCode) {showStatus("e", `รหัสสมรรถนะ ${code} มีอยู่ในระบบแล้ว กรุณาใช้รหัสอื่น`);return;}
      if (alreadyExistsName) {showStatus("e", `ชื่อสมรรถนะ "${name.value}" มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น`);return;}

      const newComp = {
        cd: code, n: name.value, t: type.value, tg: `tag-${type.value.toLowerCase()}`, det: desc.value, lv: levels.value.length, grp: "ทุกตำแหน่ง",
        levels: levels.value.map((l) => ({
          lvl: parseInt(l.lvl),
          label: `ระดับที่ ${l.lvl}`,
          indicators: l.items.filter((it) => it.trim()),
          weights: l.weights.map((w) => parseFloat(w) || 0)
        }))
      };

      if (editId.value) {
        setCompetencies(competencies.map((c) => c.cd === editId.value ? newComp : c));
        showStatus("s", "แก้ไขข้อมูลสมรรถนะเรียบร้อยแล้ว");
      } else {
        setCompetencies([newComp, ...competencies]);
        showStatus("s", "เพิ่มสมรรถนะใหม่เรียบร้อยแล้ว");
      }
      clearForm();
      setView("list");
    };

    const addLevelRow = () => {
      setLevels([...levels.value, generateLevel(String(levels.value.length + 1))]);
      setIsDirty(true);
    };

    const removeLevelRow = (idx: number) => {
      setLevels(levels.value.filter((_, i) => i !== idx));
      setIsDirty(true);
    };

    const updateIndicator = (lvIdx: number, itemIdx: number, val: string) => {
      const next = [...levels.value];
      const nextItems = [...next[lvIdx].items];
      nextItems[itemIdx] = val;
      next[lvIdx] = { ...next[lvIdx], items: nextItems };
      setLevels(next);
      setIsDirty(true);
    };

    const updateWeight = (lvIdx: number, itemIdx: number, val: string) => {
      const next = [...levels.value];
      const nextWeights = [...next[lvIdx].weights];
      nextWeights[itemIdx] = val;
      next[lvIdx] = { ...next[lvIdx], weights: nextWeights };
      setLevels(next);
      setIsDirty(true);
    };

    const updateLvlNum = (idx: number, val: string) => {
      const next = [...levels.value];
      next[idx] = { ...next[idx], lvl: val };
      setLevels(next);
      setIsDirty(true);
    };

    const LEVEL_LABELS = ["ระดับที่ 1 (Basic Level)", "ระดับที่ 2 (Doing Level)", "ระดับที่ 3 (Developing Level)", "ระดับที่ 4 (Advanced Level)", "ระดับที่ 5 (Expert Level)"];return () =>


    <>
      <div class="flex ic jb mb20">
        <div>
          <div class="sec-t">{view.value === 'list' ? 'พจนานุกรมสมรรถนะ 📖' : view.value === 'add' ? 'เพิ่มสมรรถนะการประเมิน 🆕' : 'แก้ไขสมรรถนะการประเมิน 📝'}</div>
          <div class="sec-s">{view.value === 'list' ? 'CC (Core) · MC (Managerial) · FC1/FC2 (Functional) · ระดับ 1–5 · พฤติกรรมบ่งชี้' : 'กรอกข้อมูลสมรรถนะและรายละเอียดระดับพฤติกรรม'}</div>
        </div>
        <div class="flex" style={{ gap: "8px" }}>
          {view.value === 'list' ?
          <>
              <button class="btn btn-s" onClick={() => setShowImport(true)}>📥 Import Excel</button>
              <button class="btn btn-p" onClick={() => {clearForm();setView("add");}}>+ เพิ่มสมรรถนะ</button>
            </> :

          <button class="btn btn-s" onClick={closeForm}>⬅️ กลับหน้ารายการ</button>
          }
        </div>
      </div>

      {showImport.value &&
      <ExcelImportModal
        title="นำเข้าข้อมูลสมรรถนะ"
        templateName="Competency_Template.xlsx"
        onClose={() => setShowImport(false)} />

      }

      {status.value &&
      <div class="status-msg anim-fade-in" style={{ position: "fixed", top: "20px", right: "20px", zIndex: 9999, padding: "12px 24px", borderRadius: "var(--r)", boxShadow: "0 10px 15px -3px rgba(0, 0, 0, 0.2)", background: status.value.type === 's' ? '#10b981' : '#ef4444', color: '#fff', fontWeight: 700, display: "flex", alignItems: "center", gap: "10px" }}>
          <span>{status.value.type === 's' ? '✅' : '⚠️'}</span>
          {status.value.msg}
        </div>
      }

      {view.value !== 'list' ?
      <div class="anim-fade-in">
          <div class="card shadow-sm mb20">
            <div class="ch"><div class="ct">ข้อมูลสมรรถนะหลัก</div></div>
            <div class="cb">
              <div class="flex g20 mb16">
                <div class="fg" style={{ width: "320px" }}>
                  <label class="lbl">1. ประเภทสมรรถนะ (Competency Type)</label>
                  <select class="sel" value={type.value} onChange={(e) => {setType(e.target.value);setIsDirty(true);}}>
                    {competencyTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>
                <div class="fg flex-1">
                  <label class="lbl">2. รหัสและชื่อสมรรถนะ (Code & Name)</label>
                  <div class="flex ic g0">
                    <div class="static-code" style={{ padding: '10px 16px', background: '#f8fafc', border: '1px solid var(--border)', borderRadius: '8px 0 0 8px', color: 'var(--text3)', fontWeight: 700, fontSize: '14px' }}>{type.value}-</div>
                    <input class="inp" style={{ width: '80px', borderRadius: '0' }} placeholder="001" value={codeTail.value} onChange={(e) => {setCodeTail(e.target.value);setIsDirty(true);}} />
                    <input class="inp" style={{ borderRadius: '0 8px 8px 0', borderLeft: 'none' }} placeholder="กรอกชื่อสมรรถนะ..." value={name.value} onChange={(e) => {setName(e.target.value);setIsDirty(true);}} />
                  </div>
                </div>
              </div>
              <div class="fg">
                <label class="lbl">3. รายละเอียดสมรรถนะ (Description)</label>
                <textarea class="inp" rows={3} placeholder="กรอกคำอธิบายความหมายของสมรรถนะ..." value={desc.value} onChange={(e) => {setDesc(e.target.value);setIsDirty(true);}}></textarea>
              </div>
            </div>
          </div>

          <div class="card shadow-sm mb20">
            <div class="ch flex ic jb">
              <div class="ct">รายละเอียดพฤติกรรมบ่งชี้</div>
              <button class="btn btn-s btn-xs" onClick={addLevelRow}>+ เพิ่มระดับ (Row)</button>
            </div>
            <div class="cb p0">
              <table class="tbl tbl-clean tbl-add-dict">
                <thead>
                  <tr>
                    <th style={{ width: '80px', textAlign: 'center' }}>ระดับ</th>
                    <th style={{ width: '100px', textAlign: 'center' }}>น้ำหนัก</th>
                    <th>รายละเอียดพฤติกรรม</th>
                    <th style={{ width: '60px' }}></th>
                  </tr>
                </thead>
                <tbody>
                  {levels.value.map((lv, lvIdx) =>
                <Fragment key={lvIdx}>
                      {lv.items.map((item, itemIdx) =>
                  <tr key={`${lvIdx}-${itemIdx}`} style={{ borderBottom: itemIdx === 3 ? '2.5px solid #e2e8f0' : '1px solid #f1f5f9' }}>
                          {itemIdx === 0 &&
                    <td rowSpan={4} style={{ verticalAlign: 'top', background: '#fcfdfe', borderRight: '1px solid #e2e8f0' }}>
                              <div class="flex col ic g8" style={{ marginTop: '4px' }}>
                                <div class="muted fs10 fw7">LEVEL</div>
                                <input class="inp ac" style={{ width: '50px' }} value={lv.lvl} onChange={(e) => updateLvlNum(lvIdx, e.target.value)} />
                                <button class="btn-icon-r" title="ลบระดับนี้" onClick={() => removeLevelRow(lvIdx)}>🗑️</button>
                              </div>
                            </td>
                    }
                          <td style={{ verticalAlign: 'middle', textAlign: 'center' }}>
                            <div class="fw5 fs11 muted mb4">น้ำหนัก</div>
                            <input class="inp ac" style={{ width: '80px', color: 'var(--blue)', fontWeight: 800 }} type="text" value={lv.weights ? lv.weights[itemIdx] : 0.25} onChange={(e) => updateWeight(lvIdx, itemIdx, e.target.value)} />
                          </td>
                          <td>
                            <input class="inp" placeholder={`พฤติกรรมบ่งชี้ข้อที่ ${itemIdx + 1}...`} value={item} onChange={(e) => updateIndicator(lvIdx, itemIdx, e.target.value)} />
                          </td>
                        </tr>
                  )}
                    </Fragment>
                )}
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex jc g12 mt24 mb40">
            <button class="btn btn-s w140" onClick={() => {clearForm();setView("list");}}>ยกเลิก</button>
            <button class="btn btn-p w140" onClick={saveComp}>บันทึกข้อมูล</button>
          </div>
        </div> :

      <>
          <div style={{ display: 'flex', gap: '12px', marginBottom: '16px', alignItems: 'center' }}>
            <div class="flex ic g8">
              <span class="muted fs12 fw7 uppercase tracking-wider">แสดงประเภท:</span>
              <select class="sel sel-sm" style={{ width: "200px" }} value={typeFilter.value} onChange={(e) => setTypeFilter(e.target.value)}>
                <option value="ทั้งหมด">ทั้งหมด (All)</option>
                {competencyTypes.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            </div>
            <div class="flex ic g8">
              <span class="muted fs12 fw7 uppercase tracking-wider">เรียงตาม:</span>
              <select class="sel sel-sm" style={{ width: "160px" }} value={sortBy.value} onChange={(e) => setSortBy(e.target.value)}>
                <option value="newest">วันที่อัปเดตล่าสุด</option>
                <option value="code">รหัสสมรรถนะ (A-Z)</option>
                <option value="name">ชื่อสมรรถนะ (ก-ฮ)</option>
                <option value="type">ประเภทสมรรถนะ</option>
              </select>
            </div>
          </div>

          <div class="dict-list">
            {sorted.map((c, i) => {
            const isExpanded = expandedCode.value === c.cd;
            return (
              <div key={i} class="dict-group">
                  <div
                  class={`dict-row ${isExpanded ? 'active' : ''}`}
                  onClick={() => {
                    setExpandedCode(isExpanded ? null : c.cd);
                    setExpandedLevel(null);
                  }}>
                  
                    <div class="dict-info">
                      <span class="dict-code">{c.cd}</span>
                      <span class="dict-name">{c.n}</span>
                      <span class={`dict-tag ${getCompTag(c)}`}>{getCompType(c)}</span>
                    </div>
                    <div class="dict-dots"></div>
                    <div class="flex ic g8">
                      <button class="btn btn-xs btn-s" onClick={(e) => {e.stopPropagation();editComp(c);}}>แก้ไข</button>
                      <button class="btn btn-xs btn-s" style={{ color: "#ef4444" }} onClick={(e) => {e.stopPropagation();deleteComp(c.cd);}}>ลบ</button>
                      <div class="dict-arrow" style={{ transform: isExpanded ? 'rotate(90deg)' : 'none' }}>›</div>
                    </div>
                  </div>

                  {isExpanded &&
                <div class="dict-content anim-slide-down">
                      <div class="dict-desc">{c.det}</div>
                      <div class="dict-levels-list">
                        {[1, 2, 3, 4, 5].map((lvNum) => {
                      const isLevelExpanded = expandedLevel.value === lvNum;
                      const lvData = c.levels?.find((l: any) => l.lvl === lvNum);
                      const indicators = lvData?.indicators || [
                      `พฤติกรรมบ่งชี้ข้อที่ 1 ของ${LEVEL_LABELS[lvNum - 1]}`,
                      `พฤติกรรมบ่งชี้ข้อที่ 2 ของ${LEVEL_LABELS[lvNum - 1]}`,
                      `พฤติกรรมบ่งชี้ข้อที่ 3 ของ${LEVEL_LABELS[lvNum - 1]}`,
                      `พฤติกรรมบ่งชี้ข้อที่ 4 ของ${LEVEL_LABELS[lvNum - 1]}`];


                      return (
                        <div key={lvNum} class="dict-level-box">
                              <div
                            class={`dict-row-lvl ${isLevelExpanded ? 'active' : ''}`}
                            onClick={() => setExpandedLevel(isLevelExpanded ? null : lvNum)}>
                            
                                <span class="dict-lvl-label">{LEVEL_LABELS[lvNum - 1]}</span>
                                <div class="dict-dots"></div>
                                <div class="dict-arrow" style={{ transform: isLevelExpanded ? 'rotate(90deg)' : 'none' }}>›</div>
                              </div>
                              {isLevelExpanded &&
                          <div class="dict-indicators anim-fade-in">
                                  {indicators.map((ind: string, idx: number) =>
                            <div key={idx} class="dict-ind-row">
                                      <div class="dict-weight">
                                        <div class="fs10 muted fw4">น้ำหนัก</div>
                                        {lvData?.weights?.[idx] ?? 0.25}
                                      </div>
                                      <div class="dict-ind-text">
                                        {ind.startsWith("▪") ? ind : `▪ ${ind}`}
                                      </div>
                                    </div>
                            )}
                                </div>
                          }
                            </div>);

                    })}
                      </div>
                    </div>
                }
                </div>);

          })}
          </div>
        </>
      }

      <style>{`
        .dict-list { display: flex; flex-direction: column; gap: 4px; background: #fff; border-radius: var(--r-lg); border: 1px solid var(--border); overflow: hidden; }
        .dict-group { border-bottom: 1px solid #f1f5f9; }
        .dict-group:last-child { border-bottom: none; }
        
        .dict-row { display: flex; align-items: center; padding: 16px 20px; cursor: pointer; transition: 0.2s; }
        .dict-row:hover { background: #f8fafc; }
        .dict-row.active { background: #f8fafc; }
        
        .dict-info { display: flex; align-items: center; gap: 12px; }
        .dict-code { font-family: monospace; font-size: 11px; color: var(--text3); font-weight: 700; width: 60px; }
        .dict-name { font-weight: 700; font-size: 15px; color: var(--navy); }
        .dict-tag { font-size: 9px; padding: 1px 6px; border-radius: 4px; font-weight: 800; }
        
        .dict-dots { flex: 1; border-bottom: 1px dotted #cbd5e1; margin: 0 16px; align-self: center; margin-bottom: -4px; height: 12px; }
        .dict-arrow { color: var(--text3); font-size: 20px; font-weight: 300; width: 20px; text-align: center; }
        
        .dict-content { background: #fff; }
        .dict-desc { padding: 4px 20px 16px 92px; font-size: 12px; color: var(--text3); }
        
        .dict-levels-list { margin-left: 72px; padding-bottom: 16px; border-left: 2px solid #f1f5f9; }
        .dict-level-box { margin-bottom: 2px; }
        
        .dict-row-lvl { display: flex; align-items: center; padding: 10px 20px; cursor: pointer; transition: 0.2s; }
        .dict-row-lvl:hover { background: var(--blue-lt); }
        .dict-row-lvl.active { background: var(--blue-lt); color: var(--blue); }
        .dict-lvl-label { font-size: 13px; font-weight: 700; }
        
        .dict-indicators { padding: 8px 20px 16px 40px; }
        .dict-ind-row { display: flex; gap: 16px; padding: 8px 0; border-bottom: 1px solid #f8fafc; }
        .dict-weight { min-width: 40px; font-weight: 800; font-size: 13px; color: var(--blue); text-align: center; }
        .dict-ind-text { font-size: 13px; color: var(--text2); line-height: 1.6; }
        
        .anim-slide-down { animation: slideDown 0.3s ease-out; overflow: hidden; }
        @keyframes slideDown { from { max-height: 0; opacity: 0; } to { max-height: 2000px; opacity: 1; } }
        .anim-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        .static-code { border-right: none !important; border-radius: 8px 0 0 8px !important; }
        .static-code + .inp { border-radius: 0 !important; margin-left: -1px; }
        .static-code + .inp + .inp { border-radius: 0 8px 8px 0 !important; margin-left: -1px; }
        
        .tbl-add-dict th { background: #f8fafc; font-size: 12px; font-weight: 700; color: var(--text3); border-bottom: 1.5px solid var(--border); padding: 12px; }
        .tbl-add-dict td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .btn-icon-r { background: none; border: none; font-size: 16px; cursor: pointer; opacity: 0.6; transition: 0.2s; padding: 8px; border-radius: 6px; }
        .btn-icon-r:hover { opacity: 1; background: #fee2e2; }
        .w140 { width: 140px; }
        .ac { text-align: center; }
        .py8 { padding-top: 8px; padding-bottom: 8px; }
        .mr12 { margin-right: 12px; }
        .sel-sm { font-size: 12px; border-radius: 6px; }
      `}</style>
    </>;} });



export default AdminDict;
</script>
