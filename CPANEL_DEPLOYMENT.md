# Church TV – Deploy to cPanel (Step-by-Step)

This guide walks you through publishing the LCMTV Web (Church TV) site on a **cPanel** hosting account so that:
- Your main site loads at `https://yourdomain.com/`
- The API works at `https://yourdomain.com/backend/api/`

---

## What you need before starting

- cPanel login (hosting that supports **PHP 7.4+** and **MySQL**)
- Your **domain** (e.g. `yourdomain.com`) pointed to this hosting (or a subdomain)
- **FTP/SFTP** credentials or cPanel **File Manager** access
- (Optional) **YouTube Data API key** for video features

---

## Step 1: Create the MySQL database in cPanel

1. Log in to **cPanel**.
2. Open **MySQL® Databases** (under “Databases”).
3. **Create a database**
   - In “Create New Database”, enter a name (e.g. `lcmtv_db` or `youruser_churchtv`).
   - Click **Create Database**.
   - Note the full name: cPanel often prefixes it (e.g. `youruser_lcmtv_db`).
4. **Create a database user**
   - In “MySQL Users”, set username and a strong password.
   - Click **Create User**.
   - Note the full username (e.g. `youruser_dbuser`).
5. **Add user to database**
   - In “Add User To Database”, choose the user and the database.
   - Click **Add**.
   - On the next screen, check **ALL PRIVILEGES**, then **Make Changes**.
6. **Note down** (you’ll use these in Step 4):
   - Database name (e.g. `youruser_lcmtv_db`)
   - Username (e.g. `youruser_dbuser`)
   - Password
   - Host is usually **localhost** (see “Remote MySQL” or host’s docs if different).

---

## Step 2: Prepare the database (tables and schema)

1. In cPanel, open **phpMyAdmin** (under “Databases”).
2. Select the database you created (e.g. `youruser_lcmtv_db`).
3. Go to **Import**.
4. You need the schema file from your project:
   - On your computer it’s: `backend/config/schema.sql`.
   - In phpMyAdmin: **Choose File** → select `schema.sql` → **Go**.
5. If your host has a different way to run SQL (e.g. “Run SQL”), you can paste the contents of `schema.sql` there instead.
6. After a successful import, you should see tables like `users`, `videos`, `categories`, etc.

**If you don’t have a single schema file:** run any setup/migration scripts your project provides (e.g. `backend/setup.php`, `create_*_tables.php`) from the server (see Step 6) or create tables manually from the project’s SQL files.

---

## Step 3: Upload the project files

Your goal is to have the **frontend** as the site root and the **backend** in a `backend` folder under the same root.

### Option A: Using cPanel File Manager

1. In cPanel, open **File Manager**.
2. Go to the **document root** of your domain:
   - Often `public_html` for the main domain, or a subdomain folder (e.g. `public_html/subdomain`) if you use a subdomain.
3. **Upload the frontend** (so the site runs from the root):
   - Upload everything **inside** your local `frontend` folder into this root (e.g. `public_html`):
     - `index.html`
     - `app/` (entire folder)
     - `assets/` (entire folder)
     - Other files in `frontend/` (e.g. `manifest.json`, `browserconfig.xml`, etc.)
   - Do **not** put them in a `frontend` subfolder unless you want the site at `https://yourdomain.com/frontend/` (then you’d need to adjust paths below).
4. **Upload the backend**:
   - Create a folder named **`backend`** in the same root (e.g. `public_html/backend`).
   - Upload the **entire** contents of your local `backend` folder into `public_html/backend/`:
     - `api/`, `config/`, `controllers/`, `models/`, `utils/`, `admin/`, etc.
     - Include `.htaccess` and any `.php` setup scripts.

Final structure should look like:

```text
public_html/
├── index.html          (from frontend)
├── app/                (from frontend)
├── assets/             (from frontend)
├── manifest.json        (from frontend)
├── backend/
│   ├── .htaccess
│   ├── .env            (you create in Step 4)
│   ├── api/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── utils/
│   └── ...
```

### Option B: Using FTP/SFTP

1. Connect with FileZilla (or another FTP client) to your host using the cPanel FTP credentials.
2. Navigate to `public_html` (or your domain’s root).
3. Upload the same way: **frontend** contents at root, **backend** folder with all its contents under `backend/`.

---

## Step 4: Create and configure the backend `.env` file

1. In **File Manager**, go to `public_html/backend/`.
2. Create a new file named **`.env`** (exactly, with the dot).
   - If the manager doesn’t show “Create New File” for dotfiles, create `env.txt`, paste the content, then rename to `.env`.
3. Put the following in `.env`, and **replace** the placeholders with the database and URL you noted:

