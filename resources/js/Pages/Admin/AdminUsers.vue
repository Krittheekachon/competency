<template>
  <div class="admin-users-head mb20">
    <div>
      <div class="sec-t">จัดการผู้ใช้งาน </div>
      <div class="sec-s">รายชื่อบุคลากรทั้งหมด · กำหนด Role และข้อมูลตามโครงสร้างองค์กร</div>
    </div>
    <div class="action-row">
      <!-- <button class="btn btn-s" type="button" @click="showImport = true"> Import Excel</button> -->
      <button class="btn btn-p add-user-btn" type="button" @click="openModal('modal-user')">+ เพิ่มผู้ใช้</button>
    </div>
  </div>

  <ExcelImportModal
    v-if="showImport"
    title="นำเข้าข้อมูลผู้ใช้งาน (User Import)"
    template-name="User_Template.xlsx"
    @close="showImport = false"
  />

  <div class="user-dashboard-grid mb14">
    <div class="user-metric">
      <span>ผู้ใช้ทั้งหมด</span>
      <strong>{{ props.users.length }}</strong>
    </div>
    <div class="user-metric warning">
      <span>ต้องตรวจสอบ</span>
      <strong>{{ invalidStructureCount }}</strong>
    </div>
  </div>

  <div class="card mb14">
    <div class="ch filter-row">
      <input v-model="search" class="inp search-input" placeholder=" ค้นหาชื่อ / ID..." />

      <select v-model="worklineFilter" class="sel workline-select">
        <option>ทุกสายงาน</option>
        <option v-for="workline in worklineOptions" :key="workline" :value="workline">
          {{ workline }}
        </option>
      </select>

      <select v-model="departmentFilter" class="sel department-select">
        <option>ทุกกลุ่มงาน</option>
        <option v-for="department in departmentOptions" :key="department" :value="department">
          {{ department }}
        </option>
      </select>

      <select v-model="positionFilter" class="sel position-select-filter">
        <option>ทุกตำแหน่ง</option>
        <option v-for="position in positionOptions" :key="position" :value="position">
          {{ position }}
        </option>
      </select>

      <select v-model="roleFilter" class="sel role-select">
        <option>ทุกบทบาท (Role)</option>
        <option v-for="role in roleOptions" :key="role" :value="role">
          {{ role }}
        </option>
      </select>

      <select v-model="statusFilter" class="sel status-select">
        <option>ทุกสถานะ</option>
        <option>ปกติ / ใช้งาน</option>
        <option>ระงับการใช้งาน</option>
      </select>
    </div>

    <div class="table-scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th>ID</th>
            <th style="min-width: 180px">ชื่อ-นามสกุล</th>
            <th>สายงาน</th>
            <th style="min-width: 200px">กลุ่มงาน</th>
            <th>ตำแหน่ง</th>
            <th>ระดับตำแหน่ง</th>
            <th style="min-width: 160px">บทบาทในระบบ</th>
            <th>สถานะ</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(user, index) in filteredUsers" :key="user.sso || index">
            <td class="id-cell">{{ user.sso || '—' }}</td>
            <td>
              <div class="flex ic g8">
                <div class="av user-avatar">
                  <img v-if="user.photo" class="avatar-photo" :src="user.photo" :alt="user.n" />
                  <span v-else>{{ avatarInitial(user) }}</span>
                </div>
                <div class="flex col">
                  <span class="fw6 fs13 user-name-line">
                    {{ user.t }}{{ user.n }}
                    <span
                      v-if="hasInvalidStructure(user)"
                      class="structure-warning-badge"
                      :title="structureIssueText(user)"
                    >
                      ! ต้องตรวจสอบ
                    </span>
                  </span>
                </div>
              </div>
            </td>
            <td>
              <span class="b workline-badge" :class="user.w === 'สายวิชาการ' ? 'bb' : 'bg'">
                {{ user.w || '—' }}
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
            <td class="fs12 position-cell">
              <div class="whitespace-nowrap overflow-hidden truncate full-width" :title="user.p || ''">
                {{ user.p || '—' }}
              </div>
            </td>
            <td class="muted fs11">{{ getDisplayLevel(user) || '—' }}</td>
            <td>
              <span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
                {{ roleBadge(user.r).label }}
              </span>
            </td>
            <td>
              <span class="b" :class="isActive(user) ? 'bg' : 'br'">
                {{ isActive(user) ? 'ปกติ' : 'ระงับ' }}
              </span>
            </td>
            <td>
              <div class="flex g4">
                <button class="btn btn-s btn-xs" type="button" @click="openModal('modal-user', user)">
                  แก้ไข
                </button>
                <button
                  class="btn btn-r btn-xs status-btn"
                  type="button"
                  :class="isActive(user) ? 'suspend' : 'activate'"
                  @click="toggleStatus(user.sso)"
                >
                  {{ isActive(user) ? 'ระงับ' : 'เปิด' }}
                </button>
                <button class="btn btn-r btn-xs delete-btn" type="button" @click="deleteUser(user)">
                  ลบ
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredUsers.length === 0" class="empty-result">
        ไม่พบข้อมูลที่คุณค้นหา 
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ExcelImportModal } from '../../Components/SharedUI.vue';

