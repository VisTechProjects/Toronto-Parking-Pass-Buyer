"""
Toronto Temporary Parking Permit Parser

Extracts key information from Toronto parking permit PDFs:
- Permit Number (e.g., T6146330)
- Plate Number (e.g., CSEB187)
- Barcode Label (e.g., 00435)
- Valid From date
- Valid To date

Installation:
    pip install pdfplumber PyPDF2 requests

Usage:
    Place this script in the same folder as your permit PDF and run:
    python parse_parking_permit.py
    
    For GitHub integration, set environment variable:
    export GITHUB_TOKEN=your_github_token
"""

from pathlib import Path
from typing import Dict, Optional
import re
import json
import os
import base64

def extract_text_from_pdf(pdf_path: Path) -> str:
    """Extract text content from PDF using PyPDF2 or pdfplumber."""
    text = ""
    
    # Try pdfplumber first (better text extraction)
    try:
        import pdfplumber
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
        if text.strip():
            return text
    except Exception:
        pass
    
    # Fallback to PyPDF2
    try:
        import PyPDF2
        with open(pdf_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            for page in reader.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
    except Exception:
        pass
    
    return text

def parse_permit_data(text: str) -> Dict[str, Optional[str]]:
    """
    Parse permit information from PDF text.
    
    Expected fields:
    - Permit Number (e.g., T6146330)
    - Plate Number (e.g., CSEB187)
    - Barcode Label (e.g., 00435)
    - Valid From
    - Valid To
    """
    data = {
        "permit_number": None,
        "plate_number": None,
        "barcode_label": None,
        "valid_from": None,
        "valid_to": None,
    }
    
    # Permit Number patterns - look for "Permit no.:" specifically
    permit_patterns = [
        r"Permit\s+no\.?\s*:\s*([A-Z0-9]+)",  # Match "Permit no.: T6146330"
        r"Permit\s+number\s*:\s*([A-Z0-9]+)",
    ]
    
    # License Plate patterns - look for "Plate no.:" specifically
    plate_patterns = [
        r"Plate\s+no\.?\s*:\s*([A-Z0-9]+)",  # Match "Plate no.: CSEB187"
        r"(?:License|Licence)\s+plate\s*:\s*([A-Z0-9]+)",
    ]
    
    # Barcode Label pattern - this is the 5-digit code shown on the permit
    # It appears as a standalone number on its own line
    barcode_patterns = [
        r"(?:^|\n)\s*(\d{5})\s*(?:\n|$)",  # Match standalone 5-digit number on its own line
        r"(\d{5})\s*\n[^\n]*Permit\s+no",  # Match 5-digit before "Permit no." with any text between
    ]
    
    # Date patterns for "Valid from:" and "Valid to:"
    date_patterns = [
        r"Valid\s+from\s*:\s*([A-Z][a-z]+\s+\d{1,2},?\s+\d{4}(?:\s+at\s+\d{1,2}:\d{2}\s*(?:AM|PM))?)",
        r"Valid\s+from\s*:\s*(\d{1,2}/\d{1,2}/\d{4}(?:\s+\d{1,2}:\d{2}\s*(?:AM|PM)?)?)",
    ]
    
    valid_to_patterns = [
        r"Valid\s+to\s*:\s*([A-Z][a-z]+\s+\d{1,2},?\s+\d{4}(?:\s+at\s+\d{1,2}:\d{2}\s*(?:AM|PM))?)",
        r"Valid\s+to\s*:\s*(\d{1,2}/\d{1,2}/\d{4}(?:\s+\d{1,2}:\d{2}\s*(?:AM|PM)?)?)",
    ]
    
    # Search for permit number
    for pattern in permit_patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            data["permit_number"] = match.group(1).strip()
            break
    
    # Search for plate number
    for pattern in plate_patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            data["plate_number"] = match.group(1).strip().upper()
            break
    
    # Search for barcode label
    for pattern in barcode_patterns:
        match = re.search(pattern, text, re.IGNORECASE | re.DOTALL)
        if match:
            data["barcode_label"] = match.group(1).strip()
            break
    
    # Search for valid from date
    for pattern in date_patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            data["valid_from"] = match.group(1).strip()
            break
    
    # Search for valid to date
    for pattern in valid_to_patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            data["valid_to"] = match.group(1).strip()
            break
    
    return data

def update_permit_json(folder: Path, permit_data: Dict[str, Optional[str]]) -> None:
    """Update the permit.json file locally and push to GitHub."""
    json_file = folder / "permit.json"
    
    # Parse dates to match old format (e.g., "Oct 20, 2025: 01:08")
    valid_from = permit_data["valid_from"]
    valid_to = permit_data["valid_to"]
    
    # Convert "Oct 25, 2025 at 12:00 AM" to "Oct 25, 2025: 12:00"
    if valid_from and " at " in valid_from:
        # Split and reformat
        date_part, time_part = valid_from.split(" at ")
        # Remove AM/PM and just keep time
        time_clean = time_part.replace(" AM", "").replace(" PM", "")
        valid_from = f"{date_part}: {time_clean}"
    
    if valid_to and " at " in valid_to:
        date_part, time_part = valid_to.split(" at ")
        time_clean = time_part.replace(" AM", "").replace(" PM", "")
        valid_to = f"{date_part}: {time_clean}"
    
    # Create JSON structure matching the OLD GitHub format
    json_data = {
        "permitNumber": permit_data["permit_number"],
        "plateNumber": permit_data["plate_number"],
        "validFrom": valid_from,
        "validTo": valid_to,
        "barcodeValue": permit_data["permit_number"][1:] if permit_data["permit_number"] else None,  # Remove first letter (T)
        "barcodeLabel": permit_data["barcode_label"]
    }
    
    # Write to local file with proper formatting (2-space indent to match old file)
    try:
        with open(json_file, 'w') as f:
            json.dump(json_data, f, indent=2)
        print(f"\n✅ Updated local {json_file.name}")
        print(f"   Path: {json_file}")
    except Exception as e:
        print(f"\n❌ Failed to write local JSON file: {e}")
        return
    
    # Update GitHub if token is available
    github_token = os.getenv("GITHUB_TOKEN")
    if github_token:
        update_github_file(json_data, github_token, permit_data["permit_number"])
    else:
        print(f"\n💡 To auto-update GitHub, set GITHUB_TOKEN environment variable")
        print(f"   export GITHUB_TOKEN=your_github_personal_access_token")

def update_github_file(json_data: dict, github_token: str, permit_number: str) -> None:
    """Update the permit.json file on GitHub using the API."""
    try:
        import requests
    except ImportError:
        print("\n⚠️  'requests' library not installed. Run: pip install requests")
        return
    
    # GitHub API details
    owner = "VisTechProjects"
    repo = "parking_pass_display"
    branch = "permit"
    file_path = "permit.json"
    
    api_url = f"https://api.github.com/repos/{owner}/{repo}/contents/{file_path}"
    
    headers = {
        "Authorization": f"token {github_token}",
        "Accept": "application/vnd.github.v3+json"
    }
    
    try:
        # Get current file to get its SHA
        response = requests.get(f"{api_url}?ref={branch}", headers=headers)
        
        if response.status_code == 200:
            current_file = response.json()
            sha = current_file['sha']
        else:
            print(f"\n⚠️  Could not fetch current file from GitHub: {response.status_code}")
            return
        
        # Prepare update
        content = json.dumps(json_data, indent=2)
        encoded_content = base64.b64encode(content.encode()).decode()
        
        update_data = {
            "message": f"Update permit to {permit_number}",
            "content": encoded_content,
            "sha": sha,
            "branch": branch
        }
        
        # Update file
        response = requests.put(api_url, headers=headers, json=update_data)
        
        if response.status_code in [200, 201]:
            print(f"\n🚀 Successfully pushed to GitHub!")
            print(f"   Branch: {branch}")
            print(f"   https://github.com/{owner}/{repo}/blob/{branch}/{file_path}")
        else:
            print(f"\n❌ Failed to update GitHub: {response.status_code}")
            print(f"   {response.json().get('message', 'Unknown error')}")
            
    except Exception as e:
        print(f"\n❌ Error updating GitHub: {e}")

def find_permit_pdf(folder: Path) -> Optional[Path]:
    """Find a PDF file that looks like a Toronto parking permit receipt."""
    # Look for specific filename patterns
    patterns = [
        "*Temporary Parking Permit*.pdf",
        "*Parking Permit Receipt*.pdf",
        "*permit*.pdf",
        "*receipt*.pdf",
    ]
    
    for pattern in patterns:
        matches = list(folder.glob(pattern))
        if matches:
            # Return the most recent one
            return max(matches, key=lambda f: f.stat().st_mtime)
    
    # If no pattern match, return most recent PDF
    pdfs = list(folder.glob("*.pdf"))
    if pdfs:
        return max(pdfs, key=lambda f: f.stat().st_mtime)
    
    return None

def main():
    here = Path(__file__).resolve().parent
    permit_pdf = find_permit_pdf(here)
    
    if not permit_pdf:
        print("❌ No parking permit PDF found in the current folder.")
        print("   Expected filename pattern: 'Temporary Parking Permit*.pdf'")
        return
    
    print(f"\n{'='*60}")
    print(f"📄 Scanning: {permit_pdf.name}")
    print(f"{'='*60}\n")
    
    # Extract text from PDF
    print("📖 Extracting text from PDF...")
    text = extract_text_from_pdf(permit_pdf)
    
    if not text.strip():
        print("⚠️  No text could be extracted from PDF")
        return
    
    # Parse permit data
    print("🔍 Parsing permit information...\n")
    permit_data = parse_permit_data(text)
    
    # Display results
    print(f"\n{'='*60}")
    print("📋 PERMIT INFORMATION")
    print(f"{'='*60}\n")
    
    print(f"Permit Number:  {permit_data['permit_number'] or '❌ Not found'}")
    print(f"Plate Number:   {permit_data['plate_number'] or '❌ Not found'}")
    print(f"Barcode Label:  {permit_data['barcode_label'] or '❌ Not found'}")
    print(f"Valid From:     {permit_data['valid_from'] or '❌ Not found'}")
    print(f"Valid To:       {permit_data['valid_to'] or '❌ Not found'}")
    
    print(f"\n{'='*60}\n")
    
    # Check if we got all the data
    missing = [k for k, v in permit_data.items() if v is None]
    if missing:
        print("⚠️  Some information could not be extracted.")
        print("\n💡 Debug: Full extracted text:")
        print("-" * 60)
        print(text[:1000])  # Print first 1000 chars
        if len(text) > 1000:
            print(f"\n... ({len(text) - 1000} more characters)")
        print("-" * 60)
    else:
        # All data extracted successfully - update JSON file
        update_permit_json(here, permit_data)

if __name__ == "__main__":
    main()