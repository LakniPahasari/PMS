# System Models
> Paste each PlantUML block into https://www.plantuml.com/plantuml/uml/ to render and export as PNG.

---

## 1. Use Case Diagram

**Description:**
The use case diagram identifies three primary internal actors — Admin, Pharmacist, and Store Manager — plus the Customer as an external actor for future self-registration capability. The Admin has unrestricted access to all modules. The Pharmacist handles the clinical workflow: customer management, prescription lifecycle, and payment recording. The Store Manager focuses on inventory operations. Use cases are grouped into five functional areas: Customer Management, Prescription Management, Inventory Management, Payment Management, and System Administration. Role-based access control (RBAC) is enforced at session level via the `requireRole()` function.

```plantuml
@startuml Use_Case_Diagram
left to right direction
title PharmaTrack PMS — Use Case Diagram

skinparam actorStyle awesome
skinparam packageStyle rectangle

actor "Admin" as AD #lightblue
actor "Pharmacist" as PH #lightgreen
actor "Store Manager" as SM #lightyellow
actor "Customer\n(Future)" as CU #lightgrey

rectangle "PharmaTrack PMS" {

  package "Customer Management" {
    usecase "Register Customer" as UC1
    usecase "Search & View Customers" as UC2
    usecase "Edit Customer Details" as UC3
    usecase "View Prescription History" as UC4
  }

  package "Prescription Management" {
    usecase "Create Prescription" as UC5
    usecase "Edit / Update Prescription" as UC6
    usecase "Approve Prescription" as UC7
    usecase "Process & Dispense" as UC8
    usecase "Reject Prescription" as UC9
    usecase "View Prescription Details" as UC10
  }

  package "Inventory Management" {
    usecase "Add Medicine to Stock" as UC11
    usecase "Update Stock Quantity" as UC12
    usecase "Search & Filter Medicines" as UC13
    usecase "Sort Inventory" as UC14
    usecase "Mark Medicine Inactive" as UC15
    usecase "View Expiry Alerts" as UC16
  }

  package "Payment Management" {
    usecase "Record Payment" as UC17
    usecase "Update Payment Status" as UC18
    usecase "View Payment History" as UC19
    usecase "Generate & Print Invoice" as UC20
  }

  package "System Administration" {
    usecase "Manage System Users" as UC21
    usecase "View Audit Log" as UC22
    usecase "Acknowledge Alerts" as UC23
  }
}

PH --> UC1
PH --> UC2
PH --> UC3
PH --> UC4
PH --> UC5
PH --> UC6
PH --> UC7
PH --> UC8
PH --> UC9
PH --> UC10
PH --> UC13
PH --> UC17
PH --> UC18
PH --> UC19
PH --> UC20
PH --> UC23

SM --> UC11
SM --> UC12
SM --> UC13
SM --> UC14
SM --> UC15
SM --> UC16
SM --> UC17
SM --> UC18
SM --> UC19
SM --> UC23

AD --> UC1
AD --> UC2
AD --> UC3
AD --> UC5
AD --> UC6
AD --> UC7
AD --> UC8
AD --> UC9
AD --> UC11
AD --> UC12
AD --> UC15
AD --> UC17
AD --> UC21
AD --> UC22
AD --> UC23

CU --> UC4

@enduml
```

---

## 2. Sequence Diagram — Add Prescription & Proceed to Payment

**Description:**
This sequence diagram illustrates the interaction between the Pharmacist, browser, backend PHP controllers, and the MySQL database when creating a new prescription. The browser sends a POST request to `add_prescription.php` which validates all inputs server-side (stock availability, medicine expiry, ID verification for age-restricted drugs). On success, the prescription and line items are persisted across two tables. The response includes the `prescription_id`, which the browser uses to dynamically replace the modal footer with a "Proceed to Payment" button, enabling a seamless handoff to the payment workflow without a full page reload.