type User = {
  db_id?: number | null;
  sso?: string;
  t?: string;
  n: string;
  photo?: string;
  w?: string;
  d?: string;
  p?: string;
  l?: string;
  r?: string;
  sup?: string;
  evaluator2?: string;
  act?: boolean;
  structureStatus?: string;
  structureIssues?: string[];
};

type RoleBadge = {
  label: string;
  className: string;
  style?: Record<string, string>;
};

const props = defineProps<{
  openModal: (type: string, data?: unknown) => void;
  users: User[];
  setUsers: (next: User[] | ((users: User[]) => User[])) => void;
  academicDepts: string[];
  supportDepts: string[];
  adminDepts: string[];
  worklines: string[];
}>();

const showImport = ref(false);
const search = ref('');
const worklineFilter = ref('ทุกสายงาน');
const departmentFilter = ref('ทุกกลุ่มงาน');
const positionFilter = ref('ทุกตำแหน่ง');
const roleFilter = ref('ทุกบทบาท (Role)');
const statusFilter = ref('ทุกสถานะ');
const roleOptions = [
  'บุคลากร',
  'หัวหน้างาน',
  'ผู้บังคับบัญชา',
  'ผู้บริหารคณะ',
  'งานทรัพยากรบุคคล',
  'ผู้ดูแลระบบ',
];

const getDisplayLevel = (user: User) => (user.w === 'สายงานบริหาร' ? user.p : user.l);
const formatDept = (dept?: string) => (dept ? dept.split(' > ').join(' > ') : '—');
const avatarInitial = (user: User) => user.n?.[0] || '?';
const openModal = (type: string, data?: unknown) => props.openModal(type, data);
const worklineOptions = computed(() => props.worklines || []);
const isActive = (user: User) => user.act !== false;
const hasInvalidStructure = (user: User) => user.structureStatus === 'invalid';
const structureIssueText = (user: User) => (user.structureIssues || []).join('\n') || 'ข้อมูลโครงสร้างไม่ตรงกับ master data ปัจจุบัน';
const topDepartment = (user: User) => (user.d || '').split(' > ')[0]?.trim() || '';
const invalidStructureCount = computed(() => props.users.filter(hasInvalidStructure).length);
const departmentOptions = computed(() => {
  const departments = props.users
    .filter((user) => worklineFilter.value === 'ทุกสายงาน' || user.w === worklineFilter.value)
    .map(topDepartment)
    .filter(Boolean);

  return Array.from(new Set(departments)).sort((a, b) => a.localeCompare(b, 'th'));
});
const positionOptions = computed(() => {
  const positions = props.users
    .filter((user) => worklineFilter.value === 'ทุกสายงาน' || user.w === worklineFilter.value)
    .filter((user) => departmentFilter.value === 'ทุกกลุ่มงาน' || topDepartment(user) === departmentFilter.value)
    .map((user) => user.p || '')
    .filter(Boolean);

  return Array.from(new Set(positions)).sort((a, b) => a.localeCompare(b, 'th'));
});

