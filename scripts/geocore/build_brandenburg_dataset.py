#!/usr/bin/env python3
"""Build the read-only Brandenburg GeoCore reference dataset.

The script reads the application SQLite database in immutable/read-only mode,
the existing city audit, project source files, and an official GV-ISys XLSX.
It writes only derived files below storage/app/geocore/brandenburg.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import os
import re
import sqlite3
import sys
import unicodedata
import zipfile
from collections import Counter, defaultdict
from difflib import SequenceMatcher
from pathlib import Path
from xml.etree import ElementTree as ET


SOURCE_NAME = "Destatis GV-ISys"
SOURCE_DATE = "2026-06-30"
REPORT_DATE = "2026-07-19"
SOURCE_URL = "https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/_inhalt.html"
EXPECTED_CITY_COUNT = 257
EXPECTED_FACILITY_COUNT = 1557
EXPECTED_MUNICIPALITY_COUNT = 413
EXPECTED_LANDKREIS_COUNT = 14
EXPECTED_KREISFREIE_COUNT = 4

MATCH_STATUSES = {"exact", "normalized", "partial", "locality", "ambiguous", "unmatched"}
MATCH_METHODS = {
    "exact_official_name",
    "normalized_official_name",
    "unique_partial_candidate",
    "audit_existing_candidate",
    "locality_lookup",
    "manual_required",
    "no_candidate",
}
CONFIDENCE_VALUES = {"high", "medium", "low", "none"}

OFFICIAL_FIELDS = [
    "country_code", "country_name", "state_ags", "state_name", "district_ags",
    "district_name", "district_type", "municipality_ags", "municipality_name_official",
    "municipality_name_normalized", "municipality_type", "municipality_slug",
    "administrative_seat", "postal_code_official", "source_name", "source_date", "source_url",
]

MAPPING_FIELDS = [
    "current_city_id", "current_city_name", "current_city_slug", "current_state",
    "current_state_slug", "current_postal_codes", "facility_count",
    "official_municipality_ags", "official_municipality_name", "official_district_ags",
    "official_district_name", "official_district_type", "match_status", "match_method",
    "confidence", "candidate_count", "candidate_1_ags", "candidate_1_name",
    "candidate_1_district", "candidate_2_ags", "candidate_2_name",
    "candidate_2_district", "suspected_locality_name", "notes", "requires_manual_review",
]

MANUAL_FIELDS = [
    "current_city_id", "current_city_name", "current_city_slug", "current_postal_codes",
    "facility_count", "match_status", "best_candidate_ags", "best_candidate_name",
    "best_candidate_district", "alternative_candidates", "suspected_reason",
    "recommended_manual_source", "verification_notes",
]


def fail(message: str) -> None:
    raise RuntimeError(message)


def clean(value: object) -> str:
    return "" if value is None else str(value).strip()


def canonical_official_name(value: str) -> str:
    return re.sub(r",\s*(?:Stadt|Landeshauptstadt)$", "", value).strip()


def transliterate_german(value: str) -> str:
    return (
        value.replace("Ä", "Ae").replace("Ö", "Oe").replace("Ü", "Ue")
        .replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")
    )


def normalized_name(value: str) -> str:
    value = transliterate_german(value).casefold()
    value = unicodedata.normalize("NFKD", value)
    value = "".join(char for char in value if not unicodedata.combining(char))
    return re.sub(r"[^a-z0-9]+", " ", value).strip()


def compact_name(value: str) -> str:
    return normalized_name(value).replace(" ", "")


def slugify(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", normalized_name(value)).strip("-") or "gemeinde"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def column_index(reference: str) -> int:
    letters = re.match(r"[A-Z]+", reference)
    if not letters:
        fail(f"Invalid XLSX cell reference: {reference}")
    result = 0
    for char in letters.group(0):
        result = result * 26 + ord(char) - ord("A") + 1
    return result - 1


def read_xlsx_rows(path: Path, sheet_name: str) -> list[list[str]]:
    """Read XLSX values with the Python standard library only."""
    ns_main = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    ns_rel = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    ns_pkg_rel = "http://schemas.openxmlformats.org/package/2006/relationships"
    with zipfile.ZipFile(path) as archive:
        shared_strings: list[str] = []
        if "xl/sharedStrings.xml" in archive.namelist():
            shared_root = ET.fromstring(archive.read("xl/sharedStrings.xml"))
            for item in shared_root.findall(f"{{{ns_main}}}si"):
                shared_strings.append("".join(node.text or "" for node in item.iter(f"{{{ns_main}}}t")))

        workbook = ET.fromstring(archive.read("xl/workbook.xml"))
        relation_id = None
        for sheet in workbook.findall(f".//{{{ns_main}}}sheet"):
            if sheet.attrib.get("name") == sheet_name:
                relation_id = sheet.attrib.get(f"{{{ns_rel}}}id")
                break
        if not relation_id:
            fail(f"Worksheet not found in GV-ISys XLSX: {sheet_name}")

        relationships = ET.fromstring(archive.read("xl/_rels/workbook.xml.rels"))
        target = None
        for relationship in relationships.findall(f"{{{ns_pkg_rel}}}Relationship"):
            if relationship.attrib.get("Id") == relation_id:
                target = relationship.attrib.get("Target")
                break
        if not target:
            fail(f"Worksheet relationship not found: {relation_id}")
        target = target.replace("\\", "/").lstrip("/")
        worksheet_path = target if target.startswith("xl/") else f"xl/{target}"
        worksheet = ET.fromstring(archive.read(worksheet_path))

        rows: list[list[str]] = []
        for row in worksheet.findall(f".//{{{ns_main}}}row"):
            values: dict[int, str] = {}
            max_index = -1
            for cell in row.findall(f"{{{ns_main}}}c"):
                index = column_index(cell.attrib.get("r", ""))
                max_index = max(max_index, index)
                cell_type = cell.attrib.get("t")
                if cell_type == "inlineStr":
                    value = "".join(node.text or "" for node in cell.iter(f"{{{ns_main}}}t"))
                else:
                    value_node = cell.find(f"{{{ns_main}}}v")
                    raw = "" if value_node is None else (value_node.text or "")
                    value = shared_strings[int(raw)] if cell_type == "s" and raw else raw
                values[index] = value
            rows.append([values.get(index, "") for index in range(max_index + 1)] if max_index >= 0 else [])
        return rows


def cell(row: list[str], index: int) -> str:
    return clean(row[index]) if index < len(row) else ""


def read_official_municipalities(path: Path) -> tuple[list[dict[str, str]], dict[str, dict[str, str]]]:
    rows = read_xlsx_rows(path, "Onlineprodukt_Gemeinden30062026")
    title = " ".join(cell(row, 0) for row in rows[:6])
    if "30.06.2026" not in title:
        fail("GV-ISys workbook does not identify the expected 30.06.2026 data date")

    districts: dict[str, dict[str, str]] = {}
    municipality_source_rows: list[dict[str, str]] = []
    for row in rows[6:]:
        record_type = cell(row, 0)
        land, rb, kreis = cell(row, 2), cell(row, 3), cell(row, 4)
        if land != "12":
            continue
        district_ags = f"{land}{rb}{kreis}"
        if record_type == "40" and kreis:
            districts[district_ags] = {"district_ags": district_ags, "district_name": cell(row, 7)}
        if record_type != "60":
            continue
        gem = cell(row, 6).zfill(3)
        municipality_source_rows.append({
            "district_ags": district_ags,
            "municipality_ags": f"{land}{rb}{kreis}{gem}",
            "municipality_name_official": cell(row, 7),
            "postal_code_official": cell(row, 13),
            "gem": gem,
        })

    if len(municipality_source_rows) != EXPECTED_MUNICIPALITY_COUNT:
        fail(f"Expected {EXPECTED_MUNICIPALITY_COUNT} official municipalities, found {len(municipality_source_rows)}")

    base_slugs = [slugify(canonical_official_name(row["municipality_name_official"])) for row in municipality_source_rows]
    slug_counts = Counter(base_slugs)
    official: list[dict[str, str]] = []
    for source, base_slug in zip(municipality_source_rows, base_slugs, strict=True):
        district = districts.get(source["district_ags"])
        if not district:
            fail(f"Missing official district parent for AGS {source['municipality_ags']}")
        is_kreisfreie = source["gem"] == "000"
        district_type = "kreisfreie_stadt" if is_kreisfreie else "landkreis"
        official_name = source["municipality_name_official"]
        municipality_type = "kreisfreie_stadt" if is_kreisfreie else ("stadt" if re.search(r",\s*Stadt$", official_name) else "gemeinde")
        municipality_slug = base_slug if slug_counts[base_slug] == 1 else f"{base_slug}-{source['municipality_ags']}"
        official.append({
            "country_code": "DE",
            "country_name": "Deutschland",
            "state_ags": "12",
            "state_name": "Brandenburg",
            "district_ags": source["district_ags"],
            "district_name": district["district_name"],
            "district_type": district_type,
            "municipality_ags": source["municipality_ags"],
            "municipality_name_official": official_name,
            "municipality_name_normalized": normalized_name(canonical_official_name(official_name)),
            "municipality_type": municipality_type,
            "municipality_slug": municipality_slug,
            # The quarterly GV extract supplies the administrative-seat PLZ but
            # does not expose a separate authoritative administrative-seat name.
            "administrative_seat": "",
            "postal_code_official": source["postal_code_official"],
            "source_name": SOURCE_NAME,
            "source_date": SOURCE_DATE,
            "source_url": SOURCE_URL,
        })
    official.sort(key=lambda row: row["municipality_ags"])
    return official, districts


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def split_values(value: str) -> list[str]:
    return [item.strip() for item in value.split(" | ") if item.strip()]


def rank_candidates(stored_name: str, candidates: list[dict[str, str]]) -> list[dict[str, str]]:
    stored = compact_name(stored_name)

    def score(candidate: dict[str, str]) -> tuple[float, str]:
        official = compact_name(canonical_official_name(candidate["municipality_name_official"]))
        if stored == official:
            similarity = 100.0
        elif official.startswith(stored) or stored.startswith(official):
            similarity = 85.0
        elif stored in official or official in stored:
            similarity = 75.0
        else:
            similarity = SequenceMatcher(None, stored, official).ratio() * 60.0
        return (-similarity, candidate["municipality_ags"])

    unique = list({row["municipality_ags"]: row for row in candidates}.values())
    return sorted(unique, key=score)


def build_mapping(
    cities: list[sqlite3.Row],
    facilities_by_city: dict[int, list[sqlite3.Row]],
    audit_by_id: dict[str, dict[str, str]],
    official_by_ags: dict[str, dict[str, str]],
    official_postal_index: dict[str, list[dict[str, str]]],
) -> list[dict[str, str]]:
    mappings: list[dict[str, str]] = []
    for city in cities:
        city_id = str(city["id"])
        audit = audit_by_id.get(city_id)
        if not audit:
            fail(f"City {city_id} is missing from the existing audit")
        city_facilities = facilities_by_city[int(city["id"])]
        postal_codes = sorted({clean(item["postal_code"]) for item in city_facilities})
        if audit["stored_city_name"] != city["name"] or audit["city_slug"] != city["slug"]:
            fail(f"Audit/database identity mismatch for city {city_id}")
        if int(audit["facility_count"]) != len(city_facilities) or split_values(audit["distinct_postal_codes"]) != postal_codes:
            fail(f"Audit/database facility aggregate mismatch for city {city_id}")

        audit_method = audit["official_match_method"]
        official_candidate_ags = split_values(audit["official_ags_candidates"])
        postal_candidates = []
        for postal_code in postal_codes:
            postal_candidates.extend(official_postal_index.get(postal_code, []))
        postal_candidates = list({row["municipality_ags"]: row for row in postal_candidates}.values())

        assigned: dict[str, str] | None = None
        candidates: list[dict[str, str]] = []
        suspected_locality = audit["suspected_ortsteil"] == "yes"
        notes: list[str] = []

        if audit_method == "exact_official_name" and len(official_candidate_ags) == 1:
            assigned = official_by_ags.get(official_candidate_ags[0])
            if not assigned:
                fail(f"Unknown exact official candidate for city {city_id}")
            status, method, confidence, review = "exact", "exact_official_name", "high", False
            candidates = [assigned]
        elif audit_method in {"normalized_official_name", "normalized_name_and_postal_code"} and len(official_candidate_ags) == 1:
            assigned = official_by_ags.get(official_candidate_ags[0])
            if not assigned:
                fail(f"Unknown normalized official candidate for city {city_id}")
            status, method, confidence, review = "normalized", "normalized_official_name", "high", False
            candidates = [assigned]
            if audit_method == "normalized_name_and_postal_code":
                notes.append("Official name candidate is unique only for the combined normalized-name and official-seat-PLZ evidence; PLZ was not used alone.")
        elif audit_method == "official_name_prefix_and_postal_candidate":
            candidates = [official_by_ags[ags] for ags in official_candidate_ags if ags in official_by_ags]
            status, method, confidence, review = "partial", "unique_partial_candidate", "medium", True
            notes.append("Short/prefix name candidate from the existing audit; no AGS assigned until official manual confirmation.")
        elif audit_method in {"ambiguous_official_name_prefix", "ambiguous_normalized_name"}:
            candidates = [official_by_ags[ags] for ags in official_candidate_ags if ags in official_by_ags]
            status, method, confidence, review = "ambiguous", "manual_required", "low", True
            notes.append("Multiple official name candidates remain; no AGS assigned.")
        elif suspected_locality:
            candidates = rank_candidates(city["name"], postal_candidates)
            status, method, confidence, review = "locality", "manual_required", ("low" if candidates else "none"), True
            notes.append("Stored city appears to be a locality/Ortsteil or source locality; postal candidates are discovery hints only.")
        else:
            candidates = rank_candidates(city["name"], postal_candidates)
            status, method, confidence, review = "unmatched", "no_candidate", "none", True
            notes.append("No authoritative municipality match; no AGS assigned.")

        candidates = list({row["municipality_ags"]: row for row in candidates}.values())
        candidate_1 = candidates[0] if candidates else None
        candidate_2 = candidates[1] if len(candidates) > 1 else None
        mappings.append({
            "current_city_id": city_id,
            "current_city_name": city["name"],
            "current_city_slug": city["slug"],
            "current_state": city["state"],
            "current_state_slug": city["state_slug"],
            "current_postal_codes": " | ".join(postal_codes),
            "facility_count": str(len(city_facilities)),
            "official_municipality_ags": assigned["municipality_ags"] if assigned else "",
            "official_municipality_name": assigned["municipality_name_official"] if assigned else "",
            "official_district_ags": assigned["district_ags"] if assigned else "",
            "official_district_name": assigned["district_name"] if assigned else "",
            "official_district_type": assigned["district_type"] if assigned else "",
            "match_status": status,
            "match_method": method,
            "confidence": confidence,
            "candidate_count": str(len(candidates)),
            "candidate_1_ags": candidate_1["municipality_ags"] if candidate_1 else "",
            "candidate_1_name": candidate_1["municipality_name_official"] if candidate_1 else "",
            "candidate_1_district": candidate_1["district_name"] if candidate_1 else "",
            "candidate_2_ags": candidate_2["municipality_ags"] if candidate_2 else "",
            "candidate_2_name": candidate_2["municipality_name_official"] if candidate_2 else "",
            "candidate_2_district": candidate_2["district_name"] if candidate_2 else "",
            "suspected_locality_name": "true" if suspected_locality else "false",
            "notes": " ".join(notes),
            "requires_manual_review": "true" if review else "false",
        })
    return mappings


def build_manual_rows(
    mappings: list[dict[str, str]],
    official_by_ags: dict[str, dict[str, str]],
    official_postal_index: dict[str, list[dict[str, str]]],
) -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    for mapping in mappings:
        if mapping["requires_manual_review"] != "true":
            continue
        if mapping["match_status"] in {"locality", "unmatched"}:
            candidates = rank_candidates(
                mapping["current_city_name"],
                [
                    official
                    for postal_code in split_values(mapping["current_postal_codes"])
                    for official in official_postal_index.get(postal_code, [])
                ],
            )
        else:
            candidates = []
            for key in ("candidate_1_ags", "candidate_2_ags"):
                if mapping[key] and mapping[key] in official_by_ags:
                    candidates.append(official_by_ags[mapping[key]])
        best = candidates[0] if candidates else None
        alternatives = " | ".join(
            f"{row['municipality_ags']} {row['municipality_name_official']} [{row['district_name']}]"
            for row in candidates[1:]
        )
        reason = {
            "partial": "Stored name is only a partial/short official-name candidate.",
            "locality": "Stored name appears to be an Ortsteil or non-independent address locality.",
            "ambiguous": "More than one official municipality candidate remains.",
            "unmatched": "No official municipality candidate is confirmed.",
        }.get(mapping["match_status"], "Manual verification is required.")
        rows.append({
            "current_city_id": mapping["current_city_id"],
            "current_city_name": mapping["current_city_name"],
            "current_city_slug": mapping["current_city_slug"],
            "current_postal_codes": mapping["current_postal_codes"],
            "facility_count": mapping["facility_count"],
            "match_status": mapping["match_status"],
            "best_candidate_ags": best["municipality_ags"] if best else "",
            "best_candidate_name": best["municipality_name_official"] if best else "",
            "best_candidate_district": best["district_name"] if best else "",
            "alternative_candidates": alternatives,
            "suspected_reason": reason,
            "recommended_manual_source": "Brandenburg Gemeinde- und Ortsteilverzeichnis; official Gemeinde/Amt/Landkreis website",
            "verification_notes": "Do not assign AGS from PLZ or name similarity alone; record the official source and decision.",
        })
    return rows


def validate(
    official: list[dict[str, str]],
    mappings: list[dict[str, str]],
    manual_rows: list[dict[str, str]],
) -> dict[str, Counter[str]]:
    if len(mappings) != EXPECTED_CITY_COUNT:
        fail(f"Expected {EXPECTED_CITY_COUNT} mapping rows, found {len(mappings)}")
    if len({row["current_city_id"] for row in mappings}) != EXPECTED_CITY_COUNT:
        fail("current_city_id is not unique")
    if len({row["current_city_slug"] for row in mappings}) != EXPECTED_CITY_COUNT:
        fail("current_city_slug is not unique")
    if sum(int(row["facility_count"]) for row in mappings) != EXPECTED_FACILITY_COUNT:
        fail("facility_count sum is not 1557")
    if len(official) != EXPECTED_MUNICIPALITY_COUNT:
        fail("Official municipality count is invalid")
    if len({row["municipality_ags"] for row in official}) != len(official):
        fail("municipality_ags is not unique")
    if len({row["municipality_slug"] for row in official}) != len(official):
        fail("municipality_slug is not unique")
    if any(not re.fullmatch(r"\d{8}", row["municipality_ags"]) for row in official):
        fail("Invalid municipality_ags format")
    if any(not re.fullmatch(r"\d{5}", row["district_ags"]) for row in official):
        fail("Invalid district_ags format")
    if any(row["district_type"] not in {"landkreis", "kreisfreie_stadt"} for row in official):
        fail("Unknown district_type")

    official_by_ags = {row["municipality_ags"]: row for row in official}
    for row in mappings:
        if row["match_status"] not in MATCH_STATUSES or not row["match_status"]:
            fail(f"Unknown/empty match_status for city {row['current_city_id']}")
        if row["match_method"] not in MATCH_METHODS:
            fail(f"Unknown match_method for city {row['current_city_id']}")
        if row["confidence"] not in CONFIDENCE_VALUES:
            fail(f"Unknown confidence for city {row['current_city_id']}")
        ags = row["official_municipality_ags"]
        if ags:
            official_row = official_by_ags.get(ags)
            if not official_row:
                fail(f"Assigned municipality AGS does not exist: {ags}")
            if row["official_district_ags"] != official_row["district_ags"]:
                fail(f"District parent mismatch for city {row['current_city_id']}")
            if row["match_method"] not in {"exact_official_name", "normalized_official_name"}:
                fail(f"Unsafe automatic assignment method for city {row['current_city_id']}")
        if row["match_status"] in {"partial", "locality", "ambiguous", "unmatched"} and ags:
            fail(f"Manual-review status has automatic AGS for city {row['current_city_id']}")

    expected_manual = {row["current_city_id"] for row in mappings if row["requires_manual_review"] == "true"}
    actual_manual = {row["current_city_id"] for row in manual_rows}
    if expected_manual != actual_manual or len(actual_manual) != len(manual_rows):
        fail("manual-review.csv does not exactly match requires_manual_review=true rows")
    if any(row["current_city_id"] in actual_manual for row in mappings if row["requires_manual_review"] == "false"):
        fail("A non-manual mapping appears in manual-review.csv")

    district_types = {row["district_ags"]: row["district_type"] for row in official}
    if Counter(district_types.values()) != Counter({"landkreis": EXPECTED_LANDKREIS_COUNT, "kreisfreie_stadt": EXPECTED_KREISFREIE_COUNT}):
        fail("Unexpected Landkreis/kreisfreie Stadt counts")
    return {
        "status": Counter(row["match_status"] for row in mappings),
        "confidence": Counter(row["confidence"] for row in mappings),
    }


def write_csv_atomic(path: Path, fields: list[str], rows: list[dict[str, str]]) -> None:
    temp_path = path.with_suffix(path.suffix + ".tmp")
    with temp_path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, quoting=csv.QUOTE_ALL, lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)
    os.replace(temp_path, path)


def report_markdown(official: list[dict[str, str]], mappings: list[dict[str, str]], manual: list[dict[str, str]], stats: dict[str, Counter[str]]) -> str:
    districts = {row["district_ags"]: row for row in official}
    landkreise = sum(row["district_type"] == "landkreis" for row in districts.values())
    kreisfreie = sum(row["district_type"] == "kreisfreie_stadt" for row in districts.values())
    safe = sum(row["requires_manual_review"] == "false" for row in mappings)
    lines = [
        "# GeoCore Brandenburg — Step 1 report",
        "",
        f"- Дата подготовки: **{REPORT_DATE}**.",
        f"- Официальный источник: **{SOURCE_NAME}, Gebietsstand {SOURCE_DATE}**.",
        f"- Источник: {SOURCE_URL}",
        f"- Официальных Gemeinden Brandenburg: **{len(official)}**.",
        f"- Landkreise: **{landkreise}**.",
        f"- Kreisfreie Städte: **{kreisfreie}**.",
        f"- Записей PflegeIndex cities: **{len(mappings)}**.",
        f"- Учреждений: **{sum(int(row['facility_count']) for row in mappings)}**.",
        f"- Безопасных автоматических сопоставлений: **{safe}**.",
        f"- Записей для ручной проверки: **{len(manual)}**.",
        "",
        "## Match status",
        "",
    ]
    for status in ("exact", "normalized", "partial", "locality", "ambiguous", "unmatched"):
        lines.append(f"- `{status}`: **{stats['status'][status]}**.")
    lines.extend(["", "## Confidence", ""])
    for confidence in ("high", "medium", "low", "none"):
        lines.append(f"- `{confidence}`: **{stats['confidence'][confidence]}**.")
    lines.extend([
        "",
        "## Правила нормализации",
        "",
        "Названия приводятся к нижнему регистру, немецкие Ä/Ö/Ü/ß транслитерируются как ae/oe/ue/ss, диакритика удаляется, а пунктуация заменяется пробелами. Административный суффикс `, Stadt` удаляется только для ключа сравнения и slug; официальное название сохраняется без изменений. При совпадающих slug к нему детерминированно добавляется полный AGS.",
        "",
        "## Почему PLZ не является ключом",
        "",
        "GV-ISys публикует PLZ административного центра Gemeinde, а не полный перечень адресных PLZ её территории. Один PLZ может относиться к нескольким Gemeinde или локальным записям, а одна Gemeinde может содержать несколько адресных PLZ. Поэтому PLZ используется только как дополнительное свидетельство и никогда не создаёт автоматическое сопоставление самостоятельно.",
        "",
        "## Качество и ограничения",
        "",
        "- Исходный PflegeIndex хранит адресный city/locality, который не гарантированно является самостоятельной Gemeinde.",
        "- Частичные названия и возможные Ortsteile оставлены без AGS до официальной проверки.",
        "- Поле `administrative_seat` оставлено пустым: квартальный GV-ISys содержит официальный PLZ административного центра, но не отдельное авторитетное название административного центра.",
        "- Координаты и неофициальные географические источники не использовались.",
        "",
        "## Manual review",
        "",
        "| City ID | Stored name | Status | Best candidate | Recommended source |",
        "|---:|---|---|---|---|",
    ])
    for row in manual:
        candidate = f"{row['best_candidate_ags']} {row['best_candidate_name']}".strip() or "—"
        lines.append(f"| {row['current_city_id']} | {row['current_city_name'].replace('|', '\\|')} | {row['match_status']} | {candidate.replace('|', '\\|')} | {row['recommended_manual_source'].replace('|', '\\|')} |")
    lines.extend([
        "",
        "## Следующий этап: GeoCore Database",
        "",
        "1. Загружать `official-municipalities.csv` и подтверждённые строки mapping сначала в staging-таблицы.",
        "2. Хранить AGS как текст и валидировать родительский Kreis по первым пяти позициям AGS.",
        "3. Не переносить строки manual review в production mapping до фиксации официального источника.",
        "4. После ручной проверки повторно сгенерировать файлы и только затем проектировать миграции `landkreise`/`cities`.",
        "",
        "## Готовность",
        "",
        "**Эталонный официальный слой готов для следующего этапа. Полная production-миграция пока не готова:** сначала требуется закрыть все строки `manual-review.csv`. Безопасно начинать проектирование GeoCore Database и staging-импорт, но не массовое присвоение Landkreis всем текущим cities.",
    ])
    return "\n".join(lines) + "\n"


def readme_markdown() -> str:
    return f"""# GeoCore Brandenburg Step 1

