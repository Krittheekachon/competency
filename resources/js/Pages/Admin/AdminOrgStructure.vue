<script lang="tsx">
// @ts-nocheck
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

interface AdminOrgStructureProps {academicDepts: string[];setAcademicDepts: any;supportDepts: string[];supportPositionGroups: Record<string, string[]>;setSupportPositionGroups: any;adminDepts: string[];setAdminDepts: any;supportOrg: any;setSupportOrg: any;users: any[];orgSups: Record<string, string>;setOrgSups: any;academicPos: string[];setAcademicPos: any;supportPos: string[];setSupportPos: any;adminPos: string[];setAdminPos: any;jobFamiliesByWorkline: Record<string, Record<string, string[]>>;setJobFamiliesByWorkline: any;levelsByWorkline: Record<string, string[]>;setLevelsByWorkline: any;
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

const AdminOrgStructure = defineComponent({ name: "AdminOrgStructure", props: ["academicDepts", "setAcademicDepts", "supportDepts", "supportPositionGroups", "setSupportPositionGroups", "adminDepts", "setAdminDepts", "supportOrg", "setSupportOrg", "users", "orgSups", "setOrgSups", "academicPos", "setAcademicPos", "supportPos", "setSupportPos", "adminPos", "setAdminPos", "jobFamiliesByWorkline", "setJobFamiliesByWorkline", "levelsByWorkline", "setLevelsByWorkline", "academicRank", "setAcademicRank", "supportRank", "setSupportRank", "worklines", "setWorklines", "competencyTypes", "setCompetencyTypes", "learningMethods", "setLearningMethods", "keepAdminPage"], setup(__props) {const {
setAcademicDepts,
setSupportPositionGroups,
setAdminDepts,
supportOrg: initialSupportOrg, setSupportOrg,
users, orgSups, setOrgSups,
setAcademicPos,
supportPos, setSupportPos,
setAdminPos,
setJobFamiliesByWorkline,
setLevelsByWorkline,
setAcademicRank,
setSupportRank,
setWorklines,
setCompetencyTypes,
learningMethods, setLearningMethods,
keepAdminPage
} = __props as any;
let academicDepts = [...((__props as any).academicDepts || [])];
let supportDepts = [...((__props as any).supportDepts || [])];
let supportPositionGroups = { ...((( __props as any).supportPositionGroups) || {}) };
const supportOrg = ref<Record<string, any[]>>({ ...((initialSupportOrg) || {}) });
let adminDepts = [...((__props as any).adminDepts || [])];
let academicPos = [...((__props as any).academicPos || [])];
let adminPos = [...((__props as any).adminPos || [])];
let jobFamiliesByWorkline = { ...((( __props as any).jobFamiliesByWorkline) || {}) };
let levelsByWorkline = { ...((( __props as any).levelsByWorkline) || {}) };
let worklines = [...((__props as any).worklines || [])];
const POSITION_PREVIEW_LIMIT = 4;
const SUPPORT_GROUP_PREVIEW_LIMIT = 4;
const STRUCTURE_TAB_STORAGE_KEY = "admin-org-structure-active-tab";
const STRUCTURE_TABS = ["workline", "support-chain", "pos", "comp"];
const getInitialStructureTab = () => {
if (typeof window === "undefined") return "workline";
const savedTab = window.sessionStorage.getItem(STRUCTURE_TAB_STORAGE_KEY);
return STRUCTURE_TABS.includes(savedTab || "") ? savedTab : "workline";
};
const competencyTypeList = ref<any[]>([...((__props as any).competencyTypes || [])]);
const [activeTab, setRawActiveTab] = useState(getInitialStructureTab);
const setActiveTab = (next: any) => {
setRawActiveTab((current: string) => {
const nextTab = typeof next === "function" ? next(current) : next;
const safeTab = STRUCTURE_TABS.includes(nextTab) ? nextTab : "workline";
if (typeof window !== "undefined") {
window.sessionStorage.setItem(STRUCTURE_TAB_STORAGE_KEY, safeTab);
}
return safeTab;
});
};
const [editingItem, setEditingId] = useState<any>(null);
const [newValue, setNewValue] = useState("");
const [editFullName, setEditFullName] = useState("");
const [editDesc, setEditDesc] = useState("");
const [newSupportDeptName, setNewSupportDeptName] = useState("");
const [showSupportDeptModal, setShowSupportDeptModal] = useState(false);
const [newSupportWorkNames, setNewSupportWorkNames] = useState<Record<string, string>>({});
const [newSupportUnitNames, setNewSupportUnitNames] = useState<Record<string, string>>({});
const [showAddModal, setShowAddModal] = useState(false);
const [isSavingAddItem, setIsSavingAddItem] = useState(false);
const [expandedSupportGroups, setExpandedSupportGroups] = useState<Record<string, boolean>>({});
const [showAllSupportGroups, setShowAllSupportGroups] = useState(false);
const learningMethodList = ref<any[]>([...((__props as any).learningMethods || [])]);
const [addItemData, setAddItemData] = useState({
category: "workline",
type: "1",
name: "",
fullName: "",
desc: "",
worklineName: "",
parent: "",
grandparent: ""
});
const [structureVersion, setStructureVersion] = useState(0);
const addNameInput = ref<HTMLInputElement | null>(null);
const dean = users.find((user) => ["dean", "manager"].includes(user.r))?.n || "";
const deptManagers = users.filter((user) => user.r === "supervisor");
const supervisors = users.filter((user) => ["dept_head", "head", "manager_dept"].includes(user.r));
const getCompetencyTypeCode = (item: any) => typeof item === "string" ? item : item?.code || item?.name || "";
const getCompetencyTypeFullName = (item: any) => typeof item === "string" ? "" : item?.fullName || item?.label || "";
const getCompetencyTypeDesc = (item: any) => typeof item === "string" ? "" : item?.desc || "";
const getCompetencyTypeId = (item: any) => typeof item === "string" ? null : item?.id || null;
const competencyTypeFromPayload = (payload: any, id?: any) => ({
id: id || null,
code: payload.code,
fullName: payload.full_name,
desc: payload.description
});
useEffect(() => {
competencyTypeList.value = [...((( __props as any).competencyTypes) || [])];
}, [(__props as any).competencyTypes]);
useEffect(() => {
learningMethodList.value = [...((( __props as any).learningMethods) || [])];
}, [(__props as any).learningMethods]);
useEffect(() => {
supportOrg.value = { ...((( __props as any).supportOrg) || {}) };
}, [(__props as any).supportOrg]);
const applyCompetencyTypes = (next: any[]) => {
competencyTypeList.value = [...next];
setCompetencyTypes(next);
setStructureVersion((current: number) => current + 1);
};
const upsertCompetencyType = (item: any, oldCode?: string) => {
const itemCode = getCompetencyTypeCode(item);
const itemId = getCompetencyTypeId(item);
const next = [
item,
...competencyTypeList.value.filter((current) => {
const currentId = getCompetencyTypeId(current);
const currentCode = getCompetencyTypeCode(current);
if (itemId && currentId === itemId) return false;
return currentCode !== itemCode && currentCode !== oldCode;
})
].sort((a, b) => getCompetencyTypeCode(a).localeCompare(getCompetencyTypeCode(b)));
applyCompetencyTypes(next);
};
const removeCompetencyType = (item: any, oldCode: string) => {
const itemId = getCompetencyTypeId(item);
applyCompetencyTypes(competencyTypeList.value.filter((current) => {
if (itemId && getCompetencyTypeId(current) === itemId) return false;
return getCompetencyTypeCode(current) !== oldCode;
}));
};
const syncedCompetencyTypeOr = (code: string, fallback: any) =>
competencyTypeList.value.find((item) => getCompetencyTypeCode(item) === code) || fallback;
const syncCompetencyTypesFromPage = (responsePage: any) => {
if (Array.isArray(responsePage?.props?.competencyTypes)) {
applyCompetencyTypes(responsePage.props.competencyTypes);
}
};
const applyLearningMethods = (next: any[]) => {
learningMethodList.value = [...next];
setLearningMethods(next);
setStructureVersion((current: number) => current + 1);
};
const syncLearningMethodsFromPage = (responsePage: any) => {
if (Array.isArray(responsePage.props.learningMethods)) {
applyLearningMethods(responsePage.props.learningMethods);
}
};
const syncStructureFromPage = (responsePage: any) => {
const props = responsePage?.props || {};
let didSync = false;

if (Array.isArray(props.worklines)) {
worklines = [...props.worklines];
setWorklines([...props.worklines]);
didSync = true;
}

if (props.jobFamiliesByWorkline && typeof props.jobFamiliesByWorkline === "object") {
const nextGroups = { ...props.jobFamiliesByWorkline };
jobFamiliesByWorkline = nextGroups;
academicDepts = Object.keys(nextGroups["สายวิชาการ"] || nextGroups["วิชาการ"] || {});
adminDepts = Object.keys(nextGroups["สายบริหาร"] || nextGroups["สายงานบริหาร"] || {});
supportPositionGroups = nextGroups["สายสนับสนุน"] || nextGroups["สนับสนุน"] || {};
supportDepts = Object.keys(supportPositionGroups);
setJobFamiliesByWorkline(nextGroups);
setAcademicDepts(academicDepts);
setAdminDepts(adminDepts);
setSupportPositionGroups(supportPositionGroups);
didSync = true;
}

if (props.levelsByWorkline && typeof props.levelsByWorkline === "object") {
const nextLevels = { ...props.levelsByWorkline };
levelsByWorkline = nextLevels;
setLevelsByWorkline(nextLevels);
setAcademicRank(nextLevels["สายวิชาการ"] || nextLevels["วิชาการ"] || []);
setSupportRank(nextLevels["สายสนับสนุน"] || nextLevels["สนับสนุน"] || []);
didSync = true;
}

if (props.supportOrg && typeof props.supportOrg === "object") {
supportOrg.value = { ...props.supportOrg };
setSupportOrg({ ...props.supportOrg });
didSync = true;
}

if (didSync) {
setStructureVersion((current: number) => current + 1);
}
};
const showPersistError = (errors: any) => {
const firstError = Object.values(errors || {})[0];
alert(firstError || "ไม่สามารถบันทึกข้อมูลประเภทสมรรถนะได้");
};
const keepCurrentAdminPage = () => {
if (typeof keepAdminPage === "function") {
keepAdminPage("admin-org-structure");
}
};
const persistOptions = {
preserveScroll: true,
preserveState: true,
onStart: keepCurrentAdminPage,
onFinish: keepCurrentAdminPage,
onSuccess: syncCompetencyTypesFromPage,
onError: (errors: any) => {
keepCurrentAdminPage();
showPersistError(errors);
}
};
const postStructure = (routeName: string, payload: any, onSuccess: () => void) => {
router.post(route(routeName), { ...payload, admin_page: "admin-org-structure" }, {
...persistOptions,
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
onSuccess();
syncStructureFromPage(responsePage);
},
onError: (errors: any) => {
keepCurrentAdminPage();
setIsSavingAddItem(false);
showPersistError(errors);
}
});
};
const putStructure = (routeName: string, payload: any, onSuccess: () => void) => {
router.put(route(routeName), { ...payload, admin_page: "admin-org-structure" }, {
...persistOptions,
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
onSuccess();
syncStructureFromPage(responsePage);
}
});
};
const deleteStructure = (routeName: string, payload: any, onSuccess: () => void) => {
router.delete(route(routeName), {
...persistOptions,
data: { ...payload, admin_page: "admin-org-structure" },
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
onSuccess();
syncStructureFromPage(responsePage);
}
});
};
const worklineNameFromType = (type: string) => {
if (type === "1") return "สายวิชาการ";
if (type === "2") return "สายสนับสนุน";
return "สายบริหาร";
};
const levelItemsForWorkline = (worklineName: string) => {
const directLevels = levelsByWorkline?.[worklineName] || [];
return Array.from(new Set(directLevels));
};
const fallbackLevelsForWorkline = (worklineName: string) => {
const groupMap = groupMapForWorkline(worklineName);
return Array.from(new Set(Object.entries(groupMap).flatMap(([group, positions]) => positions.length ? positions : [group])));
};
const setLevelItemsForWorkline = (worklineName: string, nextLevels: string[]) => {
const uniqueLevels = Array.from(new Set(nextLevels));
levelsByWorkline = {
...levelsByWorkline,
[worklineName]: uniqueLevels
};
setLevelsByWorkline({
...levelsByWorkline,
[worklineName]: uniqueLevels
});

if (worklineName === "สายวิชาการ") setAcademicRank(uniqueLevels);
if (worklineName === "สายสนับสนุน") setSupportRank(uniqueLevels);
setStructureVersion((current: number) => current + 1);
};
const groupMapForWorkline = (worklineName: string): Record<string, string[]> => {
const groups = jobFamiliesByWorkline?.[worklineName] || {};
if (Array.isArray(groups)) {
return groups.reduce((carry: Record<string, string[]>, groupName: string) => {
carry[groupName] = [];
return carry;
}, {});
}
return groups;
};
const setGroupMapForWorkline = (worklineName: string, groups: Record<string, string[]>) => {
jobFamiliesByWorkline = {
...jobFamiliesByWorkline,
[worklineName]: groups
};
setJobFamiliesByWorkline(jobFamiliesByWorkline);

if (worklineName === "สายวิชาการ") {
academicDepts = Object.keys(groups);
setAcademicDepts(academicDepts);
}
if (worklineName === "สายบริหาร" || worklineName === "สายงานบริหาร") {
adminDepts = Object.keys(groups);
setAdminDepts(adminDepts);
}
if (worklineName === "สายสนับสนุน") {
supportPositionGroups = groups;
supportDepts = Object.keys(groups);
setSupportPositionGroups(supportPositionGroups);
}
setStructureVersion((current: number) => current + 1);
};

const setOrgHead = (path: string, value: string) => {
setOrgSups((current) => ({ ...current, [path]: value }));
};

const addSupportDept = () => {
const name = newSupportDeptName.value.trim();
if (!name || supportOrg.value[name]) return false;
setActiveTab("support-chain");

postStructure(
"admin.structure.support-depts.store",
{ name },
() => {
setActiveTab("support-chain");
const nextSupportOrg = { ...supportOrg.value, [name]: [] };
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
setOrgSups((current) => ({
...current,
[name]: deptManagers[0]?.n || dean,
}));
setNewSupportDeptName("");
setShowSupportDeptModal(false);
}
);
return true;
};

const submitSupportDept = () => {
addSupportDept();
};

const addSupportWork = (dept: string) => {
const name = (newSupportWorkNames.value[dept] || "").trim();
if (!name) return;
const works = supportOrg.value[dept] || [];
if (works.some((item: any) => item.work === name)) return;
setActiveTab("support-chain");

postStructure(
"admin.structure.support-works.store",
{ dept_name: dept, work_name: name },
() => {
setActiveTab("support-chain");
const nextSupportOrg = {
...supportOrg.value,
[dept]: [...works, { work: name, units: [] }],
};
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
setOrgSups((current) => ({
...current,
[[dept, name].join(" > ")]: supervisors[0]?.n || "",
}));
setNewSupportWorkNames((current) => ({ ...current, [dept]: "" }));
}
);
};

const addSupportUnit = (dept: string, workName: string) => {
const workPath = [dept, workName].join(" > ");
const name = (newSupportUnitNames.value[workPath] || "").trim();
if (!name) return;
const works = supportOrg.value[dept] || [];
setActiveTab("support-chain");

postStructure(
"admin.structure.support-units.store",
{ dept_name: dept, work_name: workName, unit_name: name },
() => {
setActiveTab("support-chain");
const nextSupportOrg = {
...supportOrg.value,
[dept]: works.map((item: any) =>
item.work === workName && !(item.units || []).includes(name)
? { ...item, units: [...(item.units || []), name] }
: item
),
};
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
setNewSupportUnitNames((current) => ({ ...current, [workPath]: "" }));
}
);
};

const runOnEnter = (event: any, action: () => void) => {
if (event.key !== "Enter" || event.isComposing) return;
if (event.target?.tagName?.toLowerCase() === "textarea") return;
event.preventDefault();
action();
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
case "academic-dept":
putStructure("admin.structure.job-families.update", { workline_name: "สายวิชาการ", old_name: oldName, name: newValue.value }, () => {
setAcademicDepts(academicDepts.map((v) => v === oldName ? newValue.value : v));
setEditingId(null);
});
return;
case "admin-dept":
putStructure("admin.structure.job-families.update", { workline_name: "สายบริหาร", old_name: oldName, name: newValue.value }, () => {
setAdminDepts(adminDepts.map((v) => v === oldName ? newValue.value : v));
setEditingId(null);
});
return;
case "custom-dept":
putStructure("admin.structure.job-families.update", { workline_name: parent, old_name: oldName, name: newValue.value }, () => {
const groups = groupMapForWorkline(parent);
const nextGroups = { ...groups, [newValue.value]: groups[oldName] || [] };
delete nextGroups[oldName];
setGroupMapForWorkline(parent, nextGroups);
setEditingId(null);
});
return;
case "support-dept":{
const nextSupportPositionGroups = { ...supportPositionGroups };
nextSupportPositionGroups[newValue.value] = nextSupportPositionGroups[oldName] || [];
delete nextSupportPositionGroups[oldName];
putStructure("admin.structure.job-families.update", { workline_name: "สายสนับสนุน", old_name: oldName, name: newValue.value }, () => {
setSupportPositionGroups(nextSupportPositionGroups);
setEditingId(null);
});
return;
}
case "support-group-pos":
case "custom-group-pos":
putStructure("admin.structure.positions.update", { workline_name: workName, job_family_name: parent, old_name: oldName, name: newValue.value }, () => {
if (type === "support-group-pos") {
setSupportPositionGroups({
...supportPositionGroups,
[parent]: (supportPositionGroups[parent] || []).map((v) => v === oldName ? newValue.value : v)
});
}
if (type === "custom-group-pos") {
const groups = groupMapForWorkline(workName);
setGroupMapForWorkline(workName, {
...groups,
[parent]: (groups[parent] || []).map((v) => v === oldName ? newValue.value : v)
});
}
setEditingId(null);
});
return;
case "support-work":{
const nextSupportOrg = { ...supportOrg.value };
if (parent && nextSupportOrg[parent]) {
nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === oldName ? { ...w, work: newValue.value } : w);
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
}
break;
}
case "support-unit":{
const nextSupportOrg = { ...supportOrg.value };
if (parent && workName && nextSupportOrg[parent]) {
nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === workName ? { ...w, units: w.units.map((u: string) => u === oldName ? newValue.value : u) } : w);
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
}
break;
}
case "academic-pos":
putStructure("admin.structure.job-families.update", { workline_name: "สายวิชาการ", old_name: oldName, name: newValue.value }, () => {
setAcademicPos(academicPos.map((v) => v === oldName ? newValue.value : v));
setEditingId(null);
});
return;
case "support-pos":setSupportPos(supportPos.map((v) => v === oldName ? newValue.value : v));break;
case "admin-pos":
putStructure("admin.structure.job-families.update", { workline_name: "สายบริหาร", old_name: oldName, name: newValue.value }, () => {
setAdminPos(adminPos.map((v) => v === oldName ? newValue.value : v));
setEditingId(null);
});
return;
case "rank":
case "academic-rank":
case "support-rank":{
const targetWorklineName = parent || (type === "support-rank" ? "สายสนับสนุน" : "สายวิชาการ");
putStructure("admin.structure.levels.update", { workline_name: targetWorklineName, old_name: oldName, name: newValue.value }, () => {
setLevelItemsForWorkline(targetWorklineName, levelItemsForWorkline(targetWorklineName).map((v) => v === oldName ? newValue.value : v));
setEditingId(null);
});
return;
}
case "workline":
putStructure("admin.structure.worklines.update", { old_name: oldName, name: newValue.value }, () => {
setWorklines(worklines.map((v) => v === oldName ? newValue.value : v));
setEditingId(null);
});
return;
case "comp-type":
if (!editFullName.value.trim() || !editDesc.value.trim()) return;
{
setActiveTab("comp");
const payload = {
code: newValue.value.trim(),
full_name: editFullName.value.trim(),
description: editDesc.value.trim()
};
const id = getCompetencyTypeId(editingItem.value.item);
if (id) {
router.put(route("admin.competency-types.update", id), { ...payload, admin_page: "admin-org-structure" }, {
...persistOptions,
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
setActiveTab("comp");
syncCompetencyTypesFromPage(responsePage);
upsertCompetencyType(syncedCompetencyTypeOr(payload.code, competencyTypeFromPayload(payload, id)), oldName);
setEditingId(null);
}
});
} else {
router.post(route("admin.competency-types.store"), { ...payload, admin_page: "admin-org-structure" }, {
...persistOptions,
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
setActiveTab("comp");
syncCompetencyTypesFromPage(responsePage);
upsertCompetencyType(syncedCompetencyTypeOr(payload.code, competencyTypeFromPayload(payload)), oldName);
setEditingId(null);
}
});
}
return;
}
case "learning-method":
putStructure("admin.structure.learning-methods.update", {
old_key: oldName,
key: oldName,
label: newValue.value,
description: editingItem.value.item?.desc || ""
}, () => {
applyLearningMethods(learningMethodList.value.map((item) => item.key === oldName ? { ...item, label: newValue.value } : item));
setEditingId(null);
});
return;
}
setEditingId(null);
};

