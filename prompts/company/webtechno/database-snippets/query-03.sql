WITH candidates AS (
    SELECT
        v.valuation_id,
        v.valuation_property_apartment_number_name,
        v.valuation_property_number_name,
        v.valuation_property_postcode,
        v.valuation_property_availability,
        vc.contact_id,
        c.last_name
    FROM valuation v
    JOIN valuation_contact vc
      ON vc.valuation_id = v.valuation_id
     AND vc.is_primary = 1
    JOIN contacts c
      ON c.id = vc.contact_id
    WHERE c.last_name IS NOT NULL
      AND c.last_name <> ''
)
SELECT
    MIN(valuation_id) AS source_id,
    MAX(valuation_id) AS target_id
FROM (
    SELECT valuation_id
    FROM candidates
    ORDER BY valuation_id DESC
    LIMIT 2
) x;