<?php
/**
 * Script para convertir init.sql (MySQL) a SQLite (veterinaria.db)
 * Versión corregida - crea tablas en orden correcto
 * Uso: php create_sqlite_db.php
 */

// Archivo de salida
$dbFile = __DIR__ . '/veterinaria.db';

// Eliminar DB anterior si existe
if (file_exists($dbFile)) {
    unlink($dbFile);
}

// Crear nueva DB SQLite con soporte para foreign keys
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "✓ Base de datos SQLite creada: $dbFile\n";
} catch (PDOException $e) {
    die("Error al crear DB: " . $e->getMessage() . "\n");
}

// Crear tablas en orden correcto (respetando dependencias)
$tables = [
    // 1. Tablas base (sin FK)
    "roles" => "
        CREATE TABLE roles (
            id_rol INTEGER PRIMARY KEY AUTOINCREMENT,
            nom_rol VARCHAR(20) NOT NULL UNIQUE,
            des_rol VARCHAR(100)
        )
    ",
    "servicios" => "
        CREATE TABLE servicios (
            id_ser INTEGER PRIMARY KEY AUTOINCREMENT,
            nom_ser VARCHAR(200) NOT NULL,
            des_ser VARCHAR(300),
            tip_ser VARCHAR(20) NOT NULL,
            act_ser INTEGER DEFAULT 1,
            cos_ser REAL
        )
    ",
    "productos" => "
        CREATE TABLE productos (
            id_pro INTEGER PRIMARY KEY AUTOINCREMENT,
            nom_pro VARCHAR(100) NOT NULL,
            obs_pro VARCHAR(300),
            tip_pro VARCHAR(20) NOT NULL,
            cant_pro INTEGER NOT NULL DEFAULT 0,
            pre_pro REAL NOT NULL,
            img_pro VARCHAR(255),
            act_pro INTEGER DEFAULT 1,
            fec_cre_pro TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_upd_pro TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ",
    "password_resets" => "
        CREATE TABLE password_resets (
            id_pr INTEGER PRIMARY KEY AUTOINCREMENT,
            ema_pr VARCHAR(200) NOT NULL,
            tok_pr VARCHAR(64) NOT NULL UNIQUE,
            used_pr INTEGER DEFAULT 0,
            fec_cre_pr TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_exp_pr TEXT NOT NULL
        )
    ",

    // 2. Tablas que dependen de usuarios
    "usuarios" => "
        CREATE TABLE usuarios (
            id_usu INTEGER PRIMARY KEY AUTOINCREMENT,
            ema_usu VARCHAR(200) NOT NULL UNIQUE,
            pas_usu VARCHAR(255) NOT NULL,
            doc_usu VARCHAR(20) NOT NULL UNIQUE,
            tipd_usu VARCHAR(20) NOT NULL,
            dire_usu VARCHAR(200),
            nom_usu VARCHAR(100) NOT NULL,
            ape_usu VARCHAR(100) NOT NULL,
            tel_usu VARCHAR(20),
            id_rol_usu INTEGER NOT NULL,
            act_usu INTEGER DEFAULT 1,
            fec_cre_usu TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_upd_usu TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_rol_usu) REFERENCES roles(id_rol)
        )
    ",

    // 3. Tablas que dependen de usuarios y servicios/productos
    "mascotas" => "
        CREATE TABLE mascotas (
            id_mas INTEGER PRIMARY KEY AUTOINCREMENT,
            nom_mas VARCHAR(100) NOT NULL,
            esp_mas VARCHAR(50) NOT NULL,
            raz_mas VARCHAR(100) NOT NULL,
            sex_mas VARCHAR(10),
            ed_mas VARCHAR(20),
            pes_mas REAL,
            fec_nac_mas TEXT,
            act_mas INTEGER DEFAULT 1,
            id_prop_mas INTEGER NOT NULL,
            fec_cre_mas TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_upd_mas TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_prop_mas) REFERENCES usuarios(id_usu)
        )
    ",
    "horarios_empleado" => "
        CREATE TABLE horarios_empleado (
            id_hor INTEGER PRIMARY KEY AUTOINCREMENT,
            id_usu_hor INTEGER NOT NULL,
            tipo_hor VARCHAR(10) NOT NULL DEFAULT 'VET',
            dia_semana INTEGER NOT NULL,
            hora_inicio TEXT NOT NULL,
            hora_fin TEXT NOT NULL,
            act_hor INTEGER DEFAULT 1,
            fec_cre_hor TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_upd_hor TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usu_hor) REFERENCES usuarios(id_usu)
        )
    ",
    "asistencia" => "
        CREATE TABLE asistencia (
            id_asi INTEGER PRIMARY KEY AUTOINCREMENT,
            id_usu_asi INTEGER NOT NULL,
            fec_asi TEXT NOT NULL,
            hor_ent_asi TEXT DEFAULT '08:00:00',
            hor_sal_asi TEXT DEFAULT '18:00:00',
            hor_ent_real_asi TEXT,
            hor_sal_real_asi TEXT,
            obs_asi TEXT,
            act_asi INTEGER DEFAULT 1,
            cre_asi TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usu_asi) REFERENCES usuarios(id_usu)
        )
    ",
    "tarifas_horas" => "
        CREATE TABLE tarifas_horas (
            id_tar INTEGER PRIMARY KEY AUTOINCREMENT,
            id_rol_tar INTEGER NOT NULL,
            tar_hor_tar REAL NOT NULL DEFAULT 0.00,
            tar_hor_ext_tar REAL NOT NULL DEFAULT 0.00,
            nom_tar_tar VARCHAR(100) NOT NULL,
            act_tar INTEGER DEFAULT 1,
            cre_tar TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_rol_tar) REFERENCES roles(id_rol)
        )
    ",

    // 4. Tablas de citas (dependen de mascotas y veterinarios)
    "citas" => "
        CREATE TABLE citas (
            id_cit INTEGER PRIMARY KEY AUTOINCREMENT,
            fec_cit TEXT NOT NULL,
            hor_cit TEXT NOT NULL,
            tip_cit VARCHAR(100) NOT NULL,
            est_cit VARCHAR(20) DEFAULT 'PENDIENTE',
            obs_cit VARCHAR(200),
            diag_cit VARCHAR(1000),
            id_mas_cit INTEGER NOT NULL,
            id_vet_cit INTEGER,
            fec_cre_cit TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_upd_cit TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_mas_cit) REFERENCES mascotas(id_mas),
            FOREIGN KEY (id_vet_cit) REFERENCES usuarios(id_usu)
        )
    ",
    "vacunas_aplicadas" => "
        CREATE TABLE vacunas_aplicadas (
            id_apl INTEGER PRIMARY KEY AUTOINCREMENT,
            fec_apl TEXT NOT NULL,
            fec_prox_apl TEXT,
            hor_prox_apl TEXT,
            dos_apl VARCHAR(50),
            lot_apl VARCHAR(50),
            id_pro_apl INTEGER NOT NULL,
            id_mas_apl INTEGER NOT NULL,
            id_vet_apl INTEGER,
            fec_cre_apl TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_pro_apl) REFERENCES productos(id_pro),
            FOREIGN KEY (id_mas_apl) REFERENCES mascotas(id_mas),
            FOREIGN KEY (id_vet_apl) REFERENCES usuarios(id_usu)
        )
    ",

    // 5. Tablas de ventas
    "ventas_directas" => "
        CREATE TABLE ventas_directas (
            id_ven INTEGER PRIMARY KEY AUTOINCREMENT,
            fec_ven TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            id_usu_ven INTEGER NOT NULL,
            tot_ven REAL DEFAULT 0,
            obs_ven VARCHAR(200),
            fec_cre_ven TEXT DEFAULT CURRENT_TIMESTAMP,
            fec_upd_ven TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usu_ven) REFERENCES usuarios(id_usu)
        )
    ",

    // 6. Nómina
    "pagos_nomina" => "
        CREATE TABLE pagos_nomina (
            id_nom INTEGER PRIMARY KEY AUTOINCREMENT,
            id_usu_nom INTEGER NOT NULL,
            fec_ini_nom TEXT NOT NULL,
            fec_fin_nom TEXT NOT NULL,
            fec_gen_nom TEXT DEFAULT CURRENT_TIMESTAMP,
            hor_tra_nom REAL NOT NULL DEFAULT 0.00,
            sue_bru_nom REAL NOT NULL DEFAULT 0.00,
            ded_nom REAL NOT NULL DEFAULT 0.00,
            sue_net_nom REAL NOT NULL DEFAULT 0.00,
            est_nom TEXT DEFAULT 'PENDIENTE',
            obs_nom TEXT,
            FOREIGN KEY (id_usu_nom) REFERENCES usuarios(id_usu)
        )
    ",
    "detalle_nomina" => "
        CREATE TABLE detalle_nomina (
            id_det_nom INTEGER PRIMARY KEY AUTOINCREMENT,
            id_nom_det_nom INTEGER NOT NULL,
            id_asi_det_nom INTEGER NOT NULL,
            hor_tra_det_nom REAL NOT NULL DEFAULT 0.00,
            FOREIGN KEY (id_nom_det_nom) REFERENCES pagos_nomina(id_nom),
            FOREIGN KEY (id_asi_det_nom) REFERENCES asistencia(id_asi)
        )
    ",

    // 7. Tablas de detalle (al final)
    "detalle_citas" => "
        CREATE TABLE detalle_citas (
            id_det INTEGER PRIMARY KEY AUTOINCREMENT,
            fec_det TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            id_cit_det INTEGER NOT NULL,
            id_ser_det INTEGER,
            id_pro_det INTEGER,
            can_det INTEGER DEFAULT 1,
            sub_det REAL,
            fec_cre_det TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_cit_det) REFERENCES citas(id_cit) ON DELETE CASCADE,
            FOREIGN KEY (id_ser_det) REFERENCES servicios(id_ser),
            FOREIGN KEY (id_pro_det) REFERENCES productos(id_pro)
        )
    ",
    "venta_productos" => "
        CREATE TABLE venta_productos (
            id_vpro INTEGER PRIMARY KEY AUTOINCREMENT,
            id_ven_vpro INTEGER NOT NULL,
            id_pro_vpro INTEGER NOT NULL,
            can_vpro INTEGER DEFAULT 1,
            pre_vpro REAL,
            sub_vpro REAL,
            fec_cre_vpro TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_ven_vpro) REFERENCES ventas_directas(id_ven) ON DELETE CASCADE,
            FOREIGN KEY (id_pro_vpro) REFERENCES productos(id_pro)
        )
    "
];

