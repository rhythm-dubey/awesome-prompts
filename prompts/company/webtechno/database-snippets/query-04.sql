BEGIN;

DO $$
DECLARE
    v_source_id bigint;
    v_target_id bigint;
    v_contact_id bigint;
BEGIN
    SELECT valuation_id
    INTO v_source_id
    FROM valuation
    ORDER BY valuation_id DESC
    LIMIT 1;

    SELECT valuation_id
    INTO v_target_id
    FROM valuation
    WHERE valuation_id <> v_source_id
    ORDER BY valuation_id DESC
    LIMIT 1;

    IF v_source_id IS NULL OR v_target_id IS NULL THEN
        RAISE EXCEPTION 'Need at least 2 valuation records';
    END IF;

    SELECT id
    INTO v_contact_id
    FROM contacts
    WHERE COALESCE(last_name, '') <> ''
    ORDER BY id DESC
    LIMIT 1;

    IF v_contact_id IS NULL THEN
        RAISE EXCEPTION 'Need at least 1 contact with non-empty last_name';
    END IF;

    UPDATE valuation t
    SET
        valuation_property_apartment_number_name = s.valuation_property_apartment_number_name,
        valuation_property_number_name = s.valuation_property_number_name,
        valuation_property_postcode = s.valuation_property_postcode,
        valuation_property_availability = s.valuation_property_availability
    FROM valuation s
    WHERE s.valuation_id = v_source_id
      AND t.valuation_id = v_target_id;

    DELETE FROM valuation_contact
    WHERE valuation_id IN (v_source_id, v_target_id)
      AND is_primary = 1;

    INSERT INTO valuation_contact (valuation_id, contact_id, is_primary, created_at, updated_at)
    VALUES
        (v_source_id, v_contact_id, 1, NOW(), NOW()),
        (v_target_id, v_contact_id, 1, NOW(), NOW());
END $$;

COMMIT;