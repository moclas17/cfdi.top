<?php
$documentTitle = (string) ($legalDocument['title'] ?? 'Documento legal');
$documentDescription = (string) ($legalDocument['description'] ?? 'Información legal de AutoFactura.');
$documentIntro = is_array($legalDocument['intro'] ?? null) ? $legalDocument['intro'] : [];
$documentSections = is_array($legalDocument['sections'] ?? null) ? $legalDocument['sections'] : [];
$documentUpdatedAt = (string) ($legalDocument['updated_at'] ?? '');
?>
<!DOCTYPE html>
<html class="light scroll-smooth" lang="es">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?= e($documentTitle) ?> | AutoFactura</title>
    <meta name="description" content="<?= e($documentDescription) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#182034',
                        primary: '#002d8a',
                        paper: '#f7f9fb',
                        line: '#dfe4ec'
                    },
                    fontFamily: {
                        display: ['Manrope', 'sans-serif'],
                        body: ['Inter', 'sans-serif']
                    }
                }
            }
        };
    </script>
</head>
<body class="min-h-screen bg-paper font-body text-ink antialiased">
    <header class="sticky top-0 z-40 border-b border-line/90 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 md:px-8">
            <a href="<?= url() ?>" class="flex items-center gap-3" aria-label="Ir al inicio de AutoFactura">
                <img src="<?= asset('img/autofactura.png') ?>" alt="AutoFactura" class="h-9 w-auto md:h-10">
            </a>
            <a href="<?= url() ?>" class="inline-flex items-center gap-2 rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-primary transition hover:border-primary hover:bg-blue-50">
                <span aria-hidden="true">←</span>
                Volver al inicio
            </a>
        </div>
    </header>

    <main>
        <section class="border-b border-line bg-gradient-to-br from-white via-blue-50/60 to-slate-100">
            <div class="mx-auto max-w-7xl px-5 py-14 md:px-8 md:py-20">
                <p class="mb-4 text-xs font-bold uppercase tracking-[0.22em] text-primary">Información legal</p>
                <h1 class="max-w-4xl font-display text-4xl font-extrabold tracking-tight text-ink md:text-6xl"><?= e($documentTitle) ?></h1>
                <?php if ($documentUpdatedAt !== ''): ?>
                    <p class="mt-5 text-sm text-slate-600">Última actualización: <?= e($documentUpdatedAt) ?></p>
                <?php endif; ?>
            </div>
        </section>

        <div class="mx-auto grid max-w-7xl gap-10 px-5 py-12 md:px-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:py-16">
            <aside class="lg:sticky lg:top-28 lg:self-start">
                <details class="rounded-2xl border border-line bg-white p-5 shadow-sm lg:hidden">
                    <summary class="cursor-pointer list-none font-display text-sm font-bold uppercase tracking-wider text-primary">
                        Ver contenido del documento
                    </summary>
                    <nav aria-label="Contenido del documento" class="mt-4 border-t border-line pt-3">
                        <ol class="space-y-1.5 text-sm">
                            <?php foreach ($documentSections as $section): ?>
                                <li>
                                    <a href="#<?= e((string) ($section['id'] ?? '')) ?>" class="block rounded-lg px-3 py-2 leading-snug text-slate-600 transition hover:bg-blue-50 hover:text-primary">
                                        <?= e((string) ($section['title'] ?? '')) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                </details>

                <nav aria-label="Contenido del documento" class="hidden rounded-2xl border border-line bg-white p-5 shadow-sm lg:block">
                    <p class="mb-4 font-display text-sm font-bold uppercase tracking-wider text-slate-500">Contenido</p>
                    <ol class="space-y-1.5 text-sm">
                        <?php foreach ($documentSections as $section): ?>
                            <li>
                                <a href="#<?= e((string) ($section['id'] ?? '')) ?>" class="block rounded-lg px-3 py-2 leading-snug text-slate-600 transition hover:bg-blue-50 hover:text-primary">
                                    <?= e((string) ($section['title'] ?? '')) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </aside>

            <article class="min-w-0 rounded-3xl border border-line bg-white px-6 py-8 shadow-sm md:px-10 md:py-12">
                <div class="space-y-5 text-[15px] leading-7 text-slate-700 md:text-base md:leading-8">
                    <?php foreach ($documentIntro as $paragraph): ?>
                        <p><?= e((string) $paragraph) ?></p>
                    <?php endforeach; ?>
                </div>

                <div class="mt-12 space-y-12">
                    <?php foreach ($documentSections as $section): ?>
                        <section id="<?= e((string) ($section['id'] ?? '')) ?>" class="scroll-mt-28">
                            <h2 class="mb-5 font-display text-2xl font-extrabold tracking-tight text-ink md:text-3xl"><?= e((string) ($section['title'] ?? '')) ?></h2>

                            <?php if (!empty($section['paragraphs']) && is_array($section['paragraphs'])): ?>
                                <div class="space-y-4 text-[15px] leading-7 text-slate-700 md:text-base md:leading-8">
                                    <?php foreach ($section['paragraphs'] as $paragraph): ?>
                                        <p><?= e((string) $paragraph) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($section['items']) && is_array($section['items'])): ?>
                                <ul class="mt-5 space-y-4 border-l-2 border-blue-100 pl-5 text-[15px] leading-7 text-slate-700 md:text-base md:leading-8">
                                    <?php foreach ($section['items'] as $item): ?>
                                        <li>
                                            <?php if (is_array($item)): ?>
                                                <?php if (!empty($item['label'])): ?><strong class="font-semibold text-ink"><?= e((string) $item['label']) ?>:</strong><?php endif; ?>
                                                <?= e((string) ($item['text'] ?? '')) ?>
                                            <?php else: ?>
                                                <?= e((string) $item) ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($section['after']) && is_array($section['after'])): ?>
                                <div class="mt-5 space-y-4 text-[15px] leading-7 text-slate-700 md:text-base md:leading-8">
                                    <?php foreach ($section['after'] as $paragraph): ?>
                                        <p><?= e((string) $paragraph) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>
    </main>

    <footer class="border-t border-line bg-slate-100">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-5 py-8 text-center text-xs text-slate-500 md:flex-row md:px-8 md:text-left">
            <span>© 2026 AutoFactura. Todos los derechos reservados.</span>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2">
                <a href="<?= url('aviso-de-privacidad') ?>" class="transition hover:text-primary hover:underline">Aviso de Privacidad</a>
                <a href="<?= url('terminos-de-servicio') ?>" class="transition hover:text-primary hover:underline">Términos de Servicio</a>
            </div>
        </div>
    </footer>
</body>
</html>
