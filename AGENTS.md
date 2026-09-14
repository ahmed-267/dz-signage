# DZ Signage Agent Rules

Mandatory context for Cursor/AI work on this repository.

## Before Significant Work

1. Read the relevant `/docs` files (`PRD`, `ARCHITECTURE`, `DATA_MODEL`, `ROLES_AND_PERMISSIONS`, `ROADMAP`, `DEVELOPMENT`, `FIGMA_REFERENCE`).
2. Check current implementation before adding new abstractions.
3. Do not implement future phases unless requested.

## Product Terminology

Always preserve:

```
Media → Templates → Screen Designs → Playlists → Schedules → Publishing → Screens
```

Do not blur these concepts.

- Do **not** confuse **Templates** (reusable layouts) with **Screen Designs** (finished signage).
- Do **not** confuse **Schedules** (timing) with **Publishing** (deployment to Screens).
- Do **not** implement future roadmap phases unless explicitly requested.

## Figma

Before significant UI implementation:

1. Read `/docs/FIGMA_REFERENCE.md`.
2. Use Figma MCP to inspect the relevant screen/frame (Make file key `LV2LWOdsLyhfoG8sXFWlRV`).
3. Treat Figma as the UI/UX source of truth.
4. Treat `/docs` as architecture/business-logic source of truth.
5. Do not rely on memory when Figma is accessible.
6. Reuse existing React/UI components where possible.
7. Preserve responsiveness.
8. Preserve accessibility.
9. Preserve light/dark/system modes.

Main design: https://www.figma.com/make/LV2LWOdsLyhfoG8sXFWlRV/Follow-Markdown-File?t=HD09hia6xnKtdHpb-0&preview-route=%2Fapp%2Fmedia

## Architecture

- One Laravel backend.
- PostgreSQL primary database.
- Shared multi-tenant architecture (`workspace_id` planned).
- Enforce Workspace isolation (server-side).
- Super Admin is platform-level (not a workspace role).
- Player remains architecturally separate from Customer/Admin UI (`/player`).
- Future Templates and Screen Designs share one JSON rendering schema.
- Do not claim Horizon/Reverb/Cashier exist unless installed.

## Coding Rules

- Do not install packages without a real requirement.
- Do not duplicate existing services/components.
- Do not create speculative abstractions.
- Prefer simple maintainable architecture.
- Enforce authorization server-side.
- Validate user input.
- Use transactions where business operations require atomicity.
- Never commit secrets.
- Do not silently ignore errors.
- Do not remove tests simply to make CI pass.

## Testing

After meaningful changes:

- run relevant backend tests (`php artisan test`)
- run relevant frontend checks (`npm run lint`, `npm run types:check`)
- run build (`npm run build`) when UI/assets change
- run relevant Playwright flows (`npm run test:e2e`) when user journeys change

Fix failures before reporting completion.

## Documentation

When an architectural decision genuinely changes:

Update the appropriate `/docs` file.

Do not let documentation knowingly drift from implementation. Mark planned work as planned.

## Product Scope

DZ Signage is universal.

Do not make the platform restaurant-specific.
