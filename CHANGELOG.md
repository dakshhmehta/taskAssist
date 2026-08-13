# Changelog

## [2026-08-12]
### Added
- Per-user public iCal calendar feed (`/calendar/{token}.ics`) exposing the next 15 days of tasks, protected by a per-user secret token generated from the Filament UserResource.
- SetTimer MCP tool now supports parallel timers across tasks and a "stop all" action when `task_id` is omitted.

### Changed
- Task scheduler now distributes task times starting from 10:00 AM instead of all setting midnight.
- Activity log changes column now uses `key: New ← Old` format.
- Tag edit page task rows are clickable to open the task edit screen.
- Bulk Delete action available on the tag edit page's task relation table.
- Upcoming renewals reminder email subject shows urgency ("URGENT: N Renewals are Overdue — Action Required") when expired items exist.
- Invoice discount fields default to 0.

## [2026-06-16]
### Fixed
- Dashboard "My Upcoming Tasks" widget now always shows the task with an active timer, even if it falls outside the top 5 scheduled tasks. Uses a UNION query: ticking task + 5 upcoming scheduled tasks, deduplicated — up to 6 total.
