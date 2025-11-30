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
 * \file    doliqonto/taxvalidation.php
 * \ingroup doliqonto
 * \brief   Validate and resolve tax conflicts between Qonto and Dolibarr
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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
dol_include_once('/doliqonto/class/qontotransaction.class.php');

// Load translation files required by the page
$langs->loadLangs(array("doliqonto@doliqonto", "bills", "other"));

// Get parameters
$action = GETPOST('action', 'aZ09');
$validation_id = GETPOST('id', 'int');
$chosen_source = GETPOST('chosen_source', 'alpha');

// Access control
if (!$user->rights->doliqonto->transaction->read) {
	accessforbidden();
}

/*
 * Actions
 */

if ($action == 'resolve' && $validation_id > 0 && $chosen_source) {
	$sql = "UPDATE ".MAIN_DB_PREFIX."qonto_tax_validation";
	$sql .= " SET status = 'resolved',";
	$sql .= " resolved_by = ".$user->id.",";
	$sql .= " resolution_date = '".$db->idate(dol_now())."',";
	$sql .= " chosen_source = '".$db->escape($chosen_source)."'";
	$sql .= " WHERE rowid = ".(int)$validation_id;
	
	$result = $db->query($sql);
	
	if ($result) {
		// Update transaction tax_conflict flag
		$sql2 = "SELECT fk_qonto_transaction FROM ".MAIN_DB_PREFIX."qonto_tax_validation";
		$sql2 .= " WHERE rowid = ".(int)$validation_id;
		
		$resql2 = $db->query($sql2);
		if ($resql2) {
			$obj = $db->fetch_object($resql2);
			if ($obj) {
				$sql3 = "UPDATE ".MAIN_DB_PREFIX."qonto_transactions";
				$sql3 .= " SET tax_conflict = 0";
				$sql3 .= " WHERE rowid = ".(int)$obj->fk_qonto_transaction;
				$db->query($sql3);
			}
		}
		
		setEventMessages($langs->trans("TaxConflictResolved"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'ignore' && $validation_id > 0) {
	$sql = "UPDATE ".MAIN_DB_PREFIX."qonto_tax_validation";
	$sql .= " SET status = 'ignored',";
	$sql .= " resolved_by = ".$user->id.",";
	$sql .= " resolution_date = '".$db->idate(dol_now())."'";
	$sql .= " WHERE rowid = ".(int)$validation_id;
	
	$result = $db->query($sql);
	
	if ($result) {
		setEventMessages($langs->trans("TaxConflictIgnored"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans("TaxValidation");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($title);

// Get pending tax validations
$sql = "SELECT tv.rowid, tv.qonto_vat_amount, tv.qonto_vat_rate,";
$sql .= " tv.dolibarr_vat_amount, tv.dolibarr_vat_rate, tv.status,";
$sql .= " t.transaction_id, t.label, t.amount, t.currency, t.emitted_at, t.fk_bank";
$sql .= " FROM ".MAIN_DB_PREFIX."qonto_tax_validation as tv";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."qonto_transactions as t ON tv.fk_qonto_transaction = t.rowid";
$sql .= " WHERE tv.entity = ".$conf->entity;
$sql .= " AND tv.status = 'pending'";
$sql .= " ORDER BY tv.date_creation DESC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	if ($num > 0) {
		print '<div class="qonto-info-box">';
		print '<strong>'.$langs->trans("TaxConflictsFound").':</strong> ';
		print $langs->trans("TaxConflictsFoundDesc", $num);
		print '</div>';
		
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			
			print '<div class="qonto-tax-conflict">';
			print '<h3>'.$langs->trans("TaxConflict").' #'.($i+1).'</h3>';
			
			// Transaction details
			print '<div class="fichecenter">';
			print '<table class="border centpercent">';
			print '<tr><td class="titlefield">'.$langs->trans("TransactionId").'</td><td>'.dol_escape_htmltag($obj->transaction_id).'</td></tr>';
			print '<tr><td>'.$langs->trans("Date").'</td><td>'.dol_print_date($db->jdate($obj->emitted_at), 'day').'</td></tr>';
			print '<tr><td>'.$langs->trans("Label").'</td><td>'.dol_escape_htmltag($obj->label).'</td></tr>';
			print '<tr><td>'.$langs->trans("Amount").'</td><td>'.price($obj->amount, 0, $langs, 1, -1, -1, $obj->currency).'</td></tr>';
			
			// Invoice link through bank line
			if (!empty($obj->fk_bank)) {
				$transaction = new QontoTransaction($db);
				$transaction->fetch($obj->rowid);
				$invoiceInfo = $transaction->getFirstLinkedInvoice();
				if ($invoiceInfo) {
					$invoice = $invoiceInfo['object'];
					print '<tr><td>';
					if ($invoiceInfo['type'] == 'customer') {
						print $langs->trans("Invoice");
					} else {
						print $langs->trans("SupplierInvoice");
					}
					print '</td><td>';
					print $invoice->getNomUrl(1);
					print '</td></tr>';
				}
			}
			
			print '</table>';
			print '</div>';
			
			// Tax comparison
			print '<h4>'.$langs->trans("SelectCorrectTaxInformation").'</h4>';
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="resolve">';
			print '<input type="hidden" name="id" value="'.$obj->rowid.'">';
			
			print '<div class="qonto-tax-comparison">';
			
			// Qonto data
			print '<div class="qonto-tax-source" onclick="this.querySelector(\'input\').checked=true">';
			print '<input type="radio" name="chosen_source" value="qonto" required> ';
			print '<div class="qonto-tax-label">'.$langs->trans("QontoData").'</div>';
			print '<table class="noborder">';
			print '<tr><td>'.$langs->trans("VATRate").'</td><td><strong>'.price($obj->doliqonto_vat_rate, 0, $langs, 0, 0).'%</strong></td></tr>';
			print '<tr><td>'.$langs->trans("VATAmount").'</td><td><strong>'.price($obj->doliqonto_vat_amount, 0, $langs, 1, -1, -1, $obj->currency).'</strong></td></tr>';
			print '</table>';
			print '</div>';
			
			// Dolibarr data
			print '<div class="qonto-tax-source" onclick="this.querySelector(\'input\').checked=true">';
			print '<input type="radio" name="chosen_source" value="dolibarr" required> ';
			print '<div class="qonto-tax-label">'.$langs->trans("DolibarrData").'</div>';
			print '<table class="noborder">';
			print '<tr><td>'.$langs->trans("VATRate").'</td><td><strong>'.price($obj->dolibarr_vat_rate, 0, $langs, 0, 0).'%</strong></td></tr>';
			print '<tr><td>'.$langs->trans("VATAmount").'</td><td><strong>'.price($obj->dolibarr_vat_amount, 0, $langs, 1, -1, -1, $obj->currency).'</strong></td></tr>';
			print '</table>';
			print '</div>';
			
			print '</div>';
			
			print '<div class="center" style="margin-top: 15px;">';
			print '<button type="submit" class="button">'.$langs->trans("Resolve").'</button> ';
			print '<a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'?action=ignore&id='.$obj->rowid.'">'.$langs->trans("Ignore").'</a>';
			print '</div>';
			
			print '</form>';
			print '</div>';
			print '<br>';
			
			$i++;
		}
	} else {
		print '<div class="qonto-success-box">';
		print $langs->trans("NoTaxConflicts");
		print '</div>';
	}
	
	$db->free($resql);
} else {
	dol_print_error($db);
}

// Show resolved conflicts
print '<br><h3>'.$langs->trans("ResolvedConflicts").'</h3>';

$sql = "SELECT tv.rowid, tv.qonto_vat_amount, tv.qonto_vat_rate,";
$sql .= " tv.dolibarr_vat_amount, tv.dolibarr_vat_rate, tv.chosen_source,";
$sql .= " tv.resolution_date, t.transaction_id, t.label, u.login";
$sql .= " FROM ".MAIN_DB_PREFIX."qonto_tax_validation as tv";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."qonto_transactions as t ON tv.fk_qonto_transaction = t.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON tv.resolved_by = u.rowid";
$sql .= " WHERE tv.entity = ".$conf->entity;
$sql .= " AND tv.status = 'resolved'";
$sql .= " ORDER BY tv.resolution_date DESC";
$sql .= " LIMIT 20";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	if ($num > 0) {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("TransactionId").'</th>';
		print '<th>'.$langs->trans("Label").'</th>';
		print '<th>'.$langs->trans("ChosenSource").'</th>';
		print '<th>'.$langs->trans("ResolvedBy").'</th>';
		print '<th>'.$langs->trans("ResolvedDate").'</th>';
		print '</tr>';
		
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			
			print '<tr class="oddeven">';
			print '<td>'.substr($obj->transaction_id, 0, 20).'...</td>';
			print '<td>'.dol_escape_htmltag($obj->label).'</td>';
			print '<td>';
			if ($obj->chosen_source == 'qonto') {
				print '<span class="badge badge-info">'.$langs->trans("Qonto").'</span>';
			} else {
				print '<span class="badge badge-info">'.$langs->trans("Dolibarr").'</span>';
			}
			print '</td>';
			print '<td>'.$obj->login.'</td>';
			print '<td>'.dol_print_date($db->jdate($obj->resolution_date), 'dayhour').'</td>';
			print '</tr>';
			
			$i++;
		}
		
		print '</table>';
	} else {
		print '<div class="opacitymedium">'.$langs->trans("NoResolvedConflicts").'</div>';
	}
	
	$db->free($resql);
}

// End of page
llxFooter();
$db->close();
