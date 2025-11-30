-- Copyright (C) 2025
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

-- Add Qonto-specific fields to bank_account_extrafields table
-- This allows linking Dolibarr bank accounts to Qonto bank accounts

ALTER TABLE llx_bank_account_extrafields 
ADD COLUMN IF NOT EXISTS qonto_bank_id VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS qonto_name VARCHAR(255) DEFAULT NULL,
ADD INDEX idx_qonto_bank_id (qonto_bank_id);
