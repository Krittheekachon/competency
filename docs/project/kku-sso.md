# KKU Single Sign On (SSONext)

EN-IDP integrates with KKU SSONext by redirecting the browser to KKU, exchanging the returned one-time code on the server, and loading the authenticated KKU profile. The application does not store the SSO access token.

## URLs to register with KKU

Replace `https://en-idp.example.kku.ac.th` with the deployed EN-IDP origin.

- Redirect URL: `https://idp.enit.kku.ac.th/auth/kku/callback`
- Redirect Logout URL: `https://idp.enit.kku.ac.th`

KKU provides the App ID, Client ID, and Client Secret after these URLs are registered.

## UAT configuration

```dotenv
APP_URL=https://idp.enit.kku.ac.th
KKU_SSO_ENABLED=true
KKU_SSO_APP_ID=provided-by-kku
KKU_SSO_CLIENT_ID=provided-by-kku
KKU_SSO_CLIENT_SECRET=provided-by-kku
KKU_SSO_WEB_URL=https://sso-uat-web.kku.ac.th
KKU_SSO_API_URL=https://sso-uat-api.kku.ac.th
KKU_SSO_REDIRECT_URL=https://idp.enit.kku.ac.th/auth/kku/callback
KKU_SSO_PASSWORD_LOGIN_ENABLED=true
```

## Production configuration

```dotenv
APP_URL=https://en-idp.example.kku.ac.th
KKU_SSO_ENABLED=true
KKU_SSO_APP_ID=provided-by-kku
KKU_SSO_CLIENT_ID=provided-by-kku
KKU_SSO_CLIENT_SECRET=provided-by-kku
KKU_SSO_WEB_URL=https://ssonext.kku.ac.th
KKU_SSO_API_URL=https://ssonext-api.kku.ac.th
KKU_SSO_REDIRECT_URL=https://en-idp.example.kku.ac.th/auth/kku/callback
KKU_SSO_PASSWORD_LOGIN_ENABLED=false
```

After changing environment values, run `php artisan config:clear` (or rebuild the production config cache).

## Account matching and access control

- The callback matches `users.sso` against KKU `employeeId`, then `users.sso_user_id` against KKU `userId`, and finally the verified KKU email.
- A new KKU identity creates an active EN-IDP user with the canonical `employee` role. The user can sign in, but assessment readiness remains blocked until Admin completes the internal structure, expected level, competencies, and reviewer chains.
- Inactive existing accounts remain blocked.
- Identity fields (name, email, phone, and SSO identifiers) are refreshed at login.
- Structural fields from SSO are stored separately (`sso_workline`, `sso_position_name`, `sso_position_type_name`, `sso_level_id`, and `sso_level_name`). They never overwrite the Admin-owned `workline`, `position_id`, `level_id`, organization path, role, or reviewer chains automatically.
- `workline` is normalized through `SsoWorklineNormalizer::structureType()` to exactly `academic` or `support`; an unknown value remains unmapped for Admin review.
- A changed structural profile creates one deduplicated row in `sso_profile_changes` and appears on the Admin user dashboard.

## Admin decisions for changed SSO data

- **คงข้อมูลเดิม** resolves the change and leaves all internal IDs and workflow configuration unchanged.
- **ใช้ข้อมูลใหม่และจัดโครงสร้าง** keeps the current workflow untouched, marks the change as waiting for structure, and opens the existing user editor. Saving the completed structure resolves the change.
- **เปลี่ยนเป็นชื่อใหม่** updates the display name on the same internal position ID, records the old name as an alias, and preserves competencies and reviewer flows attached to that ID.

`position_titles` is the reusable position catalog. One title may be placed in several organization units through separate `positions` rows. SSO-discovered levels are added under their normalized workline with `expected_level = null` until Admin sets it.

## Round snapshot behavior

When an employee first loads or writes assessment data for a round, EN-IDP stores the operational profile in `user_round_profiles` and both reviewer chains in `user_round_reviewer_steps`. Assessment, FC selection, and IDP approvals then use that round snapshot. A later SSO or Admin structure change applies to a future round and does not rewrite an in-progress assessment or IDP.

HR may end an active round manually. Date windows also prevent new assessment work after their configured deadlines. IDP work remains linked to its original `assessment_round_id` and can continue after the round closes or a new round becomes active.

Keep `KKU_SSO_PASSWORD_LOGIN_ENABLED=true` during UAT if a local fallback is required. Set it to `false` in production to make KKU SSO the only login path.
