## 📸 Preview & Screenshots

### 1. Interactive Onboarding & Guided Tour
> **Guided Welcome Tour:** An interactive onboarding popup modal introducing new students to system features, navigation shortcuts, and menu options.
<img width="1900" height="898" alt="image" src="https://github.com/user-attachments/assets/a37890b1-e326-4077-a4b3-a530ba65be46" />

### 2. Student Dashboard Overview
![Student Dashboard](screenshots/dashboard.png)
> **Student Dashboard:** Clean and intuitive interface featuring quick access shortcuts (Menu, Cart, Orders, Feedback), live metric counters, and recent order status tracking.

<img width="1907" height="931" alt="image" src="https://github.com/user-attachments/assets/501c2a7e-001f-48a0-8f07-9ed48b687587" />


<img width="1872" height="833" alt="image" src="https://github.com/user-attachments/assets/2d66efab-4308-4a33-804b-7dba4c08118f" />



<img width="1916" height="910" alt="image" src="https://github.com/user-attachments/assets/c43cb46b-5835-4fe6-b7d1-4300d716588f" />


<img width="1907" height="952" alt="image" src="https://github.com/user-attachments/assets/adc5c825-3636-4198-b2f9-130cfa80dff7" />


<img width="1900" height="915" alt="image" src="https://github.com/user-attachments/assets/be640540-177d-41bb-9b82-7fd8eb733851" />



<img width="1865" height="866" alt="image" src="https://github.com/user-attachments/assets/7e01e122-8705-40db-875b-7c79d8497c8c" />






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



📄 License
This project is licensed under the MIT License
