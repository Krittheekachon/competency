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

interface AdminOrgStructureProps {academicDepts: string[];setAcademicDepts: any;supportDepts: string[];supportPositionGroups: Record<string, string[]>;setSupportPositionGroups: any;adminDepts: string[];setAdminDepts: any;supportOrg: any;setSupportOrg: any;users: any[];orgSups: Record<string, string>;setOrgSups: any;academicPos: string[];setAcademicPos: any;supportPos: string[];setSupportPos: any;adminPos: string[];setAdminPos: any;jobFamiliesByWorkline: Record<string, Record<string, string[]>>;setJobFamiliesByWorkline: any;levelsByWorkline: Record<string, string[]>;setLevelsByWorkline: any;levelExpectationsByWorkline: Record<string, Record<string, number | null>>;setLevelExpectationsByWorkline: any;levelsByJobFamily: Record<string, Record<string, string[]>>;setLevelsByJobFamily: any;levelExpectationsByJobFamily: Record<string, Record<string, Record<string, number | null>>>;setLevelExpectationsByJobFamily: any;
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

const AdminOrgStructure = defineComponent({ name: "AdminOrgStructure", props: ["academicDepts", "setAcademicDepts", "supportDepts", "supportPositionGroups", "setSupportPositionGroups", "adminDepts", "setAdminDepts", "supportOrg", "setSupportOrg", "users", "orgSups", "setOrgSups", "academicPos", "setAcademicPos", "supportPos", "setSupportPos", "adminPos", "setAdminPos", "jobFamiliesByWorkline", "setJobFamiliesByWorkline", "levelsByWorkline", "setLevelsByWorkline", "levelExpectationsByWorkline", "setLevelExpectationsByWorkline", "levelsByJobFamily", "setLevelsByJobFamily", "levelExpectationsByJobFamily", "setLevelExpectationsByJobFamily", "academicRank", "setAcademicRank", "supportRank", "setSupportRank", "worklines", "setWorklines", "competencyTypes", "setCompetencyTypes", "learningMethods", "setLearningMethods"], setup(__props) {const {
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
      setLevelExpectationsByWorkline,
      setLevelsByJobFamily,
      setLevelExpectationsByJobFamily,
      setAcademicRank,
      setSupportRank,
      setWorklines,
      setCompetencyTypes,
      learningMethods, setLearningMethods
    } = __props as any;
    let academicDepts = [...((__props as any).academicDepts || [])];
    let supportDepts = [...((__props as any).supportDepts || [])];
    let supportPositionGroups = { ...((( __props as any).supportPositionGroups) || {}) };
    let supportOrg = { ...((initialSupportOrg as any) || {}) };
    let adminDepts = [...((__props as any).adminDepts || [])];
    let academicPos = [...((__props as any).academicPos || [])];
    let adminPos = [...((__props as any).adminPos || [])];
    let jobFamiliesByWorkline = { ...((( __props as any).jobFamiliesByWorkline) || {}) };
    let levelsByWorkline = { ...((( __props as any).levelsByWorkline) || {}) };
    let levelExpectationsByWorkline = { ...((( __props as any).levelExpectationsByWorkline) || {}) };
    let levelsByJobFamily = { ...((( __props as any).levelsByJobFamily) || {}) };
    let levelExpectationsByJobFamily = { ...((( __props as any).levelExpectationsByJobFamily) || {}) };
    let worklines = [...((__props as any).worklines || [])];
    const POSITION_PREVIEW_LIMIT = 4;
    const SUPPORT_GROUP_PREVIEW_LIMIT = 4;
    const competencyTypeList = ref<any[]>([...((__props as any).competencyTypes || [])]);
    const [activeTab, setActiveTab] = useState("workline");
    const [activeLevelWorkline, setActiveLevelWorkline] = useState("");
    const [editingItem, setEditingId] = useState<any>(null);
    const [newValue, setNewValue] = useState("");
    const [editExpectedLevel, setEditExpectedLevel] = useState("1");
    const [editFullName, setEditFullName] = useState("");
    const [editDesc, setEditDesc] = useState("");
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
      expectedLevel: "1",
      worklineName: "",
      parent: "",
      grandparent: ""
    });
    const [structureVersion, setStructureVersion] = useState(0);
    const addNameInput = ref<HTMLInputElement | null>(null);
    const dean = users.find((user) => user.r === "manager")?.n || "";
    const deptManagers = users.filter((user) => ["dept_head", "manager_dept"].includes(user.r));
    const supervisors = users.filter((user) => user.r === "supervisor");
    const getCompetencyTypeCode = (item: any) => typeof item === "string" ? item : item?.code || item?.name || "";
    const getCompetencyTypeFullName = (item: any) => typeof item === "string" ? "" : item?.fullName || item?.label || "";
    const getCompetencyTypeDesc = (item: any) => typeof item === "string" ? "" : item?.desc || "";
    const getCompetencyTypeId = (item: any) => typeof item === "string" ? null : item?.id || null;
    useEffect(() => {
      competencyTypeList.value = [...((( __props as any).competencyTypes) || [])];
    }, [(__props as any).competencyTypes]);
    useEffect(() => {
      learningMethodList.value = [...((( __props as any).learningMethods) || [])];
    }, [(__props as any).learningMethods]);
    const applyCompetencyTypes = (next: any[]) => {
      competencyTypeList.value = [...next];
      setCompetencyTypes(next);
      setStructureVersion((current: number) => current + 1);
    };
    const syncCompetencyTypesFromPage = (responsePage: any) => {
      if (Array.isArray(responsePage.props.competencyTypes)) {
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
    const applyWorklines = (nextWorklines: string[]) => {
      worklines = [...nextWorklines];
      setWorklines([...nextWorklines]);
      setStructureVersion((current: number) => current + 1);
    };
    const normalizeWorklineName = (name = "") => String(name).replace(/^สายงาน\s*/, "").replace(/^สาย\s*/, "").trim();
    const worklineNameByKind = (kind: string, fallback: string) => {
      return worklines.find((name) => normalizeWorklineName(name) === kind) || fallback;
    };
    const supportWorklineName = () => worklineNameByKind("สนับสนุน", "สายสนับสนุน");
    const isWorklineKind = (user: any, kind: string) => normalizeWorklineName(user?.w || "") === kind;
    const divisionHeadOptions = users.filter((user) => isWorklineKind(user, "บริหาร") && user.r === "supervisor");
    const workHeadOptions = users.filter((user) => isWorklineKind(user, "สนับสนุน") && ["head", "dept_head", "manager_dept"].includes(user.r));
    const supportOrgFromGroups = (groups: Record<string, string[]> = {}) => Object.fromEntries(
      Object.entries(groups || {}).map(([dept, works]) => [
        dept,
        (Array.isArray(works) ? works : []).map((work) => ({ work, units: [] }))
      ])
    );
    const applyJobFamiliesByWorkline = (nextGroups: Record<string, Record<string, string[]>>) => {
      jobFamiliesByWorkline = { ...nextGroups };
      academicDepts = Object.keys(jobFamiliesByWorkline["สายวิชาการ"] || jobFamiliesByWorkline["วิชาการ"] || {});
      adminDepts = Object.keys(jobFamiliesByWorkline["สายงานบริหาร"] || jobFamiliesByWorkline["สายบริหาร"] || {});
      const supportName = supportWorklineName();
      supportPositionGroups = jobFamiliesByWorkline[supportName] || jobFamiliesByWorkline["สายสนับสนุน"] || jobFamiliesByWorkline["สายงานสนับสนุน"] || jobFamiliesByWorkline["สนับสนุน"] || {};
      supportOrg = supportOrgFromGroups(supportPositionGroups);
      supportDepts = Object.keys(supportPositionGroups);
      setJobFamiliesByWorkline(jobFamiliesByWorkline);
      setAcademicDepts(academicDepts);
      setAdminDepts(adminDepts);
      setSupportPositionGroups(supportPositionGroups);
      setSupportOrg(supportOrg);
      setStructureVersion((current: number) => current + 1);
    };
    const applyLevelsByWorkline = (nextLevels: Record<string, string[]>) => {
      levelsByWorkline = { ...nextLevels };
      setLevelsByWorkline(levelsByWorkline);
      setAcademicRank(levelsByWorkline["สายวิชาการ"] || levelsByWorkline["วิชาการ"] || []);
      setSupportRank(levelsByWorkline[supportWorklineName()] || levelsByWorkline["สายสนับสนุน"] || levelsByWorkline["สายงานสนับสนุน"] || levelsByWorkline["สนับสนุน"] || []);
      setStructureVersion((current: number) => current + 1);
    };
    const applyLevelExpectationsByWorkline = (nextExpectations: Record<string, Record<string, number | null>>) => {
      levelExpectationsByWorkline = { ...nextExpectations };
      setLevelExpectationsByWorkline(levelExpectationsByWorkline);
      setStructureVersion((current: number) => current + 1);
    };
    const applyLevelsByJobFamily = (nextLevels: Record<string, Record<string, string[]>>) => {
      levelsByJobFamily = { ...nextLevels };
      setLevelsByJobFamily(levelsByJobFamily);
      setStructureVersion((current: number) => current + 1);
    };
    const applyLevelExpectationsByJobFamily = (nextExpectations: Record<string, Record<string, Record<string, number | null>>>) => {
      levelExpectationsByJobFamily = { ...nextExpectations };
      setLevelExpectationsByJobFamily(levelExpectationsByJobFamily);
      setStructureVersion((current: number) => current + 1);
    };
    const syncStructureFromPage = (responsePage: any) => {
      const props = responsePage?.props || {};

      if (Array.isArray(props.worklines)) {
        applyWorklines(props.worklines);
      }

      if (props.jobFamiliesByWorkline && typeof props.jobFamiliesByWorkline === "object") {
        applyJobFamiliesByWorkline(props.jobFamiliesByWorkline);
      }

      if (props.supportOrg && typeof props.supportOrg === "object") {
        supportOrg = { ...props.supportOrg };
        setSupportOrg(supportOrg);
        setStructureVersion((current: number) => current + 1);
      }

      if (props.levelsByWorkline && typeof props.levelsByWorkline === "object") {
        applyLevelsByWorkline(props.levelsByWorkline);
      }

      if (props.levelExpectationsByWorkline && typeof props.levelExpectationsByWorkline === "object") {
        applyLevelExpectationsByWorkline(props.levelExpectationsByWorkline);
      }

      if (props.levelsByJobFamily && typeof props.levelsByJobFamily === "object") {
        applyLevelsByJobFamily(props.levelsByJobFamily);
      }

      if (props.levelExpectationsByJobFamily && typeof props.levelExpectationsByJobFamily === "object") {
        applyLevelExpectationsByJobFamily(props.levelExpectationsByJobFamily);
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
    const postStructure = (routeName: string, payload: any, onSuccess: () => void) => {
      router.post(route(routeName), payload, {
        ...persistOptions,
        onSuccess: (responsePage: any) => {
          onSuccess();
          syncStructureFromPage(responsePage);
        },
        onError: (errors: any) => {
          setIsSavingAddItem(false);
          showPersistError(errors);
        }
      });
    };
    const putStructure = (routeName: string, payload: any, onSuccess: () => void) => {
      router.put(route(routeName), payload, {
        ...persistOptions,
        onSuccess: (responsePage: any) => {
          onSuccess();
          syncStructureFromPage(responsePage);
        }
      });
    };
    const deleteStructure = (routeName: string, payload: any, onSuccess: () => void) => {
      router.delete(route(routeName), {
        ...persistOptions,
        data: payload,
        onSuccess: (responsePage: any) => {
          onSuccess();
          syncStructureFromPage(responsePage);
        }
      });
    };
    const worklineNameFromType = (type: string) => {
      if (type === "1") return "สายวิชาการ";
      if (type === "2") return supportWorklineName();
      return "สายงานบริหาร";
    };
    const levelItemsForWorkline = (worklineName: string) => {
      const directLevels = levelsByWorkline?.[worklineName] || [];
      return Array.from(new Set(directLevels));
    };
    const levelItemsForScope = (worklineName: string, jobFamilyName = "") => {
      const directLevels = jobFamilyName
        ? levelsByJobFamily?.[worklineName]?.[jobFamilyName] || []
        : levelsByWorkline?.[worklineName] || [];
      return Array.from(new Set(directLevels));
    };
    const expectedLevelForItem = (worklineName: string, levelName: string, jobFamilyName = "") => {
      const value = jobFamilyName
        ? levelExpectationsByJobFamily?.[worklineName]?.[jobFamilyName]?.[levelName]
        : levelExpectationsByWorkline?.[worklineName]?.[levelName];
      return value === null || value === undefined ? "" : String(value);
    };
    const levelNameCollator = new Intl.Collator("th", {
      numeric: true,
      sensitivity: "base",
    });
    const sortedLevelItemsForWorkline = (worklineName: string, levelNames: string[], jobFamilyName = "") => {
      return [...levelNames].sort((first, second) => {
        const firstExpected = Number(expectedLevelForItem(worklineName, first, jobFamilyName) || 99);
        const secondExpected = Number(expectedLevelForItem(worklineName, second, jobFamilyName) || 99);

        if (firstExpected !== secondExpected) return firstExpected - secondExpected;
        return levelNameCollator.compare(first, second);
      });
    };
    const fallbackLevelsForWorkline = (worklineName: string) => {
      const groupMap = groupMapForWorkline(worklineName);
      return Array.from(new Set(Object.entries(groupMap).flatMap(([group, positions]) => positions.length ? positions : [group])));
    };
    const fallbackLevelSourcesForWorkline = (worklineName: string) => {
      const seen = new Set<string>();
      return Object.entries(groupMapForWorkline(worklineName)).flatMap(([groupName, positions]) => {
        const items = positions.length
          ? positions.map((positionName) => ({ name: positionName, groupName, source: "position" }))
          : [{ name: groupName, groupName, source: "job-family" }];

        return items.filter((item) => {
          if (seen.has(item.name)) return false;
          seen.add(item.name);
          return true;
        });
      });
    };
    const setLevelItemsForWorkline = (worklineName: string, nextLevels: string[]) => {
      const uniqueLevels = Array.from(new Set(nextLevels));
      applyLevelsByWorkline({
        ...levelsByWorkline,
        [worklineName]: uniqueLevels
      });
    };
    const setLevelItemsForScope = (worklineName: string, jobFamilyName: string, nextLevels: string[]) => {
      const uniqueLevels = Array.from(new Set(nextLevels));
      if (!jobFamilyName) {
        setLevelItemsForWorkline(worklineName, uniqueLevels);
        return;
      }
      applyLevelsByJobFamily({
        ...levelsByJobFamily,
        [worklineName]: {
          ...(levelsByJobFamily[worklineName] || {}),
          [jobFamilyName]: uniqueLevels,
        }
      });
    };
    const setExpectedLevelForItem = (worklineName: string, levelName: string, expectedLevel: number | null, jobFamilyName = "") => {
      if (jobFamilyName) {
        applyLevelExpectationsByJobFamily({
          ...levelExpectationsByJobFamily,
          [worklineName]: {
            ...(levelExpectationsByJobFamily[worklineName] || {}),
            [jobFamilyName]: {
              ...(levelExpectationsByJobFamily[worklineName]?.[jobFamilyName] || {}),
              [levelName]: expectedLevel
            }
          }
        });
        return;
      }
      applyLevelExpectationsByWorkline({
        ...levelExpectationsByWorkline,
        [worklineName]: {
          ...(levelExpectationsByWorkline[worklineName] || {}),
          [levelName]: expectedLevel
        }
      });
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
      applyJobFamiliesByWorkline({
        ...jobFamiliesByWorkline,
        [worklineName]: groups
      });
    };

    const setOrgHead = (path: string, value: string) => {
      setOrgSups((current) => ({ ...current, [path]: value }));
    };

    const addSupportWork = (dept: string) => {
      const name = (newSupportWorkNames.value[dept] || "").trim();
      if (!name) return;
      const works = supportOrg[dept] || [];
      if (works.some((item: any) => item.work === name)) return;
      if (isSavingAddItem.value) return;
      setIsSavingAddItem(true);
      postStructure("admin.structure.support-works.store", { division_name: dept, name }, () => {
        supportOrg = { ...supportOrg, [dept]: [...works, { work: name, units: [] }] };
        setSupportOrg(supportOrg);
        setOrgSups((current) => ({ ...current, [[dept, name].join(" > ")]: workHeadOptions[0]?.n || "" }));
        setNewSupportWorkNames((current) => ({ ...current, [dept]: "" }));
        setIsSavingAddItem(false);
      });
    };

    const addSupportUnit = (dept: string, workName: string) => {
      const workPath = [dept, workName].join(" > ");
      const name = (newSupportUnitNames.value[workPath] || "").trim();
      if (!name) return;
      const works = supportOrg[dept] || [];
      if (works.some((item: any) => item.work === workName && (item.units || []).includes(name))) return;
      if (isSavingAddItem.value) return;
      setIsSavingAddItem(true);
      postStructure("admin.structure.support-units.store", {
        workline_name: supportWorklineName(),
        division_name: dept,
        work_name: workName,
        name
      }, () => {
        supportOrg = {
          ...supportOrg,
          [dept]: works.map((item: any) =>
          item.work === workName ?
          { ...item, units: [...(item.units || []), name] } :
          item
          )
        };
        setSupportOrg(supportOrg);
        setNewSupportUnitNames((current) => ({ ...current, [workPath]: "" }));
        setIsSavingAddItem(false);
      });
    };

    const startEdit = (type: string, oldName: string, extras?: any) => {
      setEditingId({ type, oldName, ...extras });
      setNewValue(oldName);
      const rankWorklineName = extras?.worklineName || extras?.parent || (type === "support-rank" ? supportWorklineName() : "สายวิชาการ");
      const rankJobFamilyName = extras?.jobFamilyName || "";
      const expectedLevel = type === "rank" || type === "academic-rank" || type === "support-rank"
        ? expectedLevelForItem(rankWorklineName, oldName, rankJobFamilyName)
        : "1";
      setEditExpectedLevel(expectedLevel || "1");
      setEditFullName(type === "comp-type" ? getCompetencyTypeFullName(extras?.item) : "");
      setEditDesc(type === "comp-type" ? getCompetencyTypeDesc(extras?.item) : "");
    };

    const saveEdit = () => {
      if (!newValue.value.trim()) return;
      const { type, oldName, parent, workName, worklineName, jobFamilyName } = editingItem.value;
      switch (type) {
        case "academic-dept":
          putStructure("admin.structure.job-families.update", { workline_name: "สายวิชาการ", old_name: oldName, name: newValue.value }, () => {
            const groups = groupMapForWorkline("สายวิชาการ");
            const nextGroups = { ...groups, [newValue.value]: groups[oldName] || [] };
            delete nextGroups[oldName];
            setGroupMapForWorkline("สายวิชาการ", nextGroups);
            setEditingId(null);
          });
          return;
        case "admin-dept":
          putStructure("admin.structure.job-families.update", { workline_name: "สายงานบริหาร", old_name: oldName, name: newValue.value }, () => {
            const groups = groupMapForWorkline("สายงานบริหาร");
            const nextGroups = { ...groups, [newValue.value]: groups[oldName] || [] };
            delete nextGroups[oldName];
            setGroupMapForWorkline("สายงานบริหาร", nextGroups);
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
            const targetWorklineName = supportWorklineName();
            putStructure("admin.structure.support-departments.update", { old_name: oldName, name: newValue.value }, () => {
              supportOrg = { ...supportOrg, [newValue.value]: supportOrg[oldName] || [] };
              delete supportOrg[oldName];
              setSupportOrg(supportOrg);
              setEditingId(null);
            });
            return;
          }
        case "support-group-pos":
        case "custom-group-pos":
          putStructure("admin.structure.positions.update", { workline_name: workName, job_family_name: parent, old_name: oldName, name: newValue.value }, () => {
            if (type === "support-group-pos") {
              setGroupMapForWorkline(supportWorklineName(), {
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
            const nextSupportOrg = { ...supportOrg };
            if (parent && nextSupportOrg[parent]) {
              putStructure("admin.structure.support-works.update", { division_name: parent, old_name: oldName, name: newValue.value }, () => {
                nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === oldName ? { ...w, work: newValue.value } : w);
                setSupportOrg(nextSupportOrg);
                setEditingId(null);
              });
              return;
            }
            break;
          }
        case "support-unit":{
            const nextSupportOrg = { ...supportOrg };
            if (parent && workName && nextSupportOrg[parent]) {
              putStructure("admin.structure.support-units.update", {
                workline_name: supportWorklineName(),
                division_name: parent,
                work_name: workName,
                old_name: oldName,
                name: newValue.value
              }, () => {
                nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === workName ? { ...w, units: w.units.map((u: string) => u === oldName ? newValue.value : u) } : w);
                setSupportOrg(nextSupportOrg);
                setEditingId(null);
              });
              return;
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
          putStructure("admin.structure.job-families.update", { workline_name: "สายงานบริหาร", old_name: oldName, name: newValue.value }, () => {
            setAdminPos(adminPos.map((v) => v === oldName ? newValue.value : v));
            setEditingId(null);
          });
          return;
        case "rank":
        case "academic-rank":
        case "support-rank":{
          const targetWorklineName = worklineName || parent || (type === "support-rank" ? supportWorklineName() : "สายวิชาการ");
          const targetJobFamilyName = jobFamilyName || "";
          const expectedLevel = Number(editExpectedLevel.value || 1);
          putStructure("admin.structure.levels.update", { workline_name: targetWorklineName, job_family_name: targetJobFamilyName, old_name: oldName, name: newValue.value, expected_level: expectedLevel }, () => {
            setLevelItemsForScope(targetWorklineName, targetJobFamilyName, levelItemsForScope(targetWorklineName, targetJobFamilyName).map((v) => v === oldName ? newValue.value : v));
            if (oldName !== newValue.value) {
              if (targetJobFamilyName) {
                const currentExpectations = levelExpectationsByJobFamily[targetWorklineName]?.[targetJobFamilyName] || {};
                const nextExpectations = { ...currentExpectations, [newValue.value]: expectedLevel };
                delete nextExpectations[oldName];
                applyLevelExpectationsByJobFamily({
                  ...levelExpectationsByJobFamily,
                  [targetWorklineName]: {
                    ...(levelExpectationsByJobFamily[targetWorklineName] || {}),
                    [targetJobFamilyName]: nextExpectations
                  }
                });
              } else {
                const currentExpectations = levelExpectationsByWorkline[targetWorklineName] || {};
                const nextExpectations = { ...currentExpectations, [newValue.value]: expectedLevel };
                delete nextExpectations[oldName];
                applyLevelExpectationsByWorkline({
                  ...levelExpectationsByWorkline,
                  [targetWorklineName]: nextExpectations
                });
              }
            } else {
              setExpectedLevelForItem(targetWorklineName, newValue.value, expectedLevel, targetJobFamilyName);
            }
            setEditingId(null);
          });
          return;
        }
        case "workline":
          putStructure("admin.structure.worklines.update", { old_name: oldName, name: newValue.value }, () => {
            applyWorklines(worklines.map((v) => v === oldName ? newValue.value : v));
            const nextGroups = { ...jobFamiliesByWorkline, [newValue.value]: jobFamiliesByWorkline[oldName] || {} };
            delete nextGroups[oldName];
            applyJobFamiliesByWorkline(nextGroups);
            const nextLevels = { ...levelsByWorkline, [newValue.value]: levelsByWorkline[oldName] || [] };
            delete nextLevels[oldName];
            applyLevelsByWorkline(nextLevels);
            const nextExpectations = { ...levelExpectationsByWorkline, [newValue.value]: levelExpectationsByWorkline[oldName] || {} };
            delete nextExpectations[oldName];
            applyLevelExpectationsByWorkline(nextExpectations);
            setEditingId(null);
          });
          return;
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
      const { type, oldName, parent, workName, worklineName, jobFamilyName } = editingItem.value;

      switch (type) {
        case "academic-dept":
          deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายวิชาการ", name: oldName }, () => {
            const nextGroups = { ...groupMapForWorkline("สายวิชาการ") };
            delete nextGroups[oldName];
            setGroupMapForWorkline("สายวิชาการ", nextGroups);
            setEditingId(null);
          });
          return;
        case "admin-dept":
          deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายงานบริหาร", name: oldName }, () => {
            const nextGroups = { ...groupMapForWorkline("สายงานบริหาร") };
            delete nextGroups[oldName];
            setGroupMapForWorkline("สายงานบริหาร", nextGroups);
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
            deleteStructure("admin.structure.support-departments.destroy", { name: oldName }, () => {
              supportOrg = { ...supportOrg };
              delete supportOrg[oldName];
              setSupportOrg(supportOrg);
              setEditingId(null);
            });
            return;
          }
        case "support-group-pos":
        case "custom-group-pos":
          deleteStructure("admin.structure.positions.destroy", { workline_name: workName, job_family_name: parent, name: oldName }, () => {
            if (type === "support-group-pos") {
              setGroupMapForWorkline(supportWorklineName(), {
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
            const nextSupportOrg = { ...supportOrg };
            if (parent && nextSupportOrg[parent]) {
              deleteStructure("admin.structure.support-works.destroy", { division_name: parent, name: oldName }, () => {
                nextSupportOrg[parent] = nextSupportOrg[parent].filter((w: any) => w.work !== oldName);
                setSupportOrg(nextSupportOrg);
                setEditingId(null);
              });
              return;
            }
            break;
          }
        case "support-unit":{
            const nextSupportOrg = { ...supportOrg };
            if (parent && workName && nextSupportOrg[parent]) {
              deleteStructure("admin.structure.support-units.destroy", {
                workline_name: supportWorklineName(),
                division_name: parent,
                work_name: workName,
                name: oldName
              }, () => {
                nextSupportOrg[parent] = nextSupportOrg[parent].map((w: any) => w.work === workName ? { ...w, units: w.units.filter((u: string) => u !== oldName) } : w);
                setSupportOrg(nextSupportOrg);
                setEditingId(null);
              });
              return;
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
          deleteStructure("admin.structure.job-families.destroy", { workline_name: "สายงานบริหาร", name: oldName }, () => {
            setAdminPos(adminPos.filter((v) => v !== oldName));
            setEditingId(null);
          });
          return;
        case "rank":
        case "academic-rank":
        case "support-rank":{
          const targetWorklineName = worklineName || parent || (type === "support-rank" ? supportWorklineName() : "สายวิชาการ");
          const targetJobFamilyName = jobFamilyName || "";
          deleteStructure("admin.structure.levels.destroy", { workline_name: targetWorklineName, job_family_name: targetJobFamilyName, name: oldName }, () => {
            setLevelItemsForScope(targetWorklineName, targetJobFamilyName, levelItemsForScope(targetWorklineName, targetJobFamilyName).filter((v) => v !== oldName));
            if (targetJobFamilyName) {
              const nextExpectations = { ...(levelExpectationsByJobFamily[targetWorklineName]?.[targetJobFamilyName] || {}) };
              delete nextExpectations[oldName];
              applyLevelExpectationsByJobFamily({
                ...levelExpectationsByJobFamily,
                [targetWorklineName]: {
                  ...(levelExpectationsByJobFamily[targetWorklineName] || {}),
                  [targetJobFamilyName]: nextExpectations
                }
              });
            } else {
              const nextExpectations = { ...(levelExpectationsByWorkline[targetWorklineName] || {}) };
              delete nextExpectations[oldName];
              applyLevelExpectationsByWorkline({
                ...levelExpectationsByWorkline,
                [targetWorklineName]: nextExpectations
              });
            }
            setEditingId(null);
          });
          return;
        }
        case "workline":
          deleteStructure("admin.structure.worklines.destroy", { name: oldName }, () => {
            applyWorklines(worklines.filter((v) => v !== oldName));
            const nextGroups = { ...jobFamiliesByWorkline };
            delete nextGroups[oldName];
            applyJobFamiliesByWorkline(nextGroups);
            const nextLevels = { ...levelsByWorkline };
            delete nextLevels[oldName];
            applyLevelsByWorkline(nextLevels);
            setEditingId(null);
          });
          return;
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
      "สายงานบริหาร");

      if (addItemData.value.category === "workline") return { title: "เพิ่มสายงาน", label: "ชื่อสายงาน" };
      if (addItemData.value.category === "support-dept") return { title: "เพิ่มฝ่าย", label: "ชื่อฝ่าย" };
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
        if (category === "support-dept") {
          if (supportOrg[trimmedName]) {
            alert(`มีฝ่าย "${trimmedName}" แล้ว`);
            return;
          }
          setIsSavingAddItem(true);
          postStructure("admin.structure.support-departments.store", { name: trimmedName }, () => {
            supportOrg = { ...supportOrg, [trimmedName]: [] };
            setSupportOrg(supportOrg);
            setOrgSups((current) => ({ ...current, [trimmedName]: divisionHeadOptions[0]?.n || "" }));
            setIsSavingAddItem(false);
            setShowAddModal(false);
            setAddItemData({ ...addItemData.value, name: "" });
          });
          return;
        } else if (category === "dept") {
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
          const nextSupportOrg = { ...supportOrg };
          if (parent && nextSupportOrg[parent]) {
            nextSupportOrg[parent] = [...nextSupportOrg[parent], { work: trimmedName, units: [] }];
            setSupportOrg(nextSupportOrg);
          }
        } else if (category === "unit") {
          const nextSupportOrg = { ...supportOrg };
          if (grandparent && parent && nextSupportOrg[grandparent]) {
            nextSupportOrg[grandparent] = nextSupportOrg[grandparent].map((w: any) => w.work === parent ? { ...w, units: [...w.units, trimmedName] } : w);
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
          const targetJobFamilyName = parent || "";
          if (levelItemsForScope(targetWorklineName, targetJobFamilyName).includes(trimmedName)) {
            alert(`มีระดับตำแหน่ง "${trimmedName}" ใน${targetJobFamilyName || targetWorklineName}แล้ว`);
            return;
          }
          const expectedLevel = Number(addItemData.value.expectedLevel || 1);
          setIsSavingAddItem(true);
          postStructure("admin.structure.levels.store", { workline_name: targetWorklineName, job_family_name: targetJobFamilyName, name: trimmedName, expected_level: expectedLevel }, () => {
            setLevelItemsForScope(targetWorklineName, targetJobFamilyName, [...levelItemsForScope(targetWorklineName, targetJobFamilyName), trimmedName]);
            setExpectedLevelForItem(targetWorklineName, trimmedName, expectedLevel, targetJobFamilyName);
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
            applyWorklines([trimmedName, ...worklines]);
            applyJobFamiliesByWorkline({
              [trimmedName]: {},
              ...jobFamiliesByWorkline
            });
            applyLevelsByWorkline({
              [trimmedName]: [],
              ...levelsByWorkline
            });
            setIsSavingAddItem(false);
            clearAddNameAndFocus();
          });
          return;
        } else if (category === "comp") {
          if (!fullName.trim() || !desc.trim()) return;
          router.post(route("admin.competency-types.store"), {
            code: trimmedName,
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
        <button class={`structure-tab ${activeTab.value === "workline" ? "active" : ""}`} onClick={() => setActiveTab("workline")}>สายงาน/กลุ่มงาน/ตำแหน่ง</button>
        <button class={`structure-tab ${activeTab.value === "pos" ? "active" : ""}`} onClick={() => setActiveTab("pos")}>ระดับตำแหน่ง</button>
        <button class={`structure-tab ${activeTab.value === "comp" ? "active" : ""}`} onClick={() => setActiveTab("comp")}>ประเภทสมรรถนะ</button>
      </div>

      <div class="anim-fade-in" data-structure-version={structureVersion.value}>
        <div class={`structure-shell ${activeTab.value === "workline" ? "workline-shell" : ""} ${activeTab.value === "pos" ? "level-shell" : ""} ${activeTab.value === "comp" ? "comp-shell" : ""}`}>
          {activeTab.value === "workline" ?
          <div class="structure-pane">
              <div class="structure-heading">สายงานและตำแหน่ง</div>
              <div class="structure-stack workline-stack">
                {worklines.map((wl) => {
                const groupMap = groupMapForWorkline(wl);
                const groupNames = Object.keys(groupMap);
                return (
                  <section key={wl} class="structure-section workline-section">
                      <div class="structure-section-head">
                        <div class="fw7 fs14 text-navy">{wl}</div>
                        <div class="flex g8">
                          <button
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
                          </button>
                          <button class="btn btn-s btn-sm" onClick={() => startEdit("workline", wl)}>แก้ไข</button>
                        </div>
                      </div>
                      <div class="support-columns">
                        {(showAllSupportGroups.value ? groupNames : groupNames.slice(0, SUPPORT_GROUP_PREVIEW_LIMIT)).map((group) => {
                          const positions = groupMap[group] || [];
                          const visiblePositions = expandedSupportGroups.value[`${wl}:${group}`] ? positions : positions.slice(0, POSITION_PREVIEW_LIMIT);
                          return (
                            <div key={group} class="support-column">
                              <div class="support-column-head">
                                <div class="fw7 fs13 text-navy">{group}</div>
                                <button class="btn-link" onClick={() => startEdit("custom-dept", group, { parent: wl })} title="แก้ไขกลุ่มงาน">✎</button>
                              </div>
                              <div class="support-position-list">
                                {visiblePositions.map((item) =>
                                  <div key={item} class="structure-item group">
                                    <span class="fs12 fw6 text-gray-700">{item}</span>
                                    <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: '12px' }} onClick={() => startEdit("custom-group-pos", item, { parent: group, workName: wl })}>✎</button>
                                  </div>
                                )}
                                {positions.length === 0 && <div class="structure-empty">ยังไม่มีตำแหน่งในกลุ่มงานนี้</div>}
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
                              <button class="support-add" onClick={() => {setAddItemData({ category: "pos", type: "", name: "", worklineName: wl, parent: group, grandparent: "" });setShowAddModal(true);}}>+ เพิ่มตำแหน่ง</button>
                            </div>
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
              </div>
            </div> :
          activeTab.value === "dept" ?
          <div class="structure-pane">
              <div class="structure-heading">กลุ่มงาน</div>
              <div class="structure-stack">
                {worklines.length === 0 &&
                  <div class="structure-empty">ยังไม่มีข้อมูลสายงานและกลุ่มงาน</div>
                }
                {worklines.includes("สายงานบริหาร") &&
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
                    {adminDepts.length === 0 && <div class="structure-empty">ยังไม่มีกลุ่มงานในสายงานบริหาร</div>}
                  </div>
                </section>
                }
                {worklines.includes("สายวิชาการ") &&
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
                    {academicDepts.length === 0 && <div class="structure-empty">ยังไม่มีกลุ่มงานในสายวิชาการ</div>}
                  </div>
                </section>
                }
                {worklines.includes("สายสนับสนุน") && supportDepts.map((item) =>
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
                {worklines.includes("สายสนับสนุน") && supportDepts.length === 0 &&
                  <div class="structure-empty">ยังไม่มีกลุ่มงานในสายสนับสนุน</div>
                }
              </div>
            </div> :

          activeTab.value === "pos" ?
          (() => {
            const selectedWorkline = activeLevelWorkline.value && worklines.includes(activeLevelWorkline.value)
              ? activeLevelWorkline.value
              : worklines[0] || "";
            const directLevels = selectedWorkline ? levelItemsForWorkline(selectedWorkline) : [];
            const scopedGroupNames = selectedWorkline
              ? Object.keys(groupMapForWorkline(selectedWorkline)).sort((first, second) => levelNameCollator.compare(first, second))
              : [];
            const sortedDirectLevels = sortedLevelItemsForWorkline(selectedWorkline, directLevels);
            const matrixRows = sortedDirectLevels.map((name) => ({ name }));
            const configuredCount = directLevels.filter((levelName) => expectedLevelForItem(selectedWorkline, levelName)).length;

            return (
              <div class="structure-pane level-redesign">
                <div class="level-page-head">
	                  <div>
	                    <div class="structure-heading">ระดับตำแหน่ง</div>
	                    <div class="level-page-copy">
	                      เลือกสายงานทางซ้าย แล้วตั้งค่าความคาดหวังแต่ละของระดับตำแหน่ง
	                    </div>
	                  </div>
                  {selectedWorkline &&
                    <button class="btn btn-p btn-sm" onClick={() => {setAddItemData({ category: "rank", type: "", name: "", expectedLevel: "1", worklineName: selectedWorkline, parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มระดับตำแหน่ง</button>
                  }
                </div>

                {worklines.length === 0 ?
                  <div class="structure-empty">ยังไม่มีข้อมูลสายงาน</div> :
                  <div class="level-layout">
                    <aside class="level-sidebar">
                      <div class="level-sidebar-title">สายงาน</div>
                      <div class="level-nav-list">
                        {worklines.map((wl) => {
                          const isActive = wl === selectedWorkline;
                          return (
                            <button key={wl} type="button" class={`level-nav-item ${isActive ? "active" : ""}`} onClick={() => setActiveLevelWorkline(wl)}>
                              <span>
                                <strong>{wl}</strong>
                              </span>
                            </button>
                          );
                        })}
                      </div>
                    </aside>

                    <section class="level-workspace">
                      <div class="level-workspace-head">
	                        <div>
	                          <div class="level-kicker">กำลังจัดการ</div>
	                          <div class="level-workline-title">{selectedWorkline}</div>
	                        </div>
	                      </div>

                      <div class="level-panel-grid">
                        <div class="level-panel main-level-panel">
                          <div class="level-panel-head">
                            <div>
                              <div class="level-panel-title">ระดับกลางของสายงาน</div>
                              {!directLevels.length && <div class="muted fs12">ยังไม่มีระดับกลางของสายงานนี้</div>}
                            </div>
                          </div>
                          {matrixRows.length > 0 ?
                            <div class="level-matrix compact-level-matrix" role="table">
                              <div class="level-matrix-head" role="row">
                                <div role="columnheader">ระดับตำแหน่ง</div>
                                <div role="columnheader">Expected Level</div>
                                <div role="columnheader"></div>
                              </div>
                              {matrixRows.map((row) => {
                                const expectedLevel = expectedLevelForItem(selectedWorkline, row.name);
                                return (
                                  <div key={`level-${row.name}`} class="level-matrix-row" role="row">
                                    <div class="level-name-cell" role="cell">
                                      <div class="level-name-text">{row.name}</div>
                                    </div>
                                    <div role="cell">
                                      {expectedLevel ?
                                        <span class={`level-pill level-${expectedLevel}`}>Level {expectedLevel}</span> :
                                        <span class="level-pill unset">ยังไม่ตั้งค่า</span>
                                      }
                                    </div>
                                    <div class="level-action-cell" role="cell">
                                      <button class="btn btn-s btn-sm" type="button" onClick={() => startEdit("rank", row.name, { parent: selectedWorkline })}>แก้ไข</button>
                                    </div>
                                  </div>
                                );
                              })}
                            </div> :
                            <div class="structure-empty">ยังไม่มีระดับกลางใน{selectedWorkline} กด “เพิ่มระดับตำแหน่ง” เพื่อสร้างระดับกลาง</div>
                          }
                        </div>

                        <div class="level-panel scoped-level-panel">
                          <div class="level-panel-head">
                            <div>
                              <div class="level-panel-title">ระดับเฉพาะกลุ่มงาน</div>
                              <div class="muted fs12">ใช้เมื่อแต่ละกลุ่มงานมีระดับตำแหน่งคนละชุด</div>
                            </div>
                          </div>
                          <div class="scoped-compact-list">
                            {scopedGroupNames.map((groupName) => {
                              const scopedLevels = levelItemsForScope(selectedWorkline, groupName);
                              const sortedScopedLevels = sortedLevelItemsForWorkline(selectedWorkline, scopedLevels, groupName);
                              const scopedConfiguredCount = scopedLevels.filter((levelName) => expectedLevelForItem(selectedWorkline, levelName, groupName)).length;

                              const scopeStatus = scopedLevels.length
                                ? "มีระดับเฉพาะกลุ่มงาน"
                                : directLevels.length
                                  ? "ใช้ระดับกลางของสายงาน"
                                  : "ยังไม่กำหนดระดับ";

                              return (
                                <details key={`${selectedWorkline}-${groupName}`} class="scoped-compact-card">
                                  <summary>
                                    <span class="scoped-summary-main">
                                      <span class="scoped-caret">›</span>
                                      <span>
                                        <strong>{groupName}</strong>
                                        {scopedLevels.length > 0 && <small>{scopedConfiguredCount}/{scopedLevels.length} ตั้งค่าแล้ว</small>}
                                      </span>
                                    </span>
                                    <span class="scoped-summary-actions">
                                      <span class={`scope-status ${scopedLevels.length ? "custom" : directLevels.length ? "shared" : "unset"}`}>{scopeStatus}</span>
                                      <button
                                        class="btn btn-s btn-sm"
                                        type="button"
                                        onClick={(e) => {
                                          e.preventDefault();
                                          e.stopPropagation();
                                          setAddItemData({ category: "rank", type: "", name: "", expectedLevel: "1", worklineName: selectedWorkline, parent: groupName, grandparent: "" });
                                          setShowAddModal(true);
                                        }}
                                      >
                                        + เพิ่ม
                                      </button>
                                    </span>
                                  </summary>
                                  {sortedScopedLevels.length > 0 &&
                                    <div class="scoped-mini-rows">
                                      {sortedScopedLevels.map((item) => {
                                        const expectedLevel = expectedLevelForItem(selectedWorkline, item, groupName);
                                        return (
                                          <div key={`${groupName}-${item}`} class="scoped-mini-row">
                                            <span>{item}</span>
                                            {expectedLevel ?
                                              <span class={`level-pill level-${expectedLevel}`}>Level {expectedLevel}</span> :
                                              <span class="level-pill unset">ยังไม่ตั้งค่า</span>
                                            }
                                            <button class="btn btn-s btn-sm" type="button" onClick={() => startEdit("rank", item, { worklineName: selectedWorkline, jobFamilyName: groupName })}>แก้ไข</button>
                                          </div>
                                        );
                                      })}
                                    </div>
                                  }
                                </details>
                              );
                            })}
                            {scopedGroupNames.length === 0 && <div class="structure-empty">ยังไม่มีกลุ่มงานใน{selectedWorkline}</div>}
                          </div>
                        </div>
                      </div>
                    </section>
                  </div>
                }
              </div>
            );
          })() :

          <div class="structure-pane">
              <div class="structure-heading">ประเภทสมรรถนะ</div>
              <div class="structure-stack comp-stack">
              <section class="structure-section comp-section-card">
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
              <section class="structure-section comp-section-card">
                <div class="structure-section-head">
                  <div class="fw7 fs14 text-navy">ประเภทการเรียนรู้</div>
                  <button class="btn btn-s btn-sm" onClick={() => {setAddItemData({ category: "learning", type: "1", name: "", desc: "", parent: "", grandparent: "" });setShowAddModal(true);}}>+ เพิ่มประเภทการเรียนรู้</button>
                </div>
                <div class="structure-grid">
                  {learningMethodList.value.map((item) =>
                <div key={item.key} class="structure-item group" style={{ alignItems: "flex-start", minHeight: "72px" }}>
                      <div style={{ minWidth: 0 }}>
                        <div class="fs13 fw6 text-gray-700 truncate">{item.label}</div>
                        <div class="muted fs11" style={{ marginTop: "4px" }}>{item.desc || "-"}</div>
                      </div>
                      <button class="btn-link opacity-0 group-hover:opacity-100" style={{ fontSize: "12px" }} onClick={() => startEdit("learning-method", item.key, { item })}>✎</button>
                    </div>
                )}
                  {learningMethodList.value.length === 0 && <div class="structure-empty">ยังไม่มีประเภทการเรียนรู้</div>}
                </div>
              </section>
              </div>
            </div>
          }
        </div>
      </div>

      {showAddModal.value &&
      <div class="mo">
          <div class="mo-box anim-fade-in" style={{ width: "450px" }}>
            <div class="mo-h">
              <div class="fw8">{getAddModalCopy().title}</div>
              <button class="btn btn-s btn-sm" type="button" onClick={() => setShowAddModal(false)}>✕</button>
            </div>
            <div class="mo-b">
              <div class="fg">
                <label class="lbl fw8" style={{ color: "var(--navy)" }}>{getAddModalCopy().label}</label>
                <input
                  ref={addNameInput}
                  class="inp"
                  value={addItemData.value.name}
                  onChange={(e) => setAddItemData({ ...addItemData.value, name: e.target.value })}
                  onKeydown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      saveAddItem();
                    }
                  }}
                  disabled={isSavingAddItem.value}
                  placeholder="กรอกชื่อที่ต้องการ..."
                  autoFocus
                />
              </div>
              {addItemData.value.category === "comp" &&
            <>
                <div class="fg">
                  <label class="lbl fw8" style={{ color: "var(--navy)" }}>ชื่อเต็มประเภทสมรรถนะ</label>
                  <input class="inp" value={addItemData.value.fullName} onChange={(e) => setAddItemData({ ...addItemData.value, fullName: e.target.value })} placeholder="กรอกชื่อเต็มประเภทสมรรถนะ" />
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
              {addItemData.value.category === "rank" &&
            <div class="fg">
                  <label class="lbl fw8" style={{ color: "var(--navy)" }}>เทียบเท่าระดับความชำนาญ</label>
                  <select class="sel" value={addItemData.value.expectedLevel || "1"} onChange={(e) => setAddItemData({ ...addItemData.value, expectedLevel: e.target.value })}>
                    {[1, 2, 3, 4, 5].map((level) =>
                                      <option key={level} value={String(level)}>Level {level}</option>
                    )}
                  </select>
                  <div class="level-help-text">
                    หมายเหตุ: การเลือกระดับ Level (1-5) ในส่วนนี้ ระบบจะนำไปใช้เป็น "ค่าความคาดหวังสมรรถนะเป้าหมาย (Expected Level)" พื้นฐานสำหรับบุคลากรในระดับตำแหน่งนี้โดยอัตโนมัติ
                  </div>
                </div>
            }

              <div class="add-modal-actions">
                <button class="btn btn-s" type="button" onClick={() => setShowAddModal(false)}>ยกเลิก</button>
                <button class="btn btn-p add-modal-submit" type="button" disabled={isSavingAddItem.value} onClick={saveAddItem}>
                  {isSavingAddItem.value ? "กำลังเพิ่ม..." : "เพิ่มรายการ"}
                </button>
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
                  <input class="inp" value={editFullName.value} onChange={(e) => setEditFullName(e.target.value)} placeholder="กรอกชื่อเต็มประเภทสมรรถนะ" />
                </div>
                <div class="fg">
                  <label class="lbl">รายละเอียดประเภทสมรรถนะ</label>
                  <textarea class="ta" rows={3} value={editDesc.value} onChange={(e) => setEditDesc(e.target.value)} placeholder="อธิบายความหมายและขอบเขตของประเภทสมรรถนะนี้..." />
                </div>
              </>
            }
              {(editingItem.value.type === "rank" || editingItem.value.type === "academic-rank" || editingItem.value.type === "support-rank") &&
            <div class="fg">
                <label class="lbl">เทียบเท่าระดับความชำนาญ</label>
                <select class="sel" value={editExpectedLevel.value} onChange={(e) => setEditExpectedLevel(e.target.value)}>
                  {[1, 2, 3, 4, 5].map((level) =>
                    <option key={level} value={String(level)}>Level {level}</option>
                  )}
                </select>
                <div class="level-help-text">
                  หมายเหตุ: การเลือกระดับ Level (1-5) ในส่วนนี้ ระบบจะนำไปใช้เป็น "ค่าความคาดหวังสมรรถนะเป้าหมาย (Expected Level)" พื้นฐานสำหรับบุคลากรในระดับตำแหน่งนี้โดยอัตโนมัติ
                </div>
              </div>
            }
              <div style={{ display: "flex", gap: "8px", marginTop: "24px", justifyContent: "space-between" }}>
                <button class="btn btn-r" style={{ background: '#fee2e2', color: '#ef4444', border: '1px solid #fecaca' }} onClick={deleteItem}> ลบรายการนี้</button>
                <div class="flex g8">
                  <button class="btn btn-s" onClick={() => setEditingId(null)}>ยกเลิก</button>
                  <button class="btn btn-p" onClick={saveEdit}> บันทึก</button>
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
        .structure-shell.workline-shell,
        .structure-shell.level-shell,
        .structure-shell.comp-shell { min-height: 0; overflow: visible; border: 0; background: transparent; }
        .structure-shell.workline-shell .structure-pane,
        .structure-shell.level-shell .structure-pane,
        .structure-shell.comp-shell .structure-pane { padding: 0; }
        .structure-pane { padding: 20px; }
        .structure-heading { margin-bottom: 14px; color: var(--text); font-size: 15px; font-weight: 800; }
        .structure-note { display: grid; gap: 4px; margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--blue-md); border-radius: 8px; background: var(--blue-lt); color: var(--text2); font-size: 12px; line-height: 1.55; }
        .add-modal-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 24px; width: 100%; }
        .add-modal-actions .btn { flex: 0 0 auto; min-width: 96px; }
        .add-modal-submit { display: inline-flex !important; align-items: center; justify-content: center; min-width: 112px; background: var(--blue) !important; color: #fff !important; border-color: var(--blue) !important; opacity: 1 !important; visibility: visible !important; }
        .structure-note b { color: var(--blue); font-size: 13px; }
        .structure-stack { display: grid; gap: 0; }
        .structure-section { padding: 16px 0; border-top: 1px solid var(--border); }
        .structure-section:first-child { padding-top: 0; border-top: 0; }
        .workline-stack { gap: 16px; }
        .workline-section { padding: 18px; border: 1px solid var(--border); border-radius: var(--r); background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .workline-stack .workline-section:first-child { padding-top: 18px; border-top: 1px solid var(--border); }
        .workline-section .structure-section-head { margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #e5edf7; }
        .level-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .level-page-copy { color: var(--text3); font-size: 13px; line-height: 1.55; max-width: 720px; }
        .level-redesign .level-page-head { margin-bottom: 14px; }
        .level-layout { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 14px; align-items: start; }
        .level-sidebar { position: sticky; top: 12px; overflow: hidden; border: 1px solid #dbe5f1; border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .level-sidebar-title { padding: 12px 14px; border-bottom: 1px solid #edf2f8; background: #fbfdff; color: var(--text3); font-size: 11px; font-weight: 900; letter-spacing: 0; text-transform: uppercase; }
        .level-nav-list { display: grid; gap: 8px; padding: 10px; }
        .level-nav-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; min-height: 48px; padding: 10px 12px; border: 1px solid #e2ebf7; border-radius: 8px; background: #fff; color: var(--text2); cursor: pointer; text-align: left; transition: background 160ms ease, border-color 160ms ease, color 160ms ease, box-shadow 160ms ease; }
        .level-nav-item:hover { border-color: #bdd1f3; background: #fbfdff; }
        .level-nav-item.active { border-color: #9fc0fb; background: #fff; color: var(--navy); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08), 0 1px 3px rgba(15, 23, 42, 0.08); }
        .level-nav-item strong { display: block; color: inherit; font-size: 13px; font-weight: 850; line-height: 1.3; }
        .level-nav-item small { display: block; margin-top: 3px; color: var(--text3); font-size: 11px; font-weight: 750; line-height: 1.25; }
        .level-workspace { display: grid; gap: 12px; min-width: 0; }
        .level-workspace-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1px solid #dbe5f1; border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .level-kicker { color: var(--text3); font-size: 11px; font-weight: 850; text-transform: uppercase; }
        .level-workline-title { margin-top: 2px; color: var(--navy); font-size: 20px; font-weight: 900; line-height: 1.2; }
        .level-panel-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(340px, 0.85fr); gap: 12px; align-items: start; }
        .level-panel { min-width: 0; border: 1px solid #dbe5f1; border-radius: 10px; background: #fff; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .level-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 62px; padding: 13px 15px; border-bottom: 1px solid #edf2f8; background: #fbfdff; }
        .level-panel-title { color: var(--navy); font-size: 14px; font-weight: 900; }
        .compact-level-matrix { max-height: 520px; border: 0; border-radius: 0; }
        .compact-level-matrix .level-matrix-head { border-top: 0; }
        .compact-level-matrix .level-matrix-head,
        .compact-level-matrix .level-matrix-row { grid-template-columns: minmax(0, 1fr) 112px 68px; gap: 8px; }
        .compact-level-matrix .level-matrix-head { padding: 0 10px; }
        .compact-level-matrix .level-matrix-row { padding: 10px; }
        .compact-level-matrix .level-name-text { white-space: normal; overflow-wrap: anywhere; }
        .compact-level-matrix .level-action-cell .btn { min-width: 0; padding-left: 10px; padding-right: 10px; }
        .level-row-sub { margin-top: 3px; color: var(--text3); font-size: 11px; font-weight: 650; }
        .scoped-level-panel { max-height: 626px; display: flex; flex-direction: column; }
        .scoped-compact-list { display: grid; gap: 10px; padding: 10px; overflow: auto; background: #fbfdff; }
        .scoped-compact-card { overflow: hidden; border: 1px solid #dbe5f1; border-radius: 9px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.035); }
        .scoped-compact-card[open] { border-color: #c8d9f2; background: #fff; }
        .scoped-compact-card summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 10px; min-height: 54px; padding: 9px 11px; cursor: pointer; list-style: none; background: #f8fbff; }
        .scoped-compact-card summary::-webkit-details-marker { display: none; }
        .scoped-summary-main { display: grid; grid-template-columns: 18px minmax(0, 1fr); align-items: center; gap: 8px; min-width: 0; }
        .scoped-summary-actions { display: inline-flex; align-items: center; justify-content: flex-end; gap: 8px; min-width: 0; }
        .scoped-caret { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 5px; color: var(--text3); font-size: 18px; line-height: 1; transform: rotate(0deg); transition: transform 160ms ease, background 160ms ease; }
        .scoped-compact-card[open] .scoped-caret { background: #eef4ff; color: var(--blue); transform: rotate(90deg); }
        .scoped-compact-card summary strong { display: block; overflow: hidden; color: var(--text); font-size: 13px; font-weight: 850; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
        .scoped-compact-card summary small { display: block; margin-top: 1px; color: var(--text3); font-size: 11px; font-weight: 750; line-height: 1.2; }
        .scope-status { display: inline-flex; align-items: center; justify-content: center; min-height: 26px; max-width: 176px; padding: 5px 9px; border: 1px solid #dbe5f1; border-radius: 999px; background: #f8fafc; color: var(--text3); font-size: 11px; font-weight: 850; line-height: 1.2; white-space: nowrap; }
        .scope-status.custom { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
        .scope-status.shared { border-color: #d1fae5; background: #ecfdf5; color: #047857; }
        .scope-status.unset { border-style: dashed; background: #fbfdff; color: var(--text3); }
        .scoped-mini-rows { display: grid; border-top: 1px solid #e5edf7; background: #fff; }
        .scoped-mini-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 8px; min-height: 44px; margin: 0 8px; padding: 7px 4px 7px 28px; border-top: 1px solid #f2f6fb; }
        .scoped-mini-row:first-child { border-top: 0; }
        .scoped-mini-row span:first-child { overflow: hidden; color: var(--text); font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .level-stack,
        .comp-stack { gap: 14px; }
        .level-section-card,
        .comp-section-card { padding: 18px; border: 1px solid var(--border); border-radius: var(--r); background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .level-stack .level-section-card:first-child,
        .comp-stack .comp-section-card:first-child { padding-top: 18px; border-top: 1px solid var(--border); }
        .level-section-card .structure-section-head,
        .comp-section-card .structure-section-head { margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #e5edf7; }
        .structure-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .structure-grid { display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); }
        .structure-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 40px; padding: 9px 11px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); overflow: hidden; }
        .level-auto-note { display: grid; gap: 4px; margin-bottom: 22px; padding: 14px 16px; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff; }
        .level-section { padding: 18px 0; border-top: 1px solid var(--border); }
        .level-section:first-of-type { border-top: 0; }
        .level-head { margin-bottom: 12px; }
        .level-matrix { max-height: 430px; overflow-x: hidden; overflow-y: auto; border: 1px solid #dbe5f1; border-radius: 8px; background: #fff; }
        .level-matrix-head,
        .level-matrix-row { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(150px, 220px) minmax(104px, 174px); align-items: center; gap: 14px; }
        .level-matrix-head { position: sticky; top: 0; z-index: 1; min-height: 38px; padding: 0 14px; border-bottom: 1px solid #eef2f7; background: #f8fafc; color: var(--text3); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .level-matrix-row { min-height: 58px; padding: 10px 14px; border-top: 1px solid #eef2f7; }
        .level-matrix-row:hover { background: #fbfdff; }
        .level-name-cell { min-width: 0; }
        .level-name-text { overflow: hidden; color: var(--text); font-size: 13px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .level-action-cell { display: flex; justify-content: flex-end; }
        .level-fallback-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
        .level-danger-btn { color: #dc2626 !important; border-color: #fecaca !important; background: #fff7f7 !important; }
        .level-danger-btn:hover { background: #fee2e2 !important; }
        .level-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 76px; border: 1px solid #d7e3f2; border-radius: 999px; background: #f8fafc; color: var(--text2); font-size: 12px; font-weight: 800; line-height: 1; padding: 8px 10px; white-space: nowrap; }
        .level-pill.level-1 { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }
        .level-pill.level-2 { border-color: #bae6fd; background: #f0f9ff; color: #0369a1; }
        .level-pill.level-3 { border-color: #99f6e4; background: #f0fdfa; color: #0f766e; }
        .level-pill.level-4 { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }
        .level-pill.level-5 { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
        .level-pill.unset { border-style: dashed; background: #fbfdff; color: var(--text3); }
        .fallback-matrix { border-style: dashed; background: #fbfdff; }
        .fallback-row { background: #fbfdff; }
        .scoped-level-block { margin-top: 16px; padding-top: 14px; border-top: 1px solid #eef2f7; }
        .scoped-level-title { margin-bottom: 10px; color: var(--text2); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .scoped-level-stack { display: grid; gap: 12px; }
        .scoped-level-card { padding: 12px; border: 1px solid #e5edf7; border-radius: 8px; background: #fbfdff; }
        .scoped-level-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .scoped-matrix { max-height: 300px; background: #fff; }
        .level-help-text { margin-top: 7px; border: 1px solid #dbeafe; border-radius: 7px; background: #eff6ff; color: var(--text2); font-size: 12px; line-height: 1.55; padding: 9px 10px; }
        .level-item.fallback { border-style: dashed; background: #fbfdff; color: var(--text3); }
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
        @media (max-width: 1180px) {
          .level-layout { grid-template-columns: 220px minmax(0, 1fr); }
          .level-panel-grid { grid-template-columns: 1fr; }
          .scoped-level-panel { max-height: 520px; }
        }
        @media (max-width: 840px) {
          .level-layout { grid-template-columns: 1fr; }
          .level-sidebar { position: static; }
          .level-nav-list { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
          .level-workspace-head { align-items: flex-start; flex-direction: column; }
        }
        @media (max-width: 640px) {
          .structure-pane { padding: 16px; }
          .structure-tabs { width: 100%; }
          .structure-section-head { align-items: flex-start; flex-direction: column; }
          .level-page-head { display: block; }
          .level-page-head .btn { margin-top: 12px; width: 100%; }
          .level-matrix { max-height: 420px; border: 0; background: transparent; }
          .level-matrix-head { display: none; }
          .level-matrix-row { grid-template-columns: 1fr; gap: 9px; margin-bottom: 10px; border: 1px solid #dbe5f1; border-radius: 8px; background: #fff; }
          .level-action-cell { justify-content: flex-start; }
          .scoped-compact-card summary { grid-template-columns: 1fr; }
          .scoped-summary-actions { justify-content: flex-start; flex-wrap: wrap; padding-left: 26px; }
          .scoped-mini-row { grid-template-columns: 1fr; justify-items: start; }
          .scoped-level-head { align-items: flex-start; flex-direction: column; }
        }
      `}</style>
    </>;} });



export default AdminOrgStructure;
</script>
