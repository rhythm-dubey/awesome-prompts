SELECT
    v.valuation_id,
    v.valuation_property_address_line_1,
    v.valuation_property_apartment_number_name,
    v.valuation_property_number_name,
    v.valuation_property_postcode,
    v.valuation_property_availability,
    c.last_name AS primary_contact_surname,
    (
        SELECT COUNT(*)
        FROM valuation d
        JOIN valuation_contact dvc
          ON dvc.valuation_id = d.valuation_id
         AND dvc.is_primary = 1
        JOIN contacts dc
          ON dc.id = dvc.contact_id
        WHERE d.valuation_id <> v.valuation_id
          AND d.valuation_property_apartment_number_name IS NOT DISTINCT FROM v.valuation_property_apartment_number_name
          AND d.valuation_property_number_name           IS NOT DISTINCT FROM v.valuation_property_number_name
          AND d.valuation_property_postcode              IS NOT DISTINCT FROM v.valuation_property_postcode
          AND d.valuation_property_availability          IS NOT DISTINCT FROM v.valuation_property_availability
          AND dc.last_name = c.last_name
    ) AS duplicate_count
FROM valuation v
JOIN valuation_contact vc
  ON vc.valuation_id = v.valuation_id
 AND vc.is_primary = 1
JOIN contacts c
  ON c.id = vc.contact_id
WHERE c.last_name IS NOT NULL
  AND c.last_name <> ''
  AND EXISTS (
      SELECT 1
      FROM valuation d
      JOIN valuation_contact dvc
        ON dvc.valuation_id = d.valuation_id
       AND dvc.is_primary = 1
      JOIN contacts dc
        ON dc.id = dvc.contact_id
      WHERE d.valuation_id <> v.valuation_id
        AND d.valuation_property_apartment_number_name IS NOT DISTINCT FROM v.valuation_property_apartment_number_name
        AND d.valuation_property_number_name           IS NOT DISTINCT FROM v.valuation_property_number_name
        AND d.valuation_property_postcode              IS NOT DISTINCT FROM v.valuation_property_postcode
        AND d.valuation_property_availability          IS NOT DISTINCT FROM v.valuation_property_availability
        AND dc.last_name = c.last_name
  )
ORDER BY v.valuation_id;