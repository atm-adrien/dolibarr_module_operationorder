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

require 'config.php';
dol_include_once('operationorder/class/operationorder.class.php');

if(empty($user->rights->operationorder->read)) accessforbidden();

$langs->load('abricot@abricot');
$langs->load('operationorder@operationorder');


$massaction = GETPOST('massaction', 'alpha');
$confirmmassaction = GETPOST('confirmmassaction', 'alpha');
$toselect = GETPOST('toselect', 'array');

$object = new OperationOrder($db);

$hookmanager->initHooks(array('operationorderlist'));

if ($object->isextrafieldmanaged)
{
    $extrafields = new ExtraFields($db);
    $extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
}

/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (!empty($confirmmassaction) && $massaction != 'presend' && $massaction != 'confirm_presend')
{
	if($massaction == 'delete' && !empty($toselect)){
		foreach ($toselect as $deleteId){
			$objectToDelete = new OperationOrder($db);
			$res = $objectToDelete->fetch($deleteId);
			if($res>0){
				if($objectToDelete->delete($user)<0)
				{
					setEventMessage($langs->trans('OperationOrderDeleteError', $objectToDelete->ref), 'errors');
				}
			}
			else{
				setEventMessage($langs->trans('OperationOrderNotFound'), 'warnings');
			}
		}

		header('Location: '.dol_buildpath('/operationorder/list.php', 1));
		exit;
	}

    $massaction = '';
}


if (empty($reshook))
{
	// do action from GETPOST ...
}


/*
 * View
 */

llxHeader('', $langs->trans('OperationOrderList'), '', '');

//$type = GETPOST('type');
//if (empty($user->rights->operationorder->all->read)) $type = 'mine';

// TODO ajouter les champs de son objet que l'on souhaite afficher
$keys = array_keys($object->fields);
$fieldList = 't.'.implode(', t.', $keys);
if (!empty($object->isextrafieldmanaged))
{
    $keys = array_keys($extralabels);
	if(!empty($keys)) {
		$fieldList .= ', et.' . implode(', et.', $keys);
	}
}


$listViewName = 'operationorder';
$inputPrefix  = 'Listview_'.$listViewName.'_search_';
$search_overshootStatus = GETPOST($inputPrefix.'overshootstatus', 'int');

$sql = 'SELECT '.$fieldList;

// Add fields from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

// overshootStatus
$sqlSub = ' (SELECT (SUM(subsel.time_spent) - SUM(subsel.time_planned)) ';
$sqlSub.= ' FROM '.MAIN_DB_PREFIX.'operationorderdet subsel ';
$sqlSub.= ' WHERE subsel.fk_operation_order = t.rowid ) as overshootstatus ';
$sql.= ' ,'.$sqlSub;

$sql.= ' FROM '.MAIN_DB_PREFIX.'operationorder t ';

if (!empty($object->isextrafieldmanaged))
{
    $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'operationorder_extrafields et ON (et.fk_object = t.rowid)';
}

$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON (s.rowid = t.fk_soc)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_operationorder_type ctype ON (ctype.rowid = t.fk_c_operationorder_type)';

$sql.= ' WHERE  t.entity IN ('.getEntity('operationorder', 1).')';
//if ($type == 'mine') $sql.= ' AND t.fk_user = '.$user->id;

if(!empty($search_overshootStatus) && $search_overshootStatus > 0){


    $sqlSub = ' (SELECT (SUM(sub.time_spent) - SUM(sub.time_planned)) ';
    $sqlSub.= ' FROM '.MAIN_DB_PREFIX.'operationorderdet sub ';
    $sqlSub.= ' WHERE sub.fk_operation_order = t.rowid ) ';

    if(intval($search_overshootStatus) === 2){
        $sqlSub.= ' >= 0 ';
    }else{
        $sqlSub.= ' < 0 ';
    }

    $sql.= ' AND '.$sqlSub;
}


// Add where from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_operationorder', 'POST');

$nbLine = GETPOST('limit');
if (empty($nbLine)) $nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : $conf->global->MAIN_SIZE_LISTE_LIMIT;

// TODO : add this to a OperationOrderStatus method
// prepare status cache
$statusStatic = new OperationOrderStatus($db);
$TStatusList = $statusStatic->fetchAll(0, false, array('status' => 1));
$TStatusSearchList = array(); // for search form
if(!empty($TStatusList)){
	foreach ($TStatusList as $status ){
		$TStatusSearchList[$status->id] = $status->label;
	}
}
$htmlName = 'overshootstatus';
$selectArray = array(
    2 => $langs->trans('overshootStatus_Over'),
    1 => $langs->trans('overshootStatus_inTime'),
);

$formOvershootStatus = $form->selectarray($inputPrefix.$htmlName , $selectArray, $search_overshootStatus, 1);

