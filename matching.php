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
 * \file    qonto/matching.php
 * \ingroup qonto
 * \brief   Match Qonto transactions with invoices
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
dol_include_once('/doliqonto/class/qontotransaction.class.php');

// Load translation files required by the page
$langs->loadLangs(array("doliqonto@doliqonto", "bills", "companies", "banks"));

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$invoice_id = GETPOST('invoice_id', 'int');
$invoice_type = GETPOST('invoice_type', 'alpha');
$search_invoice = GETPOST('search_invoice', 'alpha');

// Access control
if (!$user->rights->doliqonto->payment->match) {
	accessforbidden();
}

$transaction = new QontoTransaction($db);
if ($id > 0) {
	$result = $transaction->fetch($id);
	if ($result <= 0) {
		dol_print_error($db, $transaction->error);
		exit;
	}
}

/*
 * Actions
 */

if ($action == 'link_bank_line' && GETPOST('bank_line_id', 'int') > 0) {
	// Link transaction directly to a bank line
	$bank_line_id = GETPOST('bank_line_id', 'int');
	
	$result = $transaction->linkToBankLine($bank_line_id, $user);
	
	if ($result > 0) {
		setEventMessages($langs->trans("TransactionLinkedToBankLine"), null, 'mesgs');
		header('Location: transactions.php');
		exit;
	} else {
		setEventMessages($transaction->error, null, 'errors');
	}
}

