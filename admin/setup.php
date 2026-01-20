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
 * \file    doliqonto/admin/setup.php
 * \ingroup doliqonto
 * \brief   Qonto setup page.
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
require_once '../lib/qonto.lib.php';

// Translations
$langs->loadLangs(array("admin", "doliqonto@doliqonto"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$authMethod = getDolGlobalString('QONTO_AUTH_METHOD', 'api_key');

$arrayofparameters = array(
	'QONTO_AUTH_METHOD' => array('type'=>'select', 'values'=>array('api_key'=>'API Key (Classic)', 'oauth2'=>'OAuth 2.0'), 'enabled'=>1),
	'QONTO_API_KEY' => array('type'=>'password', 'css'=>'minwidth500', 'enabled'=>($authMethod == 'api_key')),
	'QONTO_ORGANIZATION_SLUG' => array('type'=>'varchar', 'css'=>'minwidth300', 'enabled'=>($authMethod == 'api_key')),
	'QONTO_OAUTH_CLIENT_ID' => array('type'=>'varchar', 'css'=>'minwidth500', 'enabled'=>($authMethod == 'oauth2')),
	'QONTO_OAUTH_CLIENT_SECRET' => array('type'=>'password', 'css'=>'minwidth500', 'enabled'=>($authMethod == 'oauth2')),
	'QONTO_ACCESS_TOKEN' => array('type'=>'password', 'css'=>'minwidth500', 'enabled'=>($authMethod == 'oauth2'), 'readonly'=>true),
	'QONTO_REFRESH_TOKEN' => array('type'=>'password', 'css'=>'minwidth500', 'enabled'=>($authMethod == 'oauth2'), 'readonly'=>true),
	'QONTO_TOKEN_EXPIRES_AT' => array('type'=>'varchar', 'css'=>'minwidth200', 'enabled'=>($authMethod == 'oauth2'), 'readonly'=>true),
	'QONTO_AUTO_SYNC_ENABLED' => array('type'=>'yesno', 'enabled'=>1),
	'QONTO_SYNC_DAYS_BACK' => array('type'=>'integer', 'css'=>'width100', 'enabled'=>1),
	'QONTO_AUTO_MATCH_ENABLED' => array('type'=>'yesno', 'enabled'=>1),
	'QONTO_AUTO_ATTACH_SYNC' => array('type'=>'yesno', 'enabled'=>1),
);

/*
 * Actions
 */

if ($action == 'update') {
	$error = 0;

	foreach ($arrayofparameters as $key => $val) {
		if (isset($val['enabled']) && !$val['enabled']) {
			continue;
		}
		$value = GETPOST($key, 'alpha');
		
		if ($val['type'] == 'yesno') {
			$value = ($value ? 1 : 0);
		}
		
		$res = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
		if (!$res > 0) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

if ($action == 'test_connection') {
	dol_include_once('/doliqonto/class/qontoapi.class.php');
	
	$qontoApi = new QontoApi($db);
	$result = $qontoApi->testConnection();
	
	if ($result > 0) {
		setEventMessages($langs->trans("QontoConnectionSuccess"), null, 'mesgs');
	} else {
		setEventMessages($qontoApi->error, null, 'errors');
	}
}

if ($action == 'disconnect_qonto') {
	dolibarr_del_const($db, 'QONTO_ACCESS_TOKEN', $conf->entity);
	dolibarr_del_const($db, 'QONTO_REFRESH_TOKEN', $conf->entity);
	dolibarr_del_const($db, 'QONTO_TOKEN_EXPIRES_AT', $conf->entity);
	dolibarr_del_const($db, 'QONTO_ORGANIZATION_SLUG', $conf->entity);
	setEventMessages($langs->trans("QontoDisconnected"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}



/*
 * View
 */

$page_name = "QontoSetup";

llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = qontoAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleSetup"), -1, 'doliqonto@doliqonto');

// Setup page goes here
print '<div class="info">';
print img_info().' '.$langs->trans("QontoAPIKeyHelp");
print '</div>';

if ($authMethod == 'oauth2') {
	print '<div class="warning">';
	print img_warning().' '.$langs->trans("OAuthFeatureWarning");
	print '</div>';
}

print '<br>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

foreach ($arrayofparameters as $key => $val) {
	if (isset($val['enabled']) && !$val['enabled']) {
		continue;
	}
	
	print '<tr class="oddeven">';
	print '<td>';
	print '<span id="helplink'.$key.'" class="spanforparamtooltip">'.$langs->trans($key).'</span>';
	print '</td>';
	print '<td>';

	if ($val['type'] == 'yesno') {
		print ajax_constantonoff($key);
	} elseif ($val['type'] == 'select') {
		$valuetoshow = getDolGlobalString($key);
		print '<select class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" name="'.$key.'" id="'.$key.'">';
		foreach ($val['values'] as $selectKey => $selectLabel) {
			$selected = ($valuetoshow == $selectKey) ? ' selected' : '';
			print '<option value="'.$selectKey.'"'.$selected.'>'.$selectLabel.'</option>';
		}
		print '</select>';
	} elseif ($val['type'] == 'password') {
		$valuetoshow = getDolGlobalString($key);
		$readonlyAttr = (!empty($val['readonly']) ? ' readonly="readonly" disabled="disabled"' : '');
		print '<input type="password" class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" name="'.$key.'" id="'.$key.'" value="'.$valuetoshow.'"'.$readonlyAttr.'>';
	} else {
		$valuetoshow = getDolGlobalString($key);
		$readonlyAttr = (!empty($val['readonly']) ? ' readonly="readonly" disabled="disabled"' : '');
		print '<input type="text" class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" name="'.$key.'" id="'.$key.'" value="'.$valuetoshow.'"'.$readonlyAttr.'>';
	}
	
	print '</td>';
	print '</tr>';
}

print '</table>';

print '<br><div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print '<br>';

$currentAuthMethod = getDolGlobalString('QONTO_AUTH_METHOD', 'api_key');

if ($currentAuthMethod == 'oauth2') {
	// OAuth2 mode - Connect with Qonto button
	$hasToken = getDolGlobalString('QONTO_ACCESS_TOKEN');
	$tokenExpiry = getDolGlobalString('QONTO_TOKEN_EXPIRES_AT');
	$isTokenValid = $hasToken && $tokenExpiry && $tokenExpiry > time();

	if (!$isTokenValid) {
		print '<div class="center" style="margin: 20px 0;">';
		dol_include_once('/doliqonto/class/qontoapi.class.php');
		$qontoApi = new QontoApi($db);
		$authUrl = $qontoApi->getAuthorizationUrl();
		print '<a href="'.$authUrl.'" class="button" style="background: #6C5CE7; color: white; font-weight: bold; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">';
		print '<span style="font-size: 16px;">🔗 '.$langs->trans("ConnectWithQonto").'</span>';
		print '</a>';
		print '<br><br><span class="opacitymedium">'.$langs->trans("QontoOAuthHelp").'</span>';
		print '</div>';
	} else {
		print '<div class="center" style="margin: 20px 0;">';
		print '<span class="badge badge-status4" style="font-size: 14px;">✓ '.$langs->trans("QontoConnected").'</span>';
		print '<br><br>';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display: inline-block; margin-right: 10px;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="test_connection">';
		print '<input type="submit" class="button" value="'.$langs->trans("TestConnection").'">';
		print '</form>';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display: inline-block;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="disconnect_qonto">';
		print '<input type="submit" class="button button-delete" value="'.$langs->trans("DisconnectQonto").'">';
		print '</form>';
		print '</div>';
	}
} else {
	// API Key mode - Simple test connection button
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="test_connection">';
	print '<div class="center" style="margin: 20px 0;">';
	print '<input type="submit" class="button" value="'.$langs->trans("TestConnection").'">';
	print '</div>';
	print '</form>';
}

print dol_get_fiche_end();

// Page end
llxFooter();
$db->close();
