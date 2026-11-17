/**
 * preregistro/js/preregistro.js
 * Club Pitaya — Lógica del formulario de preregistro público.
 *
 * Variables globales inyectadas desde index.php:
 *   PREREGISTRO_TOKEN  — token HMAC para validar peticiones AJAX
 *   MEMBRESIA_NUM      — número de membresía (informativo)
 */

/* ─── Estado interno ────────────────────────────────────────────────────────── */
let _datosIA  = null;   // Datos extraídos por Gemini Vision
let _imagenB64 = null;  // Base64 de la imagen cargada
let _imagenMime = 'image/jpeg';

/* ─── DOMContentLoaded ───────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

    const inputFoto     = document.getElementById('inputFotoCedula');
    const zonaUpload    = document.getElementById('zonaUpload');
    const previewImg    = document.getElementById('previewCedula');
    const uploadPrompt  = document.getElementById('uploadPrompt');
    const btnAnalizar   = document.getElementById('btnAnalizarCedula');
    const estadoAnalisis = document.getElementById('estadoAnalisis');
    const seccionDatos  = document.getElementById('seccionDatos');
    const btnGuardar    = document.getElementById('btnGuardarPerfil');
    const btnRetomar    = document.getElementById('btnRetomar');

    // ── Cargar imagen ─────────────────────────────────────────────────────────
    inputFoto.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Validar tamaño (máx 8 MB)
        if (file.size > 8 * 1024 * 1024) {
            _setEstado('⚠️ La imagen es demasiado grande (máx 8 MB).', 'danger');
            return;
        }

        _imagenMime = file.type || 'image/jpeg';
        const reader = new FileReader();
        reader.onload = function (e) {
            const dataUrl = e.target.result;
            // Extraer solo la parte base64 (sin el prefijo data:image/...;base64,)
            _imagenB64 = dataUrl.split(',')[1] || '';

            // Mostrar preview
            previewImg.src = dataUrl;
            previewImg.classList.remove('d-none');
            uploadPrompt.classList.add('d-none');

            btnAnalizar.classList.remove('d-none');
            _setEstado('', '');
            _datosIA = null;
            seccionDatos.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    });

    // ── Drag & Drop ───────────────────────────────────────────────────────────
    zonaUpload.addEventListener('dragover', function (e) {
        e.preventDefault();
        zonaUpload.classList.add('drag-over');
    });
    zonaUpload.addEventListener('dragleave', function () {
        zonaUpload.classList.remove('drag-over');
    });
    zonaUpload.addEventListener('drop', function (e) {
        e.preventDefault();
        zonaUpload.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            inputFoto.files = dt.files;
            inputFoto.dispatchEvent(new Event('change'));
        }
    });

    // ── Analizar con IA ───────────────────────────────────────────────────────
    btnAnalizar.addEventListener('click', function () {
        if (!_imagenB64) return;

        btnAnalizar.disabled = true;
        btnAnalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analizando...';
        _setEstado('Procesando imagen con IA...', 'info');

        const formData = new FormData();
        formData.append('t',      PREREGISTRO_TOKEN);
        formData.append('imagen', _imagenB64);
        formData.append('mime',   _imagenMime);

        fetch('ajax/analizar_cedula.php', {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btnAnalizar.disabled = false;
            btnAnalizar.innerHTML = '<i class="bi bi-cpu me-2"></i>Analizar con IA';

            if (res && res.success && res.datos) {
                _datosIA = res.datos;
                _mostrarDatos(res.datos);
                seccionDatos.classList.remove('d-none');
                _setEstado('✅ Datos extraídos correctamente. Revisa y guarda.', 'success');
                seccionDatos.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                const msg = (res && res.mensaje) ? res.mensaje : 'No se pudieron extraer los datos.';
                _setEstado('❌ ' + msg + ' — Intenta con una foto más clara.', 'danger');
            }
        })
        .catch(function () {
            btnAnalizar.disabled = false;
            btnAnalizar.innerHTML = '<i class="bi bi-cpu me-2"></i>Analizar con IA';
            _setEstado('❌ Error de conexión. Intenta nuevamente.', 'danger');
        });
    });

    // ── Volver a tomar foto ───────────────────────────────────────────────────
    if (btnRetomar) {
        btnRetomar.addEventListener('click', function () {
            _datosIA   = null;
            _imagenB64 = null;
            previewImg.src = '';
            previewImg.classList.add('d-none');
            uploadPrompt.classList.remove('d-none');
            btnAnalizar.classList.add('d-none');
            seccionDatos.classList.add('d-none');
            _setEstado('', '');
            inputFoto.value = '';
        });
    }

    // ── Guardar perfil ────────────────────────────────────────────────────────
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function () {
            if (!_datosIA) {
                _mostrarAlertaError('Primero analiza la foto de tu cédula.');
                return;
            }

            const nombre   = (_datosIA.nombre   || '').trim();
            const apellido = (_datosIA.apellido  || '').trim();
            const cedula   = (_datosIA.cedula    || '').trim();
            const fechaNac = (_datosIA.fecha_nacimiento || '').trim();
            const correo   = (document.getElementById('inputCorreo').value || '').trim();

            if (!nombre || !apellido || !cedula || !fechaNac) {
                _mostrarAlertaError('No se pudieron extraer todos los datos de la cédula. Intenta con una foto más clara.');
                return;
            }

            if (correo && !_validarEmail(correo)) {
                _mostrarAlertaError('El correo electrónico no es válido.');
                return;
            }

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            document.getElementById('alertaError').classList.add('d-none');

            fetch('ajax/completar_registro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    t:                 PREREGISTRO_TOKEN,
                    nombre:            nombre,
                    apellido:          apellido,
                    cedula:            cedula,
                    fecha_nacimiento:  fechaNac,
                    correo:            correo
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success) {
                    // Mostrar pantalla de éxito
                    document.querySelector('main').innerHTML = `
                        <div class="card border-0 shadow-sm rounded-4 text-center p-5 mt-3">
                            <div class="fs-1 mb-3">🎉</div>
                            <h4 class="fw-bold text-success">¡Perfil completado!</h4>
                            <p class="text-muted">${_escHtml(res.mensaje || '¡Bienvenido al Club Pitaya!')}</p>
                            <p class="text-muted small mt-2">Ya puedes disfrutar todos los beneficios de tu membresía <strong>#${MEMBRESIA_NUM}</strong>.</p>
                        </div>`;
                } else {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Guardar mi perfil';
                    const msg = (res && res.mensaje) ? res.mensaje : 'Error al guardar. Intenta nuevamente.';
                    _mostrarAlertaError(msg);
                }
            })
            .catch(function () {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Guardar mi perfil';
                _mostrarAlertaError('Error de conexión. Intenta nuevamente.');
            });
        });
    }
});

/* ─── Helpers ─────────────────────────────────────────────────────────────── */
function _mostrarDatos(datos) {
    _setField('dispNombre',   'hidNombre',   datos.nombre);
    _setField('dispApellido', 'hidApellido', datos.apellido);
    _setField('dispCedula',   'hidCedula',   datos.cedula);
    _setField('dispFechaNac', 'hidFechaNac', _formatearFecha(datos.fecha_nacimiento));
    // El valor oculto siempre en YYYY-MM-DD
    const hidFec = document.getElementById('hidFechaNac');
    if (hidFec) hidFec.value = datos.fecha_nacimiento || '';
}

function _setField(dispId, hidId, valor) {
    const disp = document.getElementById(dispId);
    const hid  = document.getElementById(hidId);
    if (disp) disp.textContent = valor || '—';
    if (hid)  hid.value = valor || '';
}

function _formatearFecha(iso) {
    if (!iso) return null;
    const partes = iso.split('-');
    if (partes.length !== 3) return iso;
    return partes[2] + '/' + partes[1] + '/' + partes[0]; // DD/MM/YYYY para mostrar
}

function _setEstado(msg, tipo) {
    const el = document.getElementById('estadoAnalisis');
    if (!el) return;
    el.textContent = msg;
    el.className = 'mt-2 small text-center';
    if (tipo === 'danger')  el.classList.add('text-danger');
    if (tipo === 'success') el.classList.add('text-success');
    if (tipo === 'info')    el.classList.add('text-muted');
}

function _mostrarAlertaError(msg) {
    const el = document.getElementById('alertaError');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function _validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function _escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
