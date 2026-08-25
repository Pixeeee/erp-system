# Administrator Company-Scoped Platform Users Design

Administrator remains the global BuilderX authority. Company admins remain scoped to one company. Administrator Platform Users will support both global system users and company-scoped users.

Use shared company-scoped tables instead of per-company table names. The first visible slice is `project_company_user`, supported by company-scoped roles, groups, permissions, and assignment link tables. Each row stores `company_key` as the Firebase Firestore document ID and `company_key_hash` for indexed lookup.

The Administrator Users screen gains a Company Users mode. Administrator selects a company, views its users, creates/edits users, assigns company roles, assigns branches/projects under that company, resets passwords, and changes status. The existing global BuilderX user CRUD remains available and unchanged.

Server writes use ADODB transactions, fixed table names, parameterized SQL, audit logs, direct read-back verification, and redirect-based rehydration. UI uses the existing two-panel Platform layout: left main panel table, right side panel form/summary, panel body scrolling only, no nested bordered cards.