const deleteItem = () => {
const { type, oldName, parent, workName } = editingItem.value;

switch (type) {
case "academic-dept":
deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายวิชาการ", name: oldName }, () => {
setAcademicDepts(academicDepts.filter((v) => v !== oldName));
setEditingId(null);
});
return;
case "admin-dept":
deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายบริหาร", name: oldName }, () => {
setAdminDepts(adminDepts.filter((v) => v !== oldName));
setEditingId(null);
});
return;
case "custom-dept":
deleteStructure("admin.structure.job-families.destroy", { workline_name: parent, name: oldName }, () => {
const nextGroups = { ...groupMapForWorkline(parent) };
delete nextGroups[oldName];
setGroupMapForWorkline(parent, nextGroups);
setEditingId(null);
});
return;
case "support-dept":{
const nextSupportPositionGroups = { ...supportPositionGroups };
delete nextSupportPositionGroups[oldName];
deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายสนับสนุน", name: oldName }, () => {
setSupportPositionGroups(nextSupportPositionGroups);
setEditingId(null);
});
return;
}
case "support-group-pos":
case "custom-group-pos":
deleteStructure("admin.structure.positions.destroy", { workline_name: workName, job_family_name: parent, name: oldName }, () => {
if (type === "support-group-pos") {
setSupportPositionGroups({
...supportPositionGroups,
[parent]: (supportPositionGroups[parent] || []).filter((v) => v !== oldName)
});
}
if (type === "custom-group-pos") {
const groups = groupMapForWorkline(workName);
setGroupMapForWorkline(workName, {
...groups,
[parent]: (groups[parent] || []).filter((v) => v !== oldName)
});
}
setEditingId(null);
});
return;
case "support-work":{
const nextSupportOrg = { ...supportOrg.value };
if (parent && nextSupportOrg[parent]) {
nextSupportOrg[parent] = nextSupportOrg[parent].filter((w: any) => w.work !== oldName);
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
}
break;
}
case "support-unit":{
const nextSupportOrg = { ...supportOrg.value };
if (parent && workName && nextSupportOrg[parent]) {
nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === workName ? { ...w, units: w.units.filter((u: string) => u !== oldName) } : w);
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
}
break;
}
case "academic-pos":
deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายวิชาการ", name: oldName }, () => {
setAcademicPos(academicPos.filter((v) => v !== oldName));
setEditingId(null);
});
return;
case "support-pos":setSupportPos(supportPos.filter((v) => v !== oldName));break;
case "admin-pos":
deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายบริหาร", name: oldName }, () => {
setAdminPos(adminPos.filter((v) => v !== oldName));
setEditingId(null);
});
return;
case "rank":
case "academic-rank":
case "support-rank":{
const targetWorklineName = parent || (type === "support-rank" ? "สายสนับสนุน" : "สายวิชาการ");
deleteStructure("admin.structure.levels.destroy", { workline_name: targetWorklineName, name: oldName }, () => {
setLevelItemsForWorkline(targetWorklineName, levelItemsForWorkline(targetWorklineName).filter((v) => v !== oldName));
setEditingId(null);
});
return;
}
case "workline":
deleteStructure("admin.structure.worklines.destroy", { name: oldName }, () => {
setWorklines(worklines.filter((v) => v !== oldName));
setEditingId(null);
});
return;
case "comp-type":{
const id = getCompetencyTypeId(editingItem.value.item);
if (id) {
setActiveTab("comp");
router.delete(route("admin.competency-types.destroy", id), {
...persistOptions,
data: { admin_page: "admin-org-structure" },
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
setActiveTab("comp");
syncCompetencyTypesFromPage(responsePage);
removeCompetencyType(editingItem.value.item, oldName);
setEditingId(null);
}
});
} else {
applyCompetencyTypes(competencyTypeList.value.filter((v) => getCompetencyTypeCode(v) !== oldName));
setEditingId(null);
}
return;
}
case "learning-method":
deleteStructure("admin.structure.learning-methods.destroy", { key: oldName }, () => {
applyLearningMethods(learningMethodList.value.filter((item) => item.key !== oldName));
setEditingId(null);
});
return;
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
const typeLabel = addItemData.value.worklineName ||
(addItemData.value.type === "1" ?
"สายวิชาการ" :
addItemData.value.type === "2" ?
"สายสนับสนุน" :
"สายบริหาร");

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

