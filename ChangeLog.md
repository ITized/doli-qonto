# Changelog

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
