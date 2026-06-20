<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  competencies: { type: Array, default: () => [] },
  idpLearningMethods: { type: Array, default: () => [] },
  learningCatalogs: { type: Array, default: () => [] },
  learningMethods: { type: Array, default: () => [] },
  deliveryTypeSettings: { type: Array, default: () => [] },
});

const focusTabs = [
  { key: 'experiential', label: 'Experiential Focus' },
  { key: 'social', label: 'Social Learning Focus' },
  { key: 'formal', label: 'Formal Learning Focus' },
];
const deliveryTypeBaseOptions = [
  { value: 'e_learning', label: 'การฝึกอบรมออนไลน์ (e-Learning)' },
  { value: 'in_class', label: 'การฝึกอบรมในห้องเรียน (In Class Training)' },
];
const levelOptions = [
  { value: 1, label: 'Level 1' },
  { value: 2, label: 'Level 2' },
  { value: 3, label: 'Level 3' },
  { value: 4, label: 'Level 4' },
  { value: 5, label: 'Level 5' },
];
const deliverySortOrder = { in_class: 1, e_learning: 2 };
const codeCollator = new Intl.Collator(['th', 'en'], { numeric: true, sensitivity: 'base' });

const activeFocus = ref('experiential');
const initialDeliveryTypeCodes = () => {
  const codes = Object.fromEntries(deliveryTypeBaseOptions.map((item) => [item.value, '']));
  props.deliveryTypeSettings.forEach((setting) => {
    const value = setting.value || setting.delivery_type;
    if (value && codes[value] !== undefined) codes[value] = String(setting.code || '');
  });
  return codes;
};
const toolModalOpen = ref(false);
const toolMode = ref('create');
const toolForm = ref({ id: null, focusType: 'experiential', code: '', title: '' });
const deliveryTypeCodes = ref(initialDeliveryTypeCodes());
const deliveryCodeErrors = ref({});
const deliveryCodeSaving = ref(false);
const catalogModalOpen = ref(false);
const catalogMode = ref('create');
const catalogSearch = ref('');
const catalogCompetencySearch = ref('');
const catalogMethodFilter = ref('all');
const catalogDeliveryFilter = ref('all');
const catalogStatusFilter = ref('all');
const catalogErrors = ref({});
const catalogForm = ref({
  id: null,
  code: '',
  name: '',
  methodKey: '',
  deliveryType: 'e_learning',
  sourceType: 'internal',
  provider: '',
  cost: '',
  hours: '',
  competencyIds: [],
  expectedLevels: [],
  description: '',
  isActive: true,
});

const activeTab = computed(() => focusTabs.find((tab) => tab.key === activeFocus.value) || focusTabs[0]);
const simpleTools = computed(() => props.idpLearningMethods.filter((tool) => tool.focusType === activeFocus.value));
const deliveryTypeSettingsByValue = computed(() => Object.fromEntries(
  props.deliveryTypeSettings.map((setting) => [setting.value || setting.key || setting.delivery_type, setting]),
));
const deliveryTypeOptions = computed(() => deliveryTypeBaseOptions.map((item) => ({
  value: item.value,
  code: deliveryTypeCodes.value[item.value] || deliveryTypeSettingsByValue.value[item.value]?.code || item.defaultCode,
  label: deliveryTypeSettingsByValue.value[item.value]?.label || item.label,
})));
const isCatalogELearning = computed(() => catalogForm.value.deliveryType === 'e_learning');
const catalogPrimaryError = computed(() => Object.values(catalogErrors.value)[0] || '');
const formalMethodKey = computed(() => {
  const formal = props.learningMethods.find((method) => {
    const key = String(method.key || '').toLowerCase();
    const label = String(method.label || '').toLowerCase();
    return key.includes('formal') || label.includes('formal');
  });
  return formal?.key || props.learningMethods[0]?.key || '';
});
const filteredCatalogs = computed(() => {
  const keyword = catalogSearch.value.trim().toLowerCase();
  return props.learningCatalogs.filter((item) => {
    const haystack = `${item.code || ''} ${item.name || ''} ${item.description || ''}`.toLowerCase();
    const matchesSearch = !keyword || haystack.includes(keyword);
    const matchesMethod = catalogMethodFilter.value === 'all' || item.methodKey === catalogMethodFilter.value;
    const matchesDelivery = catalogDeliveryFilter.value === 'all' || item.deliveryType === catalogDeliveryFilter.value;
    const matchesStatus = catalogStatusFilter.value === 'all'
      || (catalogStatusFilter.value === 'active' ? item.isActive : !item.isActive);

    return matchesSearch && matchesMethod && matchesDelivery && matchesStatus;
  }).sort((a, b) => {
    const deliveryA = deliverySortOrder[a.deliveryType] || 99;
    const deliveryB = deliverySortOrder[b.deliveryType] || 99;
    if (deliveryA !== deliveryB) return deliveryA - deliveryB;

    const codeA = String(a.code || '').trim();
    const codeB = String(b.code || '').trim();
    if (codeA && codeB) return codeCollator.compare(codeA, codeB);
    if (codeA) return -1;
    if (codeB) return 1;

    return codeCollator.compare(String(a.name || ''), String(b.name || ''));
  });
});
const filteredCatalogCompetencies = computed(() => {
  const keyword = catalogCompetencySearch.value.trim().toLowerCase();
  if (!keyword) return props.competencies;

  return props.competencies.filter((competency) => {
    const haystack = `${competency.cd || ''} ${competency.n || ''} ${competency.t || ''} ${competency.typeName || ''}`.toLowerCase();
    return haystack.includes(keyword);
  });
});
const resetCatalogFilters = () => {
  catalogSearch.value = '';
  catalogMethodFilter.value = 'all';
  catalogDeliveryFilter.value = 'all';
  catalogStatusFilter.value = 'all';
};

