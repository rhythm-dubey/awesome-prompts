# Modernization Migration Plan

## Executive Recommendation

This codebase should not be migrated as a single big-bang rewrite. The safer path is:

1. Move to a modular monolith first.
2. Introduce a modern frontend gradually.
3. Extract only high-change or high-risk domains into services when operationally justified.

Recommended target:

- Frontend: Next.js
- Backend transition phase: Laravel retained temporarily as legacy/core API
- End-state backend: domain-oriented services, starting with NestJS or Node/TypeScript APIs for new modules
- Data layer: keep MySQL initially, add Redis, S3-compatible object storage, queue/event bus

## Why This Target

### Best-fit option for this project

The current application is:

- large
- heavily server-rendered
- deeply business-rule-driven
- full of admin workflows
- rich in forms, tables, dashboards, and document/payment flows

That makes pure MERN less suitable as the primary migration target because:

- MongoDB is not a natural fit for this relational domain
- many workflows depend on transactional consistency
- existing data model is strongly relational
- admin/reporting flows are easier to preserve on SQL

### Preferred target stack

#### Option A: Next.js + modular services on Node/TypeScript

Best overall choice.

- `Next.js` for public site, authenticated portal UI, admin UI
- `NestJS` or `Express/Fastify + TypeScript` for APIs
- `MySQL/Postgres` for relational data
- `Prisma` or `TypeORM` for service-level schema access
- `Redis` for cache, queues, rate limits, sessions where needed
- `S3/R2` for files and media
- `BullMQ` or equivalent for jobs
- `Stripe`, `Pusher/Ably`, email providers, and auth providers integrated service-by-service

#### Option B: Next.js + Laravel API backend

Best transitional architecture.

- Fastest to deliver
- Lowest migration risk
- Lets you modernize UI first
- Lets you delay service extraction until boundaries are proven

#### Option C: Full microservices immediately

Not recommended as phase 1.

Reasons:

- current code has weakly enforced domain boundaries
- controllers are large and cross-cutting
- test coverage is too thin for safe simultaneous service decomposition
- operational maturity is not yet visible from the repo

## Migration Strategy

## Phase 0: Stabilize Before Rebuild

Duration: 2 to 4 weeks

Goals:

- reduce operational risk before migration
- freeze architecture drift
- establish observability and test baselines

Work:

- remove public operational routes like `clear_cache`, `migrate`, and HTTP cron endpoints
- fix route namespace issues so route inventory tools work
- rotate any exposed secrets and remove sensitive artifacts from web-accessible paths
- introduce baseline monitoring, request logging, and error tracking
- add high-value regression tests around:
  - auth
  - property creation/edit flows
  - lease generation
  - payment flows
  - STR booking
  - HOA dues
  - business proposals and milestones

Deliverables:

- hardened Laravel baseline
- migration-safe test suite
- route/domain inventory with owners

## Phase 1: Modular Monolith Refactor in Current Repo

Duration: 4 to 8 weeks

Goals:

- carve explicit domain boundaries without changing runtime topology yet
- reduce controller sprawl

Refactor the Laravel app into domain folders conceptually like this:

```text
app/
  Domains/
    Auth/
    Properties/
    Leasing/
    Payments/
    WorkOrders/
    Communications/
    STR/
    HOA/
    BusinessMarketplace/
    Admin/
    CMS/
    Copilot/
  Support/
    Shared/
    Infrastructure/
    Files/
    Notifications/
    Billing/
  Http/
    Controllers/
    Middleware/
    Requests/
```

Within each domain:

- `Actions/`
- `Services/`
- `DTOs/`
- `Policies/`
- `Repositories/` if needed
- `Models/` only if you choose domain-local models later

Immediate controller split targets:

- `Account\PropertyController`
- `Account\StrController`
- `Account\LeaseController`
- `Account\DashboardController`
- `Admin\InboxController`

Route restructuring target:

```text
routes/
  web/
    public.php
    auth.php
    account.php
    owner.php
    str.php
    hoa.php
    business.php
    support.php
    webhooks.php
  api/
    auth.php
    properties.php
    leasing.php
    payments.php
    workorders.php
    str.php
    hoa.php
    business.php
    communications.php
  admin/
    core.php
    security.php
    properties.php
    users.php
    reports.php
    documents.php
    str.php
    cms.php
```

Deliverables:

- bounded contexts visible in code
- thinner controllers
- route segmentation
- reusable service layer for later extraction

## Phase 2: Frontend Modernization With Next.js

Duration: 6 to 12 weeks

Goals:

- replace Blade-driven UX gradually
- preserve backend behavior
- improve maintainability and UX consistency

Recommended rollout order:

