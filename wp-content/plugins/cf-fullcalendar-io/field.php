<?php echo $wrapper_before; ?>
<?php echo $field_before; ?>
	<input type="hidden" name="<?php echo $field_name; ?>" value="1" data-field="<?php echo $field_base_id; ?>">
	<div id="<?php echo $field_id; ?>" style="position: relative;"></div>	
	<?php echo $field_required; ?>
	<?php echo $field_caption; ?>
<?php echo $field_after; ?>
<?php echo $wrapper_after; ?>

<div id="event-dialog-<?php echo $field_id; ?>" title="Edit Event">
  <p class="validateTips">All form fields are required.</p>
 
  <form role="form">
    <fieldset>
      <label for="event-start">Start</label>
      <input name="event-start" class="event-start">
      <label for="event-end">End</label>
      <input name="event-end" class="event-end">
      
      <!-- Allow form submission with keyboard without duplicating the dialog button -->
      <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
    </fieldset>
  </form>
</div>

<script type="text/javascript">

jQuery(document).ready(function() {
	// JS Object to become JSON string output
	var calChanges = {};
	calChanges['events'] = {};
	// div id of the calendar's location
	var CAL_DIV = "#<?php echo $field_id; ?>";
	// div id of modal for this calendar.
	var MODAL_DIV = "#event-dialog-<?php echo $field_id; ?>";
	var tempId = 0;

	/*
	 * Updates the value corresponding to this calendar field, which will
	 * be the return value to the Caldera Form.
	 */
	var updateFieldValue = function() {
		console.debug(calChanges); // DEBUG
		jQuery("input[name=<?php echo $field_name;?>]").val(encodeURI(JSON.stringify(calChanges)));
	}

	/*
	 * Adds or reconciles newEvent with the existing changes made using the
	 * calendar and updates the value for this calendar field.
	 * @param	newEvent
	 */
	var updateCalChanges = function(newEvent) {
		// Event was selected, used when reserving timeslot(s).
		if (newEvent['mode'] == "select") {
			// Set mode for synth-cal processor.
			calChanges['mode'] = "select";
			calChanges = newEvent;
		}
		else {
			// Set mode for synth-cal processor.
			calChanges['mode'] = "edit";
			if (newEvent['mode'] == "update") {
				// Making changes to an event not yet saved on GCal.
				if ("tempId" in newEvent) {
					newEvent['mode'] = "insert";
					calChanges['events'][newEvent['tempId']] = newEvent;
				}
				else { // Making changes to an existing event.
					calChanges['events'][newEvent['id']] = newEvent;
				}
			}
			else if (newEvent['mode'] == "insert") { // Creating a new event.
				calChanges['events'][newEvent["tempId"]] = newEvent;
			}
			else if (newEvent['mode'] == "remove") { // Remove event.
				// Event not yet saved on GCal, just remove it from the
				// list of changes.
				if ("tempId" in newEvent) {
					delete calChanges['events'][newEvent["tempId"]];
				}
				else { // Removing an existing event.
					calChanges['events'][newEvent["id"]] = newEvent;
				}
			}
		}
		updateFieldValue();
	}

	// Return true if calendar events should be editable, per form
	// configuration.
	var isEditable = function() {
		var isEdit = "<?php echo $field['config']['editable']?>";
		var isEditStr = isEdit ? "true" : "false";
		console.log("Current value of isEdit is" + isEditStr + "!"); // DEBUG
		return isEdit ? true : false;
	}

	var fcMakeView = function() {
		var weekly = "<?php echo $field['config']['weekly']; ?>";
		var monthly = "<?php echo $field['config']['monthly']; ?>";
		var daily = "<?php echo $field['config']['daily']; ?>";

		var views = [];
		if (monthly) { views[0] = "month"; }
		if (weekly) { views[views.length] = "agendaWeek"; }
		if (daily) { views[views.length] = "agendaDay"; }

		return views.toString();
	}

	/* Setting up dialog for event editing. */
	// Form elements for event dialog box.
	var startElt = jQuery(MODAL_DIV + ' .event-start');
	var endElt = jQuery(MODAL_DIV + ' .event-end');
	//var endElt = $(MODAL_DIV + ' .event-start');

	// Called by modal to update selected event.
	var editEvent = function() {
		var calEvent = eventDialog.data('current-event');
		eventDialog.data('current-event', null);
		var startTime = startElt.datetimepicker('getDate');
		var endTime = endElt.datetimepicker('getDate');
		//  Update event with new times.
		calEvent.start.hour(startTime.getHours());
		calEvent.start.minutes(startTime.getMinutes());
		calEvent.end.hour(endTime.getHours());
		calEvent.end.minutes(endTime.getMinutes());
		// Update field data.
		updateCalChanges(calEvent);
		// Update calendar display.
		jQuery(CAL_DIV).fullCalendar('updateEvent', calEvent);
		// Close event editor.
		eventDialog.dialog("close");
	}

	var removeEvent = function() {
		var calEvent = eventDialog.data('current-event');
		eventDialog.data('current-event', null);
		calEvent['mode'] = "remove";
		// Update field data.
		updateCalChanges(calEvent);
		// Update calendar display.
		jQuery(CAL_DIV).fullCalendar('removeEvents', calEvent["id"]);
		// Close event editor.
		eventDialog.dialog("close");
	}

	// Create jquery-ui dialog for event property editing.
	var eventDialog = jQuery(MODAL_DIV).dialog({
		autoOpen: false,
		height: 300,
		width: 350,
		modal: true,
		buttons: {
			Save: editEvent,
			Cancel: function() {
				eventDialog.dialog("close");
			},
			Delete: removeEvent
		},
		open: function() {
			jQuery(':focus', this).blur();
		},
		close: function() {
			//formElt[0].reset();
			//allFields.removeClass("ui-state-error");
		}
	});

	formElt = eventDialog.find("form").on("submit", function(event) {
		event.preventDefault();
		editEvent();
	});


	startElt.timepicker({
		controlType: 'select',
		hourMin: 7,
		hourMax: 20
	});

	endElt.timepicker({
		controlType: 'select',
		hourMin: 7,
		hourMax: 20
	});

	/*
	 * Handle clicks on existing events.
	 * @param	calEvent	{Event Object}	the event clicked.
	 */
	var eventClickHandler = function(calEvent) {
		// Select event in non-editable context, e.g. student sign up
		if (!isEditable()) {
			var id = calEvent.id;
			id = id.replace("@google.com", "");
			var eventData = {
				mode: "select",
				id: id
			};
			updateCalChanges(eventData);
		} else {
			// Provide additional editing options.
			// Set start and end times.
			var startDate = calEvent.start.toDate()
			var endDate = calEvent.end.toDate()
			startElt.datetimepicker('setDate', startDate);
			endElt.datetimepicker('setDate', endDate);
			eventDialog.data('current-event', calEvent);
			eventDialog.dialog("open");
		}
		return false;
	}

	// Create FullCalendar element.
	jQuery(CAL_DIV).fullCalendar({
		//Event sources
		googleCalendarApiKey: "<?php echo $field['config']['api_key']; ?>",
		events: {
			googleCalendarId: "<?php echo Caldera_forms::do_magic_tags($field['config']['cal_id']); ?>",
			className: 'test_events',
		},
		//View generation
		header: {
			left: "prev,next today",
			center: "title",
			right: fcMakeView()
		},
		minTime: "07:00:00",
		maxTime: "20:00:00",
		timezone: "local",
		eventClick: eventClickHandler,
		//Adding new calendar events
		selectable: isEditable(),
		selectHelper: true,
		selectOverlap: false,
		select: function(start, end) {
			var eventData = {
				title: "new tutoring time",
				start: start,
				end: end,
				tempId: "temp" + tempId,
				id: "temp" + tempId
			};
			jQuery(CAL_DIV).fullCalendar('renderEvent', eventData, true);
			eventData["mode"] = "insert";
			tempId++;
			updateCalChanges(eventData);
		},
		//Dragging/dropping, for updating existing events
		eventStartEditable: isEditable(),
		eventDurationEditable: isEditable(),
		eventLimit: true,
		eventOverlap: false,
		eventDrop: function(event, delta, revertFunc) {
			var eventData = {
				mode: "update",
				id: event.id,
				start: event.start.format(),
				end: event.end.format()
			};
			if ("tempId" in event) {
				eventData['tempId'] = event.tempId;
			}
			updateCalChanges(eventData);
		},
		eventResize: function(event, delta, revertFunc) {
			var eventData = {
				mode: "update",
				id: event.id,
				start: event.start.format(),
				end: event.end.format()
			};
			if ("tempId" in event) {
				eventData['tempId'] = event.tempId;
			}
			updateCalChanges(eventData);
		}
	});
});
</script>
