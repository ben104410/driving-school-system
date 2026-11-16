# Online Driving School Management System
### Case Study: Automobile Association of Kenya (AA Driving School - Mombasa Branch)
**Academic Project:** Diploma in Business Information Technology (DBIT)  
**Institution:** Kenya Methodist University (KeMU), School of Computing and Informatics  
**Student Name:** Cynthia Jeruto  
**Supervisor:** Gabriel Muchangi  

---

## 1. Project Background & System Overview

This project was developed based on the research proposal and system specifications for the **Online Driving School Management System at AA Driving School, Mombasa**.

Traditionally, driving schools in Kenya have relied on paper-based record books and manual scheduling to manage daily operations. As highlighted in Chapter 4 of the research findings:
- **Slow Student Registration:** 35% of operational challenges
- **Lost Student Records:** 20%
- **Payment & Tuition Balance Errors:** 18%
- **Difficult Report Generation:** 15%
- **Lesson Scheduling Conflicts & Double Booking:** 12%

This computerized system centralizes all driving school operations into an integrated, role-based platform that enforces data accuracy, eliminates scheduling conflicts, issues official receipts, tracks student learning progress, and generates instant management reports.

---

## 2. System Architecture & Tech Stack

The system implements the **Three-Tier Architecture** documented in Section 4.6 of the research report:

1. **Presentation Layer (Client):**
   - HTML5, CSS3, Bootstrap 5, FontAwesome 6, Chart.js.
   - Fully responsive, clean card-based UI with print-friendly receipt stylesheets (`@media print`).

2. **Application Layer (Business Logic):**
   - **PHP (Academic Submission Version):** Pure PHP with PDO, secure session management, input sanitization, and conflict detection algorithms. Compatible with XAMPP (Apache + PHP 7.4/8.x).
   - **Node.js (Instant Preview & Demo Version):** Self-contained Node.js web server (`server.js`) with zero external dependency requirements, utilizing JSON persistence (`data.json`) for immediate evaluation on modern machines.

3. **Database Layer:**
   - **MySQL (`database.sql`):** Fully normalized relational schema with primary keys, foreign key constraints (`ON DELETE CASCADE` / `ON DELETE SET NULL`), and seed data for AA Mombasa.

---

## 3. Core Functional Modules

### A. Authentication, Registration & Role-Based Access Control (RBAC)
- **Self-Service Sign Up / Registration:** Allows new users to create accounts directly via the Sign Up portal (`register.php` or `/register`):
  - **Student Sign Up:** Automatically assigns an official Registration Number (`AA/MBA/2026/00X`), captures learner biodata, sets training status to `Enrolled`, and enrolls the student into their chosen driving course category with pre-configured tuition fees.
  - **Instructor Sign Up:** Captures certified NTSA driving instructor license details, license expiry date, specialization categories, and automatically issues an Instructor Code (`AA-INS-00X`).
  - **Receptionist Sign Up:** Front-desk account creation for staff managing inquiries, bookings, and fee collections.
  - **Administrator Sign Up:** Management registration guarded with the AA branch authorization passcode (`AA-ADMIN-2026`).
- **Profile Editing & Password Management:** Every user can view and edit their profile details via the **My Profile** portal (`profile.php` or `/profile`).
  - Update Full Legal Name, Phone Number, and Email Address.
  - Students can update their National ID, Date of Birth, Gender, and Residential Address.
  - Instructors can update their NTSA License Number, Expiry Date, Specialization, and Address.
  - Secure Password Change with current password verification and confirmation checks.
- **Administrative Deletion & Cascading Clean-up:**
  - **Student Deletion:** Removes student record, and automatically cascades to delete all their payment receipts, lesson bookings, driving test results, and unlinks/deletes their portal user account.
  - **Instructor Deletion:** Permanently removes the instructor from the active roster, cancels their upcoming lesson schedules, and removes their linked portal user account.
  - **User Account Deletion:** Allows deleting Receptionists and other Admin accounts from the System Users directory (`users.php` / `/users`), with built-in security preventing self-deletion of the active admin session.
  - **Fleet & Schedule Deletion:** Allows removing training vehicles and deleting/canceling lesson timetable entries.
- **Administrator:** Full administrative privileges, user management, financial oversight, instructor and fleet management, reporting.
- **Receptionist:** Front-desk student registration, tuition fee recording, receipt printing, and lesson scheduling.
- **Instructor:** Access to assigned student rosters, daily driving lesson schedules, lesson status updates, and practical assessment notes.
- **Student:** Portal view for personal bio data, enrolled driving course category, fee balance ledger, timetable of booked lessons, and test assessment scores.

### B. Student Management
- Auto-generated registration numbers: `AA/MBA/2026/00X`.
- Capture of National ID, phone, email, date of birth, residential address, and training status (`Enrolled`, `In Training`, `Test Ready`, `Completed`, `Dropped`).
- Tuition fee configuration by course category:
  - Category A (Motorcycle) - KES 9,500
  - Category B (Light Vehicle - Manual) - KES 15,000
  - Category B (Light Vehicle - Automatic) - KES 16,000
  - Category C1 (Light Truck / Commercial) - KES 18,500
  - Refresher Course (10 Practical Lessons) - KES 8,000
