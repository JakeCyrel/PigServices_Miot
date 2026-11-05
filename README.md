# PigServices_Miot

Responsive PHP/MySQL starter system for connecting male and female pig owners in Bindoy, Negros Oriental.

## Run with XAMPP

1. Copy this folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin, create and select a database named `pig_breeding_services`, then import `database.sql` into it.
4. Check the credentials in `config.php`.
5. Visit `/Pig_Services_for_Bindoy_Negros_Oriental/setup_admin.php` once.
6. Delete `setup_admin.php` after setup.

The demo listing is included for testing. New accounts are available immediately after registration. Administrators can view the registered user directory from their dashboard.

## Publish a visual preview with GitHub Pages

The static preview is in `docs/`. It shows the public landing page and works on phones, but GitHub Pages does not run PHP or MySQL, so sign-in, registration, service requests, and messaging are disabled there.

1. Push this repository to GitHub.
2. In the repository, open **Settings → Pages**.
3. Under **Build and deployment**, choose **Deploy from a branch**, select the branch containing `docs/`, choose the `/docs` folder, and save.
4. Open the published address shown on that settings page. Project sites usually use `https://<account>.github.io/<repository>/`.

Do not commit database passwords or other hosting credentials to GitHub. Use a PHP/MySQL host for the fully functional system.

## Automatic InfinityFree deployment and setup

The workflow in `.github/workflows/deploy-infinityfree.yml` deploys the repository to InfinityFree `htdocs/` whenever code is pushed to `main`. It can also be started manually from the repository's **Actions** tab. It deploys the PHP app, not the GitHub Pages preview.

Before enabling it, open **Settings → Secrets and variables → Actions** in the GitHub repository. Add the non-sensitive connection details as repository variables (or keep them as secrets; the workflow supports either):

- `FTP_SERVER`: `ftpupload.net`
- `FTP_USERNAME`: your InfinityFree FTP username
- `DB_HOST`: your InfinityFree MySQL hostname
- `DB_NAME`: your InfinityFree database name
- `DB_USER`: your InfinityFree MySQL username

Add these as repository secrets, never variables:

- `FTP_PASSWORD`: your InfinityFree FTP password
- `DB_PASS`: your MySQL password
- `ADMIN_SETUP_TOKEN`: a new random setup-only token

The first visit to `https://<your-domain>/setup_admin.php` initializes tables from `database.sql` if they are missing, then asks for the setup token and the administrator email and password. After the admin is created, the setup page will no longer allow another admin to be created. Keep all secrets in GitHub's Secrets settings; never put their values in this file or send them in chat.

## View from another device on the same Wi-Fi

1. Keep Apache and MySQL running on the computer with XAMPP.
2. Find that computer's local IPv4 address (for example, with `ipconfig` on Windows).
3. On another device connected to the same Wi-Fi, open `http://<computer-ip>/Pig_Services_for_Bindoy_Negros_Oriental/`.
4. If the page cannot be reached, allow Apache through the computer's firewall for private networks.

This only works while the XAMPP computer is on and connected to the same local network. It does not publish the site to the internet.

## Publish on a PHP/MySQL host

Choose a host that provides PHP, MySQL, and a public web address. Create and select a MySQL database there, import `database.sql` using the host's database tool, and upload this project's PHP files and `assets` folder to the host's web root.

Set these environment variables in the host's PHP/app settings to the database credentials it provides:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

If the host does not support environment variables, put its database values in `config.php` before uploading, and do not publish that file in a public source repository. The local XAMPP defaults remain in place when the variables are unset.

Run `setup_admin.php` once on the hosted site and choose your administrator email and a strong password, then delete that file. Hosting providers' free plans and setup requirements vary; this project is ready for a compatible host, but it has not been uploaded or made public from this workspace.
