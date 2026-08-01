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
        ลำดับการประเมิน
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
                  <th>ผู้ประเมินคนที่ 1 (หัวหน้าหน่วย)</th>
                  <th>ผู้ประเมินคนที่ 2 (หัวหน้างาน)</th>
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
                    <div v-if="evaluatorName(user, 1)" class="flex ic g6">
                      <span class="fs11 evaluator-icon"></span>
                      <span class="fs12 evaluator-name">{{ evaluatorName(user, 1) }}</span>
                    </div>
                    <span v-else class="fs11 muted">—</span>
                  </td>
                  <td style="min-width: 140px">
                    <div v-if="evaluatorName(user, 2)" class="flex ic g6">
                      <span class="fs11 evaluator-icon"></span>
                      <span class="fs12 evaluator-name">{{ evaluatorName(user, 2) }}</span>
                    </div>
                    <span v-else class="fs11 muted">—</span>
                  </td>
                  <td style="min-width: 140px">
                    <div v-if="evaluatorName(user, 3)" class="flex ic g6">
                      <span class="fs11 evaluator-icon"></span>
                      <span class="fs12 evaluator-name">{{ evaluatorName(user, 3) }}</span>
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
    <section class="chain-map-section">
      <div class="chain-map-head">
        <div>
          <div class="chain-eyebrow">Evaluation Line Overview</div>
          <div class="chain-title">ภาพรวมสายการประเมินทั้งองค์กร</div>
          <div class="chain-sub">มองเป็นเส้นจากผู้ประเมินลำดับสูงสุดไปถึงผู้รับการประเมินแต่ละคน เพื่อเห็นช่องว่างและจุดที่ต้องจัดการได้ทันที</div>
        </div>
      </div>

      <div class="hierarchy-filterbar chain-filterbar">
        <div class="filter-field chain-search-field">
          <label>ค้นหาชื่อ</label>
          <input
            v-model="chainSearch"
            class="inp"
            placeholder="พิมพ์ชื่อหรือ ID..."
          />
        </div>
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

      <div class="chain-legend">
        <span><i class="legend-dot subject"></i>ผู้รับการประเมิน</span>
        <span><i class="legend-dot evaluator"></i>ผู้ประเมินที่กำหนดแล้ว</span>
        <span><i class="legend-dot missing"></i>ยังไม่กำหนดผู้ประเมิน</span>
      </div>

      <div class="evaluation-board">
        <div class="evaluation-board-head">
          <span>ผู้รับการประเมิน</span>
          <span>ผู้ประเมินลำดับ 1</span>
          <span>ผู้ประเมินลำดับ 2</span>
          <span>ผู้ประเมินลำดับ 3</span>
        </div>

        <div v-if="evaluationLines.length" class="evaluation-lines">
          <article
            v-for="line in evaluationLines"
            :key="line.user.sso || line.user.db_id || line.user.n"
            class="evaluation-line"
            :class="{ incomplete: line.missing.length }"
          >
            <div class="evaluation-flow-line"></div>
            <div
              v-for="step in line.steps"
              :key="step.key"
              class="evaluation-node"
              :class="{ missing: !step.user, subject: step.key === 'subject' }"
            >
              <div v-if="step.user" class="node-person">
                <div class="av node-avatar">{{ avatarInitial(step.user) }}</div>
                <div class="node-copy">
                  <div class="node-role">{{ step.label }}</div>
                  <div class="node-name">{{ step.user.t }}{{ step.user.n }}</div>
                  <div class="node-meta">{{ step.user.p || '—' }}</div>
                </div>
              </div>
              <div v-else class="node-missing">
                <div class="node-role">{{ step.label }}</div>
                <div class="node-name">ไม่ผ่านการประเมินลำดับนี้</div>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="chain-empty">
          ไม่พบข้อมูลตาม filter นี้
        </div>
      </div>
    </section>

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
  supervisor_id_1?: number | null;
  supervisor_id_2?: number | null;
  supervisor_id_3?: number | null;
  reviewerSteps?: Array<{ id?: number | null; reviewer_id?: number | null; step?: number; name?: string }>;
  supervisorChain?: Array<{ id?: number | null; reviewer_id?: number | null; step?: number; name?: string }>;
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
const chainSearch = ref('');
const selectedDept = ref('');
const hierarchyWorklineFilter = ref(ALL_WORKLINES);
const hierarchyGroupFilter = ref(ALL_GROUPS);
const hierarchyPositionFilter = ref(ALL_POSITIONS);
const drillPath = ref<User[]>([]);
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

