---
name: task-scheduler
description: Manage Daksh's task list and convert task capture/completion updates into a constrained weekday work schedule. Use when the user asks to add, update, complete, prioritize, estimate, assign to a project, or schedule tasks; when the user gives urgency/importance/duration/project metadata; or when the assistant needs to decide what should fit into Daksh's 4-hours-per-day Monday-Friday bandwidth.
---

# Task Scheduler

## Overview

Capture tasks, classify them into priority buckets, and maintain a realistic schedule for Daksh.
Default to concise execution. Preserve the user's intent, but normalize missing metadata using the rules below.

## Core task model

Treat a task as having these fields when possible:

- title
- urgency (`urgent` or `not urgent`)
- importance (`important` or `not important`)
- priority (`P1`..`P4`)
- estimated_minutes
- project
- scheduled_date or unscheduled

If the user does not provide an estimate, treat the task as a `60` minute task by default.

If the user omits fields, infer defaults instead of asking unless the missing detail is required.

Default values:

- importance: `important`
- urgency: `not urgent`
- priority from those defaults: `P2`
- project: `Personal`
- estimated_minutes: `60` unless the user gives it

If the user gives `P1`, `P2`, `P3`, or `P4` directly, convert it into urgency + importance using the mapping below and store both the explicit priority and the derived urgency/importance fields.

## Priority rules

Map urgency + importance to priority exactly as follows:

1. `P1` = urgent and important
2. `P2` = not urgent but important
3. `P3` = urgent but not important
4. `P4` = not urgent and not important

Also support the reverse mapping: if the user states `P1`..`P4`, derive urgency and importance from it immediately.

When scheduling, prefer lower number priorities first.

## Scheduling constraints

Apply these constraints when making or updating the schedule:

- Schedule at most **4 hours per day** of task work
- Assume Daksh works on scheduled tasks only on **Monday to Friday**
- Treat **weekends as off** and do not place tasks there unless the user explicitly overrides this
- **Respect unavailability** — check `unavailable.md` and skip any dates listed there
- Fit tasks into the earliest valid weekday slots while respecting priority order
- If a task estimate would overflow a day, place the remainder on the next valid weekday only if the user explicitly asked for splitting; otherwise move the full task to the next day with enough capacity
- If no estimate is provided, schedule the task as `60` minutes by default

## User Unavailability

Maintain an `unavailable.md` file in the skill folder to track dates when Daksh is not available for task work.

### File format
- One date per line in `YYYY-MM-DD` format
- Lines starting with `#` are comments
- Example:
  ```
  # Daksh's Unavailable Dates
  2026-05-01
  2026-05-15
  ```

### Rules
- **Read before scheduling** — always check `unavailable.md` before assigning dates to tasks
- **Skip unavailable days** — do not schedule any tasks on dates listed
- **Clean up past dates** — when reading the file, remove any dates in the past (before today)
- **Weekends** — do not add weekend dates to unavailable.md; weekends are already blocked by default

### Managing unavailability
When the user mentions being unavailable (travel, leave, meetings, etc.):
1. Parse the date(s) from their message
2. Append each date as a new line in `unavailable.md`
3. Confirm briefly: "Marked unavailable: 2026-05-15"
4. If regenerating schedule, skip these dates

## Storage rules

Persist tasks in per-project JSON files named exactly as `(project name).json` into this skill folder itself. Ensure the filename is always in **lowercase**.
Examples:

- `personal.json`
- `gimpex.json`
- `rankers.json`

Each file should contain a structured array of open tasks for that project. Store each task as an object with normalized fields such as:

- title
- priority
- urgency
- importance
- estimated_minutes
- project
- scheduled_date or null

Do not keep completed-task history in these files.
When a task is completed, remove it from the relevant project JSON file.

Whenever a session begins, always first scan all the .json files in this skill folder. Use it as memory.

**Token-Efficient Task Addition:** For adding new tasks, use the bundled `scripts/add-task.js` helper instead of reading/parsing/writing manually. This avoids expensive file readback.

```bash
node ~/.openclaw/workspace/skills/task-scheduler/scripts/add-task.js "Task Title" project P1 [minutes]
```

Example:
```bash
node ~/.openclaw/workspace/skills/task-scheduler/scripts/add-task.js "Fix login bug" gimpex P2 30
```

**Token-Efficient Task Listing:** For reading all tasks across all projects, use the bundled `scripts/list-all-tasks.js` helper instead of reading each JSON file individually. This reduces tool calls from 15+ to 1.

```bash
# List all tasks across all projects
node ~/.openclaw/workspace/skills/task-scheduler/scripts/list-all-tasks.js all

# List tasks for a specific project only
node ~/.openclaw/workspace/skills/task-scheduler/scripts/list-all-tasks.js lldc
```

Output: Sorted JSON array (P1 first, then P2, P3, P4) with normalized fields.

