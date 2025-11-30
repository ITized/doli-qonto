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
 * \file    doliqonto/admin/oauth_callback.php
 * \ingroup doliqonto
 * \brief   Qonto OAuth2 callback handler
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

global $langs, $user, $db, $conf;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
dol_include_once('/doliqonto/class/qontoapi.class.php');

// Translations
$langs->loadLangs(array("admin", "doliqonto@doliqonto"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Get parameters from callback
$code = GETPOST('code', 'alpha');
$state = GETPOST('state', 'alpha');
$error = GETPOST('error', 'alpha');
$errorDescription = GETPOST('error_description', 'alpha');

// Check for errors from OAuth provider
if ($error) {
	setEventMessages($langs->trans("QontoOAuthError").': '.$error.' - '.$errorDescription, null, 'errors');
	header('Location: setup.php');
	exit;
}

// Verify state parameter (CSRF protection)
$savedState = $_SESSION['qonto_oauth_state'] ?? '';
if (empty($state) || $state !== $savedState) {
	setEventMessages($langs->trans("QontoOAuthStateError"), null, 'errors');
	header('Location: setup.php');
	exit;
}

// Clear state from session
unset($_SESSION['qonto_oauth_state']);

// Exchange authorization code for tokens
if ($code) {
	$qontoApi = new QontoApi($db);
	$result = $qontoApi->exchangeCodeForTokens($code);
	
	if ($result > 0) {
		setEventMessages($langs->trans("QontoConnectionSuccess"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("QontoOAuthTokenError").': '.$qontoApi->error, null, 'errors');
	}
} else {
	setEventMessages($langs->trans("QontoOAuthCodeMissing"), null, 'errors');
}

// Redirect back to setup page
header('Location: setup.php');
exit;