watch(worklineFilter, () => {
  departmentFilter.value = 'ทุกกลุ่มงาน';
  positionFilter.value = 'ทุกตำแหน่ง';
});

watch(departmentFilter, () => {
  positionFilter.value = 'ทุกตำแหน่ง';
});

watch(departmentOptions, (options) => {
  if (departmentFilter.value !== 'ทุกกลุ่มงาน' && !options.includes(departmentFilter.value)) {
    departmentFilter.value = 'ทุกกลุ่มงาน';
  }
});

watch(positionOptions, (options) => {
  if (positionFilter.value !== 'ทุกตำแหน่ง' && !options.includes(positionFilter.value)) {
    positionFilter.value = 'ทุกตำแหน่ง';
  }
});

const roleBadge = (role?: string): RoleBadge => {
  switch (role) {
    case 'admin':
      return { label: 'ผู้ดูแลระบบ', className: 'bp' };
    case 'hr':
      return { label: 'งานทรัพยากรบุคคล', className: 'bb' };
    case 'manager':
    case 'dean':
      return {
        label: 'ผู้บริหารคณะ',
        className: 'bg',
        style: { background: '#e0f2fe', color: '#0369a1' },
      };
    case 'supervisor':
    case 'dept_head':
      return {
        label: 'หัวหน้างาน',
        className: 'bg',
        style: { background: '#fff7ed', color: '#c2410c' },
      };
    case 'manager_dept':
      return {
        label: 'ผู้บังคับบัญชา',
        className: 'bg',
        style: { background: '#f0f9ff', color: '#0284c7' },
      };
    default:
      return { label: 'บุคลากร', className: 'bgr' };
  }
};

const roleName = (role?: string) => roleBadge(role).label;

const filteredUsers = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return props.users.filter((user) => {
    const name = user.n || '';
    const id = user.sso || '';
    const matchesSearch = !keyword
      || name.toLowerCase().includes(keyword)
      || id.toLowerCase().includes(keyword);
    const matchesWorkline = worklineFilter.value === 'ทุกสายงาน' || user.w === worklineFilter.value;
    const matchesDepartment = departmentFilter.value === 'ทุกกลุ่มงาน' || topDepartment(user) === departmentFilter.value;
    const matchesPosition = positionFilter.value === 'ทุกตำแหน่ง' || user.p === positionFilter.value;
    const matchesRole = roleFilter.value === 'ทุกบทบาท (Role)' || roleName(user.r) === roleFilter.value;
    const matchesStatus = statusFilter.value === 'ทุกสถานะ'
      || (statusFilter.value === 'ปกติ / ใช้งาน' ? isActive(user) : !isActive(user));

    return matchesSearch && matchesWorkline && matchesDepartment && matchesPosition && matchesRole && matchesStatus;
  });
});

const toggleStatus = (sso?: string) => {
  if (!sso) return;

  const user = props.users.find(u => u.sso === sso);
  if (!user) return;
  if (!user.db_id) {
    alert('ไม่พบรหัสฐานข้อมูลของผู้ใช้นี้ กรุณารีเฟรชหน้าแล้วลองใหม่');
    return;
  }

  const nextActive = !isActive(user);
  const previousUsers = [...props.users];

  window.sessionStorage.setItem('cidp.admin.activePage', 'admin-users');
  props.setUsers((users) => users.map((u) => (u.sso === sso ? { ...u, act: nextActive } : u)));

  router.patch(route('admin.users.status', user.db_id), {
    act: nextActive,
  }, {
    preserveScroll: true,
    preserveState: true,
    onError: () => {
      props.setUsers(previousUsers);
      alert('ไม่สามารถบันทึกสถานะผู้ใช้ลงฐานข้อมูลได้');
    },
  });
};

