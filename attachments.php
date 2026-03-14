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
 * \file    doliqonto/attachments.php
 * \ingroup doliqonto
 * \brief   Manage attachment synchronization between Qonto and Dolibarr
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
dol_include_once('/doliqonto/class/qontoapi.class.php');
dol_include_once('/doliqonto/class/qontotransaction.class.php');

// Load translation files required by the page
$langs->loadLangs(array("doliqonto@doliqonto", "other"));

// Get parameters
$action = GETPOST('action', 'aZ09');
$transaction_id = GETPOST('transaction_id', 'int');

// Access control
if (!$user->rights->doliqonto->attachment->sync) {
	accessforbidden();
}

/*
 * Actions
 */

// AJAX endpoint: check attachment count for a transaction from Qonto API
if ($action == 'ajax_check_attachments' && $transaction_id > 0) {
	header('Content-Type: application/json');
	$transaction = new QontoTransaction($db);
	$transaction->fetch($transaction_id);

	$qontoApi = new QontoApi($db);
	$apiId = $transaction->transaction_id;
	if (!empty($transaction->raw_data)) {
		$rawDecoded = json_decode($transaction->raw_data, true);
		if ($rawDecoded && !empty($rawDecoded['id'])) {
			$apiId = $rawDecoded['id'];
		}
	}

	$attachments = $qontoApi->listTransactionAttachments($apiId);
	$count = 0;
	if ($attachments !== false && isset($attachments['attachments'])) {
		$count = count($attachments['attachments']);
		// Also update the local DB
		$newIds = array();
		foreach ($attachments['attachments'] as $att) {
			if (!empty($att['id'])) {
				$newIds[] = $att['id'];
			}
		}
		$newJson = !empty($newIds) ? json_encode($newIds) : '';
		$sql = "UPDATE ".MAIN_DB_PREFIX."qonto_transactions SET attachment_ids = '".$db->escape($newJson)."' WHERE rowid = ".(int)$transaction->id;
		$db->query($sql);
	}

	print json_encode(array('count' => $count));
	exit;
}

if ($action == 'refresh_attachments') {
	// Refresh attachment IDs from Qonto for all transactions
	$qontoApi = new QontoApi($db);
	$updated = $qontoApi->refreshAttachmentIds(30); // Last 30 days
	
	if ($updated >= 0) {
		setEventMessages($langs->trans("AttachmentsRefreshed", $updated), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("ErrorRefreshingAttachments"), null, 'errors');
	}
}

if ($action == 'sync_from_qonto' && $transaction_id > 0) {
	// Download attachments from Qonto to Dolibarr
	$transaction = new QontoTransaction($db);
	$transaction->fetch($transaction_id);
	
	if (!empty($transaction->attachment_ids)) {
		$attachmentIds = json_decode($transaction->attachment_ids, true);
		$qontoApi = new QontoApi($db);
		
		$synced = 0;
		foreach ($attachmentIds as $attachmentId) {
			// Check if attachment already synced
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."qonto_attachments";
			$sql .= " WHERE attachment_id = '".$db->escape($attachmentId)."'";
			$sql .= " AND sync_status = 'synced'";
			$sql .= " AND entity = ".$conf->entity;
			
			$resql = $db->query($sql);
			if ($resql && $db->num_rows($resql) > 0) {
				continue; // Already synced
			}
			
			// Get attachment info
			$attachmentData = $qontoApi->getAttachment($attachmentId);
			if ($attachmentData !== false && !empty($attachmentData['attachment'])) {
				$attachment = $attachmentData['attachment'];
				
				// Determine where to save the file
				$upload_dir = $conf->doliqonto->dir_output . '/temp';
				if (!dol_is_dir($upload_dir)) {
					dol_mkdir($upload_dir);
				}
				
				$filename = $attachment['file_name'];
				$filepath = $upload_dir . '/' . $filename;
				
				// Download the file
				if ($qontoApi->downloadAttachment($attachmentId, $filepath)) {
					// Store attachment info in database
					$sql = "INSERT INTO ".MAIN_DB_PREFIX."qonto_attachments (";
					$sql .= " entity, attachment_id, fk_qonto_transaction, filename,";
					$sql .= " file_size, file_url, qonto_created_at, sync_status,";
					$sql .= " sync_direction, last_sync_date, date_creation, fk_user_creat";
					$sql .= ") VALUES (";
					$sql .= " ".$conf->entity.",";
					$sql .= " '".$db->escape($attachmentId)."',";
					$sql .= " ".(int)$transaction->id.",";
					$sql .= " '".$db->escape($filename)."',";
					$sql .= " ".(int)$attachment['file_size'].",";
					$sql .= " '".$db->escape($attachment['url'])."',";
					$sql .= " '".$db->idate($attachment['created_at'])."',";
					$sql .= " 'synced',";
					$sql .= " 'from_qonto',";
					$sql .= " '".$db->idate(dol_now())."',";
					$sql .= " '".$db->idate(dol_now())."',";
					$sql .= " ".$user->id;
					$sql .= ")";
					
					$db->query($sql);
					$synced++;
				}
			}
		}
		
		if ($synced > 0) {
			setEventMessages($langs->trans("AttachmentsSynced", $synced), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("NoAttachmentsToSync"), null, 'warnings');
		}
	}
}

