# BOQ-CAD Tanzania

**Professional Web-Based Architectural Plan Drawing & Construction Cost Estimator**

Built with pure PHP, MySQL, HTML, CSS, JavaScript, AJAX, Fabric.js, Tailwind CSS & Bootstrap 5.

No Laravel · No SQLite · Ready for any standard PHP + MySQL hosting.

---

## Features

- **User Registration & Login** (any user can create an account)
- **2D Drawing Board** powered by Fabric.js  
  - Draw rooms as rectangles  
  - Draw walls as lines  
  - Auto-calculate dimensions from scale  
  - Save canvas as JSON
- **Manual Room Entry** (Length × Width × Height)
- **Quantity Takeoff (BOQ)**  
  - Blocks, cement, sand, mabati, timber, tiles, paint, doors, windows, electrical, plumbing, labour  
  - Based on standard Tanzania building ratios (e.g. 12.5 blocks/m² wall)
- **Cost Estimation** using real Tanzania market prices (Oct 2026)  
  - Materials + Labour + 10% Transport + 10% Contingency  
  - 5% waste already applied to quantities
- **Quick Estimate** mode (just enter overall L × W)
- **Bill of Quantities Report** with print & PDF-ready view
- **Excel / CSV Export**
- **Material Price List** (live from database)

---

## Requirements

- PHP 8.0+ (with PDO MySQL)
- MySQL 5.7+ / 8.0+ / MariaDB
- Apache / Nginx (or PHP built-in server for testing)
- Modern browser (Chrome, Firefox, Edge)

---

## Installation

1. **Upload** the entire `boq-cad` folder to your web server (or clone into htdocs / www).

2. **Create database** and import schema:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
   Or open phpMyAdmin → Import → select `sql/schema.sql`.

3. **Configure database** in `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'boq_cad');
   define('DB_USER', 'your_user');
   define('DB_PASS', 'your_password');
   ```

4. **Permissions** (optional for uploads):
   ```bash
   chmod 755 uploads
   ```

5. **Open in browser**:
   ```
   http://localhost/boq-cad/
   ```
   or your domain.

6. **Register** a new account and start creating projects.

---

## Project Structure

```
boq-cad/
├── api/                  # AJAX endpoints
│   ├── add-room.php
│   ├── calculate.php
│   ├── delete-project.php
│   ├── delete-room.php
│   ├── export-excel.php
│   ├── export-pdf.php
│   └── save-project.php
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── includes/
│   ├── auth.php
│   ├── calculator.php    # Quantity & cost engine
│   ├── config.php
│   ├── footer.php
│   └── header.php
├── sql/schema.sql        # Full DB + seed prices
├── uploads/
├── dashboard.php
├── index.php
├── login.php
├── logout.php
├── materials.php
├── project.php           # Drawing board + rooms
├── project-new.php
├── quick-estimate.php
├── register.php
├── boq.php               # Full BOQ report
└── README.md
```

---

## How the Calculator Works

1. User draws rooms or enters L × W × H.
2. System computes floor area and wall area.
3. Applies formula rates (e.g. 12.5 blocks per m² of wall).
4. Multiplies by average Tanzania unit prices.
5. Adds 5% waste, 10% transport, 10% contingency.
6. Stores detailed BOQ items + summary in MySQL.

---

## Tech Stack

| Layer        | Technology                          |
|--------------|-------------------------------------|
| Backend      | PHP 8 (PDO)                         |
| Database     | MySQL                               |
| Frontend     | HTML5, CSS3, Tailwind CDN, Bootstrap 5 |
| Drawing      | Fabric.js 5                         |
| AJAX         | Fetch API + jQuery                  |
| Icons        | Font Awesome 6                      |
| Export       | CSV (Excel) + Print-to-PDF          |

---

## Notes

- Prices are seeded for October 2026 Tanzania market. Update the `materials` table as needed.
- For production, set `display_errors = 0` in `config.php` and enable HTTPS.
- Real DXF export / 3D view can be added later as Phase 2/3.

---

**Built for Tanzanian builders, architects and homeowners.**
