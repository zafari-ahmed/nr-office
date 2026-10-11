# NR Associates Office — Database Design (MySQL 8)

Conventions: snake_case plural table names, `id` BIGINT UNSIGNED PK, `*_id` foreign keys, money as
`DECIMAL(14,2)` in PKR, `created_at` / `updated_at` on every table, `deleted_at` (soft delete) on business
records. Status columns are `VARCHAR(20)` validated in the app (easier to extend than MySQL ENUM).

## Module map
| Module | Tables |
|---|---|
| Foundation | `users`, `settings`, `attachments`, `activity_logs`, `notifications` (+ Laravel `sessions`, `cache`, `jobs`, `password_reset_tokens`) |
| Projects | `clients`, `projects`, `project_follow_ups`, `project_notes` |
| Quotations | `quotations`, `quotation_items` |
| Invoices | `invoices`, `invoice_items`, `invoice_payments` |
| Document log | `document_logs` (+ `attachments`) |
| Expenses | `expense_heads`, `expenses` |
| Inventory | `inventory_categories`, `inventory_items`, `inventory_transactions` |
| Employees | `employees`, `employee_salaries`, `employee_leaves` |
| Site rotation | `site_visits` (rules live in `settings`) |

## Foundation
**users** — id, name, email (unique), phone, password, role (`admin`/`manager`/`accounts`/`staff`), employee_id → employees (nullable), is_active, last_login_at, remember_token, timestamps

**settings** — id, group (`company`, `rotation`, `numbering`, `finance`), key (unique), value (TEXT), timestamps

**attachments** — id, attachable_type, attachable_id (morph), collection (`receipt`, `document`, `photo`, `file`), disk, path, original_name, mime_type, size_bytes, uploaded_by → users, timestamps

**activity_logs** — id, user_id → users, project_id → projects (nullable), subject_type, subject_id (nullable morph), action (`created`, `updated`, `status_changed`, `payment_recorded`, `logged`…), description, properties (JSON), created_at, updated_at

**notifications** — Laravel standard: id (UUID), type, notifiable_type, notifiable_id, data (JSON), read_at, timestamps

## Projects
**clients** — id, name, company_name, contact_person, email, phone, address, city, ntn, notes, timestamps, deleted_at

**projects** — id, code (unique, e.g. NR-2026-014), name, client_id → clients, status (`lead`, `quoted`, `active`, `on_hold`, `completed`, `cancelled`), project_value, site_address, city, start_date, end_date, manager_id → users, description, timestamps, deleted_at

**project_follow_ups** — id, project_id → projects, title, notes, due_date, assigned_to → users, status (`pending`, `done`), snoozed_until, completed_at, source_type/source_id (morph, e.g. a document_log), created_by → users, timestamps

**project_notes** — id, project_id → projects, user_id → users, body (TEXT), is_pinned, timestamps

## Quotations
**quotations** — id, quotation_number (unique), revision (INT, 1 = first), parent_id → quotations (previous revision, nullable), project_id → projects, client_id → clients, title, issue_date, valid_until, status (`draft`, `sent`, `accepted`, `rejected`, `expired`, `revised`), subtotal, discount_type (`percent`/`amount`), discount_value, discount_amount, tax_rate, tax_amount, total, notes, terms, sent_at, accepted_at, invoice_id → invoices (after convert, nullable), created_by → users, timestamps, deleted_at

**quotation_items** — id, quotation_id → quotations (cascade), sort_order, description, quantity DECIMAL(12,2), unit, rate, amount, timestamps

## Invoices
**invoices** — id, invoice_number (unique), project_id → projects, client_id → clients, quotation_id → quotations (nullable), issue_date, due_date, status (`draft`, `sent`, `partially_paid`, `paid`, `overdue`, `cancelled`), subtotal, discount_type, discount_value, discount_amount, tax_rate, tax_amount, total, amount_paid, balance_due, notes, terms, sent_at, created_by → users, timestamps, deleted_at

**invoice_items** — id, invoice_id → invoices (cascade), sort_order, description, quantity, unit, rate, amount, timestamps

