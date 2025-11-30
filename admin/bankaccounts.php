<?php
/* Copyright (C) 2025 Finta Ionut <ionut.finta@itized.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    doliqonto/admin/bankaccounts.php
 * \ingroup doliqonto
 * \brief   Qonto bank accounts mapping page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/compta/bank/class/account.class.php";
require_once '../lib/qonto.lib.php';
require_once '../class/qontoapi.class.php';

// Translations
$langs->loadLangs(array("admin", "banks", "doliqonto@doliqonto"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */

if ($action == 'link_account') {
	$dolibarr_bank_id = GETPOST('dolibarr_bank_id', 'int');
	$qonto_bank_id = GETPOST('qonto_bank_id', 'alphanohtml');
	$qonto_name = GETPOST('qonto_name', 'alphanohtml');
	
	if ($dolibarr_bank_id > 0 && !empty($qonto_bank_id)) {
		// Store mapping in extrafields
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."bank_account_extrafields (fk_object, qonto_bank_id, qonto_name)";
		$sql .= " VALUES (".$dolibarr_bank_id.", '".$db->escape($qonto_bank_id)."', '".$db->escape($qonto_name)."')";
		$sql .= " ON DUPLICATE KEY UPDATE qonto_bank_id='".$db->escape($qonto_bank_id)."', qonto_name='".$db->escape($qonto_name)."'";
		
		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans("QontoBankAccountLinked"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans("ErrorMissingParameters"), null, 'errors');
	}
}

if ($action == 'unlink_account') {
	$dolibarr_bank_id = GETPOST('dolibarr_bank_id', 'int');
	
	if ($dolibarr_bank_id > 0) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."bank_account_extrafields";
		$sql .= " SET qonto_bank_id = NULL, qonto_name = NULL";
		$sql .= " WHERE fk_object = ".$dolibarr_bank_id;
		
		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans("QontoBankAccountUnlinked"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
}

if ($action == 'fetch_qonto_accounts') {
	$qontoApi = new QontoApi($db);
	$result = $qontoApi->getOrganization();
	
	if ($result === false) {
		setEventMessages($qontoApi->error, null, 'errors');
	} else {
		setEventMessages($langs->trans("QontoAccountsFetched"), null, 'mesgs');
	}
}

if ($action == 'auto_link_accounts') {
	$qontoApi = new QontoApi($db);
	$qontoOrg = $qontoApi->getOrganization();
	
	if ($qontoOrg === false) {
		setEventMessages($qontoApi->error, null, 'errors');
	} elseif (!empty($qontoOrg['organization']['bank_accounts'])) {
		$qontoAccounts = $qontoOrg['organization']['bank_accounts'];
		$linked = 0;
		$skipped = 0;
		
		// Get all Dolibarr bank accounts
		$sql = "SELECT ba.rowid, ba.ref, ba.iban_prefix as iban, ef.qonto_bank_id";
		$sql .= " FROM ".MAIN_DB_PREFIX."bank_account as ba";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
		$sql .= " WHERE ba.entity IN (".getEntity('bank_account').")";
		$sql .= " AND ba.iban_prefix IS NOT NULL AND ba.iban_prefix != ''";
		
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				// Skip if already linked
				if (!empty($obj->qonto_bank_id)) {
					$skipped++;
					continue;
				}
				
				// Normalize Dolibarr IBAN (remove spaces)
				$doliIban = str_replace(' ', '', $obj->iban);
				
				// Find matching Qonto account by IBAN
				foreach ($qontoAccounts as $qAccount) {
					$qontoIban = str_replace(' ', '', $qAccount['iban']);
					
					if ($doliIban === $qontoIban) {
						// Use 'id' field from Qonto API
						$qontoBankId = $qAccount['id'];
						$qontoName = $qAccount['name'];
						
						// Check if extrafields row exists
						$sql2 = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."bank_account_extrafields WHERE fk_object = ".$obj->rowid;
						$resql2 = $db->query($sql2);
						$exists = false;
						if ($resql2) {
							$obj2 = $db->fetch_object($resql2);
							$exists = ($obj2->nb > 0);
							$db->free($resql2);
						}
						
						// Insert or update
						if ($exists) {
							$sql3 = "UPDATE ".MAIN_DB_PREFIX."bank_account_extrafields";
							$sql3 .= " SET qonto_bank_id = '".$db->escape($qontoBankId)."',";
							$sql3 .= " qonto_name = '".$db->escape($qontoName)."'";
							$sql3 .= " WHERE fk_object = ".$obj->rowid;
						} else {
							$sql3 = "INSERT INTO ".MAIN_DB_PREFIX."bank_account_extrafields";
							$sql3 .= " (fk_object, qonto_bank_id, qonto_name)";
							$sql3 .= " VALUES (".$obj->rowid.", '".$db->escape($qontoBankId)."', '".$db->escape($qontoName)."')";
						}
						
						if ($db->query($sql3)) {
							$linked++;
						} else {
							dol_syslog("Auto-link error for account ".$obj->rowid.": ".$db->lasterror(), LOG_ERR);
						}
						break;
					}
				}
			}
			$db->free($resql);
		}
		
		if ($linked > 0) {
			setEventMessages($linked.' '.$langs->trans("AccountsAutoLinked"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("NoAccountsToLink"), null, 'warnings');
		}
	}
}

