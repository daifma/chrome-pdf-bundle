# Installing Chrome / Chromium / Edge

ChromePdfBundle needs a Chromium-based browser binary on the same machine as PHP: **Google Chrome**, **Chromium** or
**Microsoft Edge**. It talks to the browser over the
[Chrome DevTools Protocol (CDP)](https://chromedevtools.github.io/devtools-protocol/), using the
[chrome-php/chrome](https://github.com/chrome-php/chrome) library.

> **No Docker needed.** The browser runs as a local headless process, started and controlled by PHP.

## How it works

When you call `->generate()`, the bundle:

1. Starts a **headless** browser process the first time it is needed, then reuses it in long-running processes
   (FPM workers, Messenger consumers). If the process has died, it is restarted automatically.
2. Creates a private working directory in `temp_dir` and writes `index.html`, the header and footer, and the assets
   into it.
3. Opens a new tab, applies cookies, headers, emulation and request filtering, then loads the page.
4. Waits (`load`, network idle, `waitDelay`, `waitForExpression`, `waitForSelector`) within the global `timeout`.
5. Calls `Page.printToPDF` or `Page.captureScreenshot`.
6. **Closes the tab** and deletes the working directory.

## PHP requirements (all platforms)

- PHP 8.1+
- **`ext-sockets`**, needed by chrome-php for the CDP connection. Check with `php -m | grep sockets`.
  - Debian/Ubuntu: built into the `php8.x-cli` / `php8.x-fpm` packages.
  - RHEL: `dnf install php-sockets` (or `php8x-php-sockets` from Remi).
  - Windows: see [below](#enable-ext-sockets-in-phpini).
- The PHP user can run the browser binary, write to `temp_dir`, and read `assets_directory`.

## Linux

### Debian / Ubuntu

```bash
# Chromium (Debian)
sudo apt update
sudo apt install -y chromium            # binary: /usr/bin/chromium

# Ubuntu: chromium-browser is a snap wrapper, which often fails for service users (www-data).
# Prefer Google Chrome's .deb on Ubuntu servers:
wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | sudo gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" | sudo tee /etc/apt/sources.list.d/google-chrome.list
sudo apt update
sudo apt install -y google-chrome-stable  # binary: /usr/bin/google-chrome-stable
```

### RHEL / Rocky / AlmaLinux / Fedora

```bash
# Google Chrome (RHEL 8/9, Rocky, Alma)
sudo dnf install -y https://dl.google.com/linux/direct/google-chrome-stable_current_x86_64.rpm
# binary: /usr/bin/google-chrome-stable

# Chromium (Fedora, or RHEL with EPEL)
sudo dnf install -y epel-release   # RHEL/Rocky/Alma only
sudo dnf install -y chromium       # binary: /usr/bin/chromium-browser
```

### Alpine

```bash
apk add --no-cache chromium   # binary: /usr/bin/chromium-browser (or /usr/bin/chromium)
```

### Arch Linux

```bash
sudo pacman -S chromium       # binary: /usr/bin/chromium
```

### Missing system libraries

Minimal servers sometimes lack shared libraries. Chrome then exits right away with `error while loading shared
libraries`. The distribution packages above pull in their dependencies. With a manually extracted Chrome, install:

```bash
# Debian / Ubuntu
sudo apt install -y libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon0 \
    libxcomposite1 libxrandr2 libgbm1 libpango-1.0-0 libcairo2 libasound2

# RHEL / Fedora
sudo dnf install -y nss atk at-spi2-atk cups-libs libXcomposite libXrandr mesa-libgbm pango alsa-lib
```

### Fonts on Linux

Servers often ship with almost no fonts, so text renders as boxes, or falls back to an ugly font. Install at least:

```bash
# Debian / Ubuntu
sudo apt install -y fonts-liberation fonts-dejavu-core fonts-noto-core fonts-noto-color-emoji
# CJK: fonts-noto-cjk   |   Microsoft core fonts: ttf-mscorefonts-installer (contrib)

# RHEL / Fedora
sudo dnf install -y liberation-fonts dejavu-sans-fonts google-noto-sans-fonts google-noto-emoji-color-fonts
```

Then run `fc-cache -f`. For pixel-identical output across machines, embed your fonts with `chrome_pdf_font_face()`
(see [fonts](fonts.md)).

## macOS

```bash
brew install --cask google-chrome
# or
brew install --cask chromium
```

**Binary paths:**
- Google Chrome: `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`
- Chromium: `/Applications/Chromium.app/Contents/MacOS/Chromium`
- Microsoft Edge: `/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge`

The bundle detects all three. Paths contain spaces: in YAML, quote them, and don't escape the spaces.

## Windows

### Which browser?

- **Microsoft Edge** (Chromium-based) is **preinstalled on Windows 10/11 and Windows Server 2019+** (Server 2022/2025
  ship with it; on Server 2019 install it from the Edge for Business MSI if it is missing). It works out of the box and
  is updated through Windows Update or Edge Update. On locked-down servers, it is usually the simplest choice.
- **Google Chrome**: install the [Chrome Enterprise MSI](https://chromeenterprise.google/browser/download/) (machine-wide
  installation in `Program Files`). The per-user installer puts Chrome in `%LOCALAPPDATA%`, where service accounts
  cannot see it.
- **Chromium**: `choco install chromium` or `scoop install chromium`.

### Auto-detection order

When `chrome_binary` is `null`, the bundle uses the first match in this order. `%ProgramFiles%`,
`%ProgramFiles(x86)%` and `%LOCALAPPDATA%` are read from the environment (with `C:\Program Files` and
`C:\Program Files (x86)` as fallbacks):

1. The `CHROME_PATH` environment variable (used as-is, `%VAR%` references are expanded)
2. Google Chrome: `%ProgramFiles%`, then `%ProgramFiles(x86)%`, then `%LOCALAPPDATA%`, each followed by
   `\Google\Chrome\Application\chrome.exe`
3. Chromium: the same three folders, followed by `\Chromium\Application\chrome.exe`
4. Microsoft Edge: the same three folders, followed by `\Microsoft\Edge\Application\msedge.exe`
5. `chrome.exe`, `chromium.exe` or `msedge.exe` in a folder of the `PATH` environment variable

So a stock Windows Server 2019+ with Edge works without any configuration, and an installed Chrome takes precedence
over Edge.

On Linux the bundle looks for `google-chrome`, `google-chrome-stable`, `chromium`, `chromium-browser`,
`microsoft-edge` and `microsoft-edge-stable` (in that order) in the `PATH` folders, then in `/usr/bin`,
`/usr/local/bin`, `/snap/bin` and `/opt/google/chrome`, and finally `/opt/google/chrome/chrome` and
`/opt/microsoft/msedge/msedge`. On macOS it checks `/Applications` and `~/Applications` first (see
[macOS](#macos)), then the Linux list.

### `CHROME_PATH`

Set it machine-wide (*System Properties → Environment Variables → System variables*, or PowerShell as administrator):

```powershell
[Environment]::SetEnvironmentVariable('CHROME_PATH', 'C:\Program Files\Google\Chrome\Application\chrome.exe', 'Machine')
```

Restart IIS (`iisreset`), the PHP-FPM or PHP-CGI service and your Messenger workers so they see the new variable. You
can also configure the path explicitly:

```yaml
daif_chrome_pdf:
    # single quotes: backslashes are literal, spaces are fine
    chrome_binary: 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
    # or
    # chrome_binary: '%env(default::CHROME_PATH)%'
```

### Enable `ext-sockets` in php.ini

`ext-sockets` ships with the official Windows PHP builds, but it is **disabled** by default. In the `php.ini` used by
your web server **and** by the CLI (they are often different files, check `php --ini` and `phpinfo()`):

```ini
extension_dir = "ext"
extension=sockets
```

Check it with `php -m | findstr sockets`. Without it, chrome-php fails with `Call to undefined function socket_create()`
or a connection error.

### IIS / FastCGI / PHP-FPM service accounts

PHP under IIS runs as the **application pool identity** (`IIS AppPool\<PoolName>`), and Chrome inherits that account.
Make sure that:

- The identity has **Read & execute** on the browser folder (`C:\Program Files\...` is readable by default).
- The identity has **Modify** on `temp_dir`. By default, that is `sys_get_temp_dir()`, which for an app pool is often
  `C:\Windows\Temp`, or a per-profile temp folder when *Load User Profile* is enabled. To avoid surprises, set an
  explicit folder:

  ```yaml
  daif_chrome_pdf:
      temp_dir: 'D:\apps\myapp\var\chrome-pdf'
  ```

  ```powershell
  icacls "D:\apps\myapp\var\chrome-pdf" /grant "IIS AppPool\MyAppPool:(OI)(CI)M"
  ```

- In the application pool's advanced settings, **Load User Profile = True**. Chrome needs a writable profile and
  `%LOCALAPPDATA%`. Without it, startup may hang until `timeout`.
- The FastCGI `activityTimeout` / `requestTimeout` and PHP's `max_execution_time` are greater than the bundle `timeout`.

### Antivirus and locked temporary files

Windows Defender and other endpoint protection tools scan the files Chrome writes, and can keep them open briefly.
The bundle closes the tab before it deletes the working directory, retries the deletion a few times, and **never fails
a conversion because of cleanup**. Instead, it logs a warning and leaves the folder behind. If folders pile up:

- Exclude `temp_dir` (and optionally the Chrome process) from real-time scanning, if your security policy allows it.
- Purge old `chrome_pdf_*` folders with a scheduled task.

### Paths with spaces and drive letters

`C:\Program Files\...`, `D:\My Apps\...` and UNC-free local paths are handled: the bundle builds valid
`file:///C:/...` URLs (forward slashes, encoded segments) and never goes through a shell. In YAML, use **single quotes**
so backslashes are not treated as escapes. In double-quoted strings you would have to write `\\`.

### Verify the installation (Windows)

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --version
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --version
php -m | findstr sockets
```

> [!NOTE]
> On Windows, `--version` may print nothing for Chrome, because the binary is a GUI program. Use
> `(Get-Item 'C:\Program Files\Google\Chrome\Application\chrome.exe').VersionInfo.ProductVersion` instead.

### Fonts on Windows

Windows ships with the common fonts (Arial, Calibri, Segoe UI, Times New Roman...). Fonts installed **per user** (the
default when you double-click a `.ttf` on recent Windows versions) are **not** visible to service accounts. Install
them *for all users*, or embed them with `chrome_pdf_font_face()`.

## Servers without Docker (on-premise)

This bundle was built for these servers:

- Install the browser from your distribution's repository, your internal mirror, or the vendor MSI. No container
  runtime is involved.
- If the server has no Internet access, download the `.deb` / `.rpm` / MSI on a connected machine and copy it over. You
  will need to update it regularly yourself, because Chrome receives security fixes every few weeks.
- Set `request_context.base_uri` if you use `->route()` from CLI or Messenger workers.
- Configure `allow_list` / `deny_list` to match your network zones (see [security](security.md)).

## Running as root requires `no_sandbox`

Chrome refuses to start its sandbox as `root` on Linux. If PHP runs as root (containers, some CI runners, `sudo php
bin/console ...`), you'll see errors such as:

```
Running as root without --no-sandbox is not supported
```

Prefer running PHP as an unprivileged user. If that is not possible:

```yaml
daif_chrome_pdf:
    no_sandbox: true
```

The sandbox is enabled by default, and turning it off has security implications (see
[security](security.md#chrome-sandbox)).

## Verify your installation

```bash
google-chrome-stable --version   # or chromium --version / chromium-browser --version
php -m | grep sockets
```

Then check that the bundle services are registered:

```bash
php bin/console debug:container daif_chrome_pdf
```

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| `Unable to find a Chrome, Chromium or Microsoft Edge executable` | Install a browser or set `chrome_binary` / `CHROME_PATH`. On Windows, the per-user installer puts Chrome where the service account can't see it. Use the MSI or Edge. |
| `Running as root without --no-sandbox is not supported` | Run PHP as a normal user, or set `no_sandbox: true`. |
| `No usable sandbox!` / `Failed to move to new namespace` | Kernel or container without user namespaces (`kernel.unprivileged_userns_clone=0`, AppArmor restrictions on Ubuntu 24.04). Enable them, or set `no_sandbox: true`. |
| `BrowserException: Timed out after 30000 ms waiting for ...` | The page is slow, waits for a resource that never arrives, or `waitForExpression`/`waitForSelector` never matches. Raise `timeout`, or use `skipNetworkIdleEvent(true)`. Check `deny_list` (a blocked script can prevent your "ready" flag). |
| Startup hangs on Windows/IIS | Set **Load User Profile = True** on the app pool, give the identity write access to `temp_dir`, and check `ext-sockets`. |
| `socket_create()` undefined | Enable `ext-sockets` (see above). |
| Squares / wrong font | Install fonts (see the Linux and Windows sections above) or embed them with `chrome_pdf_font_face()`. |
| `ERR_CERT_AUTHORITY_INVALID` | Install the internal CA in the system store, or enable `ignore_certificate_errors` (dev only). |
| Leftover `chrome_pdf_*` folders | Antivirus locks on Windows. See [antivirus](#antivirus-and-locked-temporary-files). |
| `/dev/shm` errors / crashes in small containers | The bundle already passes `--disable-dev-shm-usage`. If crashes continue, give the container more memory, or increase `/dev/shm`. |