**invoice_payments** — id, invoice_id → invoices (cascade), payment_date, amount, method (`cash`, `bank_transfer`, `cheque`, `online`), reference_no, notes, received_by → users, timestamps

## Document log
**document_logs** — id, project_id → projects, document_type (`quotation`/`invoice`), reference_no, amount (nullable), sent_date, sent_to_name, sent_to_email, last_discussion (TEXT), email_subject, email_body (LONGTEXT), next_follow_up_date (nullable), status (`sent`, `client_replied`, `accepted`, `rejected`), created_by → users, timestamps, deleted_at. Files in `attachments`.

## Expenses
**expense_heads** — id, name, slug (unique), color, icon, monthly_budget, is_active, sort_order, timestamps

**expenses** — id, expense_head_id → expense_heads, project_id → projects (nullable), employee_id → employees (nullable, for salary), expense_date, payroll_month (DATE, nullable), amount, description, vendor, payment_method (`cash`, `bank_transfer`, `cheque`, `card`), reference_no, status (`pending`, `approved`, `rejected`), approved_by → users, approved_at, created_by → users, timestamps, deleted_at. Receipts in `attachments`.

## Inventory
**inventory_categories** — id, name, type (`asset`/`consumable`), timestamps

**inventory_items** — id, inventory_category_id → inventory_categories (nullable), type (`asset`/`consumable`), name, asset_tag (unique, nullable), unit, quantity DECIMAL(12,2), min_quantity, unit_cost, condition (`new`, `good`, `fair`, `damaged`, `disposed`), location, assigned_employee_id → employees (nullable), purchase_date, warranty_until, notes, timestamps, deleted_at

**inventory_transactions** — id, inventory_item_id → inventory_items (cascade), type (`purchase`, `issue`, `return`, `adjustment`, `assign`, `unassign`, `dispose`), quantity, balance_after, employee_id → employees (nullable), project_id → projects (nullable), transaction_date, notes, created_by → users, timestamps

## Employees
**employees** — id, employee_code (unique, EMP-001), full_name, father_name, cnic (unique, 35202-1234567-1), date_of_birth, gender, phone, email, address, emergency_contact_name, emergency_contact_phone, designation, department, employment_type (`permanent`, `contract`, `intern`), joining_date, leaving_date, basic_salary, bank_name, bank_account_no, photo_path, status (`active`, `resigned`, `terminated`), is_site_staff, rotation_order, notes, timestamps, deleted_at

**employee_salaries** — id, employee_id → employees (cascade), effective_from, basic_salary, allowances, deductions, net_salary, reason, created_by → users, timestamps

**employee_leaves** — id, employee_id → employees (cascade), leave_type (`annual`, `sick`, `casual`, `unpaid`), start_date, end_date, reason, status (`pending`, `approved`, `rejected`), approved_by → users, timestamps

Live status (In office / On site / On leave) is derived: on_site if a `site_visits` row is `on_site`; on_leave if an approved leave covers today.

## Site rotation
**site_visits** — id, employee_id → employees, project_id → projects (nullable), site_name, site_address, departure_date, planned_days, expected_return_date, actual_return_date, status (`scheduled`, `on_site`, `returned`, `cancelled`), purpose, notes, reminder_sent_at, last_overdue_reminder_at, created_by → users, timestamps

Display status: `returning_soon` when on_site and expected_return_date ≤ today + remind days; `overdue` when on_site and expected_return_date < today.

Rotation rules (`settings`, group `rotation`): `rotation.default_stay_days` (7), `rotation.remind_days_before` (2), `rotation.overdue_reminder_every_days` (1), `rotation.notify_roles` (admin,manager).

## Relationship diagram (short)
```
clients 1─* projects 1─* quotations 1─* quotation_items
                    │            └─(convert)→ invoices 1─* invoice_items
                    ├─* invoices 1─* invoice_payments
                    ├─* project_follow_ups, project_notes, document_logs
                    ├─* expenses *─1 expense_heads
                    └─* site_visits *─1 employees 1─* employee_salaries, employee_leaves
inventory_categories 1─* inventory_items 1─* inventory_transactions ; inventory_items *─1 employees (assigned)
attachments → any (morph) ; activity_logs → any (morph) + project
```
