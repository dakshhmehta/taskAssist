# Project Summary

This document is a working summary of the Laravel application based on a scan of:

- `database/migrations`
- `app/Models`
- `app/Filament`
- `app/Mcp`

It is meant to be a reusable starting point for future work in this repository.

## High-Level Overview

This application appears to be an internal operations system for a web/hosting business. It combines:

- Client and billing management
- Domain, hosting, and email renewal tracking
- Internal task and timesheet tracking
- Leave and attendance management
- Admin dashboards in Filament
- A small MCP layer for AI/tool access to reseller and renewal data

The app is best understood as two large business areas connected together:

1. External/client operations
   - Clients
   - Domains
   - Hostings
   - Emails
   - Invoices
   - Renewal and receivable tracking

2. Internal/team operations
   - Users
   - Tasks
   - Timesheets
   - Leaves
   - Attendance/check-ins
   - Performance and star tracking

## Core Business Modules

### 1. Client, Billing, and Renewals

The billing side is centered around `Client`, `Invoice`, and billable assets:

- `Client` owns:
  - many `Invoice`
  - many `Domain`
  - many `Hosting`
  - many `Email`

- `Invoice` contains:
  - many `InvoiceItem`
  - many `InvoiceExtra`

- `InvoiceItem` is polymorphic and can point to:
  - `Domain`
  - `Hosting`
  - `Email`

This means domains, hostings, and emails are treated as billable/renewable assets that can be added into invoices through a shared line-item mechanism.

### 2. Work Management

The internal execution side is centered around `Task`:

- `Task` belongs to an assignee `User`
- `Task` has many `Timesheet`
- `Task` has tags
- `Task` supports comments
- `Task` supports start/stop timing
- `Task` calculates minutes taken, performance, and cost

This is a hands-on operational task board, not just a backlog table.

### 3. HR / Attendance

This side centers around `User`, `UserLeave`, `UserCheckIn`, and `Holiday`:

- Leave requests can be approved or rejected
- Approved CL leave creates transaction/ledger entries
- Attendance is tracked through user check-ins
- Holidays are used as calendar-level business constraints

### 4. Site Monitoring / Metadata

There is a smaller subsystem around:

- `Site`
- `Meta`

This appears to support site-health style monitoring with generic key/value metadata such as WordPress version, last backup timestamp, and similar signals.

### 5. MCP / Tooling

The MCP layer currently exposes a small reseller-oriented server with tools for:

- Upcoming renewals
- Reseller balance

This looks like an early AI/tool access layer over app data rather than a full platform-wide MCP surface.

## Important Models

### `app/Models/Client.php`

Key role:

- Master customer record for billing and asset ownership

Notable behavior:

- Uses ledger-related traits
- Uses Filament comments
- Recomputes `receivable_amount` on save
- Has a `display_name`
- Receivable is derived from unpaid invoices

Relationships:

- `invoices()`
- `domains()`
- `hostings()`
- `emails()`

### `app/Models/Invoice.php`

Key role:

- Invoice header and derived billing calculations

Notable behavior:

- Total is computed from invoice items plus extras
- Supports unpaid scope
- Supports mark-as-paid flow
- Has GST-related helper accessors
- Detects invoice type from invoice number
- Appears to support proforma to tax invoice conversion through traits/helpers
- Touches client on update

Relationships:

- `items()`
- `extras()`
- `client()`

### `app/Models/InvoiceItem.php`

Key role:

- Polymorphic line item joining invoice headers to billable models

Notable behavior:

- `itemable()` morph relation includes ignored models
- Supports `discount_value`, `proforma_invoice_id`, `line_description`, and `expiry_date`

Relationships:

- `itemable()`
- `invoice()`

### `app/Models/Domain.php`

Key role:

- Renewable domain asset

Notable behavior:

- Syncs expiry data from ResellerClub
- Derives client from latest invoice in some flows
- Calculates whether it has already been invoiced
- Supports ignore/unignore behavior
- Supports comments and activity logging

