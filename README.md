# DoliQonto - Qonto Banking Integration for Dolibarr

Complete Dolibarr module for Qonto banking API integration with transaction sync, payment matching, attachment management, and monthly statement reconciliation.

## Quick Start

1. **Install**: Copy to `/custom/doliqonto` and activate in Dolibarr
2. **Configure**: Setup → Enter API credentials or connect via OAuth2
3. **Link Accounts**: Bank Accounts → Auto-link by IBAN
4. **Sync**: Transactions → Sync Transactions → Auto-Match All
5. **Reconcile**: Bank Reconciliation → Select account + period → Assign to statement + Download PDF

## Features

- ✅ Dual authentication (API Key + OAuth2)
- ✅ Auto-link bank accounts by IBAN
- ✅ Transaction synchronization
- ✅ Auto-matching with existing bank lines
- ✅ Smart auto-matching with invoices (name, amount, due date)
- ✅ Manual payment matching with invoices + inline search
- ✅ Manual bank line search (by label, reference, or statement ref — no amount/date constraints)
- ✅ Attachment sync (Qonto ↔ Dolibarr) with progress indicator
- ✅ Duplicate upload prevention (PRG pattern)
- ✅ Monthly statement reconciliation (assign `num_releve`, download Qonto PDF)
- ✅ Multi-language (EN, FR, DE, ES, IT, NL, PT)

## Requirements

- Dolibarr 20.0+
- PHP 7.0+
- MySQL/MariaDB
- Qonto business account

## Module Setup

### 1. Authentication

**Option A: API Key (Recommended)**
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
- Click "Match" button on any pending transaction
- Select a suggested invoice, or use the manual invoice search
- Use the **manual bank line search** for transactions that have no matching invoice (expense reports, taxes, etc.) — search by any part of the label or reference
- Confirm → Creates payment + bank line + links to transaction

### 5. Monthly Reconciliation

- Go to: DoliQonto → Bank Reconciliation
- Select the bank account and month
- Review the reconciliation table: Qonto transactions vs matched Dolibarr bank lines
- Click **"Assign to statement YYYYMM"**: sets `num_releve` on all matched bank lines for the period (those not yet assigned), making them appear in the native Dolibarr statement view
- Click **"Download Qonto PDF"**: fetches the official Qonto monthly statement PDF from the API and saves it in the correct Dolibarr directory (`{bank_dir}/{account_id}/statement/{YYYYMM}/`) so it appears directly in `account_statement_document.php`

## Key Files

```
class/qontoapi.class.php             # API wrapper: transactions, statements, attachments
class/qontotransaction.class.php     # Transaction model, matching logic
core/hooks/doliqontohooks.class.php  # Bank card hooks
transactions.php                     # Main transaction list
matching.php                         # Payment matching UI
reconciliation.php                   # Monthly statement reconciliation
attachments.php                      # Attachment sync UI
taxvalidation.php                    # VAT conflict resolution UI
```

## Database Tables

- `llx_qonto_transactions` — Synced transactions
- `llx_bank_account_extrafields` — Qonto↔Dolibarr account links
- `llx_qonto_attachments` — Attachment metadata and sync tracking (to/from Qonto)
- `llx_qonto_tax_validation` — VAT/tax conflict resolution between Qonto and Dolibarr

**Key relationships:**
- `fk_bank` links transactions to `llx_bank.rowid`
- Invoice links are accessed through: `fk_bank` → bank line → payment → invoice
- Match status is **derived from data** (not stored):
  - **Matched**: `fk_bank IS NOT NULL`
  - **Pending**: `fk_bank IS NULL AND ignored = 0`
  - **Ignored**: `ignored = 1`
- Reconciliation uses `llx_bank.num_releve` (standard Dolibarr field, format `YYYYMM`)

## Configuration Options

| Setting | Default | Description |
|---------|---------|-------------|
| Auth Method | `api_key` | API Key or OAuth2 |
| Sync Days Back | `30` | How far back to sync |
| Auto Sync | `No` | Hourly automatic sync via cron |
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

**Bank line not found in manual search**
- The manual bank line search covers all lines including already-linked ones
- Already-linked lines are shown greyed out with a confirmation prompt before re-linking

**Statement PDF not appearing in Dolibarr**
- Use the Download button in Bank Reconciliation (not the document tab of the bank account)
- The PDF is stored in `{bank_dir}/{account_id}/statement/{YYYYMM}/` and is visible in the native statement page

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

## License

GPL-3.0 — See LICENSE file

## Credits

Developer: Finta Ionut (ITIZED)  
Website: https://itized.com

## Version

**1.2.0** (April 11, 2026)
- Monthly bank statement reconciliation
- Manual bank line search in matching page
- Statement PDF downloaded to correct Dolibarr directory with original filename
- 7 languages: EN, FR, DE, ES, IT, NL, PT
