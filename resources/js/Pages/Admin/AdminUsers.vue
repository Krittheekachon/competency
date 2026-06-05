<template>
  <div class="admin-users-head mb20">
    <div>
      <div class="sec-t">จัดการผู้ใช้งาน 👤</div>
      <div class="sec-s">รายชื่อบุคลากรทั้งหมด · กำหนด Role และข้อมูลตามโครงสร้างองค์กร</div>
    </div>
    <div class="action-row">
      <button class="btn btn-s" type="button" @click="showImport = true">📥 Import Excel</button>
      <button class="btn btn-p add-user-btn" type="button" @click="openModal('modal-user')">+ เพิ่มผู้ใช้</button>
    </div>
  </div>

  <ExcelImportModal
    v-if="showImport"
    title="นำเข้าข้อมูลผู้ใช้งาน (User Import)"
    template-name="User_Template.xlsx"
    @close="showImport = false"
  />

  <div class="card mb14">
    <div class="ch filter-row">
      <input v-model="search" class="inp search-input" placeholder="🔍 ค้นหาชื่อ / ID..." />

      <select v-model="worklineFilter" class="sel workline-select">
        <option>ทุกสายงาน</option>
        <option v-for="workline in worklineOptions" :key="workline" :value="workline">
          {{ workline }}
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
            <th style="min-width: 200px">หน่วยงาน / สังกัด</th>
            <th>ตำแหน่ง</th>
            <th>ระดับตำแหน่ง</th>
            <th>หัวหน้างาน</th>
            <th>ผู้บังคับบัญชา</th>
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
                  <span class="fw6 fs13">{{ user.t }}{{ user.n }}</span>
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
            <td class="muted fs12 person-cell">{{ user.sup || '—' }}</td>
            <td class="muted fs12 person-cell">{{ user.evaluator2 || '—' }}</td>
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
        ไม่พบข้อมูลที่คุณค้นหา 🔍
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
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

const getDisplayLevel = (user: User) => (['สายบริหาร', 'สายงานบริหาร'].includes(user.w || '') ? user.p : user.l);
const formatDept = (dept?: string) => (dept ? dept.split(' > ').join(' > ') : '—');
const avatarInitial = (user: User) => user.n?.[0] || '?';
const openModal = (type: string, data?: unknown) => props.openModal(type, data);
const worklineOptions = computed(() => props.worklines || []);
const isActive = (user: User) => user.act !== false;

const roleBadge = (role?: string): RoleBadge => {
  switch (role) {
    case 'admin':
      return { label: 'ผู้ดูแลระบบ', className: 'bp' };
    case 'hr':
      return { label: 'งานทรัพยากรบุคคล', className: 'bb' };
    case 'dean':
    case 'manager':
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
    const matchesRole = roleFilter.value === 'ทุกบทบาท (Role)' || roleName(user.r) === roleFilter.value;
    const matchesStatus = statusFilter.value === 'ทุกสถานะ'
      || (statusFilter.value === 'ปกติ / ใช้งาน' ? isActive(user) : !isActive(user));

    return matchesSearch && matchesWorkline && matchesRole && matchesStatus;
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

  const previousUsers = [...props.users];

  window.sessionStorage.setItem('cidp.admin.activePage', 'admin-users');
  props.setUsers((users) => users.filter((item) => item.db_id !== user.db_id));

  router.delete(route('admin.users.destroy', user.db_id), {
    preserveScroll: true,
    preserveState: true,
    onError: () => {
      props.setUsers(previousUsers);
      alert('ไม่สามารถลบผู้ใช้ได้');
    },
  });
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

.filter-row {
  flex-wrap: wrap;
  gap: 8px;
}

.search-input {
  max-width: 260px;
}

.workline-select {
  width: 160px;
}

.role-select {
  width: 180px;
}

.status-select {
  width: 130px;
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
</style>
