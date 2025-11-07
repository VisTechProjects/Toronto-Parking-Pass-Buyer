# Toronto Parking Pass Automation Setup

This guide will help you set up automated parking pass purchases that sync with your ESP32 e-ink display.

## System Overview

```
Desktop/Server                               GitHub                    ESP32 in Car
┌─────────────────┐                         ┌──────┐                 ┌──────────────┐
│ parking_pass_   │  1. Buy permit          │      │                 │              │
│ buyer.py        │────────────────>        │      │                 │   E-Ink      │
│                 │  2. Parse PDF           │      │                 │   Display    │
│                 │  3. Create permit.json  │      │                 │              │
│                 │  4. Push to GitHub ────>│  ○   │                 │              │
└─────────────────┘                         │      │<────────────────│  Fetches     │
                                            │      │  5. Download    │  permit.json │
    Manual or Scheduled                     └──────┘  permit.json    │  on boot or  │
    (Windows Task Scheduler)                                          │  button press│
                                                                      └──────────────┘
```

## Prerequisites

1. **Python 3.7+** installed
2. **Git** installed and configured
3. **GitHub repository** for storing permit.json (can use existing repo)
4. **ESP32** with e-ink display (Heltec Vision Master E290)
5. **GitHub Personal Access Token** (optional, for automated push authentication)

## Part 1: Desktop Script Setup

### 1. Install Dependencies

```bash
cd Toronto-Parking-Pass-Buyer
pip install -r requirements.txt
```

### 2. Configure Your Information

Edit the JSON files in the `config/` folder:

- `config/info_cars.json` - Your vehicle information
- `config/info_addresses.json` - Your address
- `config/info_payment_cards.json` - Payment card info

### 3. Set Up Environment Variables (Optional)

Create a `.env` file:

```env
asana_access_token=your_asana_token_here
```

### 4. Test the Script

Test PDF parsing with existing permit:
```bash
python parking_pass_buyer.py --parse-only --no-github
```

Test interactive mode:
```bash
python parking_pass_buyer.py
```

Test automated mode:
```bash
python parking_pass_buyer.py --vehicle 0 --card 0 --no-github
```

## Part 2: GitHub Setup

### 1. Choose a Repository

You can either:
- Use the existing `Toronto-Parking-Pass-Buyer` repo
- Create a new repo specifically for `permit.json`
- Use the `parking_pass_display` repo with a specific branch

**Recommended**: Use a separate branch in the `parking_pass_display` repo called `permit`.

### 2. Create the Branch (if needed)

```bash
cd path/to/parking_pass_display
git checkout -b permit
echo '{"permitNumber":"","plateNumber":"","validFrom":"","validTo":"","barcodeValue":"","barcodeLabel":""}' > permit.json
git add permit.json
git commit -m "Initialize permit data branch"
git push -u origin permit
```

### 3. Get the GitHub Raw URL

The URL format is:
```
https://raw.githubusercontent.com/USERNAME/REPO/BRANCH/permit.json
```

Example:
```
https://raw.githubusercontent.com/VisTechProjects/parking_pass_display/permit/permit.json
```

## Part 3: ESP32 Configuration

### 1. Configure WiFi and GitHub URL

Edit `parking_pass_display/src/wifi_config.h`:

```cpp
// ========== WIFI CREDENTIALS ==========
const char* WIFI_SSID_1 = "YourHomeWiFi";
const char* WIFI_PASS_1 = "your_password";

const char* WIFI_SSID_2 = "YourPhoneHotspot";
const char* WIFI_PASS_2 = "hotspot_password";

const char* WIFI_SSID_3 = "WorkWiFi";
const char* WIFI_PASS_3 = "";  // Empty for open network

// ========== SERVER SETTINGS ==========
const char* SERVER_URL = "https://raw.githubusercontent.com/VisTechProjects/parking_pass_display/permit/permit.json";
```

### 2. Flash the ESP32

```bash
cd parking_pass_display
pio run --target upload
```

### 3. Test the Display

The ESP32 will:
- Automatically check for permit updates on boot
- Allow manual update check with short button press (GPIO 21)
- Force update with long button press (3+ seconds)

## Part 4: Automated Scheduling (Windows)

### Option A: Windows Task Scheduler (Recommended)

1. **Open Task Scheduler**
   - Press `Win + R`, type `taskschd.msc`, press Enter

2. **Create New Task**
   - Click "Create Task" (not "Create Basic Task")
   - Name: "Toronto Parking Pass Auto-Purchase"
   - Description: "Automatically purchase weekly parking pass"
   - Check "Run whether user is logged on or not"
   - Check "Run with highest privileges"

3. **Set Trigger**
   - Click "Triggers" tab → "New"
   - Begin the task: "On a schedule"
   - Settings: Weekly
   - Choose day: Friday (day before permit expires)
   - Time: 11:00 PM
   - Check "Enabled"

4. **Set Action**
   - Click "Actions" tab → "New"
   - Action: "Start a program"
   - Program/script: `C:\Users\YourName\AppData\Local\Programs\Python\Python311\python.exe`
   - Add arguments: `parking_pass_buyer.py --vehicle 0 --card 0`
   - Start in: `C:\Users\YourName\Documents\Projects\Toronto-Parking-Pass-Buyer`

