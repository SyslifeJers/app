const ES_VENTAS = Boolean(window.ES_VENTAS);
const pagoModalEstado = {
  modal: null,
  idCita: null,
  estatus: null,
  costo: 0,
  saldo: 0,
  pagos: [],
  pacienteId: null,
  pacienteNombre: '',
  psicologoId: null,
  psicologoNombre: '',
  tipo: '',
  programado: null,
  mensajePago: ''
};
const modalPagoElement = document.getElementById('ModalTipoPago');
const tablaPagosBody = document.querySelector('#tablaPagos tbody');
const agregarPagoBtn = document.getElementById('agregarPago');
const agregarPagoSaldoBtn = document.getElementById('agregarPagoSaldo');
const resumenPagos = document.getElementById('resumenPagos');
const saldoActualLabel = document.getElementById('modalSaldoActual');
const costoCitaLabel = document.getElementById('modalCostoCita');
const pacienteLabel = document.getElementById('modalPacienteNombre');
const alertaSaldoInsuficiente = document.getElementById('alertaSaldoInsuficiente');
const modalProximaCitaElement = document.getElementById('modalProximaCita');
const alertaPagoExitoso = document.getElementById('alertaPagoExitoso');
const proximaClienteInput = document.getElementById('proximaCliente');
const proximaPsicologoInput = document.getElementById('proximaPsicologo');
const proximaFechaInput = document.getElementById('proximaFecha');
const proximaCostoInput = document.getElementById('proximaCosto');
const proximaSendIdClienteInput = document.getElementById('proximaSendIdCliente');
const proximaSendIdPsicologoInput = document.getElementById('proximaSendIdPsicologo');
const proximaResumenTipoInput = document.getElementById('proximaResumenTipo');
const confirmarProximaCitaBtn = document.getElementById('confirmarProximaCita');
const omitirProximaCitaBtn = document.getElementById('omitirProximaCita');
const formProximaCita = document.getElementById('formProximaCita');
const proximaCitaState = { modal: null, reloading: false };
const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
const METODOS_PAGO = ['Efectivo', 'Transferencia', 'Tarjeta'];
const METODO_SALDO = 'Saldo';
const paqueteSelect = document.getElementById('paqueteSelect');
const resumenPaqueteInput = document.getElementById('resumenPaquete');
const sendIdPaqueteInput = document.getElementById('sendIdPaquete');
const paqueteMetodoSelect = document.getElementById('paqueteMetodo');
const grupoMetodoPaquete = document.getElementById('grupoMetodoPaquete');
const grupoSaldoPaquete = document.getElementById('grupoSaldoPaquete');
const resumenSaldoPaqueteInput = document.getElementById('resumenSaldoPaquete');
const paquetesDisponibles = new Map();

