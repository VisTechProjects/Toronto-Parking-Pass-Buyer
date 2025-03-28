# Toronto Parking Pass Buyer

This project automates the process of purchasing parking permits from [Toronto Temp parking permits](https://secure.toronto.ca/wes/eTPP/welcome.do) using python and selenium. It interacts with a web application to fill out forms, select dropdowns, and submit data based on user-provided information.

## Why I made this
Laziness and boredom, the fact I made this readme... says it all...

## Features
- Automatically fills in user details (e.g., name, address, permit duration).
- Selects dropdown options (e.g., province, permit type).
- Handles checkboxes and form submissions.
- Reads user data from JSON files for flexibility and reusability.

## Prerequisites
- Python 3.8 or higher
- Google Chrome browser
- ChromeDriver (managed automatically by `webdriver_manager`)

## Installation
1. Clone this repository:
   ```bash
   git clone https://github.com/VisTechProjects/toronto_parking_pass_buyer.git
   cd parking_pass_buyer
   ```

2. Install the required Python packages:
   ```bash
   pip install -r requirements.txt
   ```

## File Structure
- `parking_pass_buyer.py`: Main script for automating the parking pass purchase process.
- `info_addresses.json`: Contains user address details.
- `info_payment_cards.json`: Contains payment card details.
- `info_cars.json`: Contains vehicle details.

## JSON Files: What to Modify
Before running the script, update the JSON files with your personal information.

### 1. **`info_addresses.json`**
This file contains your address details. Update the fields with your information:
```json
{
    "initals": "VK",               // Your initials
    "surname": "lastname",         // Your last name
    "steetNumber": "69",           // Your street number
    "streetName": "Some St",       // Your street name
    "permit_duration": "1 week"    // Permit duration ("1 week", 24 hours, 48 hours match drop down text)
}
```

### 2. **`info_payment_cards.json`**
This file contains your payment card details. Update the fields with your card information:
```json
[
    {
        "card_name": "Kurva Debit",     //Nickanme for card
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
        "plate": "ICUP"         // License plate number
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
1. Update the JSON files with your information as described above.
2. Run the script:
   ```bash
   python parking_pass_buyer.py
   ```

3. Follow the prompts in the terminal:
   - Select a vehicle from the list.
   - Select a payment card from the list.

4. The script will automate the process of filling out the forms and submitting the parking permit application.

## Troubleshooting
- Ensure that Google Chrome is installed and up-to-date.
- If the script fails to locate elements, verify the XPaths in the script and update them as needed.

## License
This project is licensed under the MIT License. See the `LICENSE` file for details.

## Acknowledgments
- [Selenium](https://www.selenium.dev/) for browser automation.
- [WebDriver Manager](https://github.com/SergeyPirogov/webdriver_manager) for managing ChromeDriver.
