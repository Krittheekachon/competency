<template>
  <div class="flex ic jb mb20">
    <div>
      <div class="sec-t">จัดการโครงสร้างองค์กร </div>
      <div class="sec-s">
        กำหนดกลุ่มงานและสายการบังคับบัญชา
      </div>
    </div>
    <div class="flex g8">
      <button
        class="btn btn-s"
        :class="{ 'btn-p': viewModel === 'hierarchy' }"
        style="border-radius: 12px"
        @click="setHierarchyView"
      >
         สายการบังคับบัญชา
      </button>
      <button
        class="btn btn-s"
        :class="{ 'btn-p': viewModel === 'chain' }"
        style="border-radius: 12px"
        @click="setChainView"
      >
        จัดการลำดับการบังคับบัญชา
      </button>
    </div>
  </div>

  <div v-if="viewModel === 'dept'" class="org-layout">
    <div class="org-side">
      <div class="p12 org-search-box">
        <input
          v-model="deptSearch"
          class="inp inp-sm org-search-input"
          placeholder=" ค้นหาสังกัด/กลุ่มงาน..."
        />
      </div>

      <div class="org-side-h flex ic jb">
        <span>กลุ่มงาน</span>
        <div class="filter-wrap">
          <button
            class="btn btn-xs filter-btn"
            :class="filterType !== ALL_WORKLINES ? 'btn-p' : 'btn-s'"
            @click="showFilter = !showFilter"
          >
             {{ filterType }}
          </button>

          <div v-if="showFilter" class="filter-menu">
            <div
              v-for="workline in filterOptions"
              :key="workline"
              class="filter-opt"
              :class="{ active: filterType === workline }"
              @click="selectFilter(workline)"
            >
              {{ workline }}
            </div>
          </div>
          <div v-if="showFilter" class="filter-backdrop" @click="showFilter = false" />
        </div>
      </div>

      <div class="org-side-list">
        <div
          v-for="dept in filteredDepts"
          :key="dept"
          class="org-side-item"
          :class="{ active: selectedDept === dept && !isSearchActive }"
          @click="selectDept(dept)"
        >
          <span class="dept-n">{{ dept }}</span>
          <span class="dept-c">{{ deptUserCount(dept) }}</span>
        </div>

        <div v-if="filteredDepts.length === 0" class="empty-side">
          <div class="empty-icon-sm"></div>
          ไม่พบข้อมูลในหมวดนี้
        </div>
      </div>
    </div>

    <div class="org-main">
      <div class="card mb16 p12 flex ic g12">
        <div class="muted fs16"></div>
        <input
          v-model="search"
          class="inp org-main-search"
          placeholder="ค้นหาชื่อบุคลากร (ค้นหาข้ามกลุ่มงานได้ที่นี่)..."
        />
        <button v-if="search" class="btn btn-s btn-xs" @click="search = ''">ล้าง</button>
      </div>

      <div v-if="selectedDept || isSearchActive" class="card org-transparent-card">
        <div class="flex ic jb mb12">
          <div class="fw8 fs16 org-heading">
            {{ isSearchActive ? 'ผลการค้นหา' : selectedDept }}
            <span class="muted fw4 fs13 ml8">({{ listUsers.length }} คน)</span>
          </div>
        </div>

        <div class="card overflow-hidden">
          <div class="table-scroll">
            <table class="tbl tbl-clean tbl-org">
              <thead>
                <tr>
                  <th>บุคลากร</th>
                  <th>สายงาน</th>
                  <th style="min-width: 200px">กลุ่มงาน / สังกัด</th>
                  <th>ตำแหน่ง</th>
                  <th>ผู้ประเมินคนที่ 1 (หัวหน้างาน)</th>
                  <th>ผู้ประเมินคนที่ 2 (ผู้บังคับบัญชา)</th>
                  <th>ผู้ประเมินคนที่ 3 (คณบดี)</th>
                  <th>บทบาท</th>
                  <th style="width: 130px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(user, index) in listUsers" :key="user.sso || index">
                  <td style="min-width: 200px">
                    <div class="flex ic g10">
                      <div class="av s36 user-avatar">{{ avatarInitial(user) }}</div>
                      <div class="flex col">
                        <span class="fw7 fs14 user-name">{{ user.t }}{{ user.n }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="b workline-badge" :class="user.w === 'สายวิชาการ' ? 'bb' : 'bg'">
                      {{ user.w }}
                    </span>
                  </td>
                  <td>
                    <div
                      class="fs12 fw6 text-gray-700 whitespace-nowrap overflow-hidden truncate dept-cell"
                      :title="user.d || ''"
                    >
                      {{ formatDept(user.d) }}
                    </div>
                  </td>
                  <td style="min-width: 150px; max-width: 180px">
                    <div
                      class="fw6 fs13 whitespace-nowrap overflow-hidden truncate position-cell"
                      :title="user.p || ''"
                    >
                      {{ user.p || '—' }}
                    </div>
                    <div
                      class="muted fs11 whitespace-nowrap overflow-hidden truncate level-cell"
                      :title="getDisplayLevel(user) || ''"
                    >
                      {{ getDisplayLevel(user) || '—' }}
                    </div>
                  </td>
                  <td style="min-width: 140px">
                    <div v-if="user.sup" class="flex ic g6">
                      <span class="fs11 evaluator-icon"></span>
                      <span class="fs12 evaluator-name">{{ user.sup }}</span>
                    </div>
                    <span v-else class="fs11 muted">—</span>
                  </td>
                  <td style="min-width: 140px">
                    <div v-if="user.evaluator2" class="flex ic g6">
                      <span class="fs11 evaluator-icon"></span>
                      <span class="fs12 evaluator-name">{{ user.evaluator2 }}</span>
                    </div>
                    <span v-else class="fs11 muted">—</span>
                  </td>
                  <td style="min-width: 140px">
                    <div v-if="user.evaluator3" class="flex ic g6">
                      <span class="fs11 evaluator-icon"></span>
                      <span class="fs12 evaluator-name">{{ user.evaluator3 }}</span>
                    </div>
                    <span v-else class="fs11 muted">—</span>
                  </td>
                  <td>
                    <span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
                      {{ roleBadge(user.r).label }}
                    </span>
                  </td>
                  <td>
                    <button class="btn btn-s btn-xs w-full edit-user-btn" @click="openModal('modal-user', user)">
                      แก้ไขผู้ใช้
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="listUsers.length === 0" class="empty-results">
          <div class="empty-icon"></div>
          {{ isSearchActive ? 'ไม่พบบุคลากรที่ค้นหา' : 'กลุ่มงานนี้ยังไม่มีบุคลากร' }}
        </div>
      </div>

      <div v-else class="card no-dept-card">
        <div class="no-dept-content">
          <div class="no-dept-icon"></div>
          <div class="fw7 fs16 mb4 no-dept-title">ยังไม่ได้เลือกกลุ่มงาน</div>
          <div class="muted fs13 no-dept-text">
            กรุณาเลือกกลุ่มงานจากรายการด้านซ้ายมือ หรือค้นหาชื่อบุคลากรด้านบน
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-else-if="viewModel === 'hierarchy'" class="anim-fade-in">
    <section class="hierarchy-shell">
      <div class="hierarchy-path">
        <button
          class="path-chip"
          :class="{ active: drillPath.length === 0 }"
          type="button"
          @click="popDrillPath(-1)"
        >
          คณะวิศวกรรมศาสตร์
        </button>
        <template v-for="(item, index) in drillPath" :key="`${item.sso || item.n}-${index}`">
          <span class="path-separator">/</span>
          <button
            class="path-chip"
            :class="{ active: index === drillPath.length - 1 }"
            type="button"
            @click="popDrillPath(index)"
          >
            {{ item.n }}
          </button>
        </template>
      </div>

      <div class="hierarchy-filterbar">
        <div class="filter-field">
          <label>สายงาน</label>
          <select v-model="hierarchyWorklineFilter" class="sel">
            <option :value="ALL_WORKLINES">ทุกสายงาน</option>
            <option v-for="workline in props.worklines" :key="workline" :value="workline">
              {{ workline }}
            </option>
          </select>
        </div>
        <div class="filter-field">
          <label>กลุ่มงาน</label>
          <select v-model="hierarchyGroupFilter" class="sel">
            <option v-for="group in hierarchyGroupOptions" :key="group" :value="group">
              {{ group }}
            </option>
          </select>
        </div>
        <div class="filter-field">
          <label>ตำแหน่ง</label>
          <select v-model="hierarchyPositionFilter" class="sel">
            <option v-for="position in hierarchyPositionOptions" :key="position" :value="position">
              {{ position }}
            </option>
          </select>
        </div>
      </div>

      <div class="hierarchy-panel">
        <div class="hierarchy-list-head">
          <span>บุคลากร</span>
          <span>ตำแหน่งหลัก</span>
          <span>บทบาท / ผู้ประเมิน</span>
          <span></span>
        </div>

        <div v-if="currentHierarchyUsers.length" class="hierarchy-list">
          <article
            v-for="(user, index) in currentHierarchyUsers"
            :key="user.sso || index"
            class="hierarchy-row"
            :class="{ drillable: hasSubordinates(user) }"
            @click="pushDrillPath(user)"
          >
            <div class="person-cell-main">
              <div class="av hierarchy-avatar">{{ avatarInitial(user) }}</div>
              <div class="person-copy">
                <div class="hierarchy-name">{{ user.t }}{{ user.n }}</div>
                <div class="hierarchy-dept" :title="user.d || ''">{{ formatDept(user.d) }}</div>
              </div>
            </div>

            <div class="hierarchy-position-block">
              <strong :title="user.p || ''">{{ user.p || '—' }}</strong>
              <span :title="getDisplayLevel(user) || ''">{{ getDisplayLevel(user) || '—' }}</span>
            </div>

            <div class="hierarchy-role-block">
              <div class="role-pill-row">
                <span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
                  {{ roleBadge(user.r).label }}
                </span>
                <span v-if="hasSubordinates(user)" class="badge-sub">
                  ต้องประเมิน {{ subordinateCount(user) }} คน
                </span>
              </div>
              <div class="evaluator-summary">{{ evaluatorLine(user) }}</div>
            </div>

            <div class="hierarchy-actions" @click.stop>
              <button class="btn btn-s btn-xs" type="button" @click="openModal('modal-user', user)">แก้ไข</button>
              <button
                v-if="hasSubordinates(user)"
                class="drill-button"
                type="button"
                @click="pushDrillPath(user)"
              >
                ดูผู้รับการประเมิน
              </button>
            </div>
          </article>
        </div>

        <div v-else class="empty-hierarchy">
          <div class="empty-hierarchy-title">ยังไม่มีผู้รับการประเมิน</div>
          <div class="empty-hierarchy-text">
            {{ drillPath.length
              ? `ไม่พบผู้รับการประเมินของ ${drillPath[drillPath.length - 1]?.n}`
              : 'ไม่พบผู้ที่ต้องประเมินบุคลากรตามเงื่อนไขนี้' }}
          </div>
          <button class="btn btn-s mt24" @click="popDrillPath(drillPath.length - 2)">ย้อนกลับหนึ่งระดับ</button>
        </div>
      </div>
    </section>
  </div>

  <div v-else class="chain-page anim-fade-in">
    <section class="chain-section">
      <div class="chain-section-head">
        <div>
          <div class="chain-title">ลำดับการบังคับบัญชาเริ่มต้น</div>
          <div class="chain-sub">กำหนดลำดับผู้บังคับบัญชามาตรฐานของคณะ สำหรับใช้ในการประเมินสมรรถนะ แผนพัฒนารายบุคคล (IDP) และกระบวนการอนุมัติที่เกี่ยวข้อง</div>
        </div>
        <div class="chain-actions">
          <button class="btn btn-s btn-sm" type="button" @click="openDefaultChainModal">แก้ไขค่าเริ่มต้น</button>
          <button class="btn btn-p btn-sm" type="button" @click="applyDefaultToAll">ใช้กับทุกสายงาน</button>
        </div>
      </div>

      <div class="chain-visual default-chain hierarchy-visual">
        <template v-for="(role, index) in authorityDefaultChain" :key="role">
          <div class="hierarchy-node-wrap">
            <div class="chain-node hierarchy-node" :class="{ top: index === 0, base: index === authorityDefaultChain.length - 1 }">
              <span class="chain-node-kicker">{{ hierarchyRoleLabel(index, authorityDefaultChain.length) }}</span>
              <span>{{ role }}</span>
            </div>
            <div v-if="index < authorityDefaultChain.length - 1" class="hierarchy-connector"></div>
          </div>
        </template>
      </div>
    </section>

    <section class="chain-section">
      <div class="chain-section-head">
        <div>
          <div class="chain-title">กำหนดลำดับเฉพาะหน่วยงาน</div>
          <div class="chain-sub">หากไม่ได้กำหนดเอง ระบบจะใช้ลำดับการบังคับบัญชาเริ่มต้นของคณะ</div>
        </div>
      </div>

      <div class="chain-table-wrap">
        <table class="tbl chain-table">
          <thead>
            <tr>
              <th>ฝ่าย/งาน</th>
              <th>ลำดับการบังคับบัญชา</th>
              <th>สถานะ</th>
              <th style="width: 180px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="unit in chainUnits" :key="unit.name">
              <td class="fw7 text-navy">{{ unit.name }}</td>
              <td>
                <div class="mini-chain">
                  <template v-for="(role, index) in toAuthorityOrder(unit.chain)" :key="`${unit.name}-${role}`">
                    <span class="mini-node" :class="{ top: index === 0, base: index === unit.chain.length - 1 }">{{ role }}</span>
                    <span v-if="index < unit.chain.length - 1" class="mini-arrow">&gt;</span>
                  </template>
                </div>
              </td>
              <td>
                <span class="chain-badge" :class="unit.isOverride ? 'custom' : 'default'">
                  {{ unit.isOverride ? 'กำหนดเอง' : 'ใช้ค่าเริ่มต้น' }}
                </span>
              </td>
              <td>
                <div class="chain-row-actions">
                  <button class="btn btn-s btn-xs" type="button" @click="openUnitChainModal(unit.name)">แก้ไข</button>
                  <button
                    v-if="unit.isOverride"
                    class="btn btn-r btn-xs"
                    type="button"
                    @click="resetUnitChain(unit.name)"
                  >
                    รีเซ็ตกลับค่าเริ่มต้น
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="chainUnits.length === 0" class="chain-empty">ยังไม่มีฝ่าย/งานในระบบ</div>
      </div>
    </section>

    <div v-if="chainModal.open" class="mo">
      <div class="mo-box chain-modal">
        <div class="mo-h">
          <div>
            <div class="fw8">{{ chainModal.scope === 'default' ? 'แก้ไขค่าเริ่มต้น' : `กำหนดเฉพาะ ${chainModal.unitName}` }}</div>
            <div class="muted fs12">
              ระบบจะใช้ลำดับนี้เพื่อค้นหาผู้บังคับบัญชาที่เกี่ยวข้องกับการประเมินและการอนุมัติ
            </div>
            <div class="muted fs12">
              {{ chainModal.scope === 'default'
                ? 'เลือกบทบาทที่ต้องการให้ปรากฏในลำดับการบังคับบัญชาเริ่มต้น'
                : 'เลือกบทบาทที่ต้องการให้ใช้กับหน่วยงานนี้เท่านั้น' }}
            </div>
          </div>
          <button class="btn btn-s btn-sm" type="button" @click="closeChainModal">× ปิด</button>
        </div>
        <div class="mo-b">
          <div class="chain-editor">
            <div v-for="role in ROLE_NODE_OPTIONS" :key="role" class="chain-edit-row">
              <div class="chain-edit-node">
                <div class="fw8">{{ role }}</div>
                <div class="muted fs12">{{ roleHelpText(role) }}</div>
              </div>
              <label class="chain-toggle">
                <input
                  type="checkbox"
                  :checked="chainModal.draft.includes(role)"
                  :disabled="role === 'บุคลากร'"
                  @change="toggleDraftRole(role)"
                />
                <span>{{ chainModal.draft.includes(role) ? 'รวมในลำดับการบังคับบัญชา' : 'ไม่รวมในลำดับการบังคับบัญชา' }}</span>
              </label>
            </div>
          </div>

          <div class="modal-actions">
            <button class="btn btn-s modal-action-btn" type="button" @click="closeChainModal">ยกเลิก</button>
            <button class="btn btn-p modal-action-btn modal-save-btn" type="button" @click="saveChainModal">
              {{ chainModal.scope === 'default' ? 'บันทึกค่าเริ่มต้น' : 'บันทึกการตั้งค่า' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

type User = {
  db_id?: number | null;
  sso?: string;
  t?: string;
  n: string;
  w?: string;
  d?: string;
  p?: string;
  l?: string;
  r?: string;
  sup?: string;
  evaluator2?: string;
  evaluator3?: string;
  supervisor_id_1?: number | null;
  supervisor_id_2?: number | null;
  supervisor_id_3?: number | null;
  act?: boolean;
};

type RoleBadge = {
  label: string;
  className: string;
  style?: Record<string, string>;
};

const props = defineProps<{
  openModal: (type: string, data?: unknown) => void;
  users: User[];
  setUsers?: unknown;
  academicDepts: string[];
  supportDepts: string[];
  worklines: string[];
}>();

const ALL_WORKLINES = 'ทั้งหมด';
const ALL_GROUPS = 'ทุกกลุ่มงาน';
const ALL_POSITIONS = 'ทุกตำแหน่ง';

const viewModel = ref<'dept' | 'hierarchy' | 'chain'>('hierarchy');
const showFilter = ref(false);
const filterType = ref(props.worklines[0] || 'สายวิชาการ');
const search = ref('');
const deptSearch = ref('');
const selectedDept = ref('');
const hierarchyWorklineFilter = ref(ALL_WORKLINES);
const hierarchyGroupFilter = ref(ALL_GROUPS);
const hierarchyPositionFilter = ref(ALL_POSITIONS);
const drillPath = ref<User[]>([]);
const ROLE_NODE_OPTIONS = ['บุคลากร', 'หัวหน้างาน', 'หัวหน้าฝ่าย', 'คณบดี'];
const defaultChain = ref<string[]>(['บุคลากร', 'หัวหน้างาน', 'หัวหน้าฝ่าย', 'คณบดี']);
const chainOverrides = ref<Record<string, string[]>>({});
const chainModal = ref<{
  open: boolean;
  scope: 'default' | 'unit';
  unitName: string;
  draft: string[];
}>({
  open: false,
  scope: 'default',
  unitName: '',
  draft: [],
});

const filterOptions = computed(() => [ALL_WORKLINES, ...props.worklines]);
const isSearchActive = computed(() => search.value.trim().length > 0);

const getTopDept = (user: User) => {
  if (user.w === 'สายสนับสนุน' && user.d?.includes(' > ')) {
    return user.d.split(' > ')[0];
  }

  return user.d || '';
};

const filteredDepts = computed(() => {
  return Array.from(
    new Set([
      ...props.academicDepts,
      ...props.supportDepts,
      ...props.users.map((user) => getTopDept(user)),
    ].filter(Boolean)),
  )
    .sort((a, b) => a.localeCompare(b, 'th'))
    .filter((dept) => {
      if (deptSearch.value && !dept.toLowerCase().includes(deptSearch.value.toLowerCase())) {
        return false;
      }

      if (filterType.value === ALL_WORKLINES) {
        return true;
      }

      const hasUserOfWorkline = props.users.some((user) => getTopDept(user) === dept && user.w === filterType.value);

      if (filterType.value === 'สายวิชาการ') {
        return props.academicDepts.includes(dept) || hasUserOfWorkline;
      }

      if (filterType.value === 'สายสนับสนุน') {
        return props.supportDepts.includes(dept) || hasUserOfWorkline;
      }

      return hasUserOfWorkline;
    });
});

const chainUnitNames = computed(() => {
  return filteredDepts.value;
});

const chainUnits = computed(() => chainUnitNames.value.map((name) => {
  const override = chainOverrides.value[name];

  return {
    name,
    chain: override || defaultChain.value,
    isOverride: Boolean(override),
  };
}));

const hierarchyGroupOptions = computed(() => {
  const groups = props.users
    .filter((user) => hierarchyWorklineFilter.value === ALL_WORKLINES || user.w === hierarchyWorklineFilter.value)
    .map((user) => getTopDept(user))
    .filter(Boolean);

  return [ALL_GROUPS, ...Array.from(new Set(groups)).sort((a, b) => a.localeCompare(b, 'th'))];
});

const hierarchyPositionOptions = computed(() => {
  const positions = props.users
    .filter((user) => hierarchyWorklineFilter.value === ALL_WORKLINES || user.w === hierarchyWorklineFilter.value)
    .filter((user) => hierarchyGroupFilter.value === ALL_GROUPS || getTopDept(user) === hierarchyGroupFilter.value)
    .map((user) => user.p || '')
    .filter(Boolean);

  return [ALL_POSITIONS, ...Array.from(new Set(positions)).sort((a, b) => a.localeCompare(b, 'th'))];
});

const matchesHierarchyFilters = (user: User) => {
  const matchesWorkline = hierarchyWorklineFilter.value === ALL_WORKLINES || user.w === hierarchyWorklineFilter.value;
  const matchesGroup = hierarchyGroupFilter.value === ALL_GROUPS || getTopDept(user) === hierarchyGroupFilter.value;
  const matchesPosition = hierarchyPositionFilter.value === ALL_POSITIONS || user.p === hierarchyPositionFilter.value;

  return matchesWorkline && matchesGroup && matchesPosition;
};

const listUsers = computed(() => {
  if (isSearchActive.value) {
    const keyword = search.value.toLowerCase();

    return props.users.filter((user) => {
      return user.n.toLowerCase().includes(keyword) || user.sso?.toLowerCase().includes(keyword);
    });
  }

  return props.users.filter((user) => {
    const matchesDept = getTopDept(user) === selectedDept.value;
    const matchesWorkline = filterType.value === ALL_WORKLINES || user.w === filterType.value;

    return matchesDept && matchesWorkline;
  });
});

const currentHierarchyUsers = computed(() => {
  const scopeUsers = drillPath.value.length === 0
    ? props.users.filter(hasSubordinates)
    : subordinatesFor(drillPath.value[drillPath.value.length - 1]).map((assignment) => assignment.user);

  return scopeUsers.filter(matchesHierarchyFilters);
});

watch(hierarchyWorklineFilter, () => {
  hierarchyGroupFilter.value = ALL_GROUPS;
  hierarchyPositionFilter.value = ALL_POSITIONS;
});

watch(hierarchyGroupFilter, () => {
  hierarchyPositionFilter.value = ALL_POSITIONS;
});

watch(hierarchyGroupOptions, (options) => {
  if (!options.includes(hierarchyGroupFilter.value)) {
    hierarchyGroupFilter.value = ALL_GROUPS;
  }
});

watch(hierarchyPositionOptions, (options) => {
  if (!options.includes(hierarchyPositionFilter.value)) {
    hierarchyPositionFilter.value = ALL_POSITIONS;
  }
});

watch(
  filteredDepts,
  (depts) => {
    if (selectedDept.value && depts.includes(selectedDept.value)) {
      return;
    }

    selectedDept.value = depts[0] || '';
  },
  { immediate: true },
);

const openModal = (type: string, data?: unknown) => props.openModal(type, data);

const setHierarchyView = () => {
  viewModel.value = 'hierarchy';
  drillPath.value = [];
};

const setChainView = () => {
  viewModel.value = 'chain';
};

const selectFilter = (workline: string) => {
  filterType.value = workline;
  showFilter.value = false;
};

const selectDept = (dept: string) => {
  selectedDept.value = dept;
  search.value = '';
};

const getDisplayLevel = (user: User) => (user.w === 'สายงานบริหาร' ? user.p : user.l);

const deptUserCount = (dept: string) => {
  return props.users.filter((user) => {
    const matchesDept = getTopDept(user) === dept;
    const matchesWorkline = filterType.value === ALL_WORKLINES || user.w === filterType.value;

    return matchesDept && matchesWorkline;
  }).length;
};

const formatDept = (dept?: string) => (dept ? dept.split(' > ').join(' > ') : '—');

const avatarInitial = (user: User) => user.n?.[0] || '?';

const normalizeRole = (role?: string) => {
  if (role === 'dean') return 'manager';
  if (role === 'dept_head') return 'dept_head';
  return role || 'employee';
};

const roleBadge = (role?: string): RoleBadge => {
  const normalizedRole = normalizeRole(role);
  if (['manager_dept', 'dept_head'].includes(normalizedRole || '')) {
    return {
      label: 'หัวหน้างาน',
      className: 'bg',
      style: { background: '#fff7ed', color: '#c2410c' },
    };
  }
  if (normalizedRole === 'supervisor') {
    return {
      label: 'ผู้บังคับบัญชา',
      className: 'bg',
      style: { background: '#f0f9ff', color: '#0284c7' },
    };
  }

  switch (normalizeRole(role)) {
    case 'admin':
      return { label: 'ผู้ดูแลระบบ', className: 'bp' };
    case 'hr':
      return { label: 'งานทรัพยากรบุคคล', className: 'bb' };
    case 'manager':
      return {
        label: 'ผู้บริหารคณะ',
        className: 'bg',
        style: { background: '#e0f2fe', color: '#0369a1' },
      };
    case 'manager_dept':
    case 'dept_head':
      return {
        label: 'ผู้บังคับบัญชา',
        className: 'bg',
        style: { background: '#f0f9ff', color: '#0284c7' },
      };
    case 'supervisor':
      return {
        label: 'หัวหน้างาน',
        className: 'bg',
        style: { background: '#fff7ed', color: '#c2410c' },
      };
    default:
      return { label: 'บุคลากร', className: 'bgr' };
  }
};

const displayNameForUser = (user: User) => `${user.t || ''}${user.n || ''}`.trim();
const isAssignedEvaluator = (subordinate: User, evaluator: User) => {
  if (evaluator.db_id) {
    return [subordinate.supervisor_id_1, subordinate.supervisor_id_2, subordinate.supervisor_id_3]
      .some((id) => Number(id) === Number(evaluator.db_id));
  }

  const evaluatorNames = [evaluator.n, displayNameForUser(evaluator)].filter(Boolean);

  return [subordinate.sup, subordinate.evaluator2, subordinate.evaluator3]
    .some((name) => evaluatorNames.includes(name || ''));
};
const subordinatesFor = (user: User) => props.users
  .filter((subordinate) => subordinate !== user && isAssignedEvaluator(subordinate, user))
  .map((subordinate) => ({
    user: subordinate,
    levels: [
      Number(subordinate.supervisor_id_1) === Number(user.db_id) || subordinate.sup === user.n || subordinate.sup === displayNameForUser(user)
        ? 'ลำดับที่ 1'
        : '',
      Number(subordinate.supervisor_id_2) === Number(user.db_id) || subordinate.evaluator2 === user.n || subordinate.evaluator2 === displayNameForUser(user)
        ? 'ลำดับที่ 2'
        : '',
      Number(subordinate.supervisor_id_3) === Number(user.db_id) || subordinate.evaluator3 === user.n || subordinate.evaluator3 === displayNameForUser(user)
        ? 'ลำดับที่ 3'
        : '',
    ].filter(Boolean),
  }));
const hasSubordinates = (user: User) => subordinatesFor(user).length > 0;
const subordinateCount = (user: User) => subordinatesFor(user).length;
const evaluatorLine = (user: User) => `คนที่ 1 (หัวหน้างาน): ${user.sup || '—'} · คนที่ 2 (ผู้บังคับบัญชา): ${user.evaluator2 || '—'} · คนที่ 3 (คณบดี): ${user.evaluator3 || '—'}`;

const popDrillPath = (index: number) => {
  drillPath.value = index === -1 ? [] : drillPath.value.slice(0, index + 1);
};

const pushDrillPath = (user: User) => {
  if (hasSubordinates(user)) {
    drillPath.value = [...drillPath.value, user];
  }
};

const openDefaultChainModal = () => {
  chainModal.value = {
    open: true,
    scope: 'default',
    unitName: '',
    draft: ensureRequiredChainRoles(defaultChain.value),
  };
};

const openUnitChainModal = (unitName: string) => {
  chainModal.value = {
    open: true,
    scope: 'unit',
    unitName,
    draft: ensureRequiredChainRoles(chainOverrides.value[unitName] || defaultChain.value),
  };
};

const closeChainModal = () => {
  chainModal.value.open = false;
};

const orderChainRoles = (roles: string[]) => ROLE_NODE_OPTIONS.filter((role) => roles.includes(role));
const ensureRequiredChainRoles = (roles: string[]) => orderChainRoles(Array.from(new Set(['บุคลากร', ...roles])));
const toAuthorityOrder = (roles: string[]) => [...orderChainRoles(roles)].reverse();
const hasRepeatedRoles = (roles: string[]) => new Set(roles).size !== roles.length;
const authorityDefaultChain = computed(() => toAuthorityOrder(defaultChain.value));

const hierarchyRoleLabel = (index: number, total: number) => {
  if (index === 0) return 'ผู้บริหารระดับคณะ';
  if (index === total - 1) return 'ฐานของหน่วยงาน';
  return 'ผู้บังคับบัญชา';
};

const toggleDraftRole = (role: string) => {
  if (role === 'บุคลากร') return;

  const enabled = chainModal.value.draft.includes(role);
  chainModal.value.draft = enabled
    ? ensureRequiredChainRoles(chainModal.value.draft.filter((item) => item !== role))
    : ensureRequiredChainRoles([...chainModal.value.draft, role]);
};

const roleHelpText = (role: string) => {
  if (role === 'บุคลากร') return 'จำเป็นต้องมีในทุกลำดับ';
  if (role === 'หัวหน้างาน') return 'ใช้เป็นผู้ประเมินหรือผู้ดูแลขั้นแรกของบุคลากร';
  if (role === 'หัวหน้าฝ่าย') return 'ใช้เป็นผู้ประเมินหรือผู้อนุมัติระดับถัดไป';
  return 'ใช้สำหรับภาพรวมคณะและการประเมินหัวหน้าฝ่าย';
};

const saveChainModal = () => {
  const nextChain = ensureRequiredChainRoles(chainModal.value.draft);
  if (nextChain.length === 0) {
    alert('ต้องมีอย่างน้อย 1 บทบาทในลำดับการบังคับบัญชา');
    return;
  }

  if (!nextChain.includes('บุคลากร')) {
    alert('ต้องมีบทบาทบุคลากรอยู่ในลำดับเสมอ');
    return;
  }

  if (hasRepeatedRoles(nextChain)) {
    alert('ลำดับการบังคับบัญชาต้องไม่ซ้ำบทบาทเดิม');
    return;
  }

  if (chainModal.value.scope === 'default') {
    defaultChain.value = nextChain;
  } else {
    chainOverrides.value = {
      ...chainOverrides.value,
      [chainModal.value.unitName]: nextChain,
    };
  }

  closeChainModal();
};

const applyDefaultToAll = () => {
  const affectedOverrides = Object.keys(chainOverrides.value).length;
  const totalUnits = chainUnitNames.value.length;
  if (!confirm(`ยืนยันการใช้ค่าเริ่มต้นกับทุกหน่วยงาน\n\nการดำเนินการนี้จะนำลำดับการบังคับบัญชาเริ่มต้นไปแทนที่ค่าที่กำหนดเองของทุกหน่วยงาน และไม่สามารถย้อนกลับได้\n\nหน่วยงานทั้งหมด: ${totalUnits} หน่วยงาน\nหน่วยงานที่มีค่ากำหนดเองและจะถูกแทนที่: ${affectedOverrides} หน่วยงาน`)) {
    return;
  }

  chainOverrides.value = {};
};

const resetUnitChain = (unitName: string) => {
  if (!confirm(`ยืนยันการรีเซ็ตกลับค่าเริ่มต้น\n\nต้องการให้ ${unitName} กลับไปใช้ลำดับการบังคับบัญชาเริ่มต้นของคณะใช่ไหม? การตั้งค่าเฉพาะของหน่วยงานนี้จะถูกยกเลิก`)) {
    return;
  }

  const next = { ...chainOverrides.value };
  delete next[unitName];
  chainOverrides.value = next;
};
</script>

<style scoped>
.org-layout {
  display: grid;
  grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
  gap: 24px;
  align-items: start;
  width: 100%;
  max-width: 100%;
}

.org-side {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: visible;
  position: sticky;
  top: 20px;
}

.org-side > :first-child {
  border-radius: 16px 16px 0 0;
}

.org-side-list {
  border-radius: 0 0 16px 16px;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}

.org-side-h {
  padding: 16px;
  background: #f8fafc;
  border-bottom: 1px solid var(--border);
  font-weight: 800;
  font-size: 13px;
  color: var(--navy);
}

.org-side-item {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: 0.1s;
}

.org-side-item:hover {
  background: #f8fafc;
}

.org-side-item.active {
  background: var(--blue-lt);
  border-right: 3px solid var(--blue);
}

.dept-n {
  font-size: 13px;
  font-weight: 600;
  color: var(--text2);
}

.org-side-item.active .dept-n {
  color: var(--blue);
  font-weight: 800;
}

.dept-c {
  font-size: 10px;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 6px;
  color: var(--text3);
  font-weight: 700;
}

.org-main {
  min-width: 0;
  max-width: 100%;
}

.org-search-box {
  border-bottom: 1px solid var(--border);
  background: #fff;
}

.org-search-input {
  font-size: 12px;
}

.filter-wrap {
  position: relative;
}

.filter-btn {
  font-size: 10px;
  padding: 4px 8px;
}

.filter-menu {
  position: absolute;
  top: 100%;
  right: 0;
  z-index: 50;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-top: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  width: 240px;
  padding: 6px;
  max-height: 300px;
  overflow-y: auto;
}

.filter-opt {
  padding: 10px 12px;
  font-size: 13px;
  cursor: pointer;
  border-radius: 8px;
  color: var(--text2);
  font-weight: 400;
}

.filter-opt.active {
  background: var(--blue-lt);
  color: var(--blue);
  font-weight: 700;
}

.filter-backdrop {
  position: fixed;
  inset: 0;
  z-index: 45;
}

.org-main-search {
  border: none;
  background: transparent;
  padding: 4px 0;
  font-size: 14px;
}

.org-transparent-card {
  border: none;
  box-shadow: none;
  background: transparent;
}

.org-heading,
.user-name,
.no-dept-title,
.hierarchy-name,
.empty-hierarchy-title {
  color: var(--navy);
}

.table-scroll {
  overflow-x: auto;
}

.user-avatar {
  background: var(--navy);
  color: #fff;
}

.workline-badge {
  font-size: 11px;
}

.dept-cell {
  max-width: 300px;
}

.position-cell,
.evaluator-name,
.hierarchy-position {
  color: var(--text2);
}

.level-cell,
.hierarchy-level {
  margin-top: 2px;
}

.evaluator-icon {
  opacity: 0.6;
}

.edit-user-btn {
  padding: 6px;
  font-size: 11px;
  border-radius: 6px;
}

.empty-side {
  padding: 32px 16px;
  text-align: center;
  font-size: 12px;
  color: var(--text3);
}

.empty-icon-sm {
  font-size: 24px;
  margin-bottom: 8px;
}

.empty-results {
  padding: 60px;
  text-align: center;
  color: var(--text3);
}

.empty-icon {
  font-size: 32px;
  margin-bottom: 12px;
}

.no-dept-card {
  display: grid;
  min-height: 400px;
  place-items: center;
  padding: 32px;
  background: #fff;
  border-style: dashed;
  border-width: 2px;
  overflow: hidden;
  text-align: center;
}

.no-dept-content {
  display: grid;
  justify-items: center;
  max-width: min(520px, 100%);
  min-width: 0;
}

.no-dept-icon {
  font-size: 48px;
  margin-bottom: 16px;
  line-height: 1;
}

.no-dept-text {
  max-width: 44ch;
  line-height: 1.65;
  overflow-wrap: anywhere;
}

.hierarchy-shell {
  display: grid;
  gap: 14px;
}

.hierarchy-path {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  overflow-x: auto;
}

.hierarchy-filterbar {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  padding: 14px;
  border: 1px solid #dbe5f1;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.filter-field {
  display: grid;
  gap: 6px;
  min-width: 0;
}

.filter-field label {
  color: var(--text3);
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.filter-field .sel {
  min-height: 40px;
  border-radius: 7px;
  font-size: 13px;
}

.path-chip {
  flex: 0 0 auto;
  border: 1px solid #dbe5f1;
  border-radius: 999px;
  background: #fff;
  color: var(--text2);
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
  min-height: 34px;
  padding: 0 13px;
}

.path-chip.active {
  border-color: #bfdbfe;
  background: #eff6ff;
  color: var(--blue);
}

.path-separator {
  color: #cbd5e1;
  font-weight: 800;
}

.hierarchy-panel {
  overflow: hidden;
  border: 1px solid #dbe5f1;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.hierarchy-list-head,
.hierarchy-row {
  display: grid;
  grid-template-columns: minmax(260px, 1.25fr) minmax(180px, 0.8fr) minmax(320px, 1.25fr) minmax(150px, auto);
  gap: 18px;
  align-items: center;
}

.hierarchy-list-head {
  min-height: 48px;
  padding: 0 22px;
  border-bottom: 1px solid #edf2f8;
  background: #fbfdff;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.hierarchy-row {
  min-height: 118px;
  padding: 18px 22px;
  border-bottom: 1px solid #eef2f7;
  transition: background 160ms ease, box-shadow 160ms ease;
}

.hierarchy-row:last-child {
  border-bottom: 0;
}

.hierarchy-row.drillable {
  cursor: pointer;
}

.hierarchy-row.drillable:hover {
  background: #fbfdff;
  box-shadow: inset 4px 0 0 var(--blue);
}

.person-cell-main {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.hierarchy-avatar {
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 50%;
  background: var(--navy);
  color: #fff;
  font-size: 14px;
  font-weight: 850;
}

.person-copy {
  min-width: 0;
}

.hierarchy-name {
  overflow: hidden;
  color: var(--navy);
  font-size: 15px;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hierarchy-dept {
  overflow: hidden;
  margin-top: 4px;
  color: var(--text3);
  font-size: 12px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hierarchy-position-block,
.hierarchy-role-block {
  min-width: 0;
}

.hierarchy-position-block strong {
  display: block;
  overflow: hidden;
  color: var(--text);
  font-size: 14px;
  font-weight: 850;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hierarchy-position-block span {
  display: block;
  overflow: hidden;
  margin-top: 5px;
  color: var(--text3);
  font-size: 12px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-pill-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.badge-sub {
  border-radius: 999px;
  background: #eff6ff;
  color: var(--blue);
  font-size: 11px;
  font-weight: 850;
  line-height: 1;
  padding: 6px 9px;
  white-space: nowrap;
}

.evaluator-summary {
  margin-top: 9px;
  color: var(--text3);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.55;
}

.hierarchy-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}

.drill-button {
  min-height: 30px;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  background: #eff6ff;
  color: var(--blue);
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
  padding: 0 10px;
}

.drill-button:hover {
  border-color: var(--blue);
  background: var(--blue);
  color: #fff;
}

.empty-hierarchy {
  display: grid;
  justify-items: center;
  padding: 96px 20px;
  color: var(--text3);
  text-align: center;
}

.empty-hierarchy-title {
  color: var(--navy);
  font-size: 18px;
  font-weight: 900;
}

.empty-hierarchy-text {
  max-width: 360px;
  margin-top: 8px;
  line-height: 1.6;
}

.overflow-hidden {
  overflow: hidden;
}

.ml8 {
  margin-left: 8px;
}

.p32 {
  padding: 32px;
}

.chain-page {
  display: grid;
  gap: 18px;
}

.chain-section {
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
}

.chain-section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.chain-title {
  color: var(--navy);
  font-size: 16px;
  font-weight: 900;
}

.chain-sub {
  margin-top: 4px;
  color: var(--text3);
  font-size: 12px;
  line-height: 1.55;
}

.chain-actions,
.chain-row-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.chain-visual {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 16px;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
}

.chain-visual.hierarchy-visual {
  display: grid;
  justify-items: center;
  gap: 0;
}

.default-chain {
  min-height: 260px;
}

.chain-node,
.chain-edit-node,
.mini-node {
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 13px;
  font-weight: 800;
}

.chain-node {
  display: grid;
  gap: 3px;
  min-width: 120px;
  padding: 12px 14px;
  text-align: center;
}

.hierarchy-node-wrap {
  display: grid;
  justify-items: center;
}

.hierarchy-node {
  min-width: min(100%, 260px);
  padding: 13px 18px;
}

.hierarchy-connector {
  width: 2px;
  height: 22px;
  background: #cbd5e1;
}

.chain-node.base,
.mini-node.base {
  border-color: var(--green-md);
  background: var(--green-bg);
  color: var(--green);
}

.chain-node.top,
.mini-node.top {
  border-color: var(--blue-md);
  background: var(--blue-lt);
  color: var(--blue);
}

.chain-node-kicker {
  color: var(--text3);
  font-size: 10px;
  font-weight: 800;
}

.chain-arrow {
  color: var(--text3);
  font-size: 14px;
  font-weight: 800;
}

.chain-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.chain-table th,
.chain-table td {
  padding: 14px 16px;
  vertical-align: middle;
}

.mini-chain {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
}

.mini-node {
  padding: 5px 8px;
  font-size: 11px;
}

.mini-arrow {
  color: #94a3b8;
  font-size: 12px;
  font-weight: 800;
}

.chain-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.chain-badge.default {
  background: #f1f5f9;
  color: #64748b;
}

.chain-badge.custom {
  background: #dbeafe;
  color: #1d4ed8;
}

.chain-empty {
  padding: 28px;
  color: var(--text3);
  font-size: 13px;
  text-align: center;
}

.chain-modal {
  width: min(560px, calc(100vw - 28px));
  border-radius: 10px;
}

.chain-editor {
  display: grid;
  gap: 8px;
}

.chain-edit-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}

.chain-edit-node {
  flex: 1;
  padding: 10px 12px;
}

.chain-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 212px;
  min-height: 38px;
  padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: #fff;
  color: var(--text2);
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}

.chain-toggle input {
  width: 16px;
  height: 16px;
  accent-color: var(--blue);
}

.chain-toggle:has(input:checked) {
  border-color: var(--blue-md);
  background: var(--blue-lt);
  color: var(--blue);
}

.chain-toggle:has(input:disabled) {
  cursor: not-allowed;
  opacity: 0.72;
}

@media (max-width: 1100px) {
  .hierarchy-list-head {
    display: none;
  }

  .hierarchy-row {
    grid-template-columns: 1fr;
    gap: 14px;
    align-items: start;
  }

  .hierarchy-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 720px) {
  .hierarchy-filterbar {
    grid-template-columns: 1fr;
  }

  .hierarchy-row {
    padding: 16px;
  }

  .person-cell-main {
    align-items: flex-start;
  }

  .hierarchy-name,
  .hierarchy-dept,
  .hierarchy-position-block strong,
  .hierarchy-position-block span {
    white-space: normal;
    overflow-wrap: anywhere;
  }

  .chain-section-head {
    flex-direction: column;
  }

  .chain-actions {
    width: 100%;
  }

  .chain-actions .btn {
    flex: 1 1 auto;
  }

  .chain-edit-row {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
