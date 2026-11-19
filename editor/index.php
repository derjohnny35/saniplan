<?php
require __DIR__ . '/../lib/bootstrap.php';

seite_kopf('Bereitschaftsplan-Editor', '../', 'editor', 'seite-editor');
?>
<main id="app" class="inhalt">
    <noscript>Für den Editor muss JavaScript aktiviert sein.</noscript>
</main>
<?php
seite_fuss('../', 'editor',
    '<button type="button" class="link-knopf" onclick="window.dispatchEvent(new Event(\'anleitung-oeffnen\'))">Bedienungsanleitung</button>');