if ($action == 'sync_to_qonto' && $transaction_id > 0) {
	// Upload attachments from Dolibarr to Qonto
	$transaction = new QontoTransaction($db);
	$transaction->fetch($transaction_id);
	
	$qontoApi = new QontoApi($db);
	$uploaded = 0;
	
	// Get linked invoice through bank line (clean architecture)
	$invoiceInfo = $transaction->getFirstLinkedInvoice();
	
	if ($invoiceInfo) {
		$invoice = $invoiceInfo['object'];
		
		// Get invoice attachments directory
		if ($invoiceInfo['type'] == 'customer') {
			$upload_dir = $conf->facture->dir_output . '/' . $invoice->ref;
		} else {
			$subdir = get_exdir($invoice->id, 2, 0, 0, $invoice, 'invoice_supplier');
			$upload_dir = $conf->fournisseur->facture->dir_output . '/' . $subdir . $invoice->ref;
		}
		
		dol_syslog("DoliQonto attachments.php sync_to_qonto - invoice_type=".$invoiceInfo['type']." invoice_ref=".$invoice->ref." invoice_id=".$invoice->id." upload_dir=".$upload_dir." dir_exists=".dol_is_dir($upload_dir), LOG_DEBUG);
		
		$files = dol_dir_list($upload_dir, 'files');
		
		dol_syslog("DoliQonto attachments.php sync_to_qonto - files found: ".count($files), LOG_DEBUG);
		
		foreach ($files as $file) {
			dol_syslog("DoliQonto attachments.php sync_to_qonto - uploading: ".$file['fullname'], LOG_DEBUG);
			$result = $qontoApi->uploadAttachment($transaction->transaction_id, $file['fullname'], $file['name'], $transaction->raw_data);
			if ($result !== false) {
				$uploaded++;
			} else {
				setEventMessages($qontoApi->error, null, 'errors');
			}
		}
	} else {
		dol_syslog("DoliQonto attachments.php sync_to_qonto - No linked invoice found for transaction rowid=".$transaction_id." fk_bank=".$transaction->fk_bank, LOG_WARNING);
	}
	
	if ($uploaded > 0) {
		setEventMessages($langs->trans("AttachmentsUploaded", $uploaded), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("NoAttachmentsToUpload"), null, 'warnings');
	}

	// Redirect to prevent duplicate upload on page refresh (PRG pattern)
	$redirectUrl = $_SERVER['PHP_SELF'];
	if ($uploaded > 0) {
		$redirectUrl .= '?poll_id='.(int)$transaction->id.'&poll_count='.(int)$uploaded;
	}
	header('Location: '.$redirectUrl);
	exit;
}

// Handle polling params from redirect after upload
$pollTransactionId = GETPOST('poll_id', 'int');
$pollExpectedCount = GETPOST('poll_count', 'int');

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans("AttachmentsSynchronization");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($title, '', 'object_doliqonto@doliqonto');

print '<div class="warning">';
print img_warning().' '.$langs->trans("AttachmentsFeatureWarning");
print '</div>';
print '<br>';

// Add refresh button
print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=refresh_attachments">'.$langs->trans("RefreshAttachments").'</a>';
print '</div>';

print '<br>';

