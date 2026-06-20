---
name: competency-idp-system
description: Develop, debug, test, and maintain the ENKKU Competency and Individual Development Plan (IDP) system in this repository. Use when changing Laravel controllers, PostgreSQL migrations or queries, Inertia props, Vue admin/employee/reviewer/HR screens, competency assessments, reviewer approval chains, competency gaps, IDP plans, learning catalogs, organization structures, users, roles, or related feature tests.
---

# Competency IDP System

Work as a maintainer of the existing ENKKU Competency and IDP application. Preserve its domain rules, Thai administrative UI, and current Laravel/Inertia/Vue architecture.

## Stack And Entry Points

- Use PHP 8.3+, Laravel 13, Inertia Laravel 2, Vue 3, Vite 8, and PostgreSQL.
- Start route tracing at `routes/web.php`.
- Treat `app/Http/Controllers/DashboardController.php` as the main read-model assembler for role dashboards.
- Find role navigation and page IDs in `resources/js/data.ts`.
- Find role shells in:
  - `resources/js/Pages/Admin/Dashboard.vue`
  - `resources/js/Pages/HR/Dashboard.vue`
  - `resources/js/Pages/Super/Dashboard.vue`
  - `resources/js/Pages/Head/Dashboard.vue`
  - `resources/js/Pages/Executive/Dashboard.vue`
  - `resources/js/Pages/Employee/Dashboard.vue`
- Find reusable employee views under `resources/js/Pages/Employee/`.
- Use `resources/css/app.css` and existing component-scoped styles before introducing new styling conventions.

## Working Method

1. Read the affected route, controller, migration/schema, dashboard prop, Vue component, and closest feature test before editing.
2. Trace data end to end:
   `PostgreSQL table -> controller query/validation -> Inertia prop -> Vue state/computed value -> submitted payload`.
3. Search with `rg` for table names, status strings, route names, and prop names. Domain behavior is often duplicated across controllers and role-specific pages.
4. Preserve unrelated changes in the dirty worktree. Never revert files merely because they were modified before the current task.
5. Keep changes scoped, but update every layer required for one coherent behavior.
6. Add or update a feature test for backend behavior. Run a frontend build for Vue changes.
7. For visible UI changes, verify the relevant local page through mock SSO when possible.

## Current System Summary

Treat the system as this end-to-end flow:

```text
organization and competency setup
  -> employee self-assessment
  -> configured reviewer chain
  -> approved competency result
  -> competency gap
  -> one IDP item per negative competency
  -> many development activities
  -> submit, approve, or reject each IDP item independently
  -> activity progress updates and evidence
```

Use these tables as the primary data ownership boundaries:

| Area | Tables | Purpose |
| --- | --- | --- |
| Organization | `users`, `roles`, `worklines`, `job_families`, `positions`, `levels`, `support_departments`, `support_works`, `support_units` | Store users, roles, reporting lines, and organization structure. |
| Competency setup | `competency_types`, `competencies`, `competency_levels`, `comp_level_indicators`, `position_competencies`, `hr_expectations` | Define competencies, expected levels, behaviors, and position assignments. |
| Assessment | `assessments`, `assessment_indicator_results`, `scores` | Store competency assessments, checked behaviors, reviewer scores, decisions, and comments. |
| Gap | `competency_gaps` | Store the approved result, expected level, actual level, level gap, and `requires_idp`. |
| IDP plan | `idps`, `idp_items` | Store one yearly user plan and one plan item for each negative competency gap. |
| IDP activities | `idp_activities`, `idp_activity_updates` | Store multiple development activities and their progress updates or evidence. |
| Development choices | `learning_method_types`, `idp_learning_methods`, `learning_catalogs`, `learning_catalog_competency`, `learning_catalog_delivery_types` | Supply experiential, social, and competency-filtered formal learning choices. |

Preserve these core business rules:

- Calculate the canonical gap as `actual_level - expected_level`; create IDP only when the approved gap is negative.
- Use one `idp_item` per failed competency, not per failed behavior indicator.
- Allow many `idp_activities` under one item and require their total weight to equal 100% before submission.
- Auto-save editable IDP items as drafts; never overwrite submitted or approved items.
- Submit, approve, or reject each competency item separately. Other items may remain draft.
- Require a rejection comment and return only that item to `revision_required`.
- Filter Formal Learning through `learning_catalog_competency` so the employee sees only catalogs related to the failed competency.
- Support reviewer slots `supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3`; skip empty slots and determine the current reviewer from the assigned user IDs.

## Domain Model

### Roles

Use these canonical role keys:

