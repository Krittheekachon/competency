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

- Admin must create the EN-IDP account and assign its role and organization data before the first SSO login.
- The callback first matches `users.sso` against KKU `employeeId`, then KKU `userId`, and finally matches the verified KKU email.
- If an email match has no `users.sso` value, EN-IDP links the returned KKU identifier to that account.
- Successful SSO confirms `email_verified_at`; it does not overwrite the Admin-owned name, role, position, or reviewer chains.
- Unknown and inactive accounts are rejected. SSO never creates a user or grants a role automatically.

Keep `KKU_SSO_PASSWORD_LOGIN_ENABLED=true` during UAT if a local fallback is required. Set it to `false` in production to make KKU SSO the only login path.
