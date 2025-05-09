import os
import sys
import json
import asana
from datetime import datetime, timedelta
from dotenv import load_dotenv

load_dotenv('.env')  # Load environment variables from .env file

# Auto-install missing dependencies
try:
    import selenium
    import webdriver_manager
except ImportError:
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "-r", "requirements.txt"])
    import selenium
    import webdriver_manager

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from webdriver_manager.chrome import ChromeDriverManager
import asana

# ====== Terminal Color Class ======
class bcolors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'
    
# Suppress TensorFlow logs and ChromeDriver logs
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

# ====== Chrome Setup ======
chrome_options = Options()
chrome_options.add_argument("--start-maximized")
chrome_options.add_argument("--disable-infobars")
chrome_options.add_argument("--disable-extensions")
chrome_options.add_argument("--log-level=3")
chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
# chrome_options.add_experimental_option("prefs", { #disable images for faster loading
#     "profile.managed_default_content_settings.images": 2,
#     "profile.managed_default_content_settings.fonts": 2
# })

# ====== Environment Variables ====== asana
asana_access_token = os.getenv("asana_access_token")

# ====== Helper Functions ======
def wait_for_xpath(driver, xpath, timeout=10, visible=False):
    try:
        wait = WebDriverWait(driver, timeout)
        if visible:
            return wait.until(EC.visibility_of_element_located((By.XPATH, xpath)))
        return wait.until(EC.element_to_be_clickable((By.XPATH, xpath)))
    except Exception as e:
        print(bcolors.FAIL + f"Timeout waiting for element: {xpath} — {e}" + bcolors.ENDC)
        return None

def fill_input_field(driver, xpath, value, label=None):
    input_box = wait_for_xpath(driver, xpath)
    if input_box:
        input_box.clear()
        input_box.send_keys(value)
        label_display = label or xpath
        # print(bcolors.OKGREEN + f"Filled {bcolors.OKCYAN}{label_display}{bcolors.OKGREEN} with {bcolors.HEADER}{value}{bcolors.ENDC}")
    else:
        print(bcolors.FAIL + f"❌ Failed to find input field for {label or xpath}" + bcolors.ENDC)

def select_dropdown_by_text(driver, xpath, text, label=None):
    element = wait_for_xpath(driver, xpath)
    if element:
        Select(element).select_by_visible_text(text)
        # print(bcolors.OKGREEN + f"Selected {bcolors.OKCYAN}{text}{bcolors.OKGREEN} from {label or xpath}" + bcolors.ENDC)
    else:
        print(bcolors.FAIL + f"Dropdown {label or xpath} not found." + bcolors.ENDC)

def click_checkbox_if_unchecked(driver, xpath, label=""):
    checkbox = wait_for_xpath(driver, xpath)
    if checkbox:
        if not checkbox.is_selected():
            try:
                checkbox.click()
            # print(bcolors.OKGREEN + "✅ Checkbox is now selected." + bcolors.ENDC)
            except:
                driver.execute_script("arguments[0].click();", checkbox)
                
        else:
            print(bcolors.OKBLUE + "Checkbox was already selected." + bcolors.ENDC)
    else:
        print(bcolors.FAIL + "❌ Checkbox not found." + bcolors.ENDC)

def add_task_to_asana(task_name, task_notes, due_date, asana_project_name, asana_section_name):
    
    print(bcolors.OKCYAN + "Creating Asana task..." + bcolors.ENDC)

    # Initialize Asana client
    client = asana.Client.access_token(asana_access_token)
    client.headers["Asana-Enable"] = "new_user_task_lists,new_goal_memberships"

    # Get current user
    me = client.users.me()
    assignee_gid = me["gid"]
    
    workspace_gid = me["workspaces"][0]["gid"]
    
    # Find the project
    projects = list(client.projects.get_projects({'workspace': workspace_gid, 'archived': False}))
    project = next((p for p in projects if p['name'].lower() == asana_project_name.lower()), None)
    if not project:
        raise Exception(f"❌ Project '{asana_project_name}' not found.")
    project_gid = project["gid"]
    
    # Look for the target section
    sections = list(client.sections.get_sections_for_project(project_gid))
    section = next((s for s in sections if s["name"].lower() == asana_section_name.lower()), None)

    # Create the section if it doesn't exist
    if not section:
        section = client.sections.create_in_project(project_gid, {
            "name": asana_section_name
        })
        print(f"➕ Created new section: {section['name']}")

    section_gid = section["gid"]

    # Create main task
    try:
        main_task = client.tasks.create_task({
            'name': task_name,
            'html_notes': task_notes,
            'due_on': due_date,
            'projects': [project_gid],
            'assignee': assignee_gid
        })
    except asana.error.InvalidRequestError as e:
        print(bcolors.FAIL + f"Failed to create task in Asana. Error: {e}" + bcolors.ENDC)
        return
    
    print(f"✅ Task created: {main_task['name']}")

    # Move task into the section
    client.tasks.add_project(main_task["gid"], {
        "project": project_gid,
        "section": section_gid
    })
    
    print(f"📂 Task added to section: {section['name']}")

