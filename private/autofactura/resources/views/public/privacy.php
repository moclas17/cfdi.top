<?php
$legalDocument = [
    'title' => 'Aviso de Privacidad',
    'description' => 'Aviso de privacidad integral sobre el tratamiento de datos personales en AutoFactura y cfdi.top.',
    'updated_at' => '22 de julio de 2026',
    'intro' => [
        'De conformidad con la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP) en México, le informamos que el C. Erik Francisco Valle Camacho, Ingeniero en Sistemas Computacionales, en lo sucesivo “El Responsable”, con domicilio ubicado en San Benito 212A, Santa Sofía, Sector Élite, García, Nuevo León, México, C.P. 66008, es el responsable del uso, tratamiento y protección de sus Datos Personales, y al respecto le informamos lo siguiente:',
    ],
    'sections' => [
        [
            'id' => 'datos-recabados',
            'title' => '1. Datos Personales que serán recabados',
            'paragraphs' => [
                'Para llevar a cabo las finalidades descritas en el presente aviso, utilizaremos los siguientes datos bajo su consentimiento previo:',
            ],
            'items' => [
                ['label' => 'Datos de identificación', 'text' => 'Nombre completo, razón social, régimen capital o denominación comercial.'],
                ['label' => 'Datos de contacto', 'text' => 'Correo electrónico, teléfono fijo o celular y dirección fiscal completa.'],
                ['label' => 'Datos fiscales', 'text' => 'Registro Federal de Contribuyentes (RFC), Régimen Fiscal y Código Postal.'],
                ['label' => 'Datos de facturación', 'text' => 'Información de uso de CFDI y métodos de pago de preferencia.'],
                ['label' => 'Datos financieros y de procesamiento de pago indirecto', 'text' => 'El Responsable no recaba, almacena ni trata de ninguna manera datos de tarjetas de crédito, débito o cuentas bancarias. Las transacciones económicas se realizan de forma externa, cifrada y directa a través del procesador de pagos autorizado.'],
                ['label' => 'Credenciales de timbrado', 'text' => 'Archivo de Certificado de Sello Digital (.cer), archivo de Clave Privada (.key) y la contraseña respectiva, necesarios única y exclusivamente para la automatización del timbrado fiscal. El Responsable bajo ninguna circunstancia solicitará su Firma Electrónica Avanzada (e.firma / Fiel) ni su contraseña de acceso al portal del SAT.'],
            ],
        ],
        [
            'id' => 'finalidades-primarias',
            'title' => '2. Finalidades necesarias para el servicio (Fines Primarios)',
            'items' => [
                'Dar de alta el perfil de cliente y administrar su cuenta en nuestro sitio web denominado cfdi.top.',
                'Proveer los folios de facturación y las herramientas tecnológicas para la emisión automatizada de sus comprobantes fiscales.',
                'Validar la información fiscal y transmitirla ante el Servicio de Administración Tributaria (SAT).',
                'Gestionar el cobro de los servicios, emitir y enviar los comprobantes de pago correspondientes vía electrónica.',
                'Brindar soporte técnico, atención a clientes y resolver incidencias en la plataforma.',
            ],
        ],
        [
            'id' => 'finalidades-secundarias',
            'title' => '3. Finalidades secundarias (Opcionales)',
            'items' => [
                'Enviar encuestas de satisfacción para evaluar y mejorar las funciones de nuestro sitio web.',
                'Enviar correos electrónicos con promociones, ofertas de folios o lanzamientos de nuevos servicios.',
            ],
            'after' => [
                'Si no desea que sus datos se utilicen para estos fines opcionales, puede manifestar su negativa desmarcando la casilla correspondiente al final de este documento o enviando un correo a contacto@cfdi.top.',
            ],
        ],
        [
            'id' => 'transferencia-de-datos',
            'title' => '4. Transferencia de Datos Personales',
            'paragraphs' => [
                'Le informamos que sus datos personales son compartidos dentro del territorio nacional con los siguientes terceros receptores, para las finalidades estrictamente necesarias del servicio:',
            ],
            'items' => [
                ['label' => 'Servicio de Administración Tributaria (SAT)', 'text' => 'Para la validación y registro oficial de los comprobantes emitidos.'],
                ['label' => 'Proveedores Autorizados de Certificación (PAC)', 'text' => 'Con la finalidad única de certificar y timbrar las facturas electrónicas generadas conforme a la legislación vigente.'],
                ['label' => 'Payclip, S. de R.L. de C.V. ("Clip")', 'text' => 'Con la finalidad única de procesar los pagos electrónicos de los folios y servicios contratados por usted, garantizando que el tratamiento de sus datos patrimoniales se realice mediante conexiones seguras, encriptadas y bajo las políticas de seguridad del procesador de pagos. El Responsable únicamente recibe la confirmación del estado de la transacción para activar el servicio.'],
            ],
            'after' => [
                'Al tratarse de transferencias necesarias para el cumplimiento de una relación jurídica entre usted y El Responsable, no se requiere su consentimiento expreso de acuerdo con el Artículo 37 de la LFPDPPP. Los terceros receptores se encuentran obligados a mantener el mismo nivel de confidencialidad y medidas de seguridad vigentes.',
            ],
        ],
        [
            'id' => 'derechos-arco',
            'title' => '5. Derechos ARCO y Revocación del Consentimiento',
            'paragraphs' => [
                'Usted tiene derecho a conocer qué datos personales tenemos de usted (Acceso), solicitar su corrección (Rectificación), pedir que los eliminemos de nuestros registros (Cancelación) u oponerse al uso de los mismos para fines específicos (Oposición).',
                'Para el ejercicio de cualquiera de los derechos ARCO, usted deberá enviar una solicitud por escrito al correo electrónico: contacto@cfdi.top. La solicitud deberá contener:',
            ],
            'items' => [
                'Nombre del titular y correo para notificaciones.',
                'Documento que acredite su identidad (INE o RFC de la empresa).',
                'Descripción clara del derecho que desea ejercer.',
            ],
            'after' => [
                'El Responsable comunicará la resolución en un plazo máximo de 20 (veinte) días hábiles contados desde la fecha de recepción.',
            ],
        ],
        [
            'id' => 'cookies',
            'title' => '6. Uso de tecnologías de rastreo (Cookies)',
            'paragraphs' => [
                'En nuestra plataforma en la nube cfdi.top utilizamos cookies técnicas necesarias para mantener activa su sesión, recordar sus preferencias de navegación y recopilar analíticas de tráfico anónimas. Usted puede limitar, bloquear o borrar las cookies de este sitio configurando las opciones de privacidad de su navegador de internet en cualquier momento.',
            ],
        ],
        [
            'id' => 'cambios-al-aviso',
            'title' => '7. Cambios al Aviso de Privacidad',
            'paragraphs' => [
                'El presente aviso de privacidad puede sufrir modificaciones o actualizaciones derivadas de nuevos requerimientos legales, normativas del Servicio de Administración Tributaria (SAT) o de nuestras propias necesidades operativas. Nos comprometemos a mantenerlo informado sobre estos cambios a través de avisos visibles y publicación directa en nuestra página web: cfdi.top.',
            ],
        ],
    ],
];

require __DIR__ . '/legal-document.php';
