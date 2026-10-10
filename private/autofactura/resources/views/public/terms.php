<?php
$legalDocument = [
    'title' => 'Términos y Condiciones de Servicio',
    'description' => 'Términos y condiciones aplicables al uso de la plataforma AutoFactura en cfdi.top.',
    'updated_at' => '22 de julio de 2026',
    'intro' => [
        'Bienvenido a cfdi.top. Los presentes Términos y Condiciones de Servicio (en lo sucesivo los "Términos") constituyen un contrato legalmente vinculante entre el usuario (en lo sucesivo el "Usuario" o "Cliente") y el C. Erik Francisco Valle Camacho (en lo sucesivo "El Proveedor"), con domicilio en San Benito 212A, Santa Sofía, Sector Élite, García, Nuevo León, México, C.P. 66008.',
        'Al registrarse, acceder y utilizar la plataforma web cfdi.top (en lo sucesivo la "Plataforma"), el Usuario acepta de manera expresa y sin reserva alguna el contenido de la totalidad de los presentes Términos. Si el Usuario no está de acuerdo con ellos, deberá abstenerse de utilizar la Plataforma.',
    ],
    'sections' => [
        [
            'id' => 'objeto-del-servicio',
            'title' => '1. Objeto del Servicio',
            'paragraphs' => [
                'La Plataforma es un sistema en la nube diseñado para la administración, automatización y facilitación de la emisión de Comprobantes Fiscales Digitales por Internet (CFDI). El Proveedor actúa estrictamente como un intermediario tecnológico entre el Usuario, los Proveedores Autorizados de Certificación (PAC) y el Servicio de Administración Tributaria (SAT).',
            ],
        ],
        [
            'id' => 'suscripcion-y-caducidad',
            'title' => '2. Modelo de Suscripción y Caducidad de Folios',
            'items' => [
                ['label' => 'Periodicidad', 'text' => 'El acceso a las herramientas de la Plataforma y la asignación de folios de facturación operan bajo un modelo de suscripción mensual.'],
                ['label' => 'Vigencia y Caducidad', 'text' => 'Los folios incluidos en el plan contratado tienen una vigencia estricta de 30 (treinta) días naturales a partir de la fecha de pago y activación.'],
                ['label' => 'No Acumulación', 'text' => 'Los folios que no sean utilizados por el Usuario dentro de su periodo mensual de facturación caducarán automáticamente y no serán acumulables para el mes posterior, sin responsabilidad alguna para El Proveedor ni derecho a reembolso o compensación.'],
            ],
        ],
        [
            'id' => 'pagos-y-facturacion',
            'title' => '3. Procesamiento de Pagos y Facturación del Servicio',
            'items' => [
                ['label' => 'Pasarela de Pagos', 'text' => 'Todos los cargos correspondientes a las suscripciones mensuales se procesan de forma externa y segura a través de la plataforma de Payclip, S. de R.L. de C.V. ("Clip").'],
                ['label' => 'Cargos', 'text' => 'El Usuario autoriza el procesamiento del pago bajo los términos de Clip. El Proveedor no almacena ni tiene acceso a los datos de las tarjetas bancarias del Usuario.'],
                ['label' => 'Activación', 'text' => 'El servicio o la recarga de folios se activará de forma automatizada una vez que Clip emita la confirmación de "pago aprobado" a la Plataforma. El Proveedor no es responsable por transacciones declinadas o retenidas por el banco emisor o por Clip.'],
            ],
        ],
        [
            'id' => 'cancelacion-y-reembolsos',
            'title' => '4. Política de Cancelación y Reembolsos',
            'paragraphs' => [
                'Debido a que el servicio se ejecuta de forma inmediata al activarse los folios en el perfil del Cliente, no se realizan reembolsos ni devoluciones de dinero una vez procesado el pago a través de Clip, salvo en casos demostrables de fallas técnicas imputables directamente al código de la Plataforma que impidan por completo el uso del servicio por más de 48 horas continuas.',
            ],
        ],
        [
            'id' => 'propiedad-intelectual',
            'title' => '5. Propiedad Intelectual y Licencia de Uso',
            'items' => [
                ['label' => 'Licencia', 'text' => 'El Proveedor otorga al Usuario una licencia de uso personal, revocable, no exclusiva y no transferible para utilizar el software de la Plataforma exclusivamente para los fines fiscales descritos.'],
                ['label' => 'Protección del Código', 'text' => 'Queda estrictamente prohibido copiar, modificar, distribuir, vender, realizar ingeniería inversa, descompilar o intentar extraer el código fuente de cfdi.top. La totalidad del software, diseño, logotipos, interfaces, bases de datos y algoritmos son propiedad intelectual exclusiva del C. Erik Francisco Valle Camacho y están protegidos por la Ley Federal del Derecho de Autor y la Ley Federal de Protección a la Propiedad Industrial en México.'],
            ],
        ],
        [
            'id' => 'responsabilidades',
            'title' => '6. Exclusión de Responsabilidades (Límite de Garantía)',
            'paragraphs' => [
                'El Usuario reconoce y acepta de forma expresa que El Proveedor no será responsable bajo ninguna circunstancia por:',
            ],
            'items' => [
                ['label' => 'Interrupciones del SAT o PAC', 'text' => 'Interrupciones, fallas, lentitud, actualizaciones o caídas generalizadas en los servidores del Servicio de Administración Tributaria (SAT) o de los Proveedores Autorizados de Certificación (PAC) que impidan el timbrado de las facturas.'],
                ['label' => 'Uso de Credenciales', 'text' => 'El mal uso, pérdida o robo de los Certificados de Sello Digital (CSD) o contraseñas cargadas por el Usuario en la Plataforma.'],
                ['label' => 'Errores de Llenado', 'text' => 'Errores en la información fiscal capturada por el Usuario o sus clientes finales al emitir un CFDI (montos, RFCs, regímenes fiscales incorrectos).'],
                ['label' => 'Pérdidas Comerciales', 'text' => 'Daños indirectos, pérdidas de ganancias, pérdidas de clientes (como comensales en restaurantes u huéspedes en hoteles) derivados de la imposibilidad temporal de emitir una factura.'],
            ],
        ],
        [
            'id' => 'obligaciones-del-usuario',
            'title' => '7. Obligaciones y Uso Correcto del Usuario',
            'paragraphs' => [
                'El Usuario se compromete a utilizar la Plataforma conforme a las leyes vigentes. Queda prohibido el uso del sistema para la emisión de comprobantes que amparen operaciones inexistentes, falsas o ilícitas (EDOS y EFOS). El Proveedor se reserva el derecho de rescindir la suscripción y bloquear el acceso a la cuenta de forma inmediata, sin derecho a reembolso, si detecta actividades fraudulentas o sospechosas.',
            ],
        ],
        [
            'id' => 'modificaciones',
            'title' => '8. Modificaciones a los Términos',
            'paragraphs' => [
                'El Proveedor se reserva el derecho de modificar los presentes Términos en cualquier momento. Las modificaciones surtirán efecto inmediatamente después de su publicación en la Plataforma. El uso continuo de la Plataforma posterior a dichos cambios constituye la aceptación de los nuevos Términos.',
            ],
        ],
        [
            'id' => 'jurisdiccion',
            'title' => '9. Jurisdicción y Legislación Aplicable',
            'paragraphs' => [
                'Para la interpretación, cumplimiento y resolución de cualquier controversia derivada de los presentes Términos, las partes se someten expresamente a las leyes mercantiles de la República Mexicana y a los tribunales competentes de la Ciudad de Monterrey, Nuevo León, renunciando expresamente a cualquier otro fuero que pudiera corresponderles por razón de sus domicilios presentes o futuros.',
            ],
        ],
    ],
];

require __DIR__ . '/legal-document.php';