- Comprehensive student profile dossier displaying personal details, fee balance progress bar, driving lesson timetable, and driving test scores.

### C. Certified Instructor Management
- Record of NTSA Driving Instructor License Numbers and expiry dates (compliant with Kenya Traffic Rules 2020).
- Specialization categories (Light manual/automatic, defensive driving, motorcycles).
- Auto-generated instructor codes: `AA-INS-00X`.

### D. Vehicle Fleet Management
- Dual-pedal training cars and motorbikes.
- **Vehicle Photo Upload (Choose File):** Option to choose and upload an image file (JPG, PNG, WebP) of each training vehicle, with live image thumbnail preview during registration and editing.
- Photo gallery thumbnails displayed directly in the fleet management table, with graceful fallback vehicle icons when no picture is uploaded.
- Registration plate, vehicle model, transmission (Manual / Automatic), and operational status (`Available`, `In Use`, `Maintenance`).

### E. Practical Lesson Scheduling with Conflict Detection
- Explicitly addresses the **12% scheduling conflict** problem identified in the manual system.
- Before confirming any booking, the system verifies:
  1. Instructor is not already booked for another lesson at that same date and overlapping time window.
  2. Training vehicle is not assigned to another ongoing lesson.
  3. Student does not have another booking at that time.
- Status tracking: `Scheduled`, `In Progress`, `Completed`, `Cancelled`, `No Show`.
- Instructor feedback notes recording student vehicle handling, clutch control, mirror checks, and lane discipline.

### F. Tuition Payments & Official Receipt Generation
- Supports payment methods common in Kenya: **M-Pesa**, **Cash**, and **Bank Transfer**.
- **Lipa na M-Pesa STK Push Integration:** Pre-configured with default prompt phone number **`0715718022`** for triggering automated payment prompts directly to the payee handset.
- **One-Click STK Push Prompt:** Automated simulation sends the prompt to **`0715718022`**, confirms receipt, and populates the official Safaricom transaction reference code.
- Auto-generates unique receipt numbers: `RCT-2026-000X`.
- Captures M-Pesa transaction reference codes (e.g. `QA189KL93Z`) or bank slip numbers.
- **Official Printable Receipt:** Formatted with official AA Kenya Mombasa Branch branding, P.O. Box, telephone, NTSA accreditation reference, student biodata, itemized payment, prompt phone `0715718022` on M-Pesa transactions, cumulative paid amount, remaining balance, and cashier stamp box.

### G. Driving Test Assessments
- Internal Theory Test (Road signs, Highway Code, Model Town Board).
- Internal Practical Mock Driving Test (Pre-test evaluation by senior instructors).
- Final NTSA / Police Driving Test.
- Scores out of 100, pass/fail status, examiner name, and detailed assessment remarks.

### H. Management Reports & Decision Support
- **Revenue & Collections Report:** Total tuition collected, transaction counts, and payment method breakdown (M-Pesa vs Cash vs Bank).
- **Student Enrollment Report:** Learner lists filtered by date range and course categories.
- **Driving Lessons Activity Report:** Total lessons conducted, instructor workloads, and attendance.
- **Driving Test Pass-Rate Report:** Total tests sat, successful passes, and pass-rate percentage.
- All reports feature an official branch header and print-ready stylesheet.

---

## 4. Default Demo Accounts

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `password123` | Full access to all modules, reports, and users |
| **Receptionist** | `receptionist` | `password123` | Front-desk registrations, fees, receipts, schedules |
| **Instructor** | `instructor1` | `password123` | David Ochieng (Senior NTSA Instructor) |
| **Instructor** | `instructor2` | `password123` | Hassan Mwinyi (Motorcycle & Class B) |
| **Student** | `student1` | `password123` | Mary Wanjiku (Enrolled in Class B Manual) |

---

## 5. How to Run the Node.js Version (Instant Demo / Examination)

1. Open the project root folder:
   ```
   C:\Users\Mohaa\Downloads\assets\driving-school-system
   ```
2. Double-click `start.bat` (or open terminal in `node/` and run `npm start`).
3. Your browser will open automatically at:
   ```
   http://localhost:3000
   ```
4. Click any of the quick demo buttons on the login screen to sign in as Administrator, Receptionist, Instructor, or Student!

---

## 6. How to Deploy the PHP & MySQL Version on XAMPP (University Submission)

For academic submission, viva voce presentation, or running on an Apache/XAMPP server:

1. Install **XAMPP** from [apachefriends.org](https://www.apachefriends.org/).
2. Copy the contents of the `php/` folder into your XAMPP web root directory:
   ```
   C:\xampp\htdocs\driving-school-system
   ```
3. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
4. Open your web browser and go to:
   ```
   http://localhost/phpmyadmin
   ```
5. Click **New**, create a database named `driving_school_db`, then click the **Import** tab.
6. Select `database.sql` from `php/database.sql` and click **Go** / **Import**.
7. In your browser, navigate to:
   ```
   http://localhost/driving-school-system
   ```
8. Log in with `admin` / `password123`.
