# UI/HCI Wireframes — Lo-Fi Mockups
> These are low-fidelity wireframes representing the initial design intent for each screen.
> Replace each wireframe with your actual screenshot in the final report.

---

## 1. Login Page

```
+--------------------------------------------------+
|                                                  |
|              ⚕  PharmaTrack                      |
|         Pharmacy Management System               |
|                                                  |
|  +--------------------------------------------+  |
|  |  Email Address                             |  |
|  |  [ you@drugs4u.co.uk                    ]  |  |
|  |                                            |  |
|  |  Password                                  |  |
|  |  [ ••••••••                             ]  |  |
|  |                                            |  |
|  |  [         Sign In          ]              |  |
|  +--------------------------------------------+  |
|                                                  |
+--------------------------------------------------+
```

**Design notes:** Centred single-column card on a dark background. Brand icon and system name positioned above the form. No registration link — staff accounts are created by the Admin only.

---

## 2. Dashboard

```
+--------+-----------------------------------------------+
|        |  ⚕ PharmaTrack    [📍 Main Branch]   [user] |
| SIDEBAR|-----------------------------------------------|
|        |  Dashboard                                    |
| 🏠 Dash|                                               |
| 👤 Cust|  [ 📋 12 Pending ] [ 💊 3 Low Stock ]        |
| 📋 Prsc|  [ 🔔 5 Alerts  ] [ 👤 48 Customers ]        |
| 💊 Stck|                                               |
| 💳 Pay |  +------------------+  +-------------------+  |
| 👥 Usr |  | Recent Prescrip. |  | Recent Alerts     |  |
| 📁 Aud |  |----------------- |  |-------------------|  |
| 🔔 Alrt|  | # | Customer |St |  | ⚠️ Low Stock: ... |  |
|        |  | 5 | J. Smith |✓  |  | ⚠️ Age Restrict.  |  |
| 🚪 Out |  | 4 | A. Perera|⏳ |  | ✅ Low Stock (ack) |  |
|        |  | 3 | M. Jones |❌ |  |                   |  |
+--------+  +------------------+  +-------------------+  |
            [   View All   ]       [    View All   ]      |
```

**Design notes:** Fixed left sidebar with role-based nav links. Top header shows branch and user name. Four stat cards in a responsive grid. Two-column content area below: recent prescriptions table and recent alerts feed.

---

## 3. Customers List

```
+--------+-----------------------------------------------+
| SIDEBAR|  Customers                                    |
|        |-----------------------------------------------|
|        |  [ 🔍 Search by name or ID... ] 48 customers  [+ Add New Customer]
|        |                                               |
|        |  +------------------------------------------+|
|        |  | ID  | Name       | DOB       | Age | Email | Status | Actions |
|        |  |----|------------|-----------|-----|-------|--------|---------|
|        |  | #1 | Amal Perera| 15Mar1990 | 35  | amal@ | 🟢Active| 👁 ✏️  |
|        |  | #2 | Jane Smith | 02Jun1985 | 40  | jane@ | 🟢Active| 👁 ✏️  |
|        |  | #3 | Mark Jones | —         | —   | —     | ⚫Inact | 👁 ✏️  |
|        |  +------------------------------------------+|
+--------+-----------------------------------------------+
```

**Design notes:** Search bar filters client-side by name or ID. Age column auto-calculated from date of birth. Action column has a view (👁) icon linking to the customer profile page and an edit (✏️) button opening an inline modal.

---

## 4. Add Prescription Modal

```
+--------------------------------------------------------+
| Add New Prescription                              [ X ] |
|--------------------------------------------------------|
| Customer *                                             |
| [ Amal Perera                                     ▼ ] |
| ⚠️ Known allergies: Penicillin                         |
|--------------------------------------------------------|
| Medicines                                              |
|  Medicine              | Qty  | Dosage / Instructions  |
|  [ Amoxicillin 500mg ▼]  [ 10] [ Take twice daily   ] [🗑]|
|  [ Metformin 500mg  ▼]  [  4] [                     ] [🗑]|
|  [+ Add Another Medicine]                              |
|                                                        |
| Next Refill Date  [  2026-08-01  ]                    |
|                                                        |
| ✅ Allergy Checked     🔴 ID Verified (required)       |
|                                                        |
| ⚠️ Validation Summary                                  |
|  ✅ No allergy conflicts  ❌ ID check required         |
|  ✅ Amoxicillin: 50 in stock, 10 needed               |
|                                                        |
|  Special Notes  [                                    ] |
|--------------------------------------------------------|
|    [ Cancel ]                  [ Save Prescription ]   |
+--------------------------------------------------------+
```