const deleteUser = (user: User) => {
  if (!user.db_id) {
    alert('ไม่พบรหัสฐานข้อมูลของผู้ใช้นี้ กรุณารีเฟรชหน้าแล้วลองใหม่');
    return;
  }

  const displayName = `${user.t || ''}${user.n || ''}`.trim() || user.sso || 'ผู้ใช้นี้';
  if (!confirm(`ต้องการลบ ${displayName} ใช่ไหม?`)) return;

  window.sessionStorage.setItem('cidp.admin.activePage', 'admin-users');

  try {
    router.delete(`/admin/users/${user.db_id}`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        if (Array.isArray(page.props.users)) {
          props.setUsers(page.props.users as User[]);
          return;
        }

        props.setUsers((users) => users.filter((item) => item.db_id !== user.db_id));
      },
      onError: () => {
        alert('ไม่สามารถลบผู้ใช้ได้');
      },
      onCancel: () => {
        alert('คำสั่งลบถูกยกเลิก');
      },
    });
  } catch (error) {
    console.error(error);
    alert('ไม่สามารถส่งคำสั่งลบผู้ใช้ได้');
  }
};
</script>

<style scoped>
.admin-users-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.action-row {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
  margin-left: auto;
}

.add-user-btn {
  border-color: #2563eb;
  background: #2563eb;
  color: #fff;
  min-width: 116px;
  justify-content: center;
  opacity: 1;
}

.add-user-btn:hover {
  border-color: #1d4ed8;
  background: #1d4ed8;
  color: #fff;
}

.user-dashboard-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.user-metric {
  min-height: 92px;
  padding: 16px;
  border: 1px solid #dbe5f1;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.user-metric span {
  display: block;
  color: var(--text3);
  font-size: 12px;
  font-weight: 800;
}

.user-metric strong {
  display: block;
  margin-top: 8px;
  color: var(--navy);
  font-size: 30px;
  font-weight: 950;
  line-height: 1;
}

.user-metric.warning strong {
  color: #c2410c;
}

.filter-row {
  display: grid;
  grid-template-columns: minmax(210px, 1.5fr) minmax(130px, 0.9fr) minmax(170px, 1.2fr) minmax(160px, 1.1fr) minmax(160px, 1fr) minmax(120px, 0.8fr);
  gap: 8px;
}

.search-input {
  width: 100%;
  max-width: none;
}

.workline-select {
  width: 100%;
}

.department-select {
  width: 100%;
}

.position-select-filter {
  width: 100%;
}

.role-select {
  width: 100%;
}

.status-select {
  width: 100%;
}

.table-scroll {
  overflow-x: auto;
}

.id-cell {
  font-family: monospace;
  font-size: 11px;
  color: var(--text3);
}

.user-avatar {
  width: 32px;
  height: 32px;
  font-size: 12px;
  background: var(--navy);
}

.user-name-line {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  min-width: 0;
}

.structure-warning-badge {
  display: inline-flex;
  align-items: center;
  min-height: 22px;
  padding: 3px 7px;
  border: 1px solid #fed7aa;
  border-radius: 999px;
  background: #fff7ed;
  color: #c2410c;
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
  white-space: nowrap;
}

.workline-badge {
  font-size: 11px;
}

.dept-cell {
  max-width: 300px;
}

.position-cell,
.person-cell {
  max-width: 140px;
}

.full-width {
  width: 100%;
}

.status-btn.suspend {
  background: #fee2e2;
  color: #b91c1c;
}

.status-btn.activate {
  background: #dcfce7;
  color: #15803d;
}

.delete-btn {
  border-color: #fca5a5;
  background: #fff;
  color: #dc2626;
}

.delete-btn:hover {
  border-color: #ef4444;
  background: #fef2f2;
  color: #b91c1c;
}

.empty-result {
  padding: 40px;
  text-align: center;
  color: var(--text3);
}

@media (max-width: 980px) {
  .user-dashboard-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .user-dashboard-grid {
    grid-template-columns: 1fr;
  }

  .filter-row {
    grid-template-columns: 1fr;
  }

  .search-input,
  .workline-select,
  .department-select,
  .position-select-filter,
  .role-select,
  .status-select {
    width: 100%;
    max-width: none;
  }
}
</style>
