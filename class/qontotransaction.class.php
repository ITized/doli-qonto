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
 * \file       class/qontotransaction.class.php
 * \ingroup    doliqonto
 * \brief      Class file for Qonto transactions
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for QontoTransaction
 */
class QontoTransaction extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'qontotransaction';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'qonto_transactions';

	/**
	 * @var int  Does this object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int  Does object support extrafields ?
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var string String with name of icon for qontotransaction
	 */
	public $picto = 'payment';

	/**
	 * @var array  Array with all fields and their property
	 */
	public $fields = array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>1, 'index'=>1, 'comment'=>"Id"),
		'entity' => array('type'=>'integer', 'label'=>'Entity', 'enabled'=>1, 'visible'=>0, 'position'=>5, 'notnull'=>1, 'default'=>1, 'index'=>1),
		'transaction_id' => array('type'=>'varchar(255)', 'label'=>'TransactionId', 'enabled'=>1, 'position'=>10, 'notnull'=>1, 'visible'=>1, 'index'=>1),
		'bank_account_id' => array('type'=>'varchar(255)', 'label'=>'BankAccountId', 'enabled'=>1, 'position'=>15, 'notnull'=>0, 'visible'=>1),
		'emitted_at' => array('type'=>'datetime', 'label'=>'EmittedAt', 'enabled'=>1, 'position'=>20, 'notnull'=>1, 'visible'=>1),
		'settled_at' => array('type'=>'datetime', 'label'=>'SettledAt', 'enabled'=>1, 'position'=>25, 'notnull'=>0, 'visible'=>1),
		'amount' => array('type'=>'double(24,8)', 'label'=>'Amount', 'enabled'=>1, 'position'=>30, 'notnull'=>1, 'visible'=>1),
		'currency' => array('type'=>'varchar(3)', 'label'=>'Currency', 'enabled'=>1, 'position'=>35, 'notnull'=>0, 'visible'=>1, 'default'=>'EUR'),
		'side' => array('type'=>'varchar(10)', 'label'=>'Side', 'enabled'=>1, 'position'=>40, 'notnull'=>1, 'visible'=>1),
		'operation_type' => array('type'=>'varchar(50)', 'label'=>'OperationType', 'enabled'=>1, 'position'=>45, 'notnull'=>0, 'visible'=>1),
		'status' => array('type'=>'varchar(50)', 'label'=>'Status', 'enabled'=>1, 'position'=>50, 'notnull'=>1, 'visible'=>1),
		'label' => array('type'=>'varchar(255)', 'label'=>'Label', 'enabled'=>1, 'position'=>55, 'notnull'=>0, 'visible'=>1),
		'note' => array('type'=>'text', 'label'=>'Note', 'enabled'=>1, 'position'=>60, 'notnull'=>0, 'visible'=>3),
		'reference' => array('type'=>'varchar(255)', 'label'=>'Reference', 'enabled'=>1, 'position'=>65, 'notnull'=>0, 'visible'=>1),
		'vat_amount' => array('type'=>'double(24,8)', 'label'=>'VATAmount', 'enabled'=>1, 'position'=>70, 'notnull'=>0, 'visible'=>1),
		'vat_rate' => array('type'=>'double(10,4)', 'label'=>'VATRate', 'enabled'=>1, 'position'=>75, 'notnull'=>0, 'visible'=>1),
		'counterparty_name' => array('type'=>'varchar(255)', 'label'=>'CounterpartyName', 'enabled'=>1, 'position'=>80, 'notnull'=>0, 'visible'=>1),
		'counterparty_iban' => array('type'=>'varchar(50)', 'label'=>'CounterpartyIBAN', 'enabled'=>1, 'position'=>85, 'notnull'=>0, 'visible'=>1),
		'attachment_ids' => array('type'=>'text', 'label'=>'AttachmentIds', 'enabled'=>1, 'position'=>90, 'notnull'=>0, 'visible'=>0),
		'ignored' => array('type'=>'integer', 'label'=>'Ignored', 'enabled'=>1, 'position'=>95, 'notnull'=>0, 'visible'=>1, 'default'=>0),
		'fk_bank' => array('type'=>'integer', 'label'=>'BankLine', 'enabled'=>1, 'position'=>98, 'notnull'=>0, 'visible'=>1),
		'fk_payment' => array('type'=>'integer', 'label'=>'Payment', 'enabled'=>1, 'position'=>110, 'notnull'=>0, 'visible'=>1),
		'tax_conflict' => array('type'=>'integer', 'label'=>'TaxConflict', 'enabled'=>1, 'position'=>125, 'notnull'=>0, 'visible'=>1),
		'raw_data' => array('type'=>'text', 'label'=>'RawData', 'enabled'=>1, 'position'=>130, 'notnull'=>0, 'visible'=>0),
		'date_creation' => array('type'=>'datetime', 'label'=>'DateCreation', 'enabled'=>1, 'position'=>500, 'notnull'=>1, 'visible'=>2),
		'tms' => array('type'=>'timestamp', 'label'=>'DateModification', 'enabled'=>1, 'position'=>501, 'notnull'=>0, 'visible'=>2),
		'fk_user_creat' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserAuthor', 'enabled'=>1, 'position'=>510, 'notnull'=>0, 'visible'=>0),
		'fk_user_modif' => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserModif', 'enabled'=>1, 'position'=>511, 'notnull'=>0, 'visible'=>0),
		'import_key' => array('type'=>'varchar(14)', 'label'=>'ImportId', 'enabled'=>1, 'position'=>1000, 'notnull'=>0, 'visible'=>0),
	);

	public $rowid;
	public $entity;
	public $transaction_id;
	public $bank_account_id;
	public $emitted_at;
	public $settled_at;
	public $amount;
	public $currency;
	public $side;
	public $operation_type;
	public $status;
	public $label;
	public $note;
	public $reference;
	public $vat_amount;
	public $vat_rate;
	public $counterparty_name;
	public $counterparty_iban;
	public $attachment_ids;
	public $ignored;
	public $fk_bank;
	public $fk_payment;
	public $tax_conflict;
	public $raw_data;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		global $conf;
		
		$this->entity = $conf->entity;
		$this->date_creation = dol_now();
		$this->fk_user_creat = $user->id;

		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		return $result;
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		$this->fk_user_modif = $user->id;
		
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = false)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Get list of pending transactions (not matched and not ignored)
	 *
	 * @return array|int Array of transaction objects, or -1 if error
	 */
	public function fetchPending()
	{
		global $conf;
		
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $this->table_element;
		$sql .= " WHERE entity = " . $conf->entity;
		$sql .= " AND fk_bank IS NULL";
		$sql .= " AND ignored = 0";
		$sql .= " ORDER BY emitted_at DESC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$transactions = array();
			
			while ($obj = $this->db->fetch_object($resql)) {
				$transaction = new QontoTransaction($this->db);
				$transaction->fetch($obj->rowid);
				$transactions[] = $transaction;
			}
			
			$this->db->free($resql);
			return $transactions;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Match transaction with an invoice
	 * Note: Invoice links are now managed through fk_bank -> payment -> invoice relationships
	 * The match status is determined by fk_bank presence, not stored separately
	 *
	 * @param int $invoiceId Invoice ID
	 * @param string $invoiceType 'customer' or 'supplier'
	 * @param User $user User object
	 * @return int <0 if KO, >0 if OK
	 */
	public function matchWithInvoice($invoiceId, $invoiceType, User $user)
	{
		// Invoice links are now managed through fk_bank
		// Match status is derived from fk_bank being set
		return $this->update($user);
	}

	/**
	 * Try to automatically match this transaction with a Dolibarr bank line
	 * Matches by: amount, date (within 2 days), and bank account
	 *
	 * @param User $user User object
	 * @return int >0 if matched, 0 if no match found, <0 if error
	 */
	public function autoMatch(User $user)
	{
		global $conf;

		// Get Dolibarr bank account ID from Qonto bank account ID
		$sql = "SELECT ba.rowid as dolibarr_account_id";
		$sql .= " FROM ".MAIN_DB_PREFIX."bank_account as ba";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
		$sql .= " WHERE ef.qonto_bank_id = '".$this->db->escape($this->bank_account_id)."'";
		$sql .= " AND ba.entity = ".$conf->entity;
		
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog("QontoTransaction::autoMatch - Failed to find Dolibarr account for Qonto ID ".$this->bank_account_id, LOG_WARNING);
			return 0;
		}
		
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			dol_syslog("QontoTransaction::autoMatch - No Dolibarr account linked to Qonto ID ".$this->bank_account_id, LOG_WARNING);
			return 0;
		}
		
		$dolibarrAccountId = $obj->dolibarr_account_id;
		
	// Search for matching bank line
	// Match criteria: exact date, exact amount, same direction (sign), same account
	// Note: In Dolibarr, debits are negative and credits are positive
	$searchDate = date('Y-m-d', $this->settled_at);
	
	// Determine the amount with correct sign based on side
	$searchAmount = ($this->side == 'debit') ? -abs($this->amount) : abs($this->amount);
	
	$sql = "SELECT b.rowid";
	$sql .= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sql .= " WHERE b.fk_account = ".(int)$dolibarrAccountId;
	$sql .= " AND b.amount = ".$searchAmount; // Exact amount with sign
	$sql .= " AND b.datev = '".$this->db->escape($searchDate)."'"; // Exact date
	$sql .= " AND b.rowid NOT IN (";
	$sql .= "   SELECT fk_bank FROM ".MAIN_DB_PREFIX."qonto_transactions WHERE fk_bank IS NOT NULL";
	$sql .= " )";
	$sql .= " LIMIT 1";
		
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				// Match found!
				$this->fk_bank = $obj->rowid;
				return $this->update($user);
			}
		}
		
		return 0;
	}

	/**
	 * Manually link this transaction to a Dolibarr bank line
	 *
	 * @param int $bankLineId Dolibarr bank line ID
	 * @param User $user User object
	 * @return int <0 if KO, >0 if OK
	 */
	public function linkToBankLine($bankLineId, User $user)
	{
		$this->fk_bank = $bankLineId;
		
		return $this->update($user);
	}
	
	/**
	 * Get linked invoices through bank line
	 * This is the proper way to retrieve invoice information
	 *
	 * @return array Array with 'customer' => array of customer invoice IDs, 'supplier' => array of supplier invoice IDs
	 */
	public function getLinkedInvoices()
	{
		$result = array('customer' => array(), 'supplier' => array());
		
		if (empty($this->fk_bank)) {
			dol_syslog("QontoTransaction::getLinkedInvoices - No fk_bank set for transaction ".$this->rowid, LOG_DEBUG);
			return $result;
		}
		
		// Get payment ID from bank_url table (Dolibarr's link table)
		// The bank_url table links bank lines to their source objects (payments, etc.)
		// Note: A bank line can have multiple URLs (company, payment, etc.)
		$sql = "SELECT url, type FROM ".MAIN_DB_PREFIX."bank_url WHERE fk_bank = ".(int)$this->fk_bank;
		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numUrls = $this->db->num_rows($resql);
			
			// Loop through all URLs to find payment URLs
			while ($obj = $this->db->fetch_object($resql)) {
				$urlType = $obj->type;
				$url = $obj->url;
				
				// Determine payment type from URL path (more reliable than type field)
				// Customer payments: /compta/paiement/card.php or /payment/card.php
				// Supplier payments: /fourn/paiement/card.php
				$isCustomerPayment = (strpos($url, '/compta/paiement/') !== false || strpos($url, '/payment/card.php') !== false);
				$isSupplierPayment = (strpos($url, '/fourn/paiement/') !== false);
				
				if ($isCustomerPayment || $isSupplierPayment) {
					// Try to extract payment ID from URL
					if (preg_match('/[?&]id=(\d+)/', $url, $matches)) {
						$paymentId = (int)$matches[1];
					} else {
						// URL is malformed (e.g., /fourn/paiement/card.php?id= with no ID)
						// Try to find payment ID directly from bank line
						
						if ($isSupplierPayment) {
							// Search in supplier payment table by bank line
							$sqlPay = "SELECT pf.rowid FROM ".MAIN_DB_PREFIX."paiementfourn as pf";
							$sqlPay .= " WHERE pf.fk_bank = ".(int)$this->fk_bank;
							$resqlPay = $this->db->query($sqlPay);
							if ($resqlPay && $this->db->num_rows($resqlPay) > 0) {
								$objPay = $this->db->fetch_object($resqlPay);
								$paymentId = (int)$objPay->rowid;
							}
						} elseif ($isCustomerPayment) {
							// Search in customer payment table by bank line
							$sqlPay = "SELECT p.rowid FROM ".MAIN_DB_PREFIX."paiement as p";
							$sqlPay .= " WHERE p.fk_bank = ".(int)$this->fk_bank;
							$resqlPay = $this->db->query($sqlPay);
							if ($resqlPay && $this->db->num_rows($resqlPay) > 0) {
								$objPay = $this->db->fetch_object($resqlPay);
								$paymentId = (int)$objPay->rowid;
							}
						}
					}
					
					if (!empty($paymentId)) {
						if ($isCustomerPayment) {
							// Customer payment - get invoices
							$sqlInv = "SELECT fk_facture FROM ".MAIN_DB_PREFIX."paiement_facture WHERE fk_paiement = ".$paymentId;
							$resqlInv = $this->db->query($sqlInv);
							if ($resqlInv) {
								while ($objInv = $this->db->fetch_object($resqlInv)) {
									if (!in_array($objInv->fk_facture, $result['customer'])) {
										$result['customer'][] = $objInv->fk_facture;
									}
								}
							}
						} elseif ($isSupplierPayment) {
							// Supplier payment - get invoices
							$sqlInv = "SELECT fk_facturefourn FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn WHERE fk_paiementfourn = ".$paymentId;
							$resqlInv = $this->db->query($sqlInv);
							if ($resqlInv) {
								while ($objInv = $this->db->fetch_object($resqlInv)) {
									if (!in_array($objInv->fk_facturefourn, $result['supplier'])) {
										$result['supplier'][] = $objInv->fk_facturefourn;
									}
								}
							}
						}
					}
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Get the first linked invoice (customer or supplier)
	 * Returns array with 'id' => invoice_id, 'type' => 'customer'|'supplier', 'object' => Invoice object
	 *
	 * @return array|null Array with invoice info or null if no invoice found
	 */
	public function getFirstLinkedInvoice()
	{
		$invoices = $this->getLinkedInvoices();
		
		if (!empty($invoices['customer'])) {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$invoice = new Facture($this->db);
			if ($invoice->fetch($invoices['customer'][0]) > 0) {
				return array('id' => $invoices['customer'][0], 'type' => 'customer', 'object' => $invoice);
			}
		}
		
		if (!empty($invoices['supplier'])) {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
			$invoice = new FactureFournisseur($this->db);
			if ($invoice->fetch($invoices['supplier'][0]) > 0) {
				return array('id' => $invoices['supplier'][0], 'type' => 'supplier', 'object' => $invoice);
			}
		}
		
		return null;
	}
	
	/**
	 * Count attachments for linked invoices
	 *
	 * @return int Number of attachments
	 */
	public function countInvoiceAttachments()
	{
		global $conf;
		
		$invoiceInfo = $this->getFirstLinkedInvoice();
		if (!$invoiceInfo) {
			return 0;
		}
		
		$invoice = $invoiceInfo['object'];
		
		// Build filepath matching Dolibarr's file organization
		// Customer invoices: facture/[ref] (no subdirectory)
		// Supplier invoices: fournisseur/facture/[exdir]/[ref] (with subdirectory based on ID)
		if ($invoiceInfo['type'] == 'customer') {
			$filepath = 'facture/'.$invoice->ref;
		} else {
			$subdir = get_exdir($invoice->id, 2, 0, 0, $invoice, 'invoice_supplier');
			$filepath = 'fournisseur/facture/'.$subdir.$invoice->ref;
		}
		
		// Remove trailing slash if present
		$filepath = rtrim($filepath, '/');
		
		$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."ecm_files";
		$sql .= " WHERE filepath = '".$this->db->escape($filepath)."'";
		$sql .= " AND filename != ''";
		$sql .= " AND entity = ".$conf->entity;
		
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return (int)$obj->nb;
		} else {
			dol_syslog("QontoTransaction::countInvoiceAttachments - Database query failed: ".$this->db->lasterror(), LOG_ERR);
		}
		
		return 0;
	}
	
	/**
	 * Get payment ID through bank line
	 *
	 * @return array Array with 'id' => payment_id, 'type' => 'payment'|'payment_supplier'
	 */
	public function getLinkedPayment()
	{
		if (empty($this->fk_bank)) {
			return null;
		}
		
		// Use bank_url table to get payment info
		$sql = "SELECT url, type FROM ".MAIN_DB_PREFIX."bank_url WHERE fk_bank = ".(int)$this->fk_bank;
		$resql = $this->db->query($sql);
		
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->type == 'payment' || $obj->type == 'payment_supplier') {
				// Extract payment ID from URL
				if (preg_match('/[?&]id=(\d+)/', $obj->url, $matches)) {
					return array('id' => (int)$matches[1], 'type' => $obj->type);
				}
			}
		}
		
		return null;
	}
}

