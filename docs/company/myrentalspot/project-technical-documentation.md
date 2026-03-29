# Project Overview

## Summary

This repository is a large Laravel 10 monolith for a rental/property platform that now contains multiple products in one codebase:

- Long-term rental marketplace and resident lifecycle
- Manager/landlord operations
- Owner portal and financial reporting
- Short-term rental (STR) listing, booking, payout, review, and incident flows
- HOA/community management
- Business marketplace for PM-to-contractor projects
- Admin back office, CMS, security/audit tooling, and Copilot knowledge/chat features

The codebase uses a classic Laravel MVC structure, but several domains have grown into very large controllers and broad route files. Recent migrations show aggressive feature expansion in March 2026 across STR, HOA, business marketplace, security/audit, community, collections, and Copilot.

## Inventory Snapshot

| Area | Count |
|---|---:|
| Route definitions in `routes/` | 1,684 |
| Controllers in `app/Http/Controllers` | 190 |
| Models in `app/Models` | 227 |
| Blade views in `resources/views` | 824 |
| Test files in `tests/` | 5 |

# Folder Structure

## High-Level Tree

```text
myrentalspotold/
+- app/
|  +- Http/Controllers/
|  |  +- Account/        # renter, landlord, owner-adjacent account portal
|  |  +- Admin/          # admin panel, security, reports, CMS integrations
|  |  +- API/            # mobile/API endpoints for landlord/renter/work orders
|  |  +- Auth/           # login, register, verification, 2FA
|  |  +- Business/       # PM/contractor marketplace
|  |  +- Hoa/            # HOA setup, portal, dues, meetings, violations
|  |  +- Owner/          # owner dashboard, payments, reports
|  |  \- Public/         # public personal/company pages
|  +- Models/            # 227 Eloquent models
|  +- Services/          # payment plans, RTO, reporting, Mercury, scoring
|  +- Traits/
|  +- Providers/
|  +- Policies/
|  +- Observers/
|  \- Helpers/
+- bootstrap/
+- config/
+- database/
|  +- migrations/        # strong concentration of Mar 2026 feature migrations
|  +- seeders/
|  +- factories/
|  \- sql/
+- public/               # compiled assets, examples, debug-like files
+- resources/
|  \- views/
|     +- account/        # 435 blades
|     +- admin/          # 156 blades
|     +- business/
|     +- cms/
|     +- hoa/
|     +- features/
|     +- public/
|     \- emails/
+- routes/
|  +- web.php
|  +- api.php
|  +- admin.php
|  \- cms.php
+- storage/
+- tests/
\- vendor/
```

## View Distribution

| View area | Blade files |
|---|---:|
| `account/` | 435 |
| `admin/` | 156 |
| `business/` | 41 |
| `features/` | 34 |
| `emails/` | 19 |
| `components/` | 18 |
| `auth/` | 16 |
| `cms/` | 16 |
| `public/` | 15 |
| `properties/` | 15 |
| `hoa/` | 13 |

# Features List

## Core Product Domains

| Domain | What it does | Main implementation areas |
|---|---|---|
| Public marketplace | Search listings, property details, tours, favorites, CMS pages | `SearchController`, `PropertyController`, `CmsPublicController`, `resources/views/public`, `resources/views/properties` |
| Authentication & verification | Login, social login, OTP, account picker, identity verification, 2FA | `Auth\*`, `Account\UserController`, `Account\StripeIdentityController` |
| Renter account | Dashboard, payments, applications, maintenance, inbox, profile | `Account\DashboardController`, `Account\Renter*`, `API\Renter\*` |
| Landlord/manager account | Dashboard, properties, leases, work orders, finances, vendors, reports | `Account\DashboardController`, `Account\PropertyController`, `Account\LeaseController`, `Account\WorkOrderController`, `Account\FinanceController` |
| Owner portal | Dashboard, residences, financials, reports, bills | `Owner\*`, owner views |
| STR | Public browse/listing/checkout plus host-side listing, booking, payout, reviews, incidents, iCal | `StrPublicController`, `StrCheckoutController`, `Account\StrController`, STR models/migrations |
| HOA | Community setup, dues, meetings, announcements, docs, violations, homeowner portal | `Hoa\*`, HOA models/migrations/views |
| Business marketplace | PM project creation, contractor proposals, milestones, contracts, invoices, reviews, messaging | `Business\*`, business models/migrations/views |
| Communications | Inbox, message center, template management, broadcast messaging, live chat | `Account\CommunicationsHubController`, admin inbox, chat support, message models |
| Admin & CMS | Platform admin, search, audit/security, announcements, templates, reports, CMS pages/categories/media/layouts | `Admin\*`, `Admin\CMS\*`, `routes/admin.php`, `routes/cms.php` |
| Security & compliance | blocked IPs, login history, permission changes, document access audit, session management | `Admin\SecurityController`, related models/migrations |
| AI / Copilot | user chat entrypoint, admin usage/knowledge base | `CopilotController`, `Admin\CopilotAdminController`, Copilot models/migrations |