```plantuml
@startuml Seq_AddPrescription
title Sequence Diagram — Add Prescription & Payment Handoff
skinparam sequenceArrowThickness 1.5

actor Pharmacist
participant "Browser\n(prescriptions.php)" as UI
participant "add_prescription\n.php" as AP
database "PRESCRIPTION" as PT
database "PRESCRIPTION_ITEM" as PIT
database "MEDICINE_STOCK" as MS
database "ALERT" as AL
database "AUDIT_LOG" as AUD

Pharmacist -> UI: Click "+ New Prescription"
activate UI
UI -> UI: Open Add Prescription modal
UI -> UI: Load customer & medicine dropdowns\n(pre-loaded PHP → JS JSON)

Pharmacist -> UI: Select customer
UI -> UI: Show allergy warning (if any)

Pharmacist -> UI: Add medicines + qty + dosage
UI -> UI: Real-time stock & ID check warnings

Pharmacist -> UI: Click "Save Prescription"
UI -> AP: POST {customer_id, stock_id[], item_qty[], dosage[], notes, flags}
activate AP

AP -> AP: Validate all fields
AP -> MS: SELECT stock qty, expiry, requires_id_check\nWHERE stock_id IN (...)
activate MS
MS --> AP: Return medicine details
deactivate MS

AP -> AP: Check expiry dates
AP -> AP: Check stock sufficiency
AP -> AP: Check age-restriction vs id_verified

alt Validation fails
  AP --> UI: {success: false, message: "..."}
  UI -> UI: Show error toast
else Validation passes
  AP -> PT: BEGIN TRANSACTION
  AP -> PT: INSERT INTO PRESCRIPTION
  activate PT
  PT --> AP: prescription_id = X
  deactivate PT

  loop for each medicine
    AP -> PIT: INSERT INTO PRESCRIPTION_ITEM\n(prescription_id, stock_id, qty, dosage)
    AP -> AL: INSERT low_stock ALERT if stock_after < 10
  end

  AP -> PT: COMMIT
  AP -> AUD: INSERT audit log (prescription_created)
  AP --> UI: {success: true, prescription_id: X}
  deactivate AP

  UI -> UI: Show success toast
  UI -> UI: Replace modal footer:\n[Close] [Proceed to Payment →]
  deactivate UI

  Pharmacist -> UI: Click "Proceed to Payment"
  UI -> UI: Navigate to /pages/payment_form.php?rx=X
end

@enduml
```

---

## 3. Sequence Diagram — Process Prescription (Stock Deduction)

**Description:**
This diagram shows the stock deduction flow when a pharmacist changes a prescription's status to "Processed". The `edit_prescription.php` controller first fetches the current status to detect the transition. Stock is only deducted once — on the first transition to "processed" — ensuring idempotency if the record is saved again later. For each medicine line item, the stock quantity is decremented and a low-stock alert is inserted if the new level falls below ten units. The `processed_at` timestamp is set using `COALESCE` so it is never overwritten on subsequent edits.

```plantuml
@startuml Seq_ProcessPrescription
title Sequence Diagram — Process Prescription & Stock Deduction
skinparam sequenceArrowThickness 1.5

actor Pharmacist
participant "Browser\n(prescriptions.php)" as UI
participant "edit_prescription\n.php" as EP
database "PRESCRIPTION" as PT
database "PRESCRIPTION_ITEM" as PIT
database "MEDICINE_STOCK" as MS
database "ALERT" as AL
database "AUDIT_LOG" as AUD

Pharmacist -> UI: Click Edit on prescription
UI -> UI: Open modal, show validation summary
Pharmacist -> UI: Change status to "Processed"
Pharmacist -> UI: Click "Save Changes"

UI -> EP: POST {prescription_id, status=processed, medicines[], ...}
activate EP

EP -> PT: SELECT status WHERE prescription_id = X
activate PT
PT --> EP: oldStatus = "approved"
deactivate PT

EP -> EP: Validate rejection reason (if rejected)\nValidate medicine expiry dates

EP -> PT: BEGIN TRANSACTION

EP -> PT: UPDATE PRESCRIPTION SET\nstatus=processed,\nprocessed_at=COALESCE(processed_at, NOW())
EP -> PIT: DELETE existing items (if new items submitted)
EP -> PIT: INSERT new PRESCRIPTION_ITEM rows

EP -> EP: status='processed' AND oldStatus≠'processed'?

EP -> PIT: SELECT stock_id, qty FROM PRESCRIPTION_ITEM\nWHERE prescription_id = X
activate PIT
PIT --> EP: Return line items
deactivate PIT

loop for each medicine
  EP -> MS: UPDATE MEDICINE_STOCK\nSET quantity = quantity - prescribed_qty
  EP -> MS: SELECT medication_name, quantity
  activate MS
  MS --> EP: new_qty
  deactivate MS
  alt new_qty < 10
    EP -> AL: INSERT low_stock ALERT
  end
end

EP -> PT: COMMIT
EP -> AUD: INSERT audit log\n{old_status, new_status}
EP --> UI: {success: true}
deactivate EP

UI -> UI: Show success toast
UI -> UI: Reload prescriptions page

@enduml
```

