# CovidCare Info Hub

## Preview (no server needed)
Open `index.html` directly. It runs with sample data stored in the browser (`DATA_MODE = 'local'` near the top of its script).

## Run with XAMPP (PHP + MySQL)
1. Copy this whole folder into `C:\xampp\htdocs\covid-info-hub` (or `/Applications/XAMPP/htdocs/covid-info-hub` on macOS).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`, go to the **SQL** tab, paste the contents of `database/schema.sql`, and click **Go**.
4. If your MySQL username or password differ from the XAMPP defaults (`root` with no password), edit `api/config.php`.
5. Open `index.html` and change `DATA_MODE` to `'api'` in the script near the top.
6. Visit `http://localhost/covid-info-hub/`.

Accounts, comments, and contact messages are then stored in the `covid_info_hub` database and shared by all visitors.
