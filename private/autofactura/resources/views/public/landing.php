<!DOCTYPE html>
<html class="light" lang="es">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>AutoFactura | Automatización CFDI 4.0</title>
    <meta name="description" content="AutoFactura ayuda a los negocios a delegar la captura de datos fiscales en sus clientes, agilizar su proceso de facturación CFDI 4.0 y entregar XML y PDF sin trabajo manual repetitivo.">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@300;400;500;600&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-tertiary-fixed-variant": "#005236",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-fixed": "#002113",
                        "on-background": "#191c1e",
                        "on-secondary-fixed-variant": "#3a485b",
                        "tertiary": "#003f29",
                        "on-secondary-container": "#57657a",
                        "error-container": "#ffdad6",
                        "surface-tint": "#3557bc",
                        "tertiary-container": "#00593c",
                        "on-secondary": "#ffffff",
                        "surface-container": "#eceef0",
                        "on-primary": "#ffffff",
                        "tertiary-fixed-dim": "#4edea3",
                        "on-primary-container": "#aabcff",
                        "error": "#ba1a1a",
                        "on-secondary-fixed": "#0d1c2e",
                        "surface-container-high": "#e6e8ea",
                        "inverse-on-surface": "#eff1f3",
                        "surface-container-highest": "#e0e3e5",
                        "secondary": "#515f74",
                        "on-tertiary-container": "#44d69b",
                        "on-surface": "#191c1e",
                        "inverse-primary": "#b5c4ff",
                        "on-tertiary": "#ffffff",
                        "secondary-fixed": "#d5e3fc",
                        "on-surface-variant": "#434653",
                        "surface-variant": "#e0e3e5",
                        "on-error": "#ffffff",
                        "tertiary-fixed": "#6ffbbe",
                        "on-error-container": "#93000a",
                        "surface-bright": "#f7f9fb",
                        "on-primary-fixed-variant": "#153ea3",
                        "surface": "#f7f9fb",
                        "secondary-fixed-dim": "#b9c7df",
                        "primary-container": "#2045aa",
                        "background": "#f7f9fb",
                        "outline-variant": "#c3c6d5",
                        "surface-container-low": "#f2f4f6",
                        "surface-dim": "#d8dadc",
                        "secondary-container": "#d5e3fc",
                        "primary": "#002d8a",
                        "primary-fixed-dim": "#b5c4ff",
                        "outline": "#737784",
                        "inverse-surface": "#2d3133",
                        "primary-fixed": "#dce1ff",
                        "on-primary-fixed": "#00164e"
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    fontFamily: {
                        "headline": ["Manrope"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4 { font-family: 'Manrope', sans-serif; }

        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="bg-surface text-on-surface selection:bg-primary-fixed selection:text-on-primary-fixed">
    <nav class="bg-white/80 dark:bg-slate-950/80 backdrop-blur-md docked full-width top-0 sticky no-border shadow-sm z-50">
        <div class="flex justify-between items-center px-6 py-4 max-w-7xl mx-auto w-full z-50 gap-4">
            <a class="flex items-center gap-3 min-w-0" href="<?= url('/') ?>">
                <img src="<?= asset('img/autofactura.png') ?>" alt="cfdi.top" class="h-11 w-auto shrink-0">
                <div class="text-xl font-bold tracking-tight text-blue-900 font-headline hidden sm:block">
                    AutoFactura
                </div>
            </a>
            <div class="hidden md:flex gap-8 items-center font-manrope font-semibold text-sm">
                <a class="text-slate-600 hover:text-blue-600 transition-colors" href="#soluciones">Soluciones</a>
                <a class="text-slate-600 hover:text-blue-600 transition-colors" href="#precios">Precios</a>
                <a class="text-slate-600 hover:text-blue-600 transition-colors" href="#cumplimiento">Cumplimiento</a>
            </div>
            <div class="flex items-center gap-4">
                <a class="px-4 py-2 font-manrope font-semibold text-sm text-slate-600 hover:bg-slate-100 transition-colors rounded-lg" href="<?= url('login') ?>">Iniciar Sesión</a>
                <a class="px-5 py-2.5 font-manrope font-bold text-sm bg-gradient-to-br from-primary to-primary-container text-on-primary rounded-full shadow-lg shadow-primary/20 scale-95 duration-150 ease-in-out hover:scale-100" href="<?= url('register') ?>">Probar Gratis</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="relative pt-20 pb-32 overflow-hidden">
            <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-tertiary-fixed-dim text-on-tertiary-fixed rounded-full mb-6 font-label text-xs font-bold uppercase tracking-wider">
                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                        CFDI 4.0 Autorizado por SAT
                    </div>
                    <h1 class="text-5xl lg:text-7xl font-extrabold tracking-tight text-on-surface mb-8 leading-[1.1]">
                        Tus clientes se facturan <span class="bg-gradient-to-r from-primary to-primary-container bg-clip-text text-transparent">solos en 10 segundos</span>
                    </h1>
                    <p class="text-xl text-on-surface-variant mb-10 max-w-xl leading-relaxed">
                        Elimina la captura manual de datos fiscales en caja, WhatsApp o mostrador. Automatización total para restaurantes, hoteles y negocios con alto volumen.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a class="px-8 py-4 bg-gradient-to-br from-primary to-primary-container text-on-primary rounded-full font-bold text-lg shadow-xl shadow-primary/25 flex items-center justify-center gap-2" href="<?= url('register') ?>">
                            Empezar ahora (Gratis)
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </a>
                        <a class="px-8 py-4 bg-surface-container-low text-primary rounded-full font-bold text-lg flex items-center justify-center gap-2 hover:bg-surface-container-high transition-all" href="#como-funciona">
                            Ver Demo
                        </a>
                    </div>
                    <div class="mt-8 flex items-center gap-4 text-on-surface-variant font-medium">
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full border-2 border-surface bg-slate-200"></div>
                            <div class="w-8 h-8 rounded-full border-2 border-surface bg-slate-300"></div>
                            <div class="w-8 h-8 rounded-full border-2 border-surface bg-slate-400"></div>
                        </div>
                        <span class="text-sm">+500 negocios automatizando hoy</span>
                    </div>
                </div>
                <div class="relative group">
                    <div class="absolute -inset-4 bg-primary-fixed-dim/20 rounded-[2rem] blur-2xl group-hover:bg-primary-fixed-dim/30 transition-all"></div>
                    <div class="relative bg-surface-container-lowest rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/10">
                        <img class="w-full h-[500px] object-cover" data-alt="Modern upscale restaurant interior with a server holding a sleek point of sale tablet device, warm natural light, professional atmosphere" src="https://lh3.googleusercontent.com/aida-public/AB6AXuByeSQlwi3pSi6fDUooilHhZla7HmIa97HCYWg-p84h0BMXNQ70ydUt-XWyKA7-z0MRLh61pow9Q8eC_4QA1cRG8AlT3_SSueP7GAhF6pGPqLYUEPjhFdXn2VlfnJa3avnqo8YnLcygy0MYiHWvc4QB8htbwO4pH4kUQ2UZYlsHun-UdBQY8Yiqb-NKmFQSDpod8307ieRVfaPBVjs-BVMczVC3BOxzfoaIhkANExkYRmAlh0awQGerWuNOzTNl8AYbn7r8OMweYA">
                        <div class="absolute bottom-6 left-6 right-6 glass-panel rounded-xl p-6 shadow-lg border border-white/20">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-primary tracking-widest uppercase">Resultado Real</span>
                                <span class="text-xs font-medium text-tertiary px-2 py-0.5 bg-tertiary-fixed-dim rounded-full">Automático</span>
                            </div>
                            <div class="text-2xl font-bold text-on-surface tracking-tight">"Ahorramos 20 horas semanales de administración en caja."</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-surface-container-low py-24">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-20">
                    <h2 class="text-3xl md:text-5xl font-bold text-on-surface mb-4">Basta de errores y filas</h2>
                    <p class="text-on-surface-variant text-lg">Transforma la fricción administrativa en una experiencia fluida.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    <div class="p-8 bg-surface rounded-2xl border border-error/10">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="material-symbols-outlined text-error" style="font-variation-settings: 'FILL' 1;">error</span>
                            <h3 class="text-xl font-bold">Antes: Captura Manual</h3>
                        </div>
                        <ul class="space-y-4">
                            <li class="flex gap-4 items-start text-on-surface-variant">
                                <span class="material-symbols-outlined text-error text-lg">close</span>
                                <span>Filas en caja mientras el cliente dicta su RFC.</span>
                            </li>
                            <li class="flex gap-4 items-start text-on-surface-variant">
                                <span class="material-symbols-outlined text-error text-lg">close</span>
                                <span>Errores tipográficos que invalidan la factura.</span>
                            </li>
                            <li class="flex gap-4 items-start text-on-surface-variant">
                                <span class="material-symbols-outlined text-error text-lg">close</span>
                                <span>WhatsApp saturado de fotos de tickets borrosas.</span>
                            </li>
                        </ul>
                    </div>
                    <div class="p-8 bg-surface-container-lowest rounded-2xl shadow-xl shadow-primary/5 border border-primary/5">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="material-symbols-outlined text-tertiary" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <h3 class="text-xl font-bold">Después: AutoFactura</h3>
                        </div>
                        <ul class="space-y-4">
                            <li class="flex gap-4 items-start text-on-surface-variant">
                                <span class="material-symbols-outlined text-tertiary text-lg">check</span>
                                <span>Caja libre. El cliente se factura desde su propia mesa.</span>
                            </li>
                            <li class="flex gap-4 items-start text-on-surface-variant">
                                <span class="material-symbols-outlined text-tertiary text-lg">check</span>
                                <span>Datos validados al instante contra el SAT.</span>
                            </li>
                            <li class="flex gap-4 items-start text-on-surface-variant">
                                <span class="material-symbols-outlined text-tertiary text-lg">check</span>
                                <span>Entrega inmediata por Email y WhatsApp automático.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24" id="soluciones">
            <div class="max-w-7xl mx-auto px-6">
                <div class="mb-16">
                    <h2 class="text-4xl font-extrabold text-on-surface tracking-tight mb-4">Soluciones Especializadas</h2>
                    <p class="text-on-surface-variant text-xl">Facturación sin fricción adaptada a tu industria.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 h-auto md:h-[600px]">
                    <div class="md:col-span-2 relative bg-primary rounded-2xl overflow-hidden p-10 flex flex-col justify-end text-on-primary min-h-[300px]">
                        <img class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay" data-alt="Elegant restaurant table setting with customers in the background, soft bokeh, high-end fine dining ambiance" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCjndKeW86F016dgKM_jA39Ak5xZFYHDGqdxfEmAElZgm4TM1YYUxc9J8r7lsgWwnBfzBB1PkP1awoQkIS1-x6z2E9_ptgFmQOzkd8bVJ1pG_UobkD4np3BB2nk0vqWBpy56q_XK7NLlah4uw1LtjUL0zTBZDPVbEZAfHVOCNeubc4tEjlROq-1rU_TA41Hy-cyjqgR-EvOKtA-jSZLR0VRjD30r2uEcHdTRz-AzDoC5nHPXWmBl9F-P6pboBFrf1UTC5r1BUl2wQ">
                        <div class="relative z-10">
                            <h3 class="text-3xl font-bold mb-2">Restaurantes</h3>
                            <p class="text-primary-fixed mb-6 max-w-md">Libera tu caja. Imprime un QR en el ticket y deja que el comensal haga el resto.</p>
                            <a href="https://wa.me/5217471086815?text=Hola,%20me%20interesa%20automatizar%20la%20facturación%20de%20mi%20RESTAURANTE." class="inline-flex items-center px-4 py-2 bg-on-primary text-primary rounded-full text-sm font-bold">Ver solución</a>
                        </div>
                    </div>
                    <div class="relative bg-surface-container-highest rounded-2xl overflow-hidden p-10 flex flex-col justify-end min-h-[300px]">
                        <img class="absolute inset-0 w-full h-full object-cover opacity-20" data-alt="Lobby of a boutique hotel with minimalist marble reception desk and warm ambient lighting" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBal_zSeRTPpyQjLDKuFXtpJ-LONjlDt_-marmURyoshWSgVA0VPmR4REKeMblpKb_itCqWFIIp8JLIrrLtsks4NuuvJRNDXw-mreSigPfkKOkbGooDmAUNiVpiW18CFAIZVQcw1rha2XlZHniTWs0j4mU3ro2dsmD0UBLCTTmNh-Hxfb-pwIskSVKQ5OVLJD2srMb598vi1_UWVmGWzDEm6SuABMsHFKs_SbDmMXbfuLuVpCZHr0KxfHON83F2zVZueokvITQekg">
                        <div class="relative z-10">
                            <h3 class="text-2xl font-bold mb-2 text-on-surface">Hoteles</h3>
                            <a href="https://wa.me/5217471086815?text=Hola,%20me%20interesa%20automatizar%20la%20facturación%20de%20mi%20HOTEL." class="inline-flex items-center px-4 py-2 bg-on-primary text-primary rounded-full text-sm font-bold">Ver solución</a>
                        </div>
                    </div>
                    <div class="relative bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden p-10 flex flex-col justify-end min-h-[300px]">
                        <div class="relative z-10">
                            <h3 class="text-2xl font-bold mb-2 text-primary">Gasolineras</h3>
                            <a href="https://wa.me/5217471086815?text=Hola,%20me%20interesa%20automatizar%20la%20facturación%20de%20mi%20GASOLINERA." class="inline-flex items-center px-4 py-2 bg-on-primary text-primary rounded-full text-sm font-bold">Ver solución</a>
                            <div class="flex gap-2">
                                <span class="p-2 bg-primary-fixed-dim rounded-lg"><span class="material-symbols-outlined text-primary">local_gas_station</span></span>
                                <span class="p-2 bg-primary-fixed-dim rounded-lg"><span class="material-symbols-outlined text-primary">speed</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24 bg-surface" id="como-funciona">
            <div class="max-w-7xl mx-auto px-6 text-center mb-16">
                <h2 class="text-3xl font-bold text-on-surface">Cómo funciona</h2>
                <p class="text-on-surface-variant">Tres pasos simples hacia la libertad administrativa.</p>
            </div>
            <div class="max-w-5xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-12">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-fixed-dim text-primary rounded-2xl flex items-center justify-center mx-auto mb-6 text-3xl font-bold">1</div>
                    <h4 class="text-xl font-bold mb-3">Generas enlace o QR</h4>
                    <p class="text-on-surface-variant text-sm">Integrado a tu POS o como un enlace independiente para tus clientes.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-fixed-dim text-primary rounded-2xl flex items-center justify-center mx-auto mb-6 text-3xl font-bold">2</div>
                    <h4 class="text-xl font-bold mb-3">Cliente ingresa datos</h4>
                    <p class="text-on-surface-variant text-sm">Desde su propio smartphone, sin instalar nada, con validación RFC activa.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-fixed-dim text-primary rounded-2xl flex items-center justify-center mx-auto mb-6 text-3xl font-bold">3</div>
                    <h4 class="text-xl font-bold mb-3">Timbrado al instante</h4>
                    <p class="text-on-surface-variant text-sm">El sistema timbra el CFDI 4.0 y lo envía por correo y WhatsApp automáticamente.</p>
                </div>
            </div>
        </section>

        <section class="py-16 bg-surface-container-high" id="cumplimiento">
            <div class="max-w-7xl mx-auto px-6 flex flex-wrap justify-center items-center gap-12 opacity-80">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-on-surface-variant">security</span>
                    <span class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">Encriptación AES-256</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-on-surface-variant">verified_user</span>
                    <span class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">PAC Autorizado SAT</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-on-surface-variant">article</span>
                    <span class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">Listo para CFDI 4.0</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-on-surface-variant">cloud_done</span>
                    <span class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">Disponibilidad 99.9%</span>
                </div>
            </div>
        </section>

        <section class="py-32" id="precios">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-extrabold text-on-surface mb-4 tracking-tight">Planes simples para empezar y crecer</h2>
                    <p class="text-on-surface-variant text-lg">Elige el plan que mejor se adapta a tu volumen de facturación.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 max-w-6xl mx-auto">
                    <div class="bg-surface-container-lowest p-8 rounded-2xl border border-outline-variant/30 flex flex-col shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-xl font-bold text-on-surface mb-1">Starter</h3>
                        <p class="text-sm text-on-surface-variant mb-6">30 facturas</p>
                        <div class="text-5xl font-extrabold text-primary/80 mb-3">$149</div>
                        <div class="mt-auto">
                            <div class="text-sm text-on-surface-variant mb-1">/ mes (IVA incluido)</div>
                            <div class="text-sm text-on-surface-variant mb-6">Extra: $5.50</div>
                            <a class="block w-full py-3 bg-surface-container-low text-primary rounded-xl font-bold hover:bg-primary-fixed transition-colors text-center" href="<?= url('register') ?>">Comprar Ahora</a>
                        </div>
                    </div>
                    <div class="bg-surface-container-lowest p-8 rounded-2xl border border-outline-variant/30 flex flex-col shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-xl font-bold text-on-surface mb-1">Crecimiento</h3>
                        <p class="text-sm text-on-surface-variant mb-6">100 facturas</p>
                        <div class="text-5xl font-extrabold text-primary/80 mb-3">$399</div>
                        <div class="mt-auto">
                            <div class="text-sm text-on-surface-variant mb-1">/ mes (IVA incluido)</div>
                            <div class="text-sm text-on-surface-variant mb-6">Extra: $5.00</div>
                            <a class="block w-full py-3 bg-surface-container-low text-primary rounded-xl font-bold hover:bg-primary-fixed transition-colors text-center" href="<?= url('register') ?>">Comprar Ahora</a>
                        </div>
                    </div>
                    <div class="bg-primary p-8 rounded-2xl flex flex-col text-on-primary shadow-xl relative overflow-hidden">
                        <div class="absolute top-5 right-5 bg-on-primary text-primary text-xs font-bold px-3 py-1 rounded-full">Más popular</div>
                        <h3 class="text-xl font-bold mb-1">Negocio</h3>
                        <p class="text-sm opacity-80 mb-6">300 facturas</p>
                        <div class="text-5xl font-extrabold text-on-primary-container mb-3">$999</div>
                        <div class="mt-auto">
                            <div class="text-sm opacity-80 mb-1">/ mes (IVA incluido)</div>
                            <div class="text-sm opacity-80 mb-6">Extra: $4.50</div>
                            <a class="block w-full py-3 bg-on-primary text-primary rounded-xl font-bold shadow-lg text-center" href="<?= url('register') ?>">Comprar Ahora</a>
                        </div>
                    </div>
                </div>
                <div class="mt-10 text-center">
                    <p class="text-on-surface-variant text-base">¿Facturas más de 1,000 al mes? <a class="text-primary font-bold hover:underline" href="<?= url('login') ?>">Contáctanos</a></p>
                </div>
            </div>
        </section>

        <section class="py-24 bg-surface-container-lowest">
            <div class="max-w-5xl mx-auto px-6 text-center">
                <h2 class="text-4xl md:text-5xl font-extrabold text-on-surface mb-8 tracking-tight">¿Listo para liberar a tu personal de las facturas?</h2>
                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    <a class="px-10 py-5 bg-gradient-to-br from-primary to-primary-container text-on-primary rounded-full font-bold text-xl shadow-xl" href="<?= url('register') ?>">Crear mi cuenta gratis</a>
                    <a class="px-10 py-5 border border-primary text-primary rounded-full font-bold text-xl hover:bg-primary-fixed transition-colors" href="https://wa.me/5217471086815?text=Hola,%20tengo%20dudas%20sobre%20AutoFactura%20y%20me%20gustaría%20hablar%20con%20un%20experto.">Hablar con un experto</a>
                </div>
                <p class="mt-8 text-on-surface-variant font-label text-sm italic">Sin tarjeta de crédito. Configuración en 15 minutos.</p>
            </div>
        </section>
    </main>

    <footer class="w-full border-t border-slate-200 bg-slate-100">
        <div class="flex flex-col md:flex-row justify-between items-center px-8 py-12 max-w-7xl mx-auto gap-6">
            <div class="mb-8 md:mb-0 text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-3 mb-2">
                    <img src="<?= asset('img/autofactura.png') ?>" alt="cfdi.top" class="h-10 w-auto">
                    <div class="text-lg font-black text-slate-800">AutoFactura</div>
                </div>
                <div class="font-inter text-xs text-slate-500">© 2026 AutoFactura. Todos los derechos reservados. Cumple con SAT &amp; CFDI 4.0.</div>
            </div>
            <div class="flex gap-8 font-inter text-xs text-slate-500 flex-wrap justify-center">
                <a class="text-slate-500 hover:text-blue-500 transition-all hover:underline" href="#">Aviso de Privacidad</a>
                <a class="text-slate-500 hover:text-blue-500 transition-all hover:underline" href="#">Términos de Servicio</a>
                <a class="text-slate-500 hover:text-blue-500 transition-all hover:underline" href="<?= url('login') ?>">Contacto</a>
                <a class="text-slate-500 hover:text-blue-500 transition-all hover:underline" href="<?= url('register') ?>">Documentación API</a>
            </div>
        </div>
    </footer>
</body>
</html>