**Design notes:** Dynamic medicine row builder — rows can be added or removed. Customer allergy warning auto-appears on customer selection. Validation summary panel shows real-time stock and ID check status. ID Verified toggle is revealed only when an age-restricted medicine is selected.

---

## 5. Medicine Stock Page

```
+--------+-----------------------------------------------+
| SIDEBAR|  Medicine Stock                               |
|        |-----------------------------------------------|
|        | ⚠️ Low Stock Warning: 3 medicines below 10 units [✕]
|        |                                               |
|        | [ 💊 42 Active ] [📉 3 Low Stock] [📅 2 Expiring] [🚫 1 Out]
|        |                                               |
|        | [🔍 Search name or category...] 42 total  [+ Add Medicine]
|        |                                               |
|        | +--------------------------------------------+|
|        | | ID↕| Name↕        | Cat↕| Batch | Qty    | Price | Expiry↕ | Supplier | ID? | Status | Edit |
|        | |----|-------------|-----|-------|--------|-------|---------|----------|-----|--------|------|
|        | | #1 | Amoxicillin | Ant | BT-01 | 50     | £2.00 | Dec 2027| Alliance | No  | 🟢Active|  ✏️  |
|        | | #2 | Metformin   | Oth | BT-02 | ⚠️ 5   | £1.50 | Jun 2026| AAH      | No  | 🟢Active|  ✏️  |
|        | | #3 | Controlled X| Con | BT-03 | Out    | £5.00 | Expired | Phoenix  | Yes | 🟢Active|  ✏️  |
|        | +--------------------------------------------+|
+--------+-----------------------------------------------+
```

**Design notes:** Column headers with ↕ are sortable (click to toggle asc/desc). Low-stock quantities shown as badge with ⚠️. Expired medicines flagged in the Expiry column. Search filters by name or category. Out-of-stock shown as a red badge.

---

## 6. Payment Form Page

```
+--------+----------------------------------------------+
| SIDEBAR|  ← Back   Record Payment — Prescription #5   |
|        |----------------------------------------------|
|        |                                              |
|        | +-------------------------+ +-------------+ |
|        | | PATIENT                 | | Payment     | |
|        | | Amal Perera             | | Status *    | |
|        | | amal@gmail.com          | | ○ 🔴 Unpaid | |
|        | | DOB: 15 Mar 1990        | | ○ 🟡 Await  | |
|        | |-------------------------| | ● 🟢 Paid   | |
|        | | PRESCRIPTION            | |             | |
|        | | #5 · 24 May 2026        | | Method      | |
|        | | Status: Pending         | | [💵 Cash][💳 Card]|
|        | |-------------------------| |             | |
|        | | Medication  Qty  Price  | | Amount (£)  | |
|        | | Amoxicillin  10  £20.00 | | [  20.00  ] | |
|        | | Metformin     4    —    | |             | |
|        | |       Total  £20.00     | | Notes       | |
|        | +-------------------------+ | [          ]| |
|        |                            | [Cancel][Pay]| |
|        |                            +-------------+ |
+--------+----------------------------------------------+
```

**Design notes:** Two-column layout — prescription summary on the left, payment form on the right. Amount field pre-populated from medicine unit prices. Payment method selector only appears when status is "Paid". Status options are styled with colour-coded borders.

---

## 7. Payments List Page

```
+--------+-----------------------------------------------+
| SIDEBAR|  Payments                                     |
|        |-----------------------------------------------|
|        | [💳 25 Total][✅ 18 Paid][🕐 4 Awaiting][🔴 3 Unpaid]
|        |                                               |
|        | 💰 Total Revenue Collected:  £  1,240.00      |
|        |                                               |
|        | [+ New Payment] [All▼][Unpaid][Awaiting][Paid]|
|        |                                               |
|        | +-------------------------------------------+ |
|        | |Pay#|RX   |Customer   |Amt   |Method|Status  |Date   |Act|
|        | |----|-----|-----------|------|------|--------|-------|---|
|        | | #5 |RX-5 |Amal Perera|£20.00|💳Card|🟢 Paid |24 May |✏️🧾|
|        | | #4 |RX-4 |Jane Smith |£15.00|💵Cash|🟢 Paid |23 May |✏️🧾|
|        | | #3 |RX-3 |Mark Jones |  —   |  —   |🔴Unpaid|22 May |✏️  |
|        | +-------------------------------------------+ |
+--------+-----------------------------------------------+
```