const clearAddNameAndFocus = () => {
setAddItemData({ ...addItemData.value, name: "" });
requestAnimationFrame(() => addNameInput.value?.focus());
};

const saveAddItem = () => {
if (isSavingAddItem.value) return;

const { category, type, name, fullName, desc, worklineName, parent, grandparent } = addItemData.value;
const trimmedName = name.trim();
if (trimmedName) {
if (category === "pos" && type === "2" && !parent) return;
if (category === "dept") {
const targetWorklineName = worklineName || worklineNameFromType(type);
if (Object.keys(groupMapForWorkline(targetWorklineName)).includes(trimmedName)) {
alert(`มีกลุ่มงาน "${trimmedName}" ใน${targetWorklineName}แล้ว`);
return;
}
setIsSavingAddItem(true);
postStructure("admin.structure.job-families.store", { workline_name: targetWorklineName, name: trimmedName }, () => {
setGroupMapForWorkline(targetWorklineName, {
...groupMapForWorkline(targetWorklineName),
[trimmedName]: []
});
setIsSavingAddItem(false);
clearAddNameAndFocus();
});
return;
} else if (category === "work") {
const nextSupportOrg = { ...supportOrg.value };
if (parent && nextSupportOrg[parent]) {
nextSupportOrg[parent] = [...nextSupportOrg[parent], { work: trimmedName, units: [] }];
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
}
} else if (category === "unit") {
const nextSupportOrg = { ...supportOrg.value };
if (grandparent && parent && nextSupportOrg[grandparent]) {
nextSupportOrg[grandparent] = nextSupportOrg[grandparent].map((w: any) => w.work === parent ? { ...w, units: [...w.units, trimmedName] } : w);
supportOrg.value = nextSupportOrg;
setSupportOrg(nextSupportOrg);
}
} else if (category === "pos") {
if (parent) {
const targetWorklineName = worklineName || worklineNameFromType(type);
if ((groupMapForWorkline(targetWorklineName)[parent] || []).includes(trimmedName)) {
alert(`มีตำแหน่ง "${trimmedName}" ในกลุ่มงาน ${parent} แล้ว`);
return;
}
setIsSavingAddItem(true);
postStructure("admin.structure.positions.store", { workline_name: targetWorklineName, job_family_name: parent, name: trimmedName }, () => {
const groups = groupMapForWorkline(targetWorklineName);
setGroupMapForWorkline(targetWorklineName, {
...groups,
[parent]: [...(groups[parent] || []), trimmedName]
});
setIsSavingAddItem(false);
clearAddNameAndFocus();
});
return;
}
} else if (category === "rank") {
const targetWorklineName = worklineName || worklineNameFromType(type);
if (levelItemsForWorkline(targetWorklineName).includes(trimmedName)) {
alert(`มีระดับตำแหน่ง "${trimmedName}" ใน${targetWorklineName}แล้ว`);
return;
}
setIsSavingAddItem(true);
postStructure("admin.structure.levels.store", { workline_name: targetWorklineName, name: trimmedName }, () => {
setLevelItemsForWorkline(targetWorklineName, [...levelItemsForWorkline(targetWorklineName), trimmedName]);
setIsSavingAddItem(false);
clearAddNameAndFocus();
});
return;
} else if (category === "workline") {
if (worklines.includes(trimmedName)) {
alert(`มีสายงาน "${trimmedName}" แล้ว`);
return;
}
setIsSavingAddItem(true);
postStructure("admin.structure.worklines.store", { name: trimmedName }, () => {
setWorklines([trimmedName, ...worklines]);
setIsSavingAddItem(false);
setShowAddModal(false);
});
return;
} else if (category === "comp") {
if (!fullName.trim() || !desc.trim()) return;
setActiveTab("comp");
setIsSavingAddItem(true);
const payload = {
code: trimmedName,
full_name: fullName.trim(),
description: desc.trim()
};
router.post(route("admin.competency-types.store"), { ...payload, admin_page: "admin-org-structure" }, {
...persistOptions,
onSuccess: (responsePage: any) => {
keepCurrentAdminPage();
setActiveTab("comp");
syncCompetencyTypesFromPage(responsePage);
upsertCompetencyType(syncedCompetencyTypeOr(payload.code, competencyTypeFromPayload(payload)));
setIsSavingAddItem(false);
setShowAddModal(false);
},
onError: (errors: any) => {
keepCurrentAdminPage();
setActiveTab("comp");
setIsSavingAddItem(false);
showPersistError(errors);
}
});
return;
} else if (category === "learning") {
const baseKey = trimmedName.
toLowerCase().
replace(/[^a-z0-9]+/g, "-").
replace(/^-+|-+$/g, "") || `learning-${learningMethods.length + 1}`;
let uniqueKey = baseKey;
let suffix = 2;
while (learningMethodList.value.some((item) => item.key === uniqueKey)) {
uniqueKey = `${baseKey}-${suffix}`;
suffix += 1;
}
postStructure("admin.structure.learning-methods.store", {
key: uniqueKey,
label: trimmedName,
description: desc.trim() || ""
}, () => {
syncLearningMethodsFromPage({ props: { learningMethods: [
...learningMethodList.value,
{
key: uniqueKey,
label: trimmedName,
desc: desc.trim() || ""
}
] } });
setShowAddModal(false);
});
return;
}
clearAddNameAndFocus();
}
};

