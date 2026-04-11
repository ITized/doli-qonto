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
 * \file    doliqonto/reconciliation.php
 * \ingroup doliqonto
 * \brief   Monthly statement reconciliation: assign Dolibarr bank lines to the right
 *           num_releve and attach the Qonto monthly PDF to the bank account documents.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/doliqonto/class/qontoapi.class.php');

$langs->loadLangs(array("doliqonto@doliqonto", "banks", "other"));

// Parameters
$action     = GETPOST('action', 'aZ09');
$account_id = GETPOSTINT('account_id'); // Dolibarr bank account rowid
$period     = GETPOST('period', 'alphanohtml'); // Qonto format: MM-YYYY  e.g. "02-2026"

// Access control
if (!$user->rights->doliqonto->transaction->read) {
	accessforbidden();
}

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

/**
 * Convert Qonto period "MM-YYYY" to Dolibarr num_releve "YYYYMM"
 *
 * @param string $qontoPeriod e.g. "02-2026"
 * @return string             e.g. "202602"
 */
function qontoPeriodToNumReleve($qontoPeriod)
{
	$parts = explode('-', $qontoPeriod);
	if (count($parts) !== 2) {
		return '';
	}
	return $parts[1] . $parts[0]; // YYYY + MM
}

/**
 * Convert Dolibarr num_releve "YYYYMM" to human-readable month label
 *
 * @param string $numReleve e.g. "202602"
 * @param Translate $langs
 * @return string e.g. "February 2026"
 */
function numReleveToLabel($numReleve, $langs)
{
	if (strlen($numReleve) !== 6) {
		return $numReleve;
	}
	$year  = substr($numReleve, 0, 4);
	$month = (int) substr($numReleve, 4, 2);
	$monthNames = array(
		1  => $langs->trans('January'),   2  => $langs->trans('February'),
		3  => $langs->trans('March'),     4  => $langs->trans('April'),
		5  => $langs->trans('May'),       6  => $langs->trans('June'),
		7  => $langs->trans('July'),      8  => $langs->trans('August'),
		9  => $langs->trans('September'), 10 => $langs->trans('October'),
		11 => $langs->trans('November'),  12 => $langs->trans('December'),
	);
	return ($monthNames[$month] ?? $month).' '.$year;
}

// ------------------------------------------------------------------
// Load bank account if selected
// ------------------------------------------------------------------
$bankaccount = null;
$qontoBankId = '';
if ($account_id > 0) {
	$bankaccount = new Account($db);
	if ($bankaccount->fetch($account_id) <= 0) {
		$bankaccount = null;
	} else {
		// Get linked Qonto bank account id
		$sqlEf = "SELECT qonto_bank_id FROM ".MAIN_DB_PREFIX."bank_account_extrafields";
		$sqlEf .= " WHERE fk_object = ".(int)$account_id;
		$resEf = $db->query($sqlEf);
		if ($resEf && $db->num_rows($resEf) > 0) {
			$objEf = $db->fetch_object($resEf);
			$qontoBankId = $objEf->qonto_bank_id;
		}
	}
}

// ------------------------------------------------------------------
// Actions
// ------------------------------------------------------------------

