# Decisions

This file records meaningful architecture decisions visible in the repository. Historical context is recorded only when available from repository files.

## Use `user_reviewer_steps` As Runtime Reviewer Source

Decision: Runtime approval permissions use ordered rows in `user_reviewer_steps`.

Status: current implementation.

Implications:

- Missing rows mean the user has no configured chain.
- Reviewer chains can have flexible length.
- Approval steps are not tied to fixed role slots.
- `users.supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3` must not be reintroduced.

Historical context not available in the repository.

## Faculty Approval-Only Compatibility (2026-11-05)

Decision: Following the project owner's instruction to prioritize the existing workflow described in the pre-merge review, faculty chains with no eligible scorer may finish approval using the employee's self-assessment score. This supersedes the earlier guard that rejected every such chain.

- Approval still requires the configured reviewer and the correct pending step. Approval-only reviewers do not gain scoring rights; their supplied indicator payload cannot replace the self score.
- If eligible scorers are present in the chain, their scores must be complete before final approval. MC scoring permissions are unchanged: a dean is not automatically allowed to score MC for every role.
- Preserve recognized legacy `users.workline` values as the primary classification, including the frozen round profile. Canonical title classification is a fallback for other display names; missing/unclassified titles must not disable legacy worklines. Unrecognized values alone grant no scoring authority.
- IDP draft autosave remains enabled after the form becomes complete, but only when the payload changes; it remains debounced and stops for locked plans.
- Admin workline catalogs continue reading persisted worklines. IDP uniqueness preflight continues rejecting duplicate data without deleting or merging it.
- IDP readiness batches coverage and failed-gap lookups. Submission always rechecks readiness in its transaction; no persistent readiness cache substitutes for that check.

## Management Workline Coverage (2026-11-05)

Decision: Support the existing management organization workline consistently through the canonical position catalog, organization placement, user affiliation, SSO normalization, HR competency mapping and analytics.

- Canonical type is `management`; recognized names are `บริหาร`, `สายบริหาร` and `สายงานบริหาร`. The display label is `สายงานบริหาร`. Existing workline rows and IDs are not renamed or merged.
- Management titles are selected separately from academic/support titles. Placement APIs reject a catalog title from another classified workline. Unknown types are not silently labeled support.
- A management workline is an affiliation/category, not a role or a permission grant. Existing scorer rules and the academic affiliation requirement for deputy/assistant deans remain unchanged. Approval-only compatibility still applies.
- The organization hierarchy continues using existing workline/job-family/position records and their IDs. No migration or automatic reclassification of existing users/positions is needed or performed.
- Existing SSO review decisions remain in place. This structural coverage does not resolve the separately observed pre-review change of `users.position_title_id`; that behavior requires its own review.

## Organization Chart Owns Catalog Workline Choices (2026-11-05)

Decision: The owner clarified that choices and labels must come from the actual chart records, not a fixed academic/support/management list. This supersedes the previous fixed catalog selector/display labels.

- `position_titles.workline_id` references the existing `worklines.id`. Catalog add/filter/placement uses these IDs and exact current names. Any workline added in the chart is immediately available; renaming preserves identity.
- `workline_type` remains compatibility/scoring metadata and is not the source of organization choices. Arbitrary new names do not gain scoring rights.
- The forward migration backfills from a unique placement, then an exact previous canonical label, then a unique known alias. Ambiguous/unmatched titles remain unbound; Admin can explicitly choose an actual chart workline. Used titles cannot move across their existing placements.
- Unscoped SSO identities are not guessed into one of several same-name workline-specific titles. Deleting a workline referenced by catalog titles is rejected.
- Existing users, reviewer chains, approved results, snapshots and IDP plans are not remapped by this change. Only the new nullable workline link and its indexes are added.

## Use Canonical Position Titles For Assessment Configuration

Decision: Competencies, FC selection rules, and expected levels are owned by the canonical position in `position_titles`, not by an organization-specific `positions` row.

Status: current implementation with compatibility columns for historical records and the existing organization UI.

Implications:

- Users with the same canonical position receive the same competencies across departments, works, and units.
- Organization hierarchy is for filtering, reporting, and reviewer-template assistance only.
- SSO may assign `users.position_title_id` immediately from `positionName` without waiting for organization placement.
- Historical `position_id` values remain readable during migration but must not be the source of truth for new assessment configuration.

Historical context: confirmed business rule on 2026-10-24.

## Split Reviewer Chains By Workflow Type

Decision: Reviewer chains and reviewer templates are separated by `chain_type`.

Known values:

- `assessment`
- `idp`

Status: current implementation via `2026_06_30_000000_split_reviewer_chains_by_flow_type.php`.

Implications:

- Changing an assessment chain must not remove IDP chain data.
- Changing an IDP chain must not remove assessment chain data.
- UI and backend code must pass the intended chain type.

Historical context not available in the repository.

## Treat Templates As Configuration Helpers

Decision: `reviewer_chain_templates` are helpers for filling chains, not runtime permission authority.

Status: current implementation.

Implications:

- Applying a template writes rows to `user_reviewer_steps`.
- Runtime checks read `ReviewerChainResolver`, not templates directly.
- Template assignment metadata is useful for admin management and display.

Historical context not available in the repository.

## Use `ReviewerChainResolver`

Decision: Centralize reviewer chain reads in `App\Services\ReviewerChainResolver`.

Status: current implementation.

Implications:

- Assessment, FC topic approval, IDP approval, dashboards, and notifications should share the same resolver semantics.
- Do not duplicate reviewer lookup rules across controllers.

Historical context not available in the repository.

## Use Canonical Gap Calculation

Decision: Store gap as `actual_level - expected_level`.

Status: current implementation and business invariant.

Implications:

- Negative values require IDP.
- Indicator counts may explain detail but must not replace the canonical gap.

Historical context not available in the repository.

## Create One IDP Item Per Competency Gap

Decision: A negative competency gap creates one IDP item, and activities sit under that item.

Status: partially implemented; tables support it and repository guidance requires it.

Implications:

- Do not create one IDP item per failed behavior indicator.
- Multiple development activities should remain possible under the same competency item.

Historical context not available in the repository.

## Preserve Canonical And Legacy Status Compatibility

Decision: Use canonical `approved` for completed assessment writes, while reading legacy `dean_approved` where needed.

Status: current compatibility behavior.

Implications:

- New code should write `approved`.
- Existing read paths may map `dean_approved` to approved.

Historical context not available in the repository.

## Filter Formal Learning By Competency

Decision: Formal Learning choices for IDP must be filtered through `learning_catalog_competency`.

Status: current requirement and implementation boundary.

Implications:

- Employees should not see every catalog item for every failed competency.
- The filter path is `idp_item -> competency_gap -> competency -> learning_catalog_competency -> learning_catalog`.

Historical context not available in the repository.