---

## 4. System Component Diagram

**Description:**
PharmaTrack follows a three-tier architecture: a Client Layer (web browser), a Web Server Layer (MAMP running Apache with PHP 8), and a Database Layer (MySQL). Within the web server, the frontend consists of custom HTML/CSS and vanilla JavaScript using the Fetch API for asynchronous communication with the backend. The backend is split into Page Controllers (which render full HTML views) and Action Controllers (which handle POST requests and return JSON). All controllers access the database through a centralised PDO singleton (`getDB()`). This design keeps the database connection logic in one place and enables consistent error handling and transaction support across all modules.

```plantuml
@startuml Component_Diagram
title PharmaTrack PMS — System Component Diagram
skinparam componentStyle rectangle

package "Client Layer" {
  [Web Browser] as WB
}

package "Web Server — MAMP / Apache + PHP 8" {

  package "Frontend Assets" {
    [style.css\n(Design System)] as CSS
    [main.js\n(Sidebar Toggle)] as JSM
    [Fetch API\n(Async Form Submit)] as FETCH
  }

  package "Page Controllers (pages/)" {
    [dashboard.php] as DASH
    [customers.php\ncustomer_profile.php] as CUST
    [prescriptions.php] as PRESC
    [stock.php] as STOCK
    [payments.php\npayment_form.php] as PAY
    [invoice.php] as INV
    [alerts.php] as ALRT
    [audit.php] as AUDIT
    [users.php] as USERS
  }

  package "Action Controllers (actions/)" {
    [add/edit_customer] as AC
    [add/edit_prescription] as AP
    [add/edit_stock] as AS
    [add/edit_payment] as APY
    [add/edit_user] as AU
    [acknowledge_alert] as AAL
  }

  package "Config" {
    [db.php\n(PDO Singleton getDB())] as DB
    [session.php\n(requireLogin / currentUser)] as SESS
  }

  package "Includes" {
    [header.php] as HDR
    [sidebar.php\n(Role-based Nav)] as SDB
    [footer.php] as FTR
  }
}

package "Database Layer — MySQL" {
  database "PMS Database" {
    [CUSTOMER]
    [PRESCRIPTION]
    [PRESCRIPTION_ITEM]
    [MEDICINE_STOCK]
    [PAYMENT]
    [SYSTEM_USER]
    [ALERT]
    [AUDIT_LOG]
  }
}

WB --> CSS : loads
WB --> JSM : loads
WB -down-> FETCH : form submits
WB -down-> DASH : HTTP GET
WB -down-> CUST : HTTP GET
WB -down-> PRESC : HTTP GET
WB -down-> STOCK : HTTP GET
WB -down-> PAY : HTTP GET

FETCH --> AC : POST /actions/
FETCH --> AP : POST /actions/
FETCH --> AS : POST /actions/
FETCH --> APY : POST /actions/
FETCH --> AU : POST /actions/
FETCH --> AAL : POST /actions/

DASH --> DB
CUST --> DB
PRESC --> DB
STOCK --> DB
PAY --> DB
AUDIT --> DB

AC --> DB
AP --> DB
AS --> DB
APY --> DB
AU --> DB
AAL --> DB

DB --> [CUSTOMER]
DB --> [PRESCRIPTION]
DB --> [PRESCRIPTION_ITEM]
DB --> [MEDICINE_STOCK]
DB --> [PAYMENT]
DB --> [SYSTEM_USER]
DB --> [ALERT]
DB --> [AUDIT_LOG]

SESS --> DB
SDB --> SESS
HDR --> SESS

@enduml
```

