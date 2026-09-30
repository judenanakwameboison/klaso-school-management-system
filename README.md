# Klaso: School Management System

A multi-school management platform built with PHP and MySQL. Schools join the platform and manage their students, teachers, examinations, fees and results from one dashboard.

## Why Klaso
Schools often keep student records, exam results and fees across paper files and spreadsheets. Klaso brings all of it into one secure system, with a separate portal for every role in the school.

## Features
- **Multi-school platform:** schools apply to join, and the super admin reviews each application
- **Five role-based portals:** super admin, school admin, teacher, student and parent
- **Student records:** register, view and manage student information, plus an alumni list
- **Classes, subjects and teachers:** organise the academic structure of each school
- **Attendance, examinations and results:** mark attendance, record exams and publish results
- **Fee management:** record payments, see balances, payment history and outstanding fees
- **Library:** issue and return books
- **Secure login:** separate authentication for each role, session checks on protected pages, and mandatory two-factor authentication (TOTP) for admin accounts

## Tech stack
| Area | Tools |
|---|---|
| Back end | PHP |
| Database | MySQL |
| Front end | HTML, CSS |
| Local server | XAMPP (Apache + MySQL) |

## Security
- Each role has its own authentication file in `config/`
- Protected pages check the session before loading
- Two-factor authentication for admin accounts using time-based one-time codes (`config/totp.php`)
- Database credentials are kept out of the repository through `.gitignore`, and `config/db.example.php` shows the expected setup

## Project structure
```
klaso_system/
├── admin/        School admin portal
├── assets/       Styles, images and scripts
├── auth/         Login and registration pages
├── config/       Database and role authentication
└── superadmin/   Platform owner portal (schools, applications)
```

## Getting started
1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Clone this repo into `xampp/htdocs/`:
   ```bash
   git clone https://github.com/judenanakwameboison/klaso-school-management-system.git
   ```
3. In phpMyAdmin, create a database and import `database.sql`.
4. Copy `config/db.example.php` to `config/db.php` and add your own database details.
5. Open `http://localhost/klaso-school-management-system` in your browser.

## Screenshots
### Login
![Klaso login page](docs/screenshots/login.jpg)

### Fee management
![Klaso fee management](docs/screenshots/fees.jpg)

## Author
**Jude Nana Kwame Boison**, web developer in Accra, Ghana.
[GitHub](https://github.com/judenanakwameboison) · [LinkedIn](https://www.linkedin.com/in/jude-boison-817223375)
