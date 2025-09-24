<?php
include '../Modulos/head.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
<style>
    #calendar {
        min-height: 650px;
    }

    .fc .fc-daygrid-event {
        border-radius: 6px;
        padding: 2px 4px;
    }

    .fc .fc-event-main {
        white-space: normal;
        line-height: 1.2;
    }

    .calendar-legend .badge {
        font-size: 0.85rem;
        padding: 0.35rem 0.65rem;
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
                <div class="card-body">
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
                <span class="badge" style="background-color: #1a73e8;">Creada</span>
                <span class="badge" style="background-color: #fbbc04;">Reprogramado</span>
                <span class="badge" style="background-color: #34a853;">Finalizada</span>
                <span class="badge" style="background-color: #6c757d;">Cancelada</span>
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

        const statusColors = {
            'Creada': '#1a73e8',
            'Reprogramado': '#fbbc04',
            'Finalizada': '#34a853',
            'Cancelada': '#6c757d'
        };

        const dateFormatter = new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });

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
                            const color = statusColors[item.estatus] || '#1a73e8';
                            const title = 'Paciente: ' + item.paciente + '\nPsicóloga: ' + item.psicologo;

                            return {
                                id: item.id,
                                title: title,
                                start: item.programado,
                                end: item.termina,
                                backgroundColor: color,
                                borderColor: color,
                                textColor: '#ffffff',
                                extendedProps: {
                                    paciente: item.paciente,
                                    psicologo: item.psicologo,
                                    estatus: item.estatus,
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
                const lines = arg.event.title.split('\n');
                const content = document.createElement('div');
                lines.forEach(function (line, index) {
                    const span = document.createElement('span');
                    span.textContent = line;
                    if (index === 0) {
                        span.classList.add('fw-semibold');
                    }
                    content.appendChild(span);
                    if (index < lines.length - 1) {
                        content.appendChild(document.createElement('br'));
                    }
                });
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
                detailFields.estatus.textContent = props.estatus || 'Sin registro';
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