**Batch Multi-Project Operations:** When the user marks multiple tasks done or modifies tasks across several projects in a single message, use `list-all-tasks.js` to read all tasks once, identify which project files need edits, then apply all changes. This avoids reading each project file individually and saves tokens.

**Generate Schedule:** Use `scripts/generate-schedule.js` to produce an optimized day-by-day schedule from all tasks while respecting unavailable dates.

```bash
node ~/.openclaw/workspace/skills/task-scheduler/scripts/generate-schedule.js
```

Output: Formatted schedule grouped by day (Mon–Fri), 4h max per day, skipping weekends and unavailable dates. Does NOT write dates back to JSON.

**Daily Briefing (Kian):** Use `scripts/kian.js` to generate the morning task summary.

```bash
node ~/.openclaw/workspace/skills/task-scheduler/scripts/kian.js
```

Runs daily at 10 AM IST via cron. Reports today's tasks, overdue items, and unscheduled P1s.

**Complete Task (with confirmation):** Use `scripts/complete-task.js` to find tasks matching a search phrase.

```bash
node ~/.openclaw/workspace/skills/task-scheduler/scripts/complete-task.js "search phrase"
```

Behavior:
- **No match:** Reports "No tasks found"
- **Single match:** Shows task details, asks for explicit confirmation before marking done
- **Multiple matches:** Lists all matches with numbers, asks user to pick which one

Never auto-complete when multiple matches exist — always ask.

For batch additions, chain multiple calls in one exec. Only use `json-file-editor` or manual node scripting for complex updates (editing existing tasks, bulk moves, etc.).

## Operating rules

### When the user records a new task

1. Capture the task title, project, priority, and estimate from the user's message.
   - Expected format: `"Task name, project, priority"` or `"Task name, project, priority, XXmin"`
   - Default estimate: 60 minutes if not provided.
2. Use the `add-task.js` script in a single `exec` call to append the task:
   ```bash
   node ~/.openclaw/workspace/skills/task-scheduler/scripts/add-task.js "Task Title" project P1 [minutes]
   ```
3. Confirm briefly (e.g., "Added: Task Title — P1, project, 60m").
4. **Defer memory logging** — only log when the user explicitly asks for a schedule regeneration, or batch multiple task changes into one memory entry at session end.
5. Rebuild or update the schedule only when the user asks for it ("What's my schedule?", "Regenerate", etc.).

### When the user modifies a task

1. Locate the task in the relevant project JSON file.
2. Apply the requested modifications (e.g., change priority, update estimate).
3. Log this action (modifying a task) in the daily memory journal (`memory/YYYY-MM-DD.md`).
4. Rebuild or update the schedule if necessary.
5. Confirm the modification briefly.

### When the user marks a task completed

1. Find the matching open task directly in the relevant project JSON file.
2. Remove it entirely from that project's JSON file.
3. Log this action in the daily memory journal (`memory/YYYY-MM-DD.md`).
4. Remove it from future scheduled workload.
5. Reflow later scheduled tasks if useful.
6. If the user marks multiple tasks done in one message (e.g., "Task A, done. Task B is done. Complete Task C"), iterate through and remove all of them from their respective files.
7. Confirm completion briefly.

### When regenerating the schedule

1. Read `unavailable.md` and clean up any past dates
2. Skip all unavailable dates when assigning tasks to days
3. Store the most recent snapshot of the generated schedule in the daily memory journal (`memory/YYYY-MM-DD.md`).
4. Treat this snapshot as a reference point for future workload checks.

### When the user asks what to do next

Recommend the highest-priority scheduled open task first.
If multiple tasks tie, prefer:

1. earlier scheduled date
2. shorter task if it helps fit available bandwidth
3. older task if no other tiebreaker exists

### When information is ambiguous

Make the minimum reasonable assumption and say it briefly after acting.
Example: "I treated this as important but not urgent, so P2."

## Output style

Keep replies compact and operational.
When useful, present scheduled tasks grouped by weekday with total scheduled minutes per day.
Avoid long explanations unless the user asks for planning detail.

## Project-Level Task Reports

When the user asks for a comprehensive task overview grouped by project (e.g., "Give me all tasks, in order of priority, grouped by project" or similar prompts), produce output in this exact format:

1. **Read all JSON files** in the skill folder
2. **Group tasks by project** (use `t.project` or derive from filename)
3. **Sort tasks within each project** by priority (P1 → P4)
4. **Sort projects** by their highest-priority task (projects with P1 tasks appear first)
5. **Output format:**
   - Project name in CAPS with task count and total time
   - Bullet list: `Priority • Task Title — XXm`
   - Grand total at the bottom

### Example Prompts:
- "Give me all tasks, in order of priority, grouped by project"
- "Show me a complete task breakdown by project"
- "List all open tasks organized by project and priority"
- "What tasks do I have across all projects?"

## Important limitation

This skill defines the policy and decision process for managing Daksh's tasks. Persist active tasks in per-project JSON files and keep only open tasks there. If a more advanced database or UI is later introduced, preserve the same task semantics unless Daksh changes the rules.