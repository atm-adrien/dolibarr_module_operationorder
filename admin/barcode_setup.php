<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/operationorder.php
 * 	\ingroup	operationorder
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include '../../main.inc.php'; // From htdocs directory
if (! $res) {
    $res = @include '../../../main.inc.php'; // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/operationorder.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('/operationorder/class/operationorderbarcode.class.php');
dol_include_once('/operationorder/class/operationorderbarcodeimplist.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/modules/barcode/doc/tcpdfbarcode.modules.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';



require_once DOL_DOCUMENT_ROOT.'/core/lib/barcode.lib.php';


// Translations
$langs->loadLangs(array('operationorder@operationorder', 'admin', 'other'));

// Access control
if (! $user->admin && empty($user->rights->operationorder->status->write)) {
    accessforbidden();
}

//object
$object=new OperationOrderBarCode($db);
$obj = new stdClass;
$obj->label = "Annuler";
$obj->code = "IMPAnnul";
$TBarCodes[] = $obj;

$obj = new stdClass;
$obj->label = "Fin de journée";
$obj->code = "IMPFin";
$TBarCodes[] = $obj;
$TBarCodes = array_merge($TBarCodes, $object->fetchAll('', '', array('entity' => $conf->entity)));
//$TBarCodes = $object->fetchAll('', '', array('entity' => $conf->entity));
//echo '<pre>'; var_dump($TBarCodes);

// Parameters
$action = GETPOST('action', 'alpha');
$label_barcode = GETPOST('imp_label', 'alpha');
$id_barcode = GETPOST('barcodeid', 'alpha');

$moduleBarcode = new modTcpdfbarcode($db);

/*
 * Actions
 */

if($action == 'addbarcodeimp'){

    $error = 0;

    $object->label = $label_barcode;

    $sql = "SELECT MAX(code) as code FROM ".MAIN_DB_PREFIX."operationorderbarcode";
    $resql = $db->query($sql);

    if($resql){

        if($db->num_rows($resql) > 0){

            $obj = $db->fetch_object($resql);
            $last_number = intval(substr($obj->code, 3));
            $codenumber = str_pad(($last_number + 1), 5, '0', STR_PAD_LEFT);

        } else {
            $codenumber = str_pad(1, 5, '0', STR_PAD_LEFT);
        }

        $object->code = 'IMP'.$codenumber;

        $res = $object->create($user);
        if($res < 0){
            $error++;
        }

    } else {
        $error++;
    }

    if($error){
        header('Location: '.$_SERVER['PHP_SELF']);
        setEventMessage('Error', 'errors');
    } else {
        header('Location: '.$_SERVER['PHP_SELF']);
        setEventMessage($langs->trans('BarCodeAdded'));
    }


} elseif($action == 'ask_deletebarcode') {

    $error = 0;

    $res = $object->fetch($id_barcode);
    if($res < 0) $error++;

    if(!$error){
        $res = $object->delete($user);
        if($res < 0) $error ++;
    }

    if($error){
        header('Location: '.$_SERVER['PHP_SELF']);
        setEventMessage('Error', 'errors');
    } else {
        header('Location: '.$_SERVER['PHP_SELF']);
        setEventMessage($langs->trans('BarCodeDeleted'));
    }
} elseif($action == 'generatedocument') {

	$userCodes = GETPOST('usercodes', 'int');
    $barcodeImpList = new OperationOrderBarCodeImpList($db);

    $res = $barcodeImpList->generateDocument('', $langs, $userCodes);

    if($res > 0){
        setEventMessage('FileGenerated');
    } else {
        setEventMessage('Error', 'errors');

    }
} elseif (preg_match('/set_(.*)/', $action, $reg))
{
	$code=$reg[1];

	$theValue = GETPOST($code);
	if(is_array($theValue)){
		$theValue = implode(',',$theValue);
	}

	if (dolibarr_set_const($db, $code, $theValue, 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */

$page_name = "BarCode";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = operationorderAdminPrepareHead();
dol_fiche_head(
    $head,
    'barcode',
    $langs->trans("Module104088Name"),
    -1,
    "operationorder@operationorder"
);

// Setup page goes here
$form=new Form($db);
$formfile = new FormFile($db);


//BarCodeImp List

print '<table class="noborder" width="100%">';

setup_print_title($langs->trans("OPERATION_ORDER_BARCODE_TYPE"));

//BarCodeSetup
$confKey='OPERATION_ORDER_BARCODE_TYPE';
$TAvailableBarCode = array();
$sql = "SELECT rowid, code as encoding, libelle as label, coder, example";
$sql.= " FROM ".MAIN_DB_PREFIX."c_barcode_type";
$sql.= " WHERE entity = ".$conf->entity;
$sql.= " ORDER BY code";

dol_syslog("clitheobald/admin/clitheobald_setup.php", LOG_DEBUG);
$resql=$db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	if ($num > 0) {
		while ($obj = $db->fetch_object($resql)) {
			$TAvailableBarCode[$obj->encoding] = $obj->label . ' ' . $langs->trans('BarcodeDesc' . $obj->encoding);
		}
	}
} else {
	setEventMessage($db->lasterror,'errors');
}

$customInputHtml = $form->selectarray($confKey, $TAvailableBarCode, $conf->global->OPERATION_ORDER_BARCODE_TYPE, 1);

setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

setup_print_title($langs->trans("BarCodeImpSetup"));

print '<tr>';
print '<th>'.$langs->trans("Label").'</th>';
print '<th>'.$langs->trans("Code").'</th>';
print '</tr>';

print '<tr>';
foreach($TBarCodes as $barcode){
    if(strstr($barcode->code, 'IMP')){

        print '<tr id = "barcode_'.$barcode->id.'">';
        print '<td class="center barcode_label">'.$barcode->label.'</td>';

        // Build barcode on disk (not used, this is done to make debug easier)
	    $barcodetype=empty($conf->global->OPERATION_ORDER_BARCODE_TYPE)?'C128':$conf->global->OPERATION_ORDER_BARCODE_TYPE;
        $result = $moduleBarcode->writeBarCode($barcode->code, $barcodetype, 'Y');
        // Generate on the fly and output barcode with generator
        $url = DOL_URL_ROOT.'/viewimage.php?modulepart=barcode&amp;generator=tcpdfbarcode&amp;code='.urlencode($barcode->code).'&amp;encoding=C128';
        //print $url;
        $code =  '<img src="'.$url.'" title="'.$barcode->code.'" border="0">';
        print '<td class="center barcode_code">'.$code.'<br />'.$barcode->code.'</td>';
        print '<td class="center delete_action"><a href="'.$_SERVER["PHP_SELF"].'?action=ask_deletebarcode&barcodeid='.$barcode->id.'">';
		print ($barcode->code == 'IMPAnnul' || $barcode->code == 'IMPFin' ? '' : img_delete()) ;
		print '</a></td>';
        print '</tr>';
    }
}
print '</tr>';

print '</table>';

//Add a barcode

print '<form name="addproduct" action="' . $_SERVER['PHP_SELF'] .'" method="POST">' . "\n";
print '<input type="hidden" name="action" value="addbarcodeimp">' . "\n";

print '<div class="right">';
print '<span>'.$langs->trans("Label").'</span>';
print '<input type="text" id="imp_label" name="imp_label"><button type="submit" class="button" >'.$langs->trans('AddBarCode').'</button>';
print '</div>';
print '</form>';

//Show Barcode PDF

$filedir = $conf->operationorder->multidir_output[$conf->entity];

print $formfile->showdocuments('operationorder', '', $filedir, $_SERVER['PHP_SELF'], 0, 0, '', 1, 1, 0, 48, 1);

print '<form name="generatedocument" action="' . $_SERVER['PHP_SELF'] .'" method="POST">' . "\n";
print '<input type="hidden" name="action" value="generatedocument">' . "\n";

print '<div class="right">';
print '<button type="submit" class="button" >'.$langs->transnoentities('GenerateFile').'</button>';
print '<a href="'.$_SERVER['PHP_SELF'].'?action=generatedocument&usercodes=1" class="button">'.$langs->transnoentities('GenerateFile'). ' ' . $langs->trans('User').'</a>';
print '</div>';

print '</form>';


dol_fiche_end(-1);

llxFooter();

$db->close();