if ($action == 'assign_releve' && $account_id > 0 && $period) {
	// Requires bank consolidate right
	if (!$user->hasRight('banque', 'consolidate')) {
		accessforbidden();
	}

	$numReleve = qontoPeriodToNumReleve($period);
	if (!$numReleve) {
		setEventMessages($langs->trans('ReconciliationInvalidPeriod'), null, 'errors');
	} else {
		// Determine year and month from the period for date filtering
		$parts = explode('-', $period); // ['MM', 'YYYY']
		$filterMonth = (int)$parts[0];
		$filterYear  = (int)$parts[1];

		// Update num_releve on all llx_bank lines that:
		// 1. belong to the correct Dolibarr bank account
		// 2. are linked to a Qonto transaction in this period
		// 3. do not yet have a num_releve assigned
		$sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."bank b";
		$sqlUpdate .= " INNER JOIN ".MAIN_DB_PREFIX."qonto_transactions qt ON qt.fk_bank = b.rowid";
		$sqlUpdate .= " SET b.num_releve = '".$db->escape($numReleve)."'";
		$sqlUpdate .= " WHERE b.fk_account = ".(int)$account_id;
		$sqlUpdate .= " AND qt.entity = ".(int)$conf->entity;
		$sqlUpdate .= " AND (b.num_releve IS NULL OR b.num_releve = '' OR b.num_releve = '0')";
		$sqlUpdate .= " AND YEAR(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterYear;
		$sqlUpdate .= " AND MONTH(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterMonth;

		$resUpdate = $db->query($sqlUpdate);
		if ($resUpdate) {
			$nbUpdated = $db->affected_rows($resUpdate);
			setEventMessages($langs->trans('ReconciliationAssigned', $nbUpdated, $numReleve), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?account_id='.urlencode($account_id).'&period='.urlencode($period));
	exit;
}

if ($action == 'download_statement_pdf' && $account_id > 0 && $period) {
	if (!$user->rights->doliqonto->attachment->sync) {
		accessforbidden();
	}

	if (empty($qontoBankId)) {
		setEventMessages($langs->trans('BankAccountNotLinked'), null, 'errors');
		header('Location: '.$_SERVER['PHP_SELF'].'?account_id='.urlencode($account_id).'&period='.urlencode($period));
		exit;
	}

	$qontoApi = new QontoApi($db);

	// Fetch statement list for this account+period
	$statementsResult = $qontoApi->listStatements($qontoBankId, $period, $period);
	if ($statementsResult === false) {
		setEventMessages($langs->trans('ReconciliationStatementFetchError').': '.$qontoApi->error, null, 'errors');
		header('Location: '.$_SERVER['PHP_SELF'].'?account_id='.urlencode($account_id).'&period='.urlencode($period));
		exit;
	}

	$matchingStatement = null;
	foreach ($statementsResult['statements'] as $stmt) {
		if ($stmt['period'] === $period && $stmt['bank_account_id'] === $qontoBankId) {
			$matchingStatement = $stmt;
			break;
		}
	}

	if (empty($matchingStatement)) {
		setEventMessages($langs->trans('ReconciliationNoPDFAvailable'), null, 'warnings');
		header('Location: '.$_SERVER['PHP_SELF'].'?account_id='.urlencode($account_id).'&period='.urlencode($period));
		exit;
	}

	// Determine destination directory: {dir_output}/{account_id}/statement/{YYYYMM}/
	// This matches Dolibarr's account_statement_document.php structure
	$numReleve    = qontoPeriodToNumReleve($period);
	$origFilename = !empty($matchingStatement['file']['file_name']) ? dol_sanitizeFileName($matchingStatement['file']['file_name']) : 'statement_'.$numReleve.'.pdf';
	$uploadDir    = $conf->bank->dir_output.'/'.$bankaccount->id.'/statement/'.$numReleve.'/';
	$destination  = $uploadDir.$origFilename;

	if (!dol_is_dir($uploadDir)) {
		dol_mkdir($uploadDir);
	}

	$result = $qontoApi->downloadStatement($matchingStatement['id'], $destination);
	if ($result) {
		setEventMessages($langs->trans('ReconciliationPDFDownloaded', $origFilename), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('ReconciliationPDFError').': '.$qontoApi->error, null, 'errors');
	}

	header('Location: '.$_SERVER['PHP_SELF'].'?account_id='.urlencode($account_id).'&period='.urlencode($period));
	exit;
}

// ------------------------------------------------------------------
// View
// ------------------------------------------------------------------

$form = new Form($db);

$title   = $langs->trans('BankReconciliation');
$helpurl = '';
llxHeader('', $title, $helpurl);

// ------------------------------------------------------------------
// Step 1 — Bank account selector
// ------------------------------------------------------------------
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent tableforfield">';

// Dolibarr bank account
print '<tr>';
print '<td class="titlefield"><label for="account_id">'.$langs->trans('DolibarrBankAccount').'</label></td>';
print '<td>';

// Build list of linked bank accounts
$sqlAccounts = "SELECT ba.rowid, ba.ref, ba.label, ef.qonto_bank_id, ef.qonto_name";
$sqlAccounts .= " FROM ".MAIN_DB_PREFIX."bank_account as ba";
$sqlAccounts .= " INNER JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
$sqlAccounts .= " WHERE ba.entity = ".(int)$conf->entity;
$sqlAccounts .= " AND ef.qonto_bank_id != '' AND ef.qonto_bank_id IS NOT NULL";
$sqlAccounts .= " ORDER BY ba.label ASC";

$resAccounts = $db->query($sqlAccounts);
$accountOptions = array();
if ($resAccounts) {
	while ($objAcc = $db->fetch_object($resAccounts)) {
		$qaLabel = !empty($objAcc->qonto_name) ? ' ('.$objAcc->qonto_name.')' : '';
		$accountOptions[$objAcc->rowid] = $objAcc->ref.' - '.dol_escape_htmltag($objAcc->label).$qaLabel;
	}
}

if (empty($accountOptions)) {
	print '<span class="opacitymedium">'.$langs->trans('NoBankAccountLinked').'</span>';
	print ' <a href="'.DOL_URL_ROOT.'/doliqonto/admin/bankaccounts.php">'.$langs->trans('ConfigureAPI').'</a>';
} else {
	print $form->selectarray('account_id', $accountOptions, $account_id, 1, 0, 0, '', 1);
}
print '</td>';
print '</tr>';

// Period selector — only visible when an account is selected
if ($account_id > 0 && !empty($qontoBankId)) {
	print '<tr>';
	print '<td><label for="period">'.$langs->trans('Period').'</label></td>';
	print '<td>';

	$qontoApi   = new QontoApi($db);
	$stmtResult = $qontoApi->listStatements($qontoBankId);
	if ($stmtResult !== false && !empty($stmtResult['statements'])) {
		$periodOptions = array();
		foreach ($stmtResult['statements'] as $stmt) {
			$nr    = qontoPeriodToNumReleve($stmt['period']);
			$label = numReleveToLabel($nr, $langs);
			$periodOptions[$stmt['period']] = $label.' ('.$stmt['period'].')';
		}
		print $form->selectarray('period', $periodOptions, $period, 1, 0, 0, '', 1);
	} elseif ($stmtResult === false) {
		print '<span class="error">'.$langs->trans('ReconciliationStatementFetchError').': '.dol_escape_htmltag($qontoApi->error).'</span>';
	} else {
		print '<span class="opacitymedium">'.$langs->trans('ReconciliationNoStatements').'</span>';
	}
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';

if (!empty($accountOptions)) {
	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans('Select').'">';
	print '</div>';
}
print '</form>';

print '<br>';

// ------------------------------------------------------------------
// Step 2 — Period detail (if both account + period are selected)
// ------------------------------------------------------------------
if ($account_id > 0 && $period && $bankaccount) {
	$numReleve = qontoPeriodToNumReleve($period);
	$parts     = explode('-', $period); // ['MM', 'YYYY']
	$filterMonth = (int)$parts[0];
	$filterYear  = (int)$parts[1];

	print load_fiche_titre(
		numReleveToLabel($numReleve, $langs).' — '.$langs->trans('BankAccount').' '.$bankaccount->getNomUrl(1),
		'',
		'calendar'
	);

	// ---- Statistics ----
	$sqlStats = "SELECT";
	$sqlStats .= " COUNT(*) as total_qonto,";
	$sqlStats .= " SUM(CASE WHEN qt.fk_bank IS NOT NULL AND qt.fk_bank > 0 THEN 1 ELSE 0 END) as matched,";
	$sqlStats .= " SUM(CASE WHEN qt.ignored = 1 THEN 1 ELSE 0 END) as ignored_count";
	$sqlStats .= " FROM ".MAIN_DB_PREFIX."qonto_transactions as qt";
	$sqlStats .= " WHERE qt.entity = ".(int)$conf->entity;
	$sqlStats .= " AND YEAR(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterYear;
	$sqlStats .= " AND MONTH(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterMonth;
	// Optionally filter by linked bank account
	if (!empty($qontoBankId)) {
		$sqlStats .= " AND qt.bank_account_id = '".$db->escape($qontoBankId)."'";
	}

	$resStats = $db->query($sqlStats);
	$statsQonto = array('total_qonto' => 0, 'matched' => 0, 'ignored_count' => 0);
	if ($resStats) {
		$statsQonto = (array)$db->fetch_object($resStats);
	}

	// Count bank lines already assigned to this num_releve
	$sqlBankStats = "SELECT";
	$sqlBankStats .= " COUNT(*) as bank_total,";
	$sqlBankStats .= " SUM(CASE WHEN b.rappro > 0 THEN 1 ELSE 0 END) as bank_rapproche";
	$sqlBankStats .= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sqlBankStats .= " WHERE b.fk_account = ".(int)$account_id;
	$sqlBankStats .= " AND b.num_releve = '".$db->escape($numReleve)."'";
	$resBankStats = $db->query($sqlBankStats);
	$statsBankLines = array('bank_total' => 0, 'bank_rapproche' => 0);
	if ($resBankStats) {
		$statsBankLines = (array)$db->fetch_object($resBankStats);
	}

	// Count bank lines *linked to Qonto* but without num_releve for this month
	$sqlPending = "SELECT COUNT(*) as pending_assign";
	$sqlPending .= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sqlPending .= " INNER JOIN ".MAIN_DB_PREFIX."qonto_transactions as qt ON qt.fk_bank = b.rowid";
	$sqlPending .= " WHERE b.fk_account = ".(int)$account_id;
	$sqlPending .= " AND qt.entity = ".(int)$conf->entity;
	$sqlPending .= " AND (b.num_releve IS NULL OR b.num_releve = '' OR b.num_releve = '0')";
	$sqlPending .= " AND YEAR(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterYear;
	$sqlPending .= " AND MONTH(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterMonth;
	$resPending = $db->query($sqlPending);
	$pendingAssign = 0;
	if ($resPending) {
		$objP = $db->fetch_object($resPending);
		$pendingAssign = (int)$objP->pending_assign;
	}

	// Check if a PDF is already present in the statement directory
	$statementDir = $conf->bank->dir_output.'/'.$bankaccount->id.'/statement/'.$numReleve.'/';
	$pdfExists    = false;
	$pdfFilename  = '';
	if (dol_is_dir($statementDir)) {
		$pdfFiles = dol_dir_list($statementDir, 'files', 0, '\.pdf$');
		if (!empty($pdfFiles)) {
			$pdfExists   = true;
			$pdfFilename = $pdfFiles[0]['name'];
		}
	}

	// ---- Summary box ----
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('ReconciliationStatus').'</td>';
	print '<td class="right">'.$langs->trans('QontoTransactions').'</td>';
	print '<td class="right">'.$langs->trans('Matched').'</td>';
	print '<td class="right">'.$langs->trans('ReconciliationBankLinesAssigned').'</td>';
	print '<td class="right">'.$langs->trans('ReconciliationPDFStatement').'</td>';
	print '</tr>';
	print '<tr class="oddeven">';

	// Overall status badge
	$totalQ  = (int)$statsQonto['total_qonto'];
	$matched = (int)$statsQonto['matched'];
	$unmatched = $totalQ - $matched - (int)$statsQonto['ignored_count'];

	if ($totalQ === 0) {
		$badge = '<span class="badge badge-status1">'.$langs->trans('NoTransactions').'</span>';
	} elseif ($unmatched > 0) {
		$badge = '<span class="badge badge-status8">'.$langs->trans('Incomplete').'</span>';
	} elseif ($pendingAssign > 0) {
		$badge = '<span class="badge badge-status3">'.$langs->trans('ReconciliationPendingAssign').'</span>';
	} elseif ($statsBankLines['bank_total'] > 0) {
		$badge = '<span class="badge badge-status4">'.$langs->trans('Completed').'</span>';
	} else {
		$badge = '<span class="badge badge-status1">'.$langs->trans('Pending').'</span>';
	}
	print '<td>'.$badge.'</td>';

	print '<td class="right">'.($totalQ > 0 ? $totalQ : '<span class="opacitymedium">0</span>').'</td>';
	print '<td class="right">';
	if ($totalQ > 0) {
		$pct = round($matched / $totalQ * 100);
		print $matched.' / '.$totalQ.' ('.$pct.'%)';
	} else {
		print '<span class="opacitymedium">—</span>';
	}
	print '</td>';
	print '<td class="right">'.(int)$statsBankLines['bank_total'].'</td>';
	print '<td class="right">';
	if ($pdfExists) {
		$pdfDocUrl = DOL_URL_ROOT.'/compta/bank/account_statement_document.php?account='.(int)$account_id.'&num='.urlencode($numReleve);
		print '<a href="'.dol_escape_htmltag($pdfDocUrl).'" target="_blank">'.img_picto('', 'pdf', 'class="pictofixedwidth"').dol_escape_htmltag($pdfFilename).'</a>';
	} else {
		print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
	}
	print '</td>';

	print '</tr>';
	print '</table>';
	print '</div>';

	print '<br>';

	// ---- Action buttons ----
	print '<div class="tabsAction">';

	// Assign to num_releve
	if ($pendingAssign > 0 && $user->hasRight('banque', 'consolidate')) {
		$assignUrl = $_SERVER['PHP_SELF'].'?action=assign_releve&token='.newToken().'&account_id='.(int)$account_id.'&period='.urlencode($period);
		print '<a href="'.dol_escape_htmltag($assignUrl).'" class="butAction">';
		print img_picto('', 'link', 'class="pictofixedwidth"');
		print $langs->trans('ReconciliationAssignBtn', $numReleve).'</a>';
	} elseif ($pendingAssign === 0 && $statsBankLines['bank_total'] > 0) {
		print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('ReconciliationAlreadyAssigned').'">';
		print img_picto('', 'check', 'class="pictofixedwidth"');
		print $langs->trans('ReconciliationAssignBtn', $numReleve).'</span>';
	} elseif (!$user->hasRight('banque', 'consolidate')) {
		print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('NotEnoughPermissions').'">';
		print $langs->trans('ReconciliationAssignBtn', $numReleve).'</span>';
	}

	// Download Qonto PDF
	if ($user->rights->doliqonto->attachment->sync) {
		$pdfAction = 'download_statement_pdf';
		$pdfUrl = $_SERVER['PHP_SELF'].'?action='.$pdfAction.'&token='.newToken().'&account_id='.(int)$account_id.'&period='.urlencode($period);
		$pdfClass = $pdfExists ? 'butActionDelete' : 'butAction';
		print '<a href="'.dol_escape_htmltag($pdfUrl).'" class="'.$pdfClass.'">';
		print img_picto('', 'pdf', 'class="pictofixedwidth"');
		print $langs->trans($pdfExists ? 'ReconciliationReDownloadPDF' : 'ReconciliationDownloadPDF').'</a>';
	}

	// Link to Dolibarr native relevé page
	if (!empty($numReleve)) {
		print '<a href="'.DOL_URL_ROOT.'/compta/bank/releve.php?num='.urlencode($numReleve).'&account='.(int)$account_id.'" class="butAction" target="_blank">';
		print img_picto('', 'bank', 'class="pictofixedwidth"');
		print $langs->trans('ReconciliationViewReleve').'</a>';
	}

	print '</div>';

	// ---- Transaction table ----
	print '<div class="div-table-responsive">';
	print '<table class="tagtable nobottomiftotal liste">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Date').'</td>';
	print '<td>'.$langs->trans('Counterparty').'</td>';
	print '<td>'.$langs->trans('Label').'</td>';
	print '<td class="right">'.$langs->trans('Amount').'</td>';
	print '<td class="center">'.$langs->trans('Side').'</td>';
	print '<td class="center">'.$langs->trans('MatchStatus').'</td>';
	print '<td class="center">'.$langs->trans('NumReleve').'</td>';
	print '</tr>';

	$sqlTx = "SELECT";
	$sqlTx .= " qt.rowid, qt.transaction_id, qt.emitted_at, qt.settled_at,";
	$sqlTx .= " qt.amount, qt.currency, qt.side, qt.label, qt.counterparty_name,";
	$sqlTx .= " qt.fk_bank, qt.ignored,";
	$sqlTx .= " b.num_releve as bank_releve";
	$sqlTx .= " FROM ".MAIN_DB_PREFIX."qonto_transactions as qt";
	$sqlTx .= " LEFT JOIN ".MAIN_DB_PREFIX."bank as b ON b.rowid = qt.fk_bank";
	$sqlTx .= " WHERE qt.entity = ".(int)$conf->entity;
	$sqlTx .= " AND YEAR(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterYear;
	$sqlTx .= " AND MONTH(IFNULL(qt.settled_at, qt.emitted_at)) = ".(int)$filterMonth;
	if (!empty($qontoBankId)) {
		$sqlTx .= " AND qt.bank_account_id = '".$db->escape($qontoBankId)."'";
	}
	$sqlTx .= " ORDER BY IFNULL(qt.settled_at, qt.emitted_at) ASC";

	$resTx = $db->query($sqlTx);
	if ($resTx) {
		$numTx = $db->num_rows($resTx);
		if ($numTx === 0) {
			print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoQontoTransactionsForPeriod').'</span></td></tr>';
		}
		while ($objTx = $db->fetch_object($resTx)) {
			$txDate = $db->jdate(!empty($objTx->settled_at) ? $objTx->settled_at : $objTx->emitted_at);
			print '<tr class="oddeven">';

			// Date
			print '<td class="nowrap">'.dol_print_date($txDate, 'day').'</td>';

			// Counterparty
			print '<td>'.dol_escape_htmltag($objTx->counterparty_name).'</td>';

			// Label
			print '<td>'.dol_escape_htmltag($objTx->label).'</td>';

			// Amount
			print '<td class="right nowrap">';
			print price($objTx->amount, 0, $langs, 1, -1, -1, $objTx->currency);
			print '</td>';

			// Side
			print '<td class="center">';
			if ($objTx->side == 'credit') {
				print '<span class="badge badge-status4">'.$langs->trans('Credit').'</span>';
			} else {
				print '<span class="badge badge-status8">'.$langs->trans('Debit').'</span>';
			}
			print '</td>';

			// Match status
			print '<td class="center">';
			if ($objTx->ignored) {
				print '<span class="badge badge-status2">'.$langs->trans('Ignored').'</span>';
			} elseif (!empty($objTx->fk_bank)) {
				$matchingUrl = DOL_URL_ROOT.'/compta/bank/line.php?rowid='.(int)$objTx->fk_bank.'&account='.(int)$account_id;
				print '<a href="'.dol_escape_htmltag($matchingUrl).'">';
				print '<span class="badge badge-status4">'.$langs->trans('Matched').'</span>';
				print '</a>';
			} else {
				$matchUrl = DOL_URL_ROOT.'/doliqonto/matching.php?id='.(int)$objTx->rowid;
				print '<a href="'.dol_escape_htmltag($matchUrl).'">';
				print '<span class="badge badge-status1">'.$langs->trans('Pending').'</span>';
				print '</a>';
			}
			print '</td>';

			// num_releve on linked bank line
			print '<td class="center">';
			if (!empty($objTx->fk_bank)) {
				if (!empty($objTx->bank_releve)) {
					$releveUrl = DOL_URL_ROOT.'/compta/bank/releve.php?num='.urlencode($objTx->bank_releve).'&account='.(int)$account_id;
					print '<a href="'.dol_escape_htmltag($releveUrl).'">';
					print '<span class="badge badge-status4">'.dol_escape_htmltag($objTx->bank_releve).'</span>';
					print '</a>';
				} else {
					print '<span class="badge badge-status8">'.$langs->trans('ReconciliationNotAssigned').'</span>';
				}
			} else {
				print '<span class="opacitymedium">—</span>';
			}
			print '</td>';

			print '</tr>';
		}
		$db->free($resTx);
	} else {
		print '<tr><td colspan="7">'.dol_print_error($db, '', 1).'</td></tr>';
	}

	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
