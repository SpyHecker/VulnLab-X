# VulnMart 🛒💥

**VulnMart** is an intentionally vulnerable e-commerce web application built for security professionals, developers, and ethical hackers to practice their skills in a realistic environment. 

## 🎯 The Idea

The goal of VulnMart is to simulate a real-world, modern web application that contains a variety of common (and some complex) security flaws. Unlike simple "hack me" challenges, VulnMart aims to provide a realistic shopping experience with products, user accounts, shopping carts, checkout processes, and administrative dashboards—all ridden with carefully crafted vulnerabilities.

### Key Features
- **User Roles:** Customers, Sellers, and Administrators.
- **Shopping Experience:** Product catalog, search functionality, reviews, and a shopping cart.
- **Checkout Process:** Order placement, mock payment gateway integration, and order history.
- **Admin Dashboard:** Product management, user management, and system logs.

## 🐛 Planned Vulnerabilities

VulnMart will incorporate vulnerabilities from the OWASP Top 10 and beyond, including:

1. **SQL Injection (SQLi):** In product search and login mechanisms.
2. **Cross-Site Scripting (XSS):** Stored XSS in product reviews and reflected XSS in search results.
3. **Insecure Direct Object Reference (IDOR):** Accessing other users' orders and profiles.
4. **Broken Authentication:** Weak password policies, session fixation, and lack of rate limiting.
5. **Cross-Site Request Forgery (CSRF):** Updating user details or changing passwords without proper tokens.
6. **Server-Side Request Forgery (SSRF):** In a feature that fetches product images from external URLs.
7. **XML External Entity (XXE):** In the bulk product upload functionality for sellers.
8. **Insecure Deserialization:** In session management or API payloads.
9. **Business Logic Flaws:** Manipulating cart prices, applying negative discount codes, or race conditions during checkout.
10. **Misconfigurations:** Exposed administrative interfaces, default credentials, and verbose error messages.

## 🛠️ Proposed Tech Stack

- **Frontend:** HTML5, CSS3, Vanilla JavaScript.
- **Backend:** PHP.
- **Database:** SQLite3.
- **Infrastructure:** Docker and Docker Compose (php:apache).

## 🚀 Getting Started
*(Instructions will be added once the initial codebase is implemented)*

---
*Disclaimer: VulnMart is for educational purposes only. Do not deploy this application on a public-facing server or use these techniques on systems you do not have permission to test.*