1. Public marketing/CMS pages
2. Public property search and listing pages
3. STR public browse/listing/checkout
4. Auth screens and account picker
5. Admin UI and dashboards
6. Account portal modules domain by domain

### Frontend architecture

Use one Next.js repo or one `apps/web` app in a monorepo.

Suggested structure:

```text
frontend/
  apps/
    web/
      src/
        app/
          (public)/
          (auth)/
          (dashboard)/
          admin/
          stays/
          hoa/
          business/
        components/
          ui/
          forms/
          tables/
          charts/
        features/
          auth/
          properties/
          leasing/
          payments/
          workorders/
          communications/
          str/
          hoa/
          business/
          admin/
        lib/
          api/
          auth/
          utils/
          schemas/
        styles/
        types/
      middleware.ts
      next.config.js
  packages/
    ui/
    config/
    types/
    api-client/
```

### Frontend rules

- use App Router
- use server components where practical
- use client components only for interactive areas
- centralize API client logic
- use React Query or SWR for client data sync where needed
- use Zod for form/schema validation
- use a proper component system for admin tables/forms

### During transition

- keep Laravel as backend API
- front Next.js pages first for public and new portal routes
- proxy legacy pages until replaced
- use shared auth cookies or token exchange

Deliverables:

- modern UI shell
- reusable component library
- progressive replacement of Blade views

## Phase 3: Create an API Gateway / Backend-for-Frontend Layer

Duration: 3 to 6 weeks

Goals:

- decouple frontend from Laravel internals
- standardize auth, error handling, pagination, and response shape

Introduce either:

- Next.js BFF routes for UI-specific orchestration, or
- a dedicated API gateway service

Responsibilities:

- session/token validation
- route aggregation
- response normalization
- rate limiting
- audit headers and tracing

This becomes the seam between old Laravel modules and new services.

## Phase 4: Service Extraction by Domain

Duration: incremental over 3 to 9 months

Do not extract everything at once. Extract in this order:

### 1. Communications Service

Why first:

- high isolation potential
- clear async behavior
- templates, broadcast, inbox-like logic can be decoupled

Service responsibilities:

- messages
- recipients
- templates
- read state
- notifications fan-out

### 2. STR Service

Why second:

- already behaves like a separate product
- has distinct models and routes
- public and host flows are clearly separable

Service responsibilities:

- listings
- availability
- booking lifecycle
- payouts
- reviews
- incidents
- iCal sync

### 3. HOA Service

Why third:

- strong domain cohesion
- separate UI and workflows

Service responsibilities:

- communities
- members
- dues
- violations
- announcements
- meetings
- budgets

### 4. Business Marketplace Service

Why fourth:

- distinct workflow and user roles
- already modeled as a separate marketplace

Service responsibilities:

- projects
- proposals
- milestones
- contracts
- invoices
- reviews

### 5. Document Service

Why fifth:

- central cross-cutting concern
- useful after access controls are clarified

Service responsibilities:

- upload/download
- metadata
- previews
- access audits
- retention rules
- protection/suspension

### Keep These in Core Longer

- auth/account identity
- payments ledger/billing
- leases and legal contracts
- property master data

These areas are too central and cross-cutting to extract early.

## Proposed End-State Architecture

```text
platform/
  apps/
    web/                    # Next.js frontend
    admin/                  # optional separate Next.js admin app
    gateway/                # BFF/API gateway
  services/
    auth-service/
    property-service/
    leasing-service/
    payment-service/
    communications-service/
    str-service/
    hoa-service/
    business-service/
    document-service/
    copilot-service/
  packages/
    ui/
    config/
    shared-types/
    shared-events/
    shared-auth/
    observability/
  infra/
    docker/
    k8s/                    # only if needed later
    terraform/
    scripts/
  docs/
    architecture/
    runbooks/
    api/
```

## Service Internal Structure

Recommended for each TypeScript service:

```text
services/str-service/
  src/
    modules/
      listings/
        controllers/
        services/
        dto/
        entities/
        repositories/
        policies/
      bookings/
      payouts/
      reviews/
      incidents/
    common/
      config/
      db/
      events/
      middleware/
      utils/
    app.ts
    server.ts
  prisma/
  test/
  package.json
```

## Database Migration Plan

## Phase A: Keep Current SQL Schema

Initially:

- keep MySQL
- do not replatform database during frontend migration
- use schema replication only when needed for extracted services

## Phase B: Split by Bounded Context

Use one of these approaches:

### Approach 1: Shared database, separate schemas

Best for early extraction.

- lower risk
- faster rollout
- fewer data sync issues

### Approach 2: Separate databases per service

Use later for STR, HOA, business, communications.