function formatearDatetimeLocal(fecha) {
  if (!(fecha instanceof Date) || Number.isNaN(fecha.getTime())) {
    return '';
  }

  const year = fecha.getFullYear();
  const month = String(fecha.getMonth() + 1).padStart(2, '0');
  const day = String(fecha.getDate()).padStart(2, '0');
  const hours = String(fecha.getHours()).padStart(2, '0');
  const minutes = String(fecha.getMinutes()).padStart(2, '0');

  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function obtenerFechaDesdeCadena(cadena) {
  if (!cadena) {
    return null;
  }

  const normalizada = cadena.includes('T') ? cadena : cadena.replace(' ', 'T');
  const fecha = new Date(normalizada);
  if (Number.isNaN(fecha.getTime())) {
    return null;
  }
  return fecha;
}

function normalizarHoraCerradaInput(input) {
  if (!input || !input.value) {
    return;
  }

  const fecha = obtenerFechaDesdeCadena(input.value);
  if (!fecha) {
    return;
  }

  fecha.setMinutes(0, 0, 0);
  input.value = formatearDatetimeLocal(fecha);
}

function obtenerTotalesPagos() {
  const totales = {
    total: 0,
    totalSaldo: 0,
    totalExternos: 0,
    metodos: new Set()
  };

  if (!Array.isArray(pagoModalEstado.pagos)) {
    pagoModalEstado.pagos = [];
  }

  pagoModalEstado.pagos.forEach((pago) => {
    const metodo = (pago.metodo || '').trim();
    const monto = parseFloat(pago.monto);
    if (!metodo || Number.isNaN(monto)) {
      return;
    }

    totales.total += monto;
    totales.metodos.add(metodo);
    if (metodo === METODO_SALDO) {
      totales.totalSaldo += monto;
    } else {
      totales.totalExternos += monto;
    }
  });

  return totales;
}

function actualizarResumenPagos() {
  const totales = obtenerTotalesPagos();
  if (resumenPagos) {
    let mensaje = 'Total registrado: ' + formatoMoneda.format(totales.total) + '. ';
    if (totales.total + 0.0001 < pagoModalEstado.costo) {
      const faltante = pagoModalEstado.costo - totales.total;
      mensaje += 'Faltan ' + formatoMoneda.format(faltante) + ' por cubrir. El saldo pendiente se sumará a próximas citas.';
    } else {
      const excedente = totales.total - pagoModalEstado.costo;
      if (excedente > 0.009) {
        mensaje += 'Excedente de ' + formatoMoneda.format(excedente) + ' se agregará al saldo.';
      } else {
        mensaje += 'Monto exacto para cubrir la cita.';
      }
    }

    if (totales.totalSaldo > 0.009) {
      const saldoRestante = Math.max(0, pagoModalEstado.saldo - totales.totalSaldo);
      mensaje += ' Saldo utilizado: ' + formatoMoneda.format(totales.totalSaldo) + '. Saldo restante: ' + formatoMoneda.format(saldoRestante) + '.';
    }

    resumenPagos.textContent = mensaje;
  }

  if (alertaSaldoInsuficiente) {
    if (totales.totalSaldo > pagoModalEstado.saldo + 0.0001) {
      alertaSaldoInsuficiente.classList.remove('d-none');
    } else {
      alertaSaldoInsuficiente.classList.add('d-none');
    }
  }

  actualizarBotonesPagos();
}

function actualizarBotonesPagos() {
  if (!agregarPagoSaldoBtn) {
    return;
  }

  const saldoDisponible = pagoModalEstado.saldo || 0;
  const saldoEnUso = pagoModalEstado.pagos.some((pago) => (pago.metodo || '').trim() === METODO_SALDO);

  if (saldoDisponible <= 0 || saldoEnUso) {
    agregarPagoSaldoBtn.setAttribute('disabled', 'disabled');
  } else {
    agregarPagoSaldoBtn.removeAttribute('disabled');
  }
}

function obtenerPaqueteSeleccionado() {
  if (!paqueteSelect) {
    return null;
  }
  const paqueteId = paqueteSelect.value;
  if (!paqueteId) {
    return null;
  }
  return paquetesDisponibles.get(paqueteId) || null;
}

function aplicarPaqueteSeleccionado() {
  if (!resumenPaqueteInput || !sendIdPaqueteInput) {
    return;
  }

  const paqueteSeleccionado = obtenerPaqueteSeleccionado();
  const resumenCostoInput = document.getElementById('resumenCosto');
  const costosSelect = document.getElementById('costosSelect');

  if (paqueteSeleccionado) {
    resumenPaqueteInput.value = paqueteSeleccionado.nombre;
    sendIdPaqueteInput.value = paqueteSeleccionado.id;
    if (resumenCostoInput) {
      resumenCostoInput.value = Number(paqueteSeleccionado.primer_pago_monto).toFixed(2);
      resumenCostoInput.setAttribute('readonly', 'readonly');
    }
    if (grupoMetodoPaquete) {
      grupoMetodoPaquete.classList.remove('d-none');
    }
    if (grupoSaldoPaquete) {
      grupoSaldoPaquete.classList.remove('d-none');
    }
    if (resumenSaldoPaqueteInput) {
      resumenSaldoPaqueteInput.value = formatoMoneda.format(paqueteSeleccionado.saldo_adicional);
    }
    if (paqueteMetodoSelect && !paqueteMetodoSelect.value) {
      paqueteMetodoSelect.value = 'Efectivo';
    }
  } else {
    resumenPaqueteInput.value = 'Sin paquete';
    sendIdPaqueteInput.value = '';
    if (resumenCostoInput) {
      if (costosSelect && costosSelect.selectedIndex >= 0) {
        resumenCostoInput.value = costosSelect.options[costosSelect.selectedIndex].value;
      }
      resumenCostoInput.removeAttribute('readonly');
    }
    if (grupoMetodoPaquete) {
      grupoMetodoPaquete.classList.add('d-none');
    }
    if (grupoSaldoPaquete) {
      grupoSaldoPaquete.classList.add('d-none');
    }
    if (resumenSaldoPaqueteInput) {
      resumenSaldoPaqueteInput.value = '';
    }
    if (paqueteMetodoSelect) {
      paqueteMetodoSelect.value = '';
    }
  }
}

function handlePaqueteChange() {
  aplicarPaqueteSeleccionado();
}

function handleMontoChange(index, valor) {
  if (!Array.isArray(pagoModalEstado.pagos) || !pagoModalEstado.pagos[index]) {
    return;
  }

  const numero = parseFloat(valor);
  pagoModalEstado.pagos[index].monto = Number.isNaN(numero) || numero < 0 ? 0 : numero;
  actualizarResumenPagos();
}

function handleMetodoChange(index, nuevoMetodo) {
  if (!Array.isArray(pagoModalEstado.pagos) || !pagoModalEstado.pagos[index]) {
    return;
  }

  pagoModalEstado.pagos[index].metodo = nuevoMetodo;

  if (nuevoMetodo === METODO_SALDO) {
    const montoActual = parseFloat(pagoModalEstado.pagos[index].monto);
    if (Number.isNaN(montoActual) || montoActual <= 0) {
      const totales = obtenerTotalesPagos();
      const totalSinActual = totales.total - (Number.isNaN(montoActual) ? 0 : montoActual);
      const faltante = Math.max(0, pagoModalEstado.costo - totalSinActual);
      const montoSugerido = Math.min(pagoModalEstado.saldo, faltante > 0 ? faltante : pagoModalEstado.saldo);
      pagoModalEstado.pagos[index].monto = parseFloat(montoSugerido.toFixed(2));
    }
  }

  renderPagos();
}

function eliminarPago(index) {
  if (!Array.isArray(pagoModalEstado.pagos) || !pagoModalEstado.pagos[index]) {
    return;
  }

  pagoModalEstado.pagos.splice(index, 1);

  if (pagoModalEstado.pagos.length === 0) {
    agregarPagoGenerico();
    return;
  }

  renderPagos();
}

function agregarPagoGenerico(metodo = METODOS_PAGO[0], monto = null) {
  if (!Array.isArray(pagoModalEstado.pagos)) {
    pagoModalEstado.pagos = [];
  }

  const totales = obtenerTotalesPagos();
  let montoInicial = 0;
  if (monto === null) {
    const faltante = Math.max(0, pagoModalEstado.costo - totales.total);
    montoInicial = faltante > 0 ? faltante : 0;
  } else {
    montoInicial = monto;
  }

  pagoModalEstado.pagos.push({
    metodo,
    monto: parseFloat((montoInicial || 0).toFixed(2))
  });

  renderPagos();
}

function inicializarPagos() {
  pagoModalEstado.pagos = [{
    metodo: METODOS_PAGO[0],
    monto: parseFloat(pagoModalEstado.costo.toFixed(2))
  }];
  renderPagos();
}

function renderPagos() {
  if (!tablaPagosBody) {
    return;
  }

  tablaPagosBody.innerHTML = '';

  pagoModalEstado.pagos.forEach((pago, index) => {
    const fila = document.createElement('tr');

    const celdaMetodo = document.createElement('td');
    const selectMetodo = document.createElement('select');
    selectMetodo.className = 'form-select form-select-sm';
    const metodoActual = (pago.metodo || '').trim() || METODOS_PAGO[0];
    const saldoYaAsignado = pagoModalEstado.pagos.some((p, i) => (p.metodo || '').trim() === METODO_SALDO && i !== index);
    [...METODOS_PAGO, METODO_SALDO].forEach((metodo) => {
      if (metodo === METODO_SALDO && saldoYaAsignado && metodoActual !== METODO_SALDO) {
        return;
      }
      const option = document.createElement('option');
      option.value = metodo;
      option.textContent = metodo === METODO_SALDO ? 'Saldo disponible' : metodo;
      if (metodo === metodoActual) {
        option.selected = true;
      }
      selectMetodo.appendChild(option);
    });
    selectMetodo.addEventListener('change', (event) => handleMetodoChange(index, event.target.value));
    celdaMetodo.appendChild(selectMetodo);
    fila.appendChild(celdaMetodo);

    const celdaMonto = document.createElement('td');
    const inputMonto = document.createElement('input');
    inputMonto.type = 'number';
    inputMonto.min = '0';
    inputMonto.step = '0.01';
    inputMonto.className = 'form-control form-control-sm';
    const montoValor = parseFloat(pago.monto);
    inputMonto.value = Number.isNaN(montoValor) ? '0.00' : montoValor.toFixed(2);
    inputMonto.addEventListener('input', (event) => handleMontoChange(index, event.target.value));
    inputMonto.addEventListener('blur', () => {
      const montoActualizado = parseFloat(pagoModalEstado.pagos[index].monto);
      inputMonto.value = Number.isNaN(montoActualizado) ? '0.00' : montoActualizado.toFixed(2);
    });
    celdaMonto.appendChild(inputMonto);
    fila.appendChild(celdaMonto);

    const celdaAcciones = document.createElement('td');
    const btnEliminar = document.createElement('button');
    btnEliminar.type = 'button';
    btnEliminar.className = 'btn btn-link text-danger p-0';
    btnEliminar.textContent = 'Quitar';
    btnEliminar.addEventListener('click', () => eliminarPago(index));
    celdaAcciones.appendChild(btnEliminar);
    fila.appendChild(celdaAcciones);

    tablaPagosBody.appendChild(fila);
  });

  actualizarResumenPagos();
}

if (modalPagoElement) {
  modalPagoElement.addEventListener('hidden.bs.modal', function () {
    pagoModalEstado.pagos = [];
    if (tablaPagosBody) {
      tablaPagosBody.innerHTML = '';
    }
    if (resumenPagos) {
      resumenPagos.textContent = '';
    }
    if (alertaSaldoInsuficiente) {
      alertaSaldoInsuficiente.classList.add('d-none');
    }
    if (agregarPagoSaldoBtn) {
      agregarPagoSaldoBtn.removeAttribute('disabled');
    }
  });
}

if (agregarPagoBtn) {
  agregarPagoBtn.addEventListener('click', () => agregarPagoGenerico());
}

if (agregarPagoSaldoBtn) {
  agregarPagoSaldoBtn.addEventListener('click', () => {
    const totales = obtenerTotalesPagos();
    const faltante = Math.max(0, pagoModalEstado.costo - totales.total);
    let montoSugerido = faltante > 0 ? faltante : pagoModalEstado.saldo;
    montoSugerido = Math.min(pagoModalEstado.saldo, montoSugerido);
    if (montoSugerido <= 0) {
      montoSugerido = Math.min(pagoModalEstado.saldo, pagoModalEstado.costo);
    }
    agregarPagoGenerico(METODO_SALDO, montoSugerido);
  });
}

function actualizarCitaPago(info) {
  if (!modalPagoElement || !info) {
    return;
  }

  pagoModalEstado.idCita = info.idCita || null;
  pagoModalEstado.estatus = info.estatus || null;
  pagoModalEstado.costo = parseFloat(info.costo) || 0;
  pagoModalEstado.saldo = parseFloat(info.saldo) || 0;
  pagoModalEstado.pacienteId = info.pacienteId || null;
  pagoModalEstado.pacienteNombre = info.pacienteNombre || '';
  pagoModalEstado.psicologoId = info.psicologoId || null;
  pagoModalEstado.psicologoNombre = info.psicologoNombre || '';
  pagoModalEstado.tipo = info.tipo || '';
  pagoModalEstado.programado = info.programado || null;
  pagoModalEstado.mensajePago = '';

  if (!pagoModalEstado.modal) {
    pagoModalEstado.modal = new bootstrap.Modal(modalPagoElement, {
      keyboard: false
    });
  }

  if (costoCitaLabel) {
    costoCitaLabel.textContent = formatoMoneda.format(pagoModalEstado.costo);
  }
  if (saldoActualLabel) {
    saldoActualLabel.textContent = formatoMoneda.format(pagoModalEstado.saldo);
  }
  if (pacienteLabel) {
    pacienteLabel.textContent = pagoModalEstado.pacienteNombre || '';
  }
  if (alertaSaldoInsuficiente) {
    alertaSaldoInsuficiente.classList.add('d-none');
  }

  inicializarPagos();

  pagoModalEstado.modal.show();

  const confirmarPago = document.getElementById('confirmarPago');
  if (!confirmarPago) {
    return;
  }

  confirmarPago.onclick = function () {
    if (!Array.isArray(pagoModalEstado.pagos) || pagoModalEstado.pagos.length === 0) {
      alert('Agrega al menos una forma de pago.');
      return;
    }

    const totales = obtenerTotalesPagos();
    const pagosPayload = [];

    for (const pago of pagoModalEstado.pagos) {
      const metodo = (pago.metodo || '').trim();
      const monto = parseFloat(pago.monto);
      if (!metodo) {
        alert('Selecciona una forma de pago válida.');
        return;
      }
      if (Number.isNaN(monto) || monto <= 0) {
        alert('Ingresa montos válidos para cada forma de pago.');
        return;
      }
      pagosPayload.push({
        metodo,
        monto: monto.toFixed(2)
      });
    }

    const faltantePago = Math.max(0, pagoModalEstado.costo - totales.total);
    if (faltantePago > 0.009) {
      const continuarConSaldoPendiente = window.confirm(
        'Faltan ' +
          formatoMoneda.format(faltantePago) +
          ' por cubrir. El saldo pendiente se sumará a próximas citas.\n¿Deseas continuar?'
      );
      if (!continuarConSaldoPendiente) {
        return;
      }
    }

    if (totales.totalSaldo > pagoModalEstado.saldo + 0.0001) {
      alert('El saldo disponible no es suficiente para cubrir el monto asignado.');
      return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'cancelar.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        pagoModalEstado.modal.hide();

        let responseData = null;
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            responseData = JSON.parse(xhr.responseText);
          } catch (error) {
            responseData = null;
          }
        }

        if (responseData && responseData.success) {
          pagoModalEstado.mensajePago = responseData.message || '';
          mostrarModalProximaCita();
        } else {
          if (responseData && responseData.message) {
            alert(responseData.message);
          }
          window.location.reload();
        }
      }
    };

    const params = new URLSearchParams();
    params.append('citaId', pagoModalEstado.idCita);
    params.append('estatus', pagoModalEstado.estatus);
    const metodosResumen = Array.from(totales.metodos);
    if (metodosResumen.length === 1) {
      params.append('formaPago', metodosResumen[0]);
    } else if (metodosResumen.length > 1) {
      params.append('formaPago', 'Mixto (' + metodosResumen.join(', ') + ')');
    }
    params.append('montoPago', totales.total.toFixed(2));
    params.append('pagos', JSON.stringify(pagosPayload));

    xhr.send(params.toString());
  };
}

