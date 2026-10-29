# Issues Purchasing - Approved Completion Plan

## Goal and scope

- Purchasing issue workflow, dedicated data, no Project/IT assets/printer/ink/assignment.
- Approved input: `pages/Issues_purchasing/`; preserve casing. 34 user-owned untracked files match external SHA-256 snapshot.
- Phase 1: auth/CSRF helper, core list/detail/category API migration, static SQL draft only.
- No live DB, SQL execution, global routing/menu, `pages/issues/`, root `task.md`, shared config, Git checkpoint/stage/commit.

## Decisions

- Canonical `list_issues.php`; MenuId 1329 server-side; standalone URL.
- Types Task/Support Activities; statuses To Do/In Progress/Done. Create only first two; creator-only quick Done; CanEdit any valid status with synchronized completion timestamp.
- Remove bulk Done and admin date editor. Multi-name batch atomic/idempotent. CanDelete all statuses.
- Stable category/subcategory FK; NO ACTION; reject deletion while referenced.
- Expanded plan: list/history/detail/categories/templates/issue writes/bounded export; no attachments or DDL. No write/test request without separate exact approval.

## Audit and state invariants

- See [read-only stateful bug audit](file:///C:/Users/IT%20-%2006/.gemini/antigravity-ide/brain/05d84589-d1bf-4049-a0f3-48b57c24881c/issues_purchasing_list_history_bug_audit.md): legacy GET includes IT/Project reads and DDL; old history contains mutation and asset/client flows. Current Purchasing slice has no state mutation, so transition audit is not applicable.
- Legacy category API reads/writes IT tables and asset/client configuration; do not use for Purchasing.
- Current pages/API call server-side MenuId 1329 CanView authorization; SQL Server metadata confirms `SMUserMs.UserName/GroupId`, `SMGroupTrustee.GroupId/MenuId/CanView`, `SMMenu.MenuId`. Bounded read-only query found CanView rows for MenuId 1329. No user data requested.
- Target state table `dbo.purchasing_issues`; proposed `rowversion`; stable category/subcategory IDs. Batch retries/file compensation remain CRUD-phase requirements.
- Current Purchasing issue/category/subcategory/template column metadata verified; SQL tool connection identity confirmed database `GG`. No DDL or writes executed. Actual SQLSRV row/date output remains unverified.
- Source audit: active listing includes every status except Done, including NULL/unknown, while visible status choices are only To Do/In Progress. Filter validation rejects Done under active scope. Business meaning and desired handling of unknown statuses unresolved; do not narrow query without decision.
- `issues_serverside.php` permits POST without CSRF although endpoint is read-only and current UI sends GET. No caller has been established for POST; either narrow to GET or add CSRF before relying on POST.
- Runtime: Chrome DevTools MCP `1.11.0` connected. After explicit `$conn` injection, authenticated DataTables active list and Done-only history return HTTP 200 valid JSON (`recordsTotal: 0`, `recordsFiltered: 0`, empty `data`). Unauthenticated API returned HTTP 401; invalid scope HTTP 400. Browser actual UI renders settled empty states, with no visible errors or stuck processing. Detail ID 1 safe 404; no real row was supplied. Search controls now have unique IDs and accessible Indonesian names. See [Chrome MCP recovery](file:///C:/Users/IT%20-%2006/.gemini/antigravity-ide/brain/05d84589-d1bf-4049-a0f3-48b57c24881c/chrome_devtools_mcp_recovery.md).


- Static gates: PHP syntax passed for all six module PHP files involved in endpoint, pages, auth helper, and assertion regression. PHP scope regression passed. `node --check` passed for extracted inline list/history JavaScript. Scoped `git diff --check` still pending.
- Source quality: `[!]` incomplete. Direct source review found existing maintainability concerns in endpoint size/format, duplicated detail handlers, history page ID validation, and broad task-owned legacy files not fully inspected. No new blank/generated search warning after approved fix. See [quality gate](file:///C:/Users/IT%20-%2006/.gemini/antigravity-ide/brain/05d84589-d1bf-4049-a0f3-48b57c24881c/issues_purchasing_quality_gate.md).



## Chrome DevTools MCP repair

- Root cause: npm `chrome-devtools-mcp@1.12.0` package has an internal entrypoint mismatch: main imports `logDisclaimers` from index, which does not export it. Rebuilt only its verified npx cache; fresh install reproduced defect. Registry latest remained 1.12.0.
- Tested `1.11.0` CLI `--help` successfully under Node v22.22.2. User approved exit/reopen; global config now changes only Chrome MCP pin to `1.11.0`. No other MCP entries changed.
- **Passed:** live Chrome DevTools MCP 1.11.0 connection after restart; local navigation works. App shell loads, but list API returns a safe 500 authorization-verification response. MCP repair evidence/rollback: [Chrome MCP recovery](file:///C:/Users/IT%20-%2006/.gemini/antigravity-ide/brain/05d84589-d1bf-4049-a0f3-48b57c24881c/chrome_devtools_mcp_recovery.md).


## Code-quality checklist

- [ ] Connection scope fix: `issues_serverside.php`/`get_issue_detail.php` accept explicit `$conn`; handler PHPDoc describes connection parameter; focused assert regression; authenticated HTTP checks.
- [ ] Generated DataTables search inputs on both pages need unique module-prefixed IDs and accessible Indonesian labels; verify both work independently, no duplicate IDs, keyboard focus/search, responsive/theme, Node syntax and full regression. Audit: no workflow state mutation; issue list/detail auth unaffected; generated control changes only accessible name and selector target.
- [ ] `issue_access.php`: shared permission/CSRF helper only; explicit return shapes; descriptive names; PHPDoc in Bahasa Indonesia; one statement per line.
- [ ] `issues_serverside.php`: bounded read API; auth before queries; explicit projection; sort allowlist; stable order; sanitized errors.
- [ ] `get_issue_detail.php`: authorized record read; explicit projection; no IT/Project joins; safe failures.
- [ ] `data_categories.php` and `get_sub_category_config.php`: auth+CSRF+validation before SQL; FK integrity; transactions; no asset/client config.
- [ ] `list_issues.php`, `riwayat_issues.php`, `manage_categories.php`: wire 1329/CSRF and only necessary page integration edits.
- [ ] SQL migration: create-only, readable, verified fields, safe constraints/FKs/indexes; no destructive or executable web runner.
- [ ] Developer README/task: supported behavior, routes/auth, unapplied migration, blocked evidence.
- [ ] Reopen every touched/new file; check format, responsibility, naming, required docs, no unrelated edits/placeholders.
- [ ] Gates: php -l, node --check touched inline scripts, independent source-quality review, git diff --check. Inspect untracked files directly.
- [ ] Focused auth/CSRF/API tests; runtime/DB/browser checks stay blocked if test environment unavailable.

## Baseline and rollback

- Root `task.md` is user-owned and this task ledger entry is task-related; preserve all unrelated root-ledger content and other workspace changes.
- Exact 38-file snapshot: `C:\Users\IT - 06\.gemini\antigravity-ide\brain\05d84589-d1bf-4049-a0f3-48b57c24881c\scratch\baseline_full_20261022_155907\`; hashes verified before this task's edits.
- Verified manifest: `C:\Users\IT - 06\.gemini\antigravity-ide\brain\05d84589-d1bf-4049-a0f3-48b57c24881c\scratch\baseline_full_20261022_155907\manifest.json`.
- Roll back only exact task-owned paths; remove only task-created files. Never reset/stash/stage/commit.

## Active item and validation

- Current: user approved minimal PHP scope fix in both list and detail handlers. Evidence shows database bootstrap at file scope while endpoint handlers are functions; PHP function scope does not inherit file-scope variables. Immediate pre-edit SHA-256 baselines captured outside repo. Add explicit `$conn` parameters, retain fail-closed auth, test with assert script and authenticated GET. No DDL/writes/stage/commit.
- Runtime/root cause: authenticated list shell returns 200. One approved request with fixed-label telemetry identified `connection_missing`. Moving `koneksi.php` before helper did not change API response; a temporary bootstrap-entry marker confirmed edited source executed. All temporary logs removed; `issue_access.php` SHA-256 exactly restored. Root cause confirmed: endpoint function scope did not receive file-scope `$conn`; explicit parameter injection is approved. Authorization remains fail-closed. See [diagnosis](file:///C:/Users/IT%20-%2006/.gemini/antigravity-ide/brain/05d84589-d1bf-4049-a0f3-48b57c24881c/issues_purchasing_api_root_cause.md).
- Database: bounded current GG reads map IT7 to GroupId 1 and CanView for MenuId 1329; reader SELECT grants verified. No DDL or writes.
- Static: helper PHP syntax passed after cleanup. Endpoint lint/browser regression and source-quality review remain pending.
- Audit: active listing includes non-Done unknown/NULL statuses but UI options only To Do/In Progress; semantics unresolved. Endpoint supports POST without CSRF while UI sends GET; caller contract unresolved.
- Approved diagnostic done: temporary fixed-label logs existed for exactly one user-authorized read-only GET, then were removed. No access policy change.
- Audit/evidence: flow is authenticated GET â†’ session username â†’ unique `SMUserMs` mapping â†’ MenuId 1329 CanView â†’ bounded read-only list JSON. No mutation/state transition; concurrency, retry, and recovery rules do not apply. Apache telemetry observed `$conn === null`; include-order change plus fresh-source confirmation did not change HTTP 500. Root cause after connection include remains unresolved; do not weaken authorization. No downstream record mutation.
- Code-quality/validation checklist: `issue_access.php` authorization only; `issues_serverside.php` list endpoint/bootstrap only. No abstractions or new behavior. PHPDoc unchanged. One statement per line; no compressed code. Gates: `php -l` both files, one approved GET verifying response, SQL read-only behavior, `git diff --check` scoped to touched files, duplicate ID not applicable to this bootstrap-only fix, direct source-quality review. Browser theme/focus/responsive and detail/history remain pending.

## Approved completion files and code-quality gate

- `issue_access.php`: server-side MenuId 1329 CanView seam; fail-closed unique user/trustee resolution; PHPDoc Bahasa Indonesia; one statement per line.
- `issues_serverside.php`: bounded authenticated Purchasing read API; explicit projections; validated filters; allowlisted sort; deterministic paging; safe errors; no DDL.
- `get_issue_detail.php`: authorized single Purchasing row read; strict ID; explicit columns; no Project/IT join, DDL, or attachment path; safe 404/500 response.
- `data_categories.php`/config: Purchasing-only category/subcategory filter reads if needed; no asset/client config or mutation tests without separate approval.
- `get_sub_category_config.php`: inspect direct consumers; never expose legacy asset/client flags to Purchasing view.
- `list_issues.php`, `riwayat_issues.php`: standalone authenticated read-only Purchasing views; active theme; no legacy write/Project/asset controls.
- No SQL migration or DDL: user confirms Purchasing tables already exist and ran prior SQL manually.
- Per-file check: responsibility, descriptive names, Bahasa PHPDoc for changed nontrivial functions, one statement per line, readable SQL/markup, error/rollback paths; reopen every task-owned file.
- Gates: php -l every touched PHP file; node --check every touched inline executable script; independent static source-quality review; git diff --check plus direct untracked-file inspection.
- API tests: auth decisions, ID/filter bounds, safe failure, paging/sort/response contract. No live DB writes or mutation requests absent separate exact approval.
- Browser: Chrome DevTools safe session, affected list/detail/category interactions, loading/empty/error, Console/Network, focus and responsive behavior. No claim if unavailable.
- Unverified: actual rows/status distribution, DB constraints/indexes, SQLSRV runtime date representation and authorization outcomes; report blocked where absent.

## Current slice additions

- `issues_serverside.php`: search actual Purchasing issue fields only; GET and POST DataTables compatibility; no DDL or mutation; safe close/fail paths.
- `get_issue_detail.php`: remove legacy DDL/client/asset/project resolver; return required Purchasing fields only; never expose attachment values.
- `list_issues.php`: replace 503 shell with small standalone viewer; server-side auth before connection/layout; only authorized read UI; no add/edit/delete/template/export controls in current slice.
- `riwayat_issues.php`: read-only history shell using `scope=history`; Purchasing-only list contract; no status writes, assignment, employee lookup, or export.
- `issue_access.php`: direct functions, helpers only as needed; explicit list/detail auth gates; centralized helper is already current module-owned source.
- Source quality: readable PHP, one statement per line, safe names, Bahasa PHPDoc for changed nontrivial functions; no minification. Gate each PHP file with `php -l`, test pure validators with one assert script, and audit templates/control references separately.
- Browser/API: no browser/endpoint run until DB tool isolation is verified; no live dataset row can be used if tool identity is not established. After safe browser open, check unauthenticated page and valid authenticated list/history only if available; never POST.
## Expanded plan quality checklist

- [x] `issues_serverside.php`: Purchasing-only read API; bounded paging, filters, status-scope consistency, scoped/filtered counts, allowlisted sorting, parameterized SQL, escaped text, completion timestamp.
- [x] `get_issue_detail.php`: authorized Purchasing single-record detail projection; strict ID, safe error shape and normalized dates.
- [x] `list_issues.php`: restored read-only page, theme-aware header/modal, active filters, detail UI.
- [x] `riwayat_issues.php`: Done-only GET history page, type/date filters, detail modal only.
- [ ] `issue_access.php`: current unique user/trustee MenuId 1329 CanView helper; focused auth behavior tests unavailable.
- [ ] Every changed PHP source readable, one statement per line, descriptive names, Bahasa PHPDoc on nontrivial changed functions; reopen all files and blocks.
- [x] `node --check` extracted inline list/history scripts.
- [x] Focused runtime: authenticated active/history endpoint HTTP 200 with valid DataTables JSON; empty states settle correctly; unauthenticated API HTTP 401; invalid scope HTTP 400.
- [x] Browser: active/history list UI actual response render, processing/error states, no duplicate IDs, focus/search functionality, unique accessible labels, mobile/desktop overflow, card header theme contrast, Console no errors, network list response HTTP 200.
- [x] Detail modal: visible safe not-found state tested with client-side UI setup; approved endpoint ID 1 returns safe 404. Real-record content path remains untested because user supplied no sample row and no test data writes approved.
- [!] Static source-quality review, scoped `git diff --check`, full task-owned file verification, and final change review remain pending.

## Accepted slice record — 2026-10-23

- User accepted verified read-only list/history slice. Explicit `$conn` injection fixes handler scope; generated active/history search controls have unique IDs and accessible Indonesian labels.
- Fresh authenticated active/history API requests returned HTTP 200 valid DataTables JSON with zero rows; unauthenticated API 401; invalid scope 400; detail ID 1 safe 404. Browser verified settled empty states, search focus/filter, no duplicate IDs, no desktop/mobile horizontal overflow, themed headers and no Console errors. PHP lint, assert check, extracted inline `node --check`, module `git diff --check` passed.
- Real populated detail/list behavior remains untested because no rows were visible; no test writes or DDL. This is not full CRUD or full application readiness.
- Source-quality review remains `[!]`: assertion is shallow, two statements share one line, all task-owned legacy files not reviewed. Root ledger contains unrelated prior whitespace findings outside task range.
- User requested task-only commit, but safe per-task checkpoint/staging boundary was not created; no commit/stage performed. Preserve task changes and seek safe isolation before future Git finalization.
- Evidence artifact: [accepted read-only slice](file:///C:/Users/IT%20-%2006/.gemini/antigravity-ide/brain/05d84589-d1bf-4049-a0f3-48b57c24881c/issues_purchasing_accepted_readonly_slice.md).
 No writes/DDL.


