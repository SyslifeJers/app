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

    .fc-event.calendar-event.event-type-reunion .fc-event-main {
        background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        border-color: #8b5cf6;
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

    .calendar-psychologist-legend {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
    }

    .calendar-psychologist-legend .psychologist-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        padding: 0.45rem 0.9rem;
        font-size: 0.85rem;
        font-weight: 600;
        border: 1px solid transparent;
        background-color: #e2e8f0;
        color: #1e293b;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.1);
    }

    .calendar-psychologist-legend .psychologist-badge::before {
        content: '';
        width: 0.75rem;
        height: 0.75rem;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.15);
        background: var(--psychologist-badge-color, rgba(148, 163, 184, 0.6));
        flex-shrink: 0;
    }

    .calendar-psychologist-legend .psychologist-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 30px rgba(15, 23, 42, 0.18);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        font-size: 0.85rem;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        font-weight: 600;
    }

    #calendar-alert {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 1080;
        width: min(92vw, 460px);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.25);
        border-width: 1px;
        font-weight: 600;
        white-space: normal;
    }

    #calendar-alert.alert-success {
        background-color: #dcfce7;
        border-color: #22c55e;
        color: #14532d;
    }

    #calendar-alert.alert-danger {
        background-color: #fee2e2;
        border-color: #ef4444;
        color: #7f1d1d;
    }

    #calendar-alert.alert-warning {
        background-color: #fef3c7;
        border-color: #f59e0b;
        color: #78350f;
    }

    @media (max-width: 768px) {
        .calendar-wrapper {
            padding: 1rem;
        }

        #calendar-alert {
            top: 0.75rem;
            left: 0.75rem;
            right: 0.75rem;
            width: auto;
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
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <label class="form-label" for="calendar-filter-psychologist">Filtrar por psicóloga</label>
                            <select id="calendar-filter-psychologist" class="form-select">
                                <option value="">Todas las psicólogas</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <label class="form-label" for="calendar-filter-patient">Buscar paciente</label>
                            <input type="text" id="calendar-filter-patient" class="form-control" placeholder="Nombre del paciente">
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <label class="form-label" for="available-date">Fecha para consultar disponibilidad</label>
                            <input type="date" id="available-date" class="form-control">
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary flex-grow-1 flex-sm-grow-0" id="show-available-slots">
                                Ver horas disponibles
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1 flex-sm-grow-0" id="clear-calendar-filters">
                                Limpiar filtros
                            </button>
                            <button type="button" class="btn btn-outline-primary flex-grow-1 flex-sm-grow-0" id="toggle-past-events" aria-pressed="false">
                                Mostrar citas pasadas
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
                    <div class="calendar-availability mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h6 class="fw-semibold mb-0">Reuniones internas</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="open-meeting-modal">
                                Agregar reunión
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Participantes</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                    </tr>
                                </thead>
                                <tbody id="meetings-table-body">
                                    <tr>
                                        <td colspan="4" class="text-muted">Sin reuniones registradas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
            <div class="alert alert-danger d-none" id="calendar-alert" role="alert" aria-live="assertive"></div>
        </div>
    </div>

    <div class="row d-none" id="detail-row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detalles del evento</h5>
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

                        <dt class="col-sm-3">Solicitudes de reprogramación</dt>
                        <dd class="col-sm-9" id="detail-reprogram-requests"></dd>

                        <dt class="col-sm-3">Solicitudes de cancelación</dt>
                        <dd class="col-sm-9" id="detail-cancel-requests"></dd>
                    </dl>

                    <div class="mt-4 d-none" id="detail-actions-section">
                        <h6 class="fw-semibold mb-2">Acciones</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" id="detail-reprogram-button">Reprogramar</button>
                            <button type="button" class="btn btn-danger" id="detail-cancel-button">Cancelar</button>
                        </div>
                        <p class="text-muted small mb-0 mt-2 d-none" id="detail-actions-helper"></p>
                    </div>
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
    <div class="row mt-3 d-none" id="psychologist-legend-row">
        <div class="col-12">
            <h6 class="fw-semibold mb-2">Colores por psicóloga</h6>
            <div class="d-flex flex-wrap gap-2 calendar-psychologist-legend" id="calendar-psychologist-legend"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateModalLabel">Actualizar Fecha de Cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateForm" method="post" action="../update.php">
                    <input type="hidden" name="redirect_to" value="Citas/calendario.php">
                    <div class="mb-3">
                        <label for="citaId" class="form-label">ID de la Cita</label>
                        <input type="text" class="form-control" id="citaId" name="citaId" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="fechaProgramada" class="form-label">Nueva Fecha Programada</label>
                        <input type="datetime-local" class="form-control" id="fechaProgramada" name="fechaProgramada" required step="3600">
                    </div>
                    <div class="alert alert-info" id="solicitudAviso" style="display:none;">
                        La solicitud será enviada al coordinador para su aprobación.
                    </div>
                    <button type="submit" class="btn btn-primary" id="updateSubmitButton">Actualizar</button>
                </form>
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
        let showPastEvents = false;
        let patientSearchDebounceId = null;

        const psychologistSelect = document.getElementById('calendar-filter-psychologist');
        const patientSearchInput = document.getElementById('calendar-filter-patient');
        const availableDateInput = document.getElementById('available-date');
        const showAvailableSlotsButton = document.getElementById('show-available-slots');
        const clearFiltersButton = document.getElementById('clear-calendar-filters');
        const availableSlotsContainer = document.getElementById('available-slots-container');
        const availableSlotsMessage = document.getElementById('available-slots-message');
        const availableSlotsList = document.getElementById('available-slots-list');
        const togglePastEventsButton = document.getElementById('toggle-past-events');
        const psychologistLegendRow = document.getElementById('psychologist-legend-row');
        const psychologistLegendContainer = document.getElementById('calendar-psychologist-legend');
        const meetingsTableBody = document.getElementById('meetings-table-body');
        const openMeetingModalButton = document.getElementById('open-meeting-modal');
        const meetingModalElement = document.getElementById('meetingModal');
        const saveMeetingButton = document.getElementById('saveMeetingBtn');
        const meetingParticipantsSelect = document.getElementById('meetingParticipants');
        const meetingTitleInput = document.getElementById('meetingTitle');
        const meetingDescriptionInput = document.getElementById('meetingDescription');
        const meetingStartInput = document.getElementById('meetingStart');
        const meetingEndInput = document.getElementById('meetingEnd');
        const meetingForm = document.getElementById('meetingForm');
        let meetingModal = null;

        function getPsychologistDisplayName(value) {
            if (typeof value !== 'string') {
                return 'Sin registro';
            }

            const trimmed = value.trim();
            return trimmed !== '' ? trimmed : 'Sin registro';
        }

        function normalizeHexColor(value) {
            if (typeof value !== 'string') {
                return null;
            }

            const trimmed = value.trim();
            if (trimmed === '') {
                return null;
            }

            const prefixed = trimmed.startsWith('#') ? trimmed : ('#' + trimmed);
            const match = /^#([0-9a-fA-F]{6})$/.exec(prefixed);

            if (!match) {
                return null;
            }

            return '#' + match[1].toUpperCase();
        }

        function hexToRgb(hex) {
            const normalized = normalizeHexColor(hex);
            if (!normalized) {
                return null;
            }

            const value = normalized.slice(1);
            const r = Number.parseInt(value.slice(0, 2), 16);
            const g = Number.parseInt(value.slice(2, 4), 16);
            const b = Number.parseInt(value.slice(4, 6), 16);

            if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
                return null;
            }

            return { r: r, g: g, b: b };
        }

        function rgbToHex(r, g, b) {
            function clamp(value) {
                return Math.max(0, Math.min(255, Math.round(value)));
            }

            return '#' + [clamp(r), clamp(g), clamp(b)]
                .map(function (channel) {
                    return channel.toString(16).padStart(2, '0');
                })
                .join('')
                .toUpperCase();
        }

        function adjustHexColor(hex, amount) {
            const rgb = hexToRgb(hex);
            if (!rgb) {
                return null;
            }

            const clampedAmount = Math.max(-1, Math.min(1, amount));
            const lighten = clampedAmount >= 0;
            const factor = Math.abs(clampedAmount);

            function blend(channel) {
                if (lighten) {
                    return channel + (255 - channel) * factor;
                }

                return channel * (1 - factor);
            }

            return rgbToHex(blend(rgb.r), blend(rgb.g), blend(rgb.b));
        }

        function getContrastingTextColor(hex) {
            const rgb = hexToRgb(hex);
            if (!rgb) {
                return '#0f172a';
            }

            const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
            return luminance > 0.6 ? '#0f172a' : '#f8fafc';
        }

        function createPaletteFromHex(hex) {
            const normalized = normalizeHexColor(hex);
            if (!normalized) {
                return null;
            }

            const lighter = adjustHexColor(normalized, 0.35) || normalized;
            const darker = adjustHexColor(normalized, -0.2) || normalized;
            const border = adjustHexColor(normalized, -0.35) || darker;
            const textColor = getContrastingTextColor(normalized);

            return {
                base: normalized,
                background: 'linear-gradient(135deg, ' + lighter + ' 0%, ' + darker + ' 100%)',
                border: border,
                text: textColor
            };
        }

        function createPaletteFromNameKey(key) {
            let hash = 0;
            for (let index = 0; index < key.length; index++) {
                hash = key.charCodeAt(index) + ((hash << 5) - hash);
                hash |= 0;
            }

            const absHash = Math.abs(hash);
            const hue = absHash % 360;
            const saturation = 45 + (absHash % 12);
            const startLightness = 94 - (absHash % 6);
            const endLightness = Math.max(72, startLightness - 14);

            const startColor = 'hsl(' + hue + ', ' + saturation + '%, ' + startLightness + '%)';
            const endColor = 'hsl(' + hue + ', ' + (saturation + 8) + '%, ' + endLightness + '%)';
            const borderColorLightness = Math.max(58, endLightness - 6);
            const borderColor = 'hsl(' + hue + ', ' + Math.min(65, saturation + 15) + '%, ' + borderColorLightness + '%)';

            return {
                base: startColor,
                background: 'linear-gradient(135deg, ' + startColor + ' 0%, ' + endColor + ' 100%)',
                border: borderColor,
                text: '#0f172a'
            };
        }

        function computePsychologistPalette(name, baseColor) {
            const displayName = getPsychologistDisplayName(name);
            const normalizedBase = normalizeHexColor(baseColor);
            const cacheKey = normalizedBase ? displayName + '|' + normalizedBase : displayName;

            if (psychologistColorCache[cacheKey]) {
                return psychologistColorCache[cacheKey];
            }

            const paletteFromBase = normalizedBase ? createPaletteFromHex(normalizedBase) : null;
            const palette = paletteFromBase || createPaletteFromNameKey(displayName);

            psychologistColorCache[cacheKey] = palette;
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

        function getStartOfToday() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return today;
        }

        function isFormaPagoRegistrada(formaPago) {
            if (formaPago === null || formaPago === undefined) {
                return false;
            }

            const normalized = String(formaPago).trim().toLowerCase();
            if (normalized === '' || normalized === 'pendiente' || normalized === 'sin pago' || normalized === 'no pagado') {
                return false;
            }

            return true;
        }

        function isEventEditableByPolicy(estatus, startTimestamp, endTimestamp, formaPago, todayTimestamp) {
            if (typeof startTimestamp !== 'number' || Number.isNaN(startTimestamp)) {
                return false;
            }

            if (typeof endTimestamp !== 'number' || Number.isNaN(endTimestamp)) {
                return false;
            }

            if (estatus === 'Finalizada' || estatus === 'Cancelada') {
                return false;
            }

            if (isFormaPagoRegistrada(formaPago)) {
                return false;
            }

            const effectiveToday = typeof todayTimestamp === 'number' && !Number.isNaN(todayTimestamp)
                ? todayTimestamp
                : getStartOfToday().getTime();

            return startTimestamp >= effectiveToday;
        }

        function updatePastEventsToggleLabel() {
            if (!togglePastEventsButton) {
                return;
            }

            if (showPastEvents) {
                togglePastEventsButton.textContent = 'Ocultar citas pasadas';
                togglePastEventsButton.setAttribute('aria-pressed', 'true');
            } else {
                togglePastEventsButton.textContent = 'Mostrar citas pasadas';
                togglePastEventsButton.setAttribute('aria-pressed', 'false');
            }
        }

        function updatePsychologistLegend(events) {
            if (!psychologistLegendContainer || !psychologistLegendRow) {
                return;
            }

            psychologistLegendContainer.innerHTML = '';

            if (!Array.isArray(events) || events.length === 0) {
                psychologistLegendRow.classList.add('d-none');
                return;
            }

            const psychologistMap = new Map();

            events.forEach(function (event) {
                if (!event || !event.extendedProps) {
                    return;
                }

                if (event.extendedProps.estatus === 'Cancelada') {
                    return;
                }

                const displayName = getPsychologistDisplayName(event.extendedProps.psicologo);
                if (psychologistMap.has(displayName)) {
                    return;
                }

                const palette = event.extendedProps.psicologoColor ||
                    computePsychologistPalette(displayName, event.extendedProps.psicologoColorHex);
                psychologistMap.set(displayName, palette);
            });

            if (psychologistMap.size === 0) {
                psychologistLegendRow.classList.add('d-none');
                return;
            }

            const fragment = document.createDocumentFragment();

            Array.from(psychologistMap.entries())
                .sort(function (a, b) {
                    return a[0].localeCompare(b[0], 'es', { sensitivity: 'base' });
                })
                .forEach(function (entry) {
                    const name = entry[0];
                    const palette = entry[1];
                    const badge = document.createElement('span');
                    badge.className = 'psychologist-badge';
                    badge.textContent = name;
                    badge.style.background = palette.background;
                    badge.style.color = palette.text;
                    badge.style.borderColor = palette.border;
                    const legendAccent = palette.base || palette.border;
                    badge.style.setProperty('--psychologist-badge-color', legendAccent);
                    fragment.appendChild(badge);
                });

            psychologistLegendContainer.appendChild(fragment);
            psychologistLegendRow.classList.remove('d-none');
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
            if (!psychologistSelect && !meetingParticipantsSelect) {
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
                    const fragmentMeeting = document.createDocumentFragment();

                    payload.forEach(function (item) {
                        if (!item || !item.id) {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name || 'Psicóloga sin nombre';
                        fragment.appendChild(option);

                        const optionMeeting = document.createElement('option');
                        optionMeeting.value = item.id;
                        optionMeeting.textContent = item.name || 'Psicóloga sin nombre';
                        fragmentMeeting.appendChild(optionMeeting);
                    });

                    if (psychologistSelect) {
                        psychologistSelect.appendChild(fragment);
                    }

                    if (meetingParticipantsSelect) {
                        meetingParticipantsSelect.innerHTML = '';
                        meetingParticipantsSelect.appendChild(fragmentMeeting);
                    }
                })
                .catch(function (error) {
                    console.error(error);
                });
        }

        function formatDateTimeForTable(value) {
            if (!value) {
                return 'Sin registro';
            }
            const date = new Date(value);
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                return 'Sin registro';
            }
            return dateFormatter.format(date);
        }

        function renderMeetingsTable(meetings) {
            if (!meetingsTableBody) {
                return;
            }

            meetingsTableBody.innerHTML = '';

            if (!Array.isArray(meetings) || meetings.length === 0) {
                meetingsTableBody.innerHTML = '<tr><td colspan="4" class="text-muted">Sin reuniones registradas.</td></tr>';
                return;
            }

            meetings.forEach(function (meeting) {
                const tr = document.createElement('tr');

                const tdTitulo = document.createElement('td');
                tdTitulo.textContent = meeting.titulo || 'Sin título';

                const tdParticipantes = document.createElement('td');
                tdParticipantes.textContent = meeting.psicologos || 'Sin participantes';

                const tdInicio = document.createElement('td');
                tdInicio.textContent = formatDateTimeForTable(meeting.inicio);

                const tdFin = document.createElement('td');
                tdFin.textContent = formatDateTimeForTable(meeting.fin);

                tr.appendChild(tdTitulo);
                tr.appendChild(tdParticipantes);
                tr.appendChild(tdInicio);
                tr.appendChild(tdFin);

                meetingsTableBody.appendChild(tr);
            });
        }

        function loadMeetingsTable() {
            fetch('../api/reuniones.php', { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo obtener la tabla de reuniones.');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
                        throw new Error('Formato de reuniones inválido.');
                    }
                    renderMeetingsTable(payload.data);
                })
                .catch(function (error) {
                    console.error(error);
                    if (meetingsTableBody) {
                        meetingsTableBody.innerHTML = '<tr><td colspan="4" class="text-danger">No fue posible cargar reuniones.</td></tr>';
                    }
                });
        }

        function getSelectedMeetingParticipants() {
            if (!meetingParticipantsSelect) {
                return [];
            }

            return Array.from(meetingParticipantsSelect.selectedOptions).map(function (option) {
                return option.value;
            });
        }

        function openMeetingModal() {
            if (!meetingModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                showAlert('No se pudo abrir el formulario de reunión.', 'danger');
                return;
            }

            if (!meetingModal) {
                meetingModal = new bootstrap.Modal(meetingModalElement);
            }

            if (meetingForm) {
                meetingForm.reset();
            }

            meetingModal.show();
        }

        function saveMeeting() {
            const titulo = meetingTitleInput ? meetingTitleInput.value.trim() : '';
            const descripcion = meetingDescriptionInput ? meetingDescriptionInput.value.trim() : '';
            const inicio = meetingStartInput ? meetingStartInput.value : '';
            const fin = meetingEndInput ? meetingEndInput.value : '';
            const psicologos = getSelectedMeetingParticipants();

            if (!titulo || !inicio || !fin || psicologos.length === 0) {
                showAlert('Completa título, fecha inicio, fecha fin y al menos una psicóloga.', 'warning');
                return;
            }
            console.log(JSON.stringify({
                    titulo: titulo,
                    descripcion: descripcion,
                    inicio: inicio,
                    fin: fin,
                    psicologos: psicologos
                }));
            fetch('../api/reuniones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    titulo: titulo,
                    descripcion: descripcion,
                    inicio: inicio,
                    fin: fin,
                    psicologos: psicologos
                })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success !== true) {
                        throw new Error(result.data && result.data.message ? result.data.message : 'No se pudo guardar la reunión.');
                    }

                    if (meetingModal) {
                        meetingModal.hide();
                    }

                    showTemporaryAlert('Reunión guardada correctamente.', 'success');
                    loadMeetingsTable();
                    calendar.refetchEvents();
                })
                .catch(function (error) {
                    console.error(error);
                    showAlert(error.message || 'No fue posible guardar la reunión.', 'danger');
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
        const detailActionsSection = document.getElementById('detail-actions-section');
        const detailActionsHelper = document.getElementById('detail-actions-helper');
        const detailReprogramButton = document.getElementById('detail-reprogram-button');
        const detailCancelButton = document.getElementById('detail-cancel-button');
        const reprogramModalElement = document.getElementById('updateModal');
        let reprogramModalInstance = null;

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
            costo: document.getElementById('detail-costo'),
            reprogramRequests: document.getElementById('detail-reprogram-requests'),
            cancelRequests: document.getElementById('detail-cancel-requests')
        };

        function normalizeCount(value) {
            const parsed = Number.parseInt(value, 10);
            if (Number.isNaN(parsed) || parsed < 0) {
                return 0;
            }
            return parsed;
        }

        function setRequestBadge(container, count) {
            if (!container) {
                return;
            }

            container.innerHTML = '';
            const badge = document.createElement('span');
            badge.className = count > 0 ? 'badge bg-warning text-dark' : 'badge bg-secondary';
            badge.textContent = count > 0 ? 'Pendiente (' + count + ')' : 'Sin solicitudes';
            container.appendChild(badge);
        }

        function ensureReprogramModalInstance() {
            if (!reprogramModalElement) {
                return null;
            }

            if (!reprogramModalInstance) {
                if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return null;
                }
                reprogramModalInstance = new bootstrap.Modal(reprogramModalElement);
            }

            return reprogramModalInstance;
        }

        function openReprogramModalForCita(citaId) {
            const modal = ensureReprogramModalInstance();
            if (!modal) {
                showAlert('No se pudo abrir la ventana de reprogramación.', 'danger');
                return;
            }

            const citaIdInput = document.getElementById('citaId');
            const fechaProgramadaInput = document.getElementById('fechaProgramada');
            const modalTitle = document.getElementById('updateModalLabel');
            const submitButton = document.getElementById('updateSubmitButton');
            const aviso = document.getElementById('solicitudAviso');

            if (citaIdInput) {
                citaIdInput.value = citaId || '';
            }

            if (fechaProgramadaInput) {
                fechaProgramadaInput.value = '';
            }

            if (modalTitle) {
                modalTitle.textContent = 'Actualizar Fecha de Cita';
            }

            if (submitButton) {
                submitButton.textContent = 'Actualizar';
            }

            if (aviso) {
                aviso.style.display = 'none';
            }

            modal.show();
        }

        function sendCancelRequest(citaId) {
            if (!citaId) {
                return;
            }

            const confirmed = window.confirm('¿Deseas cancelar esta cita?');
            if (!confirmed) {
                return;
            }

            const params = new URLSearchParams();
            params.append('citaId', citaId);
            params.append('estatus', '1');

            fetch('../cancelar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: params.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('Respuesta no válida del servidor.');
                    });
                })
                .then(function (data) {
                    if (!data || data.success !== true) {
                        throw new Error(data && data.message ? data.message : 'No se pudo procesar la cancelación.');
                    }

                    window.location.reload();
                })
                .catch(function (error) {
                    console.error(error);
                    showAlert(error.message || 'No se pudo procesar la cancelación. Intenta nuevamente.', 'danger');
                });
        }

        if (detailReprogramButton) {
            detailReprogramButton.addEventListener('click', function () {
                const citaId = detailReprogramButton.dataset.citaId || '';
                if (!citaId) {
                    showAlert('Selecciona una cita para reprogramar.', 'warning');
                    return;
                }

                openReprogramModalForCita(citaId);
            });
        }

        if (detailCancelButton) {
            detailCancelButton.addEventListener('click', function () {
                const citaId = detailCancelButton.dataset.citaId || '';
                if (!citaId) {
                    showAlert('Selecciona una cita para cancelar.', 'warning');
                    return;
                }

                sendCancelRequest(citaId);
            });
        }

        function updateDetail(event) {
            if (!event) {
                return;
            }

            const props = event.extendedProps || {};
            const eventKind = props.eventKind || 'cita';
            const isMeeting = eventKind === 'reunion';

            const reprogramCount = normalizeCount(props.solicitudesReprogramacionPendientes);
            const cancelCount = normalizeCount(props.solicitudesCancelacionPendientes);

            if (detailFields.paciente) {
                detailFields.paciente.textContent = isMeeting ? 'No aplica (reunión interna)' : (props.paciente || 'Sin registro');
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
                detailFields.forma.textContent = isMeeting ? 'No aplica' : (props.forma_pago || 'No especificado');
            }

            if (detailFields.costo) {
                const costoValido = props.costo !== null && props.costo !== undefined;
                detailFields.costo.textContent = costoValido
                    ? '$' + Number(props.costo).toFixed(2)
                    : 'No especificado';
            }

            if (detailFields.reprogramRequests) {
                if (isMeeting) {
                    detailFields.reprogramRequests.innerHTML = '<span class="badge bg-secondary">No aplica</span>';
                } else {
                    setRequestBadge(detailFields.reprogramRequests, reprogramCount);
                }
            }

            if (detailFields.cancelRequests) {
                if (isMeeting) {
                    detailFields.cancelRequests.innerHTML = '<span class="badge bg-secondary">No aplica</span>';
                } else {
                    setRequestBadge(detailFields.cancelRequests, cancelCount);
                }
            }

            if (detailFields.inicio) {
                detailFields.inicio.textContent = event.start ? dateFormatter.format(event.start) : 'Sin registro';
            }

            if (detailFields.fin) {
                detailFields.fin.textContent = event.end ? dateFormatter.format(event.end) : 'Sin registro';
            }

            const helperMessages = [];
            const estatusValue = typeof props.estatus === 'string' ? props.estatus.trim().toLowerCase() : '';
            const isCancelled = estatusValue === 'cancelada';

            if (detailActionsSection) {
                detailActionsSection.classList.remove('d-none');
            }

            if (detailReprogramButton) {
                detailReprogramButton.dataset.citaId = props.entityId || '';
                detailReprogramButton.textContent = 'Reprogramar';
                const editable = !isMeeting && Boolean(props.isEditable) && !isCancelled;
                detailReprogramButton.disabled = !editable || !props.entityId;
                if (!editable) {
                    helperMessages.push(isMeeting
                        ? 'Las reuniones internas no se reprograman desde este botón.'
                        : 'La cita no se puede reprogramar desde el calendario.');
                }
            }

            if (detailCancelButton) {
                detailCancelButton.dataset.citaId = props.entityId || '';
                detailCancelButton.textContent = isMeeting ? 'No disponible' : 'Cancelar';
                detailCancelButton.disabled = isMeeting || !props.entityId || isCancelled;
            }

            if (isCancelled) {
                helperMessages.push('La cita ya está cancelada.');
            }

            if (detailActionsHelper) {
                if (helperMessages.length > 0) {
                    detailActionsHelper.textContent = helperMessages.join(' ');
                    detailActionsHelper.classList.remove('d-none');
                } else {
                    detailActionsHelper.textContent = '';
                    detailActionsHelper.classList.add('d-none');
                }
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

                if (patientSearchInput) {
                    const rawPatientValue = patientSearchInput.value;
                    if (typeof rawPatientValue === 'string') {
                        const trimmedValue = rawPatientValue.trim();

                        if (trimmedValue !== '') {
                            params.append('paciente', trimmedValue);
                        }
                    }
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

                        const todayStart = getStartOfToday();
                        const todayTimestamp = todayStart.getTime();

                        const events = payload.data.map(function (item) {
                            const eventKind = item.event_kind === 'reunion' ? 'reunion' : 'cita';
                            const statusStyle = statusStyles[item.estatus] || defaultStatusStyles;
                            const paciente = item.paciente || 'Sin registro';
                            const psicologo = getPsychologistDisplayName(item.psicologo);
                            const meetingTitle = item.tipo || 'Reunión interna';
                            const title = eventKind === 'reunion'
                                ? ('Reunión: ' + meetingTitle)
                                : ('Paciente: ' + paciente + ' | Psicóloga: ' + psicologo);

                            const classNames = ['calendar-event'];
                            if (statusStyle.eventClass) {
                                classNames.push(statusStyle.eventClass);
                            }
                            if (eventKind === 'reunion') {
                                classNames.push('event-type-reunion');
                            }

                            const psicologoColorHex = normalizeHexColor(item.psicologo_color);
                            const palette = computePsychologistPalette(psicologo, psicologoColorHex);
                            const startDate = item.programado ? new Date(item.programado) : null;
                            const endDate = item.termina ? new Date(item.termina) : null;
                            const hasValidStart = startDate instanceof Date && !Number.isNaN(startDate.getTime());
                            const hasValidEnd = endDate instanceof Date && !Number.isNaN(endDate.getTime());
                            const rawStartTimestamp = hasValidStart ? startDate.getTime() : NaN;
                            const rawEndTimestamp = hasValidEnd ? endDate.getTime() : NaN;
                            const isPaid = isFormaPagoRegistrada(item.forma_pago);
                            const isEditable = eventKind === 'cita' && Boolean(
                                hasValidStart &&
                                hasValidEnd &&
                                isEventEditableByPolicy(item.estatus, rawStartTimestamp, rawEndTimestamp, item.forma_pago, todayTimestamp)
                            );
                            const startTimestamp = Number.isNaN(rawStartTimestamp) ? null : rawStartTimestamp;
                            const endTimestamp = Number.isNaN(rawEndTimestamp) ? null : rawEndTimestamp;

                            if (isEditable) {
                                classNames.push('calendar-event-editable');
                            }

                            const pendingReprogram = Number.parseInt(item.solicitudesReprogramacionPendientes, 10);
                            const pendingCancel = Number.parseInt(item.solicitudesCancelacionPendientes, 10);
                            const normalizedPendingReprogram = Number.isNaN(pendingReprogram) ? 0 : pendingReprogram;
                            const normalizedPendingCancel = Number.isNaN(pendingCancel) ? 0 : pendingCancel;

                            return {
                                id: item.id,
                                title: title,
                                start: item.programado,
                                end: item.termina,
                                classNames: classNames,
                                startEditable: isEditable,
                                durationEditable: false,
                                extendedProps: {
                                    eventKind: eventKind,
                                    entityId: item.entity_id || null,
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
                                    psicologoColorHex: psicologoColorHex,
                                    psicologoId: item.psicologo_id || null,
                                    isEditable: isEditable,
                                    isPaid: isPaid,
                                    startTimestamp: startTimestamp,
                                    endTimestamp: endTimestamp,
                                    solicitudesReprogramacionPendientes: normalizedPendingReprogram,
                                    solicitudesCancelacionPendientes: normalizedPendingCancel
                                }
                            };
                        });

                        const filteredEvents = showPastEvents
                            ? events
                            : events.filter(function (event) {
                                if (!event || !event.extendedProps) {
                                    return false;
                                }

                                const startTimestamp = event.extendedProps.startTimestamp;
                                return typeof startTimestamp === 'number' && startTimestamp >= todayTimestamp;
                            });

                        updatePsychologistLegend(filteredEvents);
                        hideAlert();
                        successCallback(filteredEvents);
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
                if (!props.isEditable || props.isPaid) {
                    return false;
                }

                if (!(dropInfo.start instanceof Date) || Number.isNaN(dropInfo.start.getTime())) {
                    return false;
                }

                const todayTimestamp = getStartOfToday().getTime();
                return dropInfo.start.getTime() >= todayTimestamp;
            },
            eventContent: function (arg) {
                const isMeeting = (arg.event.extendedProps && arg.event.extendedProps.eventKind === 'reunion');
                const content = document.createElement('div');
                content.classList.add('calendar-event-body');

                const time = document.createElement('span');
                time.classList.add('calendar-event-time');
                time.textContent = formatTimeRange(arg.event.start, arg.event.end);
                content.appendChild(time);

                const paciente = document.createElement('span');
                paciente.classList.add('calendar-event-paciente');
                paciente.textContent = isMeeting
                    ? (arg.event.extendedProps.tipo || 'Reunión interna')
                    : (arg.event.extendedProps.paciente || 'Sin registro');
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
                const harness = info.el.closest('.fc-daygrid-event-harness, .fc-timegrid-event-harness');
                if (harness) {
                    harness.style.backgroundColor = '';
                    harness.style.marginTop = '0px';
                }

                if (props.estatus === 'Cancelada') {
                    return;
                }

                const mainElement = info.el.querySelector('.fc-event-main');
                applyPaletteToMainElement(mainElement, props.psicologoColor);

                if (props.psicologoColor && props.psicologoColor.base && harness) {
                    harness.style.backgroundColor = props.psicologoColor.base;
                }
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
                const props = event.extendedProps || {};
                if (props.eventKind !== 'cita' || !props.entityId) {
                    info.revert();
                    return;
                }

                const newStart = event.start;

                if (!newStart) {
                    info.revert();
                    showAlert('La cita necesita una fecha y hora válidas.', 'danger');
                    return;
                }

                const requestBody = {
                    programado: newStart.toISOString()
                };

                fetch('../api/citas.php?id=' + encodeURIComponent(props.entityId), {
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

                        const props = event.extendedProps || {};
                        const startTimestampValue = event.start instanceof Date && !Number.isNaN(event.start.getTime())
                            ? event.start.getTime()
                            : null;
                        let endTimestampValue = null;

                        if (event.end instanceof Date && !Number.isNaN(event.end.getTime())) {
                            endTimestampValue = event.end.getTime();
                        } else if (typeof startTimestampValue === 'number') {
                            endTimestampValue = startTimestampValue + 60 * 60 * 1000;
                        }

                        const todayTimestamp = getStartOfToday().getTime();
                        const stillEditable = isEventEditableByPolicy(
                            props.estatus,
                            startTimestampValue,
                            endTimestampValue,
                            props.forma_pago,
                            todayTimestamp
                        );

                        if (event.start) {
                            event.setExtendedProp('programado', event.start.toISOString());
                        }
                        if (event.end) {
                            event.setExtendedProp('termina', event.end.toISOString());
                        } else if (typeof endTimestampValue === 'number') {
                            const provisionalEnd = new Date(endTimestampValue);
                            event.setExtendedProp('termina', provisionalEnd.toISOString());
                        } else {
                            event.setExtendedProp('termina', null);
                        }

                        const normalizedStartTimestamp = typeof startTimestampValue === 'number' ? startTimestampValue : null;
                        const normalizedEndTimestamp = typeof endTimestampValue === 'number' ? endTimestampValue : null;

                        event.setExtendedProp('startTimestamp', normalizedStartTimestamp);
                        event.setExtendedProp('endTimestamp', normalizedEndTimestamp);
                        event.setExtendedProp('isEditable', stillEditable);
                        event.setProp('startEditable', stillEditable);
                        event.setProp('durationEditable', false);

                        if (props && typeof props === 'object') {
                            props.startTimestamp = normalizedStartTimestamp;
                            props.endTimestamp = normalizedEndTimestamp;
                            props.isEditable = stillEditable;
                            if (event.start) {
                                props.programado = event.start.toISOString();
                            }
                            if (event.end) {
                                props.termina = event.end.toISOString();
                            } else if (typeof endTimestampValue === 'number') {
                                props.termina = new Date(endTimestampValue).toISOString();
                            } else {
                                props.termina = null;
                            }
                        }

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

        function triggerPatientSearchUpdate() {
            if (patientSearchDebounceId !== null) {
                window.clearTimeout(patientSearchDebounceId);
            }

            patientSearchDebounceId = window.setTimeout(function () {
                patientSearchDebounceId = null;
                calendar.refetchEvents();
            }, 300);
        }

        if (patientSearchInput) {
            patientSearchInput.addEventListener('input', triggerPatientSearchUpdate);
            patientSearchInput.addEventListener('change', triggerPatientSearchUpdate);
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

                if (patientSearchInput) {
                    patientSearchInput.value = '';
                }

                if (patientSearchDebounceId !== null) {
                    window.clearTimeout(patientSearchDebounceId);
                    patientSearchDebounceId = null;
                }

                resetAvailabilityUI();
                calendar.refetchEvents();
            });
        }

        if (togglePastEventsButton) {
            updatePastEventsToggleLabel();

            togglePastEventsButton.addEventListener('click', function () {
                showPastEvents = !showPastEvents;
                updatePastEventsToggleLabel();
                calendar.refetchEvents();
            });
        }

        if (openMeetingModalButton) {
            openMeetingModalButton.addEventListener('click', openMeetingModal);
        }

        if (saveMeetingButton) {
            saveMeetingButton.addEventListener('click', saveMeeting);
        }

        loadMeetingsTable();
        calendar.render();
    });
</script>

<div class="modal fade" id="meetingModal" tabindex="-1" aria-labelledby="meetingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="meetingModalLabel">Agregar reunión interna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="meetingForm">
                    <div class="mb-3">
                        <label for="meetingTitle" class="form-label">Título</label>
                        <input type="text" class="form-control" id="meetingTitle" required>
                    </div>
                    <div class="mb-3">
                        <label for="meetingDescription" class="form-label">Descripción (opcional)</label>
                        <textarea class="form-control" id="meetingDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="meetingStart" class="form-label">Inicio</label>
                        <input type="datetime-local" class="form-control" id="meetingStart" required>
                    </div>
                    <div class="mb-3">
                        <label for="meetingEnd" class="form-label">Fin</label>
                        <input type="datetime-local" class="form-control" id="meetingEnd" required>
                    </div>
                    <div class="mb-0">
                        <label for="meetingParticipants" class="form-label">Psicólogas participantes</label>
                        <select id="meetingParticipants" class="form-select" multiple size="8" required></select>
                        <small class="text-muted">Usa Ctrl/Cmd + click para seleccionar varias.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveMeetingBtn">Guardar reunión</button>
            </div>
        </div>
    </div>
</div>

<?php
include '../Modulos/footer.php';
?>