function prepararModalProximaCita() {
  if (!modalProximaCitaElement) {
    return;
  }

  if (!proximaCitaState.modal) {
    proximaCitaState.modal = new bootstrap.Modal(modalProximaCitaElement, {
      backdrop: 'static',
      keyboard: false
    });

    modalProximaCitaElement.addEventListener('hidden.bs.modal', () => {
      if (proximaCitaState.reloading) {
        return;
      }
      proximaCitaState.reloading = true;
      window.location.reload();
    });
  }
}

function mostrarModalProximaCita() {
  if (!modalProximaCitaElement) {
    window.location.reload();
    return;
  }

  prepararModalProximaCita();
  proximaCitaState.reloading = false;

  if (alertaPagoExitoso) {
    if (pagoModalEstado.mensajePago) {
      alertaPagoExitoso.textContent = pagoModalEstado.mensajePago;
      alertaPagoExitoso.classList.remove('d-none');
    } else {
      alertaPagoExitoso.textContent = '';
      alertaPagoExitoso.classList.add('d-none');
    }
  }

  const fechaMinima = new Date();
  fechaMinima.setSeconds(0, 0);
  if (fechaMinima.getMinutes() !== 0) {
    fechaMinima.setHours(fechaMinima.getHours() + 1);
    fechaMinima.setMinutes(0, 0, 0);
  }

  let fechaSugerida = '';
  if (proximaFechaInput) {
    const fechaProgramada = obtenerFechaDesdeCadena(pagoModalEstado.programado);
    if (fechaProgramada) {
      fechaProgramada.setMinutes(0, 0, 0);
      fechaProgramada.setDate(fechaProgramada.getDate() + 7);
      fechaSugerida = formatearDatetimeLocal(fechaProgramada);
    } else {
      fechaSugerida = formatearDatetimeLocal(fechaMinima);
    }
    proximaFechaInput.min = formatearDatetimeLocal(fechaMinima);
  }

  if (formProximaCita) {
    formProximaCita.reset();
    if (proximaClienteInput) {
      proximaClienteInput.value = pagoModalEstado.pacienteNombre || '';
    }
    if (proximaPsicologoInput) {
      proximaPsicologoInput.value = pagoModalEstado.psicologoNombre || '';
    }
    if (proximaSendIdClienteInput) {
      proximaSendIdClienteInput.value = pagoModalEstado.pacienteId || '';
    }
    if (proximaSendIdPsicologoInput) {
      proximaSendIdPsicologoInput.value = pagoModalEstado.psicologoId || '';
    }
    if (proximaResumenTipoInput) {
      proximaResumenTipoInput.value = pagoModalEstado.tipo || '';
    }
    if (proximaFechaInput) {
      proximaFechaInput.value = fechaSugerida;
      if (proximaFechaInput.value) {
        normalizarHoraCerradaInput(proximaFechaInput);
      }
    }
    if (proximaCostoInput) {
      const costo = Number.isFinite(pagoModalEstado.costo) ? pagoModalEstado.costo : 0;
      proximaCostoInput.value = costo.toFixed(2);
    }
  } else if (proximaFechaInput) {
    proximaFechaInput.value = fechaSugerida;
  }

  if (proximaCitaState.modal) {
    proximaCitaState.modal.show();
  }
}

