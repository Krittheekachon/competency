import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Rate, Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const authMode = __ENV.AUTH_MODE || 'password';
const profile = __ENV.PROFILE || 'capacity';
const accountsFile = __ENV.ACCOUNTS_FILE || './accounts.example.json';
const enableDrafts = (__ENV.ENABLE_DRAFTS || 'false').toLowerCase() === 'true';
const strictUniqueAccounts = (__ENV.STRICT_UNIQUE_ACCOUNTS || 'true').toLowerCase() === 'true';
const thinkTimeSeconds = Number(__ENV.THINK_TIME_SECONDS || 5);

const accounts = new SharedArray('load-test-accounts', () => JSON.parse(open(accountsFile)));
const journeyErrors = new Rate('journey_errors');
const dashboardDuration = new Trend('dashboard_duration', true);
const dashboardResponseBytes = new Trend('dashboard_response_bytes', true);
const assessmentDuration = new Trend('assessment_duration', true);
const draftDuration = new Trend('draft_duration', true);

const profiles = {
  smoke: {
    maxVUs: 5,
    stages: [
      { duration: '20s', target: 5 },
      { duration: '40s', target: 5 },
      { duration: '10s', target: 0 },
    ],
  },
  capacity: {
    maxVUs: 300,
    stages: [
      { duration: '2m', target: 50 },
      { duration: '3m', target: 150 },
      { duration: '5m', target: 300 },
      { duration: '15m', target: 300 },
      { duration: '3m', target: 0 },
    ],
  },
  spike1000: {
    maxVUs: 1000,
    stages: [
      { duration: '2m', target: 100 },
      { duration: '1m', target: 1000 },
      { duration: '5m', target: 1000 },
      { duration: '2m', target: 0 },
    ],
  },
};

if (!profiles[profile]) {
  throw new Error(`Unknown PROFILE=${profile}. Use smoke, capacity, or spike1000.`);
}

export const options = {
  scenarios: {
    user_journey: {
      executor: 'ramping-vus',
      gracefulRampDown: '30s',
      stages: profiles[profile].stages,
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    journey_errors: ['rate<0.01'],
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1500', 'p(99)<3000'],
    dashboard_duration: ['p(95)<1500'],
    dashboard_response_bytes: ['p(95)<1048576'],
    assessment_duration: ['p(95)<1000'],
    draft_duration: ['p(95)<2000'],
  },
};

let authenticated = false;
let account;

export function setup() {
  if (accounts.length === 0) {
    fail('The accounts file must contain at least one account.');
  }

  if (strictUniqueAccounts && accounts.length < profiles[profile].maxVUs) {
    fail(
      `PROFILE=${profile} needs ${profiles[profile].maxVUs} unique accounts, but ${accounts.length} were provided. `
      + 'Use a staging data set or set STRICT_UNIQUE_ACCOUNTS=false for a read-only diagnostic run.',
    );
  }

  if (enableDrafts && __ENV.CONFIRM_STAGING_WRITE !== 'yes') {
    fail('Draft writes require CONFIRM_STAGING_WRITE=yes. Never enable them against production.');
  }
}

export default function () {
  account ||= accounts[(__VU - 1) % accounts.length];

  if (!authenticated) {
    authenticated = login(account);
    if (!authenticated) {
      journeyErrors.add(true);
      sleep(1);
      return;
    }
  }

  const dashboard = http.get(`${baseUrl}/dashboard`, {
    tags: { name: 'GET /dashboard' },
    redirects: 0,
  });
  dashboardDuration.add(dashboard.timings.duration);
  dashboardResponseBytes.add(dashboard.body.length);
  const dashboardOk = check(dashboard, {
    'dashboard returns 200': (response) => response.status === 200,
    'dashboard is not an exception page': (response) => !response.body.includes('Whoops, looks like something went wrong'),
  });
  journeyErrors.add(!dashboardOk);

  const competencyId = Number(account.competency_id || __ENV.COMPETENCY_ID || 0);
  if (competencyId > 0) {
    const assessment = http.get(
      `${baseUrl}/assessments/load?competency_id=${encodeURIComponent(competencyId)}`,
      { tags: { name: 'GET /assessments/load' }, redirects: 0 },
    );
    assessmentDuration.add(assessment.timings.duration);
    const assessmentOk = check(assessment, {
      'assessment load returns 200': (response) => response.status === 200,
      'assessment load is JSON': (response) => (response.headers['Content-Type'] || '').includes('application/json'),
    });
    journeyErrors.add(!assessmentOk);

    if (enableDrafts) {
      saveDraft(account, competencyId);
    }
  }

  sleep(Math.max(thinkTimeSeconds, 0));
}

function login(selectedAccount) {
  const loginPath = authMode === 'mock-sso' ? '/mock-sso' : '/login';
  const loginPage = http.get(`${baseUrl}${loginPath}`, {
    tags: { name: `GET ${loginPath}` },
  });
  const csrfToken = loginPage.html().find('input[name="_token"]').first().attr('value');

  if (!csrfToken) {
    check(loginPage, { 'login page contains CSRF token': () => false });
    return false;
  }

  const payload = authMode === 'mock-sso'
    ? { _token: csrfToken, sso_id: selectedAccount.sso_id }
    : { _token: csrfToken, email: selectedAccount.email, password: selectedAccount.password };
  const response = http.post(`${baseUrl}${loginPath}`, payload, {
    tags: { name: `POST ${loginPath}` },
    redirects: 0,
  });

  return check(response, {
    'login redirects after success': (result) => [302, 303].includes(result.status),
    'login redirects to dashboard': (result) => (result.headers.Location || '').includes('/dashboard'),
  });
}

function saveDraft(selectedAccount, competencyId) {
  const cookies = http.cookieJar().cookiesForURL(baseUrl);
  const xsrfToken = cookies['XSRF-TOKEN']?.[0];

  if (!xsrfToken) {
    journeyErrors.add(true);
    check(null, { 'draft has XSRF token': () => false });
    return;
  }

  const response = http.post(
    `${baseUrl}/assessments/draft`,
    JSON.stringify({
      competency_id: competencyId,
      checked_indicators: selectedAccount.checked_indicators || [],
      note: 'k6 staging load test',
      score: Number(selectedAccount.score || 0),
    }),
    {
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(xsrfToken),
      },
      tags: { name: 'POST /assessments/draft' },
      redirects: 0,
    },
  );
  draftDuration.add(response.timings.duration);
  const draftOk = check(response, {
    'draft save returns 200': (result) => result.status === 200,
  });
  journeyErrors.add(!draftOk);
}