if ($action == 'match' && $invoice_id > 0 && $invoice_type) {
	// Match transaction with invoice
	$result = $transaction->matchWithInvoice($invoice_id, $invoice_type, $user);
	
	if ($result > 0) {
		// Create payment
		if ($invoice_type == 'customer') {
			$invoice = new Facture($db);
			$invoice->fetch($invoice_id);
			
			// Get Dolibarr bank account from Qonto bank account
			$sql = "SELECT ba.rowid FROM ".MAIN_DB_PREFIX."bank_account as ba";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
			$sql .= " WHERE ef.qonto_bank_id = '".$db->escape($transaction->bank_account_id)."'";
			$sql .= " AND ba.entity = ".$conf->entity;
			$resql = $db->query($sql);
			$bankAccountId = 0;
			if ($resql && $db->num_rows($resql) > 0) {
				$objBank = $db->fetch_object($resql);
				$bankAccountId = $objBank->rowid;
			}
			
			$payment = new Paiement($db);
			$payment->datepaye = $transaction->settled_at ? $transaction->settled_at : $transaction->emitted_at;
			$payment->amounts = array($invoice_id => abs($transaction->amount));
			$payment->paiementid = dol_getIdFromCode($db, 'VIR', 'c_paiement', 'code', 'id');
			$payment->num_payment = $transaction->reference ? $transaction->reference : $transaction->transaction_id;
			$payment->note_private = 'Qonto transaction: ' . $transaction->transaction_id;
			
			$paymentId = $payment->create($user);
			if ($paymentId > 0) {
				// Add to bank with proper account ID
				if ($bankAccountId > 0) {
					$bank_line_id = $payment->addPaymentToBank($user, 'payment', '(paiement)', $bankAccountId, '', '');
					if ($bank_line_id > 0) {
						$transaction->fk_bank = $bank_line_id;
					}
				}
				
				// Re-fetch invoice to get updated payment state
				$invoice->fetch($invoice_id);
				
				// Check if invoice is fully paid and set status
				$remaintopay = price2num($invoice->getRemainToPay(), 'MT');
				if ($remaintopay == 0) {
					$result = $invoice->setPaid($user);
					if ($result < 0) {
						dol_syslog("Error setting invoice as paid: " . $invoice->error, LOG_WARNING);
					}
				}
				
				$transaction->fk_payment = $paymentId;
				$transaction->update($user);
				setEventMessages($langs->trans("PaymentCreated"), null, 'mesgs');
			} else {
				setEventMessages($payment->error, null, 'errors');
			}
		} elseif ($invoice_type == 'supplier') {
			$invoice = new FactureFournisseur($db);
			$invoice->fetch($invoice_id);
			
			// Get Dolibarr bank account from Qonto bank account
			$sql = "SELECT ba.rowid FROM ".MAIN_DB_PREFIX."bank_account as ba";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
			$sql .= " WHERE ef.qonto_bank_id = '".$db->escape($transaction->bank_account_id)."'";
			$sql .= " AND ba.entity = ".$conf->entity;
			$resql = $db->query($sql);
			$bankAccountId = 0;
			if ($resql && $db->num_rows($resql) > 0) {
				$objBank = $db->fetch_object($resql);
				$bankAccountId = $objBank->rowid;
			}
			
			$payment = new PaiementFourn($db);
			$payment->datepaye = $transaction->settled_at ? $transaction->settled_at : $transaction->emitted_at;
			$payment->amounts = array($invoice_id => abs($transaction->amount));
			$payment->paiementid = dol_getIdFromCode($db, 'VIR', 'c_paiement', 'code', 'id');
			$payment->num_paiement = $transaction->reference ? $transaction->reference : $transaction->transaction_id;
			$payment->note_private = 'Qonto transaction: ' . $transaction->transaction_id;
			
			$paymentId = $payment->create($user);
			if ($paymentId > 0) {
				// Add to bank with proper account ID
				if ($bankAccountId > 0) {
					$bank_line_id = $payment->addPaymentToBank($user, 'payment_supplier', '(paiement)', $bankAccountId, '', '');
					if ($bank_line_id > 0) {
						$transaction->fk_bank = $bank_line_id;
					}
				}
				
				// Re-fetch invoice to get updated payment state
				$invoice->fetch($invoice_id);
				
				// Check if invoice is fully paid and set status
				$remaintopay = price2num($invoice->getRemainToPay(), 'MT');
				if ($remaintopay == 0) {
					$result = $invoice->setPaid($user);
					if ($result < 0) {
						dol_syslog("Error setting invoice as paid: " . $invoice->error, LOG_WARNING);
					}
				}
				
				$transaction->fk_payment = $paymentId;
				$transaction->update($user);
				setEventMessages($langs->trans("PaymentCreated"), null, 'mesgs');
			} else {
				setEventMessages($payment->error, null, 'errors');
			}
		}
		
		header('Location: transactions.php');
		exit;
	} else {
		setEventMessages($transaction->error, null, 'errors');
	}
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("MatchTransaction");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($title);

// Transaction details
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

print '<tr><td class="titlefield">'.$langs->trans("TransactionId").'</td><td>'.dol_escape_htmltag($transaction->transaction_id).'</td></tr>';
print '<tr><td>'.$langs->trans("Date").'</td><td>'.dol_print_date($transaction->emitted_at, 'day').'</td></tr>';
print '<tr><td>'.$langs->trans("Amount").'</td><td>'.price($transaction->amount, 0, $langs, 1, -1, -1, $transaction->currency).'</td></tr>';
print '<tr><td>'.$langs->trans("Side").'</td><td>';
if ($transaction->side == 'credit') {
	print '<span class="badge badge-status4">'.$langs->trans('Credit').'</span>';
} else {
	print '<span class="badge badge-status8">'.$langs->trans('Debit').'</span>';
}
print '</td></tr>';
print '<tr><td>'.$langs->trans("Label").'</td><td>'.dol_escape_htmltag($transaction->label).'</td></tr>';
print '<tr><td>'.$langs->trans("Counterparty").'</td><td>'.dol_escape_htmltag($transaction->counterparty_name).'</td></tr>';
if ($transaction->reference) {
	print '<tr><td>'.$langs->trans("Reference").'</td><td>'.dol_escape_htmltag($transaction->reference).'</td></tr>';
}

print '</table>';

print '</div>';
print '</div>';

print '<div class="clearboth"></div><br>';

// Search for matching invoices
print '<div class="fichecenter">';

// Determine invoice type based on transaction side
if ($transaction->side == 'credit') {
	// Credit = money received = customer invoice
	$invoice_type = 'customer';
	print '<h3>'.$langs->trans("SuggestedCustomerInvoices").'</h3>';
	
	$transactionDate = date('Y-m-d', strtotime($transaction->settled_at ? $transaction->settled_at : $transaction->emitted_at));
	$sql = "SELECT f.rowid, f.ref, f.total_ttc, f.datef as date_invoice, f.date_lim_reglement, f.paye, s.nom as company_name";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
	$sql .= " WHERE f.entity = ".$conf->entity;
	$sql .= " AND f.paye = 0";
	$sql .= " AND f.fk_statut = 1"; // Validated
	$sql .= " AND ABS(f.total_ttc - ".abs($transaction->amount).") < ".max(1, abs($transaction->amount) * 0.2); // 20% tolerance for forex
	// Order by: 1) company name matches label, 2) exact amount, 3) due date closest to transaction date
	$sql .= " ORDER BY";
	$sql .= " CASE WHEN s.nom = '".$db->escape($transaction->label)."' THEN 0 ELSE 1 END,";
	$sql .= " ABS(f.total_ttc - ".abs($transaction->amount)."),";
	$sql .= " ABS(DATEDIFF(COALESCE(f.date_lim_reglement, f.datef), '".$db->escape($transactionDate)."'))";
	$sql .= " LIMIT 20";
} else {
	// Debit = money sent = supplier invoice
	$invoice_type = 'supplier';
	print '<h3>'.$langs->trans("SuggestedSupplierInvoices").'</h3>';
	
	$transactionDate = date('Y-m-d', strtotime($transaction->settled_at ? $transaction->settled_at : $transaction->emitted_at));
	$sql = "SELECT f.rowid, f.ref, f.total_ttc, f.datef as date_invoice, f.date_lim_reglement, f.paye, s.nom as company_name";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
	$sql .= " WHERE f.entity = ".$conf->entity;
	$sql .= " AND f.paye = 0";
	$sql .= " AND f.fk_statut = 1"; // Validated
	$sql .= " AND ABS(f.total_ttc - ".abs($transaction->amount).") < ".max(1, abs($transaction->amount) * 0.2); // 20% tolerance for forex
	// Order by: 1) company name matches label, 2) exact amount, 3) due date closest to transaction date
	$sql .= " ORDER BY";
	$sql .= " CASE WHEN s.nom = '".$db->escape($transaction->label)."' THEN 0 ELSE 1 END,";
	$sql .= " ABS(f.total_ttc - ".abs($transaction->amount)."),";
	$sql .= " ABS(DATEDIFF(COALESCE(f.date_lim_reglement, f.datef), '".$db->escape($transactionDate)."'))";
	$sql .= " LIMIT 20";
}

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	if ($num > 0) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="match">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="hidden" name="invoice_type" value="'.$invoice_type.'">';
		
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("Ref").'</th>';
		print '<th>'.$langs->trans("Company").'</th>';
		print '<th>'.$langs->trans("DateInvoice").'</th>';
		print '<th>'.$langs->trans("DateDue").'</th>';
		print '<th class="right">'.$langs->trans("Amount").'</th>';
		print '<th class="center">'.$langs->trans("Status").'</th>';
		print '<th class="center">'.$langs->trans("Action").'</th>';
		print '</tr>';
		
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			
			print '<tr class="oddeven">';
			if ($invoice_type == 'supplier') {
				print '<td><a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
			} else {
				print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
			}
			print '<td>'.dol_escape_htmltag($obj->company_name).'</td>';
			print '<td>'.dol_print_date($db->jdate($obj->date_invoice), 'day').'</td>';
			print '<td>'.dol_print_date($db->jdate($obj->date_lim_reglement), 'day').'</td>';
			print '<td class="right">'.price($obj->total_ttc).'</td>';
			print '<td class="center">';
			if ($obj->paye) {
				print '<span class="badge badge-status4">'.$langs->trans("Paid").'</span>';
			} else {
				print '<span class="badge badge-status1">'.$langs->trans("Unpaid").'</span>';
			}
			print '</td>';
			print '<td class="center">';
			print '<button type="submit" name="invoice_id" value="'.$obj->rowid.'" class="button">'.$langs->trans("Match").'</button>';
			print '</td>';
			print '</tr>';
			
			$i++;
		}
		
		print '</table>';
		print '</form>';
	} else {
		print '<div class="info">'.$langs->trans("NoMatchingInvoicesFound").'</div>';
	}
	
	// Manual search section — always shown
	print '<br><h3>'.$langs->trans("ManualSearch").'</h3>';
	print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<div class="inline-block valignmiddle">';
	print '<input type="text" name="search_invoice" class="flat minwidth200" placeholder="'.$langs->trans("SearchInvoicePlaceholder").'" value="'.dol_escape_htmltag($search_invoice).'">';
	print ' <button type="submit" class="button">'.$langs->trans("Search").'</button>';
	print '</div>';
	print '</form>';
	
	// Show search results if search was performed
	if (!empty($search_invoice)) {
		$searchTerm = $db->escape($search_invoice);
		if ($invoice_type == 'supplier') {
			$sqlSearch = "SELECT f.rowid, f.ref, f.total_ttc, f.datef as date_invoice, f.date_lim_reglement, f.paye, s.nom as company_name, f.multicurrency_code";
			$sqlSearch .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f";
			$sqlSearch .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
			$sqlSearch .= " WHERE f.entity = ".$conf->entity;
			$sqlSearch .= " AND f.paye = 0";
			$sqlSearch .= " AND f.fk_statut = 1";
			$sqlSearch .= " AND (f.ref LIKE '%".$searchTerm."%' OR s.nom LIKE '%".$searchTerm."%')";
		} else {
			$sqlSearch = "SELECT f.rowid, f.ref, f.total_ttc, f.datef as date_invoice, f.date_lim_reglement, f.paye, s.nom as company_name, f.multicurrency_code";
			$sqlSearch .= " FROM ".MAIN_DB_PREFIX."facture as f";
			$sqlSearch .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
			$sqlSearch .= " WHERE f.entity = ".$conf->entity;
			$sqlSearch .= " AND f.paye = 0";
			$sqlSearch .= " AND f.fk_statut = 1";
			$sqlSearch .= " AND (f.ref LIKE '%".$searchTerm."%' OR s.nom LIKE '%".$searchTerm."%')";
		}
		$sqlSearch .= " ORDER BY f.datef DESC";
		$sqlSearch .= " LIMIT 50";
		
		$resqlSearch = $db->query($sqlSearch);
		if ($resqlSearch) {
			$numSearch = $db->num_rows($resqlSearch);
			if ($numSearch > 0) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="match">';
				print '<input type="hidden" name="id" value="'.$id.'">';
				print '<input type="hidden" name="invoice_type" value="'.$invoice_type.'">';
				
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre">';
				print '<th>'.$langs->trans("Ref").'</th>';
				print '<th>'.$langs->trans("Company").'</th>';
				print '<th>'.$langs->trans("DateInvoice").'</th>';
				print '<th>'.$langs->trans("DateDue").'</th>';
				print '<th class="right">'.$langs->trans("Amount").'</th>';
				print '<th class="center">'.$langs->trans("Status").'</th>';
				print '<th class="center">'.$langs->trans("Action").'</th>';
				print '</tr>';
				
				$j = 0;
				while ($j < $numSearch) {
					$objS = $db->fetch_object($resqlSearch);
					
					print '<tr class="oddeven">';
					if ($invoice_type == 'supplier') {
						print '<td><a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$objS->rowid.'" target="_blank">'.dol_escape_htmltag($objS->ref).'</a></td>';
					} else {
						print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$objS->rowid.'" target="_blank">'.dol_escape_htmltag($objS->ref).'</a></td>';
					}
					print '<td>'.dol_escape_htmltag($objS->company_name).'</td>';
					print '<td>'.dol_print_date($db->jdate($objS->date_invoice), 'day').'</td>';
					print '<td>'.dol_print_date($db->jdate($objS->date_lim_reglement), 'day').'</td>';
					print '<td class="right">'.price($objS->total_ttc);
					if (!empty($objS->multicurrency_code) && $objS->multicurrency_code != $conf->currency) {
						print ' <span class="opacitymedium">('.$objS->multicurrency_code.')</span>';
					}
					print '</td>';
					print '<td class="center">';
					if ($objS->paye) {
						print '<span class="badge badge-status4">'.$langs->trans("Paid").'</span>';
					} else {
						print '<span class="badge badge-status1">'.$langs->trans("Unpaid").'</span>';
					}
					print '</td>';
					print '<td class="center">';
					print '<button type="submit" name="invoice_id" value="'.$objS->rowid.'" class="button">'.$langs->trans("Match").'</button>';
					print '</td>';
					print '</tr>';
					
					$j++;
				}
				
				print '</table>';
				print '</form>';
			} else {
				print '<div class="info">'.$langs->trans("NoResults").'</div>';
			}
			$db->free($resqlSearch);
		}
	}
	
	$db->free($resql);
} else {
	dol_print_error($db);
}

