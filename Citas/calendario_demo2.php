<?php
include '../Modulos/head.php';

$ROL_VENTAS = 1;
$ROL_ADMIN = 3;
$canEditDemo2 = ((int) $rol === $ROL_ADMIN) || ((int) $rol === $ROL_VENTAS);
?>

<link rel="stylesheet" href="https://uicdn.toast.com/calendar/latest/toastui-calendar.min.css">
<style>
    #calendar {
        height: 780px;
        min-height: 650px;
    }

    .calendar-wrapper {
        background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%);
        border-radius: 18px;
        padding: 1.5rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
    }

    .demo2-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .demo2-toolbar-title {
        font-weight: 800;
        letter-spacing: 0.02em;
        color: #0f172a;
        margin: 0;
        font-size: 1.1rem;
    }

    .calendar-legend .legend-badge {
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 0.45rem 0.9rem;
        border: none;
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

    @media (max-width: 768px) {
        #calendar {
            height: 720px;
        }

        .calendar-wrapper {
            padding: 1rem;
        }
    }

    /* Compact event rendering (truncate long names) */
    .demo2-event-line {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        line-height: 1.15;
    }

    .demo2-event-time {
        font-weight: 700;
    }

    .demo2-event-label {
        font-size: 12px;
        opacity: 0.92;
    }

    .demo2-event-bar {
        display: flex;
        align-items: baseline;
        gap: 8px;
        padding: 3px 8px;
        border-radius: 10px;
        line-height: 1.25;
        min-width: 0;
    }

    .demo2-event-bar.is-cancelled {
        text-decoration: line-through;
        opacity: 0.78;
        filter: saturate(0.75);
    }

    .demo2-event-bar-time {
        flex: 0 0 auto;
        font-weight: 900;
        font-size: 12px;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        opacity: 0.98;
    }

    .demo2-event-bar-text {
        flex: 1 1 auto;
        min-width: 0;
        font-weight: 850;
        font-size: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        opacity: 0.96;
    }

    /* Ensure month view events can show 2 lines */
    #calendar .toastui-calendar-monthgrid-schedule,
    #calendar .toastui-calendar-weekday-schedule {
        height: auto !important;
        min-height: 44px;
    }

    #calendar .toastui-calendar-monthgrid-schedule-content,
    #calendar .toastui-calendar-weekday-schedule-content {
        height: auto !important;
        overflow: visible !important;
    }

    #calendar .toastui-calendar-monthgrid-schedule-title,
    #calendar .toastui-calendar-weekday-schedule-title {
        height: auto !important;
        line-height: 1.25 !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }

    #calendar .toastui-calendar-monthgrid-schedule .toastui-calendar-ellipsis {
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }

    /* Click popup (custom detail) */
    .demo2-click-popup {
        position: fixed;
        z-index: 1065;
        width: 340px;
        max-width: calc(100vw - 24px);
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 14px;
        box-shadow: 0 22px 55px rgba(15, 23, 42, 0.18);
        padding: 12px 12px;
        color: #0f172a;
    }

    .demo2-click-popup .popup-title {
        font-weight: 800;
        font-size: 1rem;
        margin: 0 0 6px 0;
    }

    .demo2-click-popup .popup-dates {
        font-size: 0.9rem;
        opacity: 0.85;
        margin: 0 0 10px 0;
    }

    .demo2-click-popup .popup-lines {
        display: grid;
        gap: 6px;
        margin: 0 0 12px 0;
    }

    .demo2-click-popup .popup-line {
        display: flex;
        gap: 8px;
        align-items: baseline;
        font-size: 0.92rem;
    }

    .demo2-click-popup .popup-key {
        min-width: 92px;
        font-weight: 700;
        opacity: 0.85;
    }

    .demo2-click-popup .popup-val {
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .demo2-click-popup .popup-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        border-top: 1px solid rgba(148, 163, 184, 0.25);
        padding-top: 10px;
        margin-top: 10px;
    }

    .demo2-click-popup .popup-btn {
        border: 1px solid rgba(148, 163, 184, 0.45);
        background: #ffffff;
        border-radius: 10px;
        padding: 8px 10px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        color: #0f172a;
    }

    .demo2-click-popup .popup-btn.primary {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }
</style>

<div class="page-inner">
    <div class="page-header">
        <h3 class="fw-bold mb-3">Calendario Demo 2</h3>
            <p class="text-muted mb-0">Vista alternativa con Toast UI Calendar. <?php echo $canEditDemo2 ? 'Edicion habilitada para ventas y administradores.' : 'Solo lectura.'; ?></p>
    </div>
    <div class="row mt-3 d-none" id="psychologist-legend-row">
        <div class="col-12">

            
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body calendar-wrapper">
                    <div class="row g-3 align-items-end mb-4 calendar-filter-row">
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <label class="form-label" for="calendar-filter-psychologist">Filtrar por psicologa</label>
                            <select id="calendar-filter-psychologist" class="form-select">
                                <option value="">Todas las psicologas</option>
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
<div class="d-flex flex-wrap gap-2 calendar-psychologist-legend" id="calendar-psychologist-legend"></div>
                    <div id="available-slots-container" class="calendar-availability mb-4 d-none">
                        <h6 class="fw-semibold mb-2">Horas disponibles</h6>
                        <p class="text-muted mb-0" id="available-slots-message">
                            Selecciona una psicologa y una fecha para consultar los horarios disponibles.
                        </p>
                        <ul class="list-group list-group-flush mt-3 d-none" id="available-slots-list"></ul>
                    </div>

                    <div class="demo2-toolbar">
                        <div class="btn-group" role="group" aria-label="Navegacion">
                            <button class="btn btn-outline-primary" type="button" id="demo2-prev">&larr;</button>
                            <button class="btn btn-outline-primary" type="button" id="demo2-today">Hoy</button>
                            <button class="btn btn-outline-primary" type="button" id="demo2-next">&rarr;</button>
                        </div>

                        <h4 class="demo2-toolbar-title" id="demo2-title">&nbsp;</h4>

                        <div class="btn-group" role="group" aria-label="Vista">
                            <button class="btn btn-outline-secondary" type="button" data-demo2-view="month">Mes</button>
                            <button class="btn btn-outline-secondary" type="button" data-demo2-view="week">Semana</button>
                            <button class="btn btn-outline-secondary" type="button" data-demo2-view="day">Dia</button>
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
                Selecciona una cita dentro del calendario para ver mas informacion.
            </div>
            <div class="alert alert-danger d-none" id="calendar-alert"></div>
        </div>
    </div>

    <div class="modal fade" id="demo2-move-modal" tabindex="-1" aria-hidden="true" aria-labelledby="demo2-move-modal-label">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="demo2-move-modal-label">Reprogramar cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="move-modal-question">Quieres mover la cita?</p>
                    <dl class="row mb-0">
                        <dt class="col-4">Paciente</dt>
                        <dd class="col-8" id="move-modal-paciente">-</dd>

                        <dt class="col-4">Psicologa</dt>
                        <dd class="col-8" id="move-modal-psicologa">-</dd>

                        <dt class="col-4">De</dt>
                        <dd class="col-8" id="move-modal-from">-</dd>

                        <dt class="col-4">Fecha</dt>
                        <dd class="col-8" id="move-modal-to">-</dd>

                        <dt class="col-4">Hora</dt>
                        <dd class="col-8">
                            <input type="time" class="form-control" id="move-modal-time" step="300">
                            <div class="form-text">Ajusta la hora antes de confirmar.</div>
                        </dd>
                    </dl>
                    <div class="alert alert-warning mt-3 mb-0" role="alert" id="move-modal-warning">
                        Al moverla, la cita se marcara como Reprogramado.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="move-modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="move-modal-confirm">Mover cita</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="demo2-cancel-modal" tabindex="-1" aria-hidden="true" aria-labelledby="demo2-cancel-modal-label">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="demo2-cancel-modal-label">Cancelar cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="cancel-modal-question">Estas seguro que deseas cancelar esta cita?</p>
                    <dl class="row mb-0">
                        <dt class="col-4">Paciente</dt>
                        <dd class="col-8" id="cancel-modal-paciente">-</dd>

                        <dt class="col-4">Psicologa</dt>
                        <dd class="col-8" id="cancel-modal-psicologa">-</dd>

                        <dt class="col-4">Fecha</dt>
                        <dd class="col-8" id="cancel-modal-fecha">-</dd>
                    </dl>
                    <div class="alert alert-danger mt-3 mb-0" role="alert" id="cancel-modal-warning">
                        Esta accion marca la cita como Cancelada.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="cancel-modal-close">Cerrar</button>
                    <button type="button" class="btn btn-danger" id="cancel-modal-confirm">Cancelar cita</button>
                </div>
            </div>
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

                        <dt class="col-sm-3">Psicologa</dt>
                        <dd class="col-sm-9" id="detail-psicologo"></dd>

                        <dt class="col-sm-3">Inicio</dt>
                        <dd class="col-sm-9" id="detail-inicio"></dd>

                        <dt class="col-sm-3">Finaliza</dt>
                        <dd class="col-sm-9" id="detail-fin"></dd>

                        <dt class="col-sm-3">Tiempo</dt>
                        <dd class="col-sm-9" id="detail-tiempo"></dd>

                        <dt class="col-sm-3">Estatus</dt>
                        <dd class="col-sm-9" id="detail-estatus"></dd>

                        <dt class="col-sm-3">Tipo</dt>
                        <dd class="col-sm-9" id="detail-tipo"></dd>

                        <dt class="col-sm-3">Forma de pago</dt>
                        <dd class="col-sm-9" id="detail-forma"></dd>

                        <dt class="col-sm-3">Costo</dt>
                        <dd class="col-sm-9" id="detail-costo"></dd>

                        <dt class="col-sm-3">Solicitudes de reprogramacion</dt>
                        <dd class="col-sm-9" id="detail-reprogram-requests"></dd>

                        <dt class="col-sm-3">Solicitudes de cancelacion</dt>
                        <dd class="col-sm-9" id="detail-cancel-requests"></dd>
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