Эта директория содержит производный административный датасет Brandenburg и сопоставление с текущими city-записями PflegeIndex.

## Файлы

- `official-municipalities.csv` — официальный слой Gemeinden/Kreise из GV-ISys.
- `pflegeindex-city-mapping.csv` — одна строка на каждый текущий `cities.id`.
- `manual-review.csv` — только записи, требующие официальной ручной проверки.
- `geocore-brandenburg-report.md` — статистика, ограничения и решение о готовности.
- `README.md` — этот файл.

## Официальный источник

- {SOURCE_NAME}, Gebietsstand {SOURCE_DATE}
- {SOURCE_URL}

## Повторный запуск

Из директории `laravel/`:

```powershell
python scripts/geocore/build_brandenburg_dataset.py --gvisys "C:\\path\\to\\AuszugGV2QAktuell.xlsx"
```

Скрипт использует только стандартную библиотеку Python, открывает SQLite в immutable/read-only режиме, не выполняет миграции и пишет только в `storage/app/geocore/brandenburg/`.

## Ограничения

- PLZ не является идентификатором Gemeinde.
- `administrative_seat` пуст, поскольку использованный квартальный GV-ISys не содержит отдельного поля с названием административного центра.
- Partial/locality/ambiguous/unmatched записи нельзя импортировать как подтверждённые.
- Все CSV и Markdown-файлы этой директории являются производными и перегенерируются скриптом.