# ====== Main Automation Workflow ======
def get_parking_pass():
    url = "https://secure.toronto.ca/wes/eTPP/welcome.do"

    # Load data
    with open('info_payment_cards.json', 'r') as file:
        info_payments = json.load(file)

    with open('info_addresses.json', 'r') as file:
        info_addresses = json.load(file)

    with open('info_cars.json', 'r') as file:
        info_cars = json.load(file)

    # Prompt for vehicle
    print("\n" + bcolors.WARNING + "Which vehicle would you like to get a parking permit for?" + bcolors.ENDC)
    for idx, vehicle in enumerate(info_cars):
        print(f"{bcolors.WARNING}{idx + 1}. {bcolors.OKCYAN}{vehicle['name']} - {vehicle['plate']}{bcolors.ENDC}")
        
    print(bcolors.HEADER + "\nChoose wisely... your parking fate depends on it." + bcolors.ENDC)

    while True:
        try:
            choice = int(input(bcolors.OKGREEN + "\nEnter the number for the vehicle: " + bcolors.ENDC))
            if 1 <= choice <= len(info_cars):
                selected_vehicle = info_cars[choice - 1]
                break
            else:
                print(bcolors.FAIL + "Please enter a valid number from the list" + bcolors.ENDC)
        except ValueError:
            print(bcolors.WARNING + "Please enter a number" + bcolors.ENDC)

    # Prompt for payment card
    print(bcolors.WARNING + "\nWhich card would you like to use to pay for parking permit?" + bcolors.ENDC)
    for idx, payment_card in enumerate(info_payments):
        print(f"{bcolors.WARNING}{idx + 1}. {bcolors.OKCYAN}{payment_card['card_name']}{bcolors.ENDC}")
    print(bcolors.HEADER + "\nRemember, it's not Monopoly money... or is it?" + bcolors.ENDC)

    while True:
        try:
            choice = int(input(bcolors.OKGREEN + "\nEnter the number for the card: " + bcolors.ENDC))
            if 1 <= choice <= len(info_payments):
                selected_payment_card = info_payments[choice - 1]
                break
            else:
                print(bcolors.FAIL + "Please enter a valid number from the list" + bcolors.ENDC)
        except ValueError:
            print(bcolors.WARNING + "Please enter a number" + bcolors.ENDC)

    print(bcolors.HEADER + f"\nGetting parking pass for: {bcolors.WARNING + selected_vehicle['name'] + bcolors.HEADER} with plate {bcolors.OKBLUE + selected_vehicle['plate'] + bcolors.ENDC}")
    print(bcolors.OKCYAN + "\nLet's hope this car is worth it..." + bcolors.ENDC)

    # Setup WebDriver
    service = Service(ChromeDriverManager().install(), log_path='NUL')
    driver = webdriver.Chrome(service=service, options=chrome_options)

    try:
        driver.get(url)

        # Agree to terms
        wait_for_xpath(driver, '//*[@id="maincontent"]/div[2]/div/div[1]/div/div[3]/div/button[2]').click()

        # ===== Page 1 =====
        page_1_field_xpaths = {
            "initals": '//*[@id="initial"]',
            "surname": '//*[@id="name"]',
            "steetNumber": '//*[@id="streetNumber"]',
            "streetName": '//*[@id="streetName"]',
            "permit_duration": '//*[@id="permitType"]',
            "permit_start_date": '//*[@id="datepicker"]',
        }

        page_1_data = {
            **info_addresses,
            "permit_start_date": (datetime.now() + timedelta(days=1)).strftime("%m/%d/%Y")
        }

        for field, xpath in page_1_field_xpaths.items():
            if field == "permit_duration":
                select_dropdown_by_text(driver, xpath, page_1_data[field], field)
            elif field == "permit_start_date":
                fill_input_field(driver, xpath, page_1_data[field], field)
            else:
                fill_input_field(driver, xpath, page_1_data[field], field)

        wait_for_xpath(driver, '//*[@id="maincontent"]/div[2]/div/div[1]/div/div[2]/div/div/div/div[3]/button').click()

        # ===== Page 2 =====
        wait_for_xpath(driver, '//*[@id="maincontent"]/div[2]/div/div[1]/div/div[2]/div/div/div/div/div[4]/button')
        try:
            WebDriverWait(driver, 10).until(
                EC.presence_of_element_located((By.XPATH, "//*[contains(text(), 'Space is available')]"))
            )
            print(bcolors.OKGREEN + "Space is available" + bcolors.ENDC)
            wait_for_xpath(driver, '//*[@id="maincontent"]/div[2]/div/div[1]/div/div[2]/div/div/div/div/div[4]/button').click()
        except:
            print(bcolors.FAIL + "Space is not available" + bcolors.ENDC)
            return

        # ===== Page 3 =====
        wait_for_xpath(driver, '//*[@id="maincontent"]/div[2]/div/div[1]/div/div[2]/div/div/div/div[3]/button')

        fill_input_field(driver, '//*[@id="licPltNum"]', selected_vehicle['plate'], "plate_number_1")
        fill_input_field(driver, '//*[@id="licPltNum2"]', selected_vehicle['plate'], "plate_number_2")
        select_dropdown_by_text(driver, '//*[@id="provCode"]', "ON - Ontario", "province")
        checkbox = wait_for_xpath(driver, '//*[@id="confirmVehicleSizeAndWeight"]', visible=True)
        if checkbox:
            click_checkbox_if_unchecked(driver, '//*[@id="confirmVehicleSizeAndWeight"]', "agreement checkbox")
        else:
            print(bcolors.FAIL + "Checkbox not found! Maybe it's hiding from responsibility, like you." + bcolors.ENDC)

        wait_for_xpath(driver, '//*[@id="maincontent"]/div[2]/div/div[1]/div/div[2]/div/div/div/div[3]/button').click()

        # ===== Payment Page (iframe) =====
        iframe = wait_for_xpath(driver, '//*[@id="monerisCheckout-Frame"]')
        driver.switch_to.frame(iframe)

        fill_input_field(driver, '//*[@id="cardholder"]', selected_payment_card["cardholder_name"], "cardholder_name")
        fill_input_field(driver, '//*[@id="pan"]', selected_payment_card["card_number"], "card_number")
        fill_input_field(driver, '//*[@id="expiry_date"]', selected_payment_card["card_expiry"], "card_expiry")
        fill_input_field(driver, '//*[@id="cvv"]', selected_payment_card["card_CVV"], "card_CVV")

        print(bcolors.OKCYAN + "\nWaiting on you to complete payment... " + bcolors.ENDC, end='')
        print(bcolors.HEADER + "\n\nPress " + bcolors.ENDC, end='')
        print(bcolors.WARNING + "enter" + bcolors.ENDC, end='')
        print(bcolors.HEADER + " when done..." + bcolors.ENDC, end='')
        input()

    finally:
        driver.quit()
        
        return selected_vehicle['name'], selected_vehicle['plate']

if __name__ == "__main__":
    vehical_name, vehical_plate = get_parking_pass()
    
    add_task_to_asana(
        task_name = f"Renew Parking Pass for {vehical_name} - {vehical_plate}",
        task_notes = """<body>
                Renew parking pass for this *crap vehicle*

                **Don't forget dumbass!**

                PS: If you forget again, future you will be very disappointed.
            </body>""",        
        due_date = (datetime.now() + timedelta(days=7)).strftime("%Y-%m-%d"),
        asana_project_name = "Parking Pass",
        asana_section_name = "Weekly Parking Pass todo"
    )
    
    print(bcolors.OKGREEN + bcolors.UNDERLINE + "Done" + bcolors.ENDC)
    
    print(bcolors.HEADER + bcolors.UNDERLINE + "\n\nWhy did I waste my life making this...\n\n" + bcolors.ENDC)
    print(bcolors.OKCYAN + "Remember: Parking is temporary, but sarcasm is forever." + bcolors.ENDC)