return () =><><div class="flex ic jb mb20"><div><div class="sec-t">จัดการโครงสร้างองค์กรและสมรรถนะ</div><div class="sec-s">กลุ่มงาน ฝ่าย/งาน ระดับตำแหน่ง และประเภทสมรรถนะ</div></div>
{activeTab.value === "workline" &&
 <button class="btn btn-p" onClick={openAddItem}>
+ เพิ่มสายงาน
 </button>
}
{activeTab.value === "support-chain" &&
 <button class="btn btn-p support-add-dept-btn" type="button" onClick={() => setShowSupportDeptModal(true)}>
+ เพิ่มฝ่าย
 </button>
}
 </div><div class="structure-tabs mb20"><button type="button" class={`structure-tab ${activeTab.value === "workline" ? "active" : ""}`} onClick={() => setActiveTab("workline")}>กลุ่มงาน</button><button type="button" class={`structure-tab ${activeTab.value === "support-chain" ? "active" : ""}`} onClick={() => setActiveTab("support-chain")}>ฝ่าย/งาน</button><button type="button" class={`structure-tab ${activeTab.value === "pos" ? "active" : ""}`} onClick={() => setActiveTab("pos")}>ระดับตำแหน่ง</button><button type="button" class={`structure-tab ${activeTab.value === "comp" ? "active" : ""}`} onClick={() => setActiveTab("comp")}>ประเภทสมรรถนะ</button></div><div class="anim-fade-in" data-structure-version={structureVersion.value}><div class={`structure-shell ${activeTab.value === "workline" ? "workline-shell" : ""} ${activeTab.value === "pos" ? "level-shell" : ""} ${activeTab.value === "comp" ? "comp-shell" : ""}`}>
{activeTab.value === "workline" ?
 <div class="structure-pane"><div class="structure-heading">กลุ่มงานและตำแหน่ง</div><div class="structure-note"><b>กำหนดกลุ่มงานหลักตามสายงาน</b><span>เพิ่มกลุ่มงานและตำแหน่งที่ใช้ในระบบ เพื่อให้หน้าเพิ่มผู้ใช้และการประเมินอ้างอิงโครงสร้างเดียวกัน</span></div><div class="structure-stack workline-stack">
{worklines.map((wl) => {
const groupMap = groupMapForWorkline(wl);
const groupNames = Object.keys(groupMap);
return (
 <section key={wl} class="structure-section workline-section"><div class="structure-section-head"><div class="fw7 fs14 text-navy">{wl}</div><div class="flex g8"><button
class="btn btn-s btn-sm"
onClick={() => {
setAddItemData({
category: "dept",
type: "",
name: "",
worklineName: wl,
parent: "",
grandparent: ""
});
setShowAddModal(true);
}}>
 
+ เพิ่มกลุ่มงาน
 </button><button class="btn btn-s btn-sm" onClick={() => startEdit("workline", wl)}>แก้ไข</button></div></div><div class="support-columns">
{(showAllSupportGroups.value ? groupNames : groupNames.slice(0, SUPPORT_GROUP_PREVIEW_LIMIT)).map((group) => {
const positions = groupMap[group] || [];
const visiblePositions = expandedSupportGroups.value[`${wl}:${group}`] ? positions : positions.slice(0, POSITION_PREVIEW_LIMIT);
return (
 <div key={group} class="support-column"><div class="support-column-head"><div class="fw7 fs13 text-navy">{group}</div><button class="btn-link" onClick={() => startEdit("custom-dept", group, { parent: wl })} title="แก้ไขกลุ่มงาน">✎</button></div><div class="support-position-list">
{visiblePositions.map((item) =><div key={item} class="structure-item group"><span class="fs12 fw6 text-gray-700">{item}</span><button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("custom-group-pos", item, { parent: group, workName: wl })}>✎</button></div>
)}
{positions.length === 0 && <div class="structure-empty">ยังไม่มีตำแหน่ง ระบบจะใช้กลุ่มงานนี้เป็นตำแหน่ง</div>}
 </div>
{positions.length > POSITION_PREVIEW_LIMIT &&
 <button
class="support-more"
onClick={() => setExpandedSupportGroups((current) => ({ ...current, [`${wl}:${group}`]: !current[`${wl}:${group}`] }))}>
{expandedSupportGroups.value[`${wl}:${group}`] ?
"ย่อรายการ" :
`ดูเพิ่มเติม ${positions.length - POSITION_PREVIEW_LIMIT} รายการ`}
 </button>
}
 <button class="support-add" onClick={() => {setAddItemData({ category: "pos", type: "", name: "", worklineName: wl, parent: group, grandparent: "" });setShowAddModal(true);}}>+ เพิ่มตำแหน่ง</button></div>
);
})}
{groupNames.length === 0 &&
 <div class="structure-empty">ยังไม่มีกลุ่มงานใน{wl}</div>
}
 </div>
{groupNames.length > SUPPORT_GROUP_PREVIEW_LIMIT &&
 <button class="support-group-more" onClick={() => setShowAllSupportGroups((current) => !current)}>
{showAllSupportGroups.value ? "ย่อกลุ่มงาน" : `ดูกลุ่มงานเพิ่มเติม ${groupNames.length - SUPPORT_GROUP_PREVIEW_LIMIT} กลุ่ม`}
 </button>
}
 </section>);

})}
{worklines.length === 0 &&
 <div class="structure-empty">ยังไม่มีข้อมูลสายงาน</div>
}
 </div></div> :