function agendarProximaCita() {
  if (!formProximaCita) {
    return;
  }

  if (proximaFechaInput) {
    normalizarHoraCerradaInput(proximaFechaInput);
  }

  const clienteId = proximaSendIdClienteInput ? proximaSendIdClienteInput.value : '';
  const psicologoId = proximaSendIdPsicologoInput ? proximaSendIdPsicologoInput.value : '';
  const tipo = proximaResumenTipoInput ? proximaResumenTipoInput.value : '';
  const fechaValor = proximaFechaInput ? proximaFechaInput.value : '';
  const costoValor = proximaCostoInput ? proximaCostoInput.value : '';

  if (!clienteId || !psicologoId || !tipo || !fechaValor || !costoValor) {
    alert('Completa la información de la próxima cita antes de continuar.');
    return;
  }

  const fechaSeleccionada = obtenerFechaDesdeCadena(fechaValor);
  if (!fechaSeleccionada) {
    alert('Selecciona una fecha válida para la próxima cita.');
    return;
  }

  const ahora = new Date();
  if (fechaSeleccionada < ahora) {
    alert('La fecha de la próxima cita debe ser igual o mayor a la fecha actual.');
    return;
  }

  const costoNumerico = parseFloat(costoValor);
  if (!Number.isFinite(costoNumerico) || costoNumerico < 0) {
    alert('Ingresa un costo válido para la próxima cita.');
    return;
  }

  const formData = new FormData(formProximaCita);
  formData.set('sendIdCliente', clienteId);
  formData.set('sendIdPsicologo', psicologoId);
  formData.set('resumenTipo', tipo);
  formData.set('resumenFecha', formatearDatetimeLocal(fechaSeleccionada));
  formData.set('resumenCosto', costoNumerico.toFixed(2));
  formData.append('sendIdPaquete', '');
  formData.append('paqueteMetodo', '');

  fetch('procesar_cita.php', {
    method: 'POST',
    body: formData
  })
    .then((response) => response.json().catch(() => null))
    .then((data) => {
      if (data && data.success) {
        alert('La próxima cita se agendó correctamente.');
        proximaCitaState.reloading = true;
        if (proximaCitaState.modal) {
          proximaCitaState.modal.hide();
        }
        window.location.reload();
      } else {
        alert(data && data.message ? data.message : 'No fue posible agendar la próxima cita.');
      }
    })
    .catch(() => {
      alert('No fue posible agendar la próxima cita.');
    });
}
document.addEventListener('click', (event) => {
  const target = event.target instanceof Element ? event.target.closest('.pagar-cita-btn') : null;
  if (!target) {
    return;
  }

  const payload = target.getAttribute('data-pago');
  if (!payload) {
    return;
  }

  try {
    const info = JSON.parse(payload);
    actualizarCitaPago(info);
  } catch (error) {
    console.error('No fue posible preparar el pago de la cita.', error);
  }
});

