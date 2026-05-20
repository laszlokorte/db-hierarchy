<?php

$dir = new DirectoryIterator(dirname(__FILE__).'/public/icons');

$dom = new DOMDocument('1.0', 'utf-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;

$svg = $dom->createElementNS('http://www.w3.org/2000/svg', 'svg');
$defs = $dom->createElement('defs');

$dom->appendChild($svg);
$svg->appendChild($defs);

foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $iconName = substr($fileinfo->getFilename(), 0, -4);

        $xmlDoc = new DOMDocument();
        $xmlDoc->load($fileinfo->getPathname());

        foreach ($xmlDoc->getElementsByTagName('svg') as $root) {
            $imported = $dom->importNode($root, true);
            $imported->setAttribute('id', $iconName);
            $defs->appendChild($imported);
        }
    }
}

echo $dom->saveXML();
