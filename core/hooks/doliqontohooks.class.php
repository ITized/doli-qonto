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
 * \file    core/hooks/doliqontohooks.class.php
 * \ingroup doliqonto
 * \brief   Hooks for DoliQonto module
 */

/**
 * Class DoliQontoHooks
 */
class DoliQontoHooks
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;
	
	/**
	 * @var string Error message
	 */
	public $error = '';
	
	/**
	 * @var array Errors
	 */
	public $errors = array();
	
	/**
	 * @var array Hook results
	 */
	public $results = array();
	
	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;
	
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
	
	/**
	 * Execute action on bankcard page
	 * Add attachment count from linked invoices for Qonto transactions
	 *
	 * @param array $parameters Hook parameters
	 * @param object $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int <0 if KO, 0 if nothing done, >0 if OK
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;
		
		$contexts = explode(':', $parameters['context']);
		
		if (in_array('bankcard', $contexts)) {
			// Check if this bank line is linked to a Qonto transaction
			$bankLineId = $object->id;
			
			$sql = "SELECT qt.rowid, qt.transaction_id FROM ".MAIN_DB_PREFIX."qonto_transactions as qt";
			$sql .= " WHERE qt.fk_bank = ".(int)$bankLineId;
			$sql .= " AND qt.entity = ".$conf->entity;
			$sql .= " LIMIT 1";
			
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				
				// Get invoice attachments
				$attachmentCount = 0;
				
				// Get payment info from bank line
				$sqlPay = "SELECT fk_type, fk_element FROM ".MAIN_DB_PREFIX."bank";
				$sqlPay .= " WHERE rowid = ".(int)$bankLineId;
				$resqlPay = $this->db->query($sqlPay);
				
				if ($resqlPay && $this->db->num_rows($resqlPay) > 0) {
					$objPay = $this->db->fetch_object($resqlPay);
					$invoiceId = null;
					$invoiceTable = null;
					
					// Determine invoice from payment type
					if ($objPay->fk_type == 'payment') {
						// Customer payment
						$sqlInv = "SELECT fk_facture FROM ".MAIN_DB_PREFIX."paiement_facture";
						$sqlInv .= " WHERE fk_paiement = ".(int)$objPay->fk_element;
						$sqlInv .= " LIMIT 1";
						$resqlInv = $this->db->query($sqlInv);
						if ($resqlInv && $this->db->num_rows($resqlInv) > 0) {
							$objInv = $this->db->fetch_object($resqlInv);
							$invoiceId = $objInv->fk_facture;
							$invoiceTable = 'facture';
						}
					} elseif ($objPay->fk_type == 'payment_supplier') {
						// Supplier payment
						$sqlInv = "SELECT fk_facturefourn FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn";
						$sqlInv .= " WHERE fk_paiementfourn = ".(int)$objPay->fk_element;
						$sqlInv .= " LIMIT 1";
						$resqlInv = $this->db->query($sqlInv);
						if ($resqlInv && $this->db->num_rows($resqlInv) > 0) {
							$objInv = $this->db->fetch_object($resqlInv);
							$invoiceId = $objInv->fk_facturefourn;
							$invoiceTable = 'facture_fourn';
						}
					}
					
					// Count attachments for the invoice
					if ($invoiceId && $invoiceTable) {
						// Get invoice reference for filepath
						$invoiceRef = '';
						if ($invoiceTable == 'facture') {
							$sqlRef = "SELECT ref FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".(int)$invoiceId;
							$resqlRef = $this->db->query($sqlRef);
							if ($resqlRef && $this->db->num_rows($resqlRef) > 0) {
								$objRef = $this->db->fetch_object($resqlRef);
								$invoiceRef = $objRef->ref;
								$dirName = 'invoice';
							}
						} else {
							$sqlRef = "SELECT ref FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid = ".(int)$invoiceId;
							$resqlRef = $this->db->query($sqlRef);
							if ($resqlRef && $this->db->num_rows($resqlRef) > 0) {
								$objRef = $this->db->fetch_object($resqlRef);
								$invoiceRef = $objRef->ref;
								$dirName = 'supplier_invoice';
							}
						}
						
						if ($invoiceRef) {
							$sqlAtt = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."ecm_files";
							$sqlAtt .= " WHERE filename != '' AND filepath LIKE '%".$dirName."/".$invoiceRef."%'";
							$resqlAtt = $this->db->query($sqlAtt);
							if ($resqlAtt) {
								$objAtt = $this->db->fetch_object($resqlAtt);
								$attachmentCount = $objAtt->nb;
							}
						}
					}
				}
				
				// Display Qonto info
				$langs->load("doliqonto@doliqonto");
				
				$this->resprints = '<tr><td class="titlefield">'.$langs->trans("QontoTransaction").'</td>';
				$this->resprints .= '<td>';
				$this->resprints .= '<a href="'.DOL_URL_ROOT.'/custom/doliqonto/transactions.php?search_transaction_id='.urlencode($obj->transaction_id).'">';
				$this->resprints .= '<span class="badge badge-status4">✓ '.$langs->trans("LinkedToQonto").'</span>';
				$this->resprints .= '</a>';
				$this->resprints .= ' <span class="opacitymedium">('.substr($obj->transaction_id, 0, 30).'...)</span>';
				$this->resprints .= '</td></tr>';
				
				if ($attachmentCount > 0) {
					$this->resprints .= '<tr><td class="titlefield">'.$langs->trans("InvoiceAttachments").'</td>';
					$this->resprints .= '<td>';
					$this->resprints .= '<span class="badge badge-status4">'.$attachmentCount.' '.$langs->trans("Attachments").'</span>';
					if ($invoiceRef) {
						$this->resprints .= ' <span class="opacitymedium">(from '.$invoiceRef.')</span>';
					}
					$this->resprints .= '</td></tr>';
				}
			}
		}
		
		return 0;
	}
}
