<?php
include '../Modulos/head.php';

$ROL_ADMIN = 3;
$canEditDemo2 = ((int) $rol === $ROL_ADMIN);
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
</style>

<div class="page-inner">
    <div class="page-header">
        <h3 class="fw-bold mb-3">Calendario Demo 2</h3>
        <p class="text-muted mb-0">Vista alternativa con Toast UI Calendar. <?php echo $canEditDemo2 ? 'Edicion habilitada para administradores.' : 'Solo lectura.'; ?></p>
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
    <div class="row mt-3 d-none" id="psychologist-legend-row">
        <div class="col-12">
            <h6 class="fw-semibold mb-2">Colores por psicologa</h6>
            <div class="d-flex flex-wrap gap-2 calendar-psychologist-legend" id="calendar-psychologist-legend"></div>
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

        function escapeHtml(value) {
            const str = value == null ? '' : String(value);
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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
                const name = getPsychologistDisplayName(raw.psicologo);
                const palette = raw.psicologoColor || computePsychologistPalette(name, raw.psicologoColorHex);
                if (!palettes[name]) {
                    palettes[name] = palette;
                }
            });
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
            fetch('../api/citas_calendario.php?' + params.toString(), { credentials: 'same-origin' })
                .then(function (response) {
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
            const paciente = raw.paciente || 'Sin registro';
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
                detailReprogramRequests.innerHTML = renderCountBadge(raw.solicitudesReprogramacionPendientes, style.badgeClass);
            }
            if (detailCancelRequests) {
                const style = statusStyles['Cancelada'] || { badgeClass: 'status-cancelada' };
                detailCancelRequests.innerHTML = renderCountBadge(raw.solicitudesCancelacionPendientes, style.badgeClass);
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
            month: { startDayOfWeek: 1 },
            week: { startDayOfWeek: 1 },
            template: {
                time: function (event) {
                    const start = toDateSafe(event.start);
                    const end = toDateSafe(event.end);
                    const timeText = start ? formatTimeRange(start, end) : '';
                    const title = escapeHtml(event.title || '');
                    const psy = event.raw && event.raw.psicologo ? ('<div style="opacity:0.85;font-size:12px;">' + escapeHtml(event.raw.psicologo) + '</div>') : '';
                    return '<div style="font-weight:700;">' + escapeHtml(timeText) + '</div><div>' + title + '</div>' + psy;
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
            fetch('../api/citas_calendario.php?' + params.toString(), { credentials: 'same-origin' })
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
                        if (item && typeof item.event_kind === 'string' && item.event_kind !== 'cita') {
                            return null;
                        }

                        const apiId = item && Object.prototype.hasOwnProperty.call(item, 'entity_id')
                            ? parseCitaId(item.entity_id)
                            : parseCitaId(item && item.id);

                        if (!apiId) {
                            return null;
                        }

                        const paciente = item.paciente || 'Sin registro';
                        const psicologo = getPsychologistDisplayName(item.psicologo);
                        const psicologoColorHex = normalizeHexColor(item.psicologo_color);
                        const palette = computePsychologistPalette(psicologo, psicologoColorHex);
                        const startDate = item.programado ? new Date(item.programado) : null;
                        const hasValidStart = startDate instanceof Date && !Number.isNaN(startDate.getTime());
                        const startTimestamp = hasValidStart ? startDate.getTime() : null;
                        const isEditable = CAN_EDIT && item.estatus !== 'Cancelada' && typeof startTimestamp === 'number' && startTimestamp >= todayTimestamp;
                        return {
                            id: String(item.id != null ? item.id : apiId),
                            calendarId: String(item.psicologo_id || 0),
                            title: paciente,
                            category: 'time',
                            start: item.programado,
                            end: item.termina,
                            isReadOnly: !isEditable,
                            backgroundColor: palette.base,
                            borderColor: palette.border,
                            color: palette.text,
                            raw: {
                                apiId: apiId,
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
        });

        calendar.on('beforeUpdateEvent', function (ev) {
            if (!CAN_EDIT) {
                return;
            }
            if (!ev || !ev.event || !ev.changes) {
                return;
            }
            const id = ev.event.id;
            const calendarId = ev.event.calendarId;
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

            fetch('../api/citas.php?id=' + encodeURIComponent(String(apiId)), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ programado: newStart.toISOString() })
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo guardar la reprogramacion.');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.error) {
                        throw new Error(data.error);
                    }
                    calendar.updateEvent(id, calendarId, changes);
                    refetchEvents();
                })
                .catch(function (error) {
                    console.error(error);
                    showAlert(error && error.message ? error.message : 'No se pudo reprogramar la cita.', 'danger');
                });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                calendar.prev();
                renderTitle();
                refetchEvents();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                calendar.next();
                renderTitle();
                refetchEvents();
            });
        }
        if (todayBtn) {
            todayBtn.addEventListener('click', function () {
                calendar.today();
                renderTitle();
                refetchEvents();
            });
        }

        if (viewButtons && viewButtons.length > 0) {
            viewButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
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
