# PMS

Prescription Management System

This repository currently contains a simple HTML Hello World example:

- `Test.html` displays "Hello World" in the browser.

Future project files can be added here as the PMS application grows.


Documents/PMS/
This is the root of your entire project. MAMP serves everything from here. When someone visits http://localhost:8888, PHP starts reading from this folder.

index.php
The entry point. It doesn't show anything itself — it just checks if you're logged in and redirects you to either the dashboard or the login page. Think of it as the front door.

setup.php
A one-time utility script. You visit it once to generate a real bcrypt password for the test user in the DB. You should delete this after using it — it has no security protection on it.

config/
Holds application-wide settings. Any file that configures how the app behaves lives here.

File	What it does
db.php	Stores your database credentials (host, port, name, user, password) and provides the getDB() function that every page uses to talk to MySQL
session.php	Manages login sessions. Provides requireLogin() (redirects you away if not logged in), requireRole() (blocks pages based on your role), and currentUser() (returns the logged-in user's info)
auth/
Handles logging in and out. Completely separate from the rest so it never needs a logged-in session to work.

File	What it does
login.php	Shows the login form, checks your email/password against the DB, starts your session, and redirects to the dashboard
logout.php	Destroys your session and sends you back to the login page
includes/
Holds reusable UI pieces that are assembled into every page. Every page calls these three files so you never repeat the same HTML.

File	What it does
header.php	Outputs the <html>, <head>, stylesheet link, and the top navigation bar with the page title. Also calls sidebar.php
sidebar.php	Builds the left-hand sidebar. Reads the logged-in user's role and shows only the nav links that role is allowed to see
footer.php	Closes the layout HTML (</main>, </body>, </html>) and loads the JavaScript file
pages/
The actual screens users see. Each file is one full page of the application.

File	What it does
dashboard.php	The home screen after login. Shows 4 stat cards (pending prescriptions, low stock, alerts, active customers) and tables of recent activity
customers.php	Lists all customers in a table, with the Add New Customer button (top right) and a pencil edit button on each row
More pages (prescriptions, stock, payments, etc.) will be added here as you build each module.

actions/
Handles form submissions behind the scenes. These files are never visited directly — they only receive data sent from a form, do something with the DB, and send back a JSON response ({ success: true, message: "..." }).

File	What it does
add_customer.php	Receives the Add Customer form data, validates it, inserts a new row into the CUSTOMER table, and logs the action to AUDIT_LOG
edit_customer.php	Receives the Edit Customer form data, validates it, updates the existing CUSTOMER row, and logs the action to AUDIT_LOG
Every new feature (prescriptions, stock, etc.) will add its own action files here.

assets/
All static files — nothing here is PHP, it's all sent directly to the browser.

File	What it does
css/style.css	The entire visual design of the app — colours, layout, sidebar, cards, tables, modals, badges, buttons, toggle switch, toasts, and responsive rules
js/main.js	Handles the sidebar open/collapse toggle when you click the hamburger button