// Crear cada tabla
echo "Creando tablas...\n";
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "  ✓ $name\n";
    } catch (PDOException $e) {
        echo "  ✗ $name: " . substr($e->getMessage(), 0, 60) . "...\n";
    }
}

// Crear índices
echo "\nCreando índices...\n";
$indexes = [
    "CREATE INDEX idx_rol_nombre ON roles(nom_rol)",
    "CREATE INDEX idx_servicio_tipo ON servicios(tip_ser)",
    "CREATE INDEX idx_servicio_activo ON servicios(act_ser)",
    "CREATE INDEX idx_servicio_nombre ON servicios(nom_ser)",
    "CREATE INDEX idx_producto_tipo ON productos(tip_pro)",
    "CREATE INDEX idx_producto_nombre ON productos(nom_pro)",
    "CREATE INDEX idx_producto_activo ON productos(act_pro)",
    "CREATE INDEX idx_usuario_email ON usuarios(ema_usu)",
    "CREATE INDEX idx_usuario_documento ON usuarios(doc_usu)",
    "CREATE INDEX idx_usuario_rol ON usuarios(id_rol_usu)",
    "CREATE INDEX idx_usuario_activo ON usuarios(act_usu)",
    "CREATE INDEX idx_mascota_propietario ON mascotas(id_prop_mas)",
    "CREATE INDEX idx_mascota_especie ON mascotas(esp_mas)",
    "CREATE INDEX idx_mascota_nombre ON mascotas(nom_mas)",
    "CREATE INDEX idx_mascota_activo ON mascotas(act_mas)",
    "CREATE INDEX idx_cita_fecha ON citas(fec_cit)",
    "CREATE INDEX idx_cita_mascota ON citas(id_mas_cit)",
    "CREATE INDEX idx_cita_veterinario ON citas(id_vet_cit)",
    "CREATE INDEX idx_cita_estado ON citas(est_cit)",
    "CREATE INDEX idx_vacuna_mascota ON vacunas_aplicadas(id_mas_apl)",
    "CREATE INDEX idx_vacuna_fecha ON vacunas_aplicadas(fec_apl)",
    "CREATE INDEX idx_vacuna_producto ON vacunas_aplicadas(id_pro_apl)",
    "CREATE INDEX idx_vacuna_veterinario ON vacunas_aplicadas(id_vet_apl)",
    "CREATE INDEX idx_detalle_cita ON detalle_citas(id_cit_det)",
    "CREATE INDEX idx_detalle_servicio ON detalle_citas(id_ser_det)",
    "CREATE INDEX idx_detalle_producto ON detalle_citas(id_pro_det)",
    "CREATE INDEX idx_detalle_fecha ON detalle_citas(fec_det)",
    "CREATE INDEX idx_venta_fecha ON ventas_directas(fec_ven)",
    "CREATE INDEX idx_venta_usuario ON ventas_directas(id_usu_ven)",
    "CREATE INDEX idx_vproducto_venta ON venta_productos(id_ven_vpro)",
    "CREATE INDEX idx_vproducto_producto ON venta_productos(id_pro_vpro)",
    "CREATE INDEX idx_pr_email ON password_resets(ema_pr)",
    "CREATE INDEX idx_pr_token ON password_resets(tok_pr)",
    "CREATE INDEX idx_horario_usuario ON horarios_empleado(id_usu_hor)",
    "CREATE INDEX idx_horario_tipo ON horarios_empleado(tipo_hor)",
    "CREATE INDEX idx_horario_dia ON horarios_empleado(dia_semana)",
    "CREATE INDEX idx_horario_activo ON horarios_empleado(act_hor)",
    "CREATE INDEX idx_asistencia_usu_fec ON asistencia(id_usu_asi, fec_asi)",
    "CREATE INDEX idx_nomina_usu_fechas ON pagos_nomina(id_usu_nom, fec_ini_nom, fec_fin_nom)"
];

