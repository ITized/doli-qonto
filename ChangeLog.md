# Changelog

## [1.1.1] - 2026-03-14

### Added
- German translation (de_DE)
- Spanish translation (es_ES)
- Italian translation (it_IT)
- Dutch translation (nl_NL)
- Portuguese translation (pt_PT)

## [1.1.0] - 2026-03-14

### Fixed
- Fixed `date()` TypeError on transaction settled_at (was string, not timestamp)
- Fixed matching page showing wrong dates (used invoice creation date instead of invoice date)
- Fixed invoices stuck at "Started paying" instead of "Paid" (use `getRemainToPay()` instead of broken `totalpaye`)
- Fixed supplier invoice attachment path missing `get_exdir()` subdirectory
- Fixed attachment upload to Qonto: added required `X-Qonto-Idempotency-Key` header
- Fixed attachment upload using correct transaction UUID from raw API data
- Fixed duplicate attachment upload on page refresh (Post-Redirect-Get pattern)
- Fixed `fk_payment` not set for supplier invoice matches in matching page
- Fixed French translation: two keys concatenated on one line (`QontoOAuthCodeMissing` + `OAuthFeatureWarning`)
- Fixed English translation: removed duplicate key definitions
- Fixed error messages leaking internal API URLs to user interface
- Removed dead code (`listClientInvoices`/`listSupplierInvoices` with wrong endpoints)

### Added
- Invoice references are now clickable links in matching page
- Smart invoice sorting: prioritizes company name match, exact amount, due date proximity
- Auto-match invoice feature: automatically creates payments when unambiguous match found
- Smart name matching: case-insensitive LIKE, prefix extraction (before `-`/`_`), first word matching
- Manual invoice search form with inline "Associer" button in matching page
- Amount tolerance of 20% for forex differences (e.g., USD invoices paid in EUR)
- Attachment upload progress indicator with polling (spinner + retry counter)
- Automatic Qonto attachment count refresh after upload via AJAX polling
- SSRF protection: domain validation on attachment download URLs
- UUID v4 generation for Qonto API idempotency keys

### Changed
- Matching page uses `f.datef` (invoice date) and `f.date_lim_reglement` (due date) instead of `f.datec`
- Attachment upload now uses multipart/form-data with `CURLFile` instead of JSON base64
- Upload results redirect to prevent duplicate submissions on page refresh

### Security
- Added SSRF protection on attachment download (URL domain validation against `*.qonto.com`)
- Prevented duplicate attachment uploads via PRG pattern
- Removed internal URL and auth method from user-facing error messages

## [1.0.1] - 2026-01-20

### Fixed
- Moved Qonto menu inside the Bank module left menu for better integration
- Fixed customer invoice matching query (incorrect field name `date_creation` replaced with `datec`)
- Fixed auto-matching functionality to handle empty/null/zero values in `fk_bank` field
- Fixed transaction list filters (match status, amounts, labels) not applying correctly
- Fixed form not refreshing after changing authentication method in setup page

### Added
- Manual bank line matching feature with flexible criteria (±10% amount, ±7 days)
- Visual warnings for features under development (OAuth, attachments, tax validation)
- Debug capability for troubleshooting matching issues

### Changed
- Auto-matching now uses exact date and exact amount with correct sign for better precision
- Enhanced filter handling with proper validation types (alphanohtml, price2num)
- Improved error messages and user feedback

## [1.0.0] - 2025-11-30

### Added
- Initial release of Qonto module for Dolibarr
- Transaction synchronization from Qonto API
- Provisional database for pending transactions
- Smart payment matching with customer invoices
- Smart payment matching with supplier invoices
- Automatic payment creation on match
- Bidirectional attachment synchronization
- Download attachments from Qonto to Dolibarr
- Upload attachments from Dolibarr to Qonto
- Tax information comparison and validation
- Conflict resolution interface for tax discrepancies
- Received payment matching with emitted client invoices
- Direct attachment transfer for received payments
- Admin configuration page
- API connection testing
- Multi-language support (English, French)
- Comprehensive permissions system
- Scheduled cron job for automatic sync
- Transaction filtering and search
- Transaction status management (pending, matched, ignored)
- Full Dolibarr 20+ compatibility
- Detailed documentation and README
- Installation guide for Dolistore publication

### Security
- Secure API key storage
- HTTPS-only API communication
- Input validation and sanitization
- SQL injection protection
- XSS prevention

### Performance
- Optimized database queries with indexes
- Efficient pagination for large transaction lists
- Batch processing for multiple transactions
- Caching of API responses

## [Unreleased]

### Planned Features
- Webhook support for real-time transaction updates
- Advanced matching rules configuration
- Bulk payment operations
- Transaction categorization
- Reporting and analytics dashboard
- Enhanced multi-currency support
- Integration with Dolibarr bank reconciliation module
- Export functionality for transactions
- Advanced search and filtering options
- Email notifications for important events
- API rate limiting and retry logic
- Support for multiple bank accounts
- Transaction duplicate detection
- Automatic VAT rate suggestion
- Custom matching algorithms