activeTab.value === "support-chain" ?
 <div class="structure-pane support-chain-pane"><div class="structure-heading">ฝ่าย/งานและหน่วยงานย่อย</div><div class="structure-note"><b>กำหนดได้สูงสุดอย่างละ 1 คน</b><span>หัวหน้าฝ่ายใช้บทบาทผู้บังคับบัญชา ส่วนหัวหน้างานใช้บทบาทหัวหน้างาน หน้าเพิ่มผู้ใช้จะอ้างอิงค่าจากหน้านี้โดยอัตโนมัติ</span></div><div class="structure-stack">
{Object.keys(supportOrg.value || {}).map((dept) =><section key={dept} class="structure-section"><div class="structure-section-head support-dept-head"><div><div class="fw8 fs14 text-navy">{dept}</div><div class="muted fs11">หัวหน้าฝ่าย (ผู้บังคับบัญชา)</div></div><select class="sel support-head-select-control" value={orgSups[dept] || ""} onChange={(e) => setOrgHead(dept, e.target.value)}><option value="">— เลือกหัวหน้าฝ่าย —</option>
{deptManagers.map((user) =><option key={user.sso} value={user.n}>{user.t}{user.n} · {user.p}</option>)}
 </select></div><div class="support-work-grid">
{(supportOrg.value[dept] || []).map((work: any) => {
const workPath = [dept, work.work].join(" > ");
return (
 <div key={work.work} class="support-work-card"><div class="support-work-head"><div class="fw8 fs13 text-navy">{work.work}</div><button class="btn-link" onClick={() => startEdit("support-work", work.work, { parent: dept })}>✎</button></div><div class="support-head-select"><div class="muted fs11">หัวหน้างาน</div><select class="sel" value={orgSups[workPath] || ""} onChange={(e) => setOrgHead(workPath, e.target.value)}><option value="">— เลือกหัวหน้างาน —</option>
{supervisors.map((user) =><option key={user.sso} value={user.n}>{user.t}{user.n} · {user.p}</option>)}
 </select></div><div class="support-unit-list">
{(work.units || []).map((unit: string) =><div key={unit} class="support-unit-row"><span>{unit}</span><button class="btn-link" onClick={() => startEdit("support-unit", unit, { parent: dept, workName: work.work })}>✎</button></div>
)}
{(work.units || []).length === 0 && <div class="structure-empty">ยังไม่มีหน่วย</div>}
 </div><div class="support-unit-add"><input class="inp" value={newSupportUnitNames.value[workPath] || ""} onInput={(e) => setNewSupportUnitNames((current) => ({ ...current, [workPath]: e.target.value }))} onKeydown={(e) => runOnEnter(e, () => addSupportUnit(dept, work.work))} placeholder="เพิ่มหน่วยใต้ งานนี้" /><button class="btn btn-s btn-sm" onClick={() => addSupportUnit(dept, work.work)}>+ เพิ่มหน่วย</button></div></div>);

})}
 <div class="support-work-card support-add-card"><input class="inp" value={newSupportWorkNames.value[dept] || ""} onInput={(e) => setNewSupportWorkNames((current) => ({ ...current, [dept]: e.target.value }))} onKeydown={(e) => runOnEnter(e, () => addSupportWork(dept))} placeholder={`เพิ่มงานใต้${dept}`} /><button class="btn btn-s btn-sm" onClick={() => addSupportWork(dept)}>+ เพิ่มงาน</button></div></div></section>
)}
 </div></div> :