Relationships:

- `hosting()`
- `invoiceItems()`
- `invoices()`
- `client()`

### `app/Models/Hosting.php`

Key role:

- Renewable hosting asset

Notable behavior:

- Can renew itself by extending expiry date
- Tracks suspension
- Calculates invoice state
- Supports ignore behavior, comments, and activity logs
- Links to hosting package

Relationships:

- `domainLink()`
- `invoiceItems()`
- `package()`
- `invoices()`
- `client()`

### `app/Models/Email.php`

Key role:

- Renewable email/GSuite-like asset

Notable behavior:

- Tracks provider, accounts count, expiry
- Calculates invoice state
- Supports ignore behavior
- `client()` may fill and save `client_id` lazily from latest invoice if missing

Relationships:

- `invoiceItems()`
- `invoices()`
- `client()`

### `app/Models/Task.php`

Key role:

- Main work execution model

Notable behavior:

- Assigned to a user
- Can be completed
- Can start and stop timers
- Determines whether work can begin
- Computes:
  - `is_completed`
  - `in_progress`
  - `minutes_taken`
  - `hms`
  - `performance`
  - `cost`
- Uses:
  - tags
  - activity logging
  - comments
  - media/media folders

Relationships:

- `assignee()`
- `timesheet()`

### `app/Models/Timesheet.php`

Key role:

- Time logs for tasks

Notable behavior:

- `working()` scope for active timers
- `toHMS()` helper for display formatting

Relationships:

- `user()`
- `task()`

### `app/Models/User.php`

Key role:

- Team member / authenticated user

Notable behavior:

- Tracks salary, salary type, work hours, biometric ID
- Computes performance from task history and time worked
- Has ledger-like star balance support
- Tracks check-ins and leave history
- Admin logic currently depends on `id == 1`

Relationships:

- `leaves()`
- `tasks()`
- `timesheet()`
- `checkIns()`

### `app/Models/UserLeave.php`

Key role:

- Leave request workflow

Notable behavior:

- Defaults `user_id` during save
- Recreates leave-related transactions after save
- Approval:
  - marks approval metadata
  - clears admin remarks
  - dispatches task rescheduling
- Rejection:
  - stores admin remarks
- Supports half-day logic

Relationships:

- `user()`
- `approvedByUser()`

### `app/Models/UserCheckIn.php`

Key role:

- Attendance punch record

Notable behavior:

- Stores `punch_at`
- Later schema adds latitude and longitude

Relationship:

- `user()`

### `app/Models/Tag.php`

Key role:

- Task grouping, planning, and costing dimension

Notable behavior:

- Tracks incomplete task count
- Derives due date from incomplete tasks
- Computes time taken and performance across tasks
- Hourly cost falls back to company rate if tag-specific cost is missing

Relationship:

- `tasks()`

### `app/Models/Site.php`

Key role:

- Site-health oriented model

Notable behavior:

- Uses metadata trait
- Uses ignore behavior
- Can route notifications to Telegram
- Has query helper for missing/stale backups

### `app/Models/Meta.php`

Key role:

- Generic polymorphic key/value metadata store

Relationship:

- `model()`

### Supporting Models

Smaller but important support models:

- `Holiday`
- `HostingPackage`
- `InvoiceExtra`
- `Comment`

## Filament Admin Surface

Filament is the main operational UI for the app.

### Key Resources

#### `ClientResource`

Purpose:

- Manage clients and navigate to related domains, hostings, emails, and invoices

Notable actions:

- Edit client
- Sync ledger

Relations:

- Domains
- Hostings
- Emails
- Invoices

#### `TaskResource`

Purpose:

- Main work queue and execution screen

Notable capabilities:

- Create and edit tasks
- Assign task owner
- Set estimate
- Toggle urgency/importance
- Use auto-schedule or manual due date
- Attach tags
- Start/stop timer
- Complete task
- Comment on task

Notable filters:

