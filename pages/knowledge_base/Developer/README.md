# Knowledge Base

## Purpose

Module `pages/knowledge_base/` menyimpan artikel tutorial internal.

## Scope

- Artikel berisi nama artikel, kategori, Bagian, dan message Summernote.
- Bagian selalu dari akun pembuat, termasuk admin.
- Kategori dibuat inline saat submit dan scoped per Bagian.
- Sidebar/menu tidak diubah.

## Access Rules

- User satu Bagian bisa lihat, edit, dan soft delete artikel Bagian sendiri.
- Admin `GroupId = 1` bisa lihat, edit, dan soft delete semua artikel.
- User beda Bagian tidak bisa akses list/detail/edit/delete artikel.

## Files

- `index.php`: AdminLTE page markup and plugin includes.
- `assets/js/knowledge_base.js`: DataTables, Select2, Summernote, actions, and submit handling.
- `kb_helpers.php`: auth context, trustee checks, logging, sanitization, category helper.
- `api_articles.php`: legacy DataTables server-side JSON.
- `archive.php`: folder archive JSON for Bagian, kategori, and artikel.
- `categories.php`: Select2 category lookup.
- `get_article.php`: detail/edit preload JSON.
- `save_article.php`: create/update JSON.
- `delete_article.php`: soft delete JSON.
- `upload_image.php`: Summernote image upload and compression.
- `migrations/001_create_knowledge_base.sql`: SQL Server tables/indexes.

## UI

- Archive navigation: Bagian folder → Kategori folder → Artikel file.
- Admin root shows all Bagian folders.
- Regular users see only their own Bagian folder.
- Breadcrumb, back button, folder/article search, and empty state are client-side archive controls.
- Article actions remain view, edit, and soft delete under existing trustee checks.
- Create Article opened inside a category folder preselects that category.
- Summernote image preview supports click, wheel zoom, and mouse pan.

## API

- `archive.php`: read-only archive JSON for `root`, `bagian`, and `category` levels.
- `api_articles.php`: legacy flat DataTables endpoint retained for compatibility but no longer used by main page.

## Database

- `dbo.knowledge_base_categories`
- `dbo.knowledge_base_articles`

Run migration manually after approval. No schema SQL executed by agent.

## Upload

- Max input 2 MB.
- MIME checked by `finfo`.
- Stored under `uploads/knowledge_base/YYYY/MM/`.
- GD compresses/resizes jpg/png/webp when available.
- GIF copied without compression to preserve animation.
- Summernote stores URL, not base64.

## Logging

Errors write JSON lines to root `logs/error-YYYY-MM-DD.log` with request ID.

- Create/edit modal focuses on article content; sharing uses separate `Share` action and modal.
- Article owner or Admin can update shared Bagian targets.
- Archive and global search use visibility targets.

## Verification

- Run `php -l` for PHP files.
- Run `node --check pages/knowledge_base/assets/js/knowledge_base.js`.
- Run `git diff --check`.
- Browser test skipped by request; user verifies UI manually.
