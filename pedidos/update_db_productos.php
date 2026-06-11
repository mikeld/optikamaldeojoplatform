<?php
// update_db_productos.php
require_once __DIR__ . '/includes/conexion.php';

try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;
    
    echo "<h3>Iniciando migración de base de datos para Tabla de Productos...</h3>";
    
    // 1. Crear tabla productos
    $sqlCreateTable = "
        CREATE TABLE IF NOT EXISTS `productos` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `codigo` VARCHAR(100) NOT NULL UNIQUE,
            `descripcion` VARCHAR(255) NOT NULL,
            `grupo` VARCHAR(100) DEFAULT 'LENTES DE CONTACTO',
            `marca` VARCHAR(100) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME DEFAULT NULL,
            INDEX `idx_productos_codigo` (`codigo`),
            INDEX `idx_productos_deleted` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sqlCreateTable);
    echo "✅ Tabla `productos` verificada/creada correctamente.<br>";
    
    // 2. Contar productos existentes
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `productos`")->fetchColumn();
    
    if ($count === 0) {
        echo "Cargando productos iniciales...<br>";
        
        $jsonPayload = <<<'JSON'
[
    {
        "codigo": "1DAY-TRUE-EYE180",
        "descripcion": "ACUVUE ONE DAY TRUE EYE 180U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "1DAY-TRUE-EYE30",
        "descripcion": "ACUVUE ONE DAY TRUE EYE 30U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "1DAY-TRUE-EYE90",
        "descripcion": "ACUVUE ONE DAY TRUE EYE 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "ACCTUA1DAY",
        "descripcion": "LENTE DE CONTACTO DIARIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ACCTUAASF",
        "descripcion": "CAJA 6U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ACCTUA-COLORS",
        "descripcion": "LENTILLA MENSUAL GRADUADA HIDROGEL COSMÉTICA 1091",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ACCTUA-FRP 6U",
        "descripcion": "LENTE DE CONTACTO MENSUAL HIDROGEL + HIALURONATO DE SODIO",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ACCTUATORICBLIST",
        "descripcion": "BLISTER ACCTUA TORIC",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ACUVU-1DAY-MOI90",
        "descripcion": "LENTE DE CONTACTO DIARIA EN FORMATO DE 90 UNIDADES   5116",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "ACUVU1D-MOI-T-90",
        "descripcion": "ACUVUE ONE DAY MOIST TORIC EN PACK DE 90U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "ACUVUE1DAYMOI-TO",
        "descripcion": "LENTE DE CONTACTO TORICA DIARIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "ADAPT.LC.ESF/T",
        "descripcion": "ADAPTACIÓN DE LC MONOFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVICIOS"
    },
    {
        "codigo": "ADAPT.LC.MF",
        "descripcion": "ADAPTACIÓN DE LC MF",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVICIOS"
    },
    {
        "codigo": "ADAPT.LC.ORTOK",
        "descripcion": "ADAPTACIÓN DE LC ORTOQUERATOLOGÍA NOCTURNA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVICIOS"
    },
    {
        "codigo": "ADDA1DAY(30)",
        "descripcion": "LENTE DE CONTACTO DIARIA 30U (BIOM.1DAY)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA1DAY(90)",
        "descripcion": "LENTE DE CONTACTO DIARIA 90U (BIOM.1DAY)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA1DAY-SILIC30",
        "descripcion": "Clarity 1 day personalizada - LC diaria HI-SI",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA1DAY-SILIC90",
        "descripcion": "Clarity 1 day personalizada - LC diaria HI-SI",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA-1DAY-T(90)",
        "descripcion": "Clarity 1 day personalizada - LC diaria HI-SI TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA1DAY-T-SILIC",
        "descripcion": "Clarity 1 day personalizada - LC diaria HI-SI TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA-ESF (6)",
        "descripcion": "CAJA LENTILLAS6U ESFERICA-PUREVISION 2HD",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ADDA-MF (6)",
        "descripcion": "CAJA LENTILLAS 6U MULTIFOCAL-PUREVISION 2HD",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ADDAONEDAY",
        "descripcion": "SOF ONE DAY BAUSCH AND LOMB",
        "grupo": "LENTES DE CONTACTO",
        "marca": "KIMERVISION"
    },
    {
        "codigo": "ADDAONEDAY90U",
        "descripcion": "SOF ONE DAY 3 CAJAS DE 30U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "KIMERVISION"
    },
    {
        "codigo": "ADDASILCO-EASY-T",
        "descripcion": "MY VISION MAX TORIC PERSONALIZADAS",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDASILICONE3U",
        "descripcion": "BIOFINITY PERSONALIZADA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDASILICTORIC3U",
        "descripcion": "BIOFINITY TORIC PERSONALIZADAS",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "ADDA-TORIC (6)",
        "descripcion": "CAJA LENTILAS 6UNIDADES ADDA TORIC-PUREVISION 2HD",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "AIROPTIX-(6U)",
        "descripcion": "LENTILLA MENSUAL HI-SI",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "AIROPTIX-MF(3U)",
        "descripcion": "LENTILLA PROGRESIVA CAJA DE 3UNIDADES 1713",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "AIROPTIX-MF(6)",
        "descripcion": "LENTILLA MENSUAL HI-SI MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "AIROPTIX-TORIC6",
        "descripcion": "LENTILLA HIDROGEL SILICONA TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "ALEXA-20",
        "descripcion": "LENTE DE CONTACTO RÍGIDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "TIEDRA"
    },
    {
        "codigo": "ALEXA-AS30",
        "descripcion": "LENTE DE CONTACTO RÍGIDA  6862",
        "grupo": "LENTES DE CONTACTO",
        "marca": "TIEDRA"
    },
    {
        "codigo": "ALEXA-H",
        "descripcion": "LENTE DE ORTOQUERATOLOGÍA NOCTURNA PARA HIPERMETROPÍA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "TIEDRA"
    },
    {
        "codigo": "BIAS MAC",
        "descripcion": "LENTE DE CONTACTO RÍGIDA PARA ASTIGMATISMOS MEDIAS 10129",
        "grupo": "LENTES DE CONTACTO",
        "marca": "CONOPTICA"
    },
    {
        "codigo": "BIAS.S.BOSTONES",
        "descripcion": "LENTILLAS RÍGIDAS",
        "grupo": "LENTES DE CONTACTO",
        "marca": "CONOPTICA"
    },
    {
        "codigo": "BIOFINITY",
        "descripcion": "LENTILLAS ESFÉRICAS 6UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY XR(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI DE FABRICACIÓN ESPECIAL 3unidades",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY XR(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI DE FABRICACIÓN ESPECIAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY(3)",
        "descripcion": "LENTE MENSUAL ESFÉRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITYENERGY3",
        "descripcion": "LENTE DE CONTACTO MENSUAL DE HIDROGEL DE SILICONA DE 3 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY-MF(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL PROGRESIVA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY-MF(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL PROGRESIVA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY-MF-T 3",
        "descripcion": "LENTE DE CONTACTO MENSUAL PROGRESIVA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY-TORIC3",
        "descripcion": "LENTILLA MENSUAL TORICA HIDROGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY-TORIC6",
        "descripcion": "BIOFINITY TORIC 6UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOFINITY-T-XR3",
        "descripcion": "LENTILLA MENSUAL TORICA HIDROGEL DE SILICONA FABRICACIÓN 3 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOMEDICS55EVO",
        "descripcion": "LENTE DE CONTACTO MENSUAL DE HIDROGEL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "BIOTRUE1DAY30",
        "descripcion": "LENTE DE CONTACTO DIARIA DE ULTRAGEL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "BIOTRUE1DAY90",
        "descripcion": "LENTE DE CONTACTO DIARIA DE ULTRAGEL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "BIOTRUE1D-MF-30",
        "descripcion": "LENTE DE CONTACTO DIARIA PROGRESIVA DE 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "BIOTRUE1D-MF-90",
        "descripcion": "LENTE DE CONTACTO DIARIA PROGRESIVA DE 90UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "BIOTRUE1D-TOR-30",
        "descripcion": "LENTE DE CONTACTO DIARIA TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "DAILIESAQUA30",
        "descripcion": "F. DAILIES AQUA 30",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIESAQUA90",
        "descripcion": "DAILIES AQUA 90",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIESAQUATORIC",
        "descripcion": "DAILIES AQUA TORIC",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIESPLUS30",
        "descripcion": "DAILIES AQUACOMFORT PLUS 30U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIESPLUS90",
        "descripcion": "DAILIES AQUACOMFORT PLUS 90U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIESPLUSPRO90",
        "descripcion": "LENTE DE CONTACTO DIARIA PROGRESIVA CAJA DE 90UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIESPLUS-PROG",
        "descripcion": "DAILIES AQUACONFORT PLUS PROGRESIVE",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIES-PLUS-T90",
        "descripcion": "LENTE DE CONTACTO DIARIA TORICA CAJA DE 90UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAILIES-PLUS-TOR",
        "descripcion": "DAILIES AQUACONFORT PLUS TORIC",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DAYANDNIGHT",
        "descripcion": "LENTE DE CONTACTO DE USO PROLONGADO",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "DIARIABAUSCHTORI",
        "descripcion": "LENTE DE CONTACTO DIARIA TÓRICA 30U   2500",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ENERY 6U",
        "descripcion": "LENTE DE CONTACTO MENSUAL HIDROGEL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ENERY MF (6)",
        "descripcion": "LENTE DE CONTACTO MULTIFOCAL MENSUAL HIDROGEL DE SILICONE 6 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "ENERY TORIC (6)",
        "descripcion": "LENTE DE CONTACTO TORIC MENSUAL HIDROGEL DE SILICONE 6 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "FABRICACION-RGP",
        "descripcion": "COSTE DE FABRICACIÓN DE LC RGP",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVICIOS"
    },
    {
        "codigo": "FANTASIA1WEEK",
        "descripcion": "LENTES DE CONTACTO FANTASIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "FASHIONLENTILLES",
        "descripcion": "LENTILLA DIARIA FANTASÍA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "FREQUENCY XCEL T",
        "descripcion": "LENTEDE CONTACTO MENSUAL TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "FUNKY",
        "descripcion": "LENTILLA FANTASIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "FUSION1DAY-MF-30",
        "descripcion": "LENTE DE CONTACO DIARIA MULTIFOCAL EDOF DE 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "GENTLE59-ESF(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL A MEDIDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "GENTLE59MF-T(3U)",
        "descripcion": "LENTE DE CONTACTO DE FABRICACIÓN MF TÓRICA 3UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "GENTLE59-TOR(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL TÓRICA PERSONALIZADA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "K38-TIPO.E-OPACA",
        "descripcion": "LENTE DE CONTACTO BLANDA PROTÉSICA ANUAL OPACA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "K-ROSE-XL",
        "descripcion": "LC SEMIESCLERAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "LENS-38-MF",
        "descripcion": "LENTEDE CONTACTO BLANDA MULTIFOCAL ANUAL PROGRESIVA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55 UV",
        "descripcion": "ESFÉRICA MENSUAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55-1DAY",
        "descripcion": "LENTE DE CONTACTO DIARIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55-BIMF3u",
        "descripcion": "LENTE DE CONTACTO MENSUAL BLANDA MULTIFOCAL DE FABRICACIÓN",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55COLOR",
        "descripcion": "LENTES DE CONTACTO FANTASIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55-PC-BIMF3u",
        "descripcion": "LENTE DE CONTACTO MENSUAL BLANDA MULTIFOCAL DE FABRICACIÓN",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55pcTORIC6U",
        "descripcion": "LENS 55 TRATAMIENTO PC TORIC (6U)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55PROSTHETIC",
        "descripcion": "LENTE DE CONTACTO COSMÉTICA MENSUAL BLANDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55-RX-MF-6u",
        "descripcion": "LENTE DE CONTACTO MENSUAL BLANDA MULTIFOCAL DE FABRICACIÓN",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55TORIC3U",
        "descripcion": "LENS 55 UV TORIC (3U)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55TORICRX",
        "descripcion": "CAJA LENS 55 UV RX TORIC (3U)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "LENS55-UV-RX",
        "descripcion": "ESFÉRICA MENSUAL DE FABRICACIÓN",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVILENS"
    },
    {
        "codigo": "MENICON-EX-Z",
        "descripcion": "LC RÍGIDA GP CORNEA REGULAR MATERIAL Z",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "MENICON-Z-COMFOR",
        "descripcion": "LENTE DE CONTACTO RÍGIDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "MENICON-Z-NIGHT",
        "descripcion": "LENTE DE CONTACTO PARA TTO DE ORTOQUERATOLOGÍA NOCTURNA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "MENIC-ROSE-K2PG",
        "descripcion": "LENTE DE CONTACTO RGP PARA CORNEA IRREGULAR",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "MIRU MENSUAL 6U",
        "descripcion": "LENTE DE CONTACTO MENSUAL HIDROGEL DE SILICON",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "MISIGHT(30U)",
        "descripcion": "LENTE DE CONTACTO DIARIA DE CONTROL DE MIOPIA 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MISIGHT(90U)",
        "descripcion": "LENTE DE CONTACTO DIARIA DE CONTROL DE MIOPIA 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYDAY(30)",
        "descripcion": "LENTE DE CONTACTO DIARIA CAJA DE 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYDAY(90)",
        "descripcion": "LENTE DE CONTACTO DIARIA CAJA DE 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYDAY-TORIC30U",
        "descripcion": "LENTE DE CONTACTO DIARIA TÓRICA HI-SI",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYDAY-TORIC90U",
        "descripcion": "LENTE DE CONTACTO DIARIA TORICA HI-SI 90NIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYLO.TORIC-6U",
        "descripcion": "LENTE DE CONTACTO BLANDA MENSUAL PARA CONTROL DE MIOPÍA   (8558)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "MYLO-6U",
        "descripcion": "LENTE DE CONTACTO BLANDA MENSUAL PARA CONTROL DE MIOPÍA   (7445)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "MYVISION-90U",
        "descripcion": "LENTE DE CONTACTO DIARIA CAJA DE 90 UNIADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYVISIONMAX(6)",
        "descripcion": "LENTE DE CONTACTO MENSIAL DE HIDROGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "MYVISIONMAX-T(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL TÓRICA 6UDS",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "OASYS 24U",
        "descripcion": "LENTE DE CONTACTO QUINCENAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYS1DAY-(30)",
        "descripcion": "LENTE DE CONTACTO DIARIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYS1DAY-(90)",
        "descripcion": "LENTE DE CONTACTO DIARIA 90 UDS",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYS1DAY-T(30)",
        "descripcion": "LENTE DE CONTACTO DIARIA TORICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYS1DAY-T(90)",
        "descripcion": "LENTE DE CONTACTO DIARIA TORICA 90 UDS",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYS-6U",
        "descripcion": "LENTE DE CONTACTO QUINCENAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYSFORASTIGM12",
        "descripcion": "6156 VISIONIS (PACK 12 U)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYSFORASTIGM6u",
        "descripcion": "3847 VISIONIS (PACK 6 U)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OASYSWITHIDRA12U",
        "descripcion": "ACUVUE OSASYS WITH HIDRACLEAR 12U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "JOHNSON AND JOHNSON"
    },
    {
        "codigo": "OPEN30-3U",
        "descripcion": "LENTE DE CONTACTO HIRDOGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "PRECISION1-30",
        "descripcion": "LENTE DE CONTACTO DIARIA ESFÉRICA 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "PRECISION1-90",
        "descripcion": "LENTE DE CONTACTO DIARIA ESFÉRICA 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "PRECISION1TORI30",
        "descripcion": "LENTE DE CONTACTO DIARIA TÓRICA 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "PRECISION1TORI90",
        "descripcion": "LENTE DE CONTACTO DIARIA TÓRICA 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "PROCLEA-MF-TORIC",
        "descripcion": "LENTE DE CONTACTO MENSUAL TORICA MULTIFOCAL (3U)",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "PROCLEAR(6U)",
        "descripcion": "LENTE DE CONTACTO MENSUAL BLANDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "PROCLEAR1DAY 90",
        "descripcion": "PROCLEAR ONE DAY 90U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "PROCLEAR-MF(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HISI, MF",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "PROCLEARMF-XR3U",
        "descripcion": "LENTE DE CONTACTO MENSUAL HISI, MF DE FABRICACIÓN ESPECIAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "PROCLEAR-TORIC6U",
        "descripcion": "LENTE DE CONTACTO MENSUAL TORICA BLANDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "COOPERVISION"
    },
    {
        "codigo": "PURE2HDESF.BLIST",
        "descripcion": "BLISTER LENTILLA MENSUAL ESFERICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "PURE2HDMF.BLIST",
        "descripcion": "BLISTER LENTILLA MENSUAL MULTIFOCAL HIDROGE-SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "REV ORTO-K",
        "descripcion": "REVISIÓN PARA ADAPTACIÓN DE LC ORTOQUERATOLOGÍA NOCTURNA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "SERVICIOS"
    },
    {
        "codigo": "SAFE-GEL·FRP(6U)",
        "descripcion": "CAJA SAFE-GEL MENSUAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SAFE-GEL1D-BLIST",
        "descripcion": "BLISTER SAFE-GEL 1DAY ESFERICAS 5UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SAFE-GEL30U",
        "descripcion": "SAFE-GEL ONE DAY 30",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SAFE-GEL90U",
        "descripcion": "SAFE-GEL ONE DAY 90",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SAFE-GELTORIC",
        "descripcion": "SAFE-GEL ONE DAY TORIC 30",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SAFELINE55",
        "descripcion": "CAJA LENTILLAS 6U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SAFELINE55BLISTE",
        "descripcion": "BLISTER SAFELINE 55",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "SOF38",
        "descripcion": "CAJA SOFLENS 38",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOF59",
        "descripcion": "LENTE DE CONTACTO MENSUAL BLANDA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOF-MF",
        "descripcion": "SOFLENS MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOFMFBLIST",
        "descripcion": "BLISTER SOFLENS MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOF-ONEDAY-30U",
        "descripcion": "SOFLENS ONE DAY 30",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOF-ONEDAY-90U",
        "descripcion": "SOFLENS ONE DAY 90U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOF-ONEDAY-T",
        "descripcion": "SOFLENS ONE DAY TORIC 30U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "SOF-TORIC-6U",
        "descripcion": "SOFLENS TORIC 4603",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "TOTAL1-30",
        "descripcion": "LENTE DE CONTACTO DIARIA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL1-90",
        "descripcion": "LENTE DE CONTACTO DIARIA 5662",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL1-PROGR-30",
        "descripcion": "LENTE DE CONTACTO MULTIFOCAL diaria DE 30 UNIDADES   3145",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL1-PROGR-90",
        "descripcion": "LENTE DE CONTACTO MULTIFOCAL DE 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL1-TORIC-30",
        "descripcion": "LENTE DE CONTACTO TÓRICA diaria DE 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL1-TORIC-90",
        "descripcion": "LENTE DE CONTACTO TÓRICA diaria DE 90 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL30-ESF(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI  ESFÉRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL30-MF(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL30-MF(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL30-TORIC(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "TOTAL30-TORIC(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "ALCON"
    },
    {
        "codigo": "ULTRA1DAY-MF-30",
        "descripcion": "LENTE DE CONTACTO DIARIA PROGRESIVA DE 30 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-ESF(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI 3 unidades",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-ESF(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-MF(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-MF(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI MULTIFOCAL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-MF-T(6)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI MULTIFOCAL TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-TORIC(3)",
        "descripcion": "LENTE DE CONTACTO MENSUAL HI-SI DE 3 UNIDADES",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "ULTRA-TORIC(6)",
        "descripcion": "LENTE DE CONTACTO HI-SI TÓRICA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "BAUSCH &LOMB"
    },
    {
        "codigo": "UPSIDE 30U",
        "descripcion": "LENTE DE CONTACTO DIARIA DE HIDROGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "XTENSA TORIC",
        "descripcion": "CAJA LENTILLAS 6U",
        "grupo": "LENTES DE CONTACTO",
        "marca": "VISIONIS"
    },
    {
        "codigo": "XTENSA-HISY-ESF",
        "descripcion": "LENTE DE CONTACTO ESFÉRICA DE HIDROGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "XTENSA-HISY-MF",
        "descripcion": "LENTE DE CONTACTO MULTIFOCAL DE HIDROGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "XTENSA-HISY-TORI",
        "descripcion": "LENTE DE CONTACTO TÓRICA DE HIDROGEL DE SILICONA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "XTENSA-MF(6)",
        "descripcion": "LENTE DE CONTACTO PROGRESIVA DE HIDROGEL",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "XTENSA-MF-T(3)",
        "descripcion": "LENTE DECONTACTO MENSUAL DE FABRICACIÓN MULTIFOCAL TÓRICA 3 unidades",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MARK ENNOVY"
    },
    {
        "codigo": "ZETA-ALPHA",
        "descripcion": "LENTE DE CONTACTO RÍGIDA 6955",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    },
    {
        "codigo": "Z-PROGRESIVE",
        "descripcion": "LENTE DE CONTACTO RGP PROGRESIVA",
        "grupo": "LENTES DE CONTACTO",
        "marca": "MENICON"
    }
]
JSON;
        
        $products = json_decode($jsonPayload, true);
        if ($products === null) {
            throw new Exception("Error al decodificar el JSON de productos: " . json_last_error_msg());
        }
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO `productos` (`codigo`, `descripcion`, `grupo`, `marca`) VALUES (:codigo, :descripcion, :grupo, :marca)");
        
        foreach ($products as $p) {
            $stmt->execute([
                ':codigo' => $p['codigo'],
                ':descripcion' => $p['descripcion'],
                ':grupo' => $p['grupo'],
                ':marca' => empty($p['marca']) ? null : $p['marca']
            ]);
        }
        
        $pdo->commit();
        echo "✅ Carga inicial de " . count($products) . " productos completada con éxito.<br>";
    } else {
        echo "ℹ️ La tabla `productos` ya contiene " . $count . " registros. Se omite la carga inicial.<br>";
    }
    
    echo "<br><b>¡Migración completada con éxito!</b>";
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<br><span style='color:red;'><b>Error durante la migración:</b> " . htmlspecialchars($e->getMessage()) . "</span>";
}
?>