const toolPayload = () => ({
  focus_type: toolForm.value.focusType,
  code: toolForm.value.code,
  title: toolForm.value.title,
});
const openToolCreate = () => {
  toolMode.value = 'create';
  toolForm.value = { id: null, focusType: activeFocus.value, code: '', title: '' };
  toolModalOpen.value = true;
};
const openToolEdit = (tool) => {
  toolMode.value = 'edit';
  toolForm.value = { id: tool.id, focusType: tool.focusType, code: tool.code || '', title: tool.title };
  toolModalOpen.value = true;
};
const submitTool = () => {
  if (toolMode.value === 'edit' && toolForm.value.id) {
    router.put(route('admin.idp-learning-methods.update', toolForm.value.id), toolPayload(), {
      preserveScroll: true,
      onSuccess: () => { toolModalOpen.value = false; },
    });
    return;
  }
  router.post(route('admin.idp-learning-methods.store'), toolPayload(), {
    preserveScroll: true,
    onSuccess: () => { toolModalOpen.value = false; },
  });
};
const deleteTool = (tool) => {
  if (!window.confirm(`ลบ "${tool.title}"?`)) return;
  router.delete(route('admin.idp-learning-methods.destroy', tool.id), { preserveScroll: true });
};

const catalogPayload = () => ({
  code: catalogForm.value.code || null,
  name: catalogForm.value.name,
  method_key: formalMethodKey.value || catalogForm.value.methodKey || null,
  delivery_type: catalogForm.value.deliveryType,
  source_type: isCatalogELearning.value ? catalogForm.value.sourceType : 'internal',
  provider: null,
  cost: isCatalogELearning.value && catalogForm.value.cost !== '' ? catalogForm.value.cost : null,
  hours: catalogForm.value.hours === '' ? null : catalogForm.value.hours,
  competency_ids: catalogForm.value.competencyIds,
  expected_levels: isCatalogELearning.value ? catalogForm.value.expectedLevels : [],
  description: catalogForm.value.description || null,
  is_active: catalogForm.value.isActive,
});
const setCatalogDeliveryType = (value) => {
  catalogForm.value.deliveryType = value;

  if (value === 'in_class') {
    catalogForm.value.expectedLevels = [];
    catalogForm.value.cost = '';
    catalogForm.value.provider = '';
    catalogForm.value.sourceType = 'internal';
  }
};
const clearCatalogErrors = () => {
  catalogErrors.value = {};
};
const clearCatalogError = (field) => {
  if (!catalogErrors.value[field]) return;
  const nextErrors = { ...catalogErrors.value };
  delete nextErrors[field];
  catalogErrors.value = nextErrors;
};
const openCatalogCreate = () => {
  catalogMode.value = 'create';
  catalogCompetencySearch.value = '';
  clearCatalogErrors();
  catalogForm.value = {
    id: null,
    code: '',
    name: '',
    methodKey: formalMethodKey.value,
    deliveryType: 'e_learning',
    sourceType: 'internal',
    provider: '',
    cost: '',
    hours: '',
    competencyIds: [],
    expectedLevels: [],
    description: '',
    isActive: true,
  };
  catalogModalOpen.value = true;
};
const openCatalogEdit = (item) => {
  catalogMode.value = 'edit';
  catalogCompetencySearch.value = '';
  clearCatalogErrors();
  catalogForm.value = {
    id: item.id,
    code: item.code || '',
    name: item.name || '',
    methodKey: item.methodKey || formalMethodKey.value,
    deliveryType: item.deliveryType || 'e_learning',
    sourceType: item.sourceType || 'internal',
    provider: item.provider || '',
    cost: item.cost ?? '',
    hours: item.hours ?? '',
    competencyIds: [...(item.competencyIds || [])].slice(0, 1),
    expectedLevels: [...(item.expectedLevels || [])],
    description: item.description || '',
    isActive: Boolean(item.isActive),
  };
  catalogModalOpen.value = true;
};
const submitCatalog = () => {
  clearCatalogErrors();
  if (catalogMode.value === 'edit' && catalogForm.value.id) {
    router.put(route('admin.learning-catalogs.update', catalogForm.value.id), catalogPayload(), {
      preserveScroll: true,
      onError: (errors) => { catalogErrors.value = errors; },
      onSuccess: () => {
        clearCatalogErrors();
        catalogModalOpen.value = false;
      },
    });
    return;
  }
  router.post(route('admin.learning-catalogs.store'), catalogPayload(), {
    preserveScroll: true,
    onError: (errors) => { catalogErrors.value = errors; },
    onSuccess: () => {
      clearCatalogErrors();
      catalogModalOpen.value = false;
    },
  });
};
const deleteCatalog = (item) => {
  if (!window.confirm(`ลบ "${item.name}" ออกจาก Formal Learning?`)) return;
  router.delete(route('admin.learning-catalogs.destroy', item.id), { preserveScroll: true });
};
const toggleCatalogCompetency = (id) => {
  catalogForm.value.competencyIds = catalogForm.value.competencyIds.includes(id) ? [] : [id];
};
const toggleExpectedLevel = (level) => {
  const current = new Set(catalogForm.value.expectedLevels);
  current.has(level) ? current.delete(level) : current.add(level);
  catalogForm.value.expectedLevels = [...current].sort((a, b) => a - b);
};
const competencyLabel = (item) => {
  const competencyId = item.competencyIds?.[0];
  const competency = props.competencies.find((entry) => entry.id === competencyId);

  if (!competency) return '-';
  return competency.cd || competency.n || '-';
};
const updateDeliveryTypeCode = (value, code) => {
  deliveryTypeCodes.value = {
    ...deliveryTypeCodes.value,
    [value]: String(code || '').replace(/\D/g, '').slice(0, 20),
  };
};
const deliveryCodeError = (value) => deliveryCodeErrors.value[`delivery_types.${value}`] || '';
const saveDeliveryTypeCodes = () => {
  deliveryCodeErrors.value = {};
  router.put(route('admin.idp-delivery-type-settings.update'), {
    delivery_types: deliveryTypeCodes.value,
  }, {
    preserveScroll: true,
    onStart: () => { deliveryCodeSaving.value = true; },
    onFinish: () => { deliveryCodeSaving.value = false; },
    onError: (errors) => { deliveryCodeErrors.value = errors; },
  });
};
const deliveryTypeOption = (value) => deliveryTypeOptions.value.find((item) => item.value === value);
const deliveryTypeCode = (value) => deliveryTypeOption(value)?.code || '-';
const deliveryTypeLabel = (value) => deliveryTypeOption(value)?.label || '-';
const deliveryTypeSelectLabel = (option) => (option.code ? `${option.code} ${option.label}` : option.label);
const deliveryTypeDisplay = (value) => {
  const option = deliveryTypeOption(value);
  if (!option) return '-';
  return deliveryTypeSelectLabel(option);
};
</script>

