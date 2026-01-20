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
 * \file    doliqonto/transactions.php
 * \ingroup doliqonto
 * \brief   List and manage Qonto transactions
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/doliqonto/class/qontoapi.class.php');
dol_include_once('/doliqonto/class/qontotransaction.class.php');

// Load translation files required by the page
$langs->loadLangs(array("doliqonto@doliqonto", "other"));

// Get parameters
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'qontotransactionslist';

$search_transaction_id = GETPOST('search_transaction_id', 'alphanohtml');
$search_status = GETPOST('search_status', 'alphanohtml');
$search_match_status = GETPOST('search_match_status', 'alphanohtml');
$search_label = GETPOST('search_label', 'alphanohtml');
$search_amount_min = price2num(GETPOST('search_amount_min', 'alphanohtml'));
$search_amount_max = price2num(GETPOST('search_amount_max', 'alphanohtml'));

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = "t.emitted_at";
}
if (!$sortorder) {
	$sortorder = "DESC";
}

// Access control
if (!$user->rights->doliqonto->transaction->read) {
	accessforbidden();
}

$object = new QontoTransaction($db);
$hookmanager->initHooks(array('qontotransactionslist'));

/*
 * Actions
 */

if ($action == 'sync') {
	$qontoApi = new QontoApi($db);
	$result = $qontoApi->syncTransactions();
	
	if ($result > 0) {
		setEventMessages($langs->trans("TransactionsSynced", $result), null, 'mesgs');
	} elseif ($result == 0) {
		setEventMessages($langs->trans("NoNewTransactions"), null, 'warnings');
	} else {
		setEventMessages($qontoApi->error, null, 'errors');
	}
	
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'auto_match_all') {
	// Auto-match all pending transactions (not matched and not ignored)
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."qonto_transactions";
	$sql .= " WHERE entity = ".$conf->entity;
	$sql .= " AND (fk_bank IS NULL OR fk_bank = '' OR fk_bank = 0)";
	$sql .= " AND (ignored IS NULL OR ignored = 0)";
	
	$resql = $db->query($sql);
	$matched = 0;
	
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$transaction = new QontoTransaction($db);
			$transaction->fetch($obj->rowid);
			$result = $transaction->autoMatch($user);
			if ($result > 0) {
				$matched++;
			}
		}
		$db->free($resql);
	}
	
	if ($matched > 0) {
		setEventMessages($matched.' '.$langs->trans("TransactionsAutoMatched"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("NoTransactionsMatched"), null, 'warnings');
	}
	
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'ignore' && !empty($toselect)) {
	foreach ($toselect as $id) {
		$transaction = new QontoTransaction($db);
		$transaction->fetch($id);
		$transaction->ignored = 1;
		$transaction->update($user);
	}
	setEventMessages($langs->trans("TransactionsIgnored"), null, 'mesgs');
}

// Clear filters
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_transaction_id = '';
	$search_status = '';
	$search_match_status = '';
	$search_label = '';
	$search_amount_min = '';
	$search_amount_max = '';
}

/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);

$title = $langs->trans("QontoTransactions");
$help_url = '';

llxHeader('', $title, $help_url);

// Build and execute query
$sql = "SELECT";
$sql .= " t.rowid,";
$sql .= " t.transaction_id,";
$sql .= " t.emitted_at,";
$sql .= " t.settled_at,";
$sql .= " t.amount,";
$sql .= " t.currency,";
$sql .= " t.side,";
$sql .= " t.operation_type,";
$sql .= " t.status,";
$sql .= " t.label,";
$sql .= " t.reference,";
$sql .= " t.counterparty_name,";
$sql .= " t.ignored,";
$sql .= " t.fk_bank";
$sql .= " FROM ".MAIN_DB_PREFIX."qonto_transactions as t";
$sql .= " WHERE t.entity = ".$conf->entity;

