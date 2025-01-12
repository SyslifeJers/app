<?php

include 'Modulos/head.php';
?>


<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        <h4 class="card-title">Citas</h4>
      </div>
      <div class="card-body">
        <div class="table-responsive">

          <?php
          // Configuración de la conexión a la base de datos
          $db_host = 'localhost';
          $db_name = 'clini234_cerene';
          $db_user = 'clini234_cerene';
          $db_pass = 'tu{]ScpQ-Vcg';

          // Crear conexión
          $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
          $conn->set_charset("utf8");
          // Verificar la conexión
          if ($conn->connect_error) {
            die("Conexión fallida: " . $conn->connect_error);
          }

          // Consulta SQL
          $sql = "SELECT ci.id, 
       n.name, 
       us.name as Psicologo, 
       ci.costo, 
       ci.Programado, 
       DATE(ci.Programado) as Fecha, 
       TIME(ci.Programado) as Hora, 
       ci.Tipo, 
       es.name as Estatus
FROM Cita ci
INNER JOIN nino n ON n.id = ci.IdNino
INNER JOIN Usuarios us ON us.id = ci.IdUsuario
INNER JOIN Estatus es ON es.id = ci.Estatus
WHERE ci.Estatus = 2 OR ci.Estatus = 3
ORDER BY ci.Programado ASC;";

          $result = $conn->query($sql);
          date_default_timezone_set('America/Mexico_City');
          $hoy = date('Y-m-d');
          echo $hoy;
          // Verificar si hay resultados y generar la tabla HTML
          if ($result->num_rows > 0) {
            echo "<table border='1' id=\"myTable\" >
	    <thead>
            <tr>
                <th>Fecha</th>
                <th>Id</th>
                <th>Paciente</th>
                <th>Psicólogo</th>
                <th>Costo</th>
                
                <th>Hora</th>
                <th>Tipo</th>
                <th>Estatus</th>
                <th>Opciones</th>
            </tr>    </thead>
			<tbody>";
            // Recorrer los resultados y mostrarlos en la tabla
          
            while ($row = $result->fetch_assoc()) {
              echo "
		<tr>
                    <td>" . $row["Fecha"] . "</td>
                <td>" . $row["id"] . "</td>
                <td>" . $row["name"] . "</td>
                <td>" . $row["Psicologo"] . "</td>
                <td>" . $row["costo"] . "</td>

                <td>" . $row["Hora"] . "</td>
                <td>" . $row["Tipo"] . "</td>
                <td>" . $row["Estatus"] . "</td>
                <td>
					<button class=\"btn btn-primary btn-sm\" onclick=\"Reprogramar(" . $row['id'] . ")\">Reprogramar</button>
					<button class=\"btn btn-danger btn-sm\" onclick=\"actualizarCita(" . $row['id'] . ",1)\">Cancelar</button> ";
              if (date('Y-m-d', strtotime($row["Fecha"])) == $hoy && ($row["Estatus"] == "Creada" || $row["Estatus"] == "Reprogramado")) {
                echo "<button class=\"btn btn-success btn-sm\" onclick=\" actualizarCitaPago(" . $row['id'] . ",4)\">Pagar</button>";
              }
              echo "
				</td>
              </tr>";
            }
            echo "</tbody></table>";
          } else {
            echo "0 resultados";
          }

          // Cerrar conexión
          $conn->close();
          ?>
        </div>
      </div>
    </div>
  </div>
</div>







