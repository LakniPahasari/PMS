# Business Process Models (BPMN)
> Paste each PlantUML block into https://www.plantuml.com/plantuml/uml/ to render and export as PNG.

---

## BPMN 1 — Prescription Intake & Dispensing

**Description:**
This process begins when a customer arrives at the pharmacy with a physical prescription. The pharmacist locates or registers the customer in PharmaTrack, then creates a prescription record selecting medicines, quantities, and dosage instructions. The system validates stock availability and expiry dates in real time. If an age-restricted medicine is included, the pharmacist must verify the customer's ID before saving. Once saved, the prescription moves through a review workflow — it can be approved, then processed (which triggers automatic stock deduction and a low-stock alert if thresholds are breached), or rejected with a documented reason. Upon processing, the system generates an invoice and the pharmacist records payment. The process concludes when the customer collects their medication.

```plantuml
@startuml BPMN_Prescription
title BPMN — Prescription Intake & Dispensing Process
skinparam swimlaneWidth 200

|Customer|
start
:Arrives with prescription;

|Pharmacist|
:Search for customer in PharmaTrack;
if (Customer registered?) then (No)
  :Register new customer;
else (Yes)
endif
:Open Add Prescription form;
:Select customer;
:Add medicines\n(name, qty, dosage);
if (Age-restricted medicine?) then (Yes)
  :Verify customer ID / DOB;
  :Tick "ID Verified";
else (No)
endif
:Review allergy warnings shown by system;
if (Allergy conflict noted?) then (Yes)
  :Add special note / consult;
else (No)
endif
:Click Save Prescription;

|System|
:Validate stock & expiry for each medicine;
if (Stock insufficient or medicine expired?) then (Yes)
  |Pharmacist|
  :Modify or remove medicine;
  stop
else (No)
endif
:Save prescription (Status: Pending);
:Record in Audit Log;
:Trigger low-stock alert if stock < 10;

|Pharmacist|
:Review prescription details;
if (Decision?) then (Reject)
  :Enter rejection reason;
  |System|
  :Update status → Rejected;
  stop
else (Approve → Process)
  |System|
  :Update status → Approved → Processed;
  :Deduct stock quantities;
  if (Any medicine now < 10 units?) then (Yes)
    :Insert low-stock alert;
  else (No)
  endif
  :Set processed_at timestamp;
  :Log to Audit Log;
endif

|Pharmacist|
:Open Payment form;
:Select payment status & method;

|Customer|
:Pays (Cash or Card);

|System|
:Record payment (status: Paid, paid_at timestamp);
:Invoice available for printing;

|Pharmacist|
:Print invoice & hand over medication;

|Customer|
:Collects medication and receipt;
stop

@enduml
```

---

## BPMN 2 — Inventory / Stock Management

**Description:**
The inventory management process is initiated by the Store Manager who monitors stock through the PharmaTrack dashboard. The system automatically surfaces low-stock warnings, expiry alerts, and out-of-stock counts via stat cards. Three parallel sub-processes run as needed: adding new medicines (with a duplicate-name guard), updating existing stock quantities after deliveries (with audit logging of the before and after quantities), and reviewing expiry dates to mark expired medicines inactive. Inactive medicines are immediately hidden from all dispensing screens while their records are retained for audit purposes. All changes are written to the Audit Log with the acting staff member's ID and timestamp.

```plantuml
@startuml BPMN_Inventory
title BPMN — Inventory / Stock Management Process
skinparam swimlaneWidth 220

|Store Manager|
start
:Log in to PharmaTrack;
:Navigate to Medicine Stock page;

|System|
:Display dashboard stat cards\n(Active, Low Stock, Expiring, Out of Stock);
if (Low stock items exist?) then (Yes)
  :Show yellow warning banner;
else (No)
endif

|Store Manager|
:Review stock list;

fork
  note: **Path A — Add New Medicine**
  :Click "+ Add Medicine";
  :Enter name, category, batch,\nqty, price, expiry, supplier;
  |System|
  :Check for duplicate medicine name;
  if (Duplicate found?) then (Yes)
    |Store Manager|
    :Use more specific name\n(e.g. include strength);
  else (No)
    |System|
    :Insert medicine record;
    if (Quantity < 10?) then (Yes)
      :Insert low-stock alert;
    else (No)
    endif
  endif

fork again
  note: **Path B — Update Stock Quantity**
  :Click Edit on existing medicine;
  :Update quantity field\n(delivery received);
  |System|
  :Save old quantity to Audit Log;
  :Update quantity;
  if (New qty < 10 AND old qty ≥ 10?) then (Yes)
    :Insert low-stock alert;
  else (No)
  endif

fork again
  note: **Path C — Manage Expiry**
  :Filter/review expiry dates;
  if (Medicine expired?) then (Yes)
    :Open Edit modal;
    :Toggle is_active = Inactive;
    |System|
    :Hide from prescription screens;
    :Retain record for audit;
  else (No)
  endif

end fork

|System|
:Log action in Audit Log\n(user ID, timestamp, changes);
:Refresh dashboard counts;
stop

@enduml
```

---

## BPMN 3 — Payment Collection

**Description:**
The payment process is triggered either automatically (via the "Proceed to Payment" button shown after saving a new prescription) or manually from the Payments page. The pharmacist opens the payment form, which pre-loads the prescription summary and auto-calculates the total from medicine unit prices. The pharmacist sets the payment status — Unpaid (deferred), Awaiting Pickup (medication prepared but not yet collected), or Paid (payment received). When marking as Paid, the payment method (Cash or Card) must be selected. The system records the payment, logs the transaction in the Audit Log, and makes the invoice available for printing. The medication is then handed to the customer.

```plantuml
@startuml BPMN_Payment
title BPMN — Payment Collection Process
skinparam swimlaneWidth 200

|Pharmacist|
start
:Prescription saved;
if (Proceed to payment now?) then (Yes)
  :Click "Proceed to Payment →" button;
else (Later)
  :Navigate to Payments page;
  :Click "+ New Payment";
  :Select unpaid prescription from list;
endif

|System|
:Load prescription details;
:Calculate total from medicine unit prices;
:Display payment form;

|Pharmacist|
:Review medicine list and total amount;
:Select payment status;

if (Status?) then (Paid)
  :Select payment method\n(Cash or Card);
  |Customer|
  :Hands over payment;
  |Pharmacist|
  :Confirm amount received;
  |System|
  :Set paid_at = current timestamp;
  :Update status = Paid;
else if (Status?) then (Awaiting Pickup)
  |System|
  :Save status = Awaiting Pickup;
  note right: Medication prepared,\ncustomer not yet arrived
else (Unpaid)
  |System|
  :Save status = Unpaid;
  note right: Payment deferred
endif

|System|
:Record payment in PAYMENT table;
:Log action in Audit Log;

if (Status = Paid?) then (Yes)
  |Pharmacist|
  :Generate and print invoice;
  |Customer|
  :Receives medication & receipt;
  stop
else (No)
  :Payment pending — process later;
  stop
endif

@enduml
```
