# Data model

This section describes **directional** entities for the JMC ERP domain. Physical migrations are the source of truth; update this document when the schema meaningfully changes.

## Tenancy

- **`tenants`** holds one row per organization (business) on the platform; includes **`base_currency`** (ISO 4217, default `USD`) for tenant-scoped money display.
- **`tenant_user`** links users to tenants with a **role** (for example `owner`); a user can exist before they belong to any tenant (sign up without an organization).
- After sign-in, the app requires at least one membership before tenant-scoped ERP routes; **current tenant** is tracked in session (`current_tenant_id`).
- All tenant-owned business tables include **`tenant_id`** (foreign key to `tenants`).

## Core reference entities

| Concept | Purpose |
|---------|---------|
| **Suppliers** | Vendors for procurement and AP |
| **Customers** | Buyers for sales and AR |
| **Products** | Items that can be bought, sold, and stored (no standalone stock quantity column—balances derive from movements) |

## Operational documents

| Concept | Purpose |
|---------|---------|
| **RFQs** | Requests for quotation to a supplier before committing spend |
| **Purchase orders** | Commitments to buy from suppliers (optional link back to an RFQ) |
| **Goods receipts** | Confirmed receipt of goods against a PO; drives inventory receipts and carries **supplier invoice reference** for AP traceability |
| **Sales orders** | Commitments to sell to customers |
| **Inventory movements** | **Authoritative** log of quantity changes (receipts, issues, adjustments, transfers as modeled) |

### Physical columns (inventory)

- **`products`:** `tenant_id`, `name`, optional `sku` (unique per tenant), optional `description`, optional **`reorder_point`** and **`reorder_qty`** (minimum stock level and suggested reorder quantity for alerts).
- **`product_categories`:** `tenant_id`, `name` (unique per tenant). **`category_product`** pivot links products to many categories for the same tenant.
- **`inventory_movements`:** `tenant_id`, `product_id`, signed `quantity` (decimal), `movement_type` (`receipt`, `issue`, `adjustment`, `transfer`), optional `notes`, optional polymorphic `reference` (for example `goods_receipt_lines` for procurement receipts).

On-hand quantity for a product is the **sum** of `inventory_movements.quantity` for that product (within the tenant).

### Physical columns (procurement)

- **`suppliers`:** `tenant_id`, `name`, optional unique-per-tenant `code`, `status` (`active`, `on_hold`, `blocked`), optional `email`, `phone`, `address`, optional `payment_terms`, `tax_id`, `notes`.
- **`rfqs`:** `tenant_id`, `supplier_id`, `status` (`pending_for_approval`, `sent`, `closed`), optional `title`, `notes`.
- **`rfq_lines`:** `rfq_id`, `product_id`, `quantity`, optional `unit_price`, optional line `notes`.
- **`purchase_orders`:** `tenant_id`, `supplier_id`, optional `rfq_id`, `status` (`confirmed`, `partially_received`, `received`, `cancelled`), `order_date`, optional `notes`.
- **`purchase_order_lines`:** `purchase_order_id`, `product_id`, `quantity_ordered`, optional `unit_cost`, `position`.
- **`goods_receipts`:** `tenant_id`, `purchase_order_id`, `status` (`posted`; `draft` reserved), `received_at`, optional **`supplier_invoice_reference`** (AP handoff), optional `notes`.
- **`goods_receipt_lines`:** `goods_receipt_id`, `purchase_order_line_id`, `quantity_received`.

### Physical columns (sales)

- **`customers`:** `tenant_id`, `name`, optional `email`, `phone`, `address`.
- **`sales_orders`:** `tenant_id`, `customer_id`, `status` (`confirmed`, `partially_fulfilled`, `fulfilled`, `cancelled`), `order_date`, optional `notes`.
- **`sales_order_lines`:** `sales_order_id`, `product_id`, `quantity_ordered`, optional `unit_price`, `position`.
- **`sales_shipments`:** `tenant_id`, `sales_order_id`, `status` (`posted`; `draft` reserved), `shipped_at`, optional `notes`. Fulfillment posts **issue** inventory movements referenced from **`sales_shipment_lines`**.
- **`sales_shipment_lines`:** `sales_shipment_id`, `sales_order_line_id`, `quantity_shipped`.
- **`sales_invoices`:** `tenant_id`, `sales_order_id`, `status` (`issued`; `draft` reserved), `issued_at`, optional **`customer_document_reference`** (AR handoff), optional `notes`. Cumulative **invoice lines per sales order line** cannot exceed **quantity_ordered** (fulfillment is tracked separately via shipments).
- **`sales_invoice_lines`:** `sales_invoice_id`, `sales_order_line_id`, `quantity_invoiced`, optional `unit_price`.

## Relationships (conceptual)

- Purchase orders and sales orders line up to **products** and affect **inventory** through **movements**.
- Accounting **AP** links to supplier-facing documents; **`goods_receipts.supplier_invoice_reference`** ties receiving to the supplier’s bill for later AP posting.
- Accounting **AR** links to customer-facing documents; **`sales_invoices.customer_document_reference`** ties billing to the customer’s reference for later AR posting.

### Physical columns (accounting)

- **`accounts_receivable`:** `tenant_id`, `sales_invoice_id` (unique), `customer_id`, `total_amount`, `amount_paid`, `status` (`open`, `partial`, `paid`), `posted_at`. Created when a sales invoice is issued (line totals from invoice lines).
- **`accounts_payable`:** `tenant_id`, `goods_receipt_id` (unique), `supplier_id`, `total_amount`, `amount_paid`, `status`, `posted_at`. Posted explicitly from a posted goods receipt; amount from receipt lines × PO `unit_cost`. Cleared **supplier advances** on the same PO auto-apply on posting.
- **`supplier_advances`:** `tenant_id`, `purchase_order_id`, `supplier_id`, `amount`, `amount_applied`, `payment_method`, `status` (`scheduled`, `cleared`, `cancelled`), `paid_at`, optional PDC cheque fields, optional `reference` / `notes`. Prepayment before goods receipt; PDC starts `scheduled` until cleared.
- **`supplier_advance_applications`:** links a cleared advance to `accounts_payable` with applied `amount` and `applied_at`.
- **`supplier_payments` / `customer_payments`:** `tenant_id`, party (`supplier_id` / `customer_id`), `amount`, `paid_at`, optional `reference`, `notes`.
- **`supplier_payment_allocations` / `customer_payment_allocations`:** link a payment to one payable/receivable with an `amount`; sum of allocations equals the payment `amount`, and cannot exceed the open balance on each open item.

## Indexing and integrity

- Use **foreign keys** for relational integrity between tenants, parties, documents, and lines.
- Index **tenant_id** and common filter columns (status, dates, foreign keys used in lists and reports).

For module-specific emphasis, see [Modules](modules/overview.md).
