<?php

require 'config.php';
dol_include_once('/operationorder/class/operationorder.class.php');
dol_include_once('/operationorder/class/operationorderaction.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');



if(empty($user->rights->operationorder->planning->read)) accessforbidden();

$langs->loadLangs(array('operationorder@operationorder'));

$title = $langs->trans("OperationOrderPlanning");
//if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/thirdpartynameonly/',$conf->global->MAIN_HTML_TITLE) && $object->name) $title=$object->name." - ".$title;
$help_url = '';

list($langjs, $dummy) = explode('_', $langs->defaultlang);

if($langjs == 'en') $langjs = 'en-gb';

$TIncludeCSS = array(
    '/operationorder/vendor/fullcalendar-4.4.0/packages/core/main.css',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/daygrid/main.css',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/timegrid/main.css'
);

$TIncludeJS = array(
    '/operationorder/vendor/fullcalendar-4.4.0/packages/core/main.js',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/core/locales-all.js',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/daygrid/main.js',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/interaction/main.js',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/timegrid/main.js',
    '/operationorder/vendor/fullcalendar-4.4.0/packages/moment/main.js'
);

$langs->loadLangs(array('operationorder@operationorder'));

$hookmanager->initHooks(array('operationorderplanning'));

$action = GETPOST('action');
$id_operationorder = GETPOST('operationorder');
$startTime = GETPOST('startTime');
$endTime = GETPOST('endTime');
$beginOfWeek = GETPOST('beginOfWeek');
$endOfWeek = GETPOST('endOfWeek');
$allDay = GETPOST('allDay');

$title = $langs->trans("OperationOrderPlanning");
//if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/thirdpartynameonly/',$conf->global->MAIN_HTML_TITLE) && $object->name) $title=$object->name." - ".$title;
$help_url = '';
llxHeader('', $title, $help_url, '', 0, 0, $TIncludeJS, $TIncludeCSS);

// test user event création right
$fk_status = $conf->global->OPODER_STATUS_ON_PLANNED;
$statusAllowed = new OperationOrderStatus($db);
$res = $statusAllowed->fetch($fk_status);
$userCanCreateEvent = 0;
if($res>0 && $statusAllowed->userCan($user, 'changeToThisStatus')){
	$userCanCreateEvent = 1;
}

if($action == "createOperationOrderAction"){

    global $langs;

    $res = createOperationOrderAction($startTime, $endTime,$allDay, $id_operationorder);

    if($res < 0){
        setEventMessage($langs->trans('ErrorORActionCreation'), 'errors');
    } else {
        setEventMessage($langs->trans('SucessORActionCreation'));
    }

}

//tableaux d'horaires à utiliser en fonction du planning utilisateur/groupe
$fullcalendar_scheduler_businessHours = array();

$Tfullcalendar_scheduler_businessHours_days = array('1'=>'lundi', '2'=>'mardi', '3'=>'mercredi', '4'=>'jeudi', '5' => 'vendredi', '6'=>'samedi', '7'=>'dimanche')

