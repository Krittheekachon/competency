<template>
  <div class="flex ic jb mb20">
    <div>
      <div class="sec-t">จัดการโครงสร้างองค์กร </div>
      <div class="sec-s">
        กำหนดกลุ่มงานและสายการบังคับบัญชา ·
        {{ viewModel === 'dept' ? 'มุมมองรายกลุ่มงาน' : 'มุมมองสายงานบังคับบัญชา' }}
      </div>
    </div>
    <div class="flex g8">
      <button
        class="btn btn-s"
        :class="{ 'btn-p': viewModel === 'dept' }"
        style="border-radius: 12px"
        @click="setDeptView"
      >
         รายกลุ่มงาน
      </button>
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
    <div class="card shadow-sm overflow-hidden hierarchy-card">
      <div class="p32 hierarchy-header">
        <div class="flex ic g12 mb16 wrap">
          <div
            class="breadcrumb-item"
            :class="{ active: drillPath.length === 0 }"
            @click="popDrillPath(-1)"
          >
            <span class="breadcrumb-icon"></span> คณะวิศวกรรมศาสตร์
          </div>
          <template v-for="(item, index) in drillPath" :key="`${item.sso || item.n}-${index}`">
            <div class="breadcrumb-separator">›</div>
            <div
              class="breadcrumb-item"
              :class="{ active: index === drillPath.length - 1 }"
              @click="popDrillPath(index)"
            >
              {{ item.n }}
            </div>
          </template>
        </div>

        <div class="flex ic jb">
          <div class="fs13 fw5 hierarchy-context">
            {{
              drillPath.length === 0
                ? ' ระดับผู้บริหารคณะ'
                : ` รายชื่อผู้ที่มีหัวหน้างานเป็น: ${drillPath[drillPath.length - 1].n}`
            }}
          </div>
        </div>
      </div>

      <div class="hierarchy-body">
        <div class="table-scroll">
          <table class="tbl tbl-clean tbl-org tbl-explorer">
            <thead>
              <tr>
                <th style="padding-left: 32px; width: 40%">ชื่อ-นามสกุล / สังกัด</th>
                <th>ตำแหน่งสายงานหลัก</th>
                <th>บทบาท / ผู้ประเมิน</th>
                <th style="width: 140px; text-align: right; padding-right: 32px"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(user, index) in currentHierarchyUsers"
                :key="user.sso || index"
                :class="{ 'row-drill': hasSubordinates(user) }"
                @click="pushDrillPath(user)"
              >
                <td style="padding-left: 32px; padding-top: 24px; padding-bottom: 24px">
                  <div class="flex ic g12">
                    <div class="av s36 hierarchy-avatar">{{ avatarInitial(user) }}</div>
                    <div class="flex col">
                      <div class="flex ic g6">
                        <span class="fw8 fs16 hierarchy-name">{{ user.t }}{{ user.n }}</span>
                        <span
                          v-if="user.d"
                          class="fs13 fw5 text-gray-400 whitespace-nowrap overflow-hidden truncate hierarchy-dept"
                          :title="user.d"
                        >
                          ({{ formatDept(user.d) }})
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td style="padding-top: 24px; padding-bottom: 24px; max-width: 200px">
                  <div
                    class="fw7 fs14 whitespace-nowrap overflow-hidden truncate hierarchy-position"
                    :title="user.p || ''"
                  >
                    {{ user.p || '—' }}
                  </div>
                  <div
                    class="muted fs12 whitespace-nowrap overflow-hidden truncate hierarchy-level"
                    :title="getDisplayLevel(user) || ''"
                  >
                    {{ getDisplayLevel(user) || '—' }}
                  </div>
                </td>
                <td style="padding-top: 24px; padding-bottom: 24px">
                  <div class="flex ic g10">
                    <span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
                      {{ roleBadge(user.r).label }}
                    </span>
                    <template v-if="hasSubordinates(user)">
                      <span class="badge-sub">ผู้รับการประเมิน {{ subordinateCount(user) }} คน</span>
                      <span class="drill-arrow">›</span>
                    </template>
                  </div>
                  <div class="muted fs12 evaluator-summary">
                    <span class="fw6">คนที่ 1 (หัวหน้างาน):</span> {{ user.sup || '—' }}
                    <span v-if="user.evaluator2">
                      · <span class="fw6">คนที่ 2 (ผู้บังคับบัญชา):</span> {{ user.evaluator2 }}
                    </span>
                  </div>
                </td>
                <td
                  style="padding-right: 40px; text-align: right; padding-top: 24px; padding-bottom: 24px"
                  @click.stop
                >
                  <button class="btn-settings" title="แก้ไขผู้ใช้" @click="openModal('modal-user', user)">⚙</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="currentHierarchyUsers.length === 0" class="flex col ic jc empty-hierarchy">
          <div class="empty-hierarchy-icon"></div>
          <div class="fw8 fs18 mb6 empty-hierarchy-title">ยังไม่มีผู้รับการประเมินถัดไป</div>
          <div class="muted fs14 empty-hierarchy-text">
            ไม่พบผู้ที่มีหัวหน้างานเป็น {{ drillPath[drillPath.length - 1]?.n }}
          </div>
          <button class="btn btn-s mt24" @click="popDrillPath(drillPath.length - 2)">ย้อนกลับหนึ่งระดับ</button>
        </div>
      </div>
    </div>
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