foreach ($indexes as $index) {
    try {
        $pdo->exec($index);
    } catch (PDOException $e) {
        // Ignorar errores de índices duplicados
    }
}
echo "  ✓ Índices creados\n";

// Insertar datos iniciales
echo "\nInsertando datos iniciales...\n";

$inserts = [
    // Roles
    "INSERT INTO roles (nom_rol, des_rol) VALUES ('ADMIN', 'Administrador del sistema')",
    "INSERT INTO roles (nom_rol, des_rol) VALUES ('VETERINARIO', 'Profesional veterinario')",
    "INSERT INTO roles (nom_rol, des_rol) VALUES ('EMPLEADO', 'Empleado de la clínica')",
    "INSERT INTO roles (nom_rol, des_rol) VALUES ('CLIENTE', 'Cliente dueño de mascotas')",
    
    // Servicios
    "INSERT INTO servicios (nom_ser, des_ser, tip_ser, act_ser, cos_ser) VALUES ('Consulta General', 'Revisión básica del estado de salud', 'CONSULTA', 1, 25000)",
    "INSERT INTO servicios (nom_ser, des_ser, tip_ser, act_ser, cos_ser) VALUES ('Vacunación', 'Aplicación de vacunas', 'VACUNA', 1, 15000)",
    "INSERT INTO servicios (nom_ser, des_ser, tip_ser, act_ser, cos_ser) VALUES ('Cirugía', 'Procedimiento quirúrgico', 'CIRUGIA', 1, 150000)",
    "INSERT INTO servicios (nom_ser, des_ser, tip_ser, act_ser, cos_ser) VALUES ('Emergencia', 'Atención de emergencia 24/7', 'EMERGENCIA', 1, 50000)",
    "INSERT INTO servicios (nom_ser, des_ser, tip_ser, act_ser, cos_ser) VALUES ('Baño y Corte', 'Servicios de estética canina/felina', 'ESTETICA', 1, 35000)",
    "INSERT INTO servicios (nom_ser, des_ser, tip_ser, act_ser, cos_ser) VALUES ('Desparasitación', 'Tratamiento antiparasitario', 'TRATAMIENTO', 1, 20000)",
    
    // Productos
    "INSERT INTO productos (nom_pro, obs_pro, tip_pro, cant_pro, pre_pro, act_pro) VALUES ('Vacuna Antirrábica', 'Protección contra rabia', 'VACUNA', 50, 18000, 1)",
    "INSERT INTO productos (nom_pro, obs_pro, tip_pro, cant_pro, pre_pro, act_pro) VALUES ('Vitamina Pet', 'Suplemento vitamínico', 'MEDICAMENTO', 100, 15000, 1)",
    "INSERT INTO productos (nom_pro, obs_pro, tip_pro, cant_pro, pre_pro, act_pro) VALUES ('Alimento Premium', 'Alimento balanceado premium', 'ALIMENTO', 30, 45000, 1)",
    "INSERT INTO productos (nom_pro, obs_pro, tip_pro, cant_pro, pre_pro, act_pro) VALUES ('Collar Antipulgas', 'Protección contra pulgas', 'ACCESORIO', 25, 22000, 1)",
    "INSERT INTO productos (nom_pro, obs_pro, tip_pro, cant_pro, pre_pro, act_pro) VALUES ('Antibiótico', 'Antibiótico de amplio espectro', 'MEDICAMENTO', 40, 28000, 1)",
    "INSERT INTO productos (nom_pro, obs_pro, tip_pro, cant_pro, pre_pro, act_pro) VALUES ('Juguete Mordedor', 'Juguete de caucho para perros', 'ACCESORIO', 50, 12000, 1)",
    
    // Tarifas
    "INSERT INTO tarifas_horas (id_rol_tar, tar_hor_tar, tar_hor_ext_tar, nom_tar_tar) VALUES (1, 15000, 22500, 'Administrador')",
    "INSERT INTO tarifas_horas (id_rol_tar, tar_hor_tar, tar_hor_ext_tar, nom_tar_tar) VALUES (2, 12000, 18000, 'Veterinario')",
    "INSERT INTO tarifas_horas (id_rol_tar, tar_hor_tar, tar_hor_ext_tar, nom_tar_tar) VALUES (3, 8000, 12000, 'Empleado')",
];

foreach ($inserts as $insert) {
    try {
        $pdo->exec($insert);
    } catch (PDOException $e) {
        // Ignorar
    }
}
echo "  ✓ Datos iniciales insertados\n";

// Mostrar resumen
echo "\n📊 Tablas creadas:\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$totalRegistros = 0;
foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    $totalRegistros += $count;
    echo "  - $table ($count registros)\n";
}

echo "\n📈 Total registros: $totalRegistros\n";
echo "\n✅ Base de datos SQLite creada exitosamente!\n";
echo "📁 Ubicación: $dbFile\n";