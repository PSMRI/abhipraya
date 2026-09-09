"""Build the public facility master from the completed facility-code workbook."""

from __future__ import annotations

import json
import sys
from pathlib import Path

import openpyxl


ROOT = Path(__file__).parents[1]
TARGET = ROOT / "api" / "masters" / "facilityCodes.json"
EXPECTED_HEADERS = (
    "stateCode", "districtName", "Blockname", "facilityName", "facilityType",
    "HFR ID", "facilityNIN", "districtCode", "blockCode", "facilityLat", "facilityLong",
)


def text(value: object) -> str:
    return "" if value is None else str(value).strip()


def main(source: Path) -> None:
    workbook = openpyxl.load_workbook(source, read_only=True, data_only=True)
    if "Sheet1" not in workbook.sheetnames:
        raise ValueError("The workbook must contain Sheet1.")
    rows = workbook["Sheet1"].values
    headers = tuple(text(value) for value in next(rows))
    if headers != EXPECTED_HEADERS:
        raise ValueError(f"Unexpected Sheet1 columns: {headers!r}")

    existing = json.loads(TARGET.read_text(encoding="utf-8"))
    existing_by_nin = {text(row.get("facilityNIN")): row for row in existing if text(row.get("facilityNIN"))}
    facilities: list[dict[str, object]] = []
    seen: set[str] = set()
    for row_number, row in enumerate(rows, start=2):
        values = dict(zip(EXPECTED_HEADERS, row))
        nin = text(values["facilityNIN"])
        # Some source IDs are written as NIN1234567890. The database and QR
        # flow use the numeric portion as the facility NIN.
        if nin.upper().startswith("NIN") and nin[3:].isdigit():
            nin = nin[3:]
        if not nin or nin in seen:
            raise ValueError(f"Invalid or duplicate facilityNIN at workbook row {row_number}.")
        seen.add(nin)
        previous = existing_by_nin.get(nin, {})
        block = text(values["Blockname"])
        district = text(values["districtName"])
        facilities.append({
            "langCode": 1,
            "facilityNIN": int(nin),
            "facilityName": text(values["facilityName"]),
            "facilityAddress": text(previous.get("facilityAddress")) or f"{block} BLOCK, {district} DISTRICT",
            "facilityPincode": text(previous.get("facilityPincode")),
            "facilityLat": text(values["facilityLat"]),
            "facilityLong": text(values["facilityLong"]),
            "facilityType": text(values["facilityType"]),
            "stateCode": text(values["stateCode"]),
            "districtCode": text(values["districtCode"]),
            "districtName": district,
            "blockCode": text(values["blockCode"]),
        })

    if not facilities:
        raise ValueError("No facility rows were found.")
    TARGET.write_text(json.dumps(facilities, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Imported {len(facilities)} facilities; retained pin codes for {sum(bool(text(existing_by_nin.get(text(row['facilityNIN']), {}).get('facilityPincode'))) for row in facilities)} matching NINs.")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: import_facility_codes_from_workbook.py WORKBOOK_PATH")
    main(Path(sys.argv[1]))
