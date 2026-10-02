# Changelog

## 2026-07-18

- Audit detail resep now loads local rows first, then checks ProInt incrementally in batches of five.
- Added visible audit progress and per-row status updates.
- New filter or pagination request cancels stale audit batches.
- ProInt candidate lookup uses No CP and restricts routes to approved PADDRY routes.
- User-facing route labels show `rtgname`; `rtgcode` remains internal.

- Pagination now always shows first and last pages, nearby pages, ellipses, and previous/next controls.

- Audit summary now scans all filtered pages in five-row batches; pagination preserves active progress, accumulated summary, and cached row results.

- List Resep Excel export now offers Ringkasan Saja or Beserta Detail Resep; summary mode exports one row per recipe and skips detail queries.

- Audit table now displays local Status Resep from status_resep_lipat, including Master Resep values.

- Audit export now offers cached Ringkasan Cepat after scan completion or full detail export; debug page explicitly loads local SweetAlert2.

- Cus Color autofill now prioritizes selected ProInt recipe, then a unique smprodtechdata value by color, then the colordesc prefix; ambiguous color mappings require user selection.

- Added PPC Guidance-only global toggle for ProInt metadata visibility across Input, View/print PDF, and standard Excel exports; hidden input layout remains balanced and metadata continues to be stored.

- Balanced hidden-metadata View/print layout into two equal six-row columns.

- Added local Cus Color to debug audit list and batch payload.

- Replaced full POST debug logging in save_resep.php with structured daily error-only logs and safe request IDs.
