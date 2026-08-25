# East West University Cafeteria Management System

A web-based cafeteria management system built with PHP, MySQL, CSS, and HTML for smooth order processing, dynamic menu administration, and sales tracking on university campuses.

 **Official Documentation:**

---

## Tech Stack & Database Architecture
* **Frontend:** HTML5, CSS3, JavaScript (Custom Responsive UI & Layout)
* **Backend:** PHP (Role-based Authentication, Order Processing & Logic)
* **Database:** MySQL (Relational Schema Optimization & Refactoring)

---

## Key System Features
* Dynamic Menu & Item Management for Admins
* Interactive Student & Staff Ordering Interface
* Role-based Access Control (Admin, Student, Faculty, Staff)
* Fully Refactored Relational Database with SQL Optimization



## 📸 Preview & Screenshots
*(Add project screenshots in a `/screenshots` folder and link them here)*

---

## How to Run Locally
1. Clone this repository to your local machine:
   ```bash
   git clone [https://github.com/shakibdev2-source/East-West-University-cafeteria-management-system.git](https://github.com/shakibdev2-source/East-West-University-cafeteria-management-system.git)
Move the project folder to your local server directory (e.g., htdocs for XAMPP).

Start Apache and MySQL modules on XAMPP Control Panel.

Open phpMyAdmin (http://localhost/phpmyadmin/) and create a new database.

Import the SQL database script located inside the mysql_database/ directory.

Open http://localhost/campusbite_db/login.php in your browser.

**Team Roles & Individual Contributions**


**Md. Shakib Hossan** —Lead Full-Stack Developer & Technical Architect
Frontend & UI Design:

Built responsive multi-role dynamic interfaces using HTML5, CSS3 (Custom Styling & Glassmorphism Design), and JavaScript (DOM Manipulation).

Integrated Font Awesome Icons (fa-solid fa-user, fa-lock, fa-eye, etc.) for intuitive UX and password-toggle features.

Backend & Architecture:

Developed multi-user role authentication and dashboards for Student, Faculty, Staff, and Admin portals.

Implemented core business logic for Cart Management, Checkout Systems, Order Tracking, Feedback Modules, and Authentication (login.php, register.php, logout.php).

Security Implementation:

Integrated CSRF Token Validation (csrf_token) in auth forms to prevent Cross-Site Request Forgery attacks.

Developed custom Dynamic CAPTCHA Verification Systems (SESSION['captcha_student']) and Interactive Refresh logic to protect login forms from bot attacks.

Secured sensitive dynamic data rendering using htmlspecialchars().

Database Architecture & Refactoring:

Designed, optimized, and refactored the relational MySQL database schema (database/), ERD, tables, and relational SQL queries.

Handled full session-state management and dynamic SQL integration across all portal modules.

Team Acknowledgments

**Ristia inam elme**
Project Documentation Specialist (PDF Report Formatting & Database Schema/ERD Visual Design).

**Md. Arifur Rahman Razu**:( https://github.com/mdarifurrahmanrazu/university-cafeteria-management-system  ) Initial Database Draft Support.

📄 License
This project is licensed under the MIT License
