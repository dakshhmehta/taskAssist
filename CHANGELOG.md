# Changelog

## [2026-06-16]
### Fixed
- Dashboard "My Upcoming Tasks" widget now always shows the task with an active timer, even if it falls outside the top 5 scheduled tasks. Uses a UNION query: ticking task + 5 upcoming scheduled tasks, deduplicated — up to 6 total.