<hr>
<div class="row ">
  <h2>Generar cita</h2>
  <div class="col-12 col-sm-6 col-md-6 col-xl-4 ">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Cliente</h3>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-12 col-lg-12">
            <form onsubmit="return searchNino();">
              <div class="form-group">
                <label for="nameSelect">Lista</label>
                <select class="form-select" name="nameSelect" id="nameSelect" onchange="updateResumen()">
                  <!-- Opciones se llenarán dinámicamente desde get_names.php -->
                </select>
                <hr>
                <label for="search">Buscar</label>
                <div class="input-group">
                  <!-- Campo de búsqueda -->
                  <input class="form-control" type="text" name="search" id="search" placeholder="Buscar por nombre">
                  <button class="btn btn-black btn-border" type="submit">Buscar</button>
                </div>
              </div>
            </form>
            <div id="results"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-6 col-xl-4">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Fecha y tipo de cita</h3>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-12 col-lg-12">
            <div class="form-group">
              <label for="idEmpleado">Psicólogos</label>
              <select name="empleado" id="idEmpleado" class="form-select" onchange="updateResumen()">
                <!-- Opciones se llenarán dinámicamente desde get_names.php -->
              </select>
            </div>
            <div class="form-group">
              <label for="costosSelect"> Tipo</label>
              <select name="costos" id="costosSelect" class="form-select" onchange="updateResumen()">
                <!-- Opciones se llenarán dinámicamente desde get_names.php -->
              </select>
            </div>
            <div class="form-group">
              <label for="citaDia"> Día de la cita</label>
              <input type="datetime-local" id="citaDia" name="citaDia" class="form-select" onchange="updateResumen()">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-6 col-xl-4">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Resumen de Cita</h3>
        </div>
      </div>
      <div class="card-body">
        <form id="formCita" action="procesar_cita.php" method="POST" onsubmit="validarFormulario(event)">
          <div class="row">
            <div class="col-md-12 col-lg-12">
              <div class="form-group">
                <label for="resumenCliente">Cliente</label>
                <input type="text" name="resumenCliente" id="resumenCliente" class="form-control" readonly>
                <input type="text" name="sendIdCliente" id="sendIdCliente" class="form-control" hidden readonly>
              </div>
              <div class="form-group">
                <label for="resumenPsicologo">Psicólogo</label>
                <input type="text" name="resumenPsicologo" id="resumenPsicologo" class="form-control" readonly>
                <input type="text" name="sendIdPsicologo" id="sendIdPsicologo" class="form-control" hidden readonly>
              </div>
              <div class="form-group">
                <label for="resumenTipo">Tipo</label>
                <input type="text" name="resumenTipo" id="resumenTipo" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="resumenCosto">Costo</label>
                <input type="number" name="resumenCosto" id="resumenCosto" class="form-control" >
              </div>
              <div class="form-group">
                <label for="resumenFecha">Fecha de la Cita</label>
                <input type="datetime-local" name="resumenFecha" id="resumenFecha" class="form-control" readonly>
              </div>
              <button type="submit" class="btn btn-primary mt-3">Guardar Cita</button>
              <hr>
              <div class="card card-stats card-round" id="idResultado">
                <div class="card-body">
                  <div class="row">
                    <div class="col-2">
                      <div class="icon-big text-center">
                        <i class="icon-close text-danger"></i>
                      </div>
                    </div>
                    <div class="col-10 col-stats">
                      <div class="numbers">

                        <div id="resultado"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="ModalTipoPago" tabindex="-1" aria-labelledby="ModalTipoPagoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ModalTipoPagoLabel">Seleccione el tipo de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <select id="tipoPago" class="form-select">
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Tarjeta">Tarjeta</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmarPago">Confirmar</button>
                </div>
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
        <form id="updateForm" method="post" action="update.php">
          <div class="mb-3">
            <label for="citaId" class="form-label">ID de la Cita</label>
            <input type="text" class="form-control" id="citaId" name="citaId" readonly>
          </div>
          <div class="mb-3">
            <label for="fechaProgramada" class="form-label">Nueva Fecha Programada</label>
            <input type="datetime-local" class="form-control" id="fechaProgramada" name="fechaProgramada" required>
          </div>
          <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
include 'Modulos/footer.php';
?>

<script>
  function actualizarCitaPago(idCita, estatus) {
    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('ModalTipoPago'), {
        keyboard: false
    });
    modal.show();

    // Manejar la confirmación del pago
    document.getElementById('confirmarPago').onclick = function () {
        const tipoPago = document.getElementById('tipoPago').value;

        // Realizar la solicitud AJAX con el tipo de pago
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "cancelar.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                location.reload();
                // Puedes redirigir o realizar otras acciones aquí
            }
        };

        const params = `citaId=${idCita}&estatus=${estatus}&formaPago=${tipoPago}`;
        xhr.send(params);

        // Ocultar el modal
        modal.hide();
    };
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
  function Reprogramar(citaId) {
    // Asigna el ID de la cita al campo del formulario
    document.getElementById('citaId').value = citaId;
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

    // Si todo es válido, enviar el formulario
    document.getElementById('formCita').submit();
  }
  function updateResumen() {
    const nameSelect = document.getElementById('nameSelect');
    const idEmpleado = document.getElementById('idEmpleado');
    const costosSelect = document.getElementById('costosSelect');
    const citaDia = document.getElementById('citaDia');

    document.getElementById('resumenCliente').value = nameSelect.options[nameSelect.selectedIndex].text;
    document.getElementById('sendIdCliente').value = nameSelect.options[nameSelect.selectedIndex].value;
    document.getElementById('resumenPsicologo').value = idEmpleado.options[idEmpleado.selectedIndex].text;
    document.getElementById('sendIdPsicologo').value = idEmpleado.options[idEmpleado.selectedIndex].value;
    var texts = costosSelect.options[costosSelect.selectedIndex].text;
    var textoLimpio = texts.replace(/[0-9:$.]/g, '');
    document.getElementById('resumenTipo').value = textoLimpio;
    document.getElementById('resumenCosto').value = costosSelect.options[costosSelect.selectedIndex].value;
    document.getElementById('resumenFecha').value = citaDia.value;

    revisarCita();
  }
  function loadAll() {
    loadCostos();
    loadNames();
    loadEmpleados();

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

  // Crear una nueva fecha que sea mañana
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate());

  // Formatear la fecha en el formato adecuado para datetime-local
  const year = tomorrow.getFullYear();
  const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
  const day = String(tomorrow.getDate()).padStart(2, '0');
  const hours = String(tomorrow.getHours()).padStart(2, '0');
  const minutes = String(tomorrow.getMinutes()).padStart(2, '0');

  // Asignar la fecha mínima al input
  const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
  citaDiaInput.min = minDateTime;
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
</script>