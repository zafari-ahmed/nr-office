# NR Associates Office — Module-wise Development Plan

Stack: **Laravel 12 (PHP 8.2+)**, **MySQL 8**, Blade + Tailwind CSS 4 + Alpine.js, Chart.js for charts.
Currency **PKR**, employee IDs use **CNIC**. Every feature below belongs to exactly one module; each module
owns its own controller(s), models, migrations, views (`resources/views/<module>/`) and routes block.

Status legend: ✅ built in this first PR · 🔜 next phase

---

## M0. Foundation (shared)
| # | Feature | Status |
|---|---------|--------|
| 0.1 | App shell: collapsible sidebar, top bar (search, + New menu, notification bell, avatar), mobile bottom tabs | ✅ |
| 0.2 | Design system: colours, Inter type scale, buttons, inputs, status pills, cards, tables, drawers, toasts | ✅ |
| 0.3 | Light theme only (dark mode toggle removed at Zafar's request) | ✅ |
| 0.4 | Login / logout, remember me | ✅ |
| 0.5 | Roles: `admin`, `manager`, `accounts`, `staff` with route-level gates | ✅ |
| 0.6 | Polymorphic file attachments (quotes, invoices, logs, expenses, employees, projects) | ✅ |
| 0.7 | Activity log (who did what, shown on dashboard + project timeline) | ✅ |
| 0.8 | Global search across projects, clients, employees, quotations, invoices | ✅ |
| 0.9 | Auto numbering (NR-2026-014, QT-2026-0001, INV-2026-0001) from Settings | ✅ |

## M1. Dashboard
| # | Feature | Status |
|---|---------|--------|
| 1.1 | Greeting + date | ✅ |
| 1.2 | 5 stat cards: Active projects, Quotations pending, Unpaid invoices (PKR), This month's expenses vs last month, Staff on site today | ✅ |
| 1.3 | "Needs attention": overdue follow-ups, overdue invoices, quotes awaiting reply, staff due back, low-stock items — each with one-click action | ✅ |
| 1.4 | "Upcoming follow-ups" next 7 days grouped by project, with Done / Snooze | ✅ |
| 1.5 | "On site now" strip (avatar, site, return date) | ✅ |
| 1.6 | Charts: monthly expenses by head (stacked, 6 months); income vs expenses (line, 6 months) | ✅ |
| 1.7 | Recent activity feed | ✅ |

## M2. Clients & Projects (the hub)
| # | Feature | Status |
|---|---------|--------|
| 2.1 | Clients CRUD (name, contact, phone, email, NTN, address) | ✅ |
| 2.2 | Projects list: table view + Kanban by status (Lead, Quoted, Active, On hold, Completed) | ✅ |
| 2.3 | Project create / edit, auto project code | ✅ |
| 2.4 | Project detail: header, KPI row (Quoted, Invoiced, Received, Outstanding, Expenses) | ✅ |
| 2.5 | Tabs: Overview, Follow-ups, Quotations, Invoices, Expenses, Site visits, Files, Notes | ✅ |
| 2.6 | Follow-ups: add, mark done, snooze, assign to user | ✅ |
| 2.7 | Notes (pin) and Files (upload/download) | ✅ |
| 2.8 | Activity timeline incl. "Logged" document cards | ✅ |

## M3. Quotations
| # | Feature | Status |
|---|---------|--------|
| 3.1 | Quotations list with status filter | ✅ |
| 3.2 | Editor: line items (description, qty, unit, rate, amount), discount, tax, live totals | ✅ |
| 3.3 | Live branded preview beside the form | ✅ |
| 3.4 | Revisions (Rev 1, Rev 2…) keeping history | ✅ |
| 3.5 | Status flow Draft → Sent → Accepted / Rejected / Expired | ✅ |
| 3.6 | Convert accepted quotation to invoice | ✅ |
| 3.7 | Print / PDF view | ✅ (browser print) · 🔜 server PDF + email send |

## M4. Invoices & Payments
| # | Feature | Status |
|---|---------|--------|
| 4.1 | Invoices list with summary chips (Draft, Sent, Partially paid, Overdue) | ✅ |
| 4.2 | Invoice editor (same line-item engine as quotations) | ✅ |
| 4.3 | "Record payment" drawer (date, amount, method, reference) with auto status / balance | ✅ |
| 4.4 | Overdue auto-flag (daily scheduled command) | ✅ |
| 4.5 | Print / PDF view | ✅ (browser print) |

## M5. Document Log (simple form for documents made outside the system)
| # | Feature | Status |
|---|---------|--------|
| 5.1 | "Log sent document" drawer: Project, Type (Quotation/Invoice), Reference no., Amount, Date sent, Sent to (name + email), Last discussion notes, Email subject + pasted email text, multi-file upload, Next follow-up date, Status (Sent, Client replied, Accepted, Rejected) | ✅ |
| 5.2 | Next follow-up date auto-creates a project follow-up | ✅ |
| 5.3 | Shows on the project timeline as a "Logged" card with paperclip, collapsible email | ✅ |
| 5.4 | Log list page with filters | ✅ |

## M6. Expenses
| # | Feature | Status |
|---|---------|--------|
| 6.1 | Expense heads (Salary, Daily office items, Office renovation, + custom) with monthly budget | ✅ |
| 6.2 | Head cards with budget progress for current month | ✅ |
| 6.3 | Expense entries table filterable by head, month, project, status | ✅ |
| 6.4 | Add expense: receipt upload, optional project link, employee link for salary | ✅ |
| 6.5 | Approval flow (pending → approved / rejected) with pending badge | ✅ |

## M7. Inventory
| # | Feature | Status |
|---|---------|--------|
| 7.1 | Tabs: Assets and Consumables, categories | ✅ |
| 7.2 | Stock level bars, low-stock badges (min quantity) | ✅ |
| 7.3 | Assign asset to employee | ✅ |
| 7.4 | Stock movements (purchase, issue, return, adjust, assign, dispose) + item history | ✅ |

## M8. Employees
| # | Feature | Status |
|---|---------|--------|
| 8.1 | Card grid with photo, designation, live status (In office / On site / On leave) | ✅ |
| 8.2 | Profile tabs: Profile, Salary history, Site visits, Assigned assets, Documents | ✅ |
| 8.3 | CNIC, emergency contact, bank details | ✅ |
| 8.4 | Salary revisions history | ✅ |
| 8.5 | Leaves (dates, type, status) driving "On leave" | ✅ |
| 8.6 | Link employee to a login user | ✅ |

## M9. Site Rotation
| # | Feature | Status |
|---|---------|--------|
| 9.1 | Timeline calendar: staff rows × day columns, bars coloured by status (Scheduled, On site, Returning soon, Overdue, Returned) | ✅ |
| 9.2 | Side panels: Returning soon, Overdue | ✅ |
| 9.3 | Schedule visit: auto return date from default stay; suggests next person in rotation | ✅ |
| 9.4 | Mark departed / returned, extend stay | ✅ |
| 9.5 | Reminders: N days before return + repeating overdue reminders (scheduled command → in-app notifications) | ✅ · 🔜 email/WhatsApp |

## M10. Settings
| # | Feature | Status |
|---|---------|--------|
| 10.1 | Company profile (name, address, phone, email, NTN, logo) | ✅ |
| 10.2 | Users & roles | ✅ |
| 10.3 | Rotation rules: default days on site, remind N days before, overdue reminder frequency, count weekends | ✅ |
| 10.4 | Expense heads & budgets | ✅ |
| 10.5 | Numbering prefixes | ✅ |
| 10.6 | Taxes & currency | ✅ |
| 10.9 | Access rights per role (Settings › Access rights, admin only): 21 permissions across all modules; staff by default see only their own profile, team projects and their own expenses. Project team picker. Account menu: my profile, edit profile, change password, log out | ✅ |
| 10.7 | Notifications: on/off and recipient roles per alert (overdue invoices, pending expenses, follow-ups, site returns, overdue from site, low stock daily/weekly) | ✅ |

## M11. Notifications
| # | Feature | Status |
|---|---------|--------|
| 11.1 | Bell with unread count + panel | ✅ |
| 11.2 | Generated by `nr:daily-reminders` (follow-ups due, invoices overdue, staff returning / overdue, low stock) | ✅ |

## M12. Reports
| # | Feature | Status |
|---|---------|--------|
| 12.0 | Reports page (admin, manager, accounts) with period filter (month, quarter, Jul–Jun financial year, calendar year, last 12 months, custom) | ✅ |
| 12.1 | Six reports: income & expenses, expenses by head vs budget, outstanding invoices by age, project summary, site visits, inventory value. CSV export and print | ✅ |

---

## Phases
1. **Phase 1 (this PR)** — Foundation, Projects hub, Quotations, Invoices, Document log, Expenses, Inventory, Employees, Site rotation, Settings, Dashboard.
2. **Phase 2** — Server-side PDF + email sending, email/WhatsApp reminders.
3. **Phase 3** — Polish from real use, permissions per action, mobile PWA.

## Setup
```bash
cp .env.example .env        # set DB_* to your MySQL database
composer install
php artisan key:generate
php artisan migrate --seed  # creates tables + sample data + admin@nrassociates.pk / password
php artisan storage:link
npm install && npm run build
php artisan serve
# cron: * * * * * php artisan schedule:run   (daily reminders, overdue invoices)
```