if (confirmarProximaCitaBtn) {
  confirmarProximaCitaBtn.addEventListener('click', agendarProximaCita);
}

if (omitirProximaCitaBtn) {
  omitirProximaCitaBtn.addEventListener('click', () => {
    proximaCitaState.reloading = true;
    if (proximaCitaState.modal) {
      proximaCitaState.modal.hide();
    }
    window.location.reload();
  });
}

if (proximaFechaInput) {
  proximaFechaInput.addEventListener('change', () => normalizarHoraCerradaInput(proximaFechaInput));
  proximaFechaInput.addEventListener('blur', () => normalizarHoraCerradaInput(proximaFechaInput));
}

function enviarFormularioJSON() {
const form = document.getElementById('formCita');
const formData = new FormData(form);

fetch('procesar_cita.php', {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    alert('Cita guardada con éxito');
    window.location.href = 'index.php'; // o mostrar modal de éxito
  } else {
    alert('Error: ' + data.message);
  }
})
.catch(error => {
  alert('Error en el servidor: ' + error.message);
});
}

function actualizarCita(idCita, estatus) {
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "cancelar.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      location.reload();
      //alert(xhr.responseText);
      // Puedes redirigir o realizar otras acciones aquí
    }
  };

    const params = `citaId=${idCita}&estatus=${estatus}`;
    xhr.send(params);

}
function finalizarCita(idCita) {
  if (!idCita) {
    return;
  }

  if (!confirm('¿Deseas finalizar esta cita?')) {
    return;
  }

  const params = new URLSearchParams();
  params.append('citaId', idCita);

  fetch('finalizar_cita.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: params.toString()
  })
    .then(response => response.json().catch(() => ({ success: false, message: 'Respuesta no válida del servidor.' })))
    .then(data => {
      if (data && data.success) {
        location.reload();
      } else {
        alert((data && data.message) ? data.message : 'No fue posible finalizar la cita.');
      }
    })
    .catch(() => {
      alert('No fue posible finalizar la cita.');
    });
}
function Reprogramar(citaId) {
  // Asigna el ID de la cita al campo del formulario
  document.getElementById('citaId').value = citaId;
  const fechaProgramadaInput = document.getElementById('fechaProgramada');
  if (fechaProgramadaInput) {
    fechaProgramadaInput.value = '';
  }

  const modalTitle = document.getElementById('updateModalLabel');
  const submitButton = document.getElementById('updateSubmitButton');
  const aviso = document.getElementById('solicitudAviso');

  if (ES_VENTAS) {
    if (modalTitle) modalTitle.textContent = 'Solicitar reprogramación';
    if (submitButton) submitButton.textContent = 'Enviar solicitud';
    if (aviso) aviso.style.display = 'block';
  } else {
    if (modalTitle) modalTitle.textContent = 'Actualizar Fecha de Cita';
    if (submitButton) submitButton.textContent = 'Actualizar';
    if (aviso) aviso.style.display = 'none';
  }

  // Abre el modal
  var updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
  updateModal.show();
}
function validarFormulario(event) {
  event.preventDefault(); // Evita el envío del formulario

  // Obtener valores de los campos
  const cliente = document.getElementById('resumenCliente').value;
  const psicologo = document.getElementById('resumenPsicologo').value;
  const tipo = document.getElementById('resumenTipo').value;
  const costo = document.getElementById('resumenCosto').value;
  const fecha = document.getElementById('resumenFecha').value;

  // Validar que los campos no estén vacíos
  if (!cliente || !psicologo || !tipo || !costo || !fecha) {
    alert('Todos los campos son obligatorios.');
    return false;
  }

  // Validar que la fecha de la cita sea igual o mayor a la fecha actual
  const fechaCita = new Date(fecha);
  const fechaActual = new Date();

  if (fechaCita < fechaActual) {
    alert('La fecha de la cita debe ser igual o mayor a la fecha actual.');
    return false;
  }

  const paqueteSeleccionado = sendIdPaqueteInput ? sendIdPaqueteInput.value : '';
  if (paqueteSeleccionado) {
    const metodoPaquete = paqueteMetodoSelect ? paqueteMetodoSelect.value : '';
    if (metodoPaquete !== 'Efectivo' && metodoPaquete !== 'Transferencia') {
      alert('Selecciona la forma de pago para el paquete (efectivo o transferencia).');
      return false;
    }
  }

  // Si todo es válido, enviar el formulario
enviarFormularioJSON();

}


