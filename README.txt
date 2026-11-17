1. Copy folder to C:\xampp\htdocs\abeka ; import sql/abeka.sql in phpMyAdmin.
2. Put your logos in assets/: logo.png (header + favicon) and bg-logo.png (large background). Paths are relative, so they now work on every page.
3. Optional photos: assets/lgu/<slug>.jpg (e.g. angeles-city.jpg). 
4. Load news: php tools/fetch_news.php
5. Create an admin: INSERT INTO users(name,email,pass,role) VALUES('Admin','a@x.com','<hash>','admin'); (hash via password_hash in PHP)
6. Mail: configure sendmail in php.ini/sendmail.ini; every application also writes an in-app confirmation regardless.

Fuel price dashboard:
- The home page now reads North Luzon brand prices from FuelPrice.ph and displays only Caltex, Shell, and Petron.
- The source page is checked at most once every 24 hours when the home page is visited; the last successful values are cached in cache/fuel_north_luzon.json.
- FuelPrice.ph publishes regional reference prices, not exact prices for every Pampanga station. The source's effective date is displayed, and prices may vary by station.
- Fuel adjustments in the Philippines usually take effect weekly (commonly Tuesday morning), so a daily refresh does not mean the displayed prices will change every day.
- The LPG figure is the North Luzon 11 kg tank average because the source does not provide an LPG price per Caltex/Shell/Petron brand in its brand comparison table.

Additional profile contact information:
- Applicants can add up to five optional mobile numbers and five personal email addresses in My Profile.
- Extra contacts are stored in the employee_contacts table, created automatically by the website.
- Employers can view these extra contacts in Applicants > View applicant details and resume.
- The account email remains unchanged; extra personal emails are additional contact methods.
- Optional manual SQL: sql/update_profile_contacts.sql


ABEKa Assistant chatbot:
- A floating “Ask ABEKa” button appears on pages that use the shared includes/footer.php.
- The assistant is a PHP/JavaScript rule-based helper that reads public LGU, news, announcement, job, fuel-price, and cached forex information from the existing database/cache.
- No AI provider account or API key is required. Responses are limited to the public information and guidance implemented in chatbot.php; it is not a generative AI chatbot.
- New files: chatbot.php and assets/js/chatbot.js. The chatbot styles are in the existing assets/css/glass.css file, and the widget is included through includes/footer.php.
- The chat endpoint uses a per-session request token and a basic per-session rate limit. It does not expose applicant records, passwords, or private account information.


Emergency Alert Center:
- Public emergency directory: alerts.php (the ALERT link appears beside Home).
- Admin hotline management: alert_admin.php (admin role only).
- Import sql/update_emergency_alerts.sql into the existing abeka database before using local hotline management.
- Add one hotline number per record and verify it with the relevant LGU/PNP/BFP/DRRMO office before marking it verified.
- The public page includes a tap-to-call 911 link and a Google Maps search/embed for the selected Pampanga municipality. Google Maps requires an internet connection.
- Local hotline numbers are not preloaded because the supplied poster should be checked against current official contacts before publishing.
- Rubik is loaded from Google Fonts in includes/header.php; an internet connection is needed unless a local Rubik font file is added to assets/fonts.
