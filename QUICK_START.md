# Quick Start Guide

## What's New

Your parking pass buyer now has:
- **CLI arguments** for automated/scheduled runs
- **Automatic PDF parsing** after purchase
- **GitHub integration** to push permit.json
- **ESP32 compatibility** - display updates automatically

## Immediate Next Steps

### 1. Test the Parser (2 minutes)

```bash
python parking_pass_buyer.py --parse-only --no-github
```

This will parse your existing PDF and create `permit.json`. Check if it looks correct!

### 2. Set Up GitHub URL (5 minutes)

**Option A: Use existing repo with a branch**
```bash
# In parking_pass_display folder
git checkout -b permit
git add permit.json
git commit -m "Add permit data"
git push -u origin permit
```

**Option B: Push to Toronto-Parking-Pass-Buyer**
```bash
# In Toronto-Parking-Pass-Buyer folder
git add permit.json
git commit -m "Add permit data"
git push
```

Your GitHub raw URL will be:
```
https://raw.githubusercontent.com/VisTechProjects/REPO_NAME/BRANCH/permit.json
```

### 3. Update ESP32 Config (3 minutes)

Edit `parking_pass_display/src/wifi_config.h`:

```cpp
const char* SERVER_URL = "https://raw.githubusercontent.com/VisTechProjects/parking_pass_display/permit/permit.json";
```

Then flash:
```bash
cd parking_pass_display
pio run --target upload
```

### 4. Test End-to-End (Optional)

Run the buyer in test mode:
```bash
python parking_pass_buyer.py --vehicle 0 --card 0 --no-github
```

## Usage Examples

### Manual Purchase (Interactive)
```bash
python parking_pass_buyer.py
```
Asks which vehicle and card to use.

### Automated Purchase (For Scheduler)
```bash
python parking_pass_buyer.py --vehicle 0 --card 0
```
Uses first vehicle (0) and first card (0), pushes to GitHub automatically.

### Just Parse PDF
```bash
python parking_pass_buyer.py --parse-only
```
Parses most recent permit PDF and creates permit.json.

## Windows Task Scheduler (5 minutes)

1. Open Task Scheduler (`Win + R` → `taskschd.msc`)
2. Create Task → Name: "Parking Pass Auto-Buy"
3. Trigger: Weekly, Friday 11 PM
4. Action: Start program
   - Program: `python.exe` (full path)
   - Arguments: `parking_pass_buyer.py --vehicle 0 --card 0`
   - Start in: `C:\Users\Vis\Documents\Projects\Toronto-Parking-Pass-Buyer`
5. Save

## How It Works

```
1. Script buys permit (or you trigger it manually)
2. Chrome downloads PDF to folder
3. Script parses PDF automatically
4. Creates permit.json with all data
5. Commits and pushes to GitHub
6. ESP32 checks GitHub on boot or button press
7. Downloads new permit.json
8. Updates e-ink display
```

## Troubleshooting

**Q: Script can't find the PDF**
A: Make sure Chrome downloads to the project folder, or move PDF there manually.

**Q: Git push fails**
A: Run `git config credential.helper store` and authenticate once manually.

**Q: ESP32 won't update**
A: Long-press button (3 seconds) to force update.

**Q: Want to test without buying?**
A: Use `--parse-only` mode with an existing PDF.

## Full Documentation

See [SETUP.md](SETUP.md) for complete setup instructions.