// Add search filters
if ($search_transaction_id) {
	$sql .= natural_search('t.transaction_id', $search_transaction_id);
}
if ($search_status && $search_status != '-1' && $search_status != '0' && $search_status != '') {
	$sql .= " AND t.status = '".$db->escape($search_status)."'";
}
if ($search_match_status && $search_match_status != '-1' && $search_match_status != '0' && $search_match_status != '') {
	if ($search_match_status == 'pending') {
		$sql .= " AND (t.fk_bank IS NULL OR t.fk_bank = '' OR t.fk_bank = 0)";
		$sql .= " AND (t.ignored IS NULL OR t.ignored = 0)";
	} elseif ($search_match_status == 'matched') {
		$sql .= " AND t.fk_bank IS NOT NULL AND t.fk_bank != '' AND t.fk_bank != 0";
	} elseif ($search_match_status == 'ignored') {
		$sql .= " AND t.ignored = 1";
	}
}
if ($search_label) {
	$sql .= natural_search('t.label', $search_label);
}
if ($search_amount_min !== '' && $search_amount_min !== false) {
	$sql .= " AND t.amount >= ".(float)$search_amount_min;
}
if ($search_amount_max !== '' && $search_amount_max !== false) {
	$sql .= " AND t.amount <= ".(float)$search_amount_max;
}

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$resql = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

// Output page
$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.urlencode($limit);
}
if ($search_transaction_id) {
	$param .= '&search_transaction_id='.urlencode($search_transaction_id);
}
if ($search_status) {
	$param .= '&search_status='.urlencode($search_status);
}
if ($search_match_status) {
	$param .= '&search_match_status='.urlencode($search_match_status);
}
if ($search_label) {
	$param .= '&search_label='.urlencode($search_label);
}
if ($search_amount_min) {
	$param .= '&search_amount_min='.urlencode($search_amount_min);
}
if ($search_amount_max) {
	$param .= '&search_amount_max='.urlencode($search_amount_max);
}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($sortfield) {
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
}
if ($sortorder) {
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
}
if ($page) {
	print '<input type="hidden" name="page" value="'.$page.'">';
}

$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('SyncTransactions'), '', 'fa fa-sync', $_SERVER["PHP_SELF"].'?action=sync', '', $user->rights->doliqonto->transaction->import);
$newcardbutton .= dolGetButtonTitle($langs->trans('AutoMatchAll'), '', 'fa fa-link', $_SERVER["PHP_SELF"].'?action=auto_match_all', '', $user->rights->doliqonto->transaction->import);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'payment', 0, $newcardbutton, '', $limit, 0, 0, 1);