## Ручные изменения

Без документированной причины и официального источника нельзя вручную менять AGS, официальные названия, родительский Kreis, source date/URL, match status/method/confidence и флаг manual review. Исправления следует вносить через версионированные правила/источники генератора, после чего перегенерировать весь набор.
"""


def write_text_atomic(path: Path, content: str) -> None:
    temp_path = path.with_suffix(path.suffix + ".tmp")
    temp_path.write_text(content, encoding="utf-8", newline="\n")
    os.replace(temp_path, path)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--gvisys", required=True, type=Path, help="Official GV-ISys XLSX for 30.06.2026")
    parser.add_argument("--project-root", type=Path, default=Path(__file__).resolve().parents[3])
    args = parser.parse_args()

    project_root = args.project_root.resolve()
    laravel_root = project_root / "laravel"
    paths = {
        "database": laravel_root / "database/database.sqlite",
        "audit": laravel_root / "storage/app/audits/brandenburg-city-audit.csv",
        "audit_report": laravel_root / "storage/app/audits/brandenburg-city-audit.md",
        "json": project_root / "mvp/data/facilities.json",
        "source_csv": project_root / "mvp/data/source/pflegefonds-einrichtungen-brandenburg-2025.csv",
        "gvisys": args.gvisys.resolve(),
    }
    for label, path in paths.items():
        if not path.is_file():
            fail(f"Required input not found ({label}): {path}")

    protected_paths = [paths["database"], paths["audit"], paths["audit_report"], paths["json"], paths["source_csv"], paths["gvisys"]]
    hashes_before = {str(path): sha256(path) for path in protected_paths}

    official, _ = read_official_municipalities(paths["gvisys"])
    official_by_ags = {row["municipality_ags"]: row for row in official}
    official_postal_index: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in official:
        if row["postal_code_official"]:
            official_postal_index[row["postal_code_official"]].append(row)

    audit_rows = read_csv(paths["audit"])
    audit_by_id = {row["city_id"]: row for row in audit_rows}
    if len(audit_rows) != EXPECTED_CITY_COUNT or len(audit_by_id) != EXPECTED_CITY_COUNT:
        fail("Existing city audit must contain exactly 257 unique city rows")

    json_rows = json.loads(paths["json"].read_text(encoding="utf-8"))
    if not isinstance(json_rows, list) or len(json_rows) != EXPECTED_FACILITY_COUNT:
        fail("facilities.json must contain exactly 1557 records")
    with paths["source_csv"].open("r", encoding="utf-8-sig", newline="") as handle:
        source_rows = list(csv.reader(handle, delimiter=";"))
    if len(source_rows) != EXPECTED_FACILITY_COUNT or any(len(row) != 6 for row in source_rows):
        fail("Original source CSV must contain exactly 1557 six-column rows")

    database_uri = paths["database"].as_uri() + "?mode=ro&immutable=1"
    connection = sqlite3.connect(database_uri, uri=True)
    connection.row_factory = sqlite3.Row
    cities = connection.execute("SELECT id, name, slug, state, state_slug FROM cities ORDER BY id").fetchall()
    facilities = connection.execute("SELECT id, city_id, postal_code FROM facilities ORDER BY city_id, id").fetchall()
    connection.close()
    if len(cities) != EXPECTED_CITY_COUNT or len(facilities) != EXPECTED_FACILITY_COUNT:
        fail("SQLite city/facility totals differ from 257/1557")
    facilities_by_city: dict[int, list[sqlite3.Row]] = defaultdict(list)
    for facility in facilities:
        facilities_by_city[int(facility["city_id"])].append(facility)

    mappings = build_mapping(cities, facilities_by_city, audit_by_id, official_by_ags, official_postal_index)
    manual_rows = build_manual_rows(mappings, official_by_ags, official_postal_index)
    stats = validate(official, mappings, manual_rows)

    output_dir = laravel_root / "storage/app/geocore/brandenburg"
    output_dir.mkdir(parents=True, exist_ok=True)
    write_csv_atomic(output_dir / "official-municipalities.csv", OFFICIAL_FIELDS, official)
    write_csv_atomic(output_dir / "pflegeindex-city-mapping.csv", MAPPING_FIELDS, mappings)
    write_csv_atomic(output_dir / "manual-review.csv", MANUAL_FIELDS, manual_rows)
    write_text_atomic(output_dir / "geocore-brandenburg-report.md", report_markdown(official, mappings, manual_rows, stats))
    write_text_atomic(output_dir / "README.md", readme_markdown())

    hashes_after = {str(path): sha256(path) for path in protected_paths}
    if hashes_before != hashes_after:
        fail("A protected input file changed during generation")

    summary = {
        "official_municipalities": len(official),
        "landkreise": EXPECTED_LANDKREIS_COUNT,
        "kreisfreie_staedte": EXPECTED_KREISFREIE_COUNT,
        "cities": len(mappings),
        "facilities": sum(int(row["facility_count"]) for row in mappings),
        "match_status": dict(stats["status"]),
        "confidence": dict(stats["confidence"]),
        "safe_automatic_matches": sum(row["requires_manual_review"] == "false" for row in mappings),
        "manual_review": len(manual_rows),
        "output_dir": str(output_dir),
        "protected_inputs_unchanged": True,
    }
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exception:
        print(f"GeoCore build failed: {exception}", file=sys.stderr)
        raise SystemExit(1)
