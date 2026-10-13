# PDF engines: qpdf, exiftool, LibreOffice

Chrome renders HTML and prints it to PDF. It cannot open an existing PDF, change it or convert an Office document.
For those features the bundle calls three command-line tools installed on the same machine, the same way it calls
Chrome:

| Tool | Used for | Builders and options |
|---|---|---|
| [qpdf](https://qpdf.readthedocs.io/) | Merge, split, rotate, flatten, encrypt, attachments, watermark and stamp overlays, bookmarks | `merge()`, `split()`, `rotate()`, `flatten()`, `encrypt()`, `embed()`, `watermark()`, `stamp()`, and the `split*`, `rotate*`, `flatten`, `userPassword` / `ownerPassword`, `embedFiles`, `watermark*`, `stamp*`, `bookmarks` options |
| [ExifTool](https://exiftool.org/) | PDF metadata (Info dictionary and XMP) | `metadata()` / `addMetadata()` on every PDF builder that has them |
| [LibreOffice](https://www.libreoffice.org/) (`soffice`) | Office, ODF, RTF, text, ... to PDF; PDF/A and PDF/UA | `office()`, `convert()`, and the `pdfFormat` / `pdfUniversalAccess` options |

There is no Docker image, no Gotenberg server and no daemon. Each tool is started as a short-lived process with
[symfony/process](https://symfony.com/doc/current/components/process.html) when a feature needs it.

**Each tool is optional.** If you only use the Chromium builders without post-processing options, you need none of
them. A missing tool only breaks the feature that needs it, with a
`Daif\ChromePdfBundle\Exception\EngineException` such as:

```
qpdf was not found, install it or set daif_chrome_pdf.qpdf_binary (or the QPDF_PATH environment variable). Searched: ...
```

Text and image watermarks or stamps are rendered by Chrome into a one-page transparent PDF, then applied with qpdf,
so they need both Chrome and qpdf.

## Versions

The bundle is tested in CI with qpdf 11, ExifTool 12 and LibreOffice 7.4 or later. Older versions may work for most
features, but bookmarks use qpdf's JSON v2 format, which needs **qpdf 11.0 or later**. Distributions with an older
qpdf (for example Debian 11, Ubuntu 22.04 or RHEL 8) can install a recent build from the
[qpdf releases page](https://github.com/qpdf/qpdf/releases) (AppImage or static binary).

## Installation

Install only the tools you need. For LibreOffice, the Writer component handles text documents, Calc spreadsheets,
Impress presentations and Draw is used by `convert()` (PDF/A, PDF/UA) and for some image formats.

### Debian / Ubuntu

```bash
sudo apt-get install -y qpdf libimage-exiftool-perl
# LibreOffice without the desktop dependencies (Debian 12+, Ubuntu 22.04+)
sudo apt-get install -y --no-install-recommends \
    libreoffice-writer-nogui libreoffice-calc-nogui libreoffice-impress-nogui libreoffice-draw-nogui
# Older releases: libreoffice-writer libreoffice-calc libreoffice-impress libreoffice-draw
```

Binaries: `/usr/bin/qpdf`, `/usr/bin/exiftool`, `/usr/bin/soffice`.

### Alpine

```bash
apk add --no-cache qpdf exiftool libreoffice
# or only the components you need: libreoffice-writer libreoffice-calc libreoffice-impress libreoffice-draw
```

Alpine images have very few fonts. Add `font-noto` or `ttf-dejavu`, otherwise LibreOffice replaces missing fonts and
the layout changes.

### RHEL / Rocky / AlmaLinux / Fedora

```bash
# qpdf is in AppStream (RHEL 9) or EPEL; ExifTool is in EPEL
sudo dnf install -y epel-release            # RHEL / Rocky / Alma only
sudo dnf install -y qpdf perl-Image-ExifTool
sudo dnf install -y libreoffice-writer libreoffice-calc libreoffice-impress libreoffice-draw
```

RHEL 10 and its rebuilds no longer ship LibreOffice. Install the RPM archive from
[libreoffice.org](https://www.libreoffice.org/download/download-libreoffice/): it installs under
`/opt/libreofficeX.Y/program/soffice`, which the bundle finds automatically.

### macOS (Homebrew)

```bash
brew install qpdf exiftool
brew install --cask libreoffice
```

Binaries: `/opt/homebrew/bin/qpdf` (Apple Silicon) or `/usr/local/bin/qpdf` (Intel), the same for `exiftool`, and
`/Applications/LibreOffice.app/Contents/MacOS/soffice`.

### Windows

With [Chocolatey](https://chocolatey.org/):

```powershell
choco install qpdf exiftool libreoffice-fresh -y
```

With [winget](https://learn.microsoft.com/windows/package-manager/winget/):

```powershell
winget install --id QPDF.QPDF -e
winget install --id OliverBetz.ExifTool -e
winget install --id TheDocumentFoundation.LibreOffice -e
```

Manual installation:

- **qpdf**: download the Windows installer or zip from the [qpdf releases](https://github.com/qpdf/qpdf/releases).
  The installer uses `C:\Program Files\qpdf <version>\bin\qpdf.exe`.
- **ExifTool**: download the Windows executable from [exiftool.org](https://exiftool.org/), rename
  `exiftool(-k).exe` to `exiftool.exe` (the `-k` suffix makes it wait for a key press, which blocks the conversion)
  and put it in `C:\Program Files\exiftool\` or anywhere on `PATH`. With Strawberry Perl, the `exiftool` script in
  `C:\Strawberry\perl\bin` also works: a binary without the `.exe`, `.bat` or `.cmd` extension is started with
  `perl`, which must then be on the `PATH`.
- **LibreOffice**: install the MSI from [libreoffice.org](https://www.libreoffice.org/download/download-libreoffice/).
  It uses `C:\Program Files\LibreOffice\program\soffice.exe`.

The account that runs PHP (IIS application pool, service account) must be able to execute these binaries and to
write in `temp_dir`. See [chrome-installation.md](chrome-installation.md#iis--fastcgi--php-fpm-service-accounts) for
the same constraints with Chrome.

## Auto-detection

For each tool, the first match wins:

1. The configured path (`qpdf_binary`, `exiftool_binary`, `libreoffice_binary`). If it is set and the file does not
   exist or is not executable, an `EngineException` is thrown: there is no silent fallback.
2. The environment variable: `QPDF_PATH`, `EXIFTOOL_PATH`, `LIBREOFFICE_PATH`.
3. The `PATH` of the PHP process (`.exe` included on Windows).
4. Well-known locations (`%VAR%` references are expanded on Windows):

| OS | qpdf | exiftool | soffice |
|---|---|---|---|
| Linux | `/usr/bin`, `/usr/local/bin` | `/usr/bin`, `/usr/local/bin` | `/usr/bin/soffice`, `/usr/local/bin/soffice`, `/usr/bin/libreoffice`, `/usr/lib/libreoffice/program/soffice`, `/opt/libreoffice*/program/soffice` |
| macOS | `/opt/homebrew/bin`, `/usr/local/bin`, `/opt/local/bin` (MacPorts) | same as qpdf | `/Applications/LibreOffice.app/Contents/MacOS/soffice` (also under `~/Applications`), `/opt/homebrew/bin`, `/usr/local/bin` |
| Windows | `%ProgramFiles%\qpdf*\bin\qpdf.exe` (and `%ProgramFiles(x86)%`) | `C:\Strawberry\perl\bin\exiftool.bat` and `exiftool`, `%ProgramFiles%\exiftool\exiftool.exe` | `%ProgramFiles%\LibreOffice\program\soffice.exe` (and `%ProgramFiles(x86)%`) |

On Windows, the shims of Chocolatey (`%ChocolateyInstall%\bin`, default `C:\ProgramData\chocolatey\bin`), winget
(`%LOCALAPPDATA%\Microsoft\WinGet\Links`) and Scoop (`%USERPROFILE%\scoop\shims`) are also checked for
`qpdf.exe`, `exiftool.exe` and `soffice.exe`.

Detection runs the first time a tool is needed, not when the container is built, so an application that never
merges PDFs never looks for qpdf.

> [!NOTE]
> The `PATH` seen by PHP-FPM, Apache or IIS is often shorter than the one of your shell. If a tool works in a
> terminal but not in the application, set its path explicitly.

## Configuration

```yaml
# config/packages/daif_chrome_pdf.yaml
daif_chrome_pdf:
    # null = auto-detection (see above)
    qpdf_binary: '%env(default::QPDF_PATH)%'
    exiftool_binary: '%env(default::EXIFTOOL_PATH)%'
    libreoffice_binary: '%env(default::LIBREOFFICE_PATH)%'

    # Timeout of each engine call, in milliseconds. LibreOffice is slow to start: keep it generous.
    engine_timeout: 120000

    # Working files of the engines are created here too. null = sys_get_temp_dir().
    temp_dir: null
```

Windows examples (single quotes keep backslashes; double `%` in YAML for environment references):

```yaml
daif_chrome_pdf:
    qpdf_binary: 'C:\Program Files\qpdf 11.9.1\bin\qpdf.exe'
    libreoffice_binary: '%%ProgramFiles%%\LibreOffice\program\soffice.exe'
```

`engine_timeout` applies to each process call (one qpdf command, one LibreOffice conversion), not to the whole
generation. The Chrome `timeout` is separate. Keep PHP's `max_execution_time` above the sum you expect.

## How LibreOffice is run

Each conversion starts `soffice --headless` with its own temporary user profile
(`-env:UserInstallation=file:///...`), created under `temp_dir` and deleted afterwards. The profile contains a small
generated Basic macro that opens the document (with its password if one is given, document macros disabled, links
not updated), switches the page styles to landscape when `landscape()` is set, and exports it with the PDF filter
options. The `--convert-to` command line option cannot pass a password or change the orientation, which is why a
macro is used. This has three consequences:

- Several conversions can run in parallel (FPM workers, Messenger consumers) without locking each other.
- The PHP user does not need a writable home directory.
- Each call pays LibreOffice's start-up time (often 1 to 3 seconds). This is the price of not running a daemon.

A LibreOffice already open on the desktop of the same user does not interfere, because the profile is different.

## Troubleshooting

- **`... was not found, install it or set daif_chrome_pdf.*_binary`**: the tool is not installed, or not on the
  `PATH` of the PHP process. Run `which qpdf` (`where qpdf` on Windows) as the user that runs PHP, then set the
  configuration key or the environment variable.
- **`qpdf exited with code 2: ...`**: the input is not a valid PDF, or is encrypted. qpdf exit code 3 (warnings) is
  accepted. The stderr output in the message tells which file failed.
- **Ubuntu: qpdf fails with `Permission denied` on files that exist**: Ubuntu ships an AppArmor profile
  (`/etc/apparmor.d/qpdf`) that only lets qpdf open `*.pdf` and `*.json` files under the home directory, `/tmp`,
  `/var/tmp`, `/mnt` and `/media`, also inside containers running on that host. The bundle already copies every
  input into its own temporary folder with a `.pdf` name, so keep `temp_dir` unset (`/tmp`) or point it under one of
  those directories.
- **Bookmarks fail with an unknown option error**: qpdf is older than 11.0. Upgrade it.
- **LibreOffice times out**: the first start after installation builds caches and can take much longer. Raise
  `engine_timeout`, and check that no dialog is waiting (corrupted file, password-protected document without
  `password()`).
- **`LibreOffice could not convert "...": unable to open the document`**: the format is not supported by the
  installed components (for example a spreadsheet without `libreoffice-calc`), the file is damaged, or the password
  is wrong or missing. Install the missing component or check the file.
- **Wrong fonts or layout in Office conversions**: the fonts used by the document are not installed on the server.
  Install them (for Microsoft fonts: `ttf-mscorefonts-installer` on Debian, or copy them on Windows) or embed them in
  the document.
- **Windows: ExifTool hangs until the timeout**: the executable is still named `exiftool(-k).exe`. Rename it.
- **Windows: access denied**: the IIS application pool identity cannot execute the binary or write in `temp_dir`.
- **Metadata not visible in a viewer**: some viewers read the XMP packet, others the Info dictionary. ExifTool writes
  the tags you pass; use the `XMP-pdf:` or `PDF:` prefix to target one of them explicitly.

Run the bundle's own engine tests to check an installation: see [testing.md](testing.md#running-this-bundles-own-engine-tests).