?>

    <script>

		operationOrderInterfaceUrl = "<?php print dol_buildpath('/operationorder/scripts/interface.php', 1); ?>?action=getPlannedOperationOrder";
		fullcalendarscheduler_initialLangCode = "<?php print !empty($conf->global->FULLCALENDARSCHEDULER_LOCALE_LANG) ? $conf->global->FULLCALENDARSCHEDULER_LOCALE_LANG : $langjs; ?>";
		fullcalendarscheduler_snapDuration = "<?php print !empty($conf->global->FULLCALENDARSCHEDULER_SNAP_DURATION) ? $conf->global->FULLCALENDARSCHEDULER_SNAP_DURATION : '00:15:00'; ?>";
		fullcalendarscheduler_aspectRatio = "<?php print !empty($conf->global->FULLCALENDARSCHEDULER_ASPECT_RATIO) ? $conf->global->FULLCALENDARSCHEDULER_ASPECT_RATIO : '1.6'; ?>";
		fullcalendarscheduler_minTime = "<?php print !empty($conf->global->FULLCALENDARSCHEDULER_MIN_TIME) ? $conf->global->FULLCALENDARSCHEDULER_MIN_TIME : '00:00'; ?>";
		fullcalendarscheduler_maxTime = "<?php print !empty($conf->global->FULLCALENDARSCHEDULER_MAX_TIME) ? $conf->global->FULLCALENDARSCHEDULER_MAX_TIME : '24:00'; ?>";

		// fullcalendar_scheduler_businessHours_days = [1, 2, 3, 4, 5];
		userCanCreateEvent = <?php print $userCanCreateEvent; ?>;

        eventSources_parameters = [
            {
                url: operationOrderInterfaceUrl,
                extraParams: {
                    eventsType: 'orPlanned'
                },
                failure: function() {
                    //document.getElementById('script-warning').style.display = 'block'
                }
            },
            {
                url: operationOrderInterfaceUrl,
                extraParams: {
                    eventsType: 'dayOff'
                },
                failure: function() {
                    //document.getElementById('script-warning').style.display = 'block'
                }
            },
            {
                url: operationOrderInterfaceUrl,
                extraParams: {
                    eventsType: 'dayFull'
                },
                failure: function() {
                    //document.getElementById('script-warning').style.display = 'block'
                }
            },
            {
                url: operationOrderInterfaceUrl,
                extraParams: {
                    eventsType: 'weekFull'
                },
                failure: function() {
                    //document.getElementById('script-warning').style.display = 'block'
                }
            }
        ]

		document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');

            var calendar = new FullCalendar.Calendar(calendarEl, {

				plugins: [ 'interaction', 'dayGrid', 'timeGrid'], // , 'list', 'rrule'
				defaultDate: '<?php print date('Y-m-d'); ?>',
				defaultView: 'timeGridWeek',
				snapDuration: fullcalendarscheduler_snapDuration,
				weekNumbers: true,
				weekNumbersWithinDays: true,
				weekNumberCalculation: 'ISO',
				header: {
					left: 'prev,next today',
					center: 'title',
					right: 'timeGridWeek'
				},
				selectable: userCanCreateEvent,
				minTime: fullcalendarscheduler_minTime,
				maxTime: fullcalendarscheduler_maxTime,
				scrollTime: '10:00:00',
				height: getFullCalendarHeight(),
				selectMirror: true,
				locale: fullcalendarscheduler_initialLangCode,
				eventLimit: true, // allow "more" link when too many events
                editable:true,
                businessHours: [],
                selectConstraint:'businessHours',
				eventDestroy: function(info) {
					$(info.el).tooltip({disabled: true});
				},
				eventRender: function(info) {

                    $(info.el).attr('title', info.event.extendedProps.msg);
                    $(info.el).attr('data-operationorderid', info.event.extendedProps.operationOrderId);
                    $(info.el).attr('data-operationorderactionid', info.event.extendedProps.operationOrderActionId);
                    $(info.el).attr('data-ope_percent', info.event.extendedProps.ope_percent);

                    $(info.el).tooltip({
                        track: true,
                        show: {
                            collision: "flipfit",
                            effect: 'toggle',
                            delay: 50
                        },
                        hide: {
                            delay: 0
                        },
                        container: "body",
                        tooltipClass: "operationOrderTooltip",
                        content: function () {
                            return this.getAttribute("title");
                        }
                    });

                    if ((info.event.extendedProps.operationOrderId) !== undefined) {
                        let eventTitle = $(info.el).find('.fc-title')[0];
                        $(eventTitle).html($(eventTitle).text());
                        let progressColor = 'green';
                        if ($(info.el).attr('data-ope_percent') > 100) progressColor = 'red';
                        $(info.el).append('<div style="position: absolute;bottom: 0;height:5px;width:100%;background-color:lightgrey;"><div style="height:100%;width:' + $(info.el).attr('data-ope_percent') + '%;background-color:' + progressColor + ';max-width:100%">&nbsp;</div>&nbsp;</div>')

                    }
                },
				eventSources: eventSources_parameters,
				loading: function(bool) {
					//document.getElementById('loading').style.display = bool ? 'block' : 'none';
				},
				eventClick: function(info) {
					// force open link into new url
					// info.jsEvent.preventDefault(); // don't let the browser navigate
					// if (info.event.url) {
					// 		window.open(info.event.url, "_blank");
					// 		return false;
					// }
				},
                select: function (selectionInfo) {
                    if(!selectionInfo.allDay) {
                        let startTimestamp = Math.floor(selectionInfo.start.getTime() / 1000);
                        let endTimestamp = Math.floor(selectionInfo.end.getTime() / 1000);

                        var beginOfWeek = Math.floor(calendar.view.activeStart.getTime() / 1000);
                        var endOfWeek = Math.floor(calendar.view.activeEnd.getTime() / 1000);

                        $.ajax({
                            url: '<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1); ?>?action=getTableDialogPlanable',
                            method: 'POST',
                            data: {
                                'url': window.location.href,
                                'startTime': startTimestamp,
                                'endTime': endTimestamp,
                                'beginOfWeek': beginOfWeek,
                                'endOfWeek': endOfWeek,
                                'allDay': selectionInfo.allDay
                            },
                            dataType: 'json',
                            // La fonction à apeller si la requête aboutie
                            success: function (data) {
                                $('#dialog-add-event').html(data.result);
                                operationorderneweventmodal.dialog("open");
                                operationorderneweventmodal.dialog({height: 'auto', width: 'auto'}); // resize to content
                                operationorderneweventmodal.parent().css({"top":"20%"});
                            }
                        });
                    }
                },
				dateClick: function(info) {
					//newEventModal(info.startStr);
				},
                eventResize: function(info) {

                    let startTimestamp = Math.floor(info.event.start.getTime()/1000);
                    let endTimestamp = Math.floor(info.event.end.getTime()/1000);
                    let fk_action = info.event.extendedProps.operationOrderActionId;
                    let action = 'resize';

                    $.ajax({
                        url: '<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1); ?>?action=updateOperationOrderAction',
                        method: 'POST',
                        data: {
                            'url' : window.location.href,
                            'startTime' : startTimestamp,
                            'endTime' : endTimestamp,
                            'fk_action' : fk_action,
                            'action' : action
                        },
                        dataType: 'json',
                        // La fonction à apeller si la requête aboutie
                        success: function (data) {
                            calendar.refetchEvents();
                        }
                    });
                },
                eventResizeStop: function(info) {
				    $('.operationOrderTooltip').hide();
                    calendar.refetchEvents();
                },
                eventDrop: function(eventDropInfo) {
				    if(!eventDropInfo.event.allDay)
				    { //Si on est pas sur un jour entier
                        $('.operationOrderTooltip').hide(); // Parfois la tooltip ne se cache pas correctement
                        let endTms = Math.round((eventDropInfo.event._instance.range.end.getTime()+(eventDropInfo.event._instance.range.start.getTimezoneOffset() * 60000)) / 1000);
                        let startTms = Math.round((eventDropInfo.event._instance.range.start.getTime()+(eventDropInfo.event._instance.range.start.getTimezoneOffset() * 60000)) / 1000);
                        let fk_action = eventDropInfo.event.extendedProps.operationOrderActionId;
                        let action = 'drop';
                        $.ajax({
                            url: '<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1); ?>?action=updateOperationOrderAction',
                            method: 'POST',
                            data: {
                                'url': window.location.href,
                                'startTime': startTms,
                                'endTime': endTms,
                                'fk_action': fk_action,
                                'action': action,
                                'allDay': eventDropInfo.event.allDay,
                            },
                            dataType: 'json',
                            // La fonction à apeller si la requête aboutie
                            success: function (data) {
                            }
                        });
                    }

                    calendar.refetchEvents();

                }
            });

			// refresh event on modal close
			$("#dialog-add-event").on("hide.bs.modal", function (e) {
				calendar.refetchEvents();
			});

			calendar.render();
			setBusinessHours();
			$(window).on('resize', function(){
				calendar.setOption('height', getFullCalendarHeight());
			});

            //Définition de la boite de dialog "Créer un nouvel événement OR"
            var operationorderneweventmodal = $('#dialog-add-event');

			operationorderneweventmodal.dialog({
                autoOpen: false,
				autoResize:true,
                close: function( event, ui ) {
                    calendar.refetchEvents();
                }
            });

            $(document).on('click','.fc-button-group',function() {
                setBusinessHours();
            });

            function getFullCalendarHeight(){
                return  $( window ).height() - $("#id-right").offset().top - 30;
            }

            function setBusinessHours(){

                var beginOfWeek = Math.floor(calendar.view.activeStart.getTime() / 1000);
                var endOfWeek = Math.floor(calendar.view.activeEnd.getTime() / 1000);

                $.ajax({
                    url: '<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1); ?>?action=getBusinessHours',
                    method: 'POST',
                    data: {
                        'url' : window.location.href,
                        'beginOfWeek' : beginOfWeek,
                        'endOfWeek' : endOfWeek
                    },
                    dataType: 'json',
                    // La fonction à apeller si la requête aboutie
                    success: function (data) {

                        var result = [];

                        if(data != 0) {

                        <?php foreach ($Tfullcalendar_scheduler_businessHours_days as $key=>$day){?>

                            if(data['<?php print $day?>']) {

                                var dayCurrent = data['<?php print $day?>'];

                                $.each(dayCurrent, function (index, value) {

                                    result.push({
                                        daysOfWeek: [<?php print $key ?>],
                                        startTime: value['min'],
                                        endTime: value['max'],
                                    });

                                });
                            }
                            <?php } ?>

                        } else {

                            <?php if(!empty($conf->global->FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_START) && !empty($conf->global->FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_END)) {?>
                            result.push({
                                daysOfWeek: [1,2,3,4,5],
                                startTime: '<?php print $conf->global->FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_START ?>',
                                endTime: '<?php print $conf->global->FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_END ?>',
                            });

                            <?php } ?>
                        }

                        calendar.setOption('businessHours', result);
                        calendar.refetchEvents();
                    }
                });
            }

        });
    </script>
<?php
print '<div id="calendar"></div>';
print '<div id="dialog-add-event" title="'.$langs->trans('CreateNewORAction').'"></div>';

llxFooter();

