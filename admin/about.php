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
 * \file    doliqonto/admin/about.php
 * \ingroup doliqonto
 * \brief   About page of module Qonto.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once '../lib/qonto.lib.php';

// Translations
$langs->loadLangs(array("errors", "admin", "doliqonto@doliqonto"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

/*
 * View
 */

$page_name = "QontoAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = qontoAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("ModuleSetup"), -1, 'doliqonto@doliqonto');

dol_include_once('/doliqonto/core/modules/modDoliQonto.class.php');
$tmpmodule = new modDoliQonto($db);

print '<div class="moduledesclong">';
print '<p><strong>'.$langs->trans("Version").':</strong> '.$tmpmodule->version.'</p>';
print '<p><strong>'.$langs->trans("Author").':</strong> '.$tmpmodule->editor_name.'</p>';
if (!empty($tmpmodule->editor_url)) {
	print '<p><strong>'.$langs->trans("Website").':</strong> <a href="'.$tmpmodule->editor_url.'" target="_blank">'.$tmpmodule->editor_url.'</a></p>';
}
print '<br>';
print '<p>'.nl2br($tmpmodule->description).'</p>';
print '<br>';
print '<p>'.nl2br($tmpmodule->descriptionlong).'</p>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