/*
 * View
 */

$page_name = "QontoBankAccounts";

llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = qontoAdminPrepareHead();
print dol_get_fiche_head($head, 'bankaccounts', $langs->trans("ModuleSetup"), -1, 'doliqonto@doliqonto');

// Check if API is configured
$authMethod = getDolGlobalString('QONTO_AUTH_METHOD', 'api_key');
$isConfigured = false;

if ($authMethod == 'oauth2') {
	$accessToken = getDolGlobalString('QONTO_ACCESS_TOKEN');
	if (!empty($accessToken)) {
		$isConfigured = true;
	}
} else {
	$apiKey = getDolGlobalString('QONTO_API_KEY');
	$orgSlug = getDolGlobalString('QONTO_ORGANIZATION_SLUG');
	if (!empty($apiKey) && !empty($orgSlug)) {
		$isConfigured = true;
	}
}

if (!$isConfigured) {
	print '<div class="warning">'.$langs->trans("QontoAPINotConfigured").'</div>';
	print '<br><a class="button" href="setup.php">'.$langs->trans("ConfigureAPI").'</a>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

// Fetch Qonto bank accounts
$qontoApi = new QontoApi($db);
$qontoOrg = $qontoApi->getOrganization();
$qontoAccounts = array();

if ($qontoOrg !== false && !empty($qontoOrg['organization']['bank_accounts'])) {
	$qontoAccounts = $qontoOrg['organization']['bank_accounts'];
}

// Fetch Dolibarr bank accounts
$sql = "SELECT ba.rowid, ba.ref, ba.label, ba.number, ba.iban_prefix as iban,";
$sql .= " ef.qonto_bank_id, ef.qonto_name";
$sql .= " FROM ".MAIN_DB_PREFIX."bank_account as ba";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
$sql .= " WHERE ba.entity IN (".getEntity('bank_account').")";
$sql .= " ORDER BY ba.ref";

$resql = $db->query($sql);
$dolibarrAccounts = array();

if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		$dolibarrAccounts[] = $obj;
		$i++;
	}
	$db->free($resql);
}

print '<div class="info">';
print $langs->trans("QontoBankAccountsHelp");
print '</div>';

print '<br>';

// Display mapping table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("DolibarrBankAccount").'</th>';
print '<th>'.$langs->trans("IBAN").'</th>';
print '<th>'.$langs->trans("QontoBankAccount").'</th>';
print '<th class="center">'.$langs->trans("Action").'</th>';
print '</tr>';

