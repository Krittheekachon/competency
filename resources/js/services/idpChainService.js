// Prototype-only in-memory data. No fetch, router, localStorage or real API calls.
const units = [
  { id: 1, name: 'ฝ่ายบริหาร', parentId: null }, { id: 2, name: 'งานการเงิน', parentId: 1 },
  { id: 3, name: 'หน่วยบัญชี', parentId: 2 }, { id: 4, name: 'หน่วยพัสดุ', parentId: 2 },
  { id: 5, name: 'งานบุคคล', parentId: 1 }, { id: 6, name: 'หน่วยพัฒนาบุคลากร', parentId: 5 },
  { id: 7, name: 'ฝ่ายวิชาการ', parentId: null }, { id: 8, name: 'งานการศึกษา', parentId: 7 },
  { id: 9, name: 'หน่วยบริการนักศึกษา', parentId: 8 },
];
const names = ['ธนา','ภาคิน','ปวีณา','กมล','นภา','วีระ','พรทิพย์','ณัฐ','อรทัย','สมชาย','ศิริพร','กิตติ','จิรา','พิมพ์','วรพล','สุภา','นิรันดร์','อารี','ชุติมา','พิชัย','มาลี','ศุภชัย','ลลิตา','อาทิตย์','วิภา','ธนพร','วิชัย','รัตนา','พงศ์','อัญชลี'];
const users = names.map((name, index) => ({ id: index + 1, name: `${name} ทดสอบ`, position: ['นักวิชาการ','เจ้าหน้าที่บริหาร','หัวหน้างาน'][index % 3], unitId: index % 9 + 1, chains: { eval: index % 3 === 0 ? null : index % 2 === 0 ? 1 : 2, idp: index % 4 === 0 ? null : index % 2 === 0 ? 3 : 4 } }));
let presets = [
  { id: 1, type: 'eval', name: 'มาตรฐานตามโครงสร้าง', description: 'ตัวอย่างการใช้หัวหน้าตามผังองค์กร', reviewerIds: [1, 2, 3], system: true },
  { id: 2, type: 'eval', name: 'ประเมินสายสนับสนุน', description: 'หัวหน้างาน → ผู้บริหาร', reviewerIds: [3, 2], system: false },
  { id: 3, type: 'idp', name: 'มาตรฐานตามโครงสร้าง', description: 'ตัวอย่างลำดับอนุมัติแผนตามผังองค์กร', reviewerIds: [1, 2], system: true },
  { id: 4, type: 'idp', name: 'IDP พัฒนาบุคลากร', description: 'ลำดับสำหรับแผนพัฒนาสายสนับสนุน', reviewerIds: [3, 1, 2], system: false },
];
let sequence = 5;
const clone = value => JSON.parse(JSON.stringify(value));
const validType = type => { if (!['eval', 'idp'].includes(type)) throw new Error('ประเภทไม่ถูกต้อง'); };
export async function listPresets(type) { validType(type); return clone(presets.filter(p => p.type === type).map(p => ({ ...p, userCount: users.filter(user => user.chains[type] === p.id).length }))); }
export async function listUsers() { return clone(users); }
export async function listUnits() { return clone(units); }
export async function previewUnitTargets(unitId, includeChildren = false) {
  const ids = new Set([Number(unitId)]);
  if (!units.some(unit => ids.has(unit.id))) return { users: [], childUnits: [] };
  if (includeChildren) { let changed = true; while (changed) { changed = false; for (const unit of units) if (ids.has(unit.parentId) && !ids.has(unit.id)) { ids.add(unit.id); changed = true; } } }
  return clone({ users: users.filter(user => ids.has(user.unitId)), childUnits: units.filter(unit => unit.id !== Number(unitId) && ids.has(unit.id)) });
}
export async function savePreset(payload) {
  validType(payload.type);
  const current = presets.find(p => p.id === payload.id && p.type === payload.type);
  if (current?.system) throw new Error('Preset ระบบแก้ไขหรือลบไม่ได้');
  if (payload.remove) {
    if (!current) throw new Error('ไม่พบ Preset');
    presets = presets.filter(p => p.id !== current.id);
    users.forEach(user => { if (user.chains[payload.type] === current.id) user.chains[payload.type] = null; });
    return;
  }
  if (!payload.name?.trim() || !payload.reviewerIds?.length) throw new Error('กรอกชื่อและผู้ประเมินอย่างน้อย 1 คน');
  const row = { id: current?.id || sequence++, type: payload.type, name: payload.name.trim(), description: payload.description || '', reviewerIds: [...new Set(payload.reviewerIds)], system: false };
  presets = [...presets.filter(p => p.id !== row.id), row];
  (payload.userIds || []).forEach(id => { const user = users.find(user => user.id === id); if (user) user.chains[payload.type] = row.id; });
  return clone(row);
}
export async function applyPreset(payload) {
  validType(payload.type);
  if (!presets.some(p => p.id === Number(payload.presetId) && p.type === payload.type)) throw new Error('ไม่พบ Preset');
  const targets = payload.mode === 'unit' ? (await previewUnitTargets(payload.unitId, payload.includeChildren)).users : users.filter(user => (payload.userIds || []).includes(user.id));
  let added = 0, replaced = 0, skipped = 0;
  targets.forEach(target => {
    const user = users.find(user => user.id === target.id);
    if (user.chains[payload.type] && payload.existingChain !== 'override') { skipped++; return; }
    if (user.chains[payload.type]) replaced++; else added++;
    user.chains[payload.type] = Number(payload.presetId);
  });
  console.log('[mock] applyPreset', clone(payload));
  return { added, replaced, skipped };
}
