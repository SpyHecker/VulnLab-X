# Vulnerable E-Commerce Web Application (Cybersecurity Training Lab)

## Overview

This project is an intentionally vulnerable e-commerce web application built using PHP and MySQL. It is designed as a hands-on learning platform for students and cybersecurity enthusiasts to understand, exploit, and fix common web application vulnerabilities.

Unlike traditional demo apps, this platform simulates a real-world e-commerce environment with authentication, product management, and transaction flows, making it ideal for practical security training.

---

## Objective

The primary goal of this project is to:

* Teach how real-world web vulnerabilities occur
* Provide a safe environment to practice exploitation techniques
* Demonstrate secure coding practices by comparing vulnerable and fixed implementations
* Help learners think like both attackers and defenders

---

## Key Features

* User registration and login system
* Product browsing and cart functionality
* Admin panel for product management
* REST API endpoints (for advanced testing)
* Multiple vulnerability modules
* Difficulty levels (Low / Medium / High)
* Attack logging and monitoring dashboard (planned)

---

## Implemented Vulnerabilities

This application intentionally includes the following vulnerabilities:

### 1. SQL Injection (SQLi)

* Login and search functionality
* Demonstrates authentication bypass and data extraction

### 2. Cross-Site Scripting (XSS)

* Reflected and stored XSS in input fields
* Demonstrates client-side code injection

### 3. Cross-Site Request Forgery (CSRF)

* Sensitive actions like password change
* Demonstrates unauthorized action execution

### 4. Insecure Direct Object Reference (IDOR)

* Accessing other users’ data by modifying IDs
* Demonstrates broken access control

### 5. File Upload Vulnerability

* Uploading malicious files (e.g., PHP shells)
* Demonstrates remote code execution risks

### 6. Command Injection

* Executing system commands via input fields

### 7. Authentication & Session Flaws

* Weak session handling
* Predictable tokens

### 8. Business Logic Flaws

* Price manipulation
* Coupon abuse
* Unauthorized order access

### 9. API & JWT Vulnerabilities (Planned)

* Token tampering
* Broken authentication

---

## Technology Stack

* Backend: PHP (Core PHP)
* Database: MySQL
* Web Server: Apache
* Containerization: Docker
* Optional Tools: phpMyAdmin

---

## Project Structure

```
vuln-ecommerce/
│
├── app/
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── config/
│   │   └── db.php
│   ├── modules/
│   │   ├── sqli.php
│   │   ├── xss.php
│   │   ├── csrf.php
│   │   ├── idor.php
│   │   └── upload.php
│   └── assets/
│
├── docker-compose.yml
└── README.md
```

---

## How It Works

Each module is designed to:

1. Introduce a vulnerability
2. Allow the user to exploit it
3. Provide hints and explanations
4. Show a secure implementation

---

## Learning Outcomes

By working with this project, users will:

* Understand how vulnerabilities are introduced in code
* Learn how attackers exploit insecure systems
* Gain hands-on experience with penetration testing
* Learn how to secure applications using best practices

---

## Safety Notice

⚠️ This application is intentionally vulnerable.

* Run only in a local environment (Docker recommended)
* Do not deploy on public servers
* Use strictly for educational purposes

---

## Future Enhancements

* AI-based vulnerability explanation system
* Real-time attack monitoring dashboard
* Integration with security tools (Burp Suite, etc.)
* Gamified learning (levels, scoring system)
* Multi-user lab environment

---

## Target Audience

* Cybersecurity students
* Ethical hacking beginners
* Developers learning secure coding
* CTF players

---

## Contribution

Contributions are welcome:

* Add new vulnerabilities
* Improve UI/UX
* Enhance security explanations

---

## License

This project is for educational use only.

---

## Author

Developed as part of a cybersecurity learning initiative to bridge the gap between theory and real-world application security.