5. **Configure Conditions**
   - Click "Conditions" tab
   - Uncheck "Start the task only if the computer is on AC power"
   - Check "Wake the computer to run this task" (optional)

6. **Configure Settings**
   - Click "Settings" tab
   - Check "Allow task to be run on demand"
   - Check "Run task as soon as possible after a scheduled start is missed"
   - If task fails, restart every: 10 minutes
   - Attempt to restart up to: 3 times

### Option B: Manual Script

Create a batch file `buy_parking_pass.bat`:

```batch
@echo off
cd /d "C:\Users\YourName\Documents\Projects\Toronto-Parking-Pass-Buyer"
python parking_pass_buyer.py --vehicle 0 --card 0
pause
```

Double-click this file whenever you need to buy a permit.

## Usage Modes

### Interactive Mode (Manual)
```bash
python parking_pass_buyer.py
```
Prompts you to select vehicle and payment card.

### Automated Mode (For Scheduling)
```bash
python parking_pass_buyer.py --vehicle 0 --card 0
```
Uses first vehicle (index 0) and first card (index 0) without prompts.

### Parse Existing PDF Only
```bash
python parking_pass_buyer.py --parse-only
```
Just parses an existing PDF and creates permit.json (useful for testing).

### Skip Asana Task
```bash
python parking_pass_buyer.py --vehicle 0 --card 0 --no-asana
```

### Skip GitHub Push (Local Only)
```bash
python parking_pass_buyer.py --vehicle 0 --card 0 --no-github
```

## Workflow

### Normal Operation

1. **Scheduled Run** (Friday 11 PM):
   - Task Scheduler runs the script
   - Script opens Chrome and navigates to parking site
   - Fills in all information automatically
   - Waits for you to complete payment manually
   - Downloads the permit PDF
   - Parses PDF and creates permit.json
   - Commits and pushes to GitHub
   - Creates Asana reminder task for next week

2. **ESP32 Updates**:
   - ESP32 boots up and checks for new permit
   - Downloads updated permit.json from GitHub
   - Displays new permit on e-ink screen
   - OR press button to manually check for updates

### Troubleshooting

**Script fails to find PDF:**
- Make sure Chrome downloads to the script's directory
- Or manually move the PDF to the script folder
- Run `--parse-only` to test PDF parsing

**Git push fails:**
- Make sure you're in a git repository
- Configure git credentials: `git config credential.helper store`
- First push may require manual authentication

**ESP32 won't connect:**
- Check WiFi credentials in `wifi_config.h`
- Verify GitHub URL is correct
- Check serial monitor: `pio device monitor`

**Permit doesn't update:**
- ESP32 compares permit numbers - new permit must be different
- Use long press (3 sec) to force update regardless

## Command Line Arguments Reference

```
--vehicle INDEX      Vehicle index (0-based, see config/info_cars.json)
--card INDEX         Payment card index (0-based, see config/info_payment_cards.json)
--no-asana          Skip creating Asana task
--no-github         Skip GitHub commit and push
--parse-only        Only parse existing PDF without buying new permit
--help              Show help message
```

## Security Notes

1. **Never commit sensitive files to git**:
   - `config/` folder is in `.gitignore`
   - `.env` file is in `.gitignore`
   - `wifi_config.h` should not be pushed (use `.example` version)

2. **Payment Information**:
   - The script only fills cardholder name
   - You must manually enter card number, expiry, CVV
   - This is intentional for security

3. **GitHub Token** (if using API method):
   - Use Personal Access Token with minimal permissions
   - Only needs `repo` scope for private repos or `public_repo` for public

## Advanced Features

### Logging
All purchase attempts and GitHub pushes are logged to `permit_history.log`:
```
[2025-11-07 18:00:00] [INFO] Starting purchase for Honda Civic (ABC123)
[2025-11-07 18:02:15] [SUCCESS] Successfully pushed permit update: Update permit to T6151625
```

### Error Screenshots
When errors occur (like no parking space available), screenshots are automatically saved to `error_screenshots/`:
- `no_space_available_20251107_180215.png`
- Helps with debugging automation issues

### GitHub Token Authentication
For better security and automation, use a GitHub Personal Access Token:

1. Go to GitHub Settings → Developer Settings → Personal Access Tokens
2. Generate new token with `repo` scope
3. Add to `.env` file:
   ```
   GITHUB_TOKEN=ghp_your_token_here
   ```

The script will automatically use the token for authentication instead of stored credentials.

### Chrome Downloads
Chrome automatically downloads PDFs to the script folder (`Toronto-Parking-Pass-Buyer/`), making the workflow seamless.

## Next Steps

- [ ] Set up Task Scheduler for weekly runs
- [ ] Test the end-to-end workflow once
- [ ] (Optional) Add GITHUB_TOKEN to `.env` for token authentication
- [ ] Keep ESP32 powered in your car
- [ ] Enjoy never forgetting your parking permit again!
