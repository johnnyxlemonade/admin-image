# Lemonade Admin Image

\`johnnyxlemonade/admin-image\` poskytuje identifikátory obrázků, varianty a
veřejné doručování obrázků pro aplikace nad Lemonade Framework. Exponuje
\`ImageAssetResolverInterface\` pro resolvování zdrojů obrázků a registruje
veřejný endpoint pro doručení deklarované varianty.

Balíček nevlastní upload persistence, lifecycle souborů ani media catalog.
Tyto odpovědnosti náleží konzumující platformě nebo host aplikaci.

## Instalace

Balíček vyžaduje PHP \`>=8.3 <8.6\` a \`johnnyxlemonade/framework\`.

    composer require johnnyxlemonade/admin-image:dev-main

## Vývoj a QA

Kontroly z rootu balíčku:

    composer cs:check
    composer stan
    composer test
    composer qa
