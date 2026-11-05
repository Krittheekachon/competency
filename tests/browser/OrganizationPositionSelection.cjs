const { chromium } = require('C:/Users/HP/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs'), assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8010', prefix = `QA_SELECT_POSITION_${Date.now()}`;
(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  let p = await browser.newPage({viewport: {width: 1440, height: 960}});
  p.setDefaultTimeout(10000);
  const records = [], checks = [], errors = [];
  p.on('pageerror', e => errors.push(e.message));
  p.on('dialog', d => { if (d.type() === 'alert') errors.push(d.message()); return d.accept(); });
  async function open(tab) {
    await p.goto(base + '/dashboard?page=admin-org-structure');
    if (p.viewportSize().width < 900 && await p.getByRole('button', {name: 'ปิดเมนู', exact: true}).isVisible()) await p.getByRole('button', {name: 'ปิดเมนู', exact: true}).click();
    await p.getByRole('button', {name: tab, exact: true}).click();
    return JSON.parse(await p.locator('#app').getAttribute('data-page')).props;
  }
  async function api(url, method, data) {
    return p.evaluate(async ({url, method, data}) => {
      const r = await fetch(url, {method, headers: {'Content-Type':'application/json', Accept:'application/json', 'X-Inertia':'true', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(data)});
      return r.status;
    }, {url, method, data});
  }
  async function createTitle(workline, suffix) {
    await open('ตำแหน่งทั้งหมด');
    const name = `${prefix}_${suffix}`;
    await p.getByRole('button', {name: '+ เพิ่มตำแหน่ง', exact: true}).click();
    await p.locator('.mo input.inp').fill(name);
    await p.locator('.mo input.inp').press('Tab');
    await p.locator('.mo select').selectOption(String(workline.id));
    console.log('Creating QA catalog title: '+suffix);
    await p.locator('.add-modal-submit').click();
    await p.locator('.mo').waitFor({state:'hidden'});
    const props = await open('ตำแหน่งทั้งหมด');
    const title = props.positionTitles.find(t => t.name === name);
    assert(title && !title.isWorklinePosition, JSON.stringify({name,errors}));
    const record = {id:title.id, name, workline}; records.push(record);
    console.log('Created QA title '+title.id);
    return record;
  }
  async function selectAndAdd(record) {
    assert.equal(await p.locator('.mo input.inp').count(), 0, 'No free text name in organization position picker');
    await p.locator('.mo select').selectOption(String(record.id));
    await p.locator('.add-modal-submit').click();
    console.log('Adding selected QA title '+record.id);
    await p.locator('.organization-position').filter({hasText:record.name}).waitFor();
    if (await p.locator('.mo').count()) await p.locator('.mo').getByRole('button', {name:'ยกเลิก', exact:true}).click();
  }
  async function hoverRemove(record) {
    const chip = p.locator('.organization-position').filter({hasText:record.name});
    const del = chip.getByRole('button', {name:`นำตำแหน่ง ${record.name} ออก`, exact:true});
    assert.equal(await chip.getByRole('button', {name:/แก้ไข/}).count(),0);
    await p.mouse.move(0,0);
    assert.equal(await del.evaluate(e => getComputedStyle(e).opacity), '0');
    await chip.hover();
    await p.waitForFunction(name => {
      const e = [...document.querySelectorAll('.position-trash')].find(e=>e.getAttribute('aria-label')===`นำตำแหน่ง ${name} ออก`);
      return e && getComputedStyle(e).opacity === '1';
    },record.name);
    const a = await chip.boundingBox(), b = await del.boundingBox();
    assert(b.x + b.width <= a.x + a.width + 1 && b.y + b.height <= a.y + a.height + 1);
    await del.click();
    await chip.waitFor({state:'detached'});
  }
  try {
    await p.goto(base+'/mock-sso');
    await p.locator('.user-card').filter({has:p.getByText('admin',{exact:true})}).getByRole('button',{name:'Login',exact:true}).click();
    await p.waitForURL('**/dashboard');
    const props = await open('จัดการรายการองค์กร');
    const management = props.organizationWorklines.find(w=>w.name==='สายบริหาร');
    const academic = props.organizationWorklines.find(w=>w.name==='สายวิชาการ');
    const support = props.organizationWorklines.find(w=>w.name==='สายสนับสนุน');
    assert(management && academic && support);
    const root = await createTitle(management,'ROOT');
    await open('จัดการรายการองค์กร');
    const rootScope = p.locator('.management-workline-positions').first();
    assert.equal(await rootScope.getByText(root.name,{exact:true}).count(),0);
    await rootScope.getByRole('button',{name:'+ เพิ่มตำแหน่งระดับสายงาน',exact:true}).click();
    await selectAndAdd(root);
    await open('จัดการรายการองค์กร');
    await hoverRemove(root);
    let state = await open('ตำแหน่งทั้งหมด');
    assert.equal(state.positionTitles.find(t=>t.id===root.id).name,root.name);
    assert.equal(state.positionTitles.find(t=>t.id===root.id).isWorklinePosition,false);
    checks.push('Root selects existing ID; refresh persists; hover trash removes placement and preserves catalog');
    const faculty = await createTitle(academic,'FACULTY');
    await open('จัดการรายการองค์กร');
    const family = p.locator('.workline-section').filter({has:p.locator('.structure-section-title strong').filter({hasText:/^สายวิชาการ$/})}).locator('.academic-department-card').first();
    faculty.cleanup={workline_name:academic.name,job_family_name:await family.locator('.structure-label strong').innerText(),name:faculty.name};
    await family.getByRole('button',{name:'+ ตำแหน่ง',exact:true}).click();
    await selectAndAdd(faculty);
    await hoverRemove(faculty);
    checks.push('Department + selects catalog; no edit action; hover trash keeps canonical title');
    const unitTitle = await createTitle(support,'UNIT');
    await open('จัดการรายการองค์กร');
    const unit = p.locator('.support-unit-block').first();
    const work = unit.locator('xpath=ancestor::div[contains(@class,"support-work-card")]');
    const division = unit.locator('xpath=ancestor::div[@class="support-column"][1]');
    unitTitle.cleanup={division_name:await division.locator('.support-column-head .structure-label strong').innerText(),work_name:await work.locator('.support-work-head .structure-label strong').innerText(),unit_name:await unit.locator('.structure-label strong').first().innerText(),name:unitTitle.name};
    await unit.getByRole('button',{name:'+ เพิ่มตำแหน่ง',exact:true}).first().click();
    await selectAndAdd(unitTitle);
    await hoverRemove(unitTitle);
    checks.push('Support unit + selects catalog; no edit action; hover trash keeps canonical title');
    const mobile = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
    await mobile.addCookies(await p.context().cookies());
    p=await mobile.newPage();
    p.setDefaultTimeout(10000);
    p.on('pageerror',e=>errors.push(e.message));
    p.on('dialog',d=>d.accept());
    await open('จัดการรายการองค์กร');
    await p.locator('.management-workline-positions').getByRole('button',{name:'+ เพิ่มตำแหน่งระดับสายงาน',exact:true}).click();
    await p.locator('.mo select').selectOption(String(root.id));
    const box=await p.locator('.mo-box').boundingBox(); assert(box.x>=0 && box.x+box.width<=390);
    await p.locator('.add-modal-submit').click();
    await p.locator('.mo').waitFor({state:'hidden'});
    const mobileChip=p.locator('.organization-position').filter({hasText:root.name});
    assert.equal(await mobileChip.locator('.position-trash').evaluate(e=>getComputedStyle(e).opacity),'1');
    await mobileChip.getByRole('button',{name:`นำตำแหน่ง ${root.name} ออก`,exact:true}).click();
    await mobileChip.waitFor({state:'detached'});
    checks.push('Mobile catalog picker fits viewport and trash can be operated');
    assert.deepEqual(errors,[]);
  } catch(e) {
    console.error('UI check failed: '+e.message);
    throw e;
  } finally {
    try { for (const record of records) {
      if (record.cleanup) await api('/admin/structure/positions','DELETE',record.cleanup);
      const status=await api('/admin/structure/position-titles','DELETE',{position_title_id:record.id});
      record.cleanupStatus=status;
    }
      const remaining=await open('ตำแหน่งทั้งหมด');
      assert(!remaining.positionTitles.some(t=>records.some(r=>r.id===t.id)), 'QA titles remain after cleanup');
    } finally { await browser.close(); }
  }
  fs.mkdirSync('output/organization-position-selection',{recursive:true});
  fs.writeFileSync('output/organization-position-selection/browser-results.json',JSON.stringify({passed:true,checks,errors,qaTitlesCreatedAndRemoved:records.map(r=>r.id)},null,2));
  console.log(JSON.stringify({passed:true,checks}));
})().catch(e=>{console.error(e);process.exitCode=1;});