**Design notes:** Revenue banner only shown when paid payments exist. Filter tabs are client-side (no page reload). Invoice (🧾) icon only appears for Paid records. Edit (✏️) links back to the payment form for updates.

---

## 8. Invoice Page (Print View)

```
+-------------------------------------------------------+
| ← Back to Prescriptions        [🖨 Print / Save PDF]  |
+-------------------------------------------------------+
|                                                       |
|  ⚕ Drugs 4U                            INVOICE       |
|  Staffordshire, UK                  No: RX-00005      |
|  Registered Pharmacy                Date: 24 May 2026 |
|  PharmaTrack PMS                    Prescription: #5  |
|-------------------------------------------------------|
|  BILLED TO                   PRESCRIPTION DETAILS     |
|  Amal Perera                 Dispensed by: S. Jones   |
|  amal@gmail.com              Created:  24 May 2026    |
|  DOB: 15 Mar 1990            Processed: 24 May 2026   |
|-------------------------------------------------------|
|  ✅ Allergy checked  ✅ ID verified  ✅ Processed      |
|-------------------------------------------------------|
|  # | Medication    | Dosage          | Qty | £Unit |£Sub|
|  1 | Amoxicillin   | Twice daily     |  10 | £2.00 |£20 |
|  2 | Metformin 500 | With food       |   4 |   —   |  — |
|                                  Total:       £20.00   |
|-------------------------------------------------------|
|  No additional notes.            Pharmacist: S. Jones |
|                                  Drugs 4U, Staffs     |
|                                                       |
|  This invoice is computer generated.                  |
+-------------------------------------------------------+
```

**Design notes:** Print-optimised layout — sidebar, header, and action buttons hidden via `@media print` CSS. Pharmacy branding in top-left, invoice metadata top-right. Compliance check row shows allergy/ID/processed status. Full medicine breakdown with unit prices and subtotals.

---

## 9. Alerts Page

```
+--------+-----------------------------------------------+
| SIDEBAR|  Alerts                                       |
|        |-----------------------------------------------|
|        | [🔔 12 Total][⚠️ 5 Unacknowledged][✅ 7 Acked]|
|        |                                               |
|        | [All][Unacknowledged][Acknowledged] [✓ Ack All]
|        |                                               |
|        | +-------------------------------------------+ |
|        | | ● [⚠️ Low Stock]    24 May 12:01           | |
|        | |   Metformin 500mg has 5 unit(s) remaining  | |
|        | |                              [Acknowledge]  | |
|        | |-------------------------------------------| |
|        | | ● [🔴 Age Restrict] 24 May 11:45           | |
|        | |   ID verification required on RX#5         | |
|        | |                              [Acknowledge]  | |
|        | |-------------------------------------------| |
|        | | ✅ [⚠️ Low Stock]   23 May 09:30  (faded)  | |
|        | |   Amoxicillin dropped to 8 units           | |
|        | +-------------------------------------------+ |
+--------+-----------------------------------------------+
```

**Design notes:** Unacknowledged alerts shown with a red dot and full opacity. Acknowledged alerts are faded (50% opacity). Filter tabs instantly show/hide rows. "Acknowledge All" button processes all in a single request.

---

## 10. Audit Log Page

```
+--------+-----------------------------------------------+
| SIDEBAR|  Audit Log                                    |
|        |-----------------------------------------------|
|        | [🔍 Search...        ] [— All actions —  ▼]   |
|        | Showing latest 500 entries                    |
|        |                                               |
|        | +-------------------------------------------+ |
|        | |# |Timestamp  |User       |Action   |Table |ID|Changes|
|        | |--|-----------|-----------|---------|------|-|-------|
|        | |12|24 May     |S. Jones   |[Prescrip|PRESC |5|status:|
|        | |  |12:01:33   |Pharmacist |Added]   |      | |pending→|
|        | |  |           |           |         |      | |processed|
|        | |11|24 May     |S. Jones   |[Payment |PAY   |2|status:|
|        | |  |11:58:10   |Pharmacist |Recorded]|      | |paid   |
|        | |10|24 May     |Admin      |[User    |USER  |3|role:  |
|        | |  |09:15:00   |Admin      |Updated] |      | |pharmacist|
|        | +-------------------------------------------+ |
+--------+-----------------------------------------------+
```

**Design notes:** Read-only table. Action type badges are colour-coded. Changes column shows before → after diffs where recorded. Search and dropdown filter apply simultaneously. Limited to last 500 entries for performance.