---

## 5. Class Diagram (Data Structure Model)

**Description:**
The class diagram represents the core data entities of PharmaTrack and their relationships. A `CUSTOMER` can have many `PRESCRIPTION` records. Each `PRESCRIPTION` is created by a `SYSTEM_USER` and may have one associated `PAYMENT`. The many-to-many relationship between prescriptions and medicines is resolved through the `PRESCRIPTION_ITEM` junction table, which also stores the prescribed quantity and dosage instructions. `MEDICINE_STOCK` records are the source of truth for availability and pricing. The `ALERT` and `AUDIT_LOG` tables serve as system-wide event records, decoupled from any single entity.

```plantuml
@startuml Class_Diagram
title PharmaTrack PMS — Class / Data Structure Diagram
skinparam classAttributeIconSize 0

class CUSTOMER {
  +customer_id : INT (PK)
  +name : VARCHAR(100)
  +email : VARCHAR(100)
  +date_of_birth : DATE
  +address : TEXT
  +medical_history : TEXT
  +allergies : TEXT
  +account_active : TINYINT
}

class SYSTEM_USER {
  +system_user_id : INT (PK)
  +name : VARCHAR(100)
  +email : VARCHAR(100)
  +password_hash : VARCHAR(255)
  +role : ENUM(admin, pharmacist, store_manager)
  +branch : VARCHAR(100)
  +is_active : TINYINT
  +last_login : DATETIME
}

class PRESCRIPTION {
  +prescription_id : INT (PK)
  +customer_id : INT (FK)
  +system_user_id : INT (FK)
  +status : ENUM(pending, approved, processed, rejected)
  +special_notes : TEXT
  +rejection_reason : TEXT
  +next_refill_date : DATE
  +allergy_checked : TINYINT
  +age_id_verified : TINYINT
  +created_at : DATETIME
  +processed_at : DATETIME
}

class PRESCRIPTION_ITEM {
  +item_id : INT (PK)
  +prescription_id : INT (FK)
  +stock_id : INT (FK)
  +quantity : INT
  +dosage : VARCHAR(150)
}

class MEDICINE_STOCK {
  +stock_id : INT (PK)
  +medication_name : VARCHAR(150)
  +category : VARCHAR(100)
  +batch_number : VARCHAR(100)
  +quantity : INT
  +unit_price : DECIMAL(10,2)
  +expiry_date : DATE
  +supplier : VARCHAR(150)
  +requires_id_check : TINYINT
  +is_active : TINYINT
}

class PAYMENT {
  +payment_id : INT (PK)
  +prescription_id : INT (FK)
  +amount : DECIMAL(10,2)
  +billed_date : DATE
  +payment_method : ENUM(cash, card)
  +payment_status : ENUM(unpaid, awaiting_pickup, paid)
  +notes : TEXT
  +paid_at : DATETIME
  +system_user_id : INT (FK)
}

class ALERT {
  +alert_id : INT (PK)
  +alert_type : VARCHAR(50)
  +message : TEXT
  +is_acknowledged : TINYINT
  +triggered_at : DATETIME
}

class AUDIT_LOG {
  +log_id : INT (PK)
  +system_user_id : INT (FK)
  +action_type : VARCHAR(50)
  +target_table : VARCHAR(50)
  +target_id : INT
  +details : TEXT (JSON)
  +timestamp : DATETIME
}

CUSTOMER "1" --> "0..*" PRESCRIPTION : has
SYSTEM_USER "1" --> "0..*" PRESCRIPTION : creates
PRESCRIPTION "1" --> "1..*" PRESCRIPTION_ITEM : contains
PRESCRIPTION_ITEM "1..*" --> "1" MEDICINE_STOCK : references
PRESCRIPTION "1" --> "0..1" PAYMENT : billed via
SYSTEM_USER "1" --> "0..*" AUDIT_LOG : generates
SYSTEM_USER "1" --> "0..*" PAYMENT : records

@enduml
```