print '</div>';

// Suggested bank lines section
print '<div class="fichecenter">';
print '<br><hr><br>';
print '<h3>'.$langs->trans("SuggestedBankLines").'</h3>';

print '<div class="warning">';
print $langs->trans("BankLineMatchingWarning");
print '</div>';
print '<br>';

// Get Dolibarr bank account from Qonto bank account
$sql = "SELECT ba.rowid as dolibarr_account_id FROM ".MAIN_DB_PREFIX."bank_account as ba";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
$sql .= " WHERE ef.qonto_bank_id = '".$db->escape($transaction->bank_account_id)."'";
$sql .= " AND ba.entity = ".$conf->entity;

$resql = $db->query($sql);
$dolibarrAccountId = 0;

if ($resql && $db->num_rows($resql) > 0) {
	$obj = $db->fetch_object($resql);
	$dolibarrAccountId = $obj->dolibarr_account_id;
}

if ($dolibarrAccountId > 0) {
	// Search for bank lines with flexible matching
	// Match criteria: same account, similar amount (±10%), date within 7 days
	$settledTs = strtotime($transaction->settled_at);
	$dateFrom = date('Y-m-d', $settledTs - (7 * 86400)); // 7 days before
	$dateTo = date('Y-m-d', $settledTs + (7 * 86400)); // 7 days after
	$amountMin = abs($transaction->amount) * 0.9; // -10%
	$amountMax = abs($transaction->amount) * 1.1; // +10%
	
	// Determine the sign based on side
	$signCondition = ($transaction->side == 'debit') ? "b.amount < 0" : "b.amount > 0";
	
	$sql = "SELECT b.rowid, b.datev, b.amount, b.label, b.num_releve";
	$sql .= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sql .= " WHERE b.fk_account = ".(int)$dolibarrAccountId;
	$sql .= " AND ".$signCondition; // Same direction (debit/credit)
	$sql .= " AND ABS(b.amount) >= ".$amountMin;
	$sql .= " AND ABS(b.amount) <= ".$amountMax;
	$sql .= " AND b.datev >= '".$db->escape($dateFrom)."'";
	$sql .= " AND b.datev <= '".$db->escape($dateTo)."'";
	$sql .= " AND b.rowid NOT IN (";
	$sql .= "   SELECT fk_bank FROM ".MAIN_DB_PREFIX."qonto_transactions WHERE fk_bank IS NOT NULL";
	$sql .= " )";
	$sql .= " ORDER BY ABS(ABS(b.amount) - ".abs($transaction->amount)."), ABS(DATEDIFF(b.datev, '".$db->escape(date('Y-m-d', $settledTs))."'))";
	$sql .= " LIMIT 20";
	
	$resql = $db->query($sql);
	
	if ($resql) {
		$num = $db->num_rows($resql);
		
		if ($num > 0) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="link_bank_line">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<th>'.$langs->trans("Date").'</th>';
			print '<th class="right">'.$langs->trans("Amount").'</th>';
			print '<th>'.$langs->trans("Label").'</th>';
			print '<th>'.$langs->trans("BankStatementRef").'</th>';
			print '<th class="center">'.$langs->trans("Action").'</th>';
			print '</tr>';
			
			$i = 0;
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				
				// Calculate differences for display
				$dateDiff = round(abs(($settledTs - strtotime($obj->datev)) / 86400));
				$amountDiff = abs($obj->amount) - abs($transaction->amount);
				
				print '<tr class="oddeven">';
				print '<td>'.dol_print_date($db->jdate($obj->datev), 'day');
				if ($dateDiff > 0) {
					print ' <span class="opacitymedium">('.$dateDiff.' '.$langs->trans("days").')</span>';
				}
				print '</td>';
				print '<td class="right">'.price($obj->amount);
				if (abs($amountDiff) > 0.01) {
					print ' <span class="opacitymedium">('.($amountDiff >= 0 ? '+' : '').price($amountDiff).')</span>';
				}
				print '</td>';
				print '<td>'.dol_escape_htmltag(dol_trunc($obj->label, 50)).'</td>';
				print '<td>'.dol_escape_htmltag($obj->num_releve).'</td>';
				print '<td class="center">';
				print '<button type="submit" name="bank_line_id" value="'.$obj->rowid.'" class="button">'.$langs->trans("Link").'</button>';
				print '</td>';
				print '</tr>';
				
				$i++;
			}
			
			print '</table>';
			print '</form>';
		} else {
			print '<div class="info">'.$langs->trans("NoMatchingBankLinesFound").'</div>';
		}
		
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
} else {
	print '<div class="warning">'.$langs->trans("BankAccountNotLinked").'</div>';
}

print '</div>';

// End of page
llxFooter();
$db->close();