- `employee`: บุคลากร
- `supervisor`: หัวหน้างาน / first reviewer
- `dept_head`: ผู้บังคับบัญชา / department reviewer
- `dean`: ผู้บริหารคณะ / final executive reviewer
- `hr`: งานทรัพยากรบุคคล
- `admin`: ผู้ดูแลระบบ

Normalize legacy aliases consistently:

- `manager` -> `dean`
- `manager_dept` -> `dept_head`

Do not add a new role alias in only one controller. Search all `normalizeRoleKey` implementations and frontend role maps.

### Organization And Competency Setup

- Store organization masters in `worklines`, `job_families`, `positions`, `levels`, `support_departments`, `support_works`, and `support_units`.
- Store competency definitions in `competency_types`, `competencies`, `competency_levels`, and `comp_level_indicators`.
- Store position assignments in `position_competencies`.
- Resolve expected competency levels through `App\Services\ExpectedLevelResolver`; do not duplicate its precedence rules in a controller.
- Treat `hr_expectations` as expectation configuration and keep workline/job-family scoping intact.

### Assessment Workflow

Use `AssessmentController` as the authority for write behavior.

The reviewer chain is configured by `users.supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3`. Missing steps are skipped. Do not require one specific evaluator slot merely because of the user's role. A non-admin user has a valid reporting line when at least one evaluator slot is assigned and every assigned evaluator has the correct role for that slot.

Status flow:

```text
draft / revision_required
  -> self_submitted      pending reviewer 1
  -> unit_evaluated      pending reviewer 2
  -> dept_evaluated      pending reviewer 3
  -> approved            all configured reviewers approved
```

Rules:

- Permit employee editing only in `draft` or `revision_required`.
- Require an assigned evaluator before ordinary users can self-assess.
- Allow only the reviewer assigned to the current step to approve or reject.
- Determine the active review step from the actual evaluator IDs, not from a hard-coded mapping between a reviewer role and one fixed step.
- On rejection, return the assessment and gap to `revision_required` and require a comment.
- Keep legacy `dean_approved` readable as approved where existing data requires compatibility, but write canonical `approved`.
- Store checked indicators in `assessment_indicator_results`, not the legacy `assessment_evidences` table.
- Update `assessments` and `competency_gaps` together inside a transaction.
- Store reviewer decisions/comments in `scores`.

Relevant tests:

- `tests/Feature/AssessmentReviewerChainTest.php`
- role dashboard tests under `tests/Feature/`

### Competency Gaps And IDP

Calculate:

```text
gap = actual_level - expected_level
requires_idp = gap < 0
```

Keep the canonical gap as a level difference. Do not replace it with `checked indicator count - expected indicator count`. Indicator counts may be supporting UI detail but must not change the stored gap or the rule that triggers IDP creation.

An IDP item is valid only when:

- the gap belongs to the authenticated user;
- `requires_idp` is true;
- the gap is negative; and
- the assessment status is `approved` or legacy `dean_approved`.

Use one IDP plan item per competency:

```text
1 negative competency gap
  -> 1 IDP item
  -> many development activities
```

Do not create separate IDP items for every failed behavior indicator. Use the relevant behavior indicator(s) to describe the development issue and expected behavior within the single competency-level plan.

Store plans in:

- `idps`: one current plan per user and active assessment year;
- `idp_items`: one plan item per selected competency gap/competency;
- `idp_activities`: one or more development activities under that competency plan, including learning method, catalog activity, dates, weight, and progress fields.

Submit and approve IDP plans per competency item:

```text
idp_items.status
  draft / revision_required
  -> submitted
  -> approved
  or revision_required when rejected
```

- Permit an employee to submit one complete `idp_item` while other competency items remain draft.
- Lock submitted and approved items against employee editing and background auto-save.
- Continue auto-saving only editable draft or revision-required items.
- Let the assigned supervisor approve or reject each submitted item independently.
- Require a rejection comment and show it when the employee edits the returned item.
- Derive the parent `idps.status` from its item statuses. Do not require every competency item to be ready before one item can be submitted.

For every activity under an IDP item, require the user to select one learning focus:

- **Experiential Learning:** Load the selectable dropdown from active `idp_learning_methods` rows whose `focus_type` is `experiential`.
- **Social Learning:** Load the selectable dropdown from active `idp_learning_methods` rows whose `focus_type` is `social`.
- **Formal Learning:** Load the selectable dropdown from active `learning_catalogs`.

Filter Formal Learning strictly by the failed competency of the current IDP item:

```text
idp_items.competency_gap_id
  -> competency_gaps.competency_id
  -> learning_catalog_competency.competency_id
  -> learning_catalogs.id
```

Never show every Learning Catalog entry across all competencies. Show only active catalog entries mapped to the current failed competency through `learning_catalog_competency`.

