# Product overview

## Summary

JMC ERP is a **multi-tenant SaaS ERP** aimed at organizations that need integrated **procurement, inventory, sales, accounting, and CRM** on a single platform.

## Core modules

| Module | Role |
|--------|------|
| **Procurement** | Sourcing, purchasing, and supplier-facing workflows |
| **Inventory** | Stock levels and movements; single source of truth for quantity |
| **Sales** | Customer orders, fulfillment handoff, and revenue-side records |
| **Accounting** | Accounts payable and receivable aligned with operational documents |
| **CRM** | Master data and relationships for **suppliers** and **customers** |

## Principles

- **Tenant isolation:** All business data is scoped to an organization (tenant).
- **Operational truth:** Inventory changes are recorded through **inventory movements**, not ad hoc field updates.
- **Thin UI, rich domain:** User interfaces (Livewire) stay thin; business rules live in services and domain code.

See [Technical architecture](technical-architecture.md) and [Development conventions](development-conventions.md) for how this maps to implementation. For a full walkthrough of how the modules connect in practice, see [End-to-end overview](end-to-end-overview.md). For phased delivery and onboarding (register first, create organization after login), see [Development phases](development-phases.md).
