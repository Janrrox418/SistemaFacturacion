<?php
session_start();

// Si el proveedor ya está logueado redirige a la página principal
if (isset($_SESSION['proveedor_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../config/sap.php';
require_once '../services/SapApiService.php';
$error = '';
$sociedades = [];
$sociedades_error = '';
$proveedor_sap_id = is_string($_POST['proveedor_sap_id'] ?? null)
    ? trim($_POST['proveedor_sap_id'])
    : '';
$sociedad_seleccionada = is_string($_POST['bukrs'] ?? null)
    ? trim($_POST['bukrs'])
    : '';
$sociedad_seleccionada_en_lista = false;
$is_list = static fn(array $values): bool => $values === []
    || array_keys($values) === range(0, count($values) - 1);

try {
    $sociedades_data = (new SapApiService(
        SAP_API_URL,
        SAP_API_USERNAME,
        SAP_API_PASSWORD
    ))->get([
        'sap-client' => SAP_API_CLIENT,
        'TIPO' => 'LOGIN_SOC',
    ]);

    if (!is_array($sociedades_data) || !$is_list($sociedades_data)) {
        throw new UnexpectedValueException('SAP devolvió un formato inválido para la lista de sociedades.');
    }

    foreach ($sociedades_data as $sociedad_sap) {
        if (
            !is_array($sociedad_sap)
            || !isset($sociedad_sap['BUKRS'], $sociedad_sap['BUTXT'])
            || !is_scalar($sociedad_sap['BUKRS'])
            || !is_scalar($sociedad_sap['BUTXT'])
            || trim((string) $sociedad_sap['BUKRS']) === ''
        ) {
            throw new UnexpectedValueException('SAP devolvió una sociedad con datos inválidos.');
        }

        $codigo_sociedad = (string) $sociedad_sap['BUKRS'];
        $sociedades[$codigo_sociedad] = (string) $sociedad_sap['BUTXT'];
    }
    $_SESSION['sap_login_sociedades'] = array_fill_keys(array_keys($sociedades), true);
} catch (SapApiException $e) {
    error_log('SAP company list request failed: ' . $e->getMessage());
    $sociedades_error = 'No fue posible cargar las sociedades desde SAP.';
    unset($_SESSION['sap_login_sociedades']);
} catch (InvalidArgumentException $e) {
    error_log('SAP company list configuration is invalid: ' . $e->getMessage());
    $sociedades_error = 'La configuración de SAP no es válida.';
    unset($_SESSION['sap_login_sociedades']);
} catch (JsonException $e) {
    error_log('SAP company list response was not valid JSON: ' . $e->getMessage());
    $sociedades_error = 'SAP devolvió una respuesta inválida para la lista de sociedades.';
    unset($_SESSION['sap_login_sociedades']);
} catch (UnexpectedValueException $e) {
    error_log($e->getMessage());
    $sociedades_error = 'SAP devolvió una respuesta inválida para la lista de sociedades.';
    unset($_SESSION['sap_login_sociedades']);
} catch (RuntimeException $e) {
    error_log('SAP company list request failed: ' . $e->getMessage());
    $sociedades_error = 'No fue posible contactar el servicio SAP para cargar las sociedades.';
    unset($_SESSION['sap_login_sociedades']);
}

if (isset($sociedades[$sociedad_seleccionada])) {
    $sociedad_seleccionada_en_lista = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $clave_estandar = is_string($_POST['clave_estandar'] ?? null)
        ? trim($_POST['clave_estandar'])
        : '';

    if ($sociedades_error !== '') {
        $error = $sociedades_error;
    } elseif (!$sociedad_seleccionada_en_lista) {
        $error = "Seleccione una sociedad válida.";
    } elseif (!preg_match('/^\d{1,10}$/', $proveedor_sap_id) || $clave_estandar === "") {
        $error = "Ingrese su número de proveedor (solo números, máximo 10 dígitos) y la clave estándar.";
    } else {
        try {
            $sap_data = (new SapApiService(
                SAP_API_URL,
                SAP_API_USERNAME,
                SAP_API_PASSWORD
            ))->get([
                'sap-client' => SAP_API_CLIENT,
                'TIPO' => 'LOGIN_LISTA',
                'BUKRS' => $sociedad_seleccionada,
                'BUSQUEDA' => $proveedor_sap_id,
            ]);

            if (!is_array($sap_data) || !$is_list($sap_data)) {
                $error = "SAP devolvió una respuesta inválida al validar el proveedor.";
            } else {
                $proveedor_sap_encontrado = null;
                foreach ($sap_data as $entry) {
                    if (
                        !is_array($entry)
                        || !isset($entry['LIFNR'], $entry['NAME1'])
                        || !is_scalar($entry['LIFNR'])
                        || !is_scalar($entry['NAME1'])
                    ) {
                        throw new UnexpectedValueException('SAP devolvió un proveedor con datos inválidos.');
                    }

                    $entry_lifnr = trim((string) $entry['LIFNR']);
                    $entry_lifnr_sin_ceros = ltrim($entry_lifnr, '0');
                    $submitted_lifnr_sin_ceros = ltrim($proveedor_sap_id, '0');
                    if ($entry_lifnr_sin_ceros === '') {
                        $entry_lifnr_sin_ceros = $entry_lifnr;
                    }
                    if ($submitted_lifnr_sin_ceros === '') {
                        $submitted_lifnr_sin_ceros = $proveedor_sap_id;
                    }

                    if (hash_equals($entry_lifnr_sin_ceros, $submitted_lifnr_sin_ceros)) {
                        $proveedor_sap_encontrado = [
                            'LIFNR' => $entry_lifnr,
                            'NAME1' => trim((string) $entry['NAME1']),
                        ];
                        break;
                    }
                }

                if (!$proveedor_sap_encontrado) {
                    $error = "SAP no encontró ese proveedor para la sociedad seleccionada.";
                } else {
                    $proveedor_sap_id = $proveedor_sap_encontrado['LIFNR'];
                    $sql = "SELECT id, nombre, proveedor_sap_id, clave_estandar
                            FROM proveedores
                            WHERE proveedor_sap_id = :proveedor_sap_id AND activo = 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['proveedor_sap_id' => $proveedor_sap_id]);
                    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

                    $lifnr_canonico = (string) $proveedor_sap_id;
                    $lifnr_sin_ceros = ltrim($lifnr_canonico, '0');
                    if ($lifnr_sin_ceros === '') {
                        $lifnr_sin_ceros = $lifnr_canonico;
                    }
                    $claves_iniciales = array_unique([$lifnr_canonico, $lifnr_sin_ceros]);

                    if (!$proveedor) {
                        $clave_inicial_valida = false;
                        foreach ($claves_iniciales as $clave_inicial) {
                            if (hash_equals($clave_inicial, $clave_estandar)) {
                                $clave_inicial_valida = true;
                                break;
                            }
                        }

                        $rif_temporal = 'SAP-' . $lifnr_canonico;
                        if (!$clave_inicial_valida) {
                            $error = "En el primer acceso, use su LIFNR como clave.";
                        } elseif (strlen($rif_temporal) > 20) {
                            $error = "No fue posible crear la cuenta automáticamente. Contacte al administrador.";
                        } else {
                            $nombre_sap = $proveedor_sap_encontrado['NAME1'];

                            if ($nombre_sap === '') {
                                $error = "SAP no devolvió el nombre del proveedor seleccionado.";
                            } else {
                                $clave_inicial_hash = password_hash($lifnr_canonico, PASSWORD_DEFAULT);
                                try {
                                    $stmt = $pdo->prepare(
                                        'INSERT INTO proveedores (proveedor_sap_id, nombre, rif, clave_estandar, activo)
                                         VALUES (?, ?, ?, ?, 1)'
                                    );
                                    $stmt->execute([
                                        $lifnr_canonico,
                                        $nombre_sap,
                                        $rif_temporal,
                                        $clave_inicial_hash,
                                    ]);
                                    $proveedor = [
                                        'id' => $pdo->lastInsertId(),
                                        'nombre' => $nombre_sap,
                                        'proveedor_sap_id' => $lifnr_canonico,
                                        'clave_estandar' => $clave_inicial_hash,
                                    ];
                                } catch (PDOException $e) {
                                    $stmt = $pdo->prepare(
                                        'SELECT id, nombre, proveedor_sap_id, clave_estandar
                                         FROM proveedores
                                         WHERE proveedor_sap_id = ? AND activo = 1'
                                    );
                                    $stmt->execute([$lifnr_canonico]);
                                    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if (!$proveedor) {
                                        error_log('Could not provision SAP supplier account: ' . $e->getMessage());
                                        $error = "No fue posible crear la cuenta del proveedor. Contacte al administrador.";
                                    }
                                }
                            }
                        }
                    }

                    if ($proveedor) {
                        $clave_guardada = (string) ($proveedor['clave_estandar'] ?? '');
                        $password_info = password_get_info($clave_guardada);
                        $es_clave_inicial = false;
                        $autenticado = false;

                        if ($clave_guardada !== '' && $password_info['algoName'] !== 'unknown') {
                            foreach ($claves_iniciales as $clave_inicial) {
                                if (password_verify($clave_inicial, $clave_guardada)) {
                                    $es_clave_inicial = true;
                                    $autenticado = hash_equals($clave_inicial, $clave_estandar)
                                        || hash_equals($lifnr_canonico, $clave_estandar)
                                        || hash_equals($lifnr_sin_ceros, $clave_estandar);
                                    break;
                                }
                            }

                            if (!$es_clave_inicial) {
                                $autenticado = password_verify($clave_estandar, $clave_guardada);
                            }
                        } elseif ($clave_guardada !== '') {
                            foreach ($claves_iniciales as $clave_inicial) {
                                if (hash_equals($clave_guardada, $clave_inicial)) {
                                    $es_clave_inicial = true;
                                    $autenticado = hash_equals($clave_inicial, $clave_estandar)
                                        || hash_equals($lifnr_canonico, $clave_estandar)
                                        || hash_equals($lifnr_sin_ceros, $clave_estandar);
                                    break;
                                }
                            }

                            if (!$es_clave_inicial) {
                                $autenticado = hash_equals($clave_guardada, $clave_estandar);
                            }
                        } else {
                            foreach ($claves_iniciales as $clave_inicial) {
                                if (hash_equals($clave_inicial, $clave_estandar)) {
                                    $autenticado = true;
                                    break;
                                }
                            }
                            $es_clave_inicial = $autenticado;
                        }

                        if ($autenticado) {
                            if ($es_clave_inicial) {
                                $stmt = $pdo->prepare('UPDATE proveedores SET clave_estandar = ? WHERE id = ?');
                                $stmt->execute([password_hash($lifnr_canonico, PASSWORD_DEFAULT), $proveedor['id']]);
                            }

                            session_regenerate_id(true);
                            $_SESSION['proveedor_id'] = $proveedor['id'];
                            $_SESSION['proveedor_sap_id'] = $proveedor['proveedor_sap_id'];
                            $_SESSION['proveedor_bukrs'] = $sociedad_seleccionada;
                            $_SESSION['proveedor_nombre'] = $proveedor['nombre'];

                            header("Location: ../index.php");
                            exit();
                        }

                        if ($error === '') {
                            $error = "La contraseña no es válida para este proveedor.";
                        }
                    }
                }
            }
        } catch (SapApiException $e) {
            error_log('SAP login request failed: ' . $e->getMessage());
            $status_code = $e->getHttpStatusCode();
            $error = $status_code === null
                ? "No fue posible contactar el servicio SAP. Inténtelo de nuevo más tarde."
                : "SAP no pudo validar el proveedor (HTTP {$status_code}).";
        } catch (InvalidArgumentException $e) {
            error_log('SAP login configuration is invalid: ' . $e->getMessage());
            $error = "La configuración de SAP no es válida.";
        } catch (JsonException $e) {
            error_log('SAP login response was not valid JSON: ' . $e->getMessage());
            $error = "SAP devolvió una respuesta inválida al validar el proveedor.";
        } catch (UnexpectedValueException $e) {
            error_log('SAP login response had an invalid structure: ' . $e->getMessage());
            $error = "SAP devolvió una respuesta inválida al validar el proveedor.";
        } catch (PDOException $e) {
            error_log('Database error during SAP login: ' . $e->getMessage());
            $error = "Error del sistema. Inténtelo de nuevo más tarde.";
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $error = "No fue posible iniciar la validación con SAP.";
        }
    }
}

require_once '../templates/header_login.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header text-center">
                <h3>Inicio de Sesión</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form action="login.php" method="post">
                    <div class="mb-3">
                        <label for="sociedad_dropdown" class="form-label">Sociedad</label>
                        <input type="hidden" name="bukrs" value="<?php echo htmlspecialchars($sociedad_seleccionada_en_lista ? $sociedad_seleccionada : '', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="dropdown sap-login-dropdown" data-sap-dropdown>
                            <button
                                class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                                type="button"
                                id="sociedad_dropdown"
                                data-bs-toggle="dropdown"
                                data-bs-display="static"
                                aria-expanded="false"
                                aria-haspopup="menu"
                                <?php echo $sociedades_error !== '' || $sociedades === [] ? 'disabled' : ''; ?>
                            >
                                <?php
                                echo $sociedad_seleccionada_en_lista
                                    ? htmlspecialchars($sociedad_seleccionada . ' - ' . $sociedades[$sociedad_seleccionada], ENT_QUOTES, 'UTF-8')
                                    : ($sociedades_error !== ''
                                        ? 'Error al cargar sociedades desde SAP'
                                        : ($sociedades === [] ? 'SAP no devolvió sociedades' : 'Seleccione una sociedad'));
                                ?>
                            </button>
                            <?php if ($sociedades !== []): ?>
                                <div class="dropdown-menu w-100" aria-labelledby="sociedad_dropdown">
                                    <div class="px-3 py-2">
                                        <input type="search" class="form-control" data-sap-search placeholder="Buscar sociedad..." aria-label="Buscar sociedad">
                                    </div>
                                    <div class="sap-login-dropdown-options" role="menu" aria-label="Sociedades SAP">
                                        <?php foreach ($sociedades as $codigo => $nombre): ?>
                                            <?php $etiqueta_sociedad = $codigo . ' - ' . $nombre; ?>
                                            <button
                                                type="button"
                                                class="dropdown-item"
                                                role="menuitem"
                                                data-sap-option
                                                data-value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-label="<?php echo htmlspecialchars($etiqueta_sociedad, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-search="<?php echo htmlspecialchars($etiqueta_sociedad, ENT_QUOTES, 'UTF-8'); ?>"
                                            ><?php echo htmlspecialchars($etiqueta_sociedad, ENT_QUOTES, 'UTF-8'); ?></button>
                                        <?php endforeach; ?>
                                        <div class="dropdown-item-text text-muted d-none" data-sap-empty>No se encontraron sociedades.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($sociedades_error !== ''): ?>
                            <div class="form-text text-danger"><?php echo htmlspecialchars($sociedades_error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="proveedor_sap_id" class="form-label">Número de proveedor</label>
                        <input
                            type="text"
                            name="proveedor_sap_id"
                            id="proveedor_sap_id"
                            class="form-control"
                            value="<?php echo htmlspecialchars($proveedor_sap_id, ENT_QUOTES, 'UTF-8'); ?>"
                            inputmode="numeric"
                            pattern="[0-9]{1,10}"
                            maxlength="10"
                            autocomplete="username"
                            aria-describedby="proveedor_sap_id_help"
                            required
                        >
                        <div id="proveedor_sap_id_help" class="form-text">Escriba su número exacto de proveedor.</div>
                    </div>
                    <div class="mb-3">
                        <label for="clave_estandar" class="form-label">Clave estándar</label>
                        <input type="password" name="clave_estandar" id="clave_estandar" class="form-control" autocomplete="current-password" required>
                        <div class="form-text">La clave inicial es el número del proveedor. Puede cambiarla después de ingresar.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Ingresar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-sap-dropdown]').forEach((dropdown) => {
    const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
    const menu = dropdown.querySelector('.dropdown-menu');
    const search = dropdown.querySelector('[data-sap-search]');
    const hiddenInput = dropdown.previousElementSibling;

    if (!toggle || !menu || !search || !hiddenInput) {
        return;
    }

    const options = Array.from(menu.querySelectorAll('[data-sap-option]'));
    const emptyMessage = menu.querySelector('[data-sap-empty]');

    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleOptions = 0;
        options.forEach((option) => {
            const matches = option.dataset.search.toLocaleLowerCase().includes(query);
            option.hidden = !matches;
            visibleOptions += matches ? 1 : 0;
        });
        emptyMessage.classList.toggle('d-none', visibleOptions > 0);
    });

    menu.addEventListener('click', (event) => {
        const option = event.target.closest('[data-sap-option]');
        if (!option) {
            return;
        }

        hiddenInput.value = option.dataset.value;
        toggle.textContent = option.dataset.label;
        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        search.value = '';
        search.dispatchEvent(new Event('input'));
    });

    toggle.addEventListener('shown.bs.dropdown', () => {
        search.focus();
    });

    toggle.addEventListener('hidden.bs.dropdown', () => {
        search.value = '';
        search.dispatchEvent(new Event('input'));
    });
});
</script>

<?php require_once '../templates/footer.php'; ?>