function updateResumen() {
  const nameSelect = document.getElementById('nameSelect');
  const idEmpleado = document.getElementById('idEmpleado');
  const costosSelect = document.getElementById('costosSelect');
  const citaDia = document.getElementById('citaDia');
  const resumenCostoInput = document.getElementById('resumenCosto');

  if (nameSelect && nameSelect.selectedIndex >= 0) {
    document.getElementById('resumenCliente').value = nameSelect.options[nameSelect.selectedIndex].text;
    document.getElementById('sendIdCliente').value = nameSelect.options[nameSelect.selectedIndex].value;
  }

  if (idEmpleado && idEmpleado.selectedIndex >= 0) {
    document.getElementById('resumenPsicologo').value = idEmpleado.options[idEmpleado.selectedIndex].text;
    document.getElementById('sendIdPsicologo').value = idEmpleado.options[idEmpleado.selectedIndex].value;
  }

  if (costosSelect && costosSelect.selectedIndex >= 0) {
    const textoOpcion = costosSelect.options[costosSelect.selectedIndex].text;
    const textoLimpio = textoOpcion.replace(/[0-9:$.]/g, '').trim();
    document.getElementById('resumenTipo').value = textoLimpio;
    if (resumenCostoInput && !(paqueteSelect && paqueteSelect.value)) {
      resumenCostoInput.value = costosSelect.options[costosSelect.selectedIndex].value;
    }
  }

  if (citaDia) {
    document.getElementById('resumenFecha').value = citaDia.value;
  }

  aplicarPaqueteSeleccionado();
  revisarCita();
}
function loadAll() {
  loadCostos();
  loadNames();
  loadEmpleados();
  loadPaquetes();

}
function loadCostos() {
  const xhr = new XMLHttpRequest();
  xhr.open('GET', 'Modulos/getPrecios.php', true);
  xhr.onload = function () {
    if (this.status === 200) {
      var jsonString = this.responseText;
      var jsonItems = jsonString.match(/({.*?})/g);

      // Convertir cada cadena JSON en un objeto JavaScript
      var items = jsonItems.map(function (item) {
        return JSON.parse(item);
      });
      items.forEach(function (item) {
        $('#costosSelect').append($('<option>', {
          value: item.costo,
          text: item.name
        }));
      });
      /*                     let names = JSON.parse(this.responseText);
                let options = '<option value="">Seleccione un nombre</option>';
                names.forEach(function (id,name) {
                    options += '<option value="${name}">${name}</option>';
                });
                document.getElementById('nameSelect').innerHTML = options; */
    }
  };
  xhr.send();
}