const findUserById = (id?: number | null) => {
  if (!id) return null;

  return props.users.find((user) => Number(user.db_id) === Number(id)) || null;
};

const chainScopedUsers = computed(() => {
  const keyword = chainSearch.value.trim().toLowerCase();

  return props.users
    .filter(matchesHierarchyFilters)
    .filter((user) => {
      if (!keyword) return true;

      return [
        user.n,
        `${user.t || ''}${user.n || ''}`,
        user.sso,
        user.p,
        roleBadge(user.r).label,
      ]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(keyword));
    });
});

const evaluationLines = computed(() => chainScopedUsers.value.map((user) => {
  const reviewerSteps = reviewerStepsForUser(user);
  const missing = reviewerSteps.length ? [] : ['ยังไม่ได้กำหนดลำดับ'];

  return {
    user,
    missing,
    steps: [
      { key: 'subject', label: roleBadge(user.r).label, user },
      ...reviewerSteps.map((step) => ({
        key: `level${step.step}`,
        label: `ผู้ประเมินลำดับ ${step.step}`,
        user: findUserById(step.reviewer_id),
      })),
    ],
  };
}));

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
      style: { background: '#f0f9ff', color: '#0284c7' },
    };
  }
  if (normalizedRole === 'supervisor') {
    return {
      label: 'หัวหน้าหน่วย',
      className: 'bg',
      style: { background: '#fff7ed', color: '#c2410c' },
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
        label: 'หัวหน้างาน',
        className: 'bg',
        style: { background: '#f0f9ff', color: '#0284c7' },
      };
    case 'supervisor':
      return {
        label: 'หัวหน้าหน่วย',
        className: 'bg',
        style: { background: '#fff7ed', color: '#c2410c' },
      };
    default:
      return { label: 'บุคลากร', className: 'bgr' };
  }
};

const displayNameForUser = (user: User) => `${user.t || ''}${user.n || ''}`.trim();
const reviewerStepsForUser = (user: User) => {
  const dynamicSteps = Array.isArray(user.reviewerSteps) && user.reviewerSteps.length
    ? user.reviewerSteps
    : (Array.isArray(user.supervisorChain) ? user.supervisorChain : []);

  if (dynamicSteps.length) {
    return dynamicSteps
      .map((step, index) => ({
        step: Number(step.step || index + 1),
        reviewer_id: Number(step.id || step.reviewer_id || 0),
      }))
      .filter((step) => step.step > 0 && step.reviewer_id > 0)
      .sort((left, right) => left.step - right.step);
  }

  return [user.supervisor_id_1, user.supervisor_id_2, user.supervisor_id_3]
    .map((id, index) => ({ step: index + 1, reviewer_id: Number(id || 0) }))
    .filter((step) => step.reviewer_id > 0);
};
const evaluatorName = (user: User, level: number) => {
  const id = reviewerStepsForUser(user).find((step) => step.step === level)?.reviewer_id;
  const evaluator = findUserById(id);

  return evaluator ? displayNameForUser(evaluator) : '';
};
const isAssignedEvaluator = (subordinate: User, evaluator: User) => {
  if (!evaluator.db_id) return false;

  return reviewerStepsForUser(subordinate)
    .some((step) => Number(step.reviewer_id) === Number(evaluator.db_id));
};
const subordinatesFor = (user: User) => props.users
  .filter((subordinate) => subordinate !== user && isAssignedEvaluator(subordinate, user))
  .map((subordinate) => ({
    user: subordinate,
    levels: reviewerStepsForUser(subordinate)
      .filter((step) => Number(step.reviewer_id) === Number(user.db_id))
      .map((step) => `ลำดับที่ ${step.step}`),
  }));