// List configuration
$listViewConfig = array(
	'view_type' => 'list' // default = [list], [raw], [chart]
	,'allow-fields-select' => true
	,'limit'=>array(
		'nbLine' => $nbLine
	)
	,'list' => array(
		'title' => $langs->trans('OperationOrderList')
		,'image' => 'title_generic.png'
		,'picto_precedent' => '<'
		,'picto_suivant' => '>'
		,'noheader' => 0
		,'messageNothing' => $langs->trans('NoOperationOrder')
		,'picto_search' => img_picto('', 'search.png', '', 0)
		,'massactions'=>array(
			'delete'  => $langs->trans('Delete')
		)
	)
	,'subQuery' => array()
	,'link' => array()
	,'type' => array(
		'date_creation' => 'date' // [datetime], [hour], [money], [number], [integer]
		,'tms' => 'date'
	)
	,'search' => array(
		'date_creation' => array('search_type' => 'calendars', 'allow_is_null' => true)
		,'tms' => array('search_type' => 'calendars', 'allow_is_null' => false)
        ,'ref' => array('search_type' => true, 'table' => 't', 'field' => 'ref')
        ,'ref_client' => array('search_type' => true, 'table' => 't', 'field' => 'ref_client')
        ,'fk_soc' => array('search_type' => true, 'table' => 's', 'field' => array('nom','name_alias')) // input text de recherche sur plusieurs champs
        ,'fk_c_operationorder_type' => array('search_type' => true, 'table' => 'ctype', 'field' => array('code','label')) // input text de recherche sur plusieurs champs
		,'label' => array('search_type' => true, 'table' => array('t', 't'), 'field' => array('label')) // input text de recherche sur plusieurs champs
		,'status' => array('search_type' => $TStatusSearchList, 'to_translate' => true) // select html, la clé = le status de l'objet, 'to_translate' à true si nécessaire
        ,'overshootstatus' => array('search_type' => 'override', 'no-auto-sql-search'=>1, 'override' => $formOvershootStatus)
	)
	,'translate' => array()
	,'hide' => array(
		'rowid' // important : rowid doit exister dans la query sql pour les checkbox de massaction
	)
	,'title'=>array (
	    'ref' => $langs->trans($object->fields['ref']['label']),
        'ref_client' => $langs->trans($object->fields['ref_client']['label']),
        'fk_soc' => $langs->trans($object->fields['fk_soc']['label']),
        'fk_project' => $langs->trans($object->fields['fk_project']['label']),
        'fk_c_operationorder_type' => $langs->trans($object->fields['fk_c_operationorder_type']['label']),
        'overshootstatus' => $langs->trans('overshootStatus'),
        'status' => $langs->trans($object->fields['status']['label']),
    )
	,'eval'=>array(
        'overshootstatus' => '_getOvershootStatus(\'@rowid@\')'
    )
);



foreach ($object->fields as $key => $field){
    // visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create).
    // Using a negative value means field is not shown by default on list but can be selected for viewing)

    if(!isset($listViewConfig['title'][$key]) && !empty($field['visible']) && in_array($field['visible'], array(1,2,4)) ) {
        $listViewConfig['title'][$key] = $langs->trans($field['label']);
    }

    if(!isset($listViewConfig['hide'][$key]) && (empty($field['visible']) || $field['visible'] <= -1)){
        $listViewConfig['hide'][] = $key;
    }

    if(!isset($listViewConfig['eval'][$key])){
        $listViewConfig['eval'][$key] = '_getObjectOutputField(\''.$key.'\', \'@rowid@\', \'@val@\')';
    }
}



$r = new Listview($db, 'operationorder');

// Change view from hooks
$parameters=array('listViewConfig' => $listViewConfig);
$reshook=$hookmanager->executeHooks('listViewConfig',$parameters,$r);    // Note that $action and $object may have been modified by hook
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if ($reshook>0)
{
	$listViewConfig = $hookmanager->resArray;
}

echo $r->render($sql, $listViewConfig);

$parameters=array('sql'=>$sql);
$reshook=$hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

$formcore->end_form();

llxFooter('');
$db->close();


function _getObjectOutputField($key, $fk_operationOrder = 0, $val = '')
{
    $operationOrder = getOperationOrderFromCache($fk_operationOrder);
    if(!$operationOrder){return 'error';}

    return $operationOrder->showOutputFieldQuick($key);
}

function _getOvershootStatus($fk_operationOrder = 0)
{
    $operationOrder = getOperationOrderFromCache($fk_operationOrder);
    if(!$operationOrder){return 'error';}

    return $operationOrder->getOvershootStatus();
}

function getOperationOrderFromCache($fk_operationOrder){
    global $db, $TOperationOrderCache;


    if(empty($TOperationOrderCache[$fk_operationOrder])){
        $operationOrder = new OperationOrder($db);
        if($operationOrder->fetch($fk_operationOrder, false) <= 0)
        {
            return false;
        }

        $TOperationOrderCache[$fk_operationOrder] = $operationOrder;
    }
    else{
        $operationOrder = $TOperationOrderCache[$fk_operationOrder];
    }

    return $operationOrder;
}
