# AI Prompt Log

AI-assisted development tools were used during this take-home assignment as development and review assistants.

The screenshots in this directory contain representative prompts and discussions used during implementation.

## Tools Used

- ChatGPT — architecture discussion, implementation guidance, debugging, testing strategy, concurrency reasoning, frontend planning, and documentation review.
- GitHub Copilot — repository-wide final code review.

## Prompt Evidence

### 01 — Architecture Planning

Discussion covering the application structure, database schema, Laravel responsibilities, and the decision to keep controllers thin while placing the transactional order workflow in a service.

### 02 — Order Transaction Design

Discussion covering the order-creation workflow, database transaction boundary, product locking, stock validation, total calculation, order-item snapshots, inventory deduction, and queued confirmation.

### 03 — Concurrency Strategy

Discussion covering `lockForUpdate()`, deterministic product lock ordering, overselling prevention, and manual verification using independent MySQL connections.

### 04 — Testing Strategy

Discussion/review covering successful order creation, insufficient-stock rollback, validation, queue assertions, customer history, low-stock behavior, and database isolation.

### 05 — Frontend Development

Discussion covering Blade, Alpine.js, Vite, local dependencies, API integration, and keeping product prices, taxes, stock validation, and order totals authoritative on the Laravel backend.

### 06 — Final GitHub Copilot Review

Repository-wide read-only review performed before final submission. Findings were manually evaluated before changes were accepted.

## Development Approach

AI suggestions were treated as proposals rather than automatically accepted changes.

The development workflow used throughout the assignment was:

```text
Requirement
    ↓
Understand
    ↓
Discuss architecture
    ↓
Implement focused change
    ↓
Inspect
    ↓
Test
    ↓
Debug if necessary
    ↓
Review
    ↓
Commit