// Mass actions
$massactionbutton = '';
$arrayofmassactions = array();
if ($user->rights->doliqonto->transaction->import) {
	$arrayofmassactions['ignore'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Ignore");
}
if (!empty($massactionbutton)) {
	print $massactionbutton;
}

$moreforfilter = '';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre center">';
print '<input class="flat" type="text" name="search_transaction_id" size="10" value="'.dol_escape_htmltag($search_transaction_id).'">';
print '</td>';
print '<td class="liste_titre">&nbsp;</td>';
print '<td class="liste_titre">&nbsp;</td>';
print '<td class="liste_titre center">';
print '<input class="flat" type="text" name="search_label" size="15" value="'.dol_escape_htmltag($search_label).'">';
print '</td>';
print '<td class="liste_titre center">';
print '<input class="flat width50" type="text" name="search_amount_min" placeholder="'.$langs->trans("Min").'" value="'.dol_escape_htmltag($search_amount_min).'">';
print ' - ';
print '<input class="flat width50" type="text" name="search_amount_max" placeholder="'.$langs->trans("Max").'" value="'.dol_escape_htmltag($search_amount_max).'">';
print '</td>';
print '<td class="liste_titre">&nbsp;</td>';
print '<td class="liste_titre center">';
$array_status = array('pending'=>$langs->trans('Pending'), 'completed'=>$langs->trans('Completed'), 'declined'=>$langs->trans('Declined'));
print $form->selectarray('search_status', $array_status, $search_status, 1);
print '</td>';
print '<td class="liste_titre center">';
$array_match = array('pending'=>$langs->trans('Pending'), 'matched'=>$langs->trans('Matched'), 'ignored'=>$langs->trans('Ignored'));
print $form->selectarray('search_match_status', $array_match, $search_match_status, 1);
print '</td>';
print '<td class="liste_titre center maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";

// Fields title label
print '<tr class="liste_titre">';
print getTitleFieldOfList('TransactionId', 0, $_SERVER["PHP_SELF"], 't.transaction_id', '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('Date', 0, $_SERVER["PHP_SELF"], 't.emitted_at', '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('Counterparty', 0, $_SERVER["PHP_SELF"], 't.counterparty_name', '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('Label', 0, $_SERVER["PHP_SELF"], 't.label', '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('Amount', 0, $_SERVER["PHP_SELF"], 't.amount', '', $param, '', $sortfield, $sortorder, 'right ');
print getTitleFieldOfList('Side', 0, $_SERVER["PHP_SELF"], 't.side', '', $param, '', $sortfield, $sortorder, 'center ');
print getTitleFieldOfList('Status', 0, $_SERVER["PHP_SELF"], 't.status', '', $param, '', $sortfield, $sortorder, 'center ');
print getTitleFieldOfList('MatchStatus', 0, $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder, 'center ');
print getTitleFieldOfList('', 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>'."\n";

// Loop on records
$i = 0;
$totalarray = array('nbfield'=>0);
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break;
	}

	print '<tr class="oddeven">';
	
	// Transaction ID
	print '<td class="nowraponall">';
	print '<span title="'.dol_escape_htmltag($obj->transaction_id).'">'.substr($obj->transaction_id, 0, 20).'...</span>';
	print '</td>';
	
	// Date
	print '<td class="center">';
	print dol_print_date($db->jdate($obj->emitted_at), 'day');
	print '</td>';
	
	// Counterparty
	print '<td>';
	print dol_escape_htmltag($obj->counterparty_name);
	print '</td>';
	
	// Label
	print '<td>';
	print dol_escape_htmltag($obj->label);
	print '</td>';
	
	// Amount
	print '<td class="right">';
	print price($obj->amount, 0, $langs, 1, -1, -1, $obj->currency);
	print '</td>';
	
	// Side
	print '<td class="center">';
	if ($obj->side == 'credit') {
		print '<span class="badge badge-status4">'.$langs->trans('Credit').'</span>';
	} else {
		print '<span class="badge badge-status8">'.$langs->trans('Debit').'</span>';
	}
	print '</td>';
	
	// Status
	print '<td class="center">';
	if ($obj->status == 'completed') {
		print '<span class="badge badge-status4">'.$langs->trans('Completed').'</span>';
	} elseif ($obj->status == 'pending') {
		print '<span class="badge badge-status1">'.$langs->trans('Pending').'</span>';
	} else {
		print '<span class="badge badge-status8">'.dol_escape_htmltag($obj->status).'</span>';
	}
	print '</td>';
	
	// Match Status (derived from ignored + fk_bank)
	print '<td class="center">';
	if ($obj->ignored) {
		print '<span class="badge badge-status9">'.$langs->trans('Ignored').'</span>';
	} elseif (!empty($obj->fk_bank)) {
		print '<span class="badge badge-status4">'.$langs->trans('Matched').'</span>';
	} else {
		print '<span class="badge badge-status1">'.$langs->trans('Pending').'</span>';
	}
	print '</td>';
	
	// Action column
	print '<td class="center">';
	if (empty($obj->fk_bank) && !$obj->ignored && $user->rights->doliqonto->payment->match) {
		print '<a class="butAction" href="matching.php?id='.$obj->rowid.'">'.$langs->trans("Match").'</a>';
	}
	print '</td>';
	
	print '</tr>'."\n";
	$i++;
}

if ($num == 0) {
	print '<tr><td colspan="9"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";

// End of page
llxFooter();
$db->close();
