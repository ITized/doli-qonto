# DoliQonto v1.0.0 - Deployment Guide

## 📋 Pre-Deployment Checklist

### 1. Backup Database (Recommended)
```sql
-- Backup important tables before installation
mysqldump -u your_user -p your_database llx_bank_account > bank_account_backup.sql
```

### 2. Review Installation Requirements
- Dolibarr 20.0+
- PHP 7.0+
- MySQL/MariaDB
- Qonto business account with API access

---

## 🚀 Deployment Steps

### Step 1: Upload Module
Upload all module files to `/custom/doliqonto/` or `/htdocs/doliqonto/`

### Step 2: Activate Module
1. Go to Home → Setup → Modules
2. Find "DoliQonto" in the modules list
3. Click "Activate"
4. Module will automatically create database tables

### Step 3: Configure API Access
1. Go to DoliQonto → Setup
2. Choose authentication method:
   - **API Key (Recommended)**: Enter Organization Slug + API Key
   - **OAuth2**: Click "Connect with Qonto"
3. Click "Test Connection" to verify

### Step 4: Link Bank Accounts
1. Go to Bank → Bank Accounts
2. The module will auto-detect Qonto accounts by IBAN
3. Verify the linkage in DoliQonto → Setup → Bank Accounts

### Step 5: Initial Sync
1. Go to DoliQonto → Transactions
2. Click "Sync Transactions"
3. Review imported transactions
4. Click "Auto-Match All" to match with existing bank lines

---

## 🔧 Features Configuration

### Transaction Synchronization
- **Manual Sync**: DoliQonto → Transactions → Sync Transactions
- **Automatic Sync**: Configure cron job (Home → Tools → Scheduled Jobs)

### Payment Matching
- **Auto-Match**: Automatically matches Qonto transactions with existing Dolibarr bank lines
- **Manual Match**: Link transactions to customer/supplier invoices manually

### Attachment Management
- Download attachments from Qonto to Dolibarr
- Upload attachments from Dolibarr to Qonto
- Bidirectional synchronization

### Tax Validation
- Compare VAT information between Qonto and Dolibarr
- Resolve tax discrepancies through dedicated interface

---

## ✅ Post-Installation Verification

### Test Features
1. **Transaction Sync** (`transactions.php`)
   - ✅ New transactions sync correctly
   - ✅ Transaction details display properly

2. **Matching Page** (`matching.php`)
   - ✅ Pending transactions display
   - ✅ Matched transactions show invoice links
   - ✅ Ignore functionality works

3. **Attachments Page** (`attachments.php`)
   - ✅ Qonto attachment count displays
   - ✅ Dolibarr attachment count displays
   - ✅ Refresh attachments feature works

4. **Tax Validation** (`taxvalidation.php`)
   - ✅ Tax conflicts are identified
   - ✅ Resolution interface works

---

## 🐛 Troubleshooting

### Connection Issues
- Verify API credentials are correct
- Check firewall allows HTTPS to thirdparty.qonto.com
- Verify Qonto API is operational

### Sync Issues
- Check module logs: Home → Tools → System Information → Error Logs
- Verify bank accounts are properly linked
- Ensure transactions exist in Qonto for the date range

### Matching Issues
- Verify bank lines exist in Dolibarr
- Check IBAN matching between Qonto and Dolibarr bank accounts
- Review transaction amounts and dates

---

## 📚 Additional Resources

- **Module Documentation**: See README.md and docs/ directory
- **API Documentation**: See class/qontoapi.class.php
- **Database Schema**: See sql/ directory

---

## 🔄 Updating the Module

### Future Updates
1. Download new version
2. Backup current installation and database
3. Replace module files
4. Run any migration scripts (will be provided in release notes)
5. Clear Dolibarr cache
6. Test functionality

---

## 📞 Support

For issues or questions:
- Check documentation in docs/ directory
- Review README.md for common solutions
- Contact module author (see module info page)

---

## 📄 License

This module is licensed under GPL-3.0. See LICENSE file for details.

**Version:** 1.0.0  
**Release Date:** November 30, 2025  
**Author:** Finta Ionut <ionut.finta@itized.com>