const hasSubordinates = (user: User) => subordinatesFor(user).length > 0;
const subordinateCount = (user: User) => subordinatesFor(user).length;
const evaluatorLine = (user: User) => {
  const steps = reviewerStepsForUser(user);

  if (!steps.length) return 'ยังไม่ได้กำหนดลำดับการประเมิน';

  return steps
    .map((step) => `คนที่ ${step.step}: ${evaluatorName(user, step.step) || '—'}`)
    .join(' · ');
};

const popDrillPath = (index: number) => {
  drillPath.value = index === -1 ? [] : drillPath.value.slice(0, index + 1);
};

const pushDrillPath = (user: User) => {
  if (hasSubordinates(user)) {
    drillPath.value = [...drillPath.value, user];
  }
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

.chain-map-section {
  display: grid;
  gap: 16px;
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
}

.chain-map-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
}

.chain-eyebrow {
  margin-bottom: 4px;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.chain-filterbar {
  grid-template-columns: minmax(260px, 1.2fr) repeat(3, minmax(0, 1fr));
  box-shadow: none;
}

.chain-search-field .inp {
  min-height: 40px;
  border-radius: 7px;
  font-size: 14px;
}

.chain-legend {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 10px 12px;
  border: 1px solid #dbe5f1;
  border-radius: 8px;
  background: #fbfdff;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.chain-legend span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
}

.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 4px;
  border: 1px solid transparent;
}

.legend-dot.subject {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.legend-dot.evaluator {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.legend-dot.missing {
  border-color: #fdba74;
  background: #fff7ed;
  border-style: dashed;
}

.evaluation-board {
  overflow: hidden;
  border: 1px solid #dbe5f1;
  border-radius: 10px;
  background: #f8fafc;
}

.evaluation-board-head {
  display: grid;
  grid-template-columns: repeat(4, minmax(190px, 1fr));
  gap: 18px;
  padding: 12px 18px;
  border-bottom: 1px solid #dbe5f1;
  background: #fff;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.evaluation-lines {
  display: grid;
  gap: 12px;
  max-height: 680px;
  overflow: auto;
  padding: 14px;
}

.evaluation-line {
  position: relative;
  display: grid;
  grid-template-columns: repeat(4, minmax(190px, 1fr));
  gap: 14px;
  align-items: stretch;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
}

.evaluation-line.incomplete {
  border-color: #e2e8f0;
  background: #fff;
}

.evaluation-flow-line {
  position: absolute;
  left: 9%;
  right: 9%;
  top: 50%;
  height: 2px;
  background: #cbd5e1;
  transform: translateY(-50%);
}

.evaluation-line.incomplete .evaluation-flow-line {
  background: #fdba74;
}

.evaluation-node {
  position: relative;
  z-index: 1;
  min-height: 94px;
  display: flex;
  align-items: center;
  border: 1px solid #bfdbfe;
  border-radius: 9px;
  background: #eff6ff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}

.evaluation-node.subject {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.evaluation-node.missing {
  border-style: dashed;
  border-color: #fdba74;
  background: #fff7ed;
}

.node-person {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  width: 100%;
  padding: 10px;
}

.node-avatar {
  flex: 0 0 auto;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 50%;
  background: var(--navy);
  color: #fff;
  font-size: 12px;
  font-weight: 850;
}

.node-copy {
  min-width: 0;
}

.node-role {
  color: #64748b;
  font-size: 10px;
  font-weight: 900;
}

.node-name {
  overflow: hidden;
  margin-top: 4px;
  color: var(--navy);
  font-size: 14px;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.node-meta {
  overflow: hidden;
  margin-top: 6px;
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.node-missing {
  display: grid;
  align-content: center;
  gap: 3px;
  width: 100%;
  padding: 10px;
  text-align: center;
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

.chain-empty {
  padding: 28px;
  color: var(--text3);
  font-size: 13px;
  text-align: center;
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

  .chain-map-head {
    flex-direction: column;
  }

  .evaluation-board {
    overflow-x: auto;
  }

  .evaluation-board-head,
  .evaluation-line {
    min-width: 980px;
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

  .chain-map-section {
    padding: 14px;
  }

  .evaluation-lines {
    max-height: 620px;
    padding: 10px;
  }
}
</style>
