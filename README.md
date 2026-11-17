# ABEKa Hiring Flow & Brevo Mailer Update

Drop these files into your `ABEKa` XAMPP project (`D:\Webdev\XAMPP\htdocs\ABEKa\`) and adapt the table/column names if yours differ.

## 1. Run the SQL Migration
Import `sql/migration_hiring_flow.sql` in `http://localhost/phpmyadmin` against your ABEKa database.

## 2. Add Your Brevo Credentials
Open `config/brevo_mailer.php` and paste your Brevo **API Key** (or SMTP Key) from `https://app.brevo.com/` -> **Settings -> SMTP & API**, plus your verified sender email (`abekaphofficial@gmail.com`).

## 3. Connect the Endpoints
- **Employer Applicants Page:** Include `employer/applicants_status_widget.php` inside each applicant card to render the 6-status dropdown (`For Review`, `For Interview`, `Interview Passed`, `Not Selected`, `Offer Accepted`, `Hired`), interview calendar + Jitsi video link, and Job Offer upload.
- **Status Update Handler:** Point the form `action` to `employer/update_application_status.php`.
- **Applicant Offer Acceptance:** On the applicant dashboard, link the **Accept Offer** button to `applicant/accept_offer.php`.
