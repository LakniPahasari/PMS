# PharmaTrack — Pharmacy Management System
## Group Project Report
### COMP70047 Enterprise Systems | Semester 2, 2025

---

| | |
|---|---|
| **Client** | Drugs 4U, Staffordshire |
| **System Name** | PharmaTrack PMS |
| **Module** | COMP70047 Enterprise Systems |
| **Submission Semester** | Semester 2, 2025 |
| **GitHub Repository** | https://github.com/LakniPahasari/PMS |

---

## Table of Contents

1. [Introduction & Business Context](#1-introduction--business-context)
2. [Project Objectives & Scope](#2-project-objectives--scope)
3. [Agile Methodology & SCRUM Framework](#3-agile-methodology--scrum-framework)
4. [Project Plan & Timeline](#4-project-plan--timeline)
5. [Tools & Version Control](#5-tools--version-control)
6. [System Requirements](#6-system-requirements)
7. [Business Process Models (BPMN)](#7-business-process-models-bpmn)
8. [System Models](#8-system-models)
9. [Database Design (ERD)](#9-database-design-erd)
10. [UI/HCI Design — Wireframes](#10-uihci-design--wireframes)
11. [System Implementation — Actual UI](#11-system-implementation--actual-ui)
12. [Architecture & Technology Stack](#12-architecture--technology-stack)
13. [Conclusion](#13-conclusion)

---

## 1. Introduction & Business Context

Drugs 4U is an independent pharmacy based in Staffordshire, UK. The business currently manages its prescription intake, medicine inventory, and customer records using a combination of paper-based logs and disconnected spreadsheets. This approach introduces significant operational risk: prescriptions can be lost or misfiled, stock levels are not monitored in real time, payment records are fragmented, and there is no centralised audit trail for regulatory compliance.

This report documents the analysis, design, and development of **PharmaTrack** — a web-based Pharmacy Management System commissioned to address these challenges. PharmaTrack digitises the core pharmacy workflows: prescription lifecycle management, inventory tracking, payment collection, staff administration, and system-wide alerting. The system is designed to be accessible from any device via a web browser and is intended for use by three staff roles: Pharmacist, Store Manager, and Admin.

The project was delivered as a collaborative group assignment for the COMP70047 Enterprise Systems module using the Agile SCRUM methodology over a series of structured sprints.

---

## 2. Project Objectives & Scope

### 2.1 Objectives

- Design and develop a multi-user, role-based web application to manage pharmacy operations
- Replace paper-based prescription handling with a structured digital workflow
- Provide real-time inventory monitoring with automated low-stock and expiry alerts
- Implement a traceable payment recording system linked to each prescription
- Maintain a full audit trail of all staff actions for compliance purposes
- Follow Agile SCRUM practices throughout the project lifecycle

### 2.2 In Scope

| Module | Description |
|--------|-------------|
| Customer Management | Register, search, view, and edit customer records including medical history and known allergies |
| Prescription Management | Create multi-medicine prescriptions, manage lifecycle (Pending → Approved → Processed / Rejected), enforce stock and ID checks |
| Inventory Management | Add and update medicines, track expiry dates, receive low-stock alerts, deactivate expired stock |
| Payment Management | Record payment against prescriptions, track status (Unpaid / Awaiting Pickup / Paid), generate print-ready invoices |
| Alerts | System-generated alerts for low stock and age-restriction events, with acknowledge functionality |
| Audit Log | Immutable log of all create/update actions with before-and-after change records |
| System Users | Admin-only management of staff accounts, roles, and branch assignments |
| Dashboard | Summary statistics and quick access to recent prescriptions and alerts |

### 2.3 Out of Scope

- Online payment gateway integration
- Customer self-registration portal (deferred to future iteration)
- Mobile native application
- Integration with external pharmacy wholesaler systems

---

## 3. Agile Methodology & SCRUM Framework

### 3.1 Approach

The team adopted the **SCRUM** framework within the broader Agile methodology. Work was organised into five time-boxed sprints, each delivering a working, demonstrable increment of the system. The product backlog was maintained in **Jira**, with user stories written in the standard format (*As a [role], I want to [action] so that [benefit]*) and assigned story point estimates using Fibonacci sizing (1, 2, 3, 5, 8).

Sprint ceremonies included:
- **Sprint Planning** — backlog items selected and tasks assigned at the start of each sprint
- **Daily Standups** — brief team synchronisation (what was done, what is planned, any blockers)
- **Sprint Review** — demonstration of working functionality to the team
- **Sprint Retrospective** — reflection on process improvements for the next sprint

### 3.2 Roles

| SCRUM Role | Responsibility |
|------------|----------------|
| Product Owner | Prioritised the backlog based on the client brief and user story acceptance criteria |
| SCRUM Master | Facilitated ceremonies, removed blockers, tracked sprint velocity |
| Development Team | Designed, built, and tested all system features |

### 3.3 User Stories & Backlog

The full product backlog was derived from the client requirements. The following table summarises the stories delivered across all sprints, grouped by priority (MoSCoW).

| Priority | User Story | Story Points | Sprint |
|----------|-----------|:---:|:---:|
| Must Have | As a pharmacist, I want to register and manage customer records so that prescriptions are linked to verified patients | 3 | 1 |
| Must Have | As a pharmacist, I want to add a prescription with multiple medicines so that complex prescriptions are fully captured | 5 | 2 |
| Must Have | As a pharmacist, I want to approve, process, and reject prescriptions so that the dispensing workflow is controlled | 3 | 2 |
| Must Have | As a pharmacist, I want stock to be automatically deducted when a prescription is processed so that inventory remains accurate | 3 | 2 |
| Must Have | As a pharmacist, I want to see a validation summary before saving so that allergy and stock risks are flagged | 2 | 2 |
| Must Have | As a store manager, I want to add new medicines to inventory so that stock levels are tracked | 3 | 3 |
| Must Have | As a store manager, I want to update stock quantities so that records reflect deliveries | 2 | 3 |
| Must Have | As a store manager, I want to track expiry dates so that expired drugs are not dispensed | 3 | 3 |
| Should Have | As a store manager, I want to search and sort the medicine list so that I can check availability quickly | 2 | 3 |
| Should Have | As a pharmacist, I want to record payment against a prescription (cash or card) so that financial records are maintained | 3 | 4 |
| Should Have | As a pharmacist, I want to generate a print-ready invoice so that the customer has a receipt | 2 | 4 |
| Should Have | As an admin, I want to manage system user accounts so that only authorised staff can log in | 3 | 5 |
| Should Have | As a manager, I want to view an audit log of all actions so that changes are traceable | 2 | 5 |
| Should Have | As any staff, I want to view and acknowledge system alerts so that issues are acted upon | 2 | 5 |
| Could Have | As a store manager, I want expired medicines automatically flagged as inactive so they cannot be dispensed | 2 | 3 |
| Won't Have | As a customer, I want to self-register online (deferred to future iteration) | — | — |

### 3.4 Sprint Summary

**Sprint 1 — Foundation & Customer Module**
Established the project repository, database schema, authentication system, and core layout (sidebar, header, CSS design system). Delivered the Customer Management module: list view, add modal, edit modal, customer profile page with prescription history.

**Sprint 2 — Prescription Management**
Designed and implemented the multi-medicine prescription system using a junction table (PRESCRIPTION_ITEM). Delivered the full prescription lifecycle — Pending, Approved, Processed, Rejected — with stock deduction, dosage fields, ID verification hard block, rejection reasons, processed_at timestamp, and a client-side validation summary panel.

**Sprint 3 — Inventory / Stock Management**
Delivered the Medicine Stock page with stat cards, sortable columns, search filtering, low-stock banner, and expiry date tracking. Added duplicate medicine name check, quantity change history in the audit log, and server-side block on dispensing expired medicines.

**Sprint 4 — Payments & Invoice**
Designed and implemented the payment flow: "Proceed to Payment" trigger from prescription save, payment form page with status and method selection, payments list with filter tabs and revenue summary, and a print-ready invoice page.

**Sprint 5 — Administration, Alerts & Audit**
Delivered System Users management (add/edit/deactivate staff), the Audit Log page with change diffs, and the Alerts page with individual and bulk acknowledge functionality. Fixed all remaining database compatibility issues.

---

## 4. Project Plan & Timeline

### 4.1 Gantt Chart

> **[INSERT GANTT CHART IMAGE HERE]**
> *(Export from Jira or your project planning tool and insert above)*

The Gantt chart above illustrates the planned and actual timelines for each sprint. Each sprint ran for approximately two weeks. Key milestones include the completion of the database schema design at the end of Sprint 1, the prescription module at the end of Sprint 2, and final system integration testing prior to submission.

### 4.2 Sprint Velocity

| Sprint | Planned Points | Delivered Points | Notes |
|--------|:-:|:-:|---|
| Sprint 1 | 8 | 8 | All items delivered on schedule |
| Sprint 2 | 18 | 18 | Prescription module required additional schema changes (FK drop for multi-medicine) |
| Sprint 3 | 10 | 10 | Delivered including all Should Have inventory stories |
| Sprint 4 | 8 | 8 | Payment and invoice delivered; existing PAYMENT table extended via ALTER TABLE |
| Sprint 5 | 7 | 7 | All remaining backlog items cleared |
| **Total** | **51** | **51** | 100% backlog completion |

---

## 5. Tools & Version Control

### 5.1 Version Control — GitHub

All source code was managed using **Git** with a remote repository hosted on **GitHub**. The team adopted a trunk-based workflow committing directly to the `main` branch given the team size, with meaningful commit messages describing each increment.

**Repository:** https://github.com/LakniPahasari/PMS

> **[INSERT SCREENSHOT OF GITHUB REPOSITORY / COMMIT HISTORY HERE]**
> *(Screenshot showing the repository page with commit history)*

Key commits include:

| Commit | Description |
|--------|-------------|
| `1848955` | Initial commit — project setup |
| `0e2036a` | Initial build: dashboard, customers, auth, base layout |
| `6c361f3` | Add files via upload (PMS.sql schema) |
| `98acaf0` | feat: complete PMS modules — prescriptions, stock, payments, users, alerts, audit |

### 5.2 Task Management — Jira

The product backlog, sprint boards, and user story tracking were managed in **Jira**. Each user story was created as a Jira issue with acceptance criteria, story point estimate, priority, and sprint assignment. The SCRUM board provided a real-time Kanban view (To Do / In Progress / Done) for the team.

> **[INSERT SCREENSHOT OF JIRA SPRINT BOARD HERE]**
> *(Screenshot of Jira board showing user stories across columns)*

> **[INSERT SCREENSHOT OF JIRA BACKLOG HERE]**
> *(Screenshot of the Jira backlog with all user stories listed)*

### 5.3 Development Environment

| Tool | Purpose |
|------|---------|
| MAMP | Local web server (Apache + MySQL on port 8889) |
| PHP 8+ | Server-side scripting language |
| MySQL | Relational database management system |
| phpMyAdmin | Database administration and schema management |
| Visual Studio Code | Primary code editor |
| Git / GitHub | Version control and remote repository |
| Jira | Agile project management and sprint tracking |
| PlantUML | Diagram generation (BPMN, UML, ERD) |

---

## 6. System Requirements

### 6.1 Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR01 | The system shall allow staff to register, view, and edit customer records | Must |
| FR02 | The system shall support prescriptions containing multiple medicines | Must |
| FR03 | The system shall enforce a prescription status lifecycle: Pending → Approved → Processed / Rejected | Must |
| FR04 | The system shall automatically deduct stock when a prescription is marked as Processed | Must |
| FR05 | The system shall block dispensing of expired or inactive medicines | Must |
| FR06 | The system shall require ID verification before saving a prescription containing age-restricted medicines | Must |
| FR07 | The system shall display customer allergy information when a prescription is being created | Must |
| FR08 | The system shall generate low-stock and age-restriction alerts automatically | Must |
| FR09 | The system shall allow staff to record payment (cash/card) against a prescription | Should |
| FR10 | The system shall generate a print-ready invoice for each paid prescription | Should |
| FR11 | The system shall log all create and update actions with the acting user's ID and timestamp | Should |
| FR12 | The system shall allow admin users to create and manage staff accounts | Should |
| FR13 | The system shall enforce role-based access control (Admin, Pharmacist, Store Manager) | Must |
| FR14 | The system shall provide a dashboard with key operational statistics | Should |

### 6.2 Non-Functional Requirements

| ID | Requirement | Category |
|----|-------------|----------|
| NFR01 | All pages must load within 2 seconds on a local network | Performance |
| NFR02 | Passwords must be hashed using PHP `password_hash()` (bcrypt) | Security |
| NFR03 | All user inputs must be validated server-side before database interaction | Security |
| NFR04 | The system must prevent SQL injection via PDO prepared statements | Security |
| NFR05 | The UI must be accessible on standard desktop browsers (Chrome, Firefox, Edge) | Compatibility |
| NFR06 | The system must prevent duplicate medicine dispensing of expired stock | Data Integrity |
| NFR07 | All database mutations must use transactions where multiple tables are affected | Reliability |
| NFR08 | The audit log must be append-only; no audit records may be deleted through the UI | Compliance |

---

## 7. Business Process Models (BPMN)

Business Process Model and Notation (BPMN) diagrams were produced to map the key operational workflows at Drugs 4U. Three core processes were modelled: prescription intake and dispensing, inventory management, and payment collection. Swimlanes are used throughout to distinguish the responsibilities of the Customer, Pharmacist, and System actors.

---

### 7.1 BPMN 1 — Prescription Intake & Dispensing

This process begins when a customer arrives at the pharmacy with a physical prescription. The pharmacist locates or registers the customer in PharmaTrack, then creates a prescription record selecting medicines, quantities, and dosage instructions. The system validates stock availability and expiry dates in real time. If an age-restricted medicine is included, the pharmacist must verify the customer's ID before saving. Once saved, the prescription moves through a review workflow — it can be approved, then processed (which triggers automatic stock deduction and a low-stock alert if thresholds are breached), or rejected with a documented reason. Upon processing, the system generates an invoice and the pharmacist records payment. The process concludes when the customer collects their medication.

> **[INSERT BPMN 1 DIAGRAM IMAGE HERE]**
> *(Prescription Intake & Dispensing — rendered from PlantUML)*

---

### 7.2 BPMN 2 — Inventory / Stock Management

The inventory management process is initiated by the Store Manager who monitors stock through the PharmaTrack dashboard. The system automatically surfaces low-stock warnings, expiry alerts, and out-of-stock counts via stat cards. Three parallel sub-processes run as needed: adding new medicines (with a duplicate-name guard), updating existing stock quantities after deliveries (with audit logging of the before and after quantities), and reviewing expiry dates to mark expired medicines inactive. Inactive medicines are immediately hidden from all dispensing screens while their records are retained for audit purposes. All changes are written to the Audit Log with the acting staff member's ID and timestamp.

> **[INSERT BPMN 2 DIAGRAM IMAGE HERE]**
> *(Inventory / Stock Management — rendered from PlantUML)*

---

### 7.3 BPMN 3 — Payment Collection

The payment process is triggered either automatically via the "Proceed to Payment" button shown after saving a new prescription, or manually from the Payments page. The pharmacist opens the payment form, which pre-loads the prescription summary and auto-calculates the total from medicine unit prices. The pharmacist sets the payment status — Unpaid (deferred), Awaiting Pickup (medication prepared but not yet collected), or Paid (payment received). When marking as Paid, the payment method (Cash or Card) must be selected. The system records the payment, logs the transaction in the Audit Log, and makes the invoice available for printing.

> **[INSERT BPMN 3 DIAGRAM IMAGE HERE]**
> *(Payment Collection — rendered from PlantUML)*

---

## 8. System Models

### 8.1 Use Case Diagram

The use case diagram identifies three primary internal actors — Admin, Pharmacist, and Store Manager — plus the Customer as an external actor for the future self-registration feature. The Admin has unrestricted access to all modules. The Pharmacist handles the clinical workflow: customer management, prescription lifecycle, and payment recording. The Store Manager focuses on inventory operations. Use cases are grouped into five functional areas: Customer Management, Prescription Management, Inventory Management, Payment Management, and System Administration. Role-based access control is enforced at session level via the `requireRole()` PHP function and reflected in the sidebar navigation.

> **[INSERT USE CASE DIAGRAM IMAGE HERE]**
> *(Use Case Diagram — rendered from PlantUML)*

---

### 8.2 Sequence Diagram 1 — Add Prescription & Payment Handoff

This sequence diagram illustrates the interaction between the Pharmacist, browser, backend PHP controllers, and the MySQL database when creating a new prescription. The browser sends a POST request to `add_prescription.php` which validates all inputs server-side including stock availability, medicine expiry dates, and ID verification for age-restricted drugs. On success, the prescription and its line items are persisted across two tables (PRESCRIPTION and PRESCRIPTION_ITEM) within a single database transaction. The JSON response includes the `prescription_id`, which the browser uses to dynamically replace the modal footer with a "Proceed to Payment" button, enabling a seamless handoff to the payment workflow without a full page reload.

> **[INSERT SEQUENCE DIAGRAM 1 IMAGE HERE]**
> *(Add Prescription & Payment Handoff — rendered from PlantUML)*

---

### 8.3 Sequence Diagram 2 — Process Prescription & Stock Deduction

This diagram shows the stock deduction flow when a pharmacist changes a prescription's status to "Processed". The `edit_prescription.php` controller first fetches the current status from the database to detect the state transition. Stock is only deducted on the first transition to "processed", ensuring idempotency if the record is saved again later. For each medicine line item, the stock quantity is decremented in the MEDICINE_STOCK table and a low-stock alert is inserted into the ALERT table if the new level falls below ten units. The `processed_at` timestamp is set using SQL `COALESCE` so it is never overwritten on subsequent edits.

> **[INSERT SEQUENCE DIAGRAM 2 IMAGE HERE]**
> *(Process Prescription & Stock Deduction — rendered from PlantUML)*

---

### 8.4 System Component Diagram

PharmaTrack follows a three-tier architecture: a Client Layer (web browser), a Web Server Layer (MAMP running Apache with PHP 8), and a Database Layer (MySQL). Within the web server, the frontend consists of custom HTML/CSS and vanilla JavaScript using the Fetch API for asynchronous form submissions. The backend is split into Page Controllers, which render full HTML views, and Action Controllers, which handle POST requests and return JSON responses. All controllers access the database through a centralised PDO singleton (`getDB()` in `config/db.php`). This design keeps database connection logic in one place and enables consistent error handling and transaction support across all modules. The `config/session.php` file provides `requireLogin()` and `currentUser()` functions used by every page.

> **[INSERT COMPONENT DIAGRAM IMAGE HERE]**
> *(System Component Diagram — rendered from PlantUML)*

---

### 8.5 Class Diagram

The class diagram represents the core data entities of PharmaTrack and their structural relationships. A CUSTOMER can have many PRESCRIPTION records. Each PRESCRIPTION is created by a SYSTEM_USER and may have one associated PAYMENT. The many-to-many relationship between prescriptions and medicines is resolved through the PRESCRIPTION_ITEM junction table, which also stores the prescribed quantity and dosage instructions per line item. MEDICINE_STOCK records are the source of truth for medicine availability, unit pricing, and expiry dates. The ALERT and AUDIT_LOG tables serve as system-wide event records, decoupled from any single entity, capturing operational anomalies and staff actions respectively.

> **[INSERT CLASS DIAGRAM IMAGE HERE]**
> *(Class / Data Structure Diagram — rendered from PlantUML)*

---

## 9. Database Design (ERD)

### 9.1 Entity Relationship Diagram

The PharmaTrack database comprises eight tables organised around the central PRESCRIPTION entity. Foreign key constraints enforce referential integrity across all relationships. The PRESCRIPTION_ITEM junction table resolves the many-to-many relationship between prescriptions and medicines, allowing a single prescription to reference multiple MEDICINE_STOCK records. Cascading constraints ensure that orphaned records cannot exist. The AUDIT_LOG table uses a generic `target_table` and `target_id` column pattern to capture actions across any entity without requiring per-table audit tables.

> **[INSERT ERD IMAGE HERE]**
> *(Entity Relationship Diagram — rendered from PlantUML)*

### 9.2 Table Descriptions

| Table | Primary Key | Description |
|-------|-------------|-------------|
| CUSTOMER | customer_id | Stores patient demographic information, medical history, and known allergies |
| SYSTEM_USER | system_user_id | Staff accounts with hashed passwords, roles (admin/pharmacist/store_manager), and branch assignment |
| PRESCRIPTION | prescription_id | Prescription header record: status lifecycle, timestamps, notes, rejection reason, compliance flags |
| PRESCRIPTION_ITEM | item_id | Junction table linking prescriptions to medicines with quantity and dosage per line item |
| MEDICINE_STOCK | stock_id | Medicine catalogue with real-time quantity, pricing, batch/expiry tracking, and age-restriction flag |
| PAYMENT | payment_id | Payment record per prescription: amount, method (cash/card), status (unpaid/awaiting_pickup/paid) |
| ALERT | alert_id | System-generated alerts for low stock and age-restriction events with acknowledgement tracking |
| AUDIT_LOG | log_id | Immutable log of all staff actions with JSON change details (before/after values) |

### 9.3 Key Design Decisions

**Multi-medicine via junction table:** The initial design placed a single `stock_id` on the PRESCRIPTION table. During Sprint 2, this was replaced with the PRESCRIPTION_ITEM junction table to support the requirement that a single prescription can contain multiple medicines. The foreign key constraint was dropped and the column removed using `ALTER TABLE PRESCRIPTION DROP FOREIGN KEY ... DROP COLUMN stock_id`.

**Idempotent stock deduction:** Stock is deducted only on the first transition from any status to "processed". The backend compares the old status fetched before the update to detect this transition, preventing double-deduction if the record is saved again.

**Audit log with JSON diffs:** The `details` column in AUDIT_LOG stores a JSON object comparing old and new field values (e.g. `{"quantity": {"before": 50, "after": 40}}`), making change history human-readable without requiring a separate audit table per entity.

**COALESCE for processed_at:** The `processed_at` timestamp is set using `COALESCE(processed_at, NOW())` ensuring it records only the first time a prescription is processed, even if the record is subsequently edited.

---

## 10. UI/HCI Design — Wireframes

Lo-fidelity wireframes were produced during the design phase to establish the layout and interaction patterns for each screen before development began. The wireframes follow established HCI principles: consistent navigation via a persistent sidebar, clear visual hierarchy using cards and stat panels, contextual feedback via toast notifications, and progressive disclosure (e.g. rejection reason field only appearing when the "Rejected" status is selected).

---

### 10.1 Login Page

> **[INSERT WIREFRAME — LOGIN PAGE]**

The login page presents a centred card layout with email and password fields. Staff accounts are created by the Admin; there is no self-registration link. On successful authentication, users are redirected to the dashboard. The session stores the user's ID, name, role, and branch for use across all subsequent pages.

---

### 10.2 Dashboard

> **[INSERT WIREFRAME — DASHBOARD]**

The dashboard provides an at-a-glance operational summary via four stat cards (Pending Prescriptions, Low Stock Items, Unacknowledged Alerts, Active Customers). Below the cards, a two-column layout shows the five most recent prescriptions and the five most recent alerts, each with a "View All" link to the relevant module page.

---

### 10.3 Customer List

> **[INSERT WIREFRAME — CUSTOMERS PAGE]**

The customers page lists all registered patients in a searchable, sortable table. Search filters client-side by name or customer ID with no page reload. The Age column is auto-calculated from date of birth. Each row provides a profile view (👁) and an inline edit (✏️) action.

---

### 10.4 Add Prescription Modal

> **[INSERT WIREFRAME — ADD PRESCRIPTION MODAL]**

The Add Prescription modal features a dynamic medicine row builder allowing one or more medicines to be added per prescription. Each row captures medicine selection, quantity, and dosage instructions. A real-time validation summary panel displays customer allergy warnings, ID check requirements, and current stock levels as the pharmacist builds the prescription.

---

### 10.5 Medicine Stock Page

> **[INSERT WIREFRAME — MEDICINE STOCK PAGE]**

The stock page leads with a low-stock warning banner (when applicable) and four stat cards. The medicine table supports client-side search by name or category and click-to-sort on five columns. Quantity badges distinguish low stock, out of stock, and healthy levels. Expired medicines are flagged in the Expiry column.

---

### 10.6 Payment Form

> **[INSERT WIREFRAME — PAYMENT FORM PAGE]**

The payment form uses a two-column layout: prescription summary (patient details, medicine list with totals) on the left and the payment form on the right. Status options (Unpaid / Awaiting Pickup / Paid) are presented as large selectable tiles. The payment method selector appears conditionally only when "Paid" is selected. The amount field is pre-populated from the calculated medicine total.

---

### 10.7 Payments List

> **[INSERT WIREFRAME — PAYMENTS LIST PAGE]**

The payments list page displays all payment records with a revenue summary banner. Filter tabs (All / Unpaid / Awaiting Pickup / Paid) apply instantly without page reload. Paid records show an invoice link (🧾) alongside the edit button.

---

### 10.8 Invoice

> **[INSERT WIREFRAME — INVOICE PAGE]**

The invoice page presents a print-ready A4-style layout with pharmacy branding, patient details, a medicine line-item table with unit prices and subtotals, a compliance check row, and pharmacist signature block. The sidebar, header, and action buttons are hidden via CSS `@media print`, leaving only the invoice content when printed or saved as PDF.

---

### 10.9 Alerts Page

> **[INSERT WIREFRAME — ALERTS PAGE]**

The alerts page lists all system-generated alerts with type badges (Low Stock, Age Restriction), timestamp, and message. Unacknowledged alerts are highlighted with a red indicator. Individual alerts can be acknowledged with a single button click, or all unacknowledged alerts can be cleared with the "Acknowledge All" button.

---

### 10.10 Audit Log

> **[INSERT WIREFRAME — AUDIT LOG PAGE]**

The audit log provides a read-only chronological list of all staff actions. Each entry shows the acting user, action type (colour-coded badge), target table, record ID, and a before/after diff of changed values. A search box and action-type dropdown filter the list client-side.

---

## 11. System Implementation — Actual UI

The following screenshots show the implemented PharmaTrack system running against the live MySQL database. All features visible in the wireframes above were successfully implemented.

---

### 11.1 Login Page

> **[INSERT SCREENSHOT — LOGIN PAGE]**

---

### 11.2 Dashboard

> **[INSERT SCREENSHOT — DASHBOARD]**

---

### 11.3 Customer Management

> **[INSERT SCREENSHOT — CUSTOMERS LIST]**

> **[INSERT SCREENSHOT — CUSTOMER PROFILE PAGE]**

---

### 11.4 Prescription Management

> **[INSERT SCREENSHOT — PRESCRIPTIONS LIST WITH PAYMENT STATUS COLUMN]**

> **[INSERT SCREENSHOT — ADD PRESCRIPTION MODAL WITH VALIDATION SUMMARY]**

> **[INSERT SCREENSHOT — EDIT PRESCRIPTION MODAL WITH PAYMENT BANNER]**

---

### 11.5 Medicine Stock

> **[INSERT SCREENSHOT — MEDICINE STOCK PAGE WITH STAT CARDS]**

> **[INSERT SCREENSHOT — ADD/EDIT MEDICINE MODAL]**

---

### 11.6 Payments

> **[INSERT SCREENSHOT — PAYMENT FORM PAGE]**

> **[INSERT SCREENSHOT — PAYMENTS LIST WITH REVENUE BANNER]**

---

### 11.7 Invoice

> **[INSERT SCREENSHOT — INVOICE PAGE (PRINT VIEW)]**

---

### 11.8 System Administration

> **[INSERT SCREENSHOT — ALERTS PAGE]**

> **[INSERT SCREENSHOT — AUDIT LOG PAGE WITH CHANGE DIFFS]**

> **[INSERT SCREENSHOT — SYSTEM USERS PAGE]**

---

## 12. Architecture & Technology Stack

### 12.1 Technology Stack

| Layer | Technology | Justification |
|-------|------------|---------------|
| Frontend | HTML5, CSS3 (custom design system), Vanilla JavaScript | Lightweight, no build tooling required; Fetch API provides async UX without a JS framework |
| Backend | PHP 8+ | Widely supported, rapid development, native PDO database abstraction |
| Database | MySQL 8 | Relational integrity, transaction support, familiar to the team |
| Web Server | Apache (via MAMP) | Standard local development stack for PHP/MySQL |
| Version Control | Git / GitHub | Industry-standard; enables parallel development and change history |
| Project Management | Jira | SCRUM board, backlog management, sprint tracking |

### 12.2 Architectural Decisions

**Three-tier architecture:** The system separates concerns into a Client Layer (browser), Web Server Layer (PHP on Apache), and Database Layer (MySQL). Page controllers render HTML views; action controllers return JSON and handle all state mutations. This separation makes the codebase maintainable and testable.

**Prepared statements throughout:** Every database query uses PDO prepared statements, eliminating SQL injection risk at the framework level.

**Database transactions for multi-table writes:** Operations that write to more than one table (e.g. creating a prescription with line items, deducting stock) are wrapped in `beginTransaction() / commit() / rollBack()` blocks to ensure atomicity.

**Pre-loaded PHP → JS JSON:** Rather than making separate AJAX calls to populate client-side validation, all required data (medicine stock levels, customer allergies, prescription items) is serialised from PHP into JavaScript objects at page load time. This eliminates network round-trips for real-time validation feedback.

**RBAC via session:** Role-based access control is enforced server-side via `requireLogin()` and `requireRole()` functions in `config/session.php`. The sidebar navigation reflects the authenticated user's role, restricting menu visibility accordingly.

### 12.3 Security Measures

| Risk | Mitigation |
|------|------------|
| SQL Injection | PDO prepared statements with parameterised queries on all database interactions |
| XSS | All user-supplied content output via `htmlspecialchars()` with `ENT_QUOTES` |
| Authentication bypass | `requireLogin()` called on every protected page; session regenerated on login |
| Weak passwords | Minimum 8-character password policy; hashed with `password_hash()` (bcrypt) |
| CSRF | All mutations require POST method; direct GET to action files returns error |
| Privilege escalation | Role verified server-side; `requireRole()` used for admin-only pages |

---

## 13. Conclusion

PharmaTrack successfully delivers a fully functional, multi-user Pharmacy Management System for Drugs 4U, Staffordshire. All Must Have and Should Have user stories from the product backlog were delivered across five SCRUM sprints, totalling 51 story points.

The system replaces the client's paper-based and spreadsheet workflows with a structured digital solution covering the complete pharmacy operational cycle: from customer registration and prescription intake, through inventory management and automated alerting, to payment collection and invoice generation. The audit log provides a compliance-ready record of all staff actions.

The project was managed using Agile SCRUM practices facilitated by Jira, with all source code version-controlled on GitHub. The three-tier PHP/MySQL architecture is maintainable, secure, and extensible — the Customer self-registration module and external reporting features identified during backlog refinement remain as clearly defined items for future sprints.

The team's iterative approach, with each sprint delivering working software, ensured that architectural decisions (such as the multi-medicine prescription redesign in Sprint 2 and the payment table extension in Sprint 4) could be accommodated without disrupting previously delivered functionality.

---

*End of Report*