activeTab.value === "dept" ?
 <div class="structure-pane"><div class="structure-heading">กลุ่มงาน</div><div class="structure-stack">
{worklines.length === 0 &&
 <div class="structure-empty">ยังไม่มีข้อมูลสายงานและกลุ่มงาน</div>
}
{(worklines.includes("สายบริหาร") || worklines.includes("สายงานบริหาร")) &&
 <section class="structure-section"><div class="structure-section-head"><div class="fw7 fs14 text-navy">สายบริหาร</div><button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "dept", type: "3", name: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มกลุ่มงาน</button></div><div class="structure-grid">
{adminDepts.map((item) =><div key={item} class="structure-item group"><div class="flex flex-col g4 overflow-hidden"><span class="fs13 fw7 text-gray-800">{item}</span></div><button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("admin-dept", item)}>✎</button></div>
)}
{adminDepts.length === 0 && <div class="structure-empty">ยังไม่มีกลุ่มงานในสายบริหาร</div>}
 </div></section>
}
{worklines.includes("สายวิชาการ") &&
 <section class="structure-section"><div class="structure-section-head"><div class="fw7 fs14 text-navy">สายวิชาการ</div><button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "dept", type: "1", name: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มกลุ่มงาน</button></div><div class="structure-grid">
{academicDepts.map((item) =><div key={item} class="structure-item group"><div class="flex flex-col g4 overflow-hidden"><span class="fs13 fw7 text-gray-800">{item}</span></div><button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("academic-dept", item)}>✎</button></div>
)}
{academicDepts.length === 0 && <div class="structure-empty">ยังไม่มีกลุ่มงานในสายวิชาการ</div>}
 </div></section>
}
{worklines.includes("สายสนับสนุน") && supportDepts.map((item) =><section key={item} class="structure-section"><div class="structure-section-head"><div class="fw7 fs14 text-navy">{item}</div><span class="fs11 muted">{(supportPositionGroups[item] || []).length} ตำแหน่ง</span></div><div class="structure-grid">
{(supportPositionGroups[item] || []).map((position) =><div key={position} class="structure-item"><span class="fs12 fw6 text-gray-700">{position}</span></div>
)}
{(supportPositionGroups[item] || []).length === 0 &&
 <div class="structure-empty">ยังไม่มีตำแหน่งในกลุ่มงานนี้</div>
}
 </div></section>
)}
{worklines.includes("สายสนับสนุน") && supportDepts.length === 0 &&
 <div class="structure-empty">ยังไม่มีกลุ่มงานในสายสนับสนุน</div>
}
 </div></div> :

