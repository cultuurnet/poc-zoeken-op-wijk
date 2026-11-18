<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use PocWijken\LookupFailed;
use PocWijken\WijkLookup;

$input = trim((string) ($_GET['event'] ?? ''));
$result = null;
$error = null;

if ($input !== '') {
    try {
        $result = WijkLookup::create()->lookup($input);
    } catch (LookupFailed $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log((string) $e);
        $error = 'Er liep iets mis: ' . $e->getMessage();
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$source = json_decode((string) @file_get_contents(dirname(__DIR__) . '/data/source.json'), true);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wijk van een event of place</title>
    <style>
        :root { --fg: #1d2330; --muted: #5d6677; --line: #d9dde5; --bg: #f6f7f9; --card: #fff; --accent: #0b5cad; --err: #b42318; --errbg: #fef3f2; --note: #fff8e6; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--fg); background: var(--bg); }
        main { max-width: 720px; margin: 0 auto; padding: 32px 16px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        p.lead { color: var(--muted); margin: 0 0 24px; }
        form { display: flex; gap: 8px; flex-wrap: wrap; }
        input[type=text] { flex: 1 1 320px; padding: 10px 12px; font: inherit; border: 1px solid var(--line); border-radius: 6px; }
        button { padding: 10px 18px; font: inherit; border: 0; border-radius: 6px; background: var(--accent); color: #fff; cursor: pointer; }
        .hint { font-size: .875rem; color: var(--muted); margin: 8px 0 0; }
        .note { background: var(--note); border: 1px solid #f0d68a; border-radius: 6px; padding: 10px 14px; margin: 24px 0 0; font-size: .9rem; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 8px; padding: 20px; margin-top: 24px; }
        .error { background: var(--errbg); border-color: #f5c2bd; color: var(--err); }
        .label { margin: 0; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        .wijk { font-size: 1.4rem; font-weight: 600; margin: 0; }
        .none { color: var(--muted); }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 16px 0 0; }
        dt { color: var(--muted); }
        dd { margin: 0; overflow-wrap: anywhere; }
        code { font-size: .9em; }
        footer { margin-top: 32px; font-size: .8rem; color: var(--muted); }
    </style>
</head>
<body>
<main>
    <h1>In welke wijk valt dit event of deze place?</h1>
    <p class="lead">Plak een UiTdatabank-link naar een event of place, of enkel de UUID.</p>

    <form method="get">
        <input type="text" name="event" value="<?= e($input) ?>" placeholder="https://io.uitdatabank.be/events/… of /places/…" autofocus>
        <button type="submit">Zoek wijk</button>
    </form>
    <p class="hint">
        Productie, acceptatie (<code>acc</code> / <code>io-acc</code>) en test (<code>test</code> / <code>io-test</code>) worden herkend.
        Een losse UUID wordt altijd op productie opgezocht, eerst als event en daarna als place.
    </p>

    <div class="note">
        <strong>Let op:</strong> de wijkindeling dekt enkel Vlaanderen en Brussel.
        Voor events en places in Wallonië (of buiten België) wordt geen wijk gevonden.
    </div>

    <?php if ($error !== null): ?>
        <div class="card error"><?= e($error) ?></div>
    <?php elseif ($result !== null): ?>
        <div class="card">
            <p class="label">Wijk</p>
            <?php if ($result['wijk'] !== null): ?>
                <p class="wijk"><?= e($result['wijk']['naam']) ?></p>
            <?php else: ?>
                <p class="wijk none">Geen wijk</p>
            <?php endif; ?>
            <dl>
                <dt><?= e($result['type']->label()) ?></dt>
                <dd><?= e($result['name'] ?? '–') ?></dd>
                <dt>Omgeving</dt>
                <dd><?= e($result['reference']->environment->label()) ?></dd>
                <dt>Wijk</dt>
                <dd><?= $result['wijk'] !== null ? e($result['wijk']['naam']) . ' <code>' . e($result['wijk']['code']) . '</code>' : '–' ?></dd>
                <dt>Deelgemeente</dt>
                <dd><?= $result['deelgemeente'] !== null ? e($result['deelgemeente']['naam']) . ' <code>' . e($result['deelgemeente']['code']) . '</code>' : '–' ?></dd>
                <dt>Gemeente (adres)</dt>
                <dd><?= e($result['locality'] ?? '–') ?></dd>
                <dt>Coördinaten</dt>
                <dd><?= e(sprintf('%F, %F', $result['latitude'], $result['longitude'])) ?></dd>
                <dt>Bron</dt>
                <dd><a href="<?= e($result['url']) ?>"><?= e($result['url']) ?></a></dd>
            </dl>
        </div>
    <?php endif; ?>

    <footer>
        Wijken voor omgevingsanalyse. © gemeentebesturen, verzameld door Data &amp; Analyse van de Vlaamse provincies.<br>
        Bron: <a href="https://github.com/provinciesincijfers/gebiedsniveaus">provinciesincijfers/gebiedsniveaus</a>
        (Modellicentie gratis hergebruik)<?php if (is_array($source)): ?>, opgehaald op <?= e(substr((string) $source['fetched_at'], 0, 10)) ?> (<?= e((string) $source['ref']) ?>)<?php endif; ?>.
    </footer>
</main>
</body>
</html>
