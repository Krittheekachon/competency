import test from 'node:test';
import assert from 'node:assert/strict';

const fresh = () => import(`../../resources/js/services/idpChainService.js?test=${Math.random()}`);

test('unit targets include only direct members unless descendants are enabled', async () => {
  const service = await fresh();
  const direct = await service.previewUnitTargets(1, false);
  const nested = await service.previewUnitTargets(1, true);
  assert.equal(direct.users.length, 4);
  assert.equal(direct.childUnits.length, 0);
  assert.equal(nested.users.length, 21);
  assert.equal(nested.childUnits.length, 5);
  assert.equal(new Set(nested.users.map(user => user.id)).size, 21);
  assert.deepEqual(await service.previewUnitTargets(null, true), { users: [], childUnits: [] });
});

test('keep skips existing chains; override replaces them without changing IDP', async () => {
  const service = await fresh();
  const before = await service.listUsers();
  const payload = { type: 'eval', presetId: 2, mode: 'unit', unitId: 1, includeChildren: true, existingChain: 'keep' };
  assert.deepEqual(await service.applyPreset(payload), { added: 7, replaced: 0, skipped: 14 });
  assert.deepEqual(await service.applyPreset({ ...payload, existingChain: 'override' }), { added: 0, replaced: 21, skipped: 0 });
  const after = await service.listUsers();
  assert.deepEqual(after.map(user => user.chains.idp), before.map(user => user.chains.idp));
  assert.equal((await service.previewUnitTargets(1, true)).users.filter(user => user.chains.eval === 2).length, 21);
});

test('individual assignment deduplicates targets and system presets stay locked', async () => {
  const service = await fresh();
  assert.deepEqual(await service.applyPreset({ type: 'eval', presetId: 2, mode: 'users', userIds: [1, 1, 4], existingChain: 'keep' }), { added: 2, replaced: 0, skipped: 0 });
  await assert.rejects(service.savePreset({ id: 1, type: 'eval', remove: true }));
  await assert.rejects(service.savePreset({ id: 3, type: 'idp', name: 'changed', reviewerIds: [1] }));
  assert.equal((await service.listPresets('eval')).length, 2);
});
