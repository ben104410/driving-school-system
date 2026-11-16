# AA Driving School Management System (Mombasa Branch)
**Research Project by Cynthia Jeruto &bull; Kenya Methodist University (KeMU)**

An integrated online driving school management system developed to automate student registration, lesson scheduling, instructor and fleet management, fee collections (M-Pesa/Cash/Bank), printable receipt generation, driving test assessments, and management reporting.

---

## Folder Structure

```
driving-school-system/
├── php/                         <-- Pure PHP & MySQL Application (for XAMPP & KeMU submission)
│   ├── config/                  <-- Database configuration (db.php)
│   ├── includes/                <-- Header, navbar, sidebar, footer, helper functions
│   ├── assets/                  <-- Custom CSS (style.css) & styling
│   ├── database.sql             <-- Complete MySQL database schema & seed data
│   ├── index.php                <-- Landing & routing
│   ├── login.php / logout.php   <-- Secure authentication & role switching
│   ├── dashboard.php            <-- Role-aware dynamic dashboard with KPI & charts
│   ├── students.php             <-- Student directory, search & status filters
│   ├── student_add.php          <-- Student registration form with auto reg no
│   ├── student_view.php         <-- Student dossier (bio, fee ledger, lessons, tests)
│   ├── student_edit.php         <-- Edit student records
│   ├── student_delete.php       <-- Remove student record (Admin only)
│   ├── instructors.php          <-- Instructor list with NTSA license numbers
│   ├── instructor_add.php       <-- Add instructor & license details
│   ├── instructor_edit.php      <-- Edit instructor details
│   ├── vehicles.php             <-- Fleet management (Dual-pedal cars & motorbikes)
│   ├── vehicle_add.php          <-- Add new vehicle
│   ├── vehicle_edit.php         <-- Edit vehicle
│   ├── schedules.php            <-- Practical driving lesson timetable
│   ├── schedule_add.php         <-- Book lesson with Double-Booking Conflict Detection!
│   ├── schedule_status.php      <-- Mark lesson completed with instructor notes
│   ├── payments.php             <-- Fee payment transaction directory
│   ├── payment_add.php          <-- Record tuition fee installment (M-Pesa/Cash/Bank)
│   ├── receipt.php              <-- Official printable receipt with AA Mombasa branding
│   ├── test_results.php         <-- Driving test assessments list
│   ├── test_result_add.php      <-- Record internal theory, mock, or NTSA test
│   ├── reports.php              <-- Revenue, student, lesson & test pass-rate reports
│   ├── users.php                <-- System user management
│   ├── profile.php              <-- My profile & password update
│   └── student_profile.php      <-- Student portal profile
│
├── node/                        <-- Ready-to-Run Node.js Version (Runs immediately on your PC)
│   ├── server.js                <-- Full HTTP server & business logic (Zero npm installs needed)
│   ├── data.json                <-- Persistent JSON database matching database.sql
│   └── package.json             <-- npm start script
│
├── docs/                        <-- Comprehensive documentation
│   └── PROJECT_MANUAL.md        <-- Detailed system manual & Chapter 4 thesis mapping
│
└── start.bat                    <-- Double-click to instantly launch the system in your browser!
```

---

## Quick Start (Run in 5 Seconds)

1. Double-click `start.bat` in this folder.
2. The web system will open in your browser at `http://localhost:3000`.
3. Sign in using any of the quick demo accounts:
   - **Administrator:** `admin` / `password123`
   - **Receptionist:** `receptionist` / `password123`
   - **Instructor:** `instructor1` / `password123`
   - **Student:** `student1` / `password123`

---

## Deploying on XAMPP (For College Submission & Defense)

1. Copy the contents of the `php/` folder into `C:\xampp\htdocs\driving-school-system`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Import `database.sql` into phpMyAdmin (`http://localhost/phpmyadmin`).
4. Access the system at `http://localhost/driving-school-system`.