if (empty($dolibarrAccounts)) {
	print '<tr><td colspan="4" class="opacitymedium center">'.$langs->trans("NoDolibarrBankAccounts").'</td></tr>';
} else {
	foreach ($dolibarrAccounts as $account) {
		print '<tr class="oddeven">';
		
		// Dolibarr account
		print '<td>';
		print '<strong>'.$account->ref.'</strong>';
		if (!empty($account->label)) {
			print ' - '.$account->label;
		}
		print '</td>';
		
		// Dolibarr IBAN
		print '<td>';
		print !empty($account->iban) ? $account->iban : '-';
		print '</td>';
		
		// Qonto mapping
		if (!empty($account->qonto_bank_id)) {
			print '<td>';
			print '<span class="badge badge-status4">'.$account->qonto_name.'</span>';
			print '</td>';
			
			print '<td class="center">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="unlink_account">';
			print '<input type="hidden" name="dolibarr_bank_id" value="'.$account->rowid.'">';
			print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans("Unlink").'">';
			print '</form>';
			print '</td>';
		} else {
			if (!empty($qontoAccounts)) {
				print '<td colspan="2">';
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="link_account">';
				print '<input type="hidden" name="dolibarr_bank_id" value="'.$account->rowid.'">';
				
				print '<select name="qonto_bank_id" class="flat minwidth300" required>';
				print '<option value="">-- '.$langs->trans("SelectQontoAccount").' --</option>';
				
				$preselectedName = '';
				$preselectedId = '';
				foreach ($qontoAccounts as $qAccount) {
					$selected = '';
					// Auto-match by IBAN if possible
					if (!empty($account->iban) && $account->iban == $qAccount['iban']) {
						$selected = ' selected="selected"';
						$preselectedName = $qAccount['name'];
					}
					
					$displayName = $qAccount['name'].' - '.$qAccount['iban'];
					if (!empty($qAccount['balance'])) {
						$displayName .= ' ('.price($qAccount['balance']).' '.$qAccount['currency'].')';
					}
					
					// Use id field as the value
					print '<option value="'.dol_escape_htmltag($qAccount['id']).'" ';
					print 'data-name="'.dol_escape_htmltag($qAccount['name']).'"';
					print $selected.'>';
					print dol_escape_htmltag($displayName);
					print '</option>';
				}
				
				print '</select> ';
				print '<input type="hidden" name="qonto_name" value="'.dol_escape_htmltag($preselectedName).'">';
				print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans("Link").'">';
				print '</form>';
				print '</td>';
			} else {
				print '<td>';
				print '<span class="opacitymedium">'.$langs->trans("NoQontoAccounts").'</span>';
				print '</td><td class="center">-</td>';
			}
		}
		
		print '</tr>';
	}
}

print '</table>';

print '<br>';

// Auto-link and Refresh buttons
print '<div class="center">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline-block; margin-right: 10px;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="auto_link_accounts">';
print '<input type="submit" class="button" value="'.$langs->trans("AutoLinkAccountsByIBAN").'">';
print '</form>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline-block;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="fetch_qonto_accounts">';
print '<input type="submit" class="button" value="'.$langs->trans("RefreshQontoAccounts").'">';
print '</form>';
print '</div>';

// JavaScript for auto-filling hidden fields
print '<script type="text/javascript">
function updateQontoData(select) {
	var selectedOption = select.options[select.selectedIndex];
	var name = selectedOption.getAttribute("data-name");
	var form = select.form;
	var hiddenField = form.querySelector(".qonto_name_field");
	if (hiddenField) {
		hiddenField.value = name || "";
	}
}

function ensureQontoNameSet(form) {
	var selectElement = form.querySelector("select[name=qonto_bank_id]");
	var hiddenField = form.querySelector(".qonto_name_field");
	
	if (selectElement && hiddenField) {
		var selectedOption = selectElement.options[selectElement.selectedIndex];
		if (selectedOption && selectedOption.value) {
			var name = selectedOption.getAttribute("data-name");
			hiddenField.value = name || "";
		}
	}
	return true;
}
</script>';

print dol_get_fiche_end();

// Page end
llxFooter();
$db->close();