Treat the one-activity-per-item shape in the current employee form/controller as incomplete implementation. The business rule is one competency plan with multiple `idp_activities`; future persistence and UI changes must preserve multiple activities rather than overwriting the prior activity.

Use `app/Http/Controllers/Employee/IdpController.php` for draft/submit validation. Preserve:

- required fields on submit but relaxed fields on draft;
- end date not before start date;
- ownership and approval checks for competency gaps;
- catalog and learning-method foreign-key validation;
- transactional replacement of plan items and activities.

### Learning And Development Data

Use these distinct concepts:

- `learning_method_types`: focus/method categories used by IDP activities.
- `idp_learning_methods`: selectable experiential and social development tools.
- `learning_catalogs`: formal learning courses and activities.
- `learning_catalog_competency`: catalog-to-competency mapping.
- `learning_catalog_delivery_types`: canonical delivery-format master, including key, code, Thai/English names, order, and active status.

Do not write new code against the removed legacy table `idp_delivery_type_settings`. It may appear only in migration compatibility logic.

Current delivery keys are:

- `e_learning`
- `in_class`

When changing delivery codes, update `learning_catalog_delivery_types` through `IdpDeliveryTypeSettingController`. Do not silently restore hard-coded UI defaults when the requirement says no default value.

## Backend Conventions

- Validate every write request in its controller.
- Use named routes through Ziggy in Vue: `route('...')`.
- Wrap multi-table writes in `DB::transaction`.
- Use structured query builder/Eloquent operations instead of SQL strings.
- Return validation errors using Laravel validation so Inertia can map field errors.
- Use schema guards only for transitional compatibility. Do not use them to conceal a missing required migration indefinitely.
- Preserve Thai validation and success messages for user-facing actions.
- Keep camelCase in Inertia/UI payloads and snake_case in database/request fields, following the existing mapping.

## Frontend Conventions

- Keep admin and operational screens compact, scan-friendly, and consistent with existing components.
- Reuse existing classes and button patterns such as `card`, `btn`, `btn-p`, `btn-s`, `inp`, `sel`, and `tbl`.
- Keep page IDs aligned with `NAV_CONFIG`, `PAGE_TITLES`, and dashboard component conditionals.
- Do not maintain a second independent copy of server-owned data unless optimistic local behavior is required.
- Preserve Inertia scroll/state behavior where existing calls use `preserveScroll`, `useRemember`, or session storage.
- Show backend validation beside the relevant field.
- Verify responsive layouts around the existing `900px` breakpoints.
- Keep Thai labels exact unless the task explicitly requests copy changes.

## Database Changes

1. Check existing migrations and `php artisan migrate:status`.
2. Create a new forward migration for a database that teammates may already have migrated.
3. Do not edit an already-shared migration solely to alter a live schema; add a follow-up migration.
4. Make migrations safe for PostgreSQL and preserve foreign-key/index behavior.
5. Run the migration against the configured database when the task requires the table to exist now.
6. Confirm with `php artisan migrate:status`; DBeaver may also require refreshing the `Tables` node.
7. Add compatibility migration logic only when upgrading real legacy data.

Never assume a migration file means the table already exists.

## Verification

Choose the narrowest relevant checks, then broaden when shared behavior changes.

```bash
php artisan test tests/Feature/RelevantTest.php
npm run build
php artisan migrate:status
```

Use the full suite when changing shared assessment, role, user, dashboard, or schema behavior:

```bash
php artisan test
```

For local visual verification:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev -- --host 127.0.0.1
```

Open `/mock-sso` in the local environment, select the required role, navigate to the changed page, and verify:

- visible ordering and layout;
- initial/empty values;
- validation and disabled states;
- save behavior and refreshed server data;
- mobile/desktop overflow for substantial UI changes.

Do not submit destructive forms during visual verification unless the task requires it.

## Common Failure Modes

- Updating Vue without adding the prop in the role dashboard and `DashboardController`.
- Updating a table name in one controller while tests or migrations still target the legacy table.
- Treating `manager` or `manager_dept` as canonical persisted roles.
- Writing `dean_approved` instead of canonical `approved`.
- Reading checked indicators from `assessment_evidences`.
- Allowing IDP creation from an unapproved or non-negative gap.
- Editing an applied migration and expecting an existing PostgreSQL database to change.
- Passing feature tests with SQLite behavior while overlooking PostgreSQL-specific migration behavior.
- Verifying only the build and not the authenticated role page.

## Completion Checklist

- Confirm the behavior across database, backend, Inertia props, and Vue.
- Confirm role and status compatibility.
- Confirm migrations are actually applied when required.
- Run focused feature tests.
- Run `npm run build` for frontend changes.
- Inspect the real role page for visible UI changes.
- Report files changed, checks run, and any remaining warning or migration action.
