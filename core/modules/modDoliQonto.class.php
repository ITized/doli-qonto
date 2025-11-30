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
 * \defgroup   doliqonto     Module DoliQonto
 * \brief      DoliQonto module descriptor.
 *
 * \file       core/modules/modDoliQonto.class.php
 * \ingroup    doliqonto
 * \brief      Description and activation file for module DoliQonto
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliQonto
 */
class modDoliQonto extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 185210;
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'doliqonto';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (advanced modules),'interface' (generic modules to allow integration of third party tools like Prestashop, Magento...),'other'
		$this->family = "financial";
		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';
		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleDoliQontoName' not found (DoliQonto is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description, used if translation string 'ModuleDoliQontoDesc' not found (DoliQonto is name of module).
		$this->description = "DoliQonto - Qonto bank integration for Dolibarr";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "DoliQonto integrates Qonto business banking with Dolibarr. Synchronize transactions, match payments with invoices, manage attachments, and validate tax information.";

		// Author
		$this->editor_name = 'Finta Ionut';
		$this->editor_url = 'https://itized.com';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where DOLIQONTO is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'doliqonto@doliqonto';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 0,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				//    '/doliqonto/css/qonto.css.php',
			),
			// Set this to relative path of js file if module has its own js file
			'js' => array(
				//    '/doliqonto/js/qonto.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			'hooks' => array(
				'invoicecard',
				'invoicesuppliercard',
				'bankcard',
			),
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/doliqonto/temp","/doliqonto/subdir");
		$this->dirs = array("/doliqonto/temp");

		// Config pages. Put here list of php page, stored into qonto/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@doliqonto");

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR'=>'modModuleToEnableFR'...)
		$this->depends = array();
		$this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = array("doliqonto@doliqonto");

		// Prerequisites
		$this->phpmin = array(7, 0); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(20, 0); // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'QontoWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('QONTO_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('QONTO_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array(
			1 => array('QONTO_API_URL', 'chaine', 'https://thirdparty.qonto.com/v2', 'Qonto API URL', 0, 'current', 0),
		);

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isset($conf->doliqonto) || !isset($conf->doliqonto->enabled)) {
			$conf->doliqonto = new stdClass();
			$conf->doliqonto->enabled = 0;
		}

		// Array to add new pages in new tabs
		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes/Widgets
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'qontowidget1.php@qonto',
			//      'note' => 'Widget provided by Qonto',
			//      'enabledbydefaulton' => 'Home',
			//  ),
		);

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		$this->cronjobs = array(
			0 => array(
				'label' => 'QontoSyncTransactions',
				'jobtype' => 'method',
				'class' => '/doliqonto/class/qontoapi.class.php',
				'objectname' => 'QontoApi',
				'method' => 'syncTransactions',
				'parameters' => '',
				'comment' => 'Synchronize transactions from Qonto',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => '$conf->doliqonto->enabled',
				'priority' => 50,
			),
		);

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		// Example:
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Read Qonto transactions';
		$this->rights[$r][4] = 'transaction';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Import Qonto transactions';
		$this->rights[$r][4] = 'transaction';
		$this->rights[$r][5] = 'import';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Match payments with invoices';
		$this->rights[$r][4] = 'payment';
		$this->rights[$r][5] = 'match';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Sync attachments';
		$this->rights[$r][4] = 'attachment';
		$this->rights[$r][5] = 'sync';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'Configure Qonto module';
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = 'write';
		$r++;
		/* END MODULEBUILDER PERMISSIONS */

		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu'=>'', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'top', // This is a Top menu entry
			'titre'=>'Qonto',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'qonto',
			'leftmenu'=>'',
			'url'=>'/doliqonto/transactions.php',
			'langs'=>'doliqonto@doliqonto', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliqonto->enabled', // Define condition to show or hide menu entry. Use '$conf->doliqonto->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->rights->doliqonto->transaction->read', // Use 'perms'=>'$user->rights->doliqonto->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
		);
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU TRANSACTIONS */
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=qonto',
			'type'=>'left',
			'titre'=>'Transactions',
			'prefix' => img_picto('', 'payment', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'qonto',
			'leftmenu'=>'qonto_transactions',
			'url'=>'/doliqonto/transactions.php',
			'langs'=>'doliqonto@doliqonto',
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliqonto->enabled',
			'perms'=>'$user->rights->doliqonto->transaction->read',
			'target'=>'',
			'user'=>2,
		);
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=qonto,fk_leftmenu=qonto_transactions',
			'type'=>'left',
			'titre'=>'PendingTransactions',
			'mainmenu'=>'qonto',
			'leftmenu'=>'qonto_pending',
			'url'=>'/doliqonto/transactions.php',
			'langs'=>'doliqonto@doliqonto',
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliqonto->enabled',
			'perms'=>'$user->rights->doliqonto->transaction->read',
			'target'=>'',
			'user'=>2,
		);
		/* END MODULEBUILDER LEFTMENU TRANSACTIONS */

		/* BEGIN MODULEBUILDER LEFTMENU ATTACHMENTS */
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=qonto',
			'type'=>'left',
			'titre'=>'Attachments',
			'prefix' => img_picto('', 'attach', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'qonto',
			'leftmenu'=>'qonto_attachments',
			'url'=>'/doliqonto/attachments.php',
			'langs'=>'doliqonto@doliqonto',
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliqonto->enabled',
			'perms'=>'$user->rights->doliqonto->attachment->sync',
			'target'=>'',
			'user'=>2,
		);
		/* END MODULEBUILDER LEFTMENU ATTACHMENTS */

		/* BEGIN MODULEBUILDER LEFTMENU TAX */
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=qonto',
			'type'=>'left',
			'titre'=>'TaxValidation',
			'prefix' => img_picto('', 'bill', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'qonto',
			'leftmenu'=>'qonto_taxvalidation',
			'url'=>'/doliqonto/taxvalidation.php',
			'langs'=>'doliqonto@doliqonto',
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliqonto->enabled',
			'perms'=>'$user->rights->doliqonto->transaction->read',
			'target'=>'',
			'user'=>2,
		);
		/* END MODULEBUILDER LEFTMENU TAX */

		/* BEGIN MODULEBUILDER LEFTMENU CONFIGURATION */
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=qonto',
			'type'=>'left',
			'titre'=>'Setup',
			'prefix' => img_picto('', 'setup', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'qonto',
			'leftmenu'=>'qonto_setup',
			'url'=>'/doliqonto/admin/setup.php',
			'langs'=>'doliqonto@doliqonto',
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliqonto->enabled',
			'perms'=>'$user->rights->doliqonto->setup->write',
			'target'=>'',
			'user'=>2,
		);
		/* END MODULEBUILDER LEFTMENU CONFIGURATION */
	}

	/**
	 * Function called when module is enabled.
	 * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 * It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/doliqonto/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extrafields during init
		//include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		//$extrafields = new ExtraFields($this->db);
		//$result1=$extrafields->addExtraField('qonto_myattr1', "New Attr 1 label", 'boolean', 1,  3, 'thirdparty',   0, 0, '', '', 1, '', 0, 0, '', '', 'doliqonto@doliqonto', '$conf->doliqonto->enabled');
		//$result2=$extrafields->addExtraField('qonto_myattr2', "New Attr 2 label", 'varchar', 1, 10, 'project',      0, 0, '', '', 1, '', 0, 0, '', '', 'doliqonto@doliqonto', '$conf->doliqonto->enabled');
		//$result3=$extrafields->addExtraField('qonto_myattr3', "New Attr 3 label", 'varchar', 1, 10, 'bank_account', 0, 0, '', '', 1, '', 0, 0, '', '', 'doliqonto@doliqonto', '$conf->doliqonto->enabled');
		//$result4=$extrafields->addExtraField('qonto_myattr4', "New Attr 4 label", 'select',  1,  3, 'thirdparty',   0, 1, '', array('options'=>array('code1'=>'Val1','code2'=>'Val2','code3'=>'Val3')), 1,'', 0, 0, '', '', 'doliqonto@doliqonto', '$conf->doliqonto->enabled');
		//$result5=$extrafields->addExtraField('qonto_myattr5', "New Attr 5 label", 'text',    1, 10, 'user',         0, 0, '', '', 1, '', 0, 0, '', '', 'doliqonto@doliqonto', '$conf->doliqonto->enabled');

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}

	/**
	 * Return path to ChangeLog file
	 *
	 * @return string Path to changelog
	 */
	public function getChangeLogList()
	{
		// Return the changelog content from ChangeLog.md
		$changelogPath = dol_buildpath('/doliqonto/ChangeLog.md', 0);
		if (file_exists($changelogPath)) {
			return file_get_contents($changelogPath);
		}
		
		return '';
	}
}