- Assignee
- Completed/incomplete
- Planned/unplanned
- Tags

#### `UserResource`

Purpose:

- Manage users and team-level reports

Notable capabilities:

- Edit users
- Adjust star balance
- Open leave ledger
- Open attendance report
- View user detail widgets/reports

#### `UserLeaveResource`

Purpose:

- Leave request and approval workflow

Notable capabilities:

- Submit leave
- Admin approve/reject
- Filter by user, code, and status
- Show badge count of new leave requests

#### `InvoiceResource`

Purpose:

- Build and manage invoices

Notable capabilities:

- Select client
- Set date and invoice number
- Add polymorphic invoice items
- Add extra manual lines
- Add invoice footnote
- Mark invoice as paid
- Convert proforma invoice to tax invoice
- Print invoice
- Queue invoice email
- Comment on invoice

Important behavior:

- Invoice items auto-fill expiry date from selected domain/hosting/email where available

#### `DomainResource`

Purpose:

- Renewal and invoicing operations for domains

Notable capabilities:

- Refresh/sync from reseller
- Ignore/unignore
- Comment
- Generate invoice
- Bulk generate invoices grouped by client

#### `HostingResource`

Purpose:

- Renewal and package management for hostings

Notable capabilities:

- Set package
- Renew hosting
- Open site URL
- Review suspension and invoice state
- Comment

#### `EmailResource`

Purpose:

- Renewal and invoicing operations for email accounts

Notable capabilities:

- Generate invoice
- Bulk generate invoices grouped by client

#### `TagResource`

Purpose:

- Operational view of task buckets/categories

Notable capabilities:

- Inspect incomplete counts, performance, time taken, due date
- Adjust hourly cost if allowed by policy

#### Smaller Resources

- `HolidayResource`
- `HostingPackageResource`

### Pages and Widgets

#### `AccountingDashboard`

Purpose:

- Reporting/dashboard page for receivables

Notable behavior:

- Touches all clients before showing receivables widget, likely to force recalculation/update

#### `SiteHealthDashboard`

Purpose:

- Monitoring dashboard for site and renewal health

Includes widgets for:

- WordPress backup freshness
- WordPress version freshness
- SSL issues
- Site downtime
- Missing Google Analytics
- Upcoming domain renewals

#### General Widgets

Other widgets include:

- Upcoming hosting renewals
- Upcoming domain renewals
- ResellerClub balance
- My upcoming tasks
- User task lists
- Quote of the day

## MCP Layer

### `app/Mcp/Servers/ResellerServer.php`

Defines a small MCP server:

- Name: `Reseller Server`
- Version: `0.0.4`

Registered tools:

- `GetResellerBalance`
- `GetUpcomingRenewals`

### `app/Mcp/Tools/GetResellerBalance.php`

Purpose:

- Return current ResellerClub balance

### `app/Mcp/Tools/GetUpcomingRenewals.php`

Purpose:

- Return upcoming renewals for domains and emails

Inputs:

- optional domain filter
- optional date cutoff

Behavior:

- excludes ignored records
- returns both domains and GSuite/email renewals in one response

## Migration / Schema Evolution Notes

The migration history shows gradual business-driven growth.

### Original foundation

Core tables were introduced for:

- users
- tasks
- timesheets
- clients
- domains
- hostings
- invoices
- invoice_items
- user_leaves
- holidays

### Later task and HR evolution

Later migrations added:

- task estimates
- task descriptions
- salary and work-hours on users
- salary type
- biometric ID
- user check-ins
- half-day leave support
- latitude/longitude on user check-ins

### Later billing evolution

Billing later gained:

- invoice extras
- paid fields on invoices
- client receivable cache field
- package link on hostings
- invoice discounts
- proforma invoice linkage fields
- invoice footnote
- invoice item expiry date
- invoice item line description

### Monitoring / metadata evolution

Later additions introduced:

- sites
- meta
- ignored-at support on sites, domains, hostings, and emails

