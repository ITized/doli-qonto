# DoliQonto - Qonto Banking Integration for Dolibarr

Complete Dolibarr module for Qonto banking API integration with transaction sync, payment matching, and attachment management.

## Quick Start

1. **Install**: Copy to `/custom/doliqonto` and activate in Dolibarr
2. **Configure**: Setup → Enter API credentials or connect via OAuth2
3. **Link Accounts**: Bank Accounts → Auto-link by IBAN
4. **Sync**: Transactions → Sync Transactions → Auto-Match All

## Features

- ✅ Dual authentication (API Key + OAuth2)
- ✅ Auto-link bank accounts by IBAN
- ✅ Transaction synchronization
- ✅ Auto-matching with existing bank lines
- ✅ Manual payment matching with invoices
- ✅ Attachment sync (Qonto ↔ Dolibarr)
- ✅ Multi-language (EN, FR)

## Documentation

- **Setup**: See Module Setup section below
- **API**: See `class/qontoapi.class.php` for endpoints
- **Hooks**: See `core/hooks/doliqontohooks.class.php`
- **Database**: See `sql/` directory for schema

## Requirements

- Dolibarr 20.0+
- PHP 7.0+
- MySQL/MariaDB
- Qonto business account

## Module Setup

### 1. Authentication

**Option A: API Key (Recommended for now)**
- Go to: DoliQonto → Setup
- Select "API Key (Classic)"
- Enter Organization Slug + API Key from Qonto dashboard
- Click "Test Connection"

**Option B: OAuth 2.0 (Pending Qonto approval)**
- Go to: DoliQonto → Setup
- Select "OAuth 2.0"
- Enter Client ID + Client Secret
- Click "Connect with Qonto"

### 2. Link Bank Accounts

- Go to: DoliQonto → Bank Accounts
- Click "Auto-Link Accounts" (matches by IBAN)
- Or manually select Qonto account for each Dolibarr account

### 3. Sync Transactions

- Go to: DoliQonto → Transactions
- Click "Sync Transactions" (imports from Qonto)
- Click "Auto-Match All" (links to existing bank lines)

### 4. Match Payments

For unmatched transactions:
- Click "Match" button
- Select invoice
- Confirm → Creates payment + bank line + links to transaction

## Key Files

```
class/qontoapi.class.php          # API wrapper, OAuth2, sync logic
class/qontotransaction.class.php  # Transaction model, matching
core/hooks/doliqontohooks.class.php  # Bank card hooks
transactions.php                  # Main transaction list
matching.php                      # Payment matching UI
attachments.php                   # Attachment sync UI
```

## Database Tables

- `llx_qonto_transactions` - Synced transactions
- `llx_bank_account_extrafields` - Qonto↔Dolibarr account links
- `llx_qonto_attachments` - Attachment metadata and sync tracking (to/from Qonto)
- `llx_qonto_tax_validation` - VAT/tax conflict resolution between Qonto and Dolibarr

**Key relationships:**
- `fk_bank` links transactions to `llx_bank.rowid`
- Invoice links are accessed through: `fk_bank` → bank line → payment → invoice
- Match status is **derived from data** (not stored):
  - **Matched**: `fk_bank IS NOT NULL`
  - **Pending**: `fk_bank IS NULL AND ignored = 0`
  - **Ignored**: `ignored = 1`
- This ensures data consistency with Dolibarr's payment architecture

**Note:** As of v1.1+:
- Redundant columns `fk_facture`, `fk_facture_fourn`, and `fk_payment_fourn` have been removed
- `sync_status` replaced with `ignored` boolean - match status is now derived from `fk_bank`
- Invoice links are managed exclusively through `fk_bank`

## Configuration Options

| Setting | Default | Description |
|---------|---------|-------------|
| Auth Method | `api_key` | API Key or OAuth2 |
| Sync Days Back | `30` | How far back to sync |
| Auto Sync | `No` | Daily automatic sync |
| Auto Match | `No` | Auto-match on sync |

## Troubleshooting

**No transactions synced**
- Check bank accounts are linked (Bank Accounts page)
- Verify API credentials (Test Connection)
- Check date range (Sync Days Back)

**Auto-match finds nothing**
- Ensure bank lines exist in Dolibarr first
- Check amounts match exactly (uses absolute value)
- Date tolerance is ±2 days

**Invoice not marked paid**
- Known issue, see KNOWN_ISSUES.md
- Workaround: Mark manually in Dolibarr

## Development

**Adding logs:**
```php
dol_syslog("DoliQonto: Message", LOG_DEBUG);
```

**Check logs:**
`/documents/dolibarr.log`

## Support

- Issues: Create GitHub issue with logs
- Email: ionut.finta@itized.com
- Documentation: See code comments

## License

GPL-3.0 - See LICENSE file

## Credits

Developer: Finta Ionut (ITIZED)  
Website: https://itized.com

## Version

**1.0.0** (November 30, 2025)
- Initial release
- Full feature set implemented
- Known issues documented
