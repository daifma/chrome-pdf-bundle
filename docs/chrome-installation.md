# Installing Chrome / Chromium

ChromePdfBundle requires a Chrome or Chromium browser binary on the machine. It communicates with it via the [Chrome DevTools Protocol (CDP)](https://chromedevtools.github.io/devtools-protocol/) using the [chrome-php/chrome](https://github.com/niconiahi/chrome-php) PHP library.

> **No Docker needed.** The browser runs as a local process, controlled directly by PHP.

## How it works

When you call `->generate()`, the bundle:

1. Starts a **headless Chrome** process (if not already running)
2. Opens a new browser tab
3. Loads your HTML/URL content
4. Calls `Page.printToPDF` or `Page.captureScreenshot` via CDP
5. Returns the binary data to your Symfony application

The underlying library `chrome-php/chrome` handles process management, WebSocket communication, and all CDP protocol details.

## Linux

### Debian / Ubuntu

```bash
# Install Chromium (recommended for servers)
sudo apt update
sudo apt install -y chromium-browser

# Or install Google Chrome
wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | sudo gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" | sudo tee /etc/apt/sources.list.d/google-chrome.list
sudo apt update
sudo apt install -y google-chrome-stable
```

**Binary paths:**
- Chromium: `/usr/bin/chromium-browser` or `/usr/bin/chromium`
- Google Chrome: `/usr/bin/google-chrome` or `/usr/bin/google-chrome-stable`

### RHEL / CentOS / Fedora / Rocky

```bash
# Install Chromium
sudo dnf install -y chromium

# Or install Google Chrome
sudo dnf install -y https://dl.google.com/linux/direct/google-chrome-stable_current_x86_64.rpm
```

**Binary paths:**
- Chromium: `/usr/bin/chromium-browser`
- Google Chrome: `/usr/bin/google-chrome-stable`

### Alpine (Docker / minimal containers)

```bash
apk add --no-cache chromium
```

**Binary path:** `/usr/bin/chromium-browser`

### Arch Linux

```bash
sudo pacman -S chromium
```

**Binary path:** `/usr/bin/chromium`

## macOS

### Homebrew

```bash
# Chromium
brew install --cask chromium

# Or Google Chrome
brew install --cask google-chrome
```

**Binary paths:**
- Chromium: `/Applications/Chromium.app/Contents/MacOS/Chromium`
- Google Chrome: `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`

### Manual download

Download from [google.com/chrome](https://www.google.com/chrome/) and install.

## Windows

### Chocolatey

```powershell
# Google Chrome
choco install googlechrome

# Or Chromium
choco install chromium
```

### Scoop

```powershell
scoop install chromium
```

### Manual download

Download from [google.com/chrome](https://www.google.com/chrome/) and install.

**Common binary paths:**
- Google Chrome: `C:\Program Files\Google\Chrome\Application\chrome.exe`
- Google Chrome (x86): `C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`
- Chromium: `C:\Users\<username>\AppData\Local\Chromium\Application\chrome.exe`

## Configuration

### Auto-detection

By default, the bundle will try to find Chrome/Chromium automatically on your system. The `chrome-php/chrome` library searches common binary paths.

```yaml
# config/packages/daif_chrome_pdf.yaml

daif_chrome_pdf:
    assets_directory: '%kernel.project_dir%/assets'
```

### Explicit binary path

If auto-detection doesn't find your browser, or if you have multiple versions, specify the path explicitly:

```yaml
# Linux
daif_chrome_pdf:
    chrome_binary: '/usr/bin/google-chrome-stable'

# macOS
daif_chrome_pdf:
    chrome_binary: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'

# Windows
daif_chrome_pdf:
    chrome_binary: 'C:\Program Files\Google\Chrome\Application\chrome.exe'
```

### Environment variable

You can also use an environment variable for flexibility across environments:

```yaml
daif_chrome_pdf:
    chrome_binary: '%env(CHROME_BINARY)%'
```

```bash
# .env.local
CHROME_BINARY=/usr/bin/chromium-browser
```

## Verify your installation

To check that Chrome is installed and accessible:

```bash
# Linux / macOS
google-chrome --version
# or
chromium --version
# or
chromium-browser --version
```

```powershell
# Windows
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --version
```

You should see output like: `Google Chrome 120.0.6099.109`

## Server / CI considerations

On headless servers (no display), Chrome runs fine in headless mode -- the bundle always launches Chrome with `--headless` and `--no-sandbox` flags.

If you encounter errors related to missing system libraries, install the required dependencies:

```bash
# Debian / Ubuntu
sudo apt install -y \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
    libxkbcommon0 libxcomposite1 libxrandr2 libgbm1 \
    libpango-1.0-0 libcairo2 libasound2

# RHEL / CentOS / Fedora
sudo dnf install -y \
    nss atk at-spi2-atk cups-libs libXcomposite \
    libXrandr libgbm pango alsa-lib
```

## About chrome-php/chrome

This bundle uses the [chrome-php/chrome](https://github.com/niconiahi/chrome-php) PHP library under the hood. It provides:

- Process management (start/stop Chrome)
- WebSocket-based CDP communication
- Page navigation, PDF generation, screenshot capture
- Cookie injection, header customization, JavaScript evaluation
- Automatic cleanup of browser resources

The library is installed automatically as a Composer dependency of this bundle.