const viewModel = ref<'dept' | 'hierarchy' | 'chain'>('dept');
const showFilter = ref(false);
const filterType = ref(props.worklines[0] || 'สายวิชาการ');
const search = ref('');
const deptSearch = ref('');
const selectedDept = ref('');
const drillPath = ref<User[]>([]);
const ROLE_NODE_OPTIONS = ['บุคลากร', 'หัวหน้างาน', 'หัวหน้าฝ่าย', 'คณบดี'];
const MOCK_CHAIN_UNITS = [
  'ฝ่ายบริหารและยุทธศาสตร์',
  'ฝ่ายวิชาการและพัฒนานักศึกษา',
  'งานบริการการศึกษา',
  'งานเทคโนโลยีสารสนเทศ',
  'งานพัสดุและอาคารสถานที่',
];
const defaultChain = ref<string[]>(['บุคลากร', 'หัวหน้างาน', 'หัวหน้าฝ่าย', 'คณบดี']);
const chainOverrides = ref<Record<string, string[]>>({
  'งานบริการการศึกษา': ['บุคลากร', 'หัวหน้าฝ่าย', 'คณบดี'],
  'งานเทคโนโลยีสารสนเทศ': ['บุคลากร', 'หัวหน้างาน', 'คณบดี'],
});
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
  const realUnits = filteredDepts.value;
  return realUnits.length ? realUnits : MOCK_CHAIN_UNITS;
});

const chainUnits = computed(() => chainUnitNames.value.map((name) => {
  const override = chainOverrides.value[name];

  return {
    name,
    chain: override || defaultChain.value,
    isOverride: Boolean(override),
  };
}));

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
  if (drillPath.value.length === 0) {
    return props.users.filter((user) => !user.sup);
  }

  const leader = drillPath.value[drillPath.value.length - 1];
  return props.users.filter((user) => user.sup === leader.n);
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

const setDeptView = () => {
  viewModel.value = 'dept';
};

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
  if (role === 'dept_head') return 'manager_dept';
  return role || 'employee';
};

const roleBadge = (role?: string): RoleBadge => {
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

const hasSubordinates = (user: User) => props.users.some((subordinate) => subordinate.sup === user.n);
const subordinateCount = (user: User) => props.users.filter((subordinate) => subordinate.sup === user.n).length;

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

.hierarchy-card {
  border-radius: 20px;
}

.hierarchy-header {
  background: #fff;
  border-bottom: 1px solid #edf2f7;
}

.hierarchy-context {
  color: var(--text3);
}

.hierarchy-body {
  min-height: 520px;
  background: #fff;
}

.breadcrumb-item {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
  font-size: 15px;
  color: var(--text3);
  border-radius: 6px;
  transition: 0.2s;
  padding: 4px 8px;
  margin-left: -8px;
}

.breadcrumb-item:hover {
  background: #f1f5f9;
  color: var(--blue);
}

.breadcrumb-item.active {
  color: var(--navy);
}

.breadcrumb-icon {
  font-size: 20px;
}

.breadcrumb-separator {
  color: #cbd5e1;
  font-weight: 200;
  font-size: 18px;
  margin: 0 2px;
}

.tbl-explorer th {
  font-size: 11px;
  text-transform: uppercase;
  color: var(--text3);
  letter-spacing: 0.1em;
  padding: 16px 12px;
  background: #fcfcfc;
  border-bottom: 1px solid #edf2f7;
}

.tbl-explorer td {
  padding: 16px 12px;
  border-bottom: 1px solid #f8fafc;
  vertical-align: middle;
}

.row-drill {
  cursor: pointer;
  transition: 0.15s;
}

.row-drill:hover td {
  background: #fcfdfe !important;
}

.hierarchy-avatar {
  background: var(--navy);
  color: #fff;
  border: none;
  font-size: 14px;
  font-weight: 700;
  border-radius: 50%;
  flex-shrink: 0;
}

.hierarchy-name {
  white-space: nowrap;
}

.hierarchy-dept {
  max-width: 240px;
}

.hierarchy-position {
  line-height: 1.3;
}

.badge-sub {
  font-size: 10px;
  font-weight: 800;
  background: #eff6ff;
  color: var(--blue);
  padding: 3px 10px;
  border-radius: 20px;
  transition: 0.2s;
}

.drill-arrow {
  font-size: 16px;
  color: #cbd5e1;
  transition: 0.2s;
}

.row-drill:hover .drill-arrow {
  color: var(--blue);
  transform: translateX(3px);
}

.row-drill:hover .badge-sub {
  background: var(--blue);
  color: #fff;
}

.evaluator-summary {
  margin-top: 8px;
}

.btn-settings {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: var(--text3);
  font-size: 14px;
  transition: 0.15s;
  cursor: pointer;
}

.btn-settings:hover {
  border-color: var(--blue);
  background: var(--blue-lt);
  color: var(--blue);
}

.empty-hierarchy {
  padding: 120px 20px;
  color: var(--text3);
}

.empty-hierarchy-icon {
  font-size: 56px;
  margin-bottom: 20px;
}

.empty-hierarchy-text {
  max-width: 300px;
  text-align: center;
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

@media (max-width: 720px) {
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
