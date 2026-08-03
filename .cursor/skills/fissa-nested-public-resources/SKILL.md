---
name: fissa-nested-public-resources
description: Implementation and review patterns for nested public child resources under suppliers (offerings is the reference). Use when adding sync APIs, slug redirects, public detail pages, admin nested forms, or offering-style media uploads.
---

# Fissa nested public resources (offerings reference)

Use this skill when implementing or reviewing features like **supplier offerings** (`aanbod`): a published child with its own slug, admin sync, media, and a public detail page under the parent supplier.

Reference implementation: `apps/api/app/Services/Admin/SupplierOfferingsWriter.php`, `SupplierOfferingSlugResolver`, `PublicSupplierOfferingController`, `apps/web/app/pages/leveranciers/.../aanbod/[offeringSlug].vue`.

---

## Design decisions (do not re-litigate)

| Topic | Decision |
|--------|----------|
| Empty sync wipe | API requires `clear_all: true` if rows existed; **no** browser modal — same permanence as deleting individual rows |
| Public detail fetch | **One** request; minimal `supplier` shell in offering JSON |
| Slug collisions | Hash suffix (`SupplierOfferingSlug`), not DB id in URL |
| Deleted offerings | Hard delete + storage cleanup; redirects may survive with `offering_id` null until one-time backfill |
| Legacy slugs | `supplier_offering_slug_redirects` + resolver; SSR 301 to canonical |
| Draft | Public 404 (not 403) |

---

## API checklist

1. **Routes:** Public GET throttled; admin `PUT` sync; owner `/api/me/suppliers/{id}/…`; media POST with `{supplier}` + `{offering}` binding check.
2. **Writer sync:** Transaction in controller; validate rows; `clear_all` gate; delete removed IDs with media cleanup; `recordSlugChange` on slug change only.
3. **Resolver:** `resolvePublished` — published row first, then redirect hops with visited-set cycle guard.
4. **Payloads:** `SupplierOfferingApiPayload` for admin/public rows; `SupplierPublicOfferingShellPayload` for embed (contact + `offerings[]` summaries only).
5. **Media:** `OfferingMediaUrlValidator` on sync; raster magic bytes on upload; `urlBelongsToOffering` on remove.
6. **Migrations:** Reversible where possible; conditional drops for idempotent deploys.
7. **Artisan:** `fissa:backfill-offering-slug-redirect-offering-ids` (+ Feature test) for prod legacy data once.

---

## Web checklist

1. **Page:** Single `useAsyncData` → `/api/suppliers/{supplierSlug}/offerings/{offeringSlug}`; `supplier` from `data.supplier`.
2. **Types:** `PublicSupplierOfferingPageSupplier` on `PublicSupplierOfferingDetail.supplier`; hero aside / contact composable accept union with `PublicSupplierDetail`.
3. **Admin form:** `prepareOfferingsForBundle()` returns `{ offerings, clearAll }`; expose for bundle save; auto `clear_all` when empty + had persisted ids.
4. **Nitro proxies:** Mirror Laravel paths for admin and `me` media routes; correct `laravel-proxy` depth.
5. **E2E:** Card → detail → back; legacy slug → canonical URL; mobile touch targets on changed CTAs.

---

## Tests (minimum)

| Area | Test |
|------|------|
| Sync | Slug from title; `clear_all` required; `clear_all` deletes |
| Public | Module off → 404; draft → 404; shell includes `offerings` |
| Redirects | Legacy slug + meta; max hops; cycle → 404 |
| Security | Admin hero 404 wrong supplier; owner gallery 403 |
| Media | Gallery upload + remove |
| Slug | Hash collision; chain collapse to active slug |
| Ops | Backfill command |

Run in container (**no sudo**):

```bash
docker compose exec api php artisan test --filter=SupplierOfferings
docker compose exec api composer run phpstan
docker compose exec web pnpm run typecheck
```

---

## Review anti-patterns (from past rounds)

- Second full `GET /suppliers/{slug}` on offering page “for shell”
- `window.confirm` or modal only for “delete all” but not for partial deletes
- Bundle save `return` when user cancels a modal — blocks unrelated dirty tabs
- Soft deletes on offerings with unique `(supplier_id, slug)`
- Accepting arbitrary image URLs in sync body (not under offering storage path)
- Skipping owner `/api/me/…` authz tests for media
- Shipping without PHPStan clean on touched PHP files

---

## Related

- Rules: `45-nested-public-resources.mdc`, `25-api-no-sql-leak.mdc`, `16-docker-compose.mdc`
- Review: `code-review-expert` (Fissa section)
- Security: `security-reviewer` for `/api/**` changes
