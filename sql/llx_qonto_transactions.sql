-- Copyright (C) 2025 SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE IF NOT EXISTS llx_qonto_transactions(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL,
	transaction_id varchar(255) NOT NULL,
	bank_account_id varchar(255),
	emitted_at datetime NOT NULL,
	settled_at datetime,
	amount decimal(24,8) NOT NULL,
	currency varchar(3) DEFAULT 'EUR',
	side varchar(10) NOT NULL COMMENT 'debit or credit',
	operation_type varchar(50),
	status varchar(50) NOT NULL,
	label varchar(255),
	note text,
	reference varchar(255),
	vat_amount decimal(24,8),
	vat_rate decimal(10,4),
	counterparty_name varchar(255),
	counterparty_iban varchar(50),
	attachment_ids text COMMENT 'JSON array of attachment IDs',
	ignored tinyint DEFAULT 0 COMMENT 'Transaction manually ignored by user',
	fk_bank integer DEFAULT NULL COMMENT 'Link to llx_bank.rowid',
	fk_payment integer,
	matched_date datetime,
	tax_conflict tinyint DEFAULT 0,
	raw_data text COMMENT 'Full JSON response from Qonto',
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14),
	-- END MODULEBUILDER FIELDS
	UNIQUE KEY uk_qonto_transactions_transaction_id (transaction_id, entity),
	INDEX idx_qonto_transactions_fk_bank (fk_bank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS llx_qonto_attachments(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	attachment_id varchar(255) NOT NULL,
	fk_qonto_transaction integer,
	filename varchar(255),
	file_size integer,
	file_url text,
	qonto_created_at datetime,
	sync_status varchar(20) DEFAULT 'pending' COMMENT 'pending, synced, error',
	sync_direction varchar(20) COMMENT 'to_qonto, from_qonto',
	fk_ecm_files integer,
	last_sync_date datetime,
	error_message text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	-- END MODULEBUILDER FIELDS
	UNIQUE KEY uk_qonto_attachments_attachment_id (attachment_id, entity),
	KEY idx_qonto_attachments_transaction (fk_qonto_transaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS llx_qonto_tax_validation(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_qonto_transaction integer NOT NULL,
	qonto_vat_amount decimal(24,8),
	qonto_vat_rate decimal(10,4),
	dolibarr_vat_amount decimal(24,8),
	dolibarr_vat_rate decimal(10,4),
	status varchar(20) DEFAULT 'pending' COMMENT 'pending, resolved, ignored',
	resolved_by integer,
	resolution_date datetime,
	chosen_source varchar(20) COMMENT 'qonto, dolibarr',
	notes text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	-- END MODULEBUILDER FIELDS
	KEY idx_qonto_tax_validation_status (status),
	KEY idx_qonto_tax_validation_transaction (fk_qonto_transaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
