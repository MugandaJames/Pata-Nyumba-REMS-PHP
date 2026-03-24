# 🏠 Pata Nyumba - Real Estate Management System

A modern, secure real estate platform built with **PHP (PDO)**, **JavaScript (ES6)**, and **MySQL**. Designed for seamless interaction between Customers, Agents, and Administrators.

## 🚀 Features
- **Dynamic Search:** Instant filtering by name, location, and property type (Rent/Sale).
- **Role-Based Dashboards:** Separate interfaces for Admin, Agent, and Customer.
- **Automated Agreements:** Generates secure PDF contracts using **TCPDF** upon request approval.
- **Security First:** Uses **UUIDs** for file naming and database records to prevent ID enumeration.
- **Audit Logging:** Tracks all administrative actions for transparency.

## 🛠️ Tech Stack
- **Backend:** PHP 8.x (XAMPP/Linux)
- **Database:** MySQL (PDO for SQL Injection protection)
- **Frontend:** Vanilla JS, CSS3, HTML5
- **Libraries:** TCPDF for PDF generation

## ⚙️ Installation (Local/XAMPP)
1. Clone the repository to your `htdocs` folder.
2. Import the `database.sql` file into phpMyAdmin.
3. Copy `backend/config.example.php` to `backend/config.php` and update your DB credentials.
4. Ensure the `uploads/` and `agreements/` folders have write permissions:
   ```bash
   sudo chmod -R 777 uploads agreements