activeTab.value === "pos" ?
 <div class="structure-pane"><div class="structure-heading">ระดับตำแหน่ง</div><div class="structure-stack level-stack">
{worklines.map((wl) => {
const directLevels = levelItemsForWorkline(wl);
const fallbackLevels = fallbackLevelsForWorkline(wl);
const visibleLevels = directLevels.length ? directLevels : fallbackLevels;
return (
 <section key={wl} class="structure-section level-section-card"><div class="structure-section-head level-head"><div><div class="fw7 fs14 text-navy">{wl}</div>
{!directLevels.length &&
 <div class="muted fs12">ยังไม่ได้เพิ่มระดับตำแหน่ง ระบบจะใช้ตำแหน่งในสายงานนี้เป็นระดับตำแหน่งอัตโนมัติ</div>
}
 </div><button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "rank", type: "", name: "", worklineName: wl, parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มระดับ</button></div><div class="structure-grid level-grid">
{visibleLevels.map((item) =><div key={item} class={`structure-item group level-item ${directLevels.length ? "" : "fallback"}`}><span class="fs13 fw6 text-gray-700 truncate">{item}</span>
{directLevels.length &&
 <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("rank", item, { parent: wl })}>✎</button>
}
 </div>
)}
{visibleLevels.length === 0 && <div class="structure-empty">ยังไม่มีตำแหน่งหรือระดับตำแหน่งใน{wl}</div>}
 </div></section>
);
})}
{worklines.length === 0 && <div class="structure-empty">ยังไม่มีข้อมูลสายงาน</div>}
 </div></div> :

 <div class="structure-pane"><div class="structure-heading">ประเภทสมรรถนะ</div><div class="structure-stack comp-stack"><section class="structure-section comp-section-card"><div class="structure-section-head"><div class="fw7 fs14 text-navy">หมวดหมู่สมรรถนะ</div><button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "comp", type: "1", name: "", fullName: "", desc: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มประเภท</button></div><div class="structure-grid">
{competencyTypeList.value.map((item) =><div key={getCompetencyTypeCode(item)} class="structure-item group competency-type-item"><div style={{ minWidth: 0 }}><div class="fs13 fw7 text-gray-800 truncate">{getCompetencyTypeCode(item)}</div><div class="muted fs12 truncate">{getCompetencyTypeFullName(item) || "-"}</div><div class="muted fs11 competency-type-desc">{getCompetencyTypeDesc(item) || "-"}</div></div><button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("comp-type", getCompetencyTypeCode(item), { item })}>✎</button></div>
)}
{competencyTypeList.value.length === 0 && <div class="structure-empty">ยังไม่มีข้อมูล</div>}
 </div></section><section class="structure-section comp-section-card"><div class="structure-section-head"><div class="fw7 fs14 text-navy">ประเภทการเรียนรู้</div><button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "learning", type: "1", name: "", desc: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มประเภทการเรียนรู้</button></div><div class="structure-grid">
{learningMethodList.value.map((item) =><div key={item.key} class="structure-item group" style={{ alignItems: "flex-start", minHeight: "72px" }}><div style={{ minWidth: 0 }}><div class="fs13 fw6 text-gray-700 truncate">{item.label}</div><div class="muted fs11" style={{ marginTop: "4px" }}>{item.desc || "-"}</div></div><button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: "12px" }} onClick={() => startEdit("learning-method", item.key, { item })}>✎</button></div>
)}
{learningMethodList.value.length === 0 && <div class="structure-empty">ยังไม่มีประเภทการเรียนรู้</div>}
 </div></section></div></div>
}
 </div></div>

{showSupportDeptModal.value &&
 <div class="mo"><div class="mo-box anim-fade-in" style={{ width: "420px" }}><div class="mo-h"><div class="fw8">เพิ่มฝ่าย</div><button class="btn btn-s btn-sm" type="button" onClick={() => setShowSupportDeptModal(false)}>✕</button></div><form class="mo-b" onSubmit={(e) => { e.preventDefault(); submitSupportDept(); }}><div class="fg"><label class="lbl fw8" style={{ color: "var(--navy)" }}>ชื่อฝ่ายใหม่</label><input
class="inp"
value={newSupportDeptName.value}
onInput={(e) => setNewSupportDeptName(e.target.value)}
onKeydown={(e) => runOnEnter(e, submitSupportDept)}
placeholder="กรอกชื่อฝ่าย..."
autoFocus
/></div><div class="add-modal-actions"><button class="btn btn-s" type="button" onClick={() => setShowSupportDeptModal(false)}>ยกเลิก</button><button class="btn btn-p add-modal-submit" type="submit">เพิ่มฝ่าย</button></div></form></div></div>
}

{showAddModal.value &&
 <div class="mo"><div class="mo-box anim-fade-in" style={{ width: "450px" }}><div class="mo-h"><div class="fw8">{getAddModalCopy().title}</div><button class="btn btn-s btn-sm" type="button" onClick={() => setShowAddModal(false)}>✕</button></div><form class="mo-b" onSubmit={(e) => { e.preventDefault(); saveAddItem(); }}><div class="fg"><label class="lbl fw8" style={{ color: "var(--navy)" }}>{getAddModalCopy().label}</label><input
ref={addNameInput}
class="inp"
value={addItemData.value.name}
onInput={(e) => setAddItemData({ ...addItemData.value, name: e.target.value })}
onKeydown={(e) => runOnEnter(e, saveAddItem)}
disabled={isSavingAddItem.value}
placeholder="กรอกชื่อที่ต้องการ..."
autoFocus
/></div>
{addItemData.value.category === "comp" &&
 <><div class="fg"><label class="lbl fw8" style={{ color: "var(--navy)" }}>ชื่อเต็มประเภทสมรรถนะ</label><input class="inp" value={addItemData.value.fullName} onChange={(e) => setAddItemData({ ...addItemData.value, fullName: e.target.value })} placeholder="กรอกชื่อเต็มประเภทสมรรถนะ" /></div><div class="fg"><label class="lbl fw8" style={{ color: "var(--navy)" }}>รายละเอียดประเภทสมรรถนะ</label><textarea class="ta" rows={3} value={addItemData.value.desc} onChange={(e) => setAddItemData({ ...addItemData.value, desc: e.target.value })} placeholder="อธิบายความหมายและขอบเขตของประเภทสมรรถนะนี้..." /></div></>
}
{addItemData.value.category === "learning" &&
 <div class="fg"><label class="lbl fw8" style={{ color: "var(--navy)" }}>รายละเอียดแบบย่อ</label><textarea class="ta" rows={3} value={addItemData.value.desc} onChange={(e) => setAddItemData({ ...addItemData.value, desc: e.target.value })} placeholder="อธิบายลักษณะของประเภทการเรียนรู้นี้โดยย่อ..." /></div>
}

 <div class="add-modal-actions"><button class="btn btn-s" type="button" onClick={() => setShowAddModal(false)}>ยกเลิก</button><button class="btn btn-p add-modal-submit" type="submit" disabled={isSavingAddItem.value}>
{isSavingAddItem.value ? "กำลังเพิ่ม..." : "เพิ่มรายการ"}
 </button></div></form></div></div>
}

