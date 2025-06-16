import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import { DATA } from "./data";
import { Datepicker } from "vanillajs-datepicker";

export const calendarPage = () => {
	const CALENDAR_EL = document.getElementById("calendar");

	// Check if the calendar view is changed via any fullcalendar calls.
	// This is required in order to maintain the day of the datepicker when
	// the calendar view is changed via the datepicker.
	let isViewChanged = false;

	// Get the current calendar view date
	let currentViewDate = {
		month: "",
		year: "",
	};

	if (CALENDAR_EL) {
		//-----------------------------------
		// Main Calendar
		//-----------------------------------
		// Initiate main calendar
		const CALENDAR_VIEW = new Calendar(CALENDAR_EL, {
			plugins: [dayGridPlugin, timeGridPlugin],
			initialView: "dayGridMonth",
			dayMaxEventRows: true,
			views: {
				dayGrid: {
					dayMaxEventRows: 4,
				},
			},
			buttonIcons: {
				prev: " ph ph-arrow-left", // Space is required at the beginning
				next: " ph ph-arrow-right",
				today: " ph ph-calendar-check",
				dayGridMonth: " ph ph-squares-four",
				timeGridWeek: " ph ph-rows",
				timeGridDay: " ph ph-rectangle",
			},
			headerToolbar: {
				left: "title",
				center: "",
				right: "prev today next dayGridMonth timeGridWeek timeGridDay",
			},
			events: DATA,
			height: "100%",
			datesSet: (info) => {
				// Get the current start date of the view
				let date = info.view.currentStart;

				// Pass the current start date values to the common object,
				// so the datepicker can utilize it
				currentViewDate = {
					month: info.view.currentStart.getMonth() + 1,
					year: info.view.currentStart.getFullYear(),
				};

				// Set the datepicker date to match the calendar date
				isViewChanged && DATEPICKER.setDate(date);

				// Calendar view is changed via the fullcalendar call, so set this to true
				isViewChanged = true;
			},
		});

		// Render the calendar
		setTimeout(() => {
			CALENDAR_VIEW.render();
		});

		//-----------------------------------
		// Datepicker Calendar
		//-----------------------------------
		const CALENDAR_NAVIGATE_EL = document.getElementById("calendar-navigate");

		// Initiate the date picker
		const DATEPICKER = new Datepicker(CALENDAR_NAVIGATE_EL, {
			maxView: 0,
			prevArrow: "west",
			nextArrow: "east",
			buttonClass: "btn",
			nextArrow: "<i class='ph ph-arrow-right'></i>",
			prevArrow: "<i class='ph ph-arrow-left'></i>",
		});

		// Change the calendar view via datepicker,
		// when a the date is chnaged
		CALENDAR_NAVIGATE_EL.addEventListener("changeDate", (event) => {
			let date = DATEPICKER.getDate();
			let month = date.getMonth() + 1;
			let year = date.getFullYear();

			// Calendar view is not changed via the fullcalendar call, so set this to false
			isViewChanged = false;

			// Change the calendar view to the datepicker date
			if (!(currentViewDate.month === month && currentViewDate.year === year)) {
				CALENDAR_VIEW.gotoDate(date);
			}
		});
	}

	//-----------------------------------
	// List of events
	//-----------------------------------
	const EVENTS_EL = document.getElementById("calendar-events");
	let listEvents = "";

	if (EVENTS_EL) {
		DATA.slice(0, 10).map((item) => {
			let date = new Date(item.start);

			listEvents += `<a href="" class="d-flex align-items-center p-2 rounded bg-hover lh-1 mb-0.5">
					<div class="w-9 h-9 me-3 bg-highlight rounded d-grid place-content-center text-center flex-shrink-0">
						<div class="fw-medium fs-7 mb-0.5 text-body-emphasis">${date.getDate()}</div>	
						<div class="fs-8 text-body-secondary">${date.toLocaleString("default", { month: "short" })}</div>
					</div>
					<div class="flex-grow-1 text-truncate">
						<div class=" text-body text-truncate mb-2">${item.title}</div>
						<div class="text-body-secondary fs-8 opacity-50">${item.start}</div>
					</div>
				</a>`;
		});

		EVENTS_EL.innerHTML = listEvents;
	}
};
