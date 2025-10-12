<?php
include '../Modulos/head.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
<style>
    #calendar {
        min-height: 650px;
    }

    .calendar-wrapper {
        background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%);
        border-radius: 18px;
        padding: 1.5rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
    }

    .fc {
        color: #0f172a;
    }

    .fc .fc-toolbar.fc-header-toolbar {
        margin-bottom: 1.5rem;
    }

    .fc .fc-toolbar-title {
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.02em;
    }

    .fc-theme-standard .fc-scrollgrid {
        border-radius: 14px;
        border: none;
        background-color: #ffffff;
        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.18);
    }

    .fc-theme-standard td,
    .fc-theme-standard th {
        border-color: rgba(148, 163, 184, 0.2);
    }

    .fc .fc-col-header-cell-cushion {
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .fc .fc-daygrid-day {
        background-color: #f8fafc;
        transition: background-color 0.2s ease-in-out;
    }

    .fc .fc-daygrid-day:hover {
        background-color: #e2e8f0;
    }

    .fc .fc-day-today {
        background-color: #dbeafe !important;
    }

    .fc .fc-daygrid-day-number {
        color: #0f172a;
        font-weight: 600;
    }

    .fc .fc-button-primary {
        background-color: #1d4ed8;
        border-color: #1d4ed8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .fc .fc-button-primary:not(:disabled):hover,
    .fc .fc-button-primary:not(:disabled).fc-button-active {
        background-color: #1e40af;
        border-color: #1e40af;
    }

    .fc .fc-daygrid-event {
        border-radius: 12px;
        padding: 0;
        border: none;
    }

    .fc-event.calendar-event {
        background: transparent;
        border: none;
    }

    .fc-event.calendar-event .fc-event-main {
        padding: 8px 10px;
        border-radius: 12px;
        border: 1px solid transparent;
        box-shadow: 0 12px 20px rgba(15, 23, 42, 0.12);
        white-space: normal;
        line-height: 1.25;

        background: var(--calendar-event-background, linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%));
        border-color: var(--calendar-event-border, #60a5fa);
        color: var(--calendar-event-text, #0f172a);
    }

    .fc-event.calendar-event.calendar-event-editable .fc-event-main {
        cursor: grab;
    }

    .fc-event.calendar-event.calendar-event-editable .fc-event-main:active {
        cursor: grabbing;

    }

    .fc-event.calendar-event .calendar-event-body {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .fc-event.calendar-event .calendar-event-time {
        color: blue;
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.35rem;
        font-weight: 700;
    }

    .fc-event.calendar-event .calendar-event-paciente {
        color: #0f172a;
        font-size: 0.86rem;
        font-weight: 600;
    }

    .fc-event.calendar-event .calendar-event-psicologo {
        color: gray;
        font-size: 0.78rem;
        opacity: 0.9;
    }


    .fc-event.calendar-event.event-status-cancelada .fc-event-main {
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5f5 100%);
        border-color: #94a3b8;
        color: #1f2937;
    }

    .fc-event.calendar-event.event-status-cancelada .calendar-event-paciente,
    .fc-event.calendar-event.event-status-cancelada .calendar-event-psicologo {
        text-decoration: line-through;
        opacity: 0.75;
    }

    .calendar-legend .legend-badge {
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 0.45rem 0.9rem;
        border: none;
    }

    .calendar-filter-row .form-label {
        font-weight: 600;
        color: #1e293b;
    }

    .calendar-availability {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        box-shadow: 0 18px 35px rgba(15, 23, 42, 0.08);
    }

    .calendar-availability .list-group-item {
        border: none;
        border-bottom: 1px solid rgba(148, 163, 184, 0.25);
        padding-left: 0;
        padding-right: 0;
    }

    .calendar-availability .list-group-item:last-child {
        border-bottom: none;
    }

    .legend-badge.status-creada,
    .status-pill.status-creada {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .legend-badge.status-reprogramado,
    .status-pill.status-reprogramado {
        background-color: #fef3c7;
        color: #b45309;
    }

    .legend-badge.status-finalizada,
    .status-pill.status-finalizada {
        background-color: #dcfce7;
        color: #047857;
    }

    .legend-badge.status-cancelada,
    .status-pill.status-cancelada {
        background-color: #e2e8f0;
        color: #1f2937;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        font-size: 0.85rem;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .calendar-wrapper {
            padding: 1rem;
        }

    }
</style>

<div class="page-inner">
    <div class="page-header">
        <h3 class="fw-bold mb-3">Calendario de citas</h3>

        
    </div>
  <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body calendar-wrapper">
                    <div class="row g-3 align-items-end mb-4 calendar-filter-row">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label" for="calendar-filter-psychologist">Filtrar por psicóloga</label>
                            <select id="calendar-filter-psychologist" class="form-select">
                                <option value="">Todas las psicólogas</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label" for="available-date">Fecha para consultar disponibilidad</label>
                            <input type="date" id="available-date" class="form-control">
                        </div>
                        <div class="col-md-4 col-sm-12 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary flex-grow-1 flex-sm-grow-0" id="show-available-slots">
                                Ver horas disponibles
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1 flex-sm-grow-0" id="clear-calendar-filters">
                                Limpiar filtros
                            </button>
                        </div>
                    </div>
                    <div id="available-slots-container" class="calendar-availability mb-4 d-none">
                        <h6 class="fw-semibold mb-2">Horas disponibles</h6>
                        <p class="text-muted mb-0" id="available-slots-message">
                            Selecciona una psicóloga y una fecha para consultar los horarios disponibles.
                        </p>
                        <ul class="list-group list-group-flush mt-3 d-none" id="available-slots-list"></ul>
                    </div>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="alert alert-info" id="calendar-instructions">
                Selecciona una cita dentro del calendario para ver más información.
            </div>
            <div class="alert alert-danger d-none" id="calendar-alert"></div>
        </div>
    </div>

    <div class="row d-none" id="detail-row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detalles de la cita</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Paciente</dt>
                        <dd class="col-sm-9" id="detail-paciente"></dd>

                        <dt class="col-sm-3">Psicóloga</dt>
                        <dd class="col-sm-9" id="detail-psicologo"></dd>

                        <dt class="col-sm-3">Inicio</dt>
                        <dd class="col-sm-9" id="detail-inicio"></dd>

                        <dt class="col-sm-3">Finaliza</dt>
                        <dd class="col-sm-9" id="detail-fin"></dd>

                        <dt class="col-sm-3">Estatus</dt>
                        <dd class="col-sm-9" id="detail-estatus"></dd>

                        <dt class="col-sm-3">Tipo</dt>
                        <dd class="col-sm-9" id="detail-tipo"></dd>

                        <dt class="col-sm-3">Forma de pago</dt>
                        <dd class="col-sm-9" id="detail-forma"></dd>

                        <dt class="col-sm-3">Costo</dt>
                        <dd class="col-sm-9" id="detail-costo"></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2 calendar-legend">
                <span class="badge legend-badge status-creada">Creada</span>
                <span class="badge legend-badge status-reprogramado">Reprogramado</span>
                <span class="badge legend-badge status-finalizada">Finalizada</span>
                <span class="badge legend-badge status-cancelada">Cancelada</span>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales-all.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarElement = document.getElementById('calendar');
        if (!calendarElement) {
            return;
        }

        const statusStyles = {
            'Creada': { eventClass: 'event-status-creada', badgeClass: 'status-creada' },
            'Reprogramado': { eventClass: 'event-status-reprogramado', badgeClass: 'status-reprogramado' },
            'Finalizada': { eventClass: 'event-status-finalizada', badgeClass: 'status-finalizada' },
            'Cancelada': { eventClass: 'event-status-cancelada', badgeClass: 'status-cancelada' }
        };

        const defaultStatusStyles = statusStyles['Creada'];
        const psychologistColorCache = {};
        let alertTimeoutId = null;
        let selectedEventId = null;

        const psychologistSelect = document.getElementById('calendar-filter-psychologist');
        const availableDateInput = document.getElementById('available-date');
        const showAvailableSlotsButton = document.getElementById('show-available-slots');
        const clearFiltersButton = document.getElementById('clear-calendar-filters');
        const availableSlotsContainer = document.getElementById('available-slots-container');
        const availableSlotsMessage = document.getElementById('available-slots-message');
        const availableSlotsList = document.getElementById('available-slots-list');

        function computePsychologistPalette(name) {
            const key = name && name.trim() !== '' ? name.trim() : 'Sin asignar';
            if (psychologistColorCache[key]) {
                return psychologistColorCache[key];
            }

            let hash = 0;
            for (let index = 0; index < key.length; index++) {
                hash = key.charCodeAt(index) + ((hash << 5) - hash);
                hash |= 0;
            }

            const absHash = Math.abs(hash);
            const hue = absHash % 360;
            const startLightness = 92 - (absHash % 12);
            const endLightness = 65 - (absHash % 8);
            const startColor = 'hsl(' + hue + ', 85%, ' + startLightness + '%)';
            const endColor = 'hsl(' + hue + ', 70%, ' + endLightness + '%)';

            const palette = {
                background: 'linear-gradient(135deg, ' + startColor + ' 0%, ' + endColor + ' 100%)',
                border: 'hsl(' + hue + ', 70%, 50%)',
                text: '#0f172a'
            };

            psychologistColorCache[key] = palette;
            return palette;
        }

        function applyPaletteToMainElement(mainElement, palette) {
            if (!mainElement || !palette) {
                return;
            }

            mainElement.style.setProperty('--calendar-event-background', palette.background);
            mainElement.style.setProperty('--calendar-event-border', palette.border);
            mainElement.style.setProperty('--calendar-event-text', palette.text);
        }

        const dateFormatter = new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });

        const timeFormatter = new Intl.DateTimeFormat('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        const availabilityDateLabelFormatter = new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'full',
            timeZone: 'America/Mexico_City'
        });

        const availabilityDatePartsFormatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'America/Mexico_City',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });

        function formatTimeRange(startDate, endDate) {
            if (!startDate) {
                return 'Sin horario';
            }

            const startTime = timeFormatter.format(startDate);

            if (!endDate) {
                return startTime + ' hrs';
            }

            return startTime + ' - ' + timeFormatter.format(endDate);
        }

        function padNumber(value, length) {
            return String(value).padStart(length, '0');
        }

        function getMexicoOffsetSuffix(year, monthIndex, day) {
            const referenceDate = new Date(Date.UTC(year, monthIndex, day, 12, 0, 0));
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'America/Mexico_City',
                hour: '2-digit',
                minute: '2-digit',
                timeZoneName: 'shortOffset',
                hour12: false
            });

            const parts = formatter.formatToParts(referenceDate);
            const offsetPart = parts.find(function (part) {
                return part.type === 'timeZoneName';
            });

            if (!offsetPart) {
                return '-06:00';
            }

            const match = offsetPart.value.match(/GMT([+-]?\d{1,2})(?::(\d{2}))?/);

            if (!match) {
                return '-06:00';
            }

            const rawHours = Number.parseInt(match[1], 10);
            if (Number.isNaN(rawHours)) {
                return '-06:00';
            }

            const minutes = match[2] ? Number.parseInt(match[2], 10) : 0;
            const sign = rawHours >= 0 ? '+' : '-';
            const hoursAbsolute = Math.abs(rawHours);

            return sign + padNumber(hoursAbsolute, 2) + ':' + padNumber(minutes, 2);
        }

        function buildDateTimeString(year, monthIndex, day, hour, minute, second, offset) {
            return (
                padNumber(year, 4) + '-' +
                padNumber(monthIndex + 1, 2) + '-' +
                padNumber(day, 2) + 'T' +
                padNumber(hour, 2) + ':' +
                padNumber(minute, 2) + ':' +
                padNumber(second, 2) +
                offset
            );
        }

        function buildDateRangeParams(year, monthIndex, day) {
            const startOffset = getMexicoOffsetSuffix(year, monthIndex, day);
            const start = buildDateTimeString(year, monthIndex, day, 0, 0, 0, startOffset);

            const nextDay = new Date(Date.UTC(year, monthIndex, day + 1, 12, 0, 0));
            const nextYear = nextDay.getUTCFullYear();
            const nextMonthIndex = nextDay.getUTCMonth();
            const nextDayNumber = nextDay.getUTCDate();
            const endOffset = getMexicoOffsetSuffix(nextYear, nextMonthIndex, nextDayNumber);
            const end = buildDateTimeString(nextYear, nextMonthIndex, nextDayNumber, 0, 0, 0, endOffset);

            return { start: start, end: end };
        }

        function showAvailabilityMessage(message, tone) {
            if (!availableSlotsMessage) {
                return;
            }

            if (availableSlotsContainer) {
                availableSlotsContainer.classList.remove('d-none');
            }

            availableSlotsMessage.textContent = message;
            availableSlotsMessage.classList.remove('text-success', 'text-danger', 'text-warning', 'text-muted');

            let toneClass = 'text-muted';
            if (tone === 'success') {
                toneClass = 'text-success';
            } else if (tone === 'danger') {
                toneClass = 'text-danger';
            } else if (tone === 'warning') {
                toneClass = 'text-warning';
            }

            availableSlotsMessage.classList.add(toneClass);

            if (availableSlotsList) {
                availableSlotsList.innerHTML = '';
                availableSlotsList.classList.add('d-none');
            }
        }

        function resetAvailabilityUI() {
            if (availableSlotsContainer) {
                availableSlotsContainer.classList.add('d-none');
            }

            if (availableSlotsList) {
                availableSlotsList.innerHTML = '';
                availableSlotsList.classList.add('d-none');
            }

            if (availableSlotsMessage) {
                availableSlotsMessage.textContent = 'Selecciona una psicóloga y una fecha para consultar los horarios disponibles.';
                availableSlotsMessage.classList.remove('text-success', 'text-danger', 'text-warning');
                availableSlotsMessage.classList.add('text-muted');
            }
        }

        function formatAvailabilitySlot(hour) {
            const startLabel = padNumber(hour, 2) + ':00';
            const endLabel = padNumber(hour + 1, 2) + ':00';
            return startLabel + ' - ' + endLabel + ' hrs';
        }

        function renderAvailableSlots(slots, dateLabel) {
            if (!availableSlotsContainer || !availableSlotsList || !availableSlotsMessage) {
                return;
            }

            availableSlotsContainer.classList.remove('d-none');
            availableSlotsList.innerHTML = '';
            availableSlotsList.classList.remove('d-none');

            slots.forEach(function (slot) {
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item d-flex justify-content-between align-items-center';

                const label = document.createElement('span');
                label.textContent = slot.label;
                listItem.appendChild(label);

                const badge = document.createElement('span');
                badge.className = 'badge bg-primary rounded-pill';
                badge.textContent = 'Disponible';
                listItem.appendChild(badge);

                availableSlotsList.appendChild(listItem);
            });

            availableSlotsMessage.textContent = 'Horarios disponibles para ' + dateLabel + ':';
            availableSlotsMessage.classList.remove('text-danger', 'text-warning', 'text-muted');
            availableSlotsMessage.classList.add('text-success');
        }

        function loadPsychologists() {
            if (!psychologistSelect) {
                return;
            }

            fetch('../Modulos/getPsicologos.php', { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo obtener la lista de psicólogas.');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!Array.isArray(payload)) {
                        return;
                    }

                    const fragment = document.createDocumentFragment();

                    payload.forEach(function (item) {
                        if (!item || !item.id) {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name || 'Psicóloga sin nombre';
                        fragment.appendChild(option);
                    });

                    psychologistSelect.appendChild(fragment);
                })
                .catch(function (error) {
                    console.error(error);
                });
        }

        function fetchAvailableSlotsForDay(psicologoId, dateValue) {
            if (!psicologoId || !dateValue) {
                return;
            }

            const parts = dateValue.split('-');

            if (parts.length !== 3) {
                showAvailabilityMessage('La fecha seleccionada no es válida.', 'danger');
                return;
            }

            const year = Number.parseInt(parts[0], 10);
            const month = Number.parseInt(parts[1], 10);
            const day = Number.parseInt(parts[2], 10);

            if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
                showAvailabilityMessage('La fecha seleccionada no es válida.', 'danger');
                return;
            }

            const monthIndex = month - 1;
            const targetDate = new Date(year, monthIndex, day);
            const dayOfWeek = targetDate.getDay();

            if (dayOfWeek === 0) {
                showAvailabilityMessage('El domingo no hay horarios disponibles.', 'warning');
                return;
            }

            const schedule = dayOfWeek === 6
                ? { start: 8, end: 13 }
                : { start: 13, end: 20 };

            const range = buildDateRangeParams(year, monthIndex, day);
            const params = new URLSearchParams({
                start: range.start,
                end: range.end,
                psicologo_id: psicologoId
            });

            showAvailabilityMessage('Consultando disponibilidad...', 'muted');

            fetch('../api/citas_calendario.php?' + params.toString(), { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo obtener la disponibilidad.');
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !Array.isArray(payload.data)) {
                        throw new Error('La respuesta del servidor no es válida.');
                    }

                    const psychologistIdNumber = Number.parseInt(psicologoId, 10);
                    const occupiedHours = new Set();

                    payload.data.forEach(function (item) {
                        if (!item || !item.programado || item.estatus === 'Cancelada') {
                            return;
                        }

                        if (Number.isInteger(psychologistIdNumber) && item.psicologo_id && item.psicologo_id !== psychologistIdNumber) {
                            return;
                        }

                        const startDate = new Date(item.programado);

                        if (!(startDate instanceof Date) || Number.isNaN(startDate.getTime())) {
                            return;
                        }

                        const dateParts = availabilityDatePartsFormatter.formatToParts(startDate);

                        const yearPart = dateParts.find(function (part) { return part.type === 'year'; });
                        const monthPart = dateParts.find(function (part) { return part.type === 'month'; });
                        const dayPart = dateParts.find(function (part) { return part.type === 'day'; });
                        const hourPart = dateParts.find(function (part) { return part.type === 'hour'; });

                        if (!yearPart || !monthPart || !dayPart || !hourPart) {
                            return;
                        }

                        const eventYear = Number.parseInt(yearPart.value, 10);
                        const eventMonth = Number.parseInt(monthPart.value, 10);
                        const eventDay = Number.parseInt(dayPart.value, 10);
                        const eventHour = Number.parseInt(hourPart.value, 10);

                        if (eventYear === year && eventMonth === month && eventDay === day) {
                            occupiedHours.add(eventHour);
                        }
                    });

                    const slots = [];
                    for (let hour = schedule.start; hour < schedule.end; hour++) {
                        if (!occupiedHours.has(hour)) {
                            slots.push({
                                hour: hour,
                                label: formatAvailabilitySlot(hour)
                            });
                        }
                    }

                    const dateLabel = availabilityDateLabelFormatter.format(new Date(Date.UTC(year, monthIndex, day, 12, 0, 0)));

                    if (slots.length === 0) {
                        showAvailabilityMessage('No hay horarios disponibles para ' + dateLabel + '.', 'warning');
                        return;
                    }

                    renderAvailableSlots(slots, dateLabel);
                })
                .catch(function (error) {
                    console.error(error);
                    showAvailabilityMessage('Ocurrió un error al consultar la disponibilidad. Intenta nuevamente.', 'danger');
                });
        }

        loadPsychologists();
        resetAvailabilityUI();

        const detailRow = document.getElementById('detail-row');
        const instructions = document.getElementById('calendar-instructions');
        const alertBox = document.getElementById('calendar-alert');

        function showAlert(message, type) {
            if (!alertBox) {
                return;
            }

            if (alertTimeoutId !== null) {
                clearTimeout(alertTimeoutId);
                alertTimeoutId = null;
            }

            const variants = ['alert-primary', 'alert-secondary', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info', 'alert-light', 'alert-dark'];
            variants.forEach(function (variant) {
                alertBox.classList.remove(variant);
            });

            const chosenClass = type ? 'alert-' + type : 'alert-info';
            alertBox.classList.add(chosenClass);
            alertBox.classList.remove('d-none');
            alertBox.textContent = message;
        }

        function hideAlert() {
            if (!alertBox) {
                return;
            }

            if (alertTimeoutId !== null) {
                clearTimeout(alertTimeoutId);
                alertTimeoutId = null;
            }

            alertBox.textContent = '';
            alertBox.classList.add('d-none');
        }

        function showTemporaryAlert(message, type, duration) {
            showAlert(message, type);

            if (!alertBox) {
                return;
            }

            const timeout = typeof duration === 'number' ? duration : 4000;
            alertTimeoutId = window.setTimeout(function () {
                hideAlert();
            }, timeout);
        }

        const detailFields = {
            paciente: document.getElementById('detail-paciente'),
            psicologo: document.getElementById('detail-psicologo'),
            inicio: document.getElementById('detail-inicio'),
            fin: document.getElementById('detail-fin'),
            estatus: document.getElementById('detail-estatus'),
            tipo: document.getElementById('detail-tipo'),
            forma: document.getElementById('detail-forma'),
            costo: document.getElementById('detail-costo')
        };

        function updateDetail(event) {
            if (!event) {
                return;
            }

            const props = event.extendedProps || {};

            if (detailFields.paciente) {
                detailFields.paciente.textContent = props.paciente || 'Sin registro';
            }

            if (detailFields.psicologo) {
                detailFields.psicologo.textContent = props.psicologo || 'Sin registro';
            }

            if (detailFields.estatus) {
                detailFields.estatus.innerHTML = '';

                if (props.estatus) {
                    const badge = document.createElement('span');
                    badge.classList.add('status-pill');

                    if (props.statusBadgeClass) {
                        badge.classList.add(props.statusBadgeClass);
                    }

                    badge.textContent = props.estatus;
                    detailFields.estatus.appendChild(badge);
                } else {
                    detailFields.estatus.textContent = 'Sin registro';
                }
            }

            if (detailFields.tipo) {
                detailFields.tipo.textContent = props.tipo || 'Sin registro';
            }

            if (detailFields.forma) {
                detailFields.forma.textContent = props.forma_pago || 'No especificado';
            }

            if (detailFields.costo) {
                const costoValido = props.costo !== null && props.costo !== undefined;
                detailFields.costo.textContent = costoValido
                    ? '$' + Number(props.costo).toFixed(2)
                    : 'No especificado';
            }

            if (detailFields.inicio) {
                detailFields.inicio.textContent = event.start ? dateFormatter.format(event.start) : 'Sin registro';
            }

            if (detailFields.fin) {
                detailFields.fin.textContent = event.end ? dateFormatter.format(event.end) : 'Sin registro';
            }
        }

        const calendar = new FullCalendar.Calendar(calendarElement, {
            initialView: 'dayGridMonth',
            locale: 'es',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Agenda'
            },
            navLinks: true,
            nowIndicator: true,
            editable: true,
            eventDurationEditable: false,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            events: function (fetchInfo, successCallback, failureCallback) {
                const params = new URLSearchParams({
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr
                });

                if (psychologistSelect && psychologistSelect.value) {
                    params.append('psicologo_id', psychologistSelect.value);
                }

                fetch('../api/citas_calendario.php?' + params.toString(), {
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Respuesta no válida del servidor');
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        if (!payload || !Array.isArray(payload.data)) {
                            throw new Error('Formato de datos inesperado');
                        }

                        const now = new Date();

                        const events = payload.data.map(function (item) {
                            const statusStyle = statusStyles[item.estatus] || defaultStatusStyles;
                            const paciente = item.paciente || 'Sin registro';
                            const psicologo = item.psicologo || 'Sin registro';
                            const title = 'Paciente: ' + paciente + ' | Psicóloga: ' + psicologo;

                            const classNames = ['calendar-event'];
                            if (statusStyle.eventClass) {
                                classNames.push(statusStyle.eventClass);
                            }

                            const palette = computePsychologistPalette(psicologo);
                            const startDate = item.programado ? new Date(item.programado) : null;
                            const endDate = item.termina ? new Date(item.termina) : null;
                            const hasValidStart = startDate instanceof Date && !Number.isNaN(startDate.getTime());
                            const hasValidEnd = endDate instanceof Date && !Number.isNaN(endDate.getTime());
                            const isStatusEditable = item.estatus !== 'Finalizada' && item.estatus !== 'Cancelada';
                            const isEditable = Boolean(hasValidStart && hasValidEnd && isStatusEditable && endDate.getTime() >= now.getTime());

                            if (isEditable) {
                                classNames.push('calendar-event-editable');
                            }

                            return {
                                id: item.id,
                                title: title,
                                start: item.programado,
                                end: item.termina,
                                classNames: classNames,
                                startEditable: isEditable,
                                durationEditable: false,
                                extendedProps: {
                                    paciente: paciente,
                                    psicologo: psicologo,
                                    estatus: item.estatus,
                                    statusBadgeClass: statusStyle.badgeClass,
                                    tipo: item.tipo,
                                    forma_pago: item.forma_pago,
                                    costo: item.costo,
                                    programado: item.programado,
                                    termina: item.termina,
                                    psicologoColor: palette,
                                    psicologoId: item.psicologo_id || null,
                                    isEditable: isEditable
                                }
                            };
                        });

                        hideAlert();
                        successCallback(events);
                    })
                    .catch(function (error) {
                        console.error(error);
                        showAlert('No se pudieron cargar las citas. Por favor intenta nuevamente.', 'danger');
                        if (typeof failureCallback === 'function') {
                            failureCallback(error);
                        }
                    });
            },
            eventAllow: function (dropInfo, draggedEvent) {
                const props = draggedEvent.extendedProps || {};
                if (!props.isEditable) {
                    return false;
                }

                const now = new Date();
                const minAllowed = new Date(now.getTime() - 60 * 1000);
                return dropInfo.start >= minAllowed;
            },
            eventContent: function (arg) {
                const content = document.createElement('div');
                content.classList.add('calendar-event-body');

                const time = document.createElement('span');
                time.classList.add('calendar-event-time');
                time.textContent = formatTimeRange(arg.event.start, arg.event.end);
                content.appendChild(time);

                const paciente = document.createElement('span');
                paciente.classList.add('calendar-event-paciente');
                paciente.textContent = arg.event.extendedProps.paciente || 'Sin registro';
                content.appendChild(paciente);

                if (arg.event.extendedProps.psicologo) {
                    const psicologo = document.createElement('span');
                    psicologo.classList.add('calendar-event-psicologo');
                    psicologo.textContent = 'Psicóloga: ' + arg.event.extendedProps.psicologo;
                    content.appendChild(psicologo);
                }

                return { domNodes: [content] };
            },
            eventDidMount: function (info) {
                if (!info.el) {
                    return;
                }

                const props = info.event.extendedProps || {};
                if (props.estatus === 'Cancelada') {
                    return;
                }

                const mainElement = info.el.querySelector('.fc-event-main');
                applyPaletteToMainElement(mainElement, props.psicologoColor);
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                selectedEventId = info.event.id;

                if (instructions) {
                    instructions.classList.add('d-none');
                }
                if (detailRow) {
                    detailRow.classList.remove('d-none');
                }

                updateDetail(info.event);
            },
            eventDrop: function (info) {
                const event = info.event;
                const newStart = event.start;

                if (!newStart) {
                    info.revert();
                    showAlert('La cita necesita una fecha y hora válidas.', 'danger');
                    return;
                }

                const requestBody = {
                    programado: newStart.toISOString()
                };

                fetch('../api/citas.php?id=' + encodeURIComponent(event.id), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(requestBody)
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('No se pudo guardar la reprogramación.');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data && data.error) {
                            throw new Error(data.error);
                        }

                        const endDate = event.end
                            ? event.end.getTime()
                            : event.start.getTime() + 60 * 60 * 1000;
                        const props = event.extendedProps || {};
                        const stillEditable = props.estatus !== 'Finalizada'
                            && props.estatus !== 'Cancelada'
                            && endDate >= Date.now();

                        if (event.start) {
                            event.setExtendedProp('programado', event.start.toISOString());
                        }
                        if (event.end) {
                            event.setExtendedProp('termina', event.end.toISOString());
                        }

                        event.setExtendedProp('isEditable', stillEditable);
                        event.setProp('startEditable', stillEditable);
                        event.setProp('durationEditable', false);

                        const currentClassNames = event.classNames ? event.classNames.slice() : [];
                        const editableIndex = currentClassNames.indexOf('calendar-event-editable');
                        if (stillEditable && editableIndex === -1) {
                            currentClassNames.push('calendar-event-editable');
                        } else if (!stillEditable && editableIndex !== -1) {
                            currentClassNames.splice(editableIndex, 1);
                        }
                        event.setProp('classNames', currentClassNames);

                        if (selectedEventId === event.id) {
                            updateDetail(event);
                        }

                        showTemporaryAlert('La cita se reprogramó correctamente.', 'success');
                    })
                    .catch(function (error) {
                        console.error(error);
                        info.revert();
                        showAlert(error.message || 'No se pudo reprogramar la cita.', 'danger');
                    });
            }
        });

        if (psychologistSelect) {
            psychologistSelect.addEventListener('change', function () {
                calendar.refetchEvents();

                if (!psychologistSelect.value) {
                    resetAvailabilityUI();
                    return;
                }

                if (availableDateInput && availableDateInput.value) {
                    fetchAvailableSlotsForDay(psychologistSelect.value, availableDateInput.value);
                }
            });
        }

        if (showAvailableSlotsButton) {
            showAvailableSlotsButton.addEventListener('click', function () {
                if (!psychologistSelect || psychologistSelect.value === '') {
                    showAvailabilityMessage('Selecciona una psicóloga para consultar los horarios disponibles.', 'warning');
                    return;
                }

                if (!availableDateInput || availableDateInput.value === '') {
                    showAvailabilityMessage('Selecciona una fecha para consultar los horarios disponibles.', 'warning');
                    return;
                }

                fetchAvailableSlotsForDay(psychologistSelect.value, availableDateInput.value);
            });
        }

        if (availableDateInput) {
            availableDateInput.addEventListener('change', function () {
                if (psychologistSelect && psychologistSelect.value && availableDateInput.value) {
                    fetchAvailableSlotsForDay(psychologistSelect.value, availableDateInput.value);
                } else if (!availableDateInput.value) {
                    resetAvailabilityUI();
                }
            });
        }

        if (clearFiltersButton) {
            clearFiltersButton.addEventListener('click', function () {
                if (psychologistSelect) {
                    psychologistSelect.value = '';
                }

                if (availableDateInput) {
                    availableDateInput.value = '';
                }

                resetAvailabilityUI();
                calendar.refetchEvents();
            });
        }

        calendar.render();
    });
</script>

<?php
include '../Modulos/footer.php';
?>
