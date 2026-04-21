# Queue Dashboard — Full Implementation Plan

This document defines the full dashboard scope for `monkeyslegion-queue`, using the current manual external wiring approach (router/bootstrap handled outside this package).

## 1) Goal

Build a production-ready Queue Dashboard UI to:

- Monitor queue health in real time
- Inspect ready, delayed, processing, and failed jobs
- Perform safe operational actions (retry, clear, delete, move)
- Help operators debug queue issues quickly

## 2) Integration Boundary

- This package provides:
  - Dashboard controller(s)
  - View templates (`.ml.php`)
  - Provider metadata (view namespace + controller classes)
  - Data shaping for dashboard pages/tabs
- Host application provides:
  - Router/controller registration
  - Container wiring (queue driver instance, auth middleware, CSRF/session)
  - Final access control policy

## 3) Dashboard Tabs

## 3.1 Overview

Purpose: quick health snapshot.

Show:
- Queue cards (ready, processing, delayed, failed)
- Total queues and queue names
- Driver/meta info (if available)
- Last refresh timestamp

Data sources:
- `getQueues()`
- `getStats($queue)`
- `countFailed()`

## 3.2 Queues

Purpose: inspect queued jobs by queue name.

Show:
- Queue selector
- Job table (id, job class, attempts, created_at)
- Quick actions: peek, delete, move queue

Data sources:
- `listQueue($queue, $limit)`
- `peek($queue)`
- `findJob($id, $queue)`
- `deleteJob($id, $queue)`
- `moveJobToQueue($jobId, $from, $to)`

## 3.3 Delayed

Purpose: visibility into delayed/release flow.

Show:
- Delayed count per queue
- Jobs becoming ready soon
- Manual trigger for delayed processing (optional)

Data sources:
- `getStats($queue)['delayed']`
- `processDelayedJobs($queue)` (action endpoint)

## 3.4 Failed Jobs

Purpose: triage and recovery.

Show:
- Failed jobs table (id, job, attempts, exception, failed_at)
- Bulk retry
- Bulk delete
- Clear all failed

Data sources:
- `getFailed($limit)`
- `retryFailed($limit)`
- `removeFailedJobs($ids)`
- `clearFailed()`

## 3.5 Actions / Maintenance

Purpose: controlled operations.

Show:
- Clear queue
- Purge all queues
- Retry failed (limited batch)
- Optional move between queues utility

Data sources:
- `clear($queue)`
- `purge()`
- `retryFailed($limit)`

## 4) Routing & Controllers

Use router attributes on controller methods.

Current:
- `DashboardController` with `#[RoutePrefix('/queue/dashboard')]`
- `index()` route already exists

Planned:
- Add method routes for tabs:
  - `GET /` → overview
  - `GET /queues`
  - `GET /delayed`
  - `GET /failed`
  - `GET /maintenance`
- Add action routes (POST) for mutating operations:
  - retry failed
  - clear queue
  - delete failed jobs
  - purge (high-risk; guarded)

## 5) Views Structure

Under `src/Template/views/dashboard/`:

- `index.ml.php` (overview)
- `queues.ml.php`
- `delayed.ml.php`
- `failed.ml.php`
- `maintenance.ml.php`
- `partials/`:
  - `header.ml.php`
  - `tabs.ml.php`
  - `flash.ml.php`
  - `stats-cards.ml.php`
  - `tables/*.ml.php`

Phase 1 stays plain HTML; phase 2 can gradually adopt ML template directives/components.

## 6) Data Contract (Controller → View)

Each page should return:

```php
[
  'view' => 'queue-dashboard::dashboard.<page>',
  'data' => [
    'title' => '...',
    'active_tab' => 'overview|queues|delayed|failed|maintenance',
    'stats' => [...],
    'items' => [...],
    'filters' => [...],
    'actions' => [...],
    'messages' => [...],
  ],
]
```

Use this consistently so host app rendering is predictable.

## 7) Security & Safety Rules

- Mutating endpoints must be POST-only
- Require CSRF protection in host app
- Add confirmation for destructive actions (`purge`, `clearFailed`, `clear`)
- Validate queue names and job IDs strictly
- Return explicit error messages (no silent fail)
- Recommend host app auth middleware for all dashboard routes

## 8) Performance

- Default page sizes for lists (e.g., 20/50/100)
- Avoid loading all jobs at once
- Optional refresh interval for overview
- Keep expensive actions explicit/manual

## 9) Implementation Phases

1. **Phase A — Navigation + Read-only**
   - Build all GET tabs and shared partials
   - Wire read-only stats and tables

2. **Phase B — Mutations**
   - Add POST action endpoints + form actions in UI
   - Add success/error feedback blocks

3. **Phase C — Hardening**
   - Validation and edge-case handling
   - Better empty states and operator UX
   - Extra tests for action behavior

4. **Phase D — Polish**
   - Improved UI consistency
   - Optional filtering/sorting refinements

## 10) Acceptance Criteria

- All planned tabs render with real queue data
- Failed job recovery actions work
- Queue maintenance actions work with guardrails
- Controller routes are attribute-based
- Provider exposes dashboard controllers for external registration
- Views resolve through `queue-dashboard` namespace
- Tests cover controller data contracts and critical actions

## 11) Start Checklist

- [ ] Add tab methods and route attributes in dashboard controller(s)
- [ ] Create tab view files and shared partials
- [ ] Implement read-only data loaders per tab
- [ ] Implement POST action endpoints for mutations
- [ ] Add tests for tab payloads + action flows
- [ ] Final pass on UX states (empty/error/success)