// Get transactions with attachments or linked to bank lines (matched transactions)
$sql = "SELECT t.rowid, t.transaction_id, t.label, t.amount, t.currency,";
$sql .= " t.fk_bank, t.attachment_ids";
$sql .= " FROM ".MAIN_DB_PREFIX."qonto_transactions as t";
$sql .= " WHERE t.entity = ".$conf->entity;
$sql .= " AND ((t.attachment_ids IS NOT NULL AND t.attachment_ids != '' AND t.attachment_ids != '[]')";
$sql .= " OR t.fk_bank IS NOT NULL)";
$sql .= " ORDER BY t.emitted_at DESC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("TransactionId").'</th>';
	print '<th>'.$langs->trans("Label").'</th>';
	print '<th class="right">'.$langs->trans("Amount").'</th>';
	print '<th>'.$langs->trans("Invoice").'</th>';
	print '<th class="center">'.$langs->trans("QontoAttachments").'</th>';
	print '<th class="center">'.$langs->trans("DolibarrAttachments").'</th>';
	print '<th class="center">'.$langs->trans("Action").'</th>';
	print '</tr>';
	
	if ($num > 0) {
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			
			print '<tr class="oddeven">';
			
			// Transaction ID
			print '<td>'.substr($obj->transaction_id, 0, 20).'...</td>';
			
			// Label
			print '<td>'.dol_escape_htmltag($obj->label).'</td>';
			
			// Amount
			print '<td class="right">'.price($obj->amount, 0, $langs, 1, -1, -1, $obj->currency).'</td>';
			
		// Load transaction to use helper methods
		$transaction = new QontoTransaction($db);
		$transaction->fetch($obj->rowid);
		
		// Invoice - retrieve through QontoTransaction helper
		print '<td>';
		$invoiceInfo = $transaction->getFirstLinkedInvoice();
		if ($invoiceInfo) {
			$invoice = $invoiceInfo['object'];
			print $invoice->getNomUrl(1);
		} else {
			print '-';
		}
		print '</td>';
		
		// Qonto attachments
		print '<td class="center" id="qonto-att-'.$obj->rowid.'">';
		if (!empty($obj->attachment_ids) && $obj->attachment_ids != '[]') {
			$attachmentIds = json_decode($obj->attachment_ids, true);
			print count($attachmentIds);
		} else {
			print '0';
		}
		print '</td>';
		
		// Dolibarr attachments - use helper method
		print '<td class="center">';
		$dolibarrFiles = $transaction->countInvoiceAttachments();
		print $dolibarrFiles;
		print '</td>';
		
		// Actions
		print '<td class="center nowraponall">';
		if (!empty($obj->attachment_ids) && $obj->attachment_ids != '[]') {
			print '<a class="butAction smallpaddingimp" href="'.$_SERVER["PHP_SELF"].'?action=sync_from_qonto&transaction_id='.$obj->rowid.'">'.$langs->trans("DownloadFromQonto").'</a> ';
		}
		if ($invoiceInfo && $dolibarrFiles > 0) {
			print '<a class="butAction smallpaddingimp" href="'.$_SERVER["PHP_SELF"].'?action=sync_to_qonto&transaction_id='.$obj->rowid.'">'.$langs->trans("UploadToQonto").'</a>';
		}
		print '</td>';			print '</tr>';
			$i++;
		}
	} else {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}
	
	print '</table>';
	
	$db->free($resql);
} else {
	dol_print_error($db);
}

// Polling JS if we just uploaded
if (!empty($pollTransactionId)) {
	print '<script>
(function() {
	var rowId = '.((int)$pollTransactionId).';
	var expected = '.((int)$pollExpectedCount).';
	var maxRetries = 10;
	var attempt = 0;
	var cell = document.getElementById("qonto-att-" + rowId);
	if (!cell) return;

	var originalContent = cell.innerHTML;
	cell.innerHTML = \'<span class="qonto-loading"></span> <span class="qonto-poll-status">Checking...</span>\';

	function poll() {
		attempt++;
		var statusEl = cell.querySelector(".qonto-poll-status");
		if (statusEl) statusEl.textContent = "Check " + attempt + "/" + maxRetries + "...";

		fetch("'.dol_escape_js($_SERVER['PHP_SELF']).'?action=ajax_check_attachments&transaction_id=" + rowId)
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (data.count > 0) {
					cell.innerHTML = data.count;
					cell.style.color = "#28a745";
					cell.style.fontWeight = "bold";
					setTimeout(function() { cell.style.color = ""; cell.style.fontWeight = ""; }, 3000);
					return;
				}
				if (attempt >= maxRetries) {
					cell.innerHTML = \'<span title="Qonto is still processing. Click Refresh Attachments or check Qonto directly." style="cursor:help;color:#e67e22;">&#9888; 0</span>\';
					return;
				}
				setTimeout(poll, 1500);
			})
			.catch(function() {
				if (attempt >= maxRetries) {
					cell.innerHTML = \'<span title="Could not verify. Click Refresh Attachments to retry." style="cursor:help;color:#e67e22;">&#9888; ?</span>\';
				} else {
					setTimeout(poll, 1500);
				}
			});
	}

	setTimeout(poll, 2000);
})();
</script>';
}

// End of page
llxFooter();
$db->close();