function loadPaquetes() {
  if (!paqueteSelect) {
    return;
  }

  fetch('Modulos/getPaquetes.php')
    .then((response) => {
      if (!response.ok) {
        throw new Error('Error al cargar paquetes');
      }
      return response.json();
    })
    .then((paquetes) => {
      paquetesDisponibles.clear();
      paqueteSelect.innerHTML = '<option value="">Sin paquete</option>';

      if (Array.isArray(paquetes)) {
        paquetes.forEach((paquete) => {
          const idCadena = String(paquete.id);
          const paqueteNormalizado = {
            id: idCadena,
            nombre: paquete.nombre,
            primer_pago_monto: parseFloat(paquete.primer_pago_monto),
            saldo_adicional: parseFloat(paquete.saldo_adicional),
          };
          paquetesDisponibles.set(idCadena, paqueteNormalizado);

          const option = document.createElement('option');
          option.value = idCadena;
          option.textContent = `${paqueteNormalizado.nombre} - pago ${formatoMoneda.format(paqueteNormalizado.primer_pago_monto)} / saldo ${formatoMoneda.format(paqueteNormalizado.saldo_adicional)}`;
          paqueteSelect.appendChild(option);
        });
      }

      aplicarPaqueteSeleccionado();
    })
    .catch(() => {
      paquetesDisponibles.clear();
      paqueteSelect.innerHTML = '<option value="">Sin paquete</option>';
      aplicarPaqueteSeleccionado();
    });
}
function loadEmpleados() {
  const xhr = new XMLHttpRequest();
  xhr.open('GET', 'Modulos/getPsicologos.php', true);
  xhr.onload = function () {
    if (this.status === 200) {
      var jsonString = this.responseText;
      var jsonItems = jsonString.match(/({.*?})/g);

      // Convertir cada cadena JSON en un objeto JavaScript
      var items = jsonItems.map(function (item) {
        return JSON.parse(item);
      });
      items.forEach(function (item) {
        $('#idEmpleado').append($('<option>', {
          value: item.id,
          text: item.name
        }));
      });

    }
  };
  xhr.send();

}

