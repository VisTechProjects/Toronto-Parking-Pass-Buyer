# Toronto Parking Pass Buyer

Automates purchasing and reprinting parking permits from [Toronto Temp parking permits](https://secure.toronto.ca/wes/eTPP/welcome.do). Fully automated from form filling to payment to PDF download.

## Why I made this
Laziness and boredom, the fact I made this readme... says it all...

## Features
- **Buy new permits** - Fully automated: fills forms, submits payment, downloads PDF
- **Reprint existing permits** - Search by plate + card, download PDF
- **PDF parsing** - Extracts permit data (number, dates, barcode)
- **GitHub sync** - Pushes permit.json to separate repo for ESP32 display
- **Asana integration** - Creates reminder task to renew next week
- **Interactive or CLI mode** - Run with prompts or fully automated with flags

## Quick Start
1. Clone and install:
   ```bash
   git clone https://github.com/VisTechProjects/toronto_parking_pass_buyer.git
   cd toronto_parking_pass_buyer
   pip install -r requirements.txt
   ```

2. Copy `.env.example` to `.env` and add your tokens:
   ```bash
   cp .env.example .env
   ```
   Then edit `.env` with your actual tokens.

3. Add your info to the JSON files in `config/` folder (see below)

4. Run it:
   ```bash
   python parking_pass_buyer.py
   ```

## Prerequisites
- Python 3.8 or higher
- Google Chrome browser
- ChromeDriver (managed automatically by `webdriver_manager`)
- GitHub token (required for pushing permit.json)
- Asana access token (required for task creation)

## File Structure
```
├── parking_pass_buyer.py    # Main script
├── config/
│   ├── info_addresses.json  # Your address details
│   ├── info_payment_cards.json  # Payment card details
│   └── info_cars.json       # Vehicle details
├── .env                     # API tokens (create this)
├── permit.json              # Generated permit data
└── old_permits/             # Archived PDFs
```

## JSON Files: What to Modify
Before running the script, update the JSON files with your personal information.

### 1. **`info_addresses.json`**
This file contains your address details. Update the fields with your information:
```json
{
    "initals": "VK",                // Your initials
    "surname": "lastname",        // Your last name
    "steetNumber": "69",            // Your street number
    "streetName": "Some St",    // Your street name
    "permit_duration": "1 week"     // Permit duration ("1 week", 24 hours, 48 hours match drop down text)
}
```

### 2. **`info_payment_cards.json`**
This file contains your payment card details. Update the fields with your card information:
```json
[
    {
        "card_name": "Kurva Debit",             //Nickanme for card
        "cardholder_name": "Yo Mama", 
        "card_number": 1234567890123456,        
        "card_expiry": "0123",                  
        "card_CVV": 123                         
    }
]
```

### 3. **`info_cars.json`**
This file contains your vehicle details. Update the fields with your vehicle information:
```json
[
    {
        "name": "Hooptie",      // Name/nickname of the vehicle
        "plate": "ICUP"   // License plate number
    },
    {
        "name": "Lexi",
        "plate": "ABC123"
    },
    {
        "name": "Thomas",
        "plate": "ABC456"
    }
]
```

## Usage

### Interactive Mode
```bash
python parking_pass_buyer.py
```
Prompts you to choose: Buy new permit or Reprint existing, then select vehicle and card.

### CLI Mode (Automated)
```bash
# Buy new permit (vehicle index 1, card index 0)
python parking_pass_buyer.py --vehicle 1 --card 0

# Reprint existing permit
python parking_pass_buyer.py --reprint --vehicle 1 --card 0

# Skip Asana task creation
python parking_pass_buyer.py --vehicle 1 --card 0 --no-asana

# Skip GitHub push
python parking_pass_buyer.py --vehicle 1 --card 0 --no-github

# Parse existing PDF only (no browser)
python parking_pass_buyer.py --parse-only
```

### CLI Options
| Option | Description |
|--------|-------------|
| `--vehicle N` | Vehicle index (0-based) |
| `--card N` | Payment card index (0-based) |
| `--reprint` | Reprint existing permit instead of buying new |
| `--no-asana` | Skip Asana task creation |
| `--no-github` | Skip GitHub push |
| `--parse-only` | Only parse existing PDF, no browser |
| `--pdf PATH` | Specific PDF to parse (with --parse-only) |

## ESP32 Integration
This script pushes `permit.json` to a separate repo (`parking_pass_display`) which an ESP32 e-ink display reads to show the current permit barcode.

## Troubleshooting
- Ensure Google Chrome is installed and up-to-date
- If elements aren't found, the Toronto site may have changed - check XPaths
- Payment failures will fall back to manual mode

## License
This project is licensed under the MIT License. See the `LICENSE` file for details.

## Acknowledgments
- [Selenium](https://www.selenium.dev/) for browser automation
- [WebDriver Manager](https://github.com/SergeyPirogov/webdriver_manager) for managing ChromeDriver
- [Asana Python SDK](https://github.com/Asana/python-asana) for task management