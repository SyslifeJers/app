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
    }

    .fc-event.calendar-event .calendar-event-body {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .fc-event.calendar-event .calendar-event-time {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.35rem;
        font-weight: 700;
    }

    .fc-event.calendar-event .calendar-event-paciente {
        font-size: 0.86rem;
        font-weight: 600;
    }

    .fc-event.calendar-event .calendar-event-psicologo {
        font-size: 0.78rem;
        opacity: 0.9;
    }

    .fc-event.calendar-event.event-status-creada .fc-event-main {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #60a5fa;
        color: #1e3a8a;
    }

    .fc-event.calendar-event.event-status-reprogramado .fc-event-main {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #f59e0b;
        color: #92400e;
    }

    .fc-event.calendar-event.event-status-finalizada .fc-event-main {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-color: #22c55e;
        color: #166534;
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
        <ul class="breadcrumbs mb-3">
            <li class="nav-home">
                <a href="/index.php">
                    <i class="icon-home"></i>
                </a>
            </li>
            <li class="separator">
                <i class="icon-arrow-right"></i>
            </li>
            <li class="nav-item">
                <a>Calendario</a>
            </li>
        </ul>
        <p class="mb-0 text-muted">Consulta las citas programadas y revisa los detalles de cada paciente y psicóloga.</p>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body calendar-wrapper">
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

        const dateFormatter = new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });

        const timeFormatter = new Intl.DateTimeFormat('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
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

        const detailRow = document.getElementById('detail-row');
        const instructions = document.getElementById('calendar-instructions');
        const alertBox = document.getElementById('calendar-alert');

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

                        const events = payload.data.map(function (item) {
                            const statusStyle = statusStyles[item.estatus] || defaultStatusStyles;
                            const paciente = item.paciente || 'Sin registro';
                            const psicologo = item.psicologo || 'Sin registro';
                            const title = 'Paciente: ' + paciente + ' | Psicóloga: ' + psicologo;

                            return {
                                id: item.id,
                                title: title,
                                start: item.programado,
                                end: item.termina,
                                classNames: ['calendar-event', statusStyle.eventClass],
                                extendedProps: {
                                    paciente: paciente,
                                    psicologo: psicologo,
                                    estatus: item.estatus,
                                    statusBadgeClass: statusStyle.badgeClass,
                                    tipo: item.tipo,
                                    forma_pago: item.forma_pago,
                                    costo: item.costo,
                                    programado: item.programado,
                                    termina: item.termina
                                }
                            };
                        });

                        if (alertBox) {
                            alertBox.classList.add('d-none');
                            alertBox.textContent = '';
                        }

                        successCallback(events);
                    })
                    .catch(function (error) {
                        console.error(error);
                        if (alertBox) {
                            alertBox.textContent = 'No se pudieron cargar las citas. Por favor intenta nuevamente.';
                            alertBox.classList.remove('d-none');
                        }
                        failureCallback(error);
                    });
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
            eventClick: function (info) {
                info.jsEvent.preventDefault();

                const props = info.event.extendedProps;
                if (instructions) {
                    instructions.classList.add('d-none');
                }
                if (detailRow) {
                    detailRow.classList.remove('d-none');
                }

                detailFields.paciente.textContent = props.paciente || 'Sin registro';
                detailFields.psicologo.textContent = props.psicologo || 'Sin registro';

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
                detailFields.tipo.textContent = props.tipo || 'Sin registro';
                detailFields.forma.textContent = props.forma_pago || 'No especificado';
                detailFields.costo.textContent =
                    props.costo !== null && props.costo !== undefined
                        ? '$' + Number(props.costo).toFixed(2)
                        : 'No especificado';

                const inicio = info.event.start ? dateFormatter.format(info.event.start) : 'Sin registro';
                const fin = info.event.end ? dateFormatter.format(info.event.end) : 'Sin registro';
                detailFields.inicio.textContent = inicio;
                detailFields.fin.textContent = fin;
            }
        });

        calendar.render();
    });
</script>

<?php
include '../Modulos/footer.php';
?>
