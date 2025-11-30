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


ALTER TABLE llx_qonto_transactions ADD INDEX idx_qonto_transactions_entity (entity);
ALTER TABLE llx_qonto_transactions ADD INDEX idx_qonto_transactions_ignored (ignored);
ALTER TABLE llx_qonto_transactions ADD INDEX idx_qonto_transactions_emitted_at (emitted_at);
ALTER TABLE llx_qonto_transactions ADD INDEX idx_qonto_transactions_status (status);

ALTER TABLE llx_qonto_attachments ADD INDEX idx_qonto_attachments_entity (entity);
ALTER TABLE llx_qonto_attachments ADD INDEX idx_qonto_attachments_sync_status (sync_status);

ALTER TABLE llx_qonto_tax_validation ADD INDEX idx_qonto_tax_validation_entity (entity);