### Accounting evolution

There is also an accounting track with migrations for:

- accounts
- journal entry types
- journal entries
- billing details on accounts
- client account linkage

This suggests accounting features are present or in progress, even if not fully represented by the scanned models in this pass.

## Key Patterns and Architecture Notes

### Business logic is model-heavy

Much of the app behavior lives directly in Eloquent models:

- invoice calculations
- task timing rules
- leave approval effects
- receivable computation
- renewal/invoicing status

This means future changes should usually start by checking model methods and traits before introducing new service classes.

### Derived values are common

Many values are not stored directly, but computed:

- invoice total
- task performance
- task time taken
- client receivable
- leave days
- invoiced status
- user stars

### Traits and package integrations matter

The app relies on several package-level behaviors:

- Filament comments
- activity logging
- transaction/ledger traits
- media library
- tags
- media folders

Future work should pay attention to these package conventions before refactoring model behavior.

### Filament is the operational center

This is not a passive admin panel. Filament is where users actively run business workflows:

- timing work
- approving leave
- generating invoices
- managing renewals
- checking reports

Any backend changes that affect operations should also be validated against the relevant Filament resource/page/widget.

## Known Risks / Watch Areas

These are useful caution points for future work.

### Hardcoded admin logic

`User::getIsAdminAttribute()` currently treats only user ID `1` as admin.

Impact:

- simple, but brittle
- may not scale to role-based permissions cleanly

### Hardcoded invoice year logic

`Invoice::nextInvoiceNumber()` currently contains a hardcoded `2025` suffix.

Impact:

- likely to cause incorrect numbering behavior across years unless updated

### Side effects inside accessors/relations

`Email::client()` may write `client_id` when accessed if it is missing.

Impact:

- surprising behavior
- could cause hidden writes during read flows

### Business rules are embedded in model methods

Renewal, invoicing, leave, and task rules are tightly embedded.

Impact:

- changes can have broad side effects
- regression risk is high if changes are made without tracing all calling paths

### Some areas look incomplete or evolving

Examples:

- placeholder-like MCP instructions
- TODOs in task and invoice logic
- `Invoice::hasItemsUnbilled()` appears unfinished

Impact:

- some features may be partial or still under construction

## Practical Mental Model For Future Work

When approaching this app, it is helpful to think in five connected modules:

1. Work management
   - `Task`
   - `Timesheet`
   - `Tag`
   - comments

2. HR and attendance
   - `User`
   - `UserLeave`
   - `UserCheckIn`
   - `Holiday`

3. Client operations
   - `Client`
   - `Domain`
   - `Hosting`
   - `Email`
   - `HostingPackage`

4. Billing and accounting
   - `Invoice`
   - `InvoiceItem`
   - `InvoiceExtra`
   - accounts/journal-related schema

5. Monitoring and integrations
   - `Site`
   - `Meta`
   - ResellerClub integration
   - MCP tools
   - site health widgets

## Suggested First Places To Check During Future Changes

If we revisit the repo later, these are strong first-stop files:

- `app/Models/Task.php`
- `app/Models/User.php`
- `app/Models/UserLeave.php`
- `app/Models/Invoice.php`
- `app/Models/Domain.php`
- `app/Models/Hosting.php`
- `app/Models/Email.php`
- `app/Filament/Resources/TaskResource.php`
- `app/Filament/Resources/InvoiceResource.php`
- `app/Filament/Resources/DomainResource.php`
- `app/Filament/Resources/HostingResource.php`
- `app/Filament/Resources/UserLeaveResource.php`

## Summary

This repository is an operations-heavy Laravel app where:

- clients and renewable assets feed invoicing workflows
- users and tasks feed performance/timesheet workflows
- leave and attendance feed HR workflows
- Filament acts as the primary business console
- MCP currently exposes a narrow reseller/renewal tool surface

For future work, assume most important business behavior is defined in model methods plus Filament actions, not only in controllers or services.