# Routes Table

## Route File Purpose

| File | Purpose |
|---|---|
| `routes/web.php` | Main web app, account portal, public routes, STR, HOA, business, webhooks, cron-like routes |
| `routes/api.php` | API auth, renter/landlord mobile/API flows, timeline, communications, work-order API |
| `routes/admin.php` | Admin back office, reports, templates, documents, security, STR admin, file storage |
| `routes/cms.php` | Admin CMS for pages, categories, media, layouts |

## Major Web Route Groups

| Prefix / area | Methods | Controller(s) | Purpose |
|---|---|---|---|
| `/verification/*` | `GET`, `POST` | `Auth\AccountVerificationController` | email/phone/identity verification |
| `/register/*` | `GET`, `POST`, `ANY` | `Auth\RegisterController` | registration type, code flow, password setup |
| `/account/dashboard/*` | `GET` | `Account\DashboardController`, `Account\RentProtectionController` | renter/landlord dashboards and rent protection |
| `/account/...` | mixed | many `Account\*` controllers | main authenticated app for profile, inbox, bank, properties, leases, payments, reports |
| `/owner/*` | mixed | `Owner\*` and redirects in `web.php` | owner dashboard and owner-specific screens |
| `/search` | `GET`, `POST` | `SearchController` | listing search and favorite toggle |
| `/property/*` | `GET`, `POST` | `PropertyController`, `ReportListingController` | public property details, gallery, map, tours, applications |
| `/stays/*` | `GET`, `POST` | `StrPublicController`, `StrCheckoutController` | STR browse, listing detail, checkout, confirmation, iCal |
| `/hoa/{hoaId}/*` | mixed | `Hoa\*` controllers | HOA dashboard, members, dues, violations, docs, meetings, budget, portal |
| `/business/*` | mixed | `Business\*` controllers | PM/contractor marketplace flows |
| `/chat-support/*` | `GET`, `POST` | `ChatSupportController` | end-user live chat support |
| `/copilot/chat` | `POST` | `CopilotController` | authenticated Copilot chat |
| webhook endpoints | `POST`, `ANY` | `StripeWebhookController`, `WebhookController`, `StripeConnectWebhook` | Stripe, Mercury, report/manual auth integrations |
| `/cron/*` | `GET` | `CronJobController` | scheduled job entrypoints exposed as HTTP routes |

## Major API Route Groups

| Prefix | Methods | Controller(s) | Purpose |
|---|---|---|---|
| `/api/login`, `/api/register`, `/api/forgot-password` | `POST` | `API\AuthController` | auth for API clients |
| `/api/landlord/*` | `GET`, `POST`, `PATCH` | `API\Landlord\*` | landlord applications, payments, dashboard, RTO |
| `/api/renter/*` | `GET`, `POST` | `API\Renter\*` | renter applications, bank, payments, dashboard |
| `/api/bookings/{id}/timeline*` | `GET`, `POST` | `API\BookingTimelineController` | booking timeline feed and updates |
| `/api/communications/*` | `GET`, `POST`, `PUT`, `DELETE` | intended `Account\CommunicationsHubController` | messages and templates API |
| `/api/v1/work-orders/*` | `GET`, `POST`, `PUT` | `API\WorkOrderApiController` | work order details, notes, attachments, bids |