- stronger isolation
- more operational complexity
- requires event-driven sync or well-defined ownership

Recommended order of DB ownership split:

1. communications
2. STR
3. HOA
4. business marketplace
5. documents

## Auth and Identity Plan

Recommended:

- central auth service or keep Laravel auth temporarily
- migrate to JWT + refresh tokens or session-backed gateway auth
- support role switching explicitly:
  - renter
  - manager
  - owner
  - admin
  - contractor/business
  - HOA roles

Identity concerns to centralize:

- login
- social login
- 2FA
- account verification
- role/account picker
- session management
- permission groups

## Files and Media Plan

Move file handling away from local/public behavior.

Target:

- object storage for originals and derivatives
- signed URLs for protected access
- async media processing
- centralized metadata and audit storage

Folders:

```text
services/document-service/
  src/
    modules/
      uploads/
      access-audit/
      previews/
      retention/
```

## Queue and Async Plan

Move these workloads to queues first:

- emails
- reminder sending
- exports
- iCal sync
- webhook retries
- report generation
- image/document conversion
- payment-plan reminders
- notification fan-out
- background checks

Suggested infra:

- Redis
- BullMQ or equivalent
- dead-letter handling
- retry policies

## Observability and Platform Standards

Before service extraction, standardize:

- structured logs
- distributed tracing
- correlation IDs
- centralized error tracking
- API versioning
- request/response contracts
- idempotency on webhook/payment endpoints

## Suggested Repo Restructuring

## Transitional Structure

Use this before full extraction:

```text
myrentalspot/
  backend/
    laravel/
      app/
      routes/
      config/
      database/
  frontend/
    apps/
      web/
  docs/
    architecture/
    migration/
  scripts/
  infra/
```

## Monorepo End-State

```text
myrentalspot/
  apps/
    web/
    gateway/
  services/
    auth-service/
    property-service/
    leasing-service/
    payment-service/
    communications-service/
    str-service/
    hoa-service/
    business-service/
    document-service/
    copilot-service/
  packages/
    ui/
    config/
    shared-types/
    shared-events/
    eslint-config/
    tsconfig/
  docs/
  infra/
  scripts/
  package.json
  pnpm-workspace.yaml
```

## Domain-to-Service Mapping

| Current domain | Transitional owner | End-state owner |
|---|---|---|
| Auth | Laravel | Auth service |
| Public CMS pages | Next.js + Laravel CMS API | Next.js + CMS/content service |
| Properties | Laravel modular domain | Property service |
| Leasing | Laravel modular domain | Leasing service |
| Payments/Billing | Laravel modular domain | Payment service |
| Work orders | Laravel modular domain | Property or work-order service |
| Communications | modular domain | Communications service |
| STR | modular domain | STR service |
| HOA | modular domain | HOA service |
| Business marketplace | modular domain | Business service |
| Documents | Laravel admin domain | Document service |
| Copilot | Laravel admin domain | Copilot service |

## Team Plan

Minimum streams:

1. Stabilization + Laravel modularization
2. Next.js frontend platform
3. API/gateway and auth strategy
4. First extracted domain service

If team size is limited, do them sequentially:

1. stabilize
2. modularize
3. move public + auth UI to Next.js
4. move account/admin UI to Next.js
5. extract communications
6. extract STR

## Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Big-bang rewrite fails feature parity | very high | migrate by domain and route slice |
| Hidden cross-domain coupling | high | modularize first inside Laravel |
| Payment/legal regressions | very high | keep in core longer and add tests first |
| Auth/session fragmentation | high | centralize auth early |
| Data consistency across services | high | delay DB split until ownership is clear |
| Admin feature backlog slows migration | medium | build admin shell and migrate feature-by-feature |

## Recommended 12-Month Roadmap

### Quarter 1

- stabilize current app
- fix security/ops exposure
- modularize route/controller structure
- add regression tests

### Quarter 2

- launch Next.js public site
- migrate search/listing/STR public flows
- introduce gateway/BFF

### Quarter 3

- migrate authenticated portal and admin shell
- keep Laravel as core API
- extract communications service

### Quarter 4

- extract STR service
- begin HOA or business service extraction
- centralize file/document service patterns

## Final Recommendation

Use `Next.js + Laravel transition + selective TypeScript service extraction`.

Do not choose pure MERN as the primary target.

Reasons:

- the domain is relational
- payment/legal/admin workflows favor SQL and strong server-side control
- gradual replacement is much safer than a full rewrite

If you want the shortest path with the best risk profile:

1. keep Laravel as backend temporarily
2. build Next.js frontend now
3. modularize backend before extraction
4. extract communications, STR, HOA, and business as separate services over time
