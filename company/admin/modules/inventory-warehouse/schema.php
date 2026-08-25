<?php
declare(strict_types=1);

function yovel_admin_inventory_warehouse_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_inventory_form_schema (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_schema_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            schema_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
            schema_version INT UNSIGNED NOT NULL,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            current_form_version_key CHAR(36) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_inventory_form_record (company_key_hash, record_type),
            INDEX idx_project_company_inventory_form_status (company_key_hash, schema_status, record_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_form_schema_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_version_key CHAR(36) NOT NULL UNIQUE,
            form_schema_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_inventory_form_version (form_schema_key, version_number),
            UNIQUE KEY uq_project_company_inventory_form_checksum (form_schema_key, schema_checksum),
            INDEX idx_project_company_inventory_form_version_scope (company_key_hash, record_type, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_form_schema_audit (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_schema_audit_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_schema_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            audit_action VARCHAR(40) NOT NULL,
            previous_checksum CHAR(64) NULL,
            next_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_inventory_form_audit_record (company_key_hash, record_type, created_at),
            INDEX idx_project_company_inventory_form_audit_schema (form_schema_key, form_version_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_inventory_execute($db, $statement, [], 'Inventory Form Builder schema update');
    }
}

function yovel_admin_inventory_catalogue_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_inventory_customs_tariff (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tariff_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, tariff_code VARCHAR(40) NOT NULL,
            tariff_description VARCHAR(255) NOT NULL DEFAULT '', created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_tariff_code (company_key_hash, tariff_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_uom (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uom_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, uom_code VARCHAR(40) NOT NULL,
            uom_name VARCHAR(120) NOT NULL, uom_category VARCHAR(80) NOT NULL, uom_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_uom_code (company_key_hash, uom_code), INDEX idx_inventory_uom_category (company_key_hash, uom_category, uom_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_manufacturer (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, manufacturer_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, manufacturer_code VARCHAR(80) NOT NULL,
            manufacturer_name VARCHAR(160) NOT NULL, website VARCHAR(255) NOT NULL DEFAULT '', manufacturer_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_manufacturer_code (company_key_hash, manufacturer_code), INDEX idx_inventory_manufacturer_name (company_key_hash, manufacturer_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_price_list (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, price_list_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, price_list_code VARCHAR(80) NOT NULL,
            price_list_name VARCHAR(160) NOT NULL, currency_code CHAR(3) NOT NULL, is_selling TINYINT(1) NOT NULL DEFAULT 0,
            is_buying TINYINT(1) NOT NULL DEFAULT 0, price_list_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_price_list_code (company_key_hash, price_list_code), INDEX idx_inventory_price_list_status (company_key_hash, price_list_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_price_list_country (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, price_list_country_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, price_list_key CHAR(36) NOT NULL, country_code CHAR(2) NOT NULL,
            UNIQUE KEY uq_inventory_price_list_country (company_key_hash, price_list_key, country_code), INDEX idx_inventory_price_country (company_key_hash, country_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_code VARCHAR(80) NOT NULL,
            item_name VARCHAR(160) NOT NULL, item_description VARCHAR(1000) NOT NULL DEFAULT '', item_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            item_kind ENUM('STOCK','NON_STOCK') NOT NULL DEFAULT 'STOCK', stock_uom_code VARCHAR(40) NOT NULL,
            has_serial_no TINYINT(1) NOT NULL DEFAULT 0, has_batch_no TINYINT(1) NOT NULL DEFAULT 0,
            is_template TINYINT(1) NOT NULL DEFAULT 0, variant_of_item_key CHAR(36) NULL, customs_tariff_code VARCHAR(40) NOT NULL DEFAULT '',
            stock_activity_count BIGINT UNSIGNED NOT NULL DEFAULT 0, created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_item_code (company_key_hash, item_code), INDEX idx_inventory_item_status (company_key_hash, item_status, item_kind),
            INDEX idx_inventory_item_variant (company_key_hash, variant_of_item_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_uom (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_uom_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL, uom_key CHAR(36) NOT NULL,
            uom_code VARCHAR(40) NOT NULL, conversion_factor DECIMAL(24,9) NOT NULL, is_stock_uom TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_inventory_item_uom (company_key_hash, item_key, uom_code), INDEX idx_inventory_item_uom_code (company_key_hash, uom_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_barcode (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_barcode_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            barcode VARCHAR(80) NOT NULL, barcode_type VARCHAR(20) NOT NULL,
            UNIQUE KEY uq_inventory_barcode (company_key_hash, barcode), INDEX idx_inventory_item_barcode_item (company_key_hash, item_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_variant_attribute (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_variant_attribute_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            attribute_name VARCHAR(120) NOT NULL, attribute_value VARCHAR(160) NOT NULL,
            UNIQUE KEY uq_inventory_item_variant_attribute (company_key_hash, item_key, attribute_name), INDEX idx_inventory_variant_attribute (company_key_hash, attribute_name, attribute_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_attribute (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_attribute_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, attribute_name VARCHAR(120) NOT NULL,
            attribute_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_item_attribute (company_key_hash, attribute_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_attribute_value (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_attribute_value_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_attribute_key CHAR(36) NOT NULL,
            attribute_value VARCHAR(160) NOT NULL, abbreviation VARCHAR(20) NOT NULL,
            UNIQUE KEY uq_inventory_item_attribute_value (company_key_hash, item_attribute_key, attribute_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_variant_setting (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, variant_setting_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, naming_mode ENUM('ITEM_CODE','ATTRIBUTE_ABBREVIATION') NOT NULL DEFAULT 'ITEM_CODE',
            setting_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE', updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_variant_setting (company_key_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_variant_field (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, variant_field_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, template_item_key CHAR(36) NOT NULL,
            item_attribute_key CHAR(36) NOT NULL, field_order INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_inventory_variant_field (company_key_hash, template_item_key, item_attribute_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_manufacturer (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_manufacturer_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            manufacturer_key CHAR(36) NOT NULL, manufacturer_part_no VARCHAR(120) NOT NULL DEFAULT '',
            UNIQUE KEY uq_inventory_item_manufacturer (company_key_hash, item_key, manufacturer_key), INDEX idx_inventory_manufacturer_part (company_key_hash, manufacturer_part_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_alternative (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_alternative_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            alternative_item_key CHAR(36) NOT NULL, two_way TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_inventory_item_alternative (company_key_hash, item_key, alternative_item_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_party_detail (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_party_detail_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            party_type ENUM('CUSTOMER','SUPPLIER') NOT NULL, party_reference_key CHAR(36) NOT NULL, party_item_code VARCHAR(120) NOT NULL DEFAULT '',
            UNIQUE KEY uq_inventory_item_party (company_key_hash, item_key, party_type, party_reference_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_tax (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_tax_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            tax_template_reference VARCHAR(120) NOT NULL, tax_rate DECIMAL(12,6) NOT NULL,
            UNIQUE KEY uq_inventory_item_tax (company_key_hash, item_key, tax_template_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_default (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_default_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            scope_key VARCHAR(120) NOT NULL, default_warehouse_reference VARCHAR(120) NOT NULL DEFAULT '', default_price_list_reference VARCHAR(120) NOT NULL DEFAULT '',
            UNIQUE KEY uq_inventory_item_default (company_key_hash, item_key, scope_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_lead_time (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_lead_time_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            lead_time_context VARCHAR(80) NOT NULL, lead_time_days INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_inventory_item_lead_time (company_key_hash, item_key, lead_time_context)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_website_spec (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_website_spec_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            specification_label VARCHAR(160) NOT NULL, specification_value VARCHAR(500) NOT NULL,
            UNIQUE KEY uq_inventory_item_website_spec (company_key_hash, item_key, specification_label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_reorder (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_reorder_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            warehouse_reference VARCHAR(120) NOT NULL, reorder_level DECIMAL(24,9) NOT NULL, reorder_qty DECIMAL(24,9) NOT NULL,
            UNIQUE KEY uq_inventory_item_reorder (company_key_hash, item_key, warehouse_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_item_price (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_price_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL,
            price_list_key CHAR(36) NOT NULL, uom_code VARCHAR(40) NOT NULL, currency_code CHAR(3) NOT NULL,
            rate DECIMAL(24,9) NOT NULL, minimum_qty DECIMAL(24,9) NOT NULL, valid_from DATE NOT NULL, valid_to DATE NULL,
            price_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_item_price_natural (company_key_hash, item_key, price_list_key, uom_code, valid_from),
            INDEX idx_inventory_item_price_validity (company_key_hash, price_status, valid_from, valid_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_inventory_execute($db, $statement, [], 'Inventory catalogue schema update');
    }
}

function yovel_admin_inventory_warehouse_control_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_inventory_stock_setting (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, stock_setting_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL,
            allow_negative_stock TINYINT(1) NOT NULL DEFAULT 0, capacity_enforcement TINYINT(1) NOT NULL DEFAULT 1,
            default_putaway_strategy ENUM('PRIORITY','CAPACITY') NOT NULL DEFAULT 'PRIORITY',
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_stock_setting_company (company_key_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_warehouse_type (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, warehouse_type_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL,
            warehouse_type_code VARCHAR(80) NOT NULL, warehouse_type_name VARCHAR(160) NOT NULL,
            warehouse_type_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_warehouse_type_code (company_key_hash, warehouse_type_code),
            INDEX idx_inventory_warehouse_type_status (company_key_hash, warehouse_type_status, warehouse_type_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_warehouse (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, warehouse_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL,
            warehouse_code VARCHAR(80) NOT NULL, warehouse_name VARCHAR(160) NOT NULL,
            parent_warehouse_key CHAR(36) NULL, warehouse_type_key CHAR(36) NULL,
            is_group TINYINT(1) NOT NULL DEFAULT 0, warehouse_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            capacity_qty DECIMAL(24,9) NOT NULL DEFAULT 0, putaway_priority INT UNSIGNED NOT NULL DEFAULT 100,
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_warehouse_code (company_key_hash, warehouse_code),
            INDEX idx_inventory_warehouse_parent (company_key_hash, parent_warehouse_key, warehouse_status),
            INDEX idx_inventory_warehouse_type (company_key_hash, warehouse_type_key, warehouse_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_dimension (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dimension_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL,
            dimension_code VARCHAR(80) NOT NULL, dimension_name VARCHAR(160) NOT NULL,
            dimension_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE', is_required TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 100, created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_dimension_code (company_key_hash, dimension_code),
            INDEX idx_inventory_dimension_status (company_key_hash, dimension_status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_dimension_value (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dimension_value_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, dimension_key CHAR(36) NOT NULL,
            dimension_value_code VARCHAR(80) NOT NULL, dimension_value_name VARCHAR(160) NOT NULL,
            dimension_value_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_dimension_value (company_key_hash, dimension_key, dimension_value_code),
            INDEX idx_inventory_dimension_value_status (company_key_hash, dimension_key, dimension_value_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_bin (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bin_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL, warehouse_key CHAR(36) NOT NULL,
            dimensions_json TEXT NOT NULL, dimensions_checksum CHAR(64) NOT NULL, actual_qty DECIMAL(24,9) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_bin_tuple (company_key_hash, item_key, warehouse_key, dimensions_checksum),
            INDEX idx_inventory_bin_warehouse (company_key_hash, warehouse_key, item_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_bin_source (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bin_source_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, bin_key CHAR(36) NOT NULL,
            item_key CHAR(36) NOT NULL, warehouse_key CHAR(36) NOT NULL, dimensions_checksum CHAR(64) NOT NULL,
            source_owner VARCHAR(80) NOT NULL, source_key VARCHAR(120) NOT NULL, source_line_key VARCHAR(120) NOT NULL DEFAULT '',
            source_type ENUM('ACTUAL','RESERVED','ORDERED','REQUESTED','PLANNED') NOT NULL,
            source_status ENUM('ACTIVE','CANCELLED') NOT NULL DEFAULT 'ACTIVE', quantity DECIMAL(24,9) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_bin_source_identity (company_key_hash, source_owner, source_key, source_line_key, source_type),
            INDEX idx_inventory_bin_source_projection (company_key_hash, bin_key, source_status, source_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_putaway_rule (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, putaway_rule_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NULL, warehouse_key CHAR(36) NOT NULL,
            dimensions_json TEXT NOT NULL, dimensions_checksum CHAR(64) NOT NULL, priority INT UNSIGNED NOT NULL,
            putaway_rule_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_putaway_rule (company_key_hash, item_key, warehouse_key, dimensions_checksum),
            INDEX idx_inventory_putaway_priority (company_key_hash, putaway_rule_status, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_reorder_rule (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reorder_rule_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, item_key CHAR(36) NOT NULL, warehouse_key CHAR(36) NOT NULL,
            reorder_level DECIMAL(24,9) NOT NULL, reorder_quantity DECIMAL(24,9) NOT NULL,
            reorder_rule_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL, updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventory_reorder_rule (company_key_hash, item_key, warehouse_key),
            INDEX idx_inventory_reorder_status (company_key_hash, warehouse_key, reorder_rule_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_inventory_execute($db, $statement, [], 'Inventory warehouse-control schema update');
    }
}