## Major Admin Route Groups

| Prefix | Methods | Controller(s) | Purpose |
|---|---|---|---|
| `/admin/dashboard` | `GET`, `POST` | `Admin\DashboardController` | admin home and preferences |
| `/admin/security/*` | `GET`, `POST` | `Admin\SecurityController` | login history, permission changes, data access log, session termination |
| `/admin/inbox/*` | `GET`, `POST` | `Admin\InboxController` | ticketing, chat support, contact submission handling |
| `/admin/property/*` | mixed | `Admin\PropertyController` and lookup controllers | property administration and verification |
| `/admin/user/*` | mixed | `Admin\UserController`, `Admin\AdminUserController` | user management, impersonation, suspension, payout verification |
| `/admin/template/*` | `GET`, `POST` | `Admin\TemplateController` | email, lease, and message templates |
| `/admin/reports/*` | `GET` | `Admin\ReportsController` | platform analytics exports |
| `/admin/str/*` | `GET`, `POST` | `Admin\StrAdminController` | STR admin settings, listings, bookings, disputes, guests, reviews |
| `/admin/documents/*` | mixed | `Admin\DocumentLibraryController` | document library, preview, protection, audits |
| `/admin/file-storage/*` | `GET`, `POST` | `Admin\FileStorageController` | storage stats, cleanup, image optimization |
| `/admin/cms/*` | mixed | `Admin\CMS\*` | page/category/media/layout management |

# Controllers Breakdown

## Key Controllers and Responsibilities

| Controller | Key functions | Responsibility |
|---|---|---|
| `Account\DashboardController` | `renter`, `landlord`, `expenseIncomeChart`, `exportDashboardExcel`, `sendReminders` | main renter/landlord dashboard orchestration and exports |
| `Account\PropertyController` | `index`, `stepOne`-`stepSix`, `map`, `getMapData`, `uploadVerificationDocument`, `showFiles` | property CRUD, listing setup wizard, map search, documents, verification |
| `Account\LeaseController` | `step1`-`step5`, `exportPdf`, `uploadSignedLease`, `viewAuditTrail` | lease generation/signature workflow |
| `Account\WorkOrderController` | `index`, `store`, `view`, `notesIndex`, `attachmentsIndex`, `bidsIndex`, `export` | work order lifecycle and related assets |
| `Account\CommunicationsHubController` | `index`, `getMessages`, `sendMessage`, `sendBroadcast`, `getTemplates` | messaging center and template CRUD |
| `Account\StrController` | `listings`, `bookings`, `updateListing`, `blockDates`, `setCustomPricing`, `payouts`, `reviews`, `incidents`, `syncIcal` | STR host operations |
| `SearchController` | `index`, `toggleFavorite` | unified long-term/STR public search |
| `StrPublicController` | `browse`, `show`, `icalFeed`, `hostProfile` | public STR storefront |
| `StrCheckoutController` | `step1`, `step2`, `step3`, `complete`, `confirmation` | STR booking/checkout pipeline |
| `Hoa\HoaDashboardController` | `index` | HOA management overview |
| `Hoa\HoaPortalController` | `index`, `payDue` | homeowner-facing HOA portal |
| `Business\BusinessProjectController` | `create`, `store`, `index`, `show`, `acceptProposal`, `history` | PM-side business project flow |
| `Business\BusinessActiveProjectController` | `index`, `show`, `startMilestone`, `uploadProof`, `completeProject` | active project milestone execution |
| `Admin\SecurityController` | `loginHistory`, `permissionChanges`, `dataAccessLog`, `blockIp`, `terminateSessions`, export methods | audit and security administration |
| `Admin\DocumentLibraryController` | `index`, `stats`, `search`, `preview`, `download`, `updateProtection`, `activity` | centralized document library |
| `Admin\StrAdminController` | `settings`, `listings`, `bookings`, `analytics`, `payouts`, `disputes`, `reviews`, `incidents` | STR back-office oversight |

## Controller Design Observation

