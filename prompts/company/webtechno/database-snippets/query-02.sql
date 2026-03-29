BEGIN;

WITH src AS (
    SELECT
        v.valuation_id,
        v.valuation_property_apartment_number_name,
        v.valuation_property_number_name,
        v.valuation_property_postcode,
        v.valuation_property_availability,
        c.last_name AS src_last_name
    FROM valuation v
    JOIN valuation_contact vc
      ON vc.valuation_id = v.valuation_id
     AND vc.is_primary = 1
    JOIN contacts c
      ON c.id = vc.contact_id
    WHERE v.valuation_id = :source_id
),
tgt_primary_contact AS (
    SELECT vc.contact_id
    FROM valuation_contact vc
    WHERE vc.valuation_id = :target_id
      AND vc.is_primary = 1
    LIMIT 1
)
UPDATE valuation v
SET
    valuation_property_apartment_number_name = s.valuation_property_apartment_number_name,
    valuation_property_number_name = s.valuation_property_number_name,
    valuation_property_postcode = s.valuation_property_postcode,
    valuation_property_availability = s.valuation_property_availability
FROM src s
WHERE v.valuation_id = :target_id;

WITH src AS (
    SELECT c.last_name AS src_last_name
    FROM valuation v
    JOIN valuation_contact vc
      ON vc.valuation_id = v.valuation_id
     AND vc.is_primary = 1
    JOIN contacts c
      ON c.id = vc.contact_id
    WHERE v.valuation_id = :source_id
),
tgt_primary_contact AS (
    SELECT vc.contact_id
    FROM valuation_contact vc
    WHERE vc.valuation_id = :target_id
      AND vc.is_primary = 1
    LIMIT 1
)
UPDATE contacts c
SET last_name = s.src_last_name
FROM src s, tgt_primary_contact t
WHERE c.id = t.contact_id;

COMMIT;