<script lang="tsx">
import { defineComponent, ref, watchEffect, type PropType } from "vue";
import { router } from "@inertiajs/vue3";
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

interface AdminOrgStructureProps {academicDepts: string[];setAcademicDepts: any;supportDepts: string[];supportPositionGroups: Record<string, string[]>;setSupportPositionGroups: any;adminDepts: string[];setAdminDepts: any;supportOrg: any;setSupportOrg: any;users: any[];orgSups: Record<string, string>;setOrgSups: any;academicPos: string[];setAcademicPos: any;supportPos: string[];setSupportPos: any;adminPos: string[];setAdminPos: any;
  academicRank: string[];
  setAcademicRank: any;
  supportRank: string[];
  setSupportRank: any;
  worklines: string[];
  setWorklines: any;
  competencyTypes: any[];
  setCompetencyTypes: any;
  learningMethods: {key: string;label: string;desc?: string;}[];
  setLearningMethods: any;
}

const AdminOrgStructure = defineComponent({ name: "AdminOrgStructure", props: ["academicDepts", "setAcademicDepts", "supportDepts", "supportPositionGroups", "setSupportPositionGroups", "adminDepts", "setAdminDepts", "supportOrg", "setSupportOrg", "users", "orgSups", "setOrgSups", "academicPos", "setAcademicPos", "supportPos", "setSupportPos", "adminPos", "setAdminPos", "academicRank", "setAcademicRank", "supportRank", "setSupportRank", "worklines", "setWorklines", "competencyTypes", "setCompetencyTypes", "learningMethods", "setLearningMethods"], setup(__props) {const {
      academicDepts, setAcademicDepts,
      supportDepts, supportPositionGroups, setSupportPositionGroups,
      adminDepts, setAdminDepts,
      supportOrg, setSupportOrg,
      users, orgSups, setOrgSups,
      academicPos, setAcademicPos,
      supportPos, setSupportPos,
      adminPos, setAdminPos,
      academicRank, setAcademicRank,
      supportRank, setSupportRank,
      worklines, setWorklines,
      competencyTypes, setCompetencyTypes,
      learningMethods, setLearningMethods
    } = __props as any;
    const POSITION_PREVIEW_LIMIT = 4;
    const SUPPORT_GROUP_PREVIEW_LIMIT = 4;
    const competencyTypeList = ref<any[]>([...((__props as any).competencyTypes || [])]);
    const [activeTab, setActiveTab] = useState("workline");
    const [editingItem, setEditingId] = useState<any>(null);
    const [newValue, setNewValue] = useState("");
    const [editFullName, setEditFullName] = useState("");
    const [editDesc, setEditDesc] = useState("");
    const [newSupportDeptName, setNewSupportDeptName] = useState("");
    const [newSupportWorkNames, setNewSupportWorkNames] = useState<Record<string, string>>({});
    const [newSupportUnitNames, setNewSupportUnitNames] = useState<Record<string, string>>({});
    const [showAddModal, setShowAddModal] = useState(false);
    const [expandedSupportGroups, setExpandedSupportGroups] = useState<Record<string, boolean>>({});
    const [showAllSupportGroups, setShowAllSupportGroups] = useState(false);
    const [addItemData, setAddItemData] = useState({
      category: "workline",
      type: "1",
      name: "",
      fullName: "",
      desc: "",
      parent: "",
      grandparent: ""
    });
    const dean = users.find((user) => user.r === "manager")?.n || "";
    const deptManagers = users.filter((user) => user.r === "manager_dept");
    const supervisors = users.filter((user) => user.r === "supervisor");
    const getCompetencyTypeCode = (item: any) => typeof item === "string" ? item : item?.code || item?.name || "";
    const getCompetencyTypeFullName = (item: any) => typeof item === "string" ? "" : item?.fullName || item?.label || "";
    const getCompetencyTypeDesc = (item: any) => typeof item === "string" ? "" : item?.desc || "";
    const getCompetencyTypeId = (item: any) => typeof item === "string" ? null : item?.id || null;
    useEffect(() => {
      competencyTypeList.value = [...((( __props as any).competencyTypes) || [])];
    }, [(__props as any).competencyTypes]);
    const applyCompetencyTypes = (next: any[]) => {
      competencyTypeList.value = [...next];
      setCompetencyTypes(next);
    };
    const syncCompetencyTypesFromPage = (responsePage: any) => {
      if (Array.isArray(responsePage.props.competencyTypes)) {
        applyCompetencyTypes(responsePage.props.competencyTypes);
      }
    };
    const showPersistError = (errors: any) => {
      const firstError = Object.values(errors || {})[0];
      alert(firstError || "ไม่สามารถบันทึกข้อมูลประเภทสมรรถนะได้");
    };
    const persistOptions = {
      preserveScroll: true,
      preserveState: true,
      onSuccess: syncCompetencyTypesFromPage,
      onError: showPersistError
    };

    const setOrgHead = (path: string, value: string) => {
      setOrgSups((current) => ({ ...current, [path]: value }));
    };

    const addSupportDept = () => {
      const name = newSupportDeptName.value.trim();
      if (!name || supportOrg[name]) return;
      setSupportOrg({ ...supportOrg, [name]: [] });
      setSupportPositionGroups({ ...supportPositionGroups, [name]: [] });
      setOrgSups((current) => ({ ...current, [name]: deptManagers[0]?.n || dean }));
      setNewSupportDeptName("");
    };

    const addSupportWork = (dept: string) => {
      const name = (newSupportWorkNames.value[dept] || "").trim();
      if (!name) return;
      const works = supportOrg[dept] || [];
      if (works.some((item: any) => item.work === name)) return;
      setSupportOrg({ ...supportOrg, [dept]: [...works, { work: name, units: [] }] });
      setOrgSups((current) => ({ ...current, [[dept, name].join(" > ")]: supervisors[0]?.n || "" }));
      setNewSupportWorkNames((current) => ({ ...current, [dept]: "" }));
    };

    const addSupportUnit = (dept: string, workName: string) => {
      const workPath = [dept, workName].join(" > ");
      const name = (newSupportUnitNames.value[workPath] || "").trim();
      if (!name) return;
      const works = supportOrg[dept] || [];
      setSupportOrg({
        ...supportOrg,
        [dept]: works.map((item: any) =>
        item.work === workName && !(item.units || []).includes(name) ?
        { ...item, units: [...(item.units || []), name] } :
        item
        )
      });
      setNewSupportUnitNames((current) => ({ ...current, [workPath]: "" }));
    };

    const startEdit = (type: string, oldName: string, extras?: any) => {
      setEditingId({ type, oldName, ...extras });
      setNewValue(oldName);
      setEditFullName(type === "comp-type" ? getCompetencyTypeFullName(extras?.item) : "");
      setEditDesc(type === "comp-type" ? getCompetencyTypeDesc(extras?.item) : "");
    };

    const saveEdit = () => {
      if (!newValue.value.trim()) return;
      const { type, oldName, parent, workName } = editingItem.value;
      switch (type) {
        case "academic-dept":setAcademicDepts(academicDepts.map((v) => v === oldName ? newValue.value : v));break;
        case "admin-dept":setAdminDepts(adminDepts.map((v) => v === oldName ? newValue.value : v));break;
        case "support-dept":{
            const nextSupportPositionGroups = { ...supportPositionGroups };
            nextSupportPositionGroups[newValue.value] = nextSupportPositionGroups[oldName] || [];
            delete nextSupportPositionGroups[oldName];
            setSupportPositionGroups(nextSupportPositionGroups);
            break;
          }
        case "support-group-pos":
          setSupportPositionGroups({
            ...supportPositionGroups,
            [parent]: (supportPositionGroups[parent] || []).map((v) => v === oldName ? newValue.value : v)
          });
          break;
        case "support-work":{
            const nextSupportOrg = { ...supportOrg };
            if (parent && nextSupportOrg[parent]) {
              nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === oldName ? { ...w, work: newValue.value } : w);
              setSupportOrg(nextSupportOrg);
            }
            break;
          }
        case "support-unit":{
            const nextSupportOrg = { ...supportOrg };
            if (parent && workName && nextSupportOrg[parent]) {
              nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === workName ? { ...w, units: w.units.map((u: string) => u === oldName ? newValue.value : u) } : w);
              setSupportOrg(nextSupportOrg);
            }
            break;
          }
        case "academic-pos":setAcademicPos(academicPos.map((v) => v === oldName ? newValue.value : v));break;
        case "support-pos":setSupportPos(supportPos.map((v) => v === oldName ? newValue.value : v));break;
        case "admin-pos":setAdminPos(adminPos.map((v) => v === oldName ? newValue.value : v));break;
        case "academic-rank":setAcademicRank(academicRank.map((v) => v === oldName ? newValue.value : v));break;
        case "support-rank":setSupportRank(supportRank.map((v) => v === oldName ? newValue.value : v));break;
        case "workline":setWorklines(worklines.map((v) => v === oldName ? newValue.value : v));break;
        case "comp-type":
          if (!editFullName.value.trim() || !editDesc.value.trim()) return;
          {
            const payload = {
              code: newValue.value.trim(),
              full_name: editFullName.value.trim(),
              description: editDesc.value.trim()
            };
            const id = getCompetencyTypeId(editingItem.value.item);
            if (id) {
              router.put(route("admin.competency-types.update", id), payload, {
                ...persistOptions,
                onSuccess: (responsePage: any) => {
                  syncCompetencyTypesFromPage(responsePage);
                  setEditingId(null);
                }
              });
            } else {
              router.post(route("admin.competency-types.store"), payload, {
                ...persistOptions,
                onSuccess: (responsePage: any) => {
                  syncCompetencyTypesFromPage(responsePage);
                  setEditingId(null);
                }
              });
            }
            return;
          }
        case "learning-method":
          setLearningMethods(learningMethods.map((item) => item.key === oldName ? { ...item, label: newValue.value } : item));
          break;
      }
      setEditingId(null);
    };

    const deleteItem = () => {
      const { type, oldName, parent, workName } = editingItem.value;

      switch (type) {
        case "academic-dept":setAcademicDepts(academicDepts.filter((v) => v !== oldName));break;
        case "admin-dept":setAdminDepts(adminDepts.filter((v) => v !== oldName));break;
        case "support-dept":{
            const nextSupportPositionGroups = { ...supportPositionGroups };
            delete nextSupportPositionGroups[oldName];
            setSupportPositionGroups(nextSupportPositionGroups);
            break;
          }
        case "support-group-pos":
          setSupportPositionGroups({
            ...supportPositionGroups,
            [parent]: (supportPositionGroups[parent] || []).filter((v) => v !== oldName)
          });
          break;
        case "support-work":{
            const nextSupportOrg = { ...supportOrg };
            if (parent && nextSupportOrg[parent]) {
              nextSupportOrg[parent] = nextSupportOrg[parent].filter((w: any) => w.work !== oldName);
              setSupportOrg(nextSupportOrg);
            }
            break;
          }
        case "support-unit":{
            const nextSupportOrg = { ...supportOrg };
            if (parent && workName && nextSupportOrg[parent]) {
              nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === workName ? { ...w, units: w.units.filter((u: string) => u !== oldName) } : w);
              setSupportOrg(nextSupportOrg);
            }
            break;
          }
        case "academic-pos":setAcademicPos(academicPos.filter((v) => v !== oldName));break;
        case "support-pos":setSupportPos(supportPos.filter((v) => v !== oldName));break;
        case "admin-pos":setAdminPos(adminPos.filter((v) => v !== oldName));break;
        case "academic-rank":setAcademicRank(academicRank.filter((v) => v !== oldName));break;
        case "support-rank":setSupportRank(supportRank.filter((v) => v !== oldName));break;
        case "workline":setWorklines(worklines.filter((v) => v !== oldName));break;
        case "comp-type":{
            const id = getCompetencyTypeId(editingItem.value.item);
            if (id) {
              router.delete(route("admin.competency-types.destroy", id), {
                ...persistOptions,
                onSuccess: (responsePage: any) => {
                  syncCompetencyTypesFromPage(responsePage);
                  setEditingId(null);
                }
              });
            } else {
              applyCompetencyTypes(competencyTypeList.value.filter((v) => getCompetencyTypeCode(v) !== oldName));
              setEditingId(null);
            }
            return;
          }
        case "learning-method":setLearningMethods(learningMethods.filter((item) => item.key !== oldName));break;
      }
      setEditingId(null);
    };

    const openAddItem = () => {
      const nextItem = activeTab.value === "comp" ?
      { category: "comp", type: "1", name: "", fullName: "", desc: "", parent: "", grandparent: "" } :
      { category: "workline", type: "1", name: "", fullName: "", desc: "", parent: "", grandparent: "" };
      setShowAddModal(true);
      setAddItemData(nextItem);
    };

    const getAddModalCopy = () => {
      const typeLabel = addItemData.value.type === "1" ?
      "สายวิชาการ" :
      addItemData.value.type === "2" ?
      "สายสนับสนุน" :
      "สายงานบริหาร";

      if (addItemData.value.category === "workline") return { title: "เพิ่มสายงาน", label: "ชื่อสายงาน" };
      if (addItemData.value.category === "comp") return { title: "เพิ่มประเภทสมรรถนะ", label: "รหัสประเภทสมรรถนะ" };
      if (addItemData.value.category === "learning") return { title: "เพิ่มประเภทการเรียนรู้", label: "ชื่อประเภทการเรียนรู้" };
      if (addItemData.value.category === "dept") return { title: `เพิ่มกลุ่มงาน${typeLabel}`, label: "ชื่อกลุ่มงาน" };
      if (addItemData.value.category === "pos") {
        return {
          title: addItemData.value.parent ? `เพิ่มตำแหน่ง ${addItemData.value.parent}` : `เพิ่มตำแหน่ง${typeLabel}`,
          label: "ชื่อตำแหน่ง"
        };
      }
      if (addItemData.value.category === "rank") return { title: `เพิ่มระดับตำแหน่ง${typeLabel}`, label: "ชื่อระดับตำแหน่ง" };
      if (addItemData.value.category === "work") return { title: `เพิ่มงาน ${addItemData.value.parent}`, label: "ชื่องาน" };
      if (addItemData.value.category === "unit") return { title: `เพิ่มหน่วย ${addItemData.value.parent}`, label: "ชื่อหน่วย" };
      return { title: "เพิ่มรายการ", label: "ชื่อรายการ" };
    };

    const saveAddItem = () => {
      const { category, type, name, fullName, desc, parent, grandparent } = addItemData.value;
      if (name.trim()) {
        if (category === "pos" && type === "2" && !parent) return;
        if (category === "dept") {
          if (type === "1") setAcademicDepts([...academicDepts, name]);else
          if (type === "3") setAdminDepts([...adminDepts, name]);else
          {
            setSupportPositionGroups({ ...supportPositionGroups, [name]: [] });
          }
        } else if (category === "work") {
          const nextSupportOrg = { ...supportOrg };
          if (parent && nextSupportOrg[parent]) {
            nextSupportOrg[parent] = [...nextSupportOrg[parent], { work: name, units: [] }];
            setSupportOrg(nextSupportOrg);
          }
        } else if (category === "unit") {
          const nextSupportOrg = { ...supportOrg };
          if (grandparent && parent && nextSupportOrg[grandparent]) {
            nextSupportOrg[grandparent] = nextSupportOrg[grandparent].map((w: any) => w.work === parent ? { ...w, units: [...w.units, name] } : w);
            setSupportOrg(nextSupportOrg);
          }
        } else if (category === "pos") {
          if (type === "1") setAcademicPos([...academicPos, name]);else
          if (type === "2" && parent) {
            setSupportPositionGroups({
              ...supportPositionGroups,
              [parent]: [...(supportPositionGroups[parent] || []), name]
            });
          } else
          if (type === "3") setAdminPos([...adminPos, name]);
        } else if (category === "rank") {
          if (type === "1") setAcademicRank([...academicRank, name]);else
          if (type === "2") setSupportRank([...supportRank, name]);
        } else if (category === "workline") {
          setWorklines([...worklines, name]);
        } else if (category === "comp") {
          if (!fullName.trim() || !desc.trim()) return;
          router.post(route("admin.competency-types.store"), {
            code: name.trim(),
            full_name: fullName.trim(),
            description: desc.trim()
          }, {
            ...persistOptions,
            onSuccess: (responsePage: any) => {
              syncCompetencyTypesFromPage(responsePage);
              setShowAddModal(false);
            }
          });
          return;
        } else if (category === "learning") {
          const baseKey = name.
          trim().
          toLowerCase().
          replace(/[^a-z0-9]+/g, "-").
          replace(/^-+|-+$/g, "") || `learning-${learningMethods.length + 1}`;
          let uniqueKey = baseKey;
          let suffix = 2;
          while (learningMethods.some((item) => item.key === uniqueKey)) {
            uniqueKey = `${baseKey}-${suffix}`;
            suffix += 1;
          }
          setLearningMethods([
          ...learningMethods,
          {
            key: uniqueKey,
            label: name.trim(),
            desc: desc.trim() || `รายละเอียดสำหรับ ${name.trim()}`
          }]
          );
        }
        setShowAddModal(false);
      }
    };return () =>


    <>
      <div class="flex ic jb mb20">
        <div>
          <div class="sec-t">จัดการโครงสร้างองค์กรและสมรรถนะ</div>
          <div class="sec-s">กลุ่มงาน ตำแหน่ง ระดับตำแหน่ง และประเภทสมรรถนะ</div>
        </div>
        {activeTab.value === "workline" &&
        <button class="btn btn-p" onClick={openAddItem}>
            + เพิ่มสายงาน
          </button>
        }
      </div>

      <div class="structure-tabs mb20">
        <button class={`structure-tab ${activeTab.value === "workline" ? "active" : ""}`} onClick={() => setActiveTab("workline")}>สายงาน</button>
        <button class={`structure-tab ${activeTab.value === "support-chain" ? "active" : ""}`} onClick={() => setActiveTab("support-chain")}>ฝ่าย/งาน</button>
        <button class={`structure-tab ${activeTab.value === "dept" ? "active" : ""}`} onClick={() => setActiveTab("dept")}>กลุ่มงาน</button>
        <button class={`structure-tab ${activeTab.value === "pos" ? "active" : ""}`} onClick={() => setActiveTab("pos")}>ระดับตำแหน่ง</button>
        <button class={`structure-tab ${activeTab.value === "comp" ? "active" : ""}`} onClick={() => setActiveTab("comp")}>ประเภทสมรรถนะ</button>
      </div>

      <div class="anim-fade-in">
        <div class="structure-shell">
          {activeTab.value === "workline" ?
          <div class="structure-pane">
              <div class="structure-heading">สายงานและตำแหน่ง</div>
              <div class="structure-stack">
                {worklines.map((wl) => {
                let posList: string[] = [];
                let type = "1";
                let editPosType = "academic-pos";
                if (wl === "สายวิชาการ") {posList = academicPos;type = "1";editPosType = "academic-pos";} else
                if (wl === "สายสนับสนุน") {
                  return (
                    <section key={wl} class="structure-section">
                        <div class="structure-section-head">
                          <div class="fw7 fs14 text-navy">{wl}</div>
                          <div class="flex g8">
                            <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "dept", type: "2", name: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มกลุ่มงาน</button>
                            <button class="btn btn-s btn-sm" onClick={() => startEdit("workline", wl)}>แก้ไข</button>
                          </div>
                        </div>
                        <div class="support-columns">
                          {(showAllSupportGroups.value ? supportDepts : supportDepts.slice(0, SUPPORT_GROUP_PREVIEW_LIMIT)).map((group) =>
                        <div key={group} class="support-column">
                              <div class="support-column-head">
                                <div class="fw7 fs13 text-navy">{group}</div>
                                <button class="btn-link" onClick={() => startEdit("support-dept", group)} title="แก้ไขกลุ่มงาน">✎</button>
                              </div>
                              <div class="support-position-list">
                                {(expandedSupportGroups.value[group] ?
                            supportPositionGroups[group] || [] :
                            (supportPositionGroups[group] || []).slice(0, POSITION_PREVIEW_LIMIT)).
                            map((item) =>
                            <div key={item} class="structure-item group">
                                    <span class="fs12 fw6 text-gray-700">{item}</span>
                                    <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("support-group-pos", item, { parent: group })}>✎</button>
                                  </div>
                            )}
                                {(supportPositionGroups[group] || []).length === 0 && <div class="structure-empty">ยังไม่มีตำแหน่ง</div>}
                              </div>
                              {(supportPositionGroups[group] || []).length > POSITION_PREVIEW_LIMIT &&
                          <button
                            class="support-more"
                            onClick={() => setExpandedSupportGroups((current) => ({ ...current, [group]: !current[group] }))}>
                            
                                  {expandedSupportGroups.value[group] ?
                            "ย่อรายการ" :
                            `ดูเพิ่มเติม ${(supportPositionGroups[group] || []).length - POSITION_PREVIEW_LIMIT} รายการ`}
                                </button>
                          }
                              <button class="support-add" onClick={() => {setAddItemData({ category: "pos", type: "2", name: "", parent: group, grandparent: "" });setShowAddModal(true);}}>+ เพิ่มตำแหน่ง</button>
                            </div>
                        )}
                        </div>
                        {supportDepts.length > SUPPORT_GROUP_PREVIEW_LIMIT &&
                      <button class="support-group-more" onClick={() => setShowAllSupportGroups((current) => !current)}>
                            {showAllSupportGroups.value ? "ย่อกลุ่มงาน" : `ดูกลุ่มงานเพิ่มเติม ${supportDepts.length - SUPPORT_GROUP_PREVIEW_LIMIT} กลุ่ม`}
                          </button>
                      }
                      </section>);

                } else
                if (wl === "สายงานบริหาร") {posList = adminPos;type = "3";editPosType = "admin-pos";}
                return (
                  <section key={wl} class="structure-section">
                      <div class="structure-section-head">
                        <div class="fw7 fs14 text-navy">{wl}</div>
                        <div class="flex g8">
                          <button
                          class="btn btn-s btn-sm"
                          onClick={() => {
                            setAddItemData({
                              category: wl === "สายวิชาการ" ? "dept" : "pos",
                              type,
                              name: "",
                              parent: "",
                              grandparent: ""
                            });
                            setShowAddModal(true);
                          }}>
                          
                            {wl === "สายวิชาการ" ? "+ เพิ่มกลุ่มงาน" : "+ เพิ่มตำแหน่ง"}
                          </button>
                          <button class="btn btn-s btn-sm" onClick={() => startEdit("workline", wl)}>แก้ไข</button>
                        </div>
                      </div>
                      <div class="structure-grid">
                        {posList.length > 0 ? posList.map((p) =>
                      <div key={p} class="structure-item group">
                            <span class="fs13 fw6 text-gray-700 truncate">{p}</span>
                            <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit(editPosType, p)}>✎</button>
                          </div>
                      ) :
                      <div class="structure-empty">ยังไม่มีข้อมูลตำแหน่งงานใน{wl}</div>
                      }
                      </div>
                    </section>);

              })}
              </div>
            </div> :
          activeTab.value === "support-chain" ?
          <div class="structure-pane">
              <div class="structure-heading">ฝ่าย/งาน สายสนับสนุน</div>
              <div class="structure-note">
                <b>กำหนดได้สูงสุดอย่างละ 1 คน</b>
                <span>หัวหน้าฝ่ายใช้บทบาทผู้บังคับบัญชา ส่วนหัวหน้างานใช้บทบาทหัวหน้างาน หน้าเพิ่มผู้ใช้จะอ้างอิงค่าจากหน้านี้โดยอัตโนมัติ</span>
              </div>
              <section class="structure-section">
                <div class="structure-section-head">
                  <div class="fw7 fs14 text-navy">เพิ่มฝ่ายสนับสนุน</div>
                  <div class="flex g8" style={{ minWidth: 0 }}>
                    <input class="inp" value={newSupportDeptName.value} onChange={(e) => setNewSupportDeptName(e.target.value)} placeholder="ชื่อฝ่ายใหม่" />
                    <button class="btn btn-s btn-sm" onClick={addSupportDept}>+ เพิ่มฝ่าย</button>
                  </div>
                </div>
              </section>
              <div class="structure-stack">
                {Object.keys(supportOrg).map((dept) =>
              <section key={dept} class="structure-section">
                    <div class="structure-section-head">
                      <div>
                        <div class="fw8 fs14 text-navy">{dept}</div>
                        <div class="muted fs11">หัวหน้าฝ่าย (ผู้บังคับบัญชา)</div>
                      </div>
                      <select class="sel" style={{ maxWidth: "320px" }} value={orgSups[dept] || ""} onChange={(e) => setOrgHead(dept, e.target.value)}>
                        <option value="">— เลือกหัวหน้าฝ่าย —</option>
                        {deptManagers.map((user) => <option key={user.sso} value={user.n}>{user.t}{user.n} · {user.p}</option>)}
                      </select>
                    </div>
                    <div class="support-work-grid">
                      {(supportOrg[dept] || []).map((work: any) => {
                    const workPath = [dept, work.work].join(" > ");
                    return (
                      <div key={work.work} class="support-work-card">
                            <div class="support-work-head">
                              <div class="fw8 fs13 text-navy">{work.work}</div>
                              <button class="btn-link" onClick={() => startEdit("support-work", work.work, { parent: dept })}>✎</button>
                            </div>
                            <div class="support-head-select">
                              <div class="muted fs11">หัวหน้างาน</div>
                              <select class="sel" value={orgSups[workPath] || ""} onChange={(e) => setOrgHead(workPath, e.target.value)}>
                                <option value="">— เลือกหัวหน้างาน —</option>
                                {supervisors.map((user) => <option key={user.sso} value={user.n}>{user.t}{user.n} · {user.p}</option>)}
                              </select>
                            </div>
                            <div class="support-unit-list">
                              {(work.units || []).map((unit: string) =>
                          <div key={unit} class="support-unit-row">
                                  <span>{unit}</span>
                                  <button class="btn-link" onClick={() => startEdit("support-unit", unit, { parent: dept, workName: work.work })}>✎</button>
                                </div>
                          )}
                              {(work.units || []).length === 0 && <div class="structure-empty">ยังไม่มีหน่วย</div>}
                            </div>
                            <div class="support-unit-add">
                              <input class="inp" value={newSupportUnitNames.value[workPath] || ""} onChange={(e) => setNewSupportUnitNames((current) => ({ ...current, [workPath]: e.target.value }))} placeholder="เพิ่มหน่วยใต้ งานนี้" />
                              <button class="btn btn-s btn-sm" onClick={() => addSupportUnit(dept, work.work)}>+ เพิ่มหน่วย</button>
                            </div>
                          </div>);

                  })}
                      <div class="support-work-card support-add-card">
                        <input class="inp" value={newSupportWorkNames.value[dept] || ""} onChange={(e) => setNewSupportWorkNames((current) => ({ ...current, [dept]: e.target.value }))} placeholder={`เพิ่มงานใต้${dept}`} />
                        <button class="btn btn-s btn-sm" onClick={() => addSupportWork(dept)}>+ เพิ่มงาน</button>
                      </div>
                    </div>
                  </section>
              )}
              </div>
            </div> :
          activeTab.value === "dept" ?
          <div class="structure-pane">
              <div class="structure-heading">กลุ่มงาน</div>
              <div class="structure-stack">
                <section class="structure-section">
                  <div class="structure-section-head">
                    <div class="fw7 fs14 text-navy">สายงานบริหาร</div>
                    <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "dept", type: "3", name: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มกลุ่มงาน</button>
                  </div>
                  <div class="structure-grid">
                    {adminDepts.map((item) =>
                  <div key={item} class="structure-item group">
                        <div class="flex flex-col g4 overflow-hidden">
                          <span class="fs13 fw7 text-gray-800">{item}</span>
                        </div>
                        <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("admin-dept", item)}>✎</button>
                      </div>
                  )}
                  </div>
                </section>
                <section class="structure-section">
                  <div class="structure-section-head">
                    <div class="fw7 fs14 text-navy">สายวิชาการ</div>
                    <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "dept", type: "1", name: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มกลุ่มงาน</button>
                  </div>
                  <div class="structure-grid">
                    {academicDepts.map((item) =>
                  <div key={item} class="structure-item group">
                        <div class="flex flex-col g4 overflow-hidden">
                          <span class="fs13 fw7 text-gray-800">{item}</span>
                        </div>
                        <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("academic-dept", item)}>✎</button>
                      </div>
                  )}
                  </div>
                </section>
                {supportDepts.map((item) =>
              <section key={item} class="structure-section">
                    <div class="structure-section-head">
                      <div class="fw7 fs14 text-navy">{item}</div>
                      <span class="fs11 muted">{(supportPositionGroups[item] || []).length} ตำแหน่ง</span>
                    </div>
                    <div class="structure-grid">
                      {(supportPositionGroups[item] || []).map((position) =>
                  <div key={position} class="structure-item">
                          <span class="fs12 fw6 text-gray-700">{position}</span>
                        </div>
                  )}
                      {(supportPositionGroups[item] || []).length === 0 &&
                  <div class="structure-empty">ยังไม่มีตำแหน่งในกลุ่มงานนี้</div>
                  }
                    </div>
                  </section>
              )}
              </div>
            </div> :

          activeTab.value === "pos" ?
          <div class="structure-pane">
              <div class="structure-heading">ระดับตำแหน่ง</div>
              <div class="structure-note">
                <b>สายงานบริหาร</b>
                <span>ใช้ชื่อตำแหน่งเป็นระดับตำแหน่งอัตโนมัติ จึงไม่มีรายการระดับให้เพิ่มหรือแก้ไขแยก</span>
              </div>
              <div class="structure-stack">
                {[
              { label: "สายวิชาการ", data: academicRank, type: "1", editType: "academic-rank" },
              { label: "สายสนับสนุน", data: supportRank, type: "2", editType: "support-rank" }].
              map((group) =>
              <section key={group.label} class="structure-section">
                    <div class="structure-section-head">
                      <div class="fw7 fs14 text-navy">{group.label}</div>
                      <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "rank", type: group.type, name: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มระดับ</button>
                    </div>
                    <div class="structure-grid">
                      {group.data.map((item) =>
                  <div key={item} class="structure-item group">
                          <span class="fs13 fw6 text-gray-700 truncate">{item}</span>
                          <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit(group.editType, item)}>✎</button>
                        </div>
                  )}
                      {group.data.length === 0 && <div class="structure-empty">ยังไม่มีข้อมูล</div>}
                    </div>
                  </section>
              )}
              </div>
            </div> :

          <div class="structure-pane">
              <div class="structure-heading">ประเภทสมรรถนะ</div>
              <section class="structure-section">
                <div class="structure-section-head">
                  <div class="fw7 fs14 text-navy">หมวดหมู่สมรรถนะ</div>
                  <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "comp", type: "1", name: "", fullName: "", desc: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มประเภท</button>
                </div>
                <div class="structure-grid">
                  {competencyTypeList.value.map((item) =>
                <div key={getCompetencyTypeCode(item)} class="structure-item group competency-type-item">
                      <div style={{ minWidth: 0 }}>
                        <div class="fs13 fw7 text-gray-800 truncate">{getCompetencyTypeCode(item)}</div>
                        <div class="muted fs12 truncate">{getCompetencyTypeFullName(item) || "-"}</div>
                        <div class="muted fs11 competency-type-desc">{getCompetencyTypeDesc(item) || "-"}</div>
                      </div>
                      <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("comp-type", getCompetencyTypeCode(item), { item })}>✎</button>
                    </div>
                )}
                  {competencyTypeList.value.length === 0 && <div class="structure-empty">ยังไม่มีข้อมูล</div>}
                </div>
              </section>
              <section class="structure-section">
                <div class="structure-section-head">
                  <div class="fw7 fs14 text-navy">ประเภทการเรียนรู้</div>
                  <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "learning", type: "1", name: "", desc: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มประเภทการเรียนรู้</button>
                </div>
                <div class="structure-grid">
                  {learningMethods.map((item) =>
                <div key={item.key} class="structure-item group" style={{ alignItems: "flex-start", minHeight: "72px" }}>
                      <div style={{ minWidth: 0 }}>
                        <div class="fs13 fw6 text-gray-700 truncate">{item.label}</div>
                        <div class="muted fs11" style={{ marginTop: "4px" }}>{item.desc || "-"}</div>
                      </div>
                      <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: "12px" }} onClick={() => startEdit("learning-method", item.key)}>✎</button>
                    </div>
                )}
                  {learningMethods.length === 0 && <div class="structure-empty">ยังไม่มีประเภทการเรียนรู้</div>}
                </div>
              </section>
            </div>
          }
        </div>
      </div>

      {showAddModal.value &&
      <div class="mo">
          <div class="mo-box anim-fade-in" style={{ width: "450px" }}>
            <div class="mo-h">
              <div class="fw8">{getAddModalCopy().title}</div>
              <button class="btn btn-s btn-sm" onClick={() => setShowAddModal(false)}>✕</button>
            </div>
            <div class="mo-b">
              <div class="fg">
                <label class="lbl fw8" style={{ color: "var(--navy)" }}>{getAddModalCopy().label}</label>
                <input class="inp" value={addItemData.value.name} onChange={(e) => setAddItemData({ ...addItemData.value, name: e.target.value })} placeholder="กรอกชื่อที่ต้องการ..." autoFocus />
              </div>
              {addItemData.value.category === "comp" &&
            <>
                <div class="fg">
                  <label class="lbl fw8" style={{ color: "var(--navy)" }}>ชื่อเต็มประเภทสมรรถนะ</label>
                  <input class="inp" value={addItemData.value.fullName} onChange={(e) => setAddItemData({ ...addItemData.value, fullName: e.target.value })} placeholder="เช่น Core Competency" />
                </div>
                <div class="fg">
                  <label class="lbl fw8" style={{ color: "var(--navy)" }}>รายละเอียดประเภทสมรรถนะ</label>
                  <textarea class="ta" rows={3} value={addItemData.value.desc} onChange={(e) => setAddItemData({ ...addItemData.value, desc: e.target.value })} placeholder="อธิบายความหมายและขอบเขตของประเภทสมรรถนะนี้..." />
                </div>
              </>
            }
              {addItemData.value.category === "learning" &&
            <div class="fg">
                  <label class="lbl fw8" style={{ color: "var(--navy)" }}>รายละเอียดแบบย่อ</label>
                  <textarea class="ta" rows={3} value={addItemData.value.desc} onChange={(e) => setAddItemData({ ...addItemData.value, desc: e.target.value })} placeholder="อธิบายลักษณะของประเภทการเรียนรู้นี้โดยย่อ..." />
                </div>
            }

              <div style={{ display: "flex", gap: "8px", marginTop: "24px", justifyContent: "flex-end" }}>
                <button class="btn btn-s" onClick={() => setShowAddModal(false)}>ยกเลิก</button>
                <button class="btn btn-p" onClick={saveAddItem}>เพิ่มรายการ</button>
              </div>
            </div>
          </div>
        </div>
      }

      {editingItem.value &&
      <div class="mo">
          <div class="mo-box anim-fade-in" style={{ width: "400px" }}>
            <div class="mo-h">
              <div class="fw8">แก้ไขข้อมูลรายการ</div>
              <button class="btn btn-s btn-sm" onClick={() => setEditingId(null)}>✕</button>
            </div>
            <div class="mo-b">
              <div class="fg">
                <label class="lbl">{editingItem.value.type === "comp-type" ? "รหัสประเภทสมรรถนะ" : "ชื่อปัจจุบัน"}: <span class="muted">{editingItem.value.oldName}</span></label>
                <input class="inp" value={newValue.value} onChange={(e) => setNewValue(e.target.value)} placeholder="กรอกชื่อใหม่..." autoFocus />
              </div>
              {editingItem.value.type === "comp-type" &&
            <>
                <div class="fg">
                  <label class="lbl">ชื่อเต็มประเภทสมรรถนะ</label>
                  <input class="inp" value={editFullName.value} onChange={(e) => setEditFullName(e.target.value)} placeholder="เช่น Core Competency" />
                </div>
                <div class="fg">
                  <label class="lbl">รายละเอียดประเภทสมรรถนะ</label>
                  <textarea class="ta" rows={3} value={editDesc.value} onChange={(e) => setEditDesc(e.target.value)} placeholder="อธิบายความหมายและขอบเขตของประเภทสมรรถนะนี้..." />
                </div>
              </>
            }
              <div style={{ display: "flex", gap: "8px", marginTop: "24px", justifyContent: "space-between" }}>
                <button class="btn btn-r" style={{ background: '#fee2e2', color: '#ef4444', border: '1px solid #fecaca' }} onClick={deleteItem}>🗑️ ลบรายการนี้</button>
                <div class="flex g8">
                  <button class="btn btn-s" onClick={() => setEditingId(null)}>ยกเลิก</button>
                  <button class="btn btn-p" onClick={saveEdit}>💾 บันทึก</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      }

      <style>{`
        .structure-tabs { display: flex; gap: 4px; padding: 4px; width: fit-content; max-width: 100%; overflow-x: auto; border: 1px solid var(--border); border-radius: var(--r); background: #fff; }
        .structure-tab { flex: 0 0 auto; border: 0; border-radius: 6px; background: transparent; color: var(--text2); cursor: pointer; font-size: 13px; font-weight: 600; padding: 8px 12px; }
        .structure-tab.active { background: var(--blue); color: #fff; }
        .structure-shell { min-height: 400px; overflow: hidden; border: 1px solid var(--border); border-radius: var(--r); background: #fff; }
        .structure-pane { padding: 20px; }
        .structure-heading { margin-bottom: 14px; color: var(--text); font-size: 15px; font-weight: 800; }
        .structure-note { display: grid; gap: 4px; margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--blue-md); border-left: 4px solid var(--blue); border-radius: 8px; background: var(--blue-lt); color: var(--text2); font-size: 12px; line-height: 1.55; }
        .structure-note b { color: var(--blue); font-size: 13px; }
        .structure-stack { display: grid; gap: 0; }
        .structure-section { padding: 16px 0; border-top: 1px solid var(--border); }
        .structure-section:first-child { padding-top: 0; border-top: 0; }
        .structure-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .structure-grid { display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); }
        .structure-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 40px; padding: 9px 11px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); overflow: hidden; }
        .competency-type-item { align-items: flex-start; min-height: 86px; }
        .competency-type-desc { display: -webkit-box; margin-top: 4px; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .support-chain-item { align-items: center; grid-column: span 2; }
        .support-chain-item .sel { max-width: 280px; }
        .support-unit-add { display: flex; gap: 8px; width: min(100%, 360px); }
        .support-work-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 270px), 1fr)); }
        .support-work-card { display: grid; align-content: start; gap: 11px; min-height: 260px; padding: 14px; border: 1px solid #dbe5f1; border-radius: 8px; background: #fff; }
        .support-work-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
        .support-unit-list { display: grid; gap: 9px; }
        .support-unit-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 48px; padding: 10px 12px; border: 1px solid #dbe5f1; border-radius: 8px; background: #f8fafc; color: var(--text); font-size: 13px; font-weight: 650; }
        .support-head-select { display: grid; gap: 5px; }
        .support-add-card { min-height: 150px; border-style: dashed; background: #fbfdff; }
        .structure-empty { grid-column: 1 / -1; padding: 14px; border: 1px dashed var(--border); border-radius: 7px; color: var(--text3); font-size: 13px; text-align: center; }
        .support-columns { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); }
        .support-group-more { justify-self: start; margin-top: 12px; border: 1px solid var(--border); border-radius: 7px; background: #fff; color: var(--blue); cursor: pointer; font-size: 12px; font-weight: 700; padding: 8px 10px; }
        .support-group-more:hover { border-color: var(--blue); background: var(--blue-lt); }
        .support-column { display: grid; align-content: start; gap: 9px; min-height: 170px; padding: 12px; border: 1px solid var(--border); border-radius: 7px; background: #fff; }
        .support-column-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; min-height: 32px; padding-bottom: 9px; border-bottom: 1px solid var(--border); }
        .support-position-list { display: grid; align-content: start; gap: 7px; }
        .support-more { justify-self: start; border: 0; background: transparent; color: var(--blue); cursor: pointer; font-size: 12px; font-weight: 700; padding: 0; }
        .support-add { min-height: 34px; border: 1px dashed var(--border); border-radius: 7px; background: transparent; color: var(--blue); cursor: pointer; font-size: 12px; font-weight: 700; }
        .support-add:hover { border-color: var(--blue); background: var(--blue-lt); }
        .tbl-mini { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tbl-mini td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: var(--text2); }
        .tbl-mini tr:hover td { background: #f8fafc; color: var(--blue); }
        .btn-link { background: none; border: none; color: var(--blue); cursor: pointer; font-size: 14px; opacity: 0.5; transition: 0.2s; }
        .btn-link:hover { opacity: 1; transform: scale(1.2); }
        .inp-clean { border: none; background: transparent; outline: none; }
        @media (max-width: 640px) {
          .structure-pane { padding: 16px; }
          .structure-tabs { width: 100%; }
          .structure-section-head { align-items: flex-start; flex-direction: column; }
        }
      `}</style>
    </>;} });



export default AdminOrgStructure;
</script>
