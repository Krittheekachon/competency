// Reuses the local QA fixture and the role/login/control paths from output/enidp-client-qa scripts.
const { chromium } = require('C:/Users/HP/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs'), path = require('path'), assert = require('node:assert/strict');
const dir = path.resolve('output/enidp-fix-qa'), base = 'http://127.0.0.1:8010';
const d = JSON.parse(fs.readFileSync(path.join(dir, 'fixture-registry.json')));
const prefix = 'QA_ENIDP_FIX_20261104_', mode = process.argv[2], checks = [], errors = [];
const check = (condition, name) => { checks.push({ name, pass: Boolean(condition) }); assert.ok(condition, name); };
async function login(browser, role, viewport = { width: 1440, height: 960 }) {
    const page = await (await browser.newContext({ viewport })).newPage();
    page.on('pageerror', e => errors.push(e.message));
    page.on('requestfailed', r => { if (!r.failure()?.errorText.includes('ERR_ABORTED')) errors.push(r.failure()?.errorText); });
    await page.goto(base + '/mock-sso');
    await page.locator('.user-card').filter({ has: page.getByText(prefix + role, { exact: true }) }).getByRole('button', { name: 'Login', exact: true }).click();
    await page.waitForURL('**/dashboard');
    return page;
}
async function post(page, url, data) {
    return page.evaluate(async ({ url, data }) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(data) });
        return { status: r.status, body: await r.json().catch(() => null) };
    }, { url, data });
}
const keys = (c, score) => [0, 1, 2].map(i => `${c.id}:${c.levels[1]}:${i}`).concat(score >= 3 ? Array.from({ length: score === 3 ? 1 : 3 }, (_, i) => `${c.id}:${c.levels[3]}:${i}`) : []);
async function reviewer(page, person, type, score) {
    await page.goto(base + '/dashboard');
    await page.getByText('อนุมัติการประเมิน', { exact: true }).first().click();
    await page.locator('.assessment-hub-tabs button').filter({ hasText: 'ตรวจผลและส่งต่อ' }).click();
    await page.getByRole('button', { name: prefix + person, exact: true }).click();
    await page.locator('.approval-competency-head').filter({ hasText: prefix + type }).click();
    const boxes = page.locator('.detail-indicator-list input[type=checkbox]');
    check(await boxes.count() === (score === null ? 0 : 9), `${person}/${type}: correct score-edit permission`);
    if (score !== null) for (let i = 0; i < 9; i++) await boxes.nth(i).setChecked(i < (score === 3 ? 4 : 6));
    const response = page.waitForResponse(r => r.url().endsWith('/assessments/approve') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'ผ่านและส่งต่อ', exact: true }).click();
    await page.locator('.approval-confirm-button').click();
    check([302, 303].includes((await response).status()), `${person}/${type}: reviewer UI approval succeeded`);
}
async function fillPlan(page) {
    await page.locator('.goal-grid textarea').nth(0).fill(prefix + 'goal');
    await page.locator('.goal-grid textarea').nth(1).fill(prefix + 'criteria');
    await page.getByRole('button', { name: 'เลือกเครื่องมือ / หลักสูตร' }).click();
    await page.locator('.activity-builder-tabs button').filter({ hasText: 'Formal' }).click();
    await page.locator('.activity-choice').filter({ hasText: prefix + 'failed course' }).click();
    await page.locator('input[placeholder="เช่น 30"]').fill('100');
}
const draftSaved = page => page.waitForResponse(r => r.url().endsWith('/employee/idp/draft') && r.status() === 200, { timeout: 8000 });
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        if (mode === 'prepare') {
            for (const person of ['support', 'academic', 'executive']) {
                const p = await login(browser, person);
                for (const type of person === 'executive' ? ['CC', 'FC1', 'MC'] : ['CC', 'FC1']) {
                    const c = d.competencies[type];
                    check((await post(p, '/assessments/save', { competency_id: c.id, score: 2, checked_indicators: Object.fromEntries(keys(c, 2).map(k => [k, true])) })).status === 200, `${person}/${type}: self submit`);
                }
            }
            const deputy = await login(browser, 'deputy'), dean = await login(browser, 'dean');
            for (const type of ['CC', 'FC1']) {
                const c = d.competencies[type];
                check((await post(deputy, '/assessments/approve', { user_id: d.users.support, competency_id: c.id, checked: keys(c, type === 'CC' ? 3 : 4) })).status === 200, 'support deputy preparation');
                check((await post(dean, '/assessments/approve', { user_id: d.users.support, competency_id: c.id })).status === 200, 'support dean preparation');
            }
        }
        if (mode === 'reviewers') {
            const head = await login(browser, 'head'), dean = await login(browser, 'dean');
            for (const person of ['academic', 'executive']) {
                const types = person === 'executive' ? ['CC', 'FC1', 'MC'] : ['CC', 'FC1'];
                for (const type of types) await reviewer(head, person, type, type === 'MC' ? null : (type === 'CC' ? 3 : 4));
                for (const type of types) await reviewer(dean, person, type, type === 'MC' ? 3 : null);
            }
            await head.screenshot({ path: path.join(dir, 'reviewer-ui-coverage.png'), fullPage: false });
        }
        if (mode === 'idp-before' || mode === 'idp') {
            const p = await login(browser, 'executive');
            await p.goto(base + '/dashboard?page=emp-idp');
            check(await p.locator('.faculty-plan-competencies li').count() === 2, 'both failed gaps covered');
            // Register first, so fast responses cannot be missed.
            const saved = draftSaved(p).catch(() => null);
            await fillPlan(p);
            const response = await saved;
            if (mode === 'idp-before') {
                await p.reload();
                checks.push({ name: 'complete draft retained before fix', pass: await p.locator('.activity').count() === 1 });
            } else {
                check(Boolean(response), 'complete draft auto-saved');
                await p.reload();
                check(await p.locator('.activity').count() === 1, 'complete draft activity retained after refresh');
                check(await p.locator('.goal-grid textarea').nth(0).inputValue() === prefix + 'goal', 'goal retained');
                check(await p.locator('.goal-grid textarea').nth(1).inputValue() === prefix + 'criteria', 'success criteria retained');
                check(await p.locator('input[placeholder="เช่น 30"]').inputValue() === '100', 'weight retained');
                check((await p.locator('.faculty-formal-course').innerText()).includes(prefix + 'failed course description'), 'course name and description retained');
                const edit = draftSaved(p);
                await p.locator('.goal-grid textarea').nth(0).fill(prefix + 'edited goal');
                await edit;
                await p.reload();
                check(await p.locator('.goal-grid textarea').nth(0).inputValue() === prefix + 'edited goal', 'editing complete draft persists');
                check(await p.locator('.faculty-plan-competencies li').count() === 2, 'gap links retained after editing');
                await p.screenshot({ path: path.join(dir, 'idp-after-refresh.png'), fullPage: true });
                // Approved assessments have no authorized reopen path. Assert the lock without inventing one.
                const blocked = await post(p, '/assessments/draft', { competency_id: d.competencies.CC.id, score: 0, checked_indicators: {} });
                check(blocked.status === 422, 'approved assessment remains locked (D08 cannot be reopened by employee)');
                const submit = p.waitForResponse(r => r.url().endsWith('/employee/idp/submit-item') && r.request().method() === 'POST');
                await p.getByRole('button', { name: 'ส่งแผนให้ผู้อนุมัติ', exact: true }).click();
                check([302, 303].includes((await submit).status()), 'saved draft can still be submitted');
                await p.reload();
                check(await p.locator('.goal-grid textarea').nth(0).isDisabled(), 'submitted plan stays locked');
            }
        }
        if (mode === 'evidence') {
            const p = await login(browser, 'executive');
            await p.goto(base + '/dashboard?page=emp-idp');
            await p.locator('.activities-section').scrollIntoViewIfNeeded();
            await p.screenshot({ path: path.join(dir, 'idp-persisted-details.png'), fullPage: false });
            await p.setViewportSize({ width: 390, height: 844 });
            // The accessible close button lives inside the overlay rather than underneath it.
            await p.screenshot({ path: path.join(dir, 'mobile-close-control.png'), fullPage: false });
        }
        if (mode === 'mobile-before' || mode === 'mobile') {
            for (const role of mode === 'mobile-before' ? ['executive'] : ['executive', 'support', 'hr', 'dean', 'admin']) {
                const p = await login(browser, role, { width: 390, height: 844 });
                const close = p.getByRole('button', { name: 'ปิดเมนู', exact: true });
                if (mode === 'mobile-before') checks.push({ name: 'accessible mobile close before fix', pass: await close.count() > 0 });
                else {
                    await close.click();
                    check(await p.locator('.sidebar').count() === 0, `${role}: mobile sidebar closes`);
                    const toggle = role === 'admin' ? p.getByRole('button', { name: 'เปิดเมนู', exact: true }) : p.getByRole('button', { name: '☰', exact: true });
                    await toggle.click();
                    await close.click();
                    check(await p.locator('.sidebar').count() === 0, `${role}: reopen and close again`);
                    check(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${role}: no document overflow`);
                    if (role === 'executive') {
                        await p.goto(base + '/dashboard?page=emp-idp');
                        if (await close.count()) await close.click();
                        await p.screenshot({ path: path.join(dir, 'mobile-idp-menu-closed.png'), fullPage: false });
                        await p.setViewportSize({ width: 900, height: 844 });
                        await p.getByRole('button', { name: '☰', exact: true }).click(); await close.click();
                        check(await p.locator('.sidebar').count() === 0, '900px breakpoint closes');
                    }
                }
            }
        }
        check(errors.length === 0, 'no unexpected browser errors');
    } catch (e) { errors.push(e.message); process.exitCode = 1; }
    finally { fs.writeFileSync(path.join(dir, mode + '.json'), JSON.stringify({ checks, errors }, null, 2)); await browser.close(); console.log(JSON.stringify({ mode, checks: checks.length, failed: checks.filter(c => !c.pass).length, errors })); }
})();