{editingItem.value &&
 <div class="mo"><div class="mo-box anim-fade-in" style={{ width: "400px" }}><div class="mo-h"><div class="fw8">แก้ไขข้อมูลรายการ</div><button class="btn btn-s btn-sm" onClick={() => setEditingId(null)}>✕</button></div><form class="mo-b" onSubmit={(e) => { e.preventDefault(); saveEdit(); }}><div class="fg"><label class="lbl">{editingItem.value.type === "comp-type" ? "รหัสประเภทสมรรถนะ" : "ชื่อปัจจุบัน"}: <span class="muted">{editingItem.value.oldName}</span></label><input class="inp" value={newValue.value} onInput={(e) => setNewValue(e.target.value)} onKeydown={(e) => runOnEnter(e, saveEdit)} placeholder="กรอกชื่อใหม่..." autoFocus /></div>
{editingItem.value.type === "comp-type" &&
 <><div class="fg"><label class="lbl">ชื่อเต็มประเภทสมรรถนะ</label><input class="inp" value={editFullName.value} onChange={(e) => setEditFullName(e.target.value)} placeholder="กรอกชื่อเต็มประเภทสมรรถนะ" /></div><div class="fg"><label class="lbl">รายละเอียดประเภทสมรรถนะ</label><textarea class="ta" rows={3} value={editDesc.value} onChange={(e) => setEditDesc(e.target.value)} placeholder="อธิบายความหมายและขอบเขตของประเภทสมรรถนะนี้..." /></div></>
}
 <div style={{ display: "flex", gap: "8px", marginTop: "24px", justifyContent: "space-between" }}><button class="btn btn-r" type="button" style={{ background: '#fee2e2', color: '#ef4444', border: '1px solid #fecaca' }} onClick={deleteItem}> ลบรายการนี้</button><div class="flex g8"><button class="btn btn-s" type="button" onClick={() => setEditingId(null)}>ยกเลิก</button><button class="btn btn-p edit-modal-save" type="submit"> บันทึก</button></div></div></form></div></div>
}

 <style>{`
.structure-tabs { position: relative; z-index: 100; display: flex; gap: 4px; padding: 4px; width: fit-content; max-width: 100%; overflow-x: auto; border: 1px solid var(--border); border-radius: var(--r); background: #fff; pointer-events: auto; }
.structure-tab { position: relative; z-index: 1; flex: 0 0 auto; border: 0; border-radius: 6px; background: transparent; color: var(--text2); cursor: pointer; font-size: 13px; font-weight: 600; padding: 8px 12px; pointer-events: auto; }
.structure-tab.active { background: var(--blue); color: #fff; }
.structure-shell { position: relative; z-index: 1; min-height: 400px; overflow: hidden; border: 1px solid var(--border); border-radius: var(--r); background: #fff; }
.structure-shell.workline-shell,
.structure-shell.level-shell,
.structure-shell.comp-shell { min-height: 400px; overflow: hidden; border: 1px solid var(--border); background: #fff; }
.structure-shell.workline-shell .structure-pane,
.structure-shell.level-shell .structure-pane,
.structure-shell.comp-shell .structure-pane { padding: 20px; }
.structure-pane { padding: 20px; }
.structure-heading { margin-bottom: 14px; color: var(--text); font-size: 15px; font-weight: 800; }
.structure-note { display: grid; gap: 4px; margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--blue-md); border-left: 4px solid var(--blue); border-radius: 8px; background: var(--blue-lt); color: var(--text2); font-size: 12px; line-height: 1.55; }
.add-modal-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 24px; width: 100%; }
.add-modal-actions .btn { flex: 0 0 auto; min-width: 96px; }
.add-modal-submit { display: inline-flex !important; align-items: center; justify-content: center; min-width: 112px; background: var(--blue) !important; color: #fff !important; border-color: var(--blue) !important; opacity: 1 !important; visibility: visible !important; }
.edit-modal-save { display: inline-flex !important; align-items: center; justify-content: center; background: var(--blue) !important; color: #fff !important; border-color: var(--blue) !important; opacity: 1 !important; visibility: visible !important; }
.support-add-dept-btn { flex: 0 0 auto; min-height: 38px; white-space: nowrap; background: var(--blue) !important; border-color: var(--blue) !important; color: #fff !important; opacity: 1 !important; visibility: visible !important; }
.add-modal-submit:hover,
.edit-modal-save:hover,
.support-add-dept-btn:hover { background: #1d4ed8 !important; border-color: #1d4ed8 !important; color: #fff !important; }
.structure-note b { color: var(--blue); font-size: 13px; }
.structure-stack { display: grid; gap: 0; }
.structure-section { padding: 16px 0; border-top: 1px solid var(--border); }
.structure-section:first-child { padding-top: 0; border-top: 0; }
.workline-stack { gap: 14px; }
.workline-section { padding: 0 16px 16px; border: 1px solid #dbe5f1; border-radius: 10px; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); overflow: hidden; }
.workline-stack .workline-section:first-child { padding-top: 0; border-top: 1px solid #dbe5f1; }
.workline-section .structure-section-head { margin: 0 -16px 14px; padding: 13px 16px; border-bottom: 1px solid #dbe5f1; background: #f8fafc; }
.level-stack,
.comp-stack { gap: 0; }
.level-section-card,
.comp-section-card { padding: 16px 0; border: 0; border-top: 1px solid var(--border); border-radius: 0; background: transparent; box-shadow: none; }
.level-stack .level-section-card:first-child,
.comp-stack .comp-section-card:first-child { padding-top: 0; border-top: 0; }
.level-section-card .structure-section-head,
.comp-section-card .structure-section-head { margin-bottom: 12px; padding-bottom: 0; border-bottom: 0; }
.structure-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; min-width: 0; }
.structure-grid { display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); }
.structure-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 40px; padding: 9px 11px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); overflow: hidden; }
.level-auto-note { display: grid; gap: 4px; margin-bottom: 22px; padding: 14px 16px; border: 1px solid #bfdbfe; border-left: 4px solid var(--blue); border-radius: 8px; background: #eff6ff; }
.level-section { padding: 18px 0; border-top: 1px solid var(--border); }
.level-section:first-of-type { border-top: 0; }
.level-head { margin-bottom: 12px; }
.level-grid { grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); }
.level-item { min-height: 40px; background: #f8fafc; }
.level-item.fallback { border-style: dashed; background: #fbfdff; color: var(--text3); }
.competency-type-item { align-items: flex-start; min-height: 86px; }
.competency-type-desc { display: -webkit-box; margin-top: 4px; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.support-chain-item { align-items: center; grid-column: span 2; }
.support-chain-item .sel { max-width: 280px; }
.support-chain-pane button,
.support-chain-pane input,
.support-chain-pane select { position: relative; z-index: 1; }
.support-add-head,
.support-dept-head { align-items: flex-start; }
.support-add-controls { display: flex; flex: 0 1 min(100%, 420px); gap: 8px; min-width: 0; }
.support-add-controls .inp { min-width: 0; flex: 1 1 auto; }
.support-add-controls .btn { flex: 0 0 auto; white-space: nowrap; }
.support-head-select-control { flex: 0 1 320px; max-width: min(100%, 320px); min-width: 180px; }
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
.support-column { display: grid; align-content: start; gap: 9px; min-height: 170px; padding: 12px; border: 1px solid #dbe5f1; border-radius: 7px; background: #fff; }
.support-column-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; min-height: 32px; padding-bottom: 9px; border-bottom: 1px solid var(--border); }
.support-position-list { display: grid; align-content: start; gap: 7px; }
.support-more { justify-self: start; border: 0; background: transparent; color: var(--blue); cursor: pointer; font-size: 12px; font-weight: 700; padding: 0; }
.support-add { width: 100%; min-height: 34px; border: 1px dashed #dbe5f1; border-radius: 7px; background: transparent; color: var(--blue); cursor: pointer; font-size: 12px; font-weight: 700; }
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
`}</style></>;} });



export default AdminOrgStructure;
</script>