function loadNames() {
  const xhr = new XMLHttpRequest();
  xhr.open('GET', 'get_names.php', true);
  xhr.onload = function () {
    if (this.status === 200) {
      var jsonString = this.responseText;
      var jsonItems = jsonString.match(/({.*?})/g);

      // Convertir cada cadena JSON en un objeto JavaScript
      var items = jsonItems.map(function (item) {
        return JSON.parse(item);
      });
      items.forEach(function (item) {
        $('#nameSelect').append($('<option>', {
          value: item.id,
          text: item.name
        }));
      });
      /*                     let names = JSON.parse(this.responseText);
                let options = '<option value="">Seleccione un nombre</option>';
                names.forEach(function (id,name) {
                    options += '<option value="${name}">${name}</option>';
                });
                document.getElementById('nameSelect').innerHTML = options; */
    }
  };
  xhr.send();

}

function nini(id) {
  $("#nameSelect").val(id);
  updateResumen();
}
function searchNino() {

  const search = document.getElementById('search').value;

  const xhr = new XMLHttpRequest();
  xhr.open('GET', `search.php?search=${search}`, true);
  xhr.onload = function () {
    if (this.status === 200) {
      document.getElementById('results').innerHTML = this.responseText;
    }
  };
  xhr.send();

  return false; // Evitar el envío del formulario
}

window.onload = loadAll;

const citaDiaInput = document.getElementById('citaDia');
if (citaDiaInput) {
  const fechaMinima = new Date();
  fechaMinima.setSeconds(0, 0);
  if (fechaMinima.getMinutes() !== 0) {
    fechaMinima.setHours(fechaMinima.getHours() + 1);
    fechaMinima.setMinutes(0, 0, 0);
  }

  citaDiaInput.min = formatearDatetimeLocal(fechaMinima);
  citaDiaInput.step = 3600;
  citaDiaInput.addEventListener('change', () => {
    normalizarHoraCerradaInput(citaDiaInput);
    updateResumen();
  });
  citaDiaInput.addEventListener('blur', () => normalizarHoraCerradaInput(citaDiaInput));
  normalizarHoraCerradaInput(citaDiaInput);
}

const fechaProgramadaInput = document.getElementById('fechaProgramada');
if (fechaProgramadaInput) {
  const fechaMinimaReprogramacion = new Date();
  fechaMinimaReprogramacion.setSeconds(0, 0);
  if (fechaMinimaReprogramacion.getMinutes() !== 0) {
    fechaMinimaReprogramacion.setHours(fechaMinimaReprogramacion.getHours() + 1);
    fechaMinimaReprogramacion.setMinutes(0, 0, 0);
  }

  fechaProgramadaInput.min = formatearDatetimeLocal(fechaMinimaReprogramacion);
  fechaProgramadaInput.step = 3600;
  fechaProgramadaInput.addEventListener('change', () => normalizarHoraCerradaInput(fechaProgramadaInput));
  fechaProgramadaInput.addEventListener('blur', () => normalizarHoraCerradaInput(fechaProgramadaInput));
}
$(document).ready(function () {
  document.getElementById('idResultado').style.display = "none";
  $('#myTable').DataTable({
    language: {
      lengthMenu: 'Número de filas _MENU_',
      zeroRecords: 'No encontró nada, usa los filtros para pulir la busqueda',
      info: 'Página _PAGE_ de _PAGES_',
      search: 'Buscar:',
      paginate: {
        first: 'Primero',
        last: 'Ultimo',
        next: 'Siguiente',
        previous: 'Previo'
      },
      infoEmpty: 'No hay registros disponibles',
      infoFiltered: '(Buscamos en _MAX_ resultados)',
    },
  });
});
function revisarCita() {
  var idUsuario = document.getElementById('sendIdPsicologo').value;
  var resumenFecha = document.getElementById('resumenFecha').value;
  console.log(idUsuario, resumenFecha)
  var formData = new FormData();
  formData.append('IdUsuario', idUsuario);
  formData.append('resumenFecha', resumenFecha);

  fetch('Modulos/validarCita.php', {
    method: 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      var resultadoDiv = document.getElementById('resultado');
      var resultadoDivGeneral = document.getElementById('idResultado');
      resultadoDiv.innerHTML = '';

      if (data.success) {
        resultadoDivGeneral.style.display = "none";
        resultadoDiv.innerHTML = 'La cita es válida.';
      } else {
        resultadoDivGeneral.style.display = "block";
        resultadoDiv.innerHTML = ' <p class="card-category">' + data.message + '</p>';
        if (data.citas && data.citas.length > 0) {
          data.citas.forEach(function (cita) {
            resultadoDiv.innerHTML += 'Fecha: ' + cita.fecha + ' - Niño: ' + cita.name + '<br>';
          });
        }
      }
    })
    .catch(error => console.error('Error:', error));
};