<script src="https://uicdn.toast.com/calendar/latest/toastui-calendar.min.js"></script>
<script>
    window.DEMO2_CAN_EDIT = <?php echo $canEditDemo2 ? 'true' : 'false'; ?>;
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarElement = document.getElementById('calendar');
        if (!calendarElement || !window.tui || !window.tui.Calendar) {
            return;
        }

        const CAN_EDIT = Boolean(window.DEMO2_CAN_EDIT);
        const ESTATUS_REPROGRAMADO_ID = 3;
        const ESTATUS_CANCELADA_ID = 1;

        const API_CITAS_BASE = '<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/api/citas.php';
        const API_REUNIONES_BASE = '<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/api/reuniones.php';

        const statusStyles = {
            'Creada': { badgeClass: 'status-creada' },
            'Reprogramado': { badgeClass: 'status-reprogramado' },
            'Finalizada': { badgeClass: 'status-finalizada' },
            'Cancelada': { badgeClass: 'status-cancelada' }
        };

        const psychologistColorCache = {};
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

        const moveModalEl = document.getElementById('demo2-move-modal');
        const moveModalPaciente = document.getElementById('move-modal-paciente');
        const moveModalPsicologa = document.getElementById('move-modal-psicologa');
        const moveModalFrom = document.getElementById('move-modal-from');
        const moveModalTo = document.getElementById('move-modal-to');
        const moveModalTime = document.getElementById('move-modal-time');
        const moveModalCancel = document.getElementById('move-modal-cancel');
        const moveModalConfirm = document.getElementById('move-modal-confirm');
        const moveModalQuestion = document.getElementById('move-modal-question');
        const moveModalWarning = document.getElementById('move-modal-warning');

        const cancelModalEl = document.getElementById('demo2-cancel-modal');
        const cancelModalPaciente = document.getElementById('cancel-modal-paciente');
        const cancelModalPsicologa = document.getElementById('cancel-modal-psicologa');
        const cancelModalFecha = document.getElementById('cancel-modal-fecha');
        const cancelModalClose = document.getElementById('cancel-modal-close');
        const cancelModalConfirm = document.getElementById('cancel-modal-confirm');
        const cancelModalQuestion = document.getElementById('cancel-modal-question');
        const cancelModalWarning = document.getElementById('cancel-modal-warning');

        const instructions = document.getElementById('calendar-instructions');
        const alertBox = document.getElementById('calendar-alert');
        const detailRow = document.getElementById('detail-row');

        const detailPaciente = document.getElementById('detail-paciente');
        const detailPsicologo = document.getElementById('detail-psicologo');
        const detailInicio = document.getElementById('detail-inicio');
        const detailFin = document.getElementById('detail-fin');
        const detailTiempo = document.getElementById('detail-tiempo');
        const detailEstatus = document.getElementById('detail-estatus');
        const detailTipo = document.getElementById('detail-tipo');
        const detailForma = document.getElementById('detail-forma');
        const detailCosto = document.getElementById('detail-costo');
        const detailReprogramRequests = document.getElementById('detail-reprogram-requests');
        const detailCancelRequests = document.getElementById('detail-cancel-requests');

        const titleEl = document.getElementById('demo2-title');
        const prevBtn = document.getElementById('demo2-prev');
        const nextBtn = document.getElementById('demo2-next');
        const todayBtn = document.getElementById('demo2-today');
        const viewButtons = document.querySelectorAll('[data-demo2-view]');

        const clickPopup = (function () {
            const existing = document.getElementById('demo2-click-popup');
            if (existing) {
                return existing;
            }
            const el = document.createElement('div');
            el.id = 'demo2-click-popup';
            el.className = 'demo2-click-popup d-none';
            el.setAttribute('aria-hidden', 'true');
            document.body.appendChild(el);
            return el;
        })();

        let lastCalendarPointer = null;

        function findMonthMorePopoverElement() {
            return (
                document.querySelector('.toastui-calendar-more-popover') ||
                document.querySelector('.toastui-calendar-more-popover-container') ||
                document.querySelector('[class*="toastui-calendar-more"]') ||
                document.querySelector('[class*="more-popover"]')
            );
        }

        function repositionMonthMorePopover() {
            if (!lastCalendarPointer) {
                return false;
            }
            const el = findMonthMorePopoverElement();
            if (!el) {
                return false;
            }
            const rect = el.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return false;
            }

            const margin = 12;
            let x = lastCalendarPointer.x + margin;
            let y = lastCalendarPointer.y + margin;

            const maxX = Math.max(margin, window.innerWidth - rect.width - margin);
            const maxY = Math.max(margin, window.innerHeight - rect.height - margin);
            x = Math.max(margin, Math.min(x, maxX));
            y = Math.max(margin, Math.min(y, maxY));

            el.style.position = 'fixed';
            el.style.left = x + 'px';
            el.style.top = y + 'px';
            el.style.transform = 'none';
            el.style.margin = '0';
            el.style.zIndex = '1070';
            return true;
        }

        function escapeHtml(value) {
            const str = value == null ? '' : String(value);
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function truncateText(value, maxChars) {
            const str = value == null ? '' : String(value);
            const max = Number.isFinite(maxChars) ? Math.max(0, Math.floor(maxChars)) : 0;
            if (max === 0 || str.length <= max) {
                return str;
            }
            if (max <= 3) {
                return str.slice(0, max);
            }
            return str.slice(0, max - 3).trimEnd() + '...';
        }

        function shortPersonName(fullName) {
            const str = fullName == null ? '' : String(fullName);
            const tokens = str
                .replace(/\s+/g, ' ')
                .trim()
                .split(' ')
                .filter(function (t) { return t !== ''; });

            if (tokens.length <= 1) {
                return tokens[0] || '';
            }

            if (tokens.length === 2) {
                return tokens[0] + ' ' + tokens[1];
            }

            // Common MX format: Nombre(s) + ApellidoP + ApellidoM
            // Use first given name + first surname.
            const firstName = tokens[0];
            const firstSurname = tokens[tokens.length - 2];
            return (firstName + ' ' + firstSurname).trim();
        }

        function hideClickPopup() {
            if (!clickPopup) {
                return;
            }
            clickPopup._demo2Raw = null;
            clickPopup.classList.add('d-none');
            clickPopup.setAttribute('aria-hidden', 'true');
            clickPopup.innerHTML = '';
        }

        function formatDateOnly(value) {
            if (!value) {
                return '';
            }
            const d = toDateSafe(value);
            if (!d) {
                return '';
            }
            const fmt = new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' });
            return fmt.format(d);
        }

        function buildPopupHtml(raw) {
            const isMeeting = raw && raw.eventKind === 'reunion';
            const paciente = isMeeting ? (raw && raw.tipo ? String(raw.tipo) : 'Reunión interna') : (raw && raw.paciente ? String(raw.paciente) : 'Sin registro');
            const psicologo = raw && raw.psicologo ? String(raw.psicologo) : 'Sin registro';
            const estatus = raw && raw.estatus ? String(raw.estatus) : 'Sin dato';
            const tipo = raw && raw.tipo ? String(raw.tipo) : 'Sin dato';
            const forma = raw && raw.forma_pago ? String(raw.forma_pago) : 'Sin registrar';
            const costo = raw && raw.costo != null && !Number.isNaN(Number(raw.costo)) ? ('$' + Number(raw.costo).toFixed(2)) : 'Sin dato';
            const tiempo = raw && raw.tiempo != null ? (String(raw.tiempo) + ' min') : 'Sin dato';

            const startDate = raw && raw.programado ? new Date(raw.programado) : null;
            const endDate = raw && raw.termina ? new Date(raw.termina) : null;
            const dateLine = (startDate && endDate)
                ? (formatDateOnly(startDate) + ' - ' + formatDateOnly(endDate))
                : (startDate ? formatDateOnly(startDate) : '');
            const timeLine = (startDate || endDate) ? formatTimeRange(startDate, endDate) : '';

            return (
                '<div class="popup-title" title="' + escapeHtml(paciente) + '">' + escapeHtml(paciente) + '</div>' +
                '<div class="popup-dates">' + escapeHtml(dateLine) + (timeLine ? (' · ' + escapeHtml(timeLine)) : '') + '</div>' +
                '<div class="popup-lines">' +
                '<div class="popup-line"><span class="popup-key">Psicologa</span><span class="popup-val" title="' + escapeHtml(psicologo) + '">' + escapeHtml(psicologo) + '</span></div>' +
                '<div class="popup-line"><span class="popup-key">Estatus</span><span class="popup-val" title="' + escapeHtml(estatus) + '">' + escapeHtml(estatus) + '</span></div>' +
                (isMeeting ? '' : '<div class="popup-line"><span class="popup-key">Tipo</span><span class="popup-val" title="' + escapeHtml(tipo) + '">' + escapeHtml(tipo) + '</span></div>') +
                (isMeeting ? '' : '<div class="popup-line"><span class="popup-key">Pago</span><span class="popup-val" title="' + escapeHtml(forma) + '">' + escapeHtml(forma) + '</span></div>') +
                (isMeeting ? '' : '<div class="popup-line"><span class="popup-key">Costo</span><span class="popup-val" title="' + escapeHtml(costo) + '">' + escapeHtml(costo) + '</span></div>') +
                (isMeeting ? '' : '<div class="popup-line"><span class="popup-key">Tiempo</span><span class="popup-val" title="' + escapeHtml(tiempo) + '">' + escapeHtml(tiempo) + '</span></div>') +
                '</div>' +
                '<div class="popup-actions">' +
                (CAN_EDIT && estatus !== 'Cancelada' && estatus !== 'Finalizada' ? '<button type="button" class="popup-btn" data-demo2-action="cancel">Cancelar</button>' : '') +
                '<button type="button" class="popup-btn" data-demo2-action="close">Cerrar</button>' +
                '</div>'
            );
        }

        function handleJsonResponse(response, fallbackMessage) {
            return response
                .json()
                .catch(function () { return null; })
                .then(function (payload) {
                    if (!response.ok) {
                        const message = payload && (payload.error || payload.message)
                            ? String(payload.error || payload.message)
                            : (fallbackMessage || 'Ocurrio un error en la solicitud.');
                        throw new Error(message);
                    }
                    return payload;
                });
        }

        function confirmCancelWithModal(raw) {
            if (!cancelModalEl || !cancelModalConfirm || !cancelModalClose) {
                return Promise.resolve(window.confirm('Deseas cancelar esta cita?'));
            }
            if (!window.bootstrap || !window.bootstrap.Modal) {
                return Promise.resolve(window.confirm('Deseas cancelar esta cita?'));
            }

            const paciente = raw && raw.paciente ? String(raw.paciente) : 'Sin registro';
            const psicologo = raw && raw.psicologo ? String(raw.psicologo) : 'Sin registro';
            const inicio = raw && raw.programado ? new Date(raw.programado) : null;
            const isMeeting = raw && raw.eventKind === 'reunion';
            if (cancelModalPaciente) cancelModalPaciente.textContent = paciente;
            if (cancelModalPsicologa) cancelModalPsicologa.textContent = psicologo;
            if (cancelModalFecha) cancelModalFecha.textContent = inicio ? dateFormatter.format(inicio) : 'Sin dato';

            if (cancelModalPaciente && isMeeting) {
                cancelModalPaciente.textContent = raw && raw.tipo ? String(raw.tipo) : 'Reunión interna';
            }

            const labelEl = document.getElementById('demo2-cancel-modal-label');
            if (labelEl) {
                labelEl.textContent = isMeeting ? 'Cancelar reunión' : 'Cancelar cita';
            }
            if (cancelModalQuestion) {
                cancelModalQuestion.textContent = isMeeting
                    ? 'Estas seguro que deseas cancelar esta reunión?'
                    : 'Estas seguro que deseas cancelar esta cita?';
            }
            if (cancelModalWarning) {
                cancelModalWarning.textContent = isMeeting
                    ? 'Esta accion elimina la reunión interna del calendario.'
                    : 'Esta accion marca la cita como Cancelada.';
            }

            const modal = window.bootstrap.Modal.getOrCreateInstance(cancelModalEl, { backdrop: 'static', keyboard: true });

            return new Promise(function (resolve) {
                let done = false;

                function cleanup() {
                    cancelModalEl.removeEventListener('hidden.bs.modal', onHidden);
                    cancelModalConfirm.removeEventListener('click', onConfirm);
                    cancelModalClose.removeEventListener('click', onCancel);
                }

                function finish(value) {
                    if (done) {
                        return;
                    }
                    done = true;
                    cleanup();
                    resolve(value);
                }

                function onHidden() {
                    finish(false);
                }

                function onConfirm() {
                    if (document.activeElement && typeof document.activeElement.blur === 'function') {
                        document.activeElement.blur();
                    }
                    modal.hide();
                    finish(true);
                }

                function onCancel() {
                    if (document.activeElement && typeof document.activeElement.blur === 'function') {
                        document.activeElement.blur();
                    }
                    modal.hide();
                    finish(false);
                }

                cancelModalEl.addEventListener('hidden.bs.modal', onHidden);
                cancelModalConfirm.addEventListener('click', onConfirm);
                cancelModalClose.addEventListener('click', onCancel);

                modal.show();
            });
        }

        function showClickPopup(ev) {
            if (!clickPopup || !ev || !ev.event || !ev.event.raw) {
                return;
            }
            const native = ev.nativeEvent || null;
            if (!native || typeof native.clientX !== 'number' || typeof native.clientY !== 'number') {
                return;
            }

            clickPopup._demo2Raw = ev.event.raw;
            clickPopup.innerHTML = buildPopupHtml(ev.event.raw);
            clickPopup.classList.remove('d-none');
            clickPopup.setAttribute('aria-hidden', 'false');

            const margin = 12;
            let x = native.clientX + margin;
            let y = native.clientY + margin;

            clickPopup.style.left = x + 'px';
            clickPopup.style.top = y + 'px';

            window.requestAnimationFrame(function () {
                const rect = clickPopup.getBoundingClientRect();
                const maxX = Math.max(margin, window.innerWidth - rect.width - margin);
                const maxY = Math.max(margin, window.innerHeight - rect.height - margin);
                const clampedX = Math.max(margin, Math.min(x, maxX));
                const clampedY = Math.max(margin, Math.min(y, maxY));
                clickPopup.style.left = clampedX + 'px';
                clickPopup.style.top = clampedY + 'px';
            });
        }

        function showAlert(message, tone) {
            if (!alertBox) {
                return;
            }
            const type = tone || 'danger';
            alertBox.textContent = message;
            alertBox.classList.remove('d-none', 'alert-danger', 'alert-warning', 'alert-success', 'alert-info');
            alertBox.classList.add('alert-' + type);
        }

        function hideAlert() {
            if (!alertBox) {
                return;
            }
            alertBox.textContent = '';
            alertBox.classList.add('d-none');
        }

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

        function updatePsychologistLegend(events) {
            if (!psychologistLegendRow || !psychologistLegendContainer) {
                return;
            }
            const palettes = {};
            (events || []).forEach(function (event) {
                const raw = event && event.raw ? event.raw : null;
                if (!raw) {
                    return;
                }
                if (raw.eventKind === 'reunion') {
                    return;
                }
                const name = getPsychologistDisplayName(raw.psicologo);
                // Avoid showing combined names (ex: "A, B, C") in the legend.
                if (name.indexOf(',') !== -1) {
                    return;
                }
                const palette = raw.psicologoColor || computePsychologistPalette(name, raw.psicologoColorHex);
                if (!palettes[name]) {
                    palettes[name] = palette;
                }
            });

            const hasMeetings = (events || []).some(function (event) {
                return Boolean(event && event.raw && event.raw.eventKind === 'reunion');
            });

            if (hasMeetings && !palettes['Reunión interna']) {
                const meetingPalette = createPaletteFromHex('#EF4444') || {
                    base: '#EF4444',
                    background: 'linear-gradient(135deg, #FCA5A5 0%, #EF4444 100%)',
                    border: '#B91C1C',
                    text: '#ffffff'
                };
                palettes['Reunión interna'] = meetingPalette;
            }
            const names = Object.keys(palettes);
            if (names.length === 0) {
                psychologistLegendRow.classList.add('d-none');
                psychologistLegendContainer.innerHTML = '';
                return;
            }
            psychologistLegendContainer.innerHTML = '';
            const fragment = document.createDocumentFragment();
            names.sort(function (a, b) { return a.localeCompare(b, 'es'); }).forEach(function (name) {
                const palette = palettes[name];
                const badge = document.createElement('span');
                badge.className = 'psychologist-badge';
                badge.textContent = name;
                if (palette) {
                    badge.style.background = palette.background;
                    badge.style.color = palette.text;
                    badge.style.borderColor = palette.border;
                    const legendAccent = palette.base || palette.border;
                    badge.style.setProperty('--psychologist-badge-color', legendAccent);
                }
                fragment.appendChild(badge);
            });
            psychologistLegendContainer.appendChild(fragment);
            psychologistLegendRow.classList.remove('d-none');
        }

        function confirmMoveWithModal(raw, fromDate, toDate) {
            if (!moveModalEl || !moveModalConfirm || !moveModalCancel) {
                return Promise.resolve(window.confirm('Quieres mover la cita?') ? toDate : null);
            }
            if (!window.bootstrap || !window.bootstrap.Modal) {
                return Promise.resolve(window.confirm('Quieres mover la cita?') ? toDate : null);
            }

            if (!(toDate instanceof Date) || Number.isNaN(toDate.getTime())) {
                return Promise.resolve(null);
            }

            const isMeeting = raw && raw.eventKind === 'reunion';
            const paciente = isMeeting
                ? (raw && raw.tipo ? String(raw.tipo) : 'Reunión interna')
                : (raw && raw.paciente ? String(raw.paciente) : 'Sin registro');
            const psicologo = raw && raw.psicologo ? String(raw.psicologo) : 'Sin registro';
            if (moveModalPaciente) moveModalPaciente.textContent = paciente;
            if (moveModalPsicologa) moveModalPsicologa.textContent = psicologo;
            if (moveModalFrom) moveModalFrom.textContent = fromDate ? dateFormatter.format(fromDate) : 'Sin dato';
            if (moveModalTo) {
                const dateOnlyFormatter = new Intl.DateTimeFormat('es-MX', { dateStyle: 'full' });
                moveModalTo.textContent = dateOnlyFormatter.format(toDate);
            }

            if (moveModalTime) {
                const hh = String(toDate.getHours()).padStart(2, '0');
                const mm = String(toDate.getMinutes()).padStart(2, '0');
                moveModalTime.value = hh + ':' + mm;
            }

            const labelEl = document.getElementById('demo2-move-modal-label');
            if (labelEl) {
                labelEl.textContent = isMeeting ? 'Reprogramar reunión' : 'Reprogramar cita';
            }
            if (moveModalQuestion) {
                moveModalQuestion.textContent = isMeeting ? 'Quieres mover la reunión?' : 'Quieres mover la cita?';
            }
            if (moveModalWarning) {
                moveModalWarning.textContent = isMeeting
                    ? 'Al moverla, se guardará la nueva fecha y hora de la reunión.'
                    : 'Al moverla, la cita se marcara como Reprogramado.';
                moveModalWarning.classList.remove('alert-warning', 'alert-info');
                moveModalWarning.classList.add(isMeeting ? 'alert-info' : 'alert-warning');
            }

            const modal = window.bootstrap.Modal.getOrCreateInstance(moveModalEl, { backdrop: 'static', keyboard: true });

            return new Promise(function (resolve) {
                let done = false;

                function computeAdjustedDate() {
                    const adjusted = new Date(toDate.getTime());
                    if (!moveModalTime) {
                        return adjusted;
                    }
                    const rawTime = typeof moveModalTime.value === 'string' ? moveModalTime.value : '';
                    const match = rawTime.match(/^(\d{2}):(\d{2})$/);
                    if (!match) {
                        return adjusted;
                    }
                    const hours = Number.parseInt(match[1], 10);
                    const minutes = Number.parseInt(match[2], 10);
                    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                        return adjusted;
                    }
                    adjusted.setHours(hours, minutes, 0, 0);
                    return adjusted;
                }

                function cleanup() {
                    moveModalEl.removeEventListener('hidden.bs.modal', onHidden);
                    moveModalConfirm.removeEventListener('click', onConfirm);
                    moveModalCancel.removeEventListener('click', onCancel);
                }

                function finish(value) {
                    if (done) {
                        return;
                    }
                    done = true;
                    cleanup();
                    resolve(value);
                }

                function onHidden() {
                    finish(null);
                }

                function onConfirm() {
                    if (document.activeElement && typeof document.activeElement.blur === 'function') {
                        document.activeElement.blur();
                    }
                    modal.hide();
                    finish(computeAdjustedDate());
                }

                function onCancel() {
                    if (document.activeElement && typeof document.activeElement.blur === 'function') {
                        document.activeElement.blur();
                    }
                    modal.hide();
                    finish(null);
                }

                moveModalEl.addEventListener('hidden.bs.modal', onHidden);
                moveModalConfirm.addEventListener('click', onConfirm);
                moveModalCancel.addEventListener('click', onCancel);

                modal.show();
            });
        }

        const dateFormatter = new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
        const timeFormatter = new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
        const availabilityDateLabelFormatter = new Intl.DateTimeFormat('es-MX', { dateStyle: 'full', timeZone: 'America/Mexico_City' });
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

        function formatStartTimeOnly(startDate) {
            if (!startDate) {
                return '';
            }
            return timeFormatter.format(startDate);
        }

        function pad2(value) {
            return String(value).padStart(2, '0');
        }

        // API accepts 'Y-m-d H:i:s' (no timezone, no milliseconds)
        function toSqlDateTime(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                return null;
            }
            return (
                date.getFullYear() + '-' +
                pad2(date.getMonth() + 1) + '-' +
                pad2(date.getDate()) + ' ' +
                pad2(date.getHours()) + ':' +
                pad2(date.getMinutes()) + ':' +
                pad2(date.getSeconds())
            );
        }

        function buildEventBarHtml(event, timeLabel) {
            const pacienteFull = event && event.title ? String(event.title) : '';
            const pacienteShort = shortPersonName(pacienteFull);
            const tipo = event && event.raw && event.raw.tipo ? String(event.raw.tipo) : '';
            const isMeeting = Boolean(event && event.raw && event.raw.eventKind === 'reunion');
            const estatus = event && event.raw && event.raw.estatus ? String(event.raw.estatus) : '';
            const isCancelled = estatus === 'Cancelada';

            const text = isMeeting
                ? (tipo || 'Reunión interna')
                : (pacienteShort || tipo || 'Sin registro');

            const fullLabelTitle = isMeeting ? (tipo || 'Reunión interna') : pacienteFull;
            const fullLabel = [fullLabelTitle, (event && event.raw && event.raw.psicologo ? String(event.raw.psicologo) : '')]
                .filter(function (v) { return typeof v === 'string' && v.trim() !== ''; })
                .join(' / ');

            return (
                '<div class="demo2-event-bar' + (isCancelled ? ' is-cancelled' : '') + '" title="' + escapeHtml(fullLabel) + '">' +
                '<span class="demo2-event-bar-time">' + escapeHtml(timeLabel || '') + '</span>' +
                '<span class="demo2-event-bar-text">' + escapeHtml(text) + '</span>' +
                '</div>'
            );
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
            const offsetPart = parts.find(function (part) { return part.type === 'timeZoneName'; });
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
        }

        function resetAvailabilityUI() {
            if (availableSlotsContainer) {
                availableSlotsContainer.classList.add('d-none');
            }
            if (availableSlotsList) {
                availableSlotsList.classList.add('d-none');
                availableSlotsList.innerHTML = '';
            }
            if (availableSlotsMessage) {
                availableSlotsMessage.textContent = 'Selecciona una psicologa y una fecha para consultar los horarios disponibles.';
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

        function fetchAvailableSlotsForDay(psicologoId, dateValue) {
            if (!psicologoId || !dateValue) {
                return;
            }
            const parts = dateValue.split('-');
            if (parts.length !== 3) {
                showAvailabilityMessage('La fecha seleccionada no es valida.', 'danger');
                return;
            }
            const year = Number.parseInt(parts[0], 10);
            const month = Number.parseInt(parts[1], 10);
            const day = Number.parseInt(parts[2], 10);
            if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
                showAvailabilityMessage('La fecha seleccionada no es valida.', 'danger');
                return;
            }
            const monthIndex = month - 1;
            const targetDate = new Date(year, monthIndex, day);
            const dayOfWeek = targetDate.getDay();
            if (dayOfWeek === 0) {
                showAvailabilityMessage('El domingo no hay horarios disponibles.', 'warning');
                return;
            }
            const schedule = dayOfWeek === 6 ? { start: 8, end: 13 } : { start: 13, end: 20 };
            const range = buildDateRangeParams(year, monthIndex, day);
            const params = new URLSearchParams({
                start: range.start,
                end: range.end,
                psicologo_id: psicologoId
            });
            showAvailabilityMessage('Consultando disponibilidad...', 'muted');
            fetch('<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/api/citas_calendario.php?' + params.toString(), { credentials: 'same-origin' }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo obtener la disponibilidad.');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !Array.isArray(payload.data)) {
                        throw new Error('La respuesta del servidor no es valida.');
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
                        const endDate = item.termina ? new Date(item.termina) : null;
                        const endTime = (endDate instanceof Date && !Number.isNaN(endDate.getTime()))
                            ? endDate.getTime()
                            : (startDate.getTime() + 60 * 60 * 1000);
                        const startTime = startDate.getTime();
                        for (let ts = startTime; ts < endTime; ts += 60 * 60 * 1000) {
                            const slotDate = new Date(ts);
                            const dateParts = availabilityDatePartsFormatter.formatToParts(slotDate);
                            const yearPart = dateParts.find(function (part) { return part.type === 'year'; });
                            const monthPart = dateParts.find(function (part) { return part.type === 'month'; });
                            const dayPart = dateParts.find(function (part) { return part.type === 'day'; });
                            const hourPart = dateParts.find(function (part) { return part.type === 'hour'; });
                            if (!yearPart || !monthPart || !dayPart || !hourPart) {
                                continue;
                            }
                            const eventYear = Number.parseInt(yearPart.value, 10);
                            const eventMonth = Number.parseInt(monthPart.value, 10);
                            const eventDay = Number.parseInt(dayPart.value, 10);
                            const eventHour = Number.parseInt(hourPart.value, 10);
                            if (eventYear === year && eventMonth === month && eventDay === day) {
                                occupiedHours.add(eventHour);
                            }
                        }
                    });
                    const slots = [];
                    for (let hour = schedule.start; hour < schedule.end; hour++) {
                        if (!occupiedHours.has(hour)) {
                            slots.push({ hour: hour, label: formatAvailabilitySlot(hour) });
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
                    showAvailabilityMessage('Ocurrio un error al consultar la disponibilidad. Intenta nuevamente.', 'danger');
                });
        }

        function normalizeCount(value) {
            const parsed = Number.parseInt(value, 10);
            return Number.isNaN(parsed) ? 0 : parsed;
        }

        function renderCountBadge(count, badgeClass) {
            const normalized = normalizeCount(count);
            const cls = badgeClass || 'status-creada';
            const label = normalized > 0 ? ('Pendiente (' + normalized + ')') : 'Sin solicitudes';
            return '<span class="status-pill ' + cls + '">' + escapeHtml(label) + '</span>';
        }

        function updateDetailFromRaw(raw) {
            if (!raw) {
                return;
            }
            const isMeeting = raw.eventKind === 'reunion';
            const paciente = isMeeting ? 'No aplica (reunión interna)' : (raw.paciente || 'Sin registro');
            const psicologo = getPsychologistDisplayName(raw.psicologo);
            const inicio = raw.programado ? new Date(raw.programado) : null;
            const fin = raw.termina ? new Date(raw.termina) : null;
            const tiempo = raw.tiempo != null ? String(raw.tiempo) + ' min' : 'Sin dato';
            const estatus = raw.estatus || 'Sin dato';
            const tipo = raw.tipo || 'Sin dato';
            const formaPago = raw.forma_pago || 'Sin registrar';
            const costo = raw.costo != null && !Number.isNaN(Number(raw.costo)) ? ('$' + Number(raw.costo).toFixed(2)) : 'Sin dato';

            if (detailPaciente) detailPaciente.textContent = paciente;
            if (detailPsicologo) detailPsicologo.textContent = psicologo;
            if (detailInicio) detailInicio.textContent = inicio ? dateFormatter.format(inicio) : 'Sin dato';
            if (detailFin) detailFin.textContent = fin ? dateFormatter.format(fin) : 'Sin dato';
            if (detailTiempo) detailTiempo.textContent = tiempo;

            if (detailEstatus) {
                const style = statusStyles[estatus] || { badgeClass: 'status-creada' };
                detailEstatus.innerHTML = '<span class="status-pill ' + escapeHtml(style.badgeClass) + '">' + escapeHtml(estatus) + '</span>';
            }
            if (detailTipo) detailTipo.textContent = tipo;
            if (detailForma) detailForma.textContent = formaPago;
            if (detailCosto) detailCosto.textContent = costo;

            if (detailReprogramRequests) {
                const style = statusStyles['Reprogramado'] || { badgeClass: 'status-reprogramado' };
                detailReprogramRequests.innerHTML = isMeeting ? '<span class="text-muted">No aplica</span>' : renderCountBadge(raw.solicitudesReprogramacionPendientes, style.badgeClass);
            }
            if (detailCancelRequests) {
                const style = statusStyles['Cancelada'] || { badgeClass: 'status-cancelada' };
                detailCancelRequests.innerHTML = isMeeting ? '<span class="text-muted">No aplica</span>' : renderCountBadge(raw.solicitudesCancelacionPendientes, style.badgeClass);
            }
        }

        function getStartOfToday() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return today;
        }

        function toDateSafe(value) {
            if (!value) {
                return null;
            }
            if (typeof value.toDate === 'function') {
                return value.toDate();
            }
            if (value instanceof Date) {
                return value;
            }
            const d = new Date(value);
            return Number.isNaN(d.getTime()) ? null : d;
        }

        function parseCitaId(value) {
            if (typeof value === 'number' && Number.isFinite(value)) {
                return value;
            }
            if (typeof value !== 'string') {
                return null;
            }
            const trimmed = value.trim();
            if (trimmed === '') {
                return null;
            }
            const match = trimmed.match(/(\d+)/);
            if (!match) {
                return null;
            }
            const parsed = Number.parseInt(match[1], 10);
            return Number.isNaN(parsed) ? null : parsed;
        }

        function getVisibleRange() {
            const start = toDateSafe(calendar.getDateRangeStart());
            const end = toDateSafe(calendar.getDateRangeEnd());
            if (!start || !end) {
                const fallback = new Date();
                const fallbackEnd = new Date(fallback.getTime() + 7 * 24 * 60 * 60 * 1000);
                return { start: fallback, end: fallbackEnd };
            }
            return { start: start, end: end };
        }

        function renderTitle() {
            if (!titleEl) {
                return;
            }
            const viewName = calendar.getViewName();
            const activeDate = toDateSafe(calendar.getDate());
            const monthFormatter = new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric' });
            const rangeFormatter = new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' });
            if (viewName === 'month') {
                titleEl.textContent = activeDate ? monthFormatter.format(activeDate) : '';
                return;
            }
            const range = getVisibleRange();
            const startLabel = rangeFormatter.format(range.start);
            const endLabel = rangeFormatter.format(new Date(range.end.getTime() - 1));
            titleEl.textContent = startLabel + ' - ' + endLabel;
        }

        const calendar = new tui.Calendar('#calendar', {
            defaultView: 'month',
            usageStatistics: false,
            useFormPopup: false,
            useDetailPopup: false,
            isReadOnly: !CAN_EDIT,
            gridSelection: {
                enableClick: false,
                enableDblClick: false
            },
            month: {
                startDayOfWeek: 1,
                dayNames: ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab']
            },
            week: {
                startDayOfWeek: 1,
                dayNames: ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab']
            },
            template: {
                monthDayName: function (model) {
                    const labels = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
                    return labels[model.day] || model.label;
                },
                weekDayName: function (model) {
                    const labels = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
                    const dayName = labels[model.day] || model.dayName;
                    return '<span style="font-weight:800;">' + escapeHtml(dayName) + '</span> <span style="opacity:0.85;">' + escapeHtml(String(model.date)) + '</span>';
                },
                monthGridHeaderExceed: function (hiddenEvents) {
                    const count = Number.isFinite(hiddenEvents) ? hiddenEvents : 0;
                    return '<span>+' + escapeHtml(String(count)) + ' mas</span>';
                },
                weekGridFooterExceed: function (hiddenEvents) {
                    const count = Number.isFinite(hiddenEvents) ? hiddenEvents : 0;
                    return '+' + escapeHtml(String(count)) + ' mas';
                },
                monthMoreClose: function () {
                    return '<span style="font-weight:800;">Cerrar</span>';
                },
                monthMoreTitleDate: function (moreTitle) {
                    const ymd = moreTitle && moreTitle.ymd ? String(moreTitle.ymd) : '';
                    const parts = ymd.split('-');
                    if (parts.length === 3) {
                        const y = Number.parseInt(parts[0], 10);
                        const m = Number.parseInt(parts[1], 10);
                        const d = Number.parseInt(parts[2], 10);
                        if (!Number.isNaN(y) && !Number.isNaN(m) && !Number.isNaN(d)) {
                            const date = new Date(Date.UTC(y, m - 1, d, 12, 0, 0));
                            const fmt = new Intl.DateTimeFormat('es-MX', { weekday: 'short', day: '2-digit', month: 'short' });
                            return escapeHtml(fmt.format(date));
                        }
                    }
                    return escapeHtml(ymd);
                },
                monthGridTime: function () {
                    // We render everything in monthGridTitle.
                    return '';
                },
                monthGridTitle: function (event) {
                    const start = toDateSafe(event.start);
                    const timeShort = start ? formatStartTimeOnly(start) : '';
                    return buildEventBarHtml(event, timeShort);
                },
                time: function (event) {
                    const start = toDateSafe(event.start);
                    const end = toDateSafe(event.end);
                    const timeText = start ? (formatStartTimeOnly(start) + (end ? (' - ' + formatStartTimeOnly(end)) : '')) : '';
                    return buildEventBarHtml(event, timeText);
                }
            }
        });

        function refetchEvents() {
            const range = getVisibleRange();
            const params = new URLSearchParams({
                start: range.start.toISOString(),
                end: range.end.toISOString()
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
            fetch('<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/api/citas_calendario.php?' + params.toString(), { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudieron cargar las citas.');
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
                        const eventKind = item && typeof item.event_kind === 'string' ? item.event_kind : 'cita';

                        const apiId = item && Object.prototype.hasOwnProperty.call(item, 'entity_id')
                            ? parseCitaId(item.entity_id)
                            : parseCitaId(item && item.id);

                        if (!apiId) {
                            return null;
                        }

                        const isMeeting = eventKind === 'reunion';
                        const paciente = isMeeting ? '' : (item.paciente || 'Sin registro');
                        const psicologo = getPsychologistDisplayName(item.psicologo);
                         const estatus = item.estatus || '';
                         const tipo = item.tipo || '';
                         const formaPago = item.forma_pago || '';
                         const psicologoColorHex = normalizeHexColor(item.psicologo_color);
                         const palette = computePsychologistPalette(psicologo, psicologoColorHex);
                         const eventAccent = isMeeting
                             ? '#EF4444'
                             : (palette && palette.border ? palette.border : (palette && palette.base ? palette.base : '#2563eb'));
                         const eventText = getContrastingTextColor(eventAccent);
                         const startDate = item.programado ? new Date(item.programado) : null;
                         const hasValidStart = startDate instanceof Date && !Number.isNaN(startDate.getTime());
                         const startTimestamp = hasValidStart ? startDate.getTime() : null;
                         const isEditable = CAN_EDIT && estatus !== 'Cancelada' && typeof startTimestamp === 'number' && startTimestamp >= todayTimestamp;
                         return {
                             id: String(item.id != null ? item.id : apiId),
                             calendarId: String(item.psicologo_id || 0),
                             title: paciente,
                             category: 'time',
                             start: item.programado,
                             end: item.termina,
                             isReadOnly: !isEditable,
                             location: '',
                             attendees: psicologo && String(psicologo).trim() !== '' ? [String(psicologo)] : [],
                             state: estatus || '',
                             body: [
                                 tipo ? ('Tipo: ' + tipo) : null,
                                 formaPago ? ('Forma de pago: ' + formaPago) : null,
                                 (item.costo != null && !Number.isNaN(Number(item.costo))) ? ('Costo: $' + Number(item.costo).toFixed(2)) : null,
                                 (item.tiempo != null) ? ('Tiempo: ' + String(item.tiempo) + ' min') : null
                             ].filter(function (line) { return Boolean(line); }).join('\n'),
                             backgroundColor: eventAccent,
                             borderColor: eventAccent,
                             color: eventText,
                             raw: {
                                 apiId: apiId,
                                 eventKind: eventKind,
                                 paciente: paciente,
                                 psicologo: psicologo,
                                 estatus: item.estatus,
                                 tipo: item.tipo,
                                 forma_pago: item.forma_pago,
                                 costo: item.costo,
                                 tiempo: item.tiempo,
                                 programado: item.programado,
                                 termina: item.termina,
                                 psicologoColor: palette,
                                 psicologoColorHex: psicologoColorHex,
                                 psicologoId: item.psicologo_id || null,
                                 solicitudesReprogramacionPendientes: normalizeCount(item.solicitudesReprogramacionPendientes),
                                 solicitudesCancelacionPendientes: normalizeCount(item.solicitudesCancelacionPendientes)
                             }
                         };
                    });

                    const normalizedEvents = events.filter(function (ev) { return Boolean(ev); });
                    const filteredEvents = showPastEvents
                        ? normalizedEvents
                        : normalizedEvents.filter(function (event) {
                            const raw = event && event.raw ? event.raw : null;
                            if (!raw || !raw.programado) {
                                return false;
                            }
                            const start = new Date(raw.programado);
                            return start instanceof Date && !Number.isNaN(start.getTime()) && start.getTime() >= todayTimestamp;
                        });
                    calendar.clear();
                    calendar.createEvents(filteredEvents);
                    updatePsychologistLegend(filteredEvents);
                    hideAlert();
                    renderTitle();
                })
                .catch(function (error) {
                    console.error(error);
                    showAlert('No se pudieron cargar las citas. Por favor intenta nuevamente.', 'danger');
                });
        }

        function updatePastEventsToggleLabel() {
            if (!togglePastEventsButton) {
                return;
            }
            togglePastEventsButton.textContent = showPastEvents ? 'Ocultar citas pasadas' : 'Mostrar citas pasadas';
            togglePastEventsButton.setAttribute('aria-pressed', showPastEvents ? 'true' : 'false');
        }

        function loadPsychologists() {
            if (!psychologistSelect) {
                return;
            }
            fetch('../Modulos/getPsicologos.php', { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo obtener la lista de psicologas.');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!Array.isArray(payload)) {
                        return;
                    }
                    const fragment = document.createDocumentFragment();
                    const calendars = [];
                    payload.forEach(function (item) {
                        if (!item || !item.id) {
                            return;
                        }
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name || 'Psicologa sin nombre';
                        fragment.appendChild(option);
                        const palette = computePsychologistPalette(item.name || '', normalizeHexColor(item.color_hex));
                        calendars.push({
                            id: String(item.id),
                            name: item.name || 'Psicologa',
                            backgroundColor: palette.base,
                            borderColor: palette.border,
                            color: palette.text
                        });
                    });
                    psychologistSelect.appendChild(fragment);
                    if (calendars.length > 0) {
                        calendar.setCalendars(calendars);
                    }
                })
                .catch(function (error) {
                    console.error(error);
                });
        }

        calendar.on('clickEvent', function (ev) {
            if (instructions) {
                instructions.classList.add('d-none');
            }
            if (detailRow) {
                detailRow.classList.remove('d-none');
            }
            const raw = ev && ev.event ? ev.event.raw : null;
            updateDetailFromRaw(raw);
            showClickPopup(ev);
        });

        if (clickPopup) {
            clickPopup.addEventListener('pointerdown', function (e) {
                e.stopPropagation();
            });
            clickPopup.addEventListener('click', function (e) {
                const target = e.target && e.target.closest ? e.target.closest('[data-demo2-action]') : null;
                const action = target ? target.getAttribute('data-demo2-action') : '';
                if (action === 'close') {
                    hideClickPopup();
                    return;
                }
                if (action === 'cancel') {
                    const raw = clickPopup && clickPopup._demo2Raw ? clickPopup._demo2Raw : null;
                    const apiId = raw && raw.apiId ? raw.apiId : null;
                    if (!apiId) {
                        showAlert('Esta cita no se puede cancelar desde este calendario.', 'warning');
                        return;
                    }

                    confirmCancelWithModal(raw)
                        .then(function (confirmed) {
                            if (!confirmed) {
                                return;
                            }

                            hideClickPopup();

                            const isMeeting = raw && raw.eventKind === 'reunion';
                            const request = isMeeting
                                ? fetch(API_REUNIONES_BASE + '?id=' + encodeURIComponent(String(apiId)), {
                                    method: 'DELETE',
                                    credentials: 'same-origin'
                                })
                                : fetch(API_CITAS_BASE + '?id=' + encodeURIComponent(String(apiId)), {
                                    method: 'PUT',
                                    headers: { 'Content-Type': 'application/json' },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ estatus: ESTATUS_CANCELADA_ID })
                                });

                            const fallbackMessage = isMeeting
                                ? 'No se pudo cancelar la reunión.'
                                : 'No se pudo cancelar la cita.';

                            return request
                                .then(function (response) { return handleJsonResponse(response, fallbackMessage); })
                                .then(function () {
                                    refetchEvents();
                                })
                                .catch(function (error) {
                                    console.error(error);
                                    showAlert(error && error.message ? error.message : fallbackMessage, 'danger');
                                    refetchEvents();
                                });
                        });
                }
            });

            document.addEventListener('pointerdown', function (e) {
                if (clickPopup.classList.contains('d-none')) {
                    return;
                }
                if (e && e.target && clickPopup.contains(e.target)) {
                    return;
                }
                hideClickPopup();
            }, true);

            document.addEventListener('keydown', function (e) {
                if (e && e.key === 'Escape') {
                    hideClickPopup();
                }
            });
        }

        calendar.on('beforeUpdateEvent', function (ev) {
            if (!CAN_EDIT) {
                return;
            }
            if (!ev || !ev.event || !ev.changes) {
                return;
            }
            const id = ev.event.id;
            const changes = ev.changes;
            if (!changes.start) {
                return;
            }
            const newStart = toDateSafe(changes.start);
            if (!newStart) {
                showAlert('La cita necesita una fecha y hora validas.', 'danger');
                return;
            }
            const apiId = ev.event.raw && ev.event.raw.apiId ? ev.event.raw.apiId : parseCitaId(id);
            if (!apiId) {
                showAlert('Este evento no se puede actualizar desde este calendario.', 'warning');
                return;
            }

            const previousStart = toDateSafe(ev.event.start);

            confirmMoveWithModal(ev.event.raw || null, previousStart, newStart)
                .then(function (finalStart) {
                    if (!finalStart) {
                        refetchEvents();
                        return;
                    }

                    const programadoSql = toSqlDateTime(finalStart);
                    if (!programadoSql) {
                        showAlert('La fecha seleccionada no es valida.', 'danger');
                        return;
                    }

                    hideClickPopup();

                    const isMeeting = ev.event.raw && ev.event.raw.eventKind === 'reunion';

                    let request;
                    let fallbackMessage;
                    if (isMeeting) {
                        const previousEnd = toDateSafe(ev.event.end);
                        const durationMs = previousEnd && previousStart
                            ? Math.max(5 * 60 * 1000, previousEnd.getTime() - previousStart.getTime())
                            : (60 * 60 * 1000);
                        const finalEnd = new Date(finalStart.getTime() + durationMs);
                        const inicioSql = programadoSql;
                        const finSql = toSqlDateTime(finalEnd);
                        if (!finSql) {
                            showAlert('La fecha de fin no es valida.', 'danger');
                            return;
                        }

                        request = fetch(API_REUNIONES_BASE + '?id=' + encodeURIComponent(String(apiId)), {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ inicio: inicioSql, fin: finSql })
                        });
                        fallbackMessage = 'No se pudo reprogramar la reunión.';
                    } else {
                        request = fetch(API_CITAS_BASE + '?id=' + encodeURIComponent(String(apiId)), {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ programado: programadoSql, estatus: ESTATUS_REPROGRAMADO_ID })
                        });
                        fallbackMessage = 'No se pudo guardar la reprogramacion.';
                    }

                    request
                        .then(function (response) { return handleJsonResponse(response, fallbackMessage); })
                        .then(function (data) {
                            if (data && data.error) {
                                throw new Error(data.error);
                            }
                            refetchEvents();
                        })
                        .catch(function (error) {
                            console.error(error);
                            showAlert(error && error.message ? error.message : fallbackMessage, 'danger');
                            refetchEvents();
                        });
                });
        });

        // Toast UI month "+X mas" popover can be mispositioned when the page scrolls.
        // Track the last pointer position inside the calendar and reposition the popover near it.
        if (calendarElement) {
            const pointerHandler = function (e) {
                if (!e || typeof e.clientX !== 'number' || typeof e.clientY !== 'number') {
                    return;
                }
                lastCalendarPointer = { x: e.clientX, y: e.clientY };
            };
            calendarElement.addEventListener('pointerdown', pointerHandler, true);
            calendarElement.addEventListener('mousedown', pointerHandler, true);
            calendarElement.addEventListener('click', function (e) {
                pointerHandler(e);
                window.requestAnimationFrame(function () {
                    repositionMonthMorePopover();
                    window.setTimeout(repositionMonthMorePopover, 0);
                    window.setTimeout(repositionMonthMorePopover, 50);
                });
            }, true);

            if (window.MutationObserver) {
                const mo = new MutationObserver(function () {
                    repositionMonthMorePopover();
                });
                mo.observe(document.body, { childList: true, subtree: true });
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                hideClickPopup();
                calendar.prev();
                renderTitle();
                refetchEvents();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                hideClickPopup();
                calendar.next();
                renderTitle();
                refetchEvents();
            });
        }
        if (todayBtn) {
            todayBtn.addEventListener('click', function () {
                hideClickPopup();
                calendar.today();
                renderTitle();
                refetchEvents();
            });
        }

        if (viewButtons && viewButtons.length > 0) {
            viewButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    hideClickPopup();
                    const view = btn.getAttribute('data-demo2-view');
                    if (!view) {
                        return;
                    }
                    calendar.changeView(view);
                    renderTitle();
                    refetchEvents();
                });
            });
        }

        if (psychologistSelect) {
            psychologistSelect.addEventListener('change', function () {
                resetAvailabilityUI();
                refetchEvents();
                if (psychologistSelect.value && availableDateInput && availableDateInput.value) {
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
                refetchEvents();
            }, 300);
        }

        if (patientSearchInput) {
            patientSearchInput.addEventListener('input', triggerPatientSearchUpdate);
            patientSearchInput.addEventListener('change', triggerPatientSearchUpdate);
        }

        if (showAvailableSlotsButton) {
            showAvailableSlotsButton.addEventListener('click', function () {
                if (!psychologistSelect || !psychologistSelect.value) {
                    showAvailabilityMessage('Selecciona una psicologa antes de consultar disponibilidad.', 'warning');
                    return;
                }
                if (!availableDateInput || !availableDateInput.value) {
                    showAvailabilityMessage('Selecciona una fecha para consultar disponibilidad.', 'warning');
                    return;
                }
                fetchAvailableSlotsForDay(psychologistSelect.value, availableDateInput.value);
            });
        }

        if (availableDateInput) {
            availableDateInput.addEventListener('change', function () {
                if (psychologistSelect && psychologistSelect.value && availableDateInput.value) {
                    fetchAvailableSlotsForDay(psychologistSelect.value, availableDateInput.value);
                }
            });
        }

        if (clearFiltersButton) {
            clearFiltersButton.addEventListener('click', function () {
                if (psychologistSelect) psychologistSelect.value = '';
                if (availableDateInput) availableDateInput.value = '';
                if (patientSearchInput) patientSearchInput.value = '';
                if (patientSearchDebounceId !== null) {
                    window.clearTimeout(patientSearchDebounceId);
                    patientSearchDebounceId = null;
                }
                resetAvailabilityUI();
                refetchEvents();
            });
        }

        if (togglePastEventsButton) {
            updatePastEventsToggleLabel();
            togglePastEventsButton.addEventListener('click', function () {
                showPastEvents = !showPastEvents;
                updatePastEventsToggleLabel();
                refetchEvents();
            });
        }

        loadPsychologists();
        resetAvailabilityUI();
        renderTitle();
        refetchEvents();
    });
</script>

<?php
include '../Modulos/footer.php';
?>