Several controllers are acting as application-service layers plus HTTP controllers:

- `Account\PropertyController` is extremely large and spans property CRUD, listing media, map search, notes, finances, verification, file handling, Zillow sync, and promotions.
- `Account\StrController` mixes listing setup, booking operations, payouts, reviews, incidents, messaging templates, and background checks.
- `Account\DashboardController` combines renter and landlord dashboard composition, exports, notifications, reminder sending, and Stripe balance.
- `Account\LeaseController` contains a multi-step lease builder plus PDF/signature/audit behaviors.

This increases coupling and makes testing difficult.

# Models Description

## Core Domain Entities

| Model | Purpose | Key relationships observed |
|---|---|---|
| `User` | central actor for renters, landlords, owners, contacts, PM/business users | has many `Property`, `Inbox`, `Notification`, `UserSession`, `StrWishlist`; belongs to `PermissionGroup`; many-to-many manager/owner links |
| `Property` | parent rental property | belongs to `User`; has many `PropertyUnit`, `Booking`, `Document`, `RecurringExpense`, `PropertyVerification` |
| `PropertyUnit` | leaseable or bookable unit | belongs to `Property`; has many `Booking`, `Bill`, `Document`, `StrBooking`, `StrBlockedDate`, `StrCustomPricing`, `StrReview`, `StrIncident` |
| `Booking` | long-term booking/application/residency record | belongs to `Property`, `PropertyUnit`, `User`(landlord); has many `Bill`, `BookingPaymentPeriod`, `Document`, `BookingTimeline`, `Showing`; has one latest `Lease` |
| `Lease` | lease document/contract | belongs to `Booking`; has many `LeaseAuditTrail`; has one active `RtoAgreement` |
| `WorkOrder` | maintenance/project work order | belongs to `Task`, `MaintenanceRequest`, `User`; has many `WorkOrderNote`, `WorkOrderAttachment`, `OwnerBill` |
| `Document` | uploaded files and document library records | belongs to `DocumentCategory`, `User`, `Property`, `PropertyUnit`, `Booking`; has many `DocumentAccessAudit` |
| `StrBooking` | short-term booking | belongs to `PropertyUnit`, `Property`, guest `User`, host `User`; has one `StrPayout`, one `StrReview`, many `StrMessage` |
| `HoaCommunity` | HOA container entity | belongs to `Property`; has many members, dues, violations, announcements, documents, meetings, expenses |
| `BusinessProject` | PM marketplace project | belongs to PM `User`, awarded `BusinessProfile`, `Property`; has many proposals, milestones, invoices, messages |

## Supporting Model Families

| Family | Example models |
|---|---|
| Messaging | `Inbox`, `CommunicationsMessage`, `CommunicationsMessageRecipient`, `StrMessage`, `BusinessMessage`, `ChatMessage` |
| Security/Audit | `BlockedIP`, `LoginAttempt`, `KnownDevice`, `ActiveSession`, `PermissionChange`, `DataAccessLog`, `DocumentAccessAudit`, `AdminAuditLog` |
| HOA | `HoaMember`, `HoaDue`, `HoaViolation`, `HoaAnnouncement`, `HoaDocument`, `HoaMeeting`, `HoaBudget`, `HoaReserveFund` |
| Business | `BusinessProfile`, `BusinessProposal`, `BusinessMilestone`, `BusinessContract`, `BusinessInvoice`, `BusinessReview` |
| STR | `StrBlockedDate`, `StrCustomPricing`, `StrPayout`, `StrReview`, `StrIncident`, `StrGuestProfile`, `StrWishlist`, `StrMessageTemplate` |
| Owner finance | `OwnerBill`, `OwnerBillAttachment`, `OwnerBillHistory`, `Distribution`, `Contribution`, `OwnerCredit` |

## Migration Trend

| Migration theme | Count |
|---|---:|
| Core/general | 31 |
| STR | 17 |
| HOA | 14 |
| Community | 14 |
| Ops/finance/RTO/work orders | 15 |
| Admin/security | 16 |
| Business marketplace | 9 |
| Copilot | 2 |