```env
# Database (use the values from Step 1)
DB_HOST=localhost
DB_USER=youruser_dbuser
DB_PASS=your_database_password
DB_NAME=youruser_lcmtv_db
DB_PORT=3306

# YouTube (optional; get key from Google Cloud Console)
YOUTUBE_API_KEY=your_youtube_api_key_here

# JWT secret – use a long random string (e.g. 32+ characters)
JWT_SECRET=your_long_random_jwt_secret_here

# Production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

4. Save the file.
5. **Security:** Set permissions so only the server can read it (e.g. **640** via File Manager “Permissions” or “Change Permissions”).

---

## Step 5: Set the frontend API base URL

The frontend must call your live API, not localhost.

1. In File Manager, open **`public_html/app/app.js`** (or edit locally and re-upload).
2. Find the line that sets the API base, e.g.:

   ```js
   .constant('API_BASE', '/LCMTVWebNew/backend/api')
   ```

3. Change it to use the path to your backend on the live domain (no localhost, no project folder name):

   ```js
   .constant('API_BASE', '/backend/api')
   ```

   So:
   - If the site is `https://yourdomain.com/`, the API will be `https://yourdomain.com/backend/api`.
   - If you put the site in a subfolder (e.g. `https://yourdomain.com/churchtv/`), use:

   ```js
   .constant('API_BASE', '/churchtv/backend/api')
   ```

4. Save and re-upload if you edited locally.

---

## Step 6: Run backend setup scripts (if you use them)

If your project uses PHP scripts to create tables or an admin user (e.g. `setup.php`, `create_*_tables.php`, `fix_demo_password.php`):

1. In cPanel, open **Terminal** (if available), or use **PHP** run from the browser (only if your host allows it and the folder is not public).
2. From the **backend** directory run, for example:
   - `php setup.php`
   - `php create_analytics_tables.php`
   - etc.
3. Alternatively, some hosts let you run a script once by visiting it (e.g. `https://yourdomain.com/backend/setup.php`). If you do that, **delete or protect** the script afterward so it can’t be run again by others.

---

## Step 7: Set permissions

In File Manager:

1. **Backend folder:** typically **755** for directories and **644** for `.php` files.
2. **`.env`:** **640** (readable by server only).
3. If you have a **logs** or **cache** directory under backend, set them writable (e.g. **755** or **775** depending on the server user).

---

## Step 8: Choose PHP version (cPanel)

1. In cPanel, open **Select PHP Version** or **MultiPHP Manager**.
2. Select **PHP 7.4** or **8.0+** for the domain (or the folder that contains the site).
3. Ensure required extensions are enabled: **mysqli**, **mbstring**, **json**, **curl** (and **openssl** if you use HTTPS/JWT).

---

## Step 9: Optional – Redirect root to “frontend” (only if you used a subfolder)

If you uploaded the frontend inside a subfolder (e.g. `public_html/frontend/`) and want `https://yourdomain.com/` to redirect to `https://yourdomain.com/frontend/`:

1. In **File Manager**, go to `public_html/`.
2. Edit or create **`.htaccess`** in the root.
3. Add (adjust path if different):

```apache
RewriteEngine On
RewriteRule ^$ frontend/ [L,R=302]
```

If the site root is already the frontend (as in Step 3), you don’t need this.

---

## Step 10: Test the site

1. **Homepage:** Open `https://yourdomain.com/` (or `https://yourdomain.com/frontend/` if you use that).
2. **API:** Open `https://yourdomain.com/backend/api/` or a known endpoint (e.g. `https://yourdomain.com/backend/api/categories`). You should get JSON, not a 404 or a directory listing.
3. **Login / admin:** If you created an admin user, test login and that videos/categories load.
4. **Browser console:** Check for errors (e.g. 404 to `/backend/api/...`). If you see wrong paths, double-check **Step 5** (API_BASE).

---

## Troubleshooting

| Problem | What to check |
|--------|----------------|
| Blank page or 500 error | PHP version (7.4+), PHP error log in cPanel, file permissions. |
| “Database connection failed” | `.env` in `backend/`, correct DB_NAME, DB_USER, DB_PASS, DB_HOST (usually `localhost`). |
| API 404 | `backend/.htaccess` is present; `backend/api/index.php` exists; API_BASE in `app/app.js` is `/backend/api` (or your path). |
| CORS or mixed content | Use HTTPS everywhere; if you use a separate API domain, configure CORS in the backend. |
| “Unauthorized” or JWT errors | JWT_SECRET set in `.env` and same for all requests; no trailing spaces in .env values. |

---

## Summary checklist

- [ ] MySQL database and user created in cPanel; user has all privileges on the database.
- [ ] Schema (and any migrations) applied (e.g. `schema.sql` via phpMyAdmin or setup scripts).
- [ ] Frontend files at document root; full `backend` folder under `backend/`.
- [ ] `backend/.env` created with DB_*, JWT_SECRET, APP_URL, and optional YOUTUBE_API_KEY.
- [ ] `app/app.js` updated: `API_BASE` = `/backend/api` (or your path).
- [ ] PHP 7.4+ selected; mysqli, mbstring, json, curl enabled.
- [ ] Permissions: backend 755/644; `.env` 640.
- [ ] Site and API tested in the browser and console.

After this, your site is published through cPanel with the main site at your domain root and the API at `/backend/api`.