<template>
  <div class="admin-idp-tools">
    <div class="page-head">
      <div>
        <div class="sec-t">เครื่องมือ / แนวทาง สำหรับทำแผน IDP</div>
        <div class="sec-s">จัดการหัวข้อที่บุคลากรเลือกใช้ตอนทำแผน IDP</div>
      </div>
    </div>

    <div class="focus-tabs">
      <button
        v-for="tab in focusTabs"
        :key="tab.key"
        class="focus-tab"
        :class="{ active: activeFocus === tab.key }"
        type="button"
        @click="activeFocus = tab.key"
      >
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <section v-if="activeFocus !== 'formal'" class="card focus-panel">
      <div class="panel-head">
        <div>
          <div class="ct">{{ activeTab.label }}</div>
          <div class="cs">เพิ่ม/ลดหัวข้อเครื่องมือที่ User จะเลือกตอนทำ IDP</div>
        </div>
        <button class="btn btn-p" type="button" @click="openToolCreate">เพิ่มหัวข้อ</button>
      </div>

      <div class="tool-list">
        <div v-if="!simpleTools.length" class="empty-box">ยังไม่มีหัวข้อในหมวดนี้</div>
        <div v-for="(tool, index) in simpleTools" v-else :key="tool.id" class="tool-row">
          <div class="tool-no">{{ index + 1 }}</div>
          <div class="tool-title">
            <span class="tool-code">{{ tool.code || '-' }}</span>
            <strong>{{ tool.title }}</strong>
          </div>
          <div class="tool-actions">
            <button class="btn btn-s btn-sm" type="button" @click="openToolEdit(tool)">แก้ไข</button>
            <button class="btn btn-s btn-sm danger" type="button" @click="deleteTool(tool)">ลบ</button>
          </div>
        </div>
      </div>
    </section>

    <div v-else class="formal-stack">
      <section class="card delivery-code-card">
        <div class="delivery-code-copy">
          <div class="delivery-code-title">ตั้งค่ารหัสรูปแบบ</div>
          <div class="delivery-code-sub">แก้ตรงนี้ครั้งเดียว ทุกกิจกรรมที่ใช้รูปแบบเดียวกันจะใช้รหัสนี้ร่วมกัน</div>
        </div>
        <div class="delivery-code-fields">
          <label v-for="option in deliveryTypeOptions" :key="option.value" class="delivery-code-field">
            <span>{{ option.label }}</span>
            <input
              class="inp delivery-code-input"
              inputmode="numeric"
              maxlength="20"
              pattern="[0-9]*"
              :value="option.code"
              @input="updateDeliveryTypeCode(option.value, $event.target.value)"
            />
            <small v-if="deliveryCodeError(option.value)" class="field-error">{{ deliveryCodeError(option.value) }}</small>
          </label>
          <button class="btn btn-p btn-sm" type="button" :disabled="deliveryCodeSaving" @click="saveDeliveryTypeCodes">
            {{ deliveryCodeSaving ? 'กำลังบันทึก' : 'บันทึกรหัสรูปแบบ' }}
          </button>
        </div>
      </section>

      <section class="card focus-panel">
        <div class="panel-head">
          <div>
            <div class="ct">Learning Catalog</div>
            <div class="cs">จัดการกิจกรรมและหลักสูตรสำหรับ Formal Learning</div>
          </div>
          <div class="panel-actions">
            <button class="btn btn-p" type="button" @click="openCatalogCreate">เพิ่มกิจกรรม</button>
          </div>
        </div>

        <div class="catalog-card">
          <div class="catalog-filter-bar">
            <input
              v-model="catalogSearch"
              class="inp catalog-filter-search"
              placeholder="ค้นหารหัส / ชื่อกิจกรรม / คำอธิบาย"
            />
            <select v-model="catalogDeliveryFilter" class="sel catalog-filter-select">
              <option value="all">ทุกรูปแบบ</option>
              <option v-for="option in deliveryTypeOptions" :key="option.value" :value="option.value">{{ deliveryTypeSelectLabel(option) }}</option>
            </select>
            <select v-model="catalogStatusFilter" class="sel catalog-filter-select compact">
              <option value="all">ทุกสถานะ</option>
              <option value="active">เปิดใช้</option>
              <option value="inactive">ปิด</option>
            </select>
            <button class="btn btn-s btn-sm" type="button" @click="resetCatalogFilters">ล้าง</button>
          </div>
          <div class="table-wrap">
            <table class="tbl catalog-table">
              <thead>
                <tr>
                  <th class="col-order">ลำดับ</th>
                  <th>สมรรถนะที่เกี่ยวข้อง</th>
                  <th>รหัส</th>
                  <th>ชื่อกิจกรรม</th>
                  <th>รูปแบบ</th>
                  <th>สถานะ</th>
                  <th></th>
                </tr>
              </thead>
              <tbody v-if="!learningCatalogs.length">
                <tr>
                  <td colspan="7">
                    <div class="table-empty-cell">
                      <div>
                        <div class="fw8 fs14">ยังไม่มี Learning Catalog</div>
                        <p class="muted fs13 mb0">เมื่อมีตารางกิจกรรมพัฒนาแล้ว รายการหลักสูตรและกิจกรรมจะแสดงที่นี่</p>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tbody v-else-if="!filteredCatalogs.length">
                <tr>
                  <td colspan="7">
                    <div class="table-empty-cell">
                      <div>
                        <div class="fw8 fs14">ไม่พบรายการตาม filter</div>
                        <p class="muted fs13 mb0">ลองเปลี่ยนคำค้นหา ประเภท รูปแบบ หรือสถานะอีกครั้ง</p>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr v-for="(item, index) in filteredCatalogs" :key="item.id">
                  <td class="fw8 muted">{{ index + 1 }}</td>
                  <td><span class="competency-badge">{{ competencyLabel(item) }}</span></td>
                  <td class="fw8">{{ item.code || '-' }}</td>
                  <td>
                    <div class="fw8">{{ item.name }}</div>
                  </td>
                  <td>
                    <div class="catalog-format-cell">
                      <span class="catalog-delivery-label">{{ deliveryTypeLabel(item.deliveryType) }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="status-pill" :class="item.isActive ? 'active' : 'inactive'">
                      {{ item.isActive ? 'เปิดใช้' : 'ปิด' }}
                    </span>
                  </td>
                  <td>
                    <div class="row-actions">
                      <button class="btn btn-s btn-sm" type="button" @click="openCatalogEdit(item)">แก้ไข</button>
                      <button class="btn btn-s btn-sm danger" type="button" @click="deleteCatalog(item)">ลบ</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>

    <div v-if="toolModalOpen" class="modal" @click.self="toolModalOpen = false">
      <form class="modal-box compact" @submit.prevent="submitTool">
        <div class="modal-head">
          <div class="ct">{{ toolMode === 'edit' ? 'แก้ไขหัวข้อ' : 'เพิ่มหัวข้อ' }}</div>
          <button class="modal-close" type="button" @click="toolModalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <label class="lbl">รหัส</label>
          <input v-model="toolForm.code" class="inp" required placeholder="เช่น EXP-01" />
          <label class="lbl">หัวข้อ</label>
          <input v-model="toolForm.title" class="inp" required placeholder="เช่น การมอบหมายงานโครงการ / งานพิเศษ" />
          <div class="muted-note">ส่วนแนบไฟล์ template เตรียมไว้ในฐานข้อมูลแล้ว แต่ยังไม่เปิดให้กรอกในหน้านี้</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-s" type="button" @click="toolModalOpen = false">ยกเลิก</button>
          <button class="btn btn-p" type="submit">บันทึก</button>
        </div>
      </form>
    </div>

    <div v-if="catalogModalOpen" class="modal" @click.self="catalogModalOpen = false">
      <form class="modal-box" @submit.prevent="submitCatalog">
        <div class="modal-head">
          <div>
            <div class="ct">{{ catalogMode === 'edit' ? 'แก้ไขกิจกรรม Learning Catalog' : 'เพิ่มกิจกรรม Learning Catalog' }}</div>
            <div class="cs">ข้อมูลนี้จะถูกใช้เป็นกิจกรรมใน Formal Learning Focus</div>
          </div>
          <button class="modal-close" type="button" @click="catalogModalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <section class="form-section catalog-section">
            <div class="section-title">1. เลือกรูปแบบกิจกรรม</div>
            <div class="form-grid">
              <div class="fg">
                <label class="lbl">รูปแบบ</label>
                <select
                  class="sel"
                  :value="catalogForm.deliveryType"
                  @change="setCatalogDeliveryType($event.target.value)"
                >
                  <option v-for="option in deliveryTypeOptions" :key="option.value" :value="option.value">{{ deliveryTypeSelectLabel(option) }}</option>
                </select>
              </div>
              <div class="fg">
                <label class="lbl">รหัสรูปแบบ</label>
                <div class="readonly-field">{{ deliveryTypeDisplay(catalogForm.deliveryType) }}</div>
                <div class="field-hint">แก้รหัสรูปแบบได้จากแผงตั้งค่าด้านบนตาราง Learning Catalog</div>
              </div>
            </div>
          </section>

          <section class="form-section catalog-section">
            <div class="catalog-section-head">
              <div>
                <div class="section-title">2. เลือกสมรรถนะที่เกี่ยวข้อง</div>
                <div class="section-sub">เลือกสมรรถนะหลักที่หลักสูตรนี้ช่วยพัฒนาหรือปิด Gap ได้ 1 รายการ</div>
              </div>
              <span class="count-pill">{{ catalogForm.competencyIds.length ? 'เลือกแล้ว' : 'ยังไม่เลือก' }}</span>
            </div>
            <input
              v-model="catalogCompetencySearch"
              class="inp catalog-search-input"
              placeholder="ค้นหารหัสหรือชื่อสมรรถนะ เช่น CC, MC, FC"
            />
            <div class="competency-list">
              <button
                v-for="competency in filteredCatalogCompetencies"
                :key="competency.id"
                class="competency-option"
                :class="{ selected: catalogForm.competencyIds.includes(competency.id) }"
                type="button"
                @click="toggleCatalogCompetency(competency.id)"
              >
                <span>{{ competency.cd }}</span>
                <strong>{{ competency.n }}</strong>
                <em>{{ competency.t || '-' }}</em>
              </button>
              <div v-if="!filteredCatalogCompetencies.length" class="empty-inline">ไม่พบสมรรถนะที่ค้นหา</div>
            </div>

            <div v-if="isCatalogELearning" class="level-picker">
              <div class="catalog-section-head compact">
                <div>
                  <div class="section-title small">เหมาะสำหรับผู้ที่มีระดับความคาดหวัง</div>
                  <div class="section-sub">เลือกได้มากกว่า 1 ระดับ หรือไม่ระบุก็ได้หากคู่มือไม่ได้กำหนดไว้</div>
                </div>
                <button class="count-pill ghost" type="button" @click="catalogForm.expectedLevels = []">
                  {{ catalogForm.expectedLevels.length ? `${catalogForm.expectedLevels.length} ระดับ` : 'ไม่ระบุ' }}
                </button>
              </div>
              <div class="level-grid">
                <button
                  v-for="level in levelOptions"
                  :key="level.value"
                  class="level-option"
                  :class="{ selected: catalogForm.expectedLevels.includes(level.value) }"
                  type="button"
                  @click="toggleExpectedLevel(level.value)"
                >
                  <span class="level-dot"></span>
                  <strong>{{ level.label }}</strong>
                  <small>
                    <template v-if="level.value === 1">พื้นฐาน / เริ่มต้น</template>
                    <template v-else-if="level.value === 2">ลงมือปฏิบัติ</template>
                    <template v-else-if="level.value === 3">พัฒนา / ทำได้ดี</template>
                    <template v-else-if="level.value === 4">ขั้นสูง / ชำนาญ</template>
                    <template v-else>เชี่ยวชาญ</template>
                  </small>
                </button>
              </div>
            </div>
          </section>

          <section class="form-section catalog-section">
            <div class="section-title">3. รายละเอียดหลักสูตร/กิจกรรม</div>
            <div v-if="catalogPrimaryError" class="form-alert">
              {{ catalogPrimaryError }}
            </div>
            <div class="form-grid">
              <div class="fg">
                <label class="lbl">{{ isCatalogELearning ? 'รหัสหลักสูตร/บทเรียน' : 'รหัสหลักสูตร' }}</label>
                <input
                  v-model="catalogForm.code"
                  class="inp"
                  :class="{ invalid: catalogErrors.code }"
                  :placeholder="isCatalogELearning ? 'เช่น ศูนย์นวัตกรรมการเรียนการสอน, OCSC014' : 'เช่น TN001'"
                  @input="clearCatalogError('code')"
                />
                <div v-if="catalogErrors.code" class="field-error">{{ catalogErrors.code }}</div>
              </div>
              <div class="fg">
                <label class="lbl">ประเภทกิจกรรม</label>
                <div class="readonly-field">Formal Learning</div>
              </div>
              <div class="fg">
                <label class="lbl">{{ isCatalogELearning ? 'ชื่อหลักสูตร/บทเรียน' : 'ชื่อหลักสูตร' }}</label>
                <input
                  v-model="catalogForm.name"
                  class="inp"
                  :class="{ invalid: catalogErrors.name }"
                  required
                  placeholder="ชื่อกิจกรรมพัฒนา"
                  @input="clearCatalogError('name')"
                />
                <div v-if="catalogErrors.name" class="field-error">{{ catalogErrors.name }}</div>
              </div>
              <div class="fg span-2">
                <label class="lbl">คำอธิบายหลักสูตร</label>
                <textarea v-model="catalogForm.description" class="ta" placeholder="รายละเอียด เนื้อหา และวัตถุประสงค์ของการเรียนรู้"></textarea>
              </div>
              <template v-if="isCatalogELearning">
                <div class="fg">
                  <label class="lbl">แหล่งหลักสูตร</label>
                  <select v-model="catalogForm.sourceType" class="sel">
                    <option value="internal">ภายในมหาวิทยาลัย</option>
                    <option value="external">ภายนอกมหาวิทยาลัย</option>
                  </select>
                </div>
                <div class="fg">
                  <label class="lbl">ค่าใช้จ่าย</label>
                  <input v-model="catalogForm.cost" class="inp" min="0" step="0.01" type="number" placeholder="0" />
                </div>
              </template>
              <div class="fg" :class="{ 'span-2': !isCatalogELearning }">
                <label class="lbl">{{ isCatalogELearning ? 'ชั่วโมงเรียนรู้' : 'จำนวนชั่วโมง' }}</label>
                <input v-model="catalogForm.hours" class="inp" min="0" step="0.5" type="number" placeholder="เช่น 3" />
              </div>
            </div>
          </section>

          <label class="active-toggle catalog-status-toggle">
            <input v-model="catalogForm.isActive" type="checkbox" />
            <span>
              <strong>เปิดใช้งานหลักสูตรนี้</strong>
              <small>เมื่อเปิด ระบบจะนำไปแสดงเป็นหลักสูตรแนะนำให้บุคลากรที่มีคะแนนตกเกณฑ์</small>
            </span>
          </label>
        </div>
        <div class="modal-foot">
          <button class="btn btn-s" type="button" @click="catalogModalOpen = false">ยกเลิก</button>
          <button class="btn btn-p" type="submit">{{ catalogMode === 'edit' ? 'บันทึกการแก้ไข' : 'เพิ่มกิจกรรม' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.admin-idp-tools { display: grid; gap: 16px; }
.page-head, .panel-head, .row-actions, .tool-actions, .modal-head, .modal-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.admin-idp-tools .btn.btn-p,
.modal .btn.btn-p {
  border: 1px solid #2563eb;
  background: #2563eb;
  color: #fff;
  box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18);
}
.admin-idp-tools .btn.btn-p:hover,
.modal .btn.btn-p:hover {
  border-color: #1d4ed8;
  background: #1d4ed8;
}
.formal-stack { display: grid; gap: 16px; }
.panel-actions { display: flex; align-items: center; gap: 10px; }
.panel-count { display: inline-flex; align-items: center; min-height: 34px; border: 1px solid #dbe7f7; border-radius: 999px; background: #f8fbff; color: var(--text3); font-size: 12px; font-weight: 900; padding: 4px 12px; white-space: nowrap; }
.focus-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.focus-tab { min-height: 74px; display: flex; align-items: center; gap: 12px; border: 1px solid var(--border); border-radius: 10px; background: #fff; color: var(--text2); cursor: pointer; padding: 14px 16px; text-align: left; box-shadow: var(--sh); }
.focus-tab.active { border-color: #9fc0fb; color: var(--navy); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08), var(--sh); }
.focus-tab span { font-size: 14px; font-weight: 900; }
.focus-panel { overflow: hidden; }
.panel-head { padding: 16px 18px; border-bottom: 1px solid var(--border); background: #fff; }
.tool-list { display: grid; }
.tool-row { display: grid; grid-template-columns: 58px minmax(0, 1fr) auto; align-items: center; gap: 14px; min-height: 64px; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.tool-row:last-child { border-bottom: 0; }
.tool-no { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: var(--bg); color: var(--text3); font-size: 12px; font-weight: 900; }
.tool-title { display: flex; align-items: center; gap: 10px; color: var(--text); font-size: 14px; font-weight: 800; line-height: 1.45; }
.tool-code { border-radius: 999px; background: #eef4ff; color: #2563eb; font-size: 11px; font-weight: 900; padding: 5px 9px; white-space: nowrap; }
.delivery-code-card { display: grid; grid-template-columns: minmax(220px, 0.55fr) minmax(0, 1fr); gap: 14px; align-items: center; padding: 16px 18px; background: #fff; }
.delivery-code-title { color: var(--text); font-size: 14px; font-weight: 900; }
.delivery-code-sub { margin-top: 3px; color: var(--text3); font-size: 12px; font-weight: 700; line-height: 1.45; }
.delivery-code-fields { display: flex; align-items: flex-end; justify-content: flex-end; flex-wrap: wrap; gap: 10px; }
.delivery-code-field { min-width: 260px; display: grid; gap: 6px; color: var(--text2); font-size: 12px; font-weight: 900; }
.delivery-code-input { width: 92px; min-height: 40px; text-align: center; font-size: 15px; font-weight: 900; letter-spacing: 0; }
.catalog-card { background: #fff; }
.catalog-card-head { display: flex; align-items: center; gap: 12px; min-height: 56px; padding: 14px 18px; border-bottom: 1px solid var(--border); }
.catalog-filter-bar { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(220px, 0.55fr) minmax(130px, 0.32fr) auto; gap: 10px; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--border); background: #fbfdff; }
.catalog-filter-search, .catalog-filter-select { min-height: 40px; border-radius: 8px; font-size: 13px; }
.catalog-filter-select.compact { min-width: 124px; }
.table-wrap { max-height: 560px; overflow: auto; }
.tbl { width: 100%; border-collapse: collapse; min-width: 760px; }
.catalog-table { min-width: 860px; }
.tbl th, .tbl td { padding: 13px 18px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
.tbl th { position: sticky; top: 0; z-index: 2; color: var(--text3); font-size: 12px; font-weight: 900; background: #fbfdff; }
.col-order { width: 72px; }
.competency-badge { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; border: 1px solid #dbe7f7; background: #f8fbff; color: var(--text2); font-size: 12px; font-weight: 900; padding: 4px 12px; white-space: nowrap; }
.catalog-format-cell { display: grid; gap: 5px; align-items: start; }
.catalog-code-badge { display: inline-flex; align-items: center; width: fit-content; min-height: 24px; border-radius: 8px; background: #f8fafc; color: var(--text); font-size: 12px; font-weight: 900; letter-spacing: 0; padding: 3px 9px; }
.catalog-delivery-label { color: var(--text2); font-size: 12px; font-weight: 800; line-height: 1.35; }
.status-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.status-pill.active { border: 1px solid #bbf7d0; background: #dcfce7; color: #15803d; }
.status-pill.inactive { border: 1px solid #fecaca; background: #fee2e2; color: #b91c1c; }
.empty-box { min-height: 116px; display: grid; place-items: center; color: var(--text3); font-size: 13px; font-weight: 800; background: var(--bg); }
.table-empty-cell { min-height: 118px; display: flex; align-items: center; gap: 14px; background: var(--bg); padding: 20px; }
.tbl td:has(.table-empty-cell) { padding: 0; }
.ml-auto { margin-left: auto; }
.fs12 { font-size: 12px; }
.fs13 { font-size: 13px; }
.fs14 { font-size: 14px; }
.mb0 { margin-bottom: 0; }
.danger { color: #b42318 !important; }
.modal { position: fixed; inset: 0; z-index: 900; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 28px; background: rgba(15, 23, 42, 0.42); }
.modal-box { width: min(1180px, 100%); border-radius: 12px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); overflow: hidden; }
.modal-box.compact { width: min(620px, 100%); }
.modal-head, .modal-foot { padding: 16px 20px; border-bottom: 1px solid var(--border); }
.modal-foot { border-top: 1px solid var(--border); border-bottom: 0; justify-content: flex-end; }
.modal-body { display: grid; gap: 14px; padding: 18px 20px; }
.modal-close { width: 40px; height: 40px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text3); font-size: 26px; cursor: pointer; }
.form-section { border: 1px solid var(--border); border-radius: 10px; background: #fff; padding: 14px; }
.catalog-section { padding: 18px; }
.catalog-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
.catalog-section-head.compact { align-items: center; margin-bottom: 12px; }
.section-title { margin-bottom: 12px; color: var(--text); font-size: 15px; font-weight: 900; }
.catalog-section .section-title { margin-bottom: 0; }
.section-title.small { font-size: 13px; }
.section-sub { margin-top: 3px; color: var(--text3); font-size: 12px; font-weight: 700; line-height: 1.5; }
.count-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 34px; border: 1px solid #cfe0f5; border-radius: 999px; background: #eff6ff; color: #2563eb; font-size: 12px; font-weight: 900; padding: 4px 12px; white-space: nowrap; }
.count-pill.ghost { cursor: pointer; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 16px; }
.readonly-field { min-height: 42px; display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; background: #f8fafc; color: var(--text); font-size: 13px; font-weight: 800; padding: 8px 12px; }
.inp.invalid { border-color: #fca5a5; background: #fff7f7; }
.form-alert { margin-bottom: 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 13px; font-weight: 900; padding: 10px 12px; }
.field-error { margin-top: 6px; color: #b91c1c; font-size: 12px; font-weight: 800; }
.field-hint { margin-top: 6px; color: var(--text3); font-size: 12px; font-weight: 800; }
.span-2 { grid-column: span 2; }
.catalog-search-input { min-height: 44px; margin-bottom: 12px; }
.competency-list { max-height: 210px; overflow-y: auto; display: grid; gap: 8px; padding-right: 4px; }
.competency-option { display: grid; grid-template-columns: 22px 74px minmax(0, 1fr) auto; gap: 10px; align-items: center; min-height: 52px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text); text-align: left; padding: 10px 12px; cursor: pointer; }
.competency-option::before { content: ""; width: 16px; height: 16px; border: 1px solid #d8e4f3; border-radius: 4px; background: #fff; }
.competency-option.selected { border-color: #9fc0fb; background: #eff6ff; }
.competency-option.selected::before { border-color: #2563eb; background: #2563eb; box-shadow: inset 0 0 0 3px #2563eb; }
.competency-option span { color: var(--text3); font-weight: 900; }
.competency-option strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.competency-option em { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; min-height: 24px; border-radius: 999px; background: #f1f5f9; color: var(--text2); font-size: 11px; font-style: normal; font-weight: 900; padding: 2px 8px; }
.empty-inline { min-height: 48px; display: grid; place-items: center; color: var(--text3); font-size: 12px; font-weight: 800; }
.level-picker { margin-top: 12px; border: 1px solid var(--border); border-radius: 10px; background: #fbfdff; padding: 14px; }
.level-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.level-option { min-height: 76px; display: grid; grid-template-columns: 22px 1fr; grid-template-rows: auto auto; align-content: center; column-gap: 10px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text2); font-weight: 900; cursor: pointer; padding: 12px; text-align: left; }
.level-option.selected { border-color: #9fc0fb; background: #eff6ff; color: var(--blue); }
.level-dot { grid-row: span 2; width: 18px; height: 18px; border: 1px solid #d8e4f3; border-radius: 50%; background: #fff; margin-top: 1px; }
.level-option.selected .level-dot { border-color: #2563eb; box-shadow: inset 0 0 0 5px #2563eb; }
.level-option strong { color: var(--text); font-size: 13px; }
.level-option.selected strong { color: var(--blue); }
.level-option small { color: var(--text3); font-size: 11px; font-weight: 800; }
.active-toggle { display: flex; align-items: center; gap: 10px; color: var(--text); font-weight: 900; }
.catalog-status-toggle { align-items: flex-start; border: 1px solid var(--border); border-radius: 10px; background: #fff; padding: 14px; }
.catalog-status-toggle input { width: 16px; height: 16px; margin-top: 2px; accent-color: #2563eb; }
.catalog-status-toggle span { display: grid; gap: 3px; }
.catalog-status-toggle small { color: var(--text3); font-size: 12px; font-weight: 700; }
.muted-note { margin-top: 10px; color: var(--text3); font-size: 12px; }
.fw8 { font-weight: 800; }
@media (max-width: 900px) {
  .focus-tabs, .form-grid, .level-grid, .catalog-filter-bar { grid-template-columns: 1fr; }
  .delivery-code-card { grid-template-columns: 1fr; }
  .delivery-code-fields { justify-content: flex-start; }
  .delivery-code-field { min-width: min(100%, 260px); }
  .span-2 { grid-column: auto; }
  .tool-row { grid-template-columns: 1fr; }
  .tool-actions, .panel-actions { justify-content: flex-start; }
  .panel-head { align-items: flex-start; flex-direction: column; }
}
</style>