# Pages / UI Flow

## Primary User Flows

| Flow | Likely screens/views |
|---|---|
| Public visitor | home/CMS page -> search -> property detail or STR listing -> register/login -> apply/checkout |
| Renter | login -> account picker/dashboard -> applications/residence/payments/maintenance/messages/profile |
| Landlord/manager | login -> landlord dashboard -> properties -> units -> lease/work order/finance/report subflows |
| Owner | login -> owner dashboard -> properties/residences/financials/reports/payments |
| STR host | login -> STR dashboard -> listings/calendar/bookings/payouts/reviews/incidents |
| HOA manager/member | HOA dashboard/portal -> members/dues/violations/documents/meetings/financials |
| Business PM | marketplace dashboard -> create project -> review proposals -> award -> manage milestones/invoices |
| Contractor/business user | browse projects -> submit proposal -> active project -> upload proof -> receive review/payment |
| Admin | admin login -> dashboard -> inbox/security/users/properties/templates/reports/documents/CMS |

## View Layer Notes

- `resources/views/account/` is the dominant UI area and contains landlord, renter, owner-adjacent, maintenance, communication, STR, bank, and profile screens.
- `resources/views/admin/` provides a full back-office UI including STR, documents, security, reports, and templates.
- `resources/views/business/` and `resources/views/hoa/` indicate each newer module has its own front-end area rather than being isolated into separate apps.
- `resources/views/public/str/` supports public STR browse, listing, host profile, and checkout pages.

# Tech Stack

## Backend

| Item | Details |
|---|---|
| Framework | Laravel `^10.10` |
| PHP | `^8.1` |
| Auth/API | Laravel Sanctum |
| PDF/Docs | `barryvdh/laravel-dompdf`, `spatie/laravel-pdf` |
| Payments | `stripe/stripe-php` |
| Cloud/storage | AWS SDK + Flysystem S3 |
| Messaging/realtime | Pusher |
| Social/Auth | Socialite, Google API, Firebase JWT, Google2FA |
| Media/files | Intervention Image, HEIC-to-JPG |
| Spreadsheets | PhpSpreadsheet |

## Frontend / Build

| Item | Details |
|---|---|
| Bundler | Vite |
| JS deps | Axios, Bootstrap, Popper, Sass |
| Other dependency | Puppeteer |
| Frontend style | Blade-driven server-rendered app with large legacy public asset folder |

## Internal Services

- `PaymentPlanService`
- `PaymentPlanAgreementService`
- `PaymentActionService`
- `BookingTimelineService`
- `RenterScoreService`
- `RtoPaymentService`
- `RtoEmailService`
- `RtoPdfService`
- `StripeReportingService`
- `MercuryService`
- `SteadilyService`
- `CopilotService`

# Data Flow

## Standard Flow

```text
Request
  -> Route group (`web.php`, `api.php`, `admin.php`, `cms.php`)
  -> Middleware (`auth`, `auth:admin`, verification, IP blocking, suspension checks)
  -> Controller action
  -> Eloquent model queries / service calls
  -> Blade view or JSON response
```

## Examples

| Flow | Path |
|---|---|
| Long-term property search | request -> `SearchController@index` -> `Property/PropertyUnit/FavoriteProperty` queries -> search blade |
| Property management | request -> `Account\PropertyController` -> `Property`, `PropertyUnit`, `Photo`, `Document`, verification models -> account property views/JSON |
| Lease generation | request -> `Account\LeaseController` -> `Booking`, `Lease`, templates, PDF/signature logic -> lease builder or PDF |
| Work order API | request -> `/api/v1/work-orders/*` -> `API\WorkOrderApiController` -> `WorkOrder`, `WorkOrderNote`, `WorkOrderAttachment` -> JSON |
| STR checkout | request -> `StrCheckoutController` -> `PropertyUnit`, `StrBooking`, payout/payment logic -> confirmation page |
| HOA dues | request -> `Hoa\HoaDuesController` or `Hoa\HoaPortalController` -> `HoaDue`, `HoaMember`, payment handling -> HOA views/updates |
| Business marketplace | request -> `Business\BusinessProjectController` / `Business\BusinessActiveProjectController` -> project/proposal/milestone/invoice models -> business views |

