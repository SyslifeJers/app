<?php
include '../Modulos/head.php';

$ROL_PRACTICANTE = 6;
$agendaSoloLectura = ((int) $rol === $ROL_PRACTICANTE);
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

    .calendar-click-popup {
        position: fixed;
        z-index: 1095;
        width: 380px;
        max-width: calc(100vw - 24px);
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 14px;
        box-shadow: 0 22px 55px rgba(15, 23, 42, 0.18);
        padding: 12px;
        color: #0f172a;
    }

    .calendar-click-popup .popup-title {
        font-weight: 800;
        font-size: 1rem;
        margin: 0 0 6px 0;
    }

    .calendar-click-popup .popup-dates {
        font-size: 0.9rem;
        opacity: 0.85;
        margin: 0 0 10px 0;
    }

    .calendar-click-popup .popup-lines {
        display: grid;
        gap: 6px;
        margin: 0 0 12px 0;
    }

    .calendar-click-popup .popup-line {
        display: flex;
        gap: 8px;
        align-items: baseline;
        font-size: 0.92rem;
    }

    .calendar-click-popup .popup-key {
        min-width: 92px;
        font-weight: 700;
        opacity: 0.85;
    }

    .calendar-click-popup .popup-val {
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .calendar-click-popup .popup-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .calendar-click-popup .popup-btn {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
        border-radius: 10px;
        padding: 7px 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .calendar-click-popup .popup-btn.primary {
        border-color: #2563eb;
        background: #2563eb;
        color: #ffffff;
    }

    .calendar-click-popup .popup-btn.danger {
        border-color: #dc2626;
        background: #dc2626;
        color: #ffffff;
    }

    .detail-contact-wrapper {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .detail-message-button {
        padding: 0.15rem 0.5rem;
        font-size: 0.78rem;
        line-height: 1.2;
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

    .fc-event.calendar-event.event-type-recurring-reservation .fc-event-main {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #d97706;
        color: #78350f;
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
    <div class="page-header d-flex justify-content-between align-items-start">
        <h3 class="fw-bold mb-3">Calendario de citas</h3>
                    <?php if (!$agendaSoloLectura) { ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-primary" id="open-recurring-reservation-modal">
                            Agregar reservación continua
                        </button>
                        <button type="button" class="btn btn-outline-primary " id="open-meeting-modal">
                            Agregar reunión
                        </button>
                    </div>
                    <?php } ?>
        
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

                        <dt class="col-sm-3">Número de contacto</dt>
                        <dd class="col-sm-9">
                            <span class="detail-contact-wrapper">
                                <span id="detail-contacto"></span>
                                <button type="button" class="btn btn-outline-success btn-sm detail-message-button" id="detail-message-button">Mensaje</button>
                            </span>
                        </dd>

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

                        <dt class="col-sm-3">Solicitudes de reprogramación</dt>
                        <dd class="col-sm-9" id="detail-reprogram-requests"></dd>

                        <dt class="col-sm-3">Solicitudes de cancelación</dt>
                        <dd class="col-sm-9" id="detail-cancel-requests"></dd>
                    </dl>

                    <?php if (!$agendaSoloLectura) { ?>
                    <div class="mt-4 d-none" id="detail-actions-section">
                        <h6 class="fw-semibold mb-2">Acciones</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" id="detail-reprogram-button">Reprogramar</button>
                            <button type="button" class="btn btn-danger" id="detail-cancel-button">Cancelar</button>
                        </div>
                        <p class="text-muted small mb-0 mt-2 d-none" id="detail-actions-helper"></p>
                    </div>
                    <?php } ?>
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

    <div class="row mt-4">
        <div class="col-12">
            <div class="calendar-availability">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h6 class="fw-semibold mb-0">Reuniones internas</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0" id="meetingsTable">
                        <thead class="table-light">
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
        </div>
    </div>
</div>

<?php if (!$agendaSoloLectura) { ?>
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

<div class="modal fade" id="moveCitaModal" tabindex="-1" aria-labelledby="moveCitaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveCitaModalLabel">Reprogramar cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-3">
                    <dt class="col-sm-4">Paciente</dt>
                    <dd class="col-sm-8" id="move-cita-paciente">Sin registro</dd>
                    <dt class="col-sm-4">Psicóloga</dt>
                    <dd class="col-sm-8" id="move-cita-psicologa">Sin registro</dd>
                    <dt class="col-sm-4">Fecha actual</dt>
                    <dd class="col-sm-8" id="move-cita-from">Sin dato</dd>
                    <dt class="col-sm-4">Nueva fecha</dt>
                    <dd class="col-sm-8" id="move-cita-to">Sin dato</dd>
                </dl>
                <div class="mb-0">
                    <label for="move-cita-time" class="form-label">Hora</label>
                    <input type="time" class="form-control" id="move-cita-time" step="1800">
                </div>
                <div class="alert alert-warning mt-3 mb-0" id="move-cita-warning">
                    Al moverla, la cita se guardará como reprogramada.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="move-cita-cancel">Cancelar</button>
                <button type="button" class="btn btn-primary" id="move-cita-confirm">Guardar cambio</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="recurringReservationModal" tabindex="-1" aria-labelledby="recurringReservationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recurringReservationModalLabel">Agregar reservación continua</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="recurringReservationForm">
                    <div class="mb-3">
                        <label for="recurringReservationPatient" class="form-label">Paciente</label>
                        <select id="recurringReservationPatient" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="recurringReservationPsychologist" class="form-label">Psicóloga</label>
                        <select id="recurringReservationPsychologist" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="recurringReservationType" class="form-label">Tipo</label>
                        <select id="recurringReservationType" class="form-select" required>
                            <option value="Cita">Cita</option>
                            <option value="Diagnostico">Diagnóstico</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="recurringReservationTime" class="form-label">Hora</label>
                            <input type="time" class="form-control" id="recurringReservationTime" required>
                        </div>
                        <div class="col-md-6">
                            <label for="recurringReservationDuration" class="form-label">Tiempo (minutos)</label>
                            <input type="number" class="form-control" id="recurringReservationDuration" min="1" step="1" value="60" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="recurringReservationStartDate" class="form-label">Fecha inicio</label>
                            <input type="date" class="form-control" id="recurringReservationStartDate" required>
                        </div>
                        <div class="col-md-6">
                            <label for="recurringReservationEndDate" class="form-label">Fecha fin (opcional)</label>
                            <input type="date" class="form-control" id="recurringReservationEndDate">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label d-block">Días de la semana</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="1" id="res-day-1"><label class="form-check-label" for="res-day-1">Lunes</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="2" id="res-day-2"><label class="form-check-label" for="res-day-2">Martes</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="3" id="res-day-3"><label class="form-check-label" for="res-day-3">Miércoles</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="4" id="res-day-4"><label class="form-check-label" for="res-day-4">Jueves</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="5" id="res-day-5"><label class="form-check-label" for="res-day-5">Viernes</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="6" id="res-day-6"><label class="form-check-label" for="res-day-6">Sábado</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="recurringReservationDays[]" value="7" id="res-day-7"><label class="form-check-label" for="res-day-7">Domingo</label></div>
                        </div>
                        <small class="text-muted">Selecciona de 1 a 3 días. No se captura costo porque no aplica.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveRecurringReservationBtn">Guardar reservación</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales-all.global.min.js"></script>
<script>
    const USER_CAN_EDIT = <?php echo $agendaSoloLectura ? 'false' : 'true'; ?>;

    
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
        const openRecurringReservationModalButton = document.getElementById('open-recurring-reservation-modal');
        const recurringReservationModalElement = document.getElementById('recurringReservationModal');
        const saveRecurringReservationButton = document.getElementById('saveRecurringReservationBtn');
        const recurringReservationForm = document.getElementById('recurringReservationForm');
        const recurringReservationPatientSelect = document.getElementById('recurringReservationPatient');
        const recurringReservationPsychologistSelect = document.getElementById('recurringReservationPsychologist');
        const recurringReservationTypeSelect = document.getElementById('recurringReservationType');
        const recurringReservationTimeInput = document.getElementById('recurringReservationTime');
        const recurringReservationDurationInput = document.getElementById('recurringReservationDuration');
        const recurringReservationStartInput = document.getElementById('recurringReservationStartDate');
        const recurringReservationEndInput = document.getElementById('recurringReservationEndDate');
        const meetingsTableElement = document.getElementById('meetingsTable');
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
        let recurringReservationModal = null;
        let meetingsDataTable = null;

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

        function toSqlDateTime(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                return null;
            }

            return (
                date.getFullYear() + '-' +
                padNumber(date.getMonth() + 1, 2) + '-' +
                padNumber(date.getDate(), 2) + ' ' +
                padNumber(date.getHours(), 2) + ':' +
                padNumber(date.getMinutes(), 2) + ':' +
                padNumber(date.getSeconds(), 2)
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
            if (!psychologistSelect && !meetingParticipantsSelect && !recurringReservationPsychologistSelect) {
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
                    const fragmentRecurring = document.createDocumentFragment();

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

                        const optionRecurring = document.createElement('option');
                        optionRecurring.value = item.id;
                        optionRecurring.textContent = item.name || 'Psicóloga sin nombre';
                        fragmentRecurring.appendChild(optionRecurring);
                    });

                    if (psychologistSelect) {
                        psychologistSelect.appendChild(fragment);
                    }

                    if (meetingParticipantsSelect) {
                        meetingParticipantsSelect.innerHTML = '';
                        meetingParticipantsSelect.appendChild(fragmentMeeting);
                    }

                    if (recurringReservationPsychologistSelect) {
                        recurringReservationPsychologistSelect.innerHTML = '<option value="">Selecciona una psicóloga</option>';
                        recurringReservationPsychologistSelect.appendChild(fragmentRecurring);
                    }
                })
                .catch(function (error) {
                    console.error(error);
                });
        }

        function loadPatients() {
            if (!recurringReservationPatientSelect) {
                return;
            }

            fetch('../get_names.php', { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo obtener la lista de pacientes.');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!Array.isArray(payload)) {
                        return;
                    }

                    recurringReservationPatientSelect.innerHTML = '<option value="">Selecciona un paciente</option>';
                    payload.forEach(function (item) {
                        if (!item || !item.id) {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name || 'Paciente sin nombre';
                        recurringReservationPatientSelect.appendChild(option);
                    });
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

        function ensureMeetingsDataTable() {
            if (meetingsDataTable || !meetingsTableElement) {
                return;
            }

            if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
                return;
            }

            if (meetingsTableBody) {
                meetingsTableBody.innerHTML = '';
            }

            meetingsDataTable = window.jQuery(meetingsTableElement).DataTable({
                data: [],
                columns: [
                    {
                        data: 'titulo',
                        render: function (data) {
                            const value = typeof data === 'string' ? data.trim() : '';
                            return value !== '' ? value : 'Sin título';
                        }
                    },
                    {
                        data: 'psicologos',
                        render: function (data) {
                            const value = typeof data === 'string' ? data.trim() : '';
                            return value !== '' ? value : 'Sin participantes';
                        }
                    },
                    {
                        data: 'inicio',
                        render: function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                return data || '';
                            }
                            return formatDateTimeForTable(data);
                        }
                    },
                    {
                        data: 'fin',
                        render: function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                return data || '';
                            }
                            return formatDateTimeForTable(data);
                        }
                    }
                ],
                order: [[2, 'asc']],
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                language: {
                    lengthMenu: 'Mostrar _MENU_ reuniones',
                    zeroRecords: 'No se encontraron reuniones',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ reuniones',
                    infoEmpty: 'Sin reuniones registradas',
                    infoFiltered: '(filtrado de _MAX_ reuniones)',
                    search: 'Buscar:',
                    emptyTable: 'Sin reuniones registradas.',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    }
                }
            });
        }

        function renderMeetingsTable(meetings) {
            ensureMeetingsDataTable();

            if (meetingsDataTable) {
                const data = Array.isArray(meetings) ? meetings : [];
                meetingsDataTable.clear();
                if (data.length > 0) {
                    meetingsDataTable.rows.add(data);
                }
                meetingsDataTable.draw();
                return;
            }

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
            ensureMeetingsDataTable();
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
                    if (typeof showTemporaryAlert === 'function') {
                        showTemporaryAlert('No fue posible cargar reuniones.', 'danger');
                    }

                    if (meetingsDataTable) {
                        meetingsDataTable.clear().draw();
                        return;
                    }
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

        function openRecurringReservationModal() {
            if (!recurringReservationModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                showAlert('No se pudo abrir el formulario de reservación continua.', 'danger');
                return;
            }

            if (!recurringReservationModal) {
                recurringReservationModal = new bootstrap.Modal(recurringReservationModalElement);
            }

            if (recurringReservationForm) {
                recurringReservationForm.reset();
            }

            recurringReservationModal.show();
        }

        function getRecurringReservationDays() {
            return Array.from(document.querySelectorAll('input[name="recurringReservationDays[]"]:checked')).map(function (input) {
                return Number.parseInt(input.value, 10);
            }).filter(function (value) {
                return Number.isInteger(value) && value >= 1 && value <= 7;
            });
        }

        function buildRecurringReservationConflictMessage(data) {
            let message = data && data.message ? data.message : 'La psicóloga seleccionada ya tiene una cita o reservación en ese horario.';
            if (data && data.conflict_data && data.conflict_data.paciente) {
                message += ' Paciente en conflicto: ' + data.conflict_data.paciente + '.';
            }
            message += ' ¿Deseas continuar y marcar la reservación como forzada?';
            return message;
        }

        function saveRecurringReservation(forceFlag) {
            const pacienteId = recurringReservationPatientSelect ? recurringReservationPatientSelect.value : '';
            const psicologoId = recurringReservationPsychologistSelect ? recurringReservationPsychologistSelect.value : '';
            const tipo = recurringReservationTypeSelect ? recurringReservationTypeSelect.value : '';
            const horaInicio = recurringReservationTimeInput ? recurringReservationTimeInput.value : '';
            const tiempo = recurringReservationDurationInput ? recurringReservationDurationInput.value : '';
            const fechaInicio = recurringReservationStartInput ? recurringReservationStartInput.value : '';
            const fechaFin = recurringReservationEndInput ? recurringReservationEndInput.value : '';
            const diasSemana = getRecurringReservationDays();

            if (!pacienteId || !psicologoId || !tipo || !horaInicio || !tiempo || !fechaInicio || diasSemana.length === 0) {
                showAlert('Completa paciente, psicóloga, tipo, hora, tiempo, fecha inicio y al menos un día.', 'warning');
                return;
            }

            fetch('../api/reservaciones_continuas.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    paciente_id: Number.parseInt(pacienteId, 10),
                    psicologo_id: Number.parseInt(psicologoId, 10),
                    tipo: tipo,
                    hora_inicio: horaInicio,
                    tiempo: Number.parseInt(tiempo, 10),
                    fecha_inicio: fechaInicio,
                    fecha_fin: fechaFin || null,
                    dias_semana: diasSemana,
                    forzar: forceFlag === true
                })
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    }).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data && result.data.success === true) {
                        if (recurringReservationModal) {
                            recurringReservationModal.hide();
                        }
                        showTemporaryAlert('Reservación continua guardada correctamente.', 'success');
                        calendar.refetchEvents();
                        return;
                    }

                    if (result.data && result.data.conflict && forceFlag !== true) {
                        if (window.confirm(buildRecurringReservationConflictMessage(result.data))) {
                            saveRecurringReservation(true);
                        }
                        return;
                    }

                    throw new Error(result.data && result.data.message ? result.data.message : 'No se pudo guardar la reservación continua.');
                })
                .catch(function (error) {
                    console.error(error);
                    showAlert(error.message || 'No se pudo guardar la reservación continua.', 'danger');
                });
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
        loadPatients();
        resetAvailabilityUI();

        const detailRow = document.getElementById('detail-row');
        const instructions = document.getElementById('calendar-instructions');
        const alertBox = document.getElementById('calendar-alert');
        const detailActionsSection = document.getElementById('detail-actions-section');
        const detailActionsHelper = document.getElementById('detail-actions-helper');
        const detailReprogramButton = document.getElementById('detail-reprogram-button');
        const detailCancelButton = document.getElementById('detail-cancel-button');
        const detailMessageButton = document.getElementById('detail-message-button');
        const reprogramModalElement = document.getElementById('updateModal');
        const moveCitaModalElement = document.getElementById('moveCitaModal');
        const moveCitaPaciente = document.getElementById('move-cita-paciente');
        const moveCitaPsicologa = document.getElementById('move-cita-psicologa');
        const moveCitaFrom = document.getElementById('move-cita-from');
        const moveCitaTo = document.getElementById('move-cita-to');
        const moveCitaTime = document.getElementById('move-cita-time');
        const moveCitaCancel = document.getElementById('move-cita-cancel');
        const moveCitaConfirm = document.getElementById('move-cita-confirm');
        let reprogramModalInstance = null;
        let moveCitaModalInstance = null;
        const clickPopup = (function () {
            const existing = document.getElementById('calendar-click-popup');
            if (existing) {
                return existing;
            }
            const el = document.createElement('div');
            el.id = 'calendar-click-popup';
            el.className = 'calendar-click-popup d-none';
            el.setAttribute('aria-hidden', 'true');
            document.body.appendChild(el);
            return el;
        })();

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

        function escapeHtml(value) {
            const str = value == null ? '' : String(value);
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function hideClickPopup() {
            if (!clickPopup) {
                return;
            }
            clickPopup._calendarEventId = null;
            clickPopup.classList.add('d-none');
            clickPopup.setAttribute('aria-hidden', 'true');
            clickPopup.innerHTML = '';
        }

        function positionClickPopup(jsEvent) {
            if (!clickPopup) {
                return;
            }
            const rect = clickPopup.getBoundingClientRect();
            const margin = 12;
            let x = (jsEvent && typeof jsEvent.clientX === 'number' ? jsEvent.clientX : window.innerWidth / 2) + margin;
            let y = (jsEvent && typeof jsEvent.clientY === 'number' ? jsEvent.clientY : window.innerHeight / 2) + margin;
            const maxX = Math.max(margin, window.innerWidth - rect.width - margin);
            const maxY = Math.max(margin, window.innerHeight - rect.height - margin);
            x = Math.max(margin, Math.min(x, maxX));
            y = Math.max(margin, Math.min(y, maxY));
            clickPopup.style.left = x + 'px';
            clickPopup.style.top = y + 'px';
        }

        function buildPopupHtml(event) {
            const props = event && event.extendedProps ? event.extendedProps : {};
            const isMeeting = props.eventKind === 'reunion';
            const isRecurringReservation = props.eventKind === 'reservacion_continua';
            const paciente = isMeeting ? (props.tipo || 'Reunión interna') : (props.paciente || 'Sin registro');
            const psicologo = props.psicologo || 'Sin registro';
            const contacto = isMeeting || isRecurringReservation ? 'No aplica' : (props.contacto_telefono || 'No especificado');
            const estatus = props.estatus || 'Sin dato';
            const tipo = props.tipo || 'Sin dato';
            const forma = isMeeting || isRecurringReservation ? 'No aplica' : (props.forma_pago || 'No especificado');
            const costo = props.costo !== null && props.costo !== undefined ? ('$' + Number(props.costo).toFixed(2)) : (isRecurringReservation ? 'No aplica' : 'No especificado');
            const tiempo = props.tiempo != null ? (String(props.tiempo) + ' min') : 'Sin dato';
            const startDate = event && event.start ? new Date(event.start) : null;
            const endDate = event && event.end ? new Date(event.end) : null;
            const dateLine = (startDate && endDate)
                ? (new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(startDate) + ' · ' + formatTimeRange(startDate, endDate))
                : (startDate ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(startDate) : 'Sin fecha');

            return (
                '<div class="popup-title" title="' + escapeHtml(paciente) + '">' + escapeHtml(paciente) + '</div>' +
                '<div class="popup-dates">' + escapeHtml(dateLine) + '</div>' +
                '<div class="popup-lines">' +
                '<div class="popup-line"><span class="popup-key">Psicóloga</span><span class="popup-val">' + escapeHtml(psicologo) + '</span></div>' +
                '<div class="popup-line"><span class="popup-key">Contacto</span><span class="popup-val">' + escapeHtml(contacto) + '</span>' + (USER_CAN_EDIT && !isMeeting && !isRecurringReservation ? '<button type="button" class="btn btn-outline-success btn-sm detail-message-button" data-calendar-action="message">Mensaje</button>' : '') + '</div>' +
                '<div class="popup-line"><span class="popup-key">Estatus</span><span class="popup-val">' + escapeHtml(estatus) + '</span></div>' +
                '<div class="popup-line"><span class="popup-key">Tipo</span><span class="popup-val">' + escapeHtml(tipo) + '</span></div>' +
                '<div class="popup-line"><span class="popup-key">Pago</span><span class="popup-val">' + escapeHtml(forma) + '</span></div>' +
                '<div class="popup-line"><span class="popup-key">Costo</span><span class="popup-val">' + escapeHtml(costo) + '</span></div>' +
                '<div class="popup-line"><span class="popup-key">Tiempo</span><span class="popup-val">' + escapeHtml(tiempo) + '</span></div>' +
                '</div>' +
                '<div class="popup-actions">' +
               
                (USER_CAN_EDIT && !isMeeting && !isRecurringReservation ? '<button type="button" class="popup-btn primary" data-calendar-action="reprogram">Reprogramar</button>' : '') +
                (USER_CAN_EDIT && !isMeeting ? '<button type="button" class="popup-btn danger" data-calendar-action="cancel">Cancelar</button>' : '') +
                '<button type="button" class="popup-btn" data-calendar-action="close">Cerrar</button>' +
                '</div>'
            );
        }

        function showClickPopup(event, jsEvent) {
            if (!clickPopup || !event) {
                return;
            }
            clickPopup._calendarEventId = event.id || null;
            clickPopup.innerHTML = buildPopupHtml(event);
            clickPopup.classList.remove('d-none');
            clickPopup.setAttribute('aria-hidden', 'false');
            positionClickPopup(jsEvent);
        }

        const detailFields = {
            paciente: document.getElementById('detail-paciente'),
            psicologo: document.getElementById('detail-psicologo'),
            contacto: document.getElementById('detail-contacto'),
            inicio: document.getElementById('detail-inicio'),
            fin: document.getElementById('detail-fin'),
            tiempo: document.getElementById('detail-tiempo'),
            estatus: document.getElementById('detail-estatus'),
            tipo: document.getElementById('detail-tipo'),
            forma: document.getElementById('detail-forma'),
            costo: document.getElementById('detail-costo'),
            reprogramRequests: document.getElementById('detail-reprogram-requests'),
            cancelRequests: document.getElementById('detail-cancel-requests')
        };

        function formatReminderDate(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                return 'Sin fecha';
            }

            const formatter = new Intl.DateTimeFormat('es-MX', {
                day: 'numeric',
                month: 'long'
            });
            const parts = formatter.formatToParts(date);
            const dayPart = parts.find(function (part) { return part.type === 'day'; });
            const monthPart = parts.find(function (part) { return part.type === 'month'; });
            const dayText = dayPart ? dayPart.value : '';
            const monthText = monthPart ? monthPart.value : '';
            const monthCapitalized = monthText ? monthText.charAt(0).toUpperCase() + monthText.slice(1) : '';

            return (dayText && monthCapitalized) ? (dayText + ' de ' + monthCapitalized) : formatter.format(date);
        }

        function formatReminderTime(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                return 'Sin horario';
            }

            return new Intl.DateTimeFormat('es-MX', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).format(date).replace(/\s+/g, ' ').trim();
        }

        function buildReminderMessage(event) {
            if (!event) {
                return '';
            }

            const props = event.extendedProps || {};
            const startDate = event.start instanceof Date ? event.start : null;
            const fechaTexto = formatReminderDate(startDate);
            const horaTexto = formatReminderTime(startDate);
            const paciente = props.paciente || 'su pequeño';
            const terapeuta = props.psicologo || 'nuestra terapeuta';

            return [
                'Hola, muy buenas tardes 😊',
                'Esperamos se encuentren muy bien.',
                '',
                'Les contactamos para recordarles que el día ' + fechaTexto + ' a la ' + horaTexto + ' está agendada la sesión de su pequeño ' + paciente + ' con nuestra terapeuta ' + terapeuta + '.',
                '',
                '¿Podrían confirmarnos si les será posible asistir?',
                'Quedamos atentas. ¡Será un gusto recibirlos! ✨'
            ].join('\n');
        }

        function copyReminderMessageForEvent(event) {
            if (!event) {
                showAlert('No fue posible localizar la cita seleccionada.', 'warning');
                return;
            }

            const props = event.extendedProps || {};
            if (props.eventKind !== 'cita') {
                showAlert('El mensaje solo está disponible para citas.', 'warning');
                return;
            }

            const reminderMessage = buildReminderMessage(event);
            if (!reminderMessage) {
                showAlert('No fue posible generar el mensaje.', 'danger');
                return;
            }

            const fallbackCopy = function () {
                const temp = document.createElement('textarea');
                temp.value = reminderMessage;
                temp.setAttribute('readonly', 'readonly');
                temp.style.position = 'fixed';
                temp.style.opacity = '0';
                document.body.appendChild(temp);
                temp.focus();
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            };

            Promise.resolve()
                .then(function () {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        return navigator.clipboard.writeText(reminderMessage);
                    }
                    fallbackCopy();
                    return null;
                })
                .then(function () {
                    showTemporaryAlert('Mensaje copiado al portapapeles.', 'success');
                })
                .catch(function () {
                    try {
                        fallbackCopy();
                        showTemporaryAlert('Mensaje copiado al portapapeles.', 'success');
                    } catch (error) {
                        console.error(error);
                        showAlert('No fue posible copiar el mensaje.', 'danger');
                    }
                });
        }

        function copyReminderMessage() {
            if (!selectedEventId) {
                showAlert('Selecciona una cita para generar el mensaje.', 'warning');
                return;
            }

            const event = calendar.getEventById(selectedEventId);
            copyReminderMessageForEvent(event);
        }

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

        function ensureMoveCitaModalInstance() {
            if (!moveCitaModalElement) {
                return null;
            }

            if (!moveCitaModalInstance) {
                if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return null;
                }
                moveCitaModalInstance = new bootstrap.Modal(moveCitaModalElement, { backdrop: 'static', keyboard: true });
            }

            return moveCitaModalInstance;
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

        function confirmMoveWithModal(event, fromDate, toDate) {
            const modal = ensureMoveCitaModalInstance();
            if (!modal || !(toDate instanceof Date) || Number.isNaN(toDate.getTime())) {
                return Promise.resolve(toDate);
            }

            const props = event && event.extendedProps ? event.extendedProps : {};
            if (moveCitaPaciente) {
                moveCitaPaciente.textContent = props.paciente || 'Sin registro';
            }
            if (moveCitaPsicologa) {
                moveCitaPsicologa.textContent = props.psicologo || 'Sin registro';
            }
            if (moveCitaFrom) {
                moveCitaFrom.textContent = fromDate ? dateFormatter.format(fromDate) : 'Sin dato';
            }
            if (moveCitaTo) {
                moveCitaTo.textContent = new Intl.DateTimeFormat('es-MX', { dateStyle: 'full' }).format(toDate);
            }
            if (moveCitaTime) {
                moveCitaTime.value = String(toDate.getHours()).padStart(2, '0') + ':' + String(toDate.getMinutes()).padStart(2, '0');
            }

            return new Promise(function (resolve) {
                let done = false;

                function cleanup() {
                    moveCitaModalElement.removeEventListener('hidden.bs.modal', onHidden);
                    if (moveCitaConfirm) {
                        moveCitaConfirm.removeEventListener('click', onConfirm);
                    }
                    if (moveCitaCancel) {
                        moveCitaCancel.removeEventListener('click', onCancel);
                    }
                }

                function finish(value) {
                    if (done) {
                        return;
                    }
                    done = true;
                    cleanup();
                    resolve(value);
                }

                function computeAdjustedDate() {
                    const adjusted = new Date(toDate.getTime());
                    if (!moveCitaTime || typeof moveCitaTime.value !== 'string') {
                        return adjusted;
                    }
                    const match = moveCitaTime.value.match(/^(\d{2}):(\d{2})$/);
                    if (!match) {
                        return adjusted;
                    }
                    adjusted.setHours(Number.parseInt(match[1], 10), Number.parseInt(match[2], 10), 0, 0);
                    return adjusted;
                }

                function onHidden() {
                    finish(null);
                }

                function onConfirm() {
                    modal.hide();
                    finish(computeAdjustedDate());
                }

                function onCancel() {
                    modal.hide();
                    finish(null);
                }

                moveCitaModalElement.addEventListener('hidden.bs.modal', onHidden);
                if (moveCitaConfirm) {
                    moveCitaConfirm.addEventListener('click', onConfirm);
                }
                if (moveCitaCancel) {
                    moveCitaCancel.addEventListener('click', onCancel);
                }

                modal.show();
            });
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

        function sendCancelRecurringReservationRequest(reservacionId) {
            if (!reservacionId) {
                return;
            }

            const confirmed = window.confirm('¿Deseas cancelar esta reservación continua?');
            if (!confirmed) {
                return;
            }

            const params = new URLSearchParams();
            params.append('reservacionId', reservacionId);

            fetch('../api/reservaciones_continuas_cancelar.php', {
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
                        throw new Error(data && data.message ? data.message : 'No se pudo cancelar la reservación continua.');
                    }

                    window.location.reload();
                })
                .catch(function (error) {
                    console.error(error);
                    showAlert(error.message || 'No se pudo cancelar la reservación continua.', 'danger');
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
                const entityId = detailCancelButton.dataset.citaId || '';
                const eventKind = detailCancelButton.dataset.eventKind || 'cita';
                if (!entityId) {
                    showAlert('Selecciona un elemento para cancelar.', 'warning');
                    return;
                }

                if (eventKind === 'reservacion_continua') {
                    sendCancelRecurringReservationRequest(entityId);
                } else {
                    sendCancelRequest(entityId);
                }
            });
        }

        if (detailMessageButton) {
            detailMessageButton.addEventListener('click', copyReminderMessage);
        }

        if (clickPopup) {
            clickPopup.addEventListener('click', function (event) {
                const target = event.target instanceof Element ? event.target.closest('[data-calendar-action]') : null;
                if (!target) {
                    return;
                }

                const action = target.getAttribute('data-calendar-action');
                const eventId = clickPopup._calendarEventId || '';
                const calendarEvent = eventId ? calendar.getEventById(eventId) : null;

                if (action === 'close') {
                    hideClickPopup();
                    return;
                }

                if (!calendarEvent) {
                    hideClickPopup();
                    showAlert('No fue posible localizar el evento seleccionado.', 'warning');
                    return;
                }

                if (action === 'reprogram') {
                    hideClickPopup();
                    openReprogramModalForCita(calendarEvent.extendedProps && calendarEvent.extendedProps.entityId ? calendarEvent.extendedProps.entityId : '');
                    return;
                }

                if (action === 'message') {
                    copyReminderMessageForEvent(calendarEvent);
                    hideClickPopup();
                    return;
                }

                if (action === 'cancel') {
                    hideClickPopup();
                    if (calendarEvent.extendedProps && calendarEvent.extendedProps.eventKind === 'reservacion_continua') {
                        sendCancelRecurringReservationRequest(calendarEvent.extendedProps.entityId || '');
                    } else {
                        sendCancelRequest(calendarEvent.extendedProps && calendarEvent.extendedProps.entityId ? calendarEvent.extendedProps.entityId : '');
                    }
                }
            });

            document.addEventListener('pointerdown', function (event) {
                if (clickPopup.classList.contains('d-none')) {
                    return;
                }
                if (event && event.target && clickPopup.contains(event.target)) {
                    return;
                }
                hideClickPopup();
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event && event.key === 'Escape') {
                    hideClickPopup();
                }
            });
        }

        function updateDetail(event) {
            if (!event) {
                return;
            }

            const props = event.extendedProps || {};
            const eventKind = props.eventKind || 'cita';
            const isMeeting = eventKind === 'reunion';
            const isRecurringReservation = eventKind === 'reservacion_continua';

            const reprogramCount = normalizeCount(props.solicitudesReprogramacionPendientes);
            const cancelCount = normalizeCount(props.solicitudesCancelacionPendientes);

            if (detailFields.paciente) {
                detailFields.paciente.textContent = isMeeting ? 'No aplica (reunión interna)' : (props.paciente || 'Sin registro');
            }

            if (detailFields.psicologo) {
                detailFields.psicologo.textContent = props.psicologo || 'Sin registro';
            }

            if (detailFields.contacto) {
                detailFields.contacto.textContent = props.contacto_telefono || 'No especificado';
            }

            if (detailMessageButton) {
                detailMessageButton.disabled = isMeeting || isRecurringReservation;
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
                detailFields.forma.textContent = (isMeeting || isRecurringReservation) ? 'No aplica' : (props.forma_pago || 'No especificado');
            }

            if (detailFields.costo) {
                const costoValido = props.costo !== null && props.costo !== undefined;
                detailFields.costo.textContent = costoValido
                    ? '$' + Number(props.costo).toFixed(2)
                    : (isRecurringReservation ? 'No aplica' : 'No especificado');
            }

            if (detailFields.reprogramRequests) {
                if (isMeeting || isRecurringReservation) {
                    detailFields.reprogramRequests.innerHTML = '<span class="badge bg-secondary">No aplica</span>';
                } else {
                    setRequestBadge(detailFields.reprogramRequests, reprogramCount);
                }
            }

            if (detailFields.cancelRequests) {
                if (isMeeting || isRecurringReservation) {
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

            if (detailFields.tiempo) {
                let minutos = Number.parseInt(props.tiempo, 10);
                if (Number.isNaN(minutos) || minutos <= 0) {
                    if (event.start instanceof Date && event.end instanceof Date) {
                        const diffMs = event.end.getTime() - event.start.getTime();
                        minutos = Math.round(diffMs / 60000);
                    }
                }
                detailFields.tiempo.textContent = Number.isFinite(minutos) && minutos > 0 ? (minutos + ' min') : 'Sin registro';
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
                const editable = USER_CAN_EDIT && !isMeeting && !isRecurringReservation && Boolean(props.isEditable) && !isCancelled;
                detailReprogramButton.disabled = !editable || !props.entityId;
                if (!editable) {
                    helperMessages.push(isMeeting
                        ? 'Las reuniones internas no se reprograman desde este botón.'
                        : (isRecurringReservation
                            ? 'Las reservaciones continuas solo se pueden cancelar.'
                            : 'La cita no se puede reprogramar desde el calendario.'));
                }
            }

            if (detailCancelButton) {
                detailCancelButton.dataset.citaId = props.entityId || '';
                detailCancelButton.dataset.eventKind = eventKind;
                detailCancelButton.textContent = isMeeting ? 'No disponible' : (isRecurringReservation ? 'Cancelar reservación' : 'Cancelar');
                detailCancelButton.disabled = !USER_CAN_EDIT || isMeeting || !props.entityId || isCancelled;
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
            editable: USER_CAN_EDIT,
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
                            const eventKind = item.event_kind === 'reunion'
                                ? 'reunion'
                                : (item.event_kind === 'reservacion_continua' ? 'reservacion_continua' : 'cita');
                            const statusStyle = statusStyles[item.estatus] || defaultStatusStyles;
                            const paciente = item.paciente || 'Sin registro';
                            const psicologo = getPsychologistDisplayName(item.psicologo);
                            let meetingTitle = item.tipo || 'Reunión interna';
                            if (meetingTitle.length > 35) {
                                meetingTitle = meetingTitle.substring(0, 35) + '...';
                            }
                            const title = eventKind === 'reunion'
                                ? ('Reunión: ' + meetingTitle)
                                : (eventKind === 'reservacion_continua'
                                    ? ('Reservación continua: ' + paciente + ' | Psicóloga: ' + psicologo)
                                    : ('Paciente: ' + paciente + ' | Psicóloga: ' + psicologo));

                            const classNames = ['calendar-event'];
                            if (statusStyle.eventClass) {
                                classNames.push(statusStyle.eventClass);
                            }
                            if (eventKind === 'reunion') {
                                classNames.push('event-type-reunion');
                            } else if (eventKind === 'reservacion_continua') {
                                classNames.push('event-type-recurring-reservation');
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
                            const isEditable = USER_CAN_EDIT && eventKind === 'cita' && Boolean(
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
                                    contacto_telefono: item.contacto_telefono || null,
                                    psicologo: psicologo,
                                    estatus: item.estatus,
                                    statusBadgeClass: statusStyle.badgeClass,
                                    tipo: item.tipo,
                                    forma_pago: item.forma_pago,
                                    costo: item.costo,
                                    tiempo: item.tiempo,
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
                const eventKind = arg.event.extendedProps && arg.event.extendedProps.eventKind;
                const isMeeting = eventKind === 'reunion';
                const isRecurringReservation = eventKind === 'reservacion_continua';
                const content = document.createElement('div');
                content.classList.add('calendar-event-body');

                const time = document.createElement('span');
                time.classList.add('calendar-event-time');
                let timeText = formatTimeRange(arg.event.start, arg.event.end);
                const durationMinutes = Number.parseInt(arg.event.extendedProps && arg.event.extendedProps.tiempo, 10);
                if (Number.isFinite(durationMinutes) && durationMinutes > 0 && !isMeeting) {
                    timeText += ' (' + durationMinutes + 'm)';
                }
                time.textContent = timeText;
                content.appendChild(time);

                const paciente = document.createElement('span');
                paciente.classList.add('calendar-event-paciente');
                let meetingLabel = arg.event.extendedProps.tipo || 'Reunión interna';
                if (meetingLabel.length > 35) {
                    meetingLabel = meetingLabel.substring(0, 35) + '...';
                }
                paciente.textContent = isMeeting
                    ? meetingLabel
                    : (isRecurringReservation
                        ? ('Reserva: ' + (arg.event.extendedProps.paciente || 'Sin registro'))
                        : (arg.event.extendedProps.paciente || 'Sin registro'));
                content.appendChild(paciente);

                if (arg.event.extendedProps.psicologo) {
                    const psicologo = document.createElement('span');
                    psicologo.classList.add('calendar-event-psicologo');
                    let psicologoText = 'A: ' + arg.event.extendedProps.psicologo;
                    if (psicologoText.length > 35) {
                        psicologoText = psicologoText.substring(0, 35) + '...';
                    }
                    psicologo.textContent = psicologoText;
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

                hideClickPopup();

                if (instructions) {
                    instructions.classList.add('d-none');
                }
                if (detailRow) {
                    detailRow.classList.remove('d-none');
                }

                updateDetail(info.event);
                showClickPopup(info.event, info.jsEvent);
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

                const previousStart = info.oldEvent && info.oldEvent.start ? new Date(info.oldEvent.start) : null;

                confirmMoveWithModal(event, previousStart, newStart)
                    .then(function (finalStart) {
                        if (!finalStart) {
                            info.revert();
                            return null;
                        }

                        const programadoSql = toSqlDateTime(finalStart);
                        if (!programadoSql) {
                            throw new Error('La fecha seleccionada no es válida.');
                        }

                        const requestBody = {
                            programado: programadoSql
                        };

                        return fetch('../api/citas.php?id=' + encodeURIComponent(props.entityId), {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(requestBody)
                        }).then(function (response) {
                            return response.json().catch(function () {
                                return null;
                            }).then(function (data) {
                                return {
                                    ok: response.ok,
                                    data: data,
                                    requestBody: requestBody,
                                    finalStart: finalStart
                                };
                            });
                        });
                    })
                    .then(function (result) {
                        if (!result) {
                            return null;
                        }

                        var data = result.data || null;
                        if (!result.ok) {
                            if (data && data.conflict && window.confirm((data.message || 'La psicóloga seleccionada ya tiene una cita en ese horario.') + '\n\n¿Deseas continuar y marcar la cita como forzada?')) {
                                result.requestBody.forzar = true;
                                return fetch('../api/citas.php?id=' + encodeURIComponent(props.entityId), {
                                    method: 'PUT',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(result.requestBody)
                                }).then(function (retryResponse) {
                                    return retryResponse.json().catch(function () {
                                        return null;
                                    }).then(function (retryData) {
                                        if (!retryResponse.ok) {
                                            throw new Error((retryData && (retryData.error || retryData.message)) || 'No se pudo guardar la reprogramación.');
                                        }
                                        return {
                                            data: retryData,
                                            finalStart: result.finalStart
                                        };
                                    });
                                });
                            }

                            throw new Error((data && (data.error || data.message)) || 'No se pudo guardar la reprogramación.');
                        }

                        return {
                            data: data,
                            finalStart: result.finalStart
                        };
                    })
                    .then(function (result) {
                        if (!result) {
                            return;
                        }

                        const currentProps = event.extendedProps || {};
                        const currentStart = result.finalStart instanceof Date ? result.finalStart : event.start;
                        const durationMinutes = Number.parseInt(currentProps.tiempo, 10);
                        const durationMs = Number.isFinite(durationMinutes) && durationMinutes > 0 ? durationMinutes * 60000 : 3600000;
                        const newEnd = currentStart instanceof Date ? new Date(currentStart.getTime() + durationMs) : null;
                        const startTimestampValue = currentStart instanceof Date ? currentStart.getTime() : null;
                        const endTimestampValue = newEnd instanceof Date ? newEnd.getTime() : null;
                        const todayTimestamp = getStartOfToday().getTime();
                        const stillEditable = isEventEditableByPolicy(currentProps.estatus, startTimestampValue, endTimestampValue, currentProps.forma_pago, todayTimestamp);

                        if (currentStart instanceof Date) {
                            event.setStart(currentStart);
                            event.setExtendedProp('programado', currentStart.toISOString());
                        }
                        if (newEnd instanceof Date) {
                            event.setEnd(newEnd);
                            event.setExtendedProp('termina', newEnd.toISOString());
                        }

                        event.setExtendedProp('startTimestamp', startTimestampValue);
                        event.setExtendedProp('endTimestamp', endTimestampValue);
                        event.setExtendedProp('isEditable', stillEditable);
                        event.setProp('startEditable', stillEditable);
                        event.setProp('durationEditable', false);

                        currentProps.startTimestamp = startTimestampValue;
                        currentProps.endTimestamp = endTimestampValue;
                        currentProps.isEditable = stillEditable;
                        currentProps.programado = currentStart instanceof Date ? currentStart.toISOString() : currentProps.programado;
                        currentProps.termina = newEnd instanceof Date ? newEnd.toISOString() : currentProps.termina;

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

        if (openRecurringReservationModalButton) {
            openRecurringReservationModalButton.addEventListener('click', openRecurringReservationModal);
        }

        if (saveMeetingButton) {
            saveMeetingButton.addEventListener('click', saveMeeting);
        }

        if (saveRecurringReservationButton) {
            saveRecurringReservationButton.addEventListener('click', function () {
                saveRecurringReservation(false);
            });
        }

        loadMeetingsTable();
        calendar.render();
    });
</script>

<?php if (!$agendaSoloLectura) { ?>
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
<?php } ?>

<?php
include '../Modulos/footer.php';
?>