# Issues

## High-Risk Findings

| Severity | Finding | Evidence |
|---|---|---|
| Critical | Public maintenance endpoints allow cache clear and DB migration over GET | `routes/web.php` exposes `clear_cache` and `migrate` closures |
| Critical | HTTP cron endpoints are public and trigger operational jobs | `routes/web.php` `/cron/*` routes call payout, backup, deletion, fee, reset, and sync actions |
| Critical | Sensitive-looking files exist in web-visible or repo-visible locations | `.env`, `storage/keys/AuthKey_ZULA32264U.p8`, `public/key.txt`, `public/info.php` are present in the project tree |
| High | Route configuration inconsistency breaks route introspection and likely some API resolution | `php artisan route:list --json` fails with missing class `WebTechno\Http\Controllers\API\Account\CommunicationsHubController` |
| High | Very large controllers indicate poor separation of concerns and high regression risk | `Account\PropertyController`, `Account\StrController`, `Account\DashboardController`, `Account\LeaseController` |
| High | Test coverage is extremely limited for the size of the platform | only 5 test files, one real API test found |
| Medium | Monolith mixes many product lines in one deployment unit | long-term rentals, STR, HOA, business marketplace, CMS, Copilot, security tools all share one app |
| Medium | Operational TODOs remain in core service logic | `PaymentPlanService` still has TODO comments for resuming late fees |
| Medium | Route files are very large and difficult to reason about | `routes/web.php` contains major platform routing for multiple domains |

## Code Quality Observations

- Domain logic is often embedded directly inside controllers instead of thin controllers + service/action classes.
- The same app serves web pages, admin, API, operational hooks, and background-like job endpoints.
- Large use of string-based controller references suggests older Laravel patterns mixed with newer class-based usage.
- The public asset area contains legacy JS/CSS bundles and utility files, indicating partial modernization rather than a coherent asset strategy.

# Upgrade Plan

## Recommended Architecture Direction

1. Split route definitions by bounded context.
   Move STR, HOA, business, owner, renter/landlord portal, webhooks, and operational routes into separate route files/providers.

2. Break up large controllers into actions/services.
   Start with `Account\PropertyController`, `Account\StrController`, and `Account\LeaseController`.

3. Remove dangerous HTTP maintenance entrypoints.
   Replace public `clear_cache`, `migrate`, and `/cron/*` routes with Laravel scheduler/queue/CLI-only jobs behind server-side auth.

4. Fix namespace/route-resolution inconsistencies.
   Resolve the communications API controller mismatch so `artisan route:list` works cleanly and route registration is reliable.

5. Harden secrets and debug exposure.
   Remove sensitive files from web roots and source control, rotate any exposed keys, disable debug/info endpoints in all non-local environments.

6. Increase automated test coverage by domain.
   Prioritize auth/verification, payments, leases, STR booking, HOA dues, and business project lifecycle.

7. Introduce module-level boundaries.
   Keep one repo if needed, but isolate domains into clearer modules with dedicated services, requests, policies, and tests.

8. Move long-running or side-effect-heavy workflows to queues.
   Email, reminders, exports, webhooks, media processing, syncs, and background checks should be queued consistently.

## Suggested Near-Term Refactor Order

| Phase | Focus |
|---|---|
| Phase 1 | security cleanup: remove public ops routes, rotate secrets, fix route namespace errors |
| Phase 2 | controller decomposition: property, STR, lease, dashboard |
| Phase 3 | test foundation: feature tests around payments, leases, work orders, STR, HOA |
| Phase 4 | route/module separation and service extraction |
| Phase 5 | performance/scalability improvements: queues, caching, search indexing, asset cleanup |

# Final Assessment

This project is feature-rich and commercially ambitious, but it has grown as a single Laravel monolith with substantial domain sprawl. The strongest assets are its broad functional coverage and recent investment in STR, HOA, security, business marketplace, and AI features. The main risks are operational exposure, oversized controllers, route complexity, and low test coverage relative to platform scope.
