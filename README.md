# MDU-E22E-ECOM

Kodeeksempler fra E-COMMERCE holdet i efteråret 2024.

## WP Playground med Networking

Her er et link til WP Playground, hvor repositories og themes kan installeres, eller i WP lingo **"networking=yes"**:

* [Playground With Networking](https://playground.wordpress.net/?storage=device&networking=yes)

Læs meget mere om Playground [her](https://wordpress.org/playground/)

## ./blueprint

Mappen indeholder en JSON fil med nogle gode plugins til WP.
# Hvad er Blueprints?

Blueprints bruges til opsætning af en lokal WordPress. De kan bruges i forbindelse med Playground eller loades, hvis du har installeret **wp-now** i node.js:

~~~~~
npx @wp-now/wp-now start --blueprint=pers-plugin-liste.json
~~~~~

## Sådan installeres wp-now

WordPress Playground bliver mere og mere populært blandt udviklere. Derfor kan det officielle link blive lidt ustabilt. En bedre løsning er at installere **wp-now** på din egen computer.

Læs denne [artikel på multimusen.dk](https://multimusen.dk/wp-now/)

Blueprints køres teoretisk set sådan i browseren:

~~~~~
https://playground.wordpress.net/#{"preferredVersions": {"php":"7.4", "wp":"5.9"}}
~~~~~

Så en WordPress med mine plugins burde kunne installeres sådan:

~~~~ 
https://playground.wordpress.net/#{"landingPage":"/wp-admin/","preferredVersions":{"php":"7.4","wp":"5.9"},"phpExtensionBundles":["kitchen-sink"],"steps":[{"step":"login","username":"admin","password":"password"},{"step":"installPlugin","pluginZipFile":{"resource":"wordpress.org\/plugins","slug":"instant-images"},"options":{"activate":true}},{"step":"installPlugin","pluginZipFile":{"resource":"wordpress.org\/plugins","slug":"w3-total-cache"},"options":{"activate":true}},{"step":"installPlugin","pluginZipFile":{"resource":"wordpress.org\/plugins","slug":"wordpress-seo"},"options":{"activate":false}},{"step":"installPlugin","pluginZipFile":{"resource":"wordpress.org\/plugins","slug":"ewww-image-optimizer"},"options":{"activate":true}},{"step":"installPlugin","pluginZipFile":{"resource":"wordpress.org\/plugins","slug":"blocks-animation"},"options":{"activate":true}},{"step":"installPlugin","pluginZipFile":{"resource":"wordpress.org\/plugins","slug":"font-awesome"},"options":{"activate":true}}]}
~~~~ 

## En bedre løsning

**wp-now** er i praksis lettere at anvende og noget mere stabil. Hvis du ofte bruger Playground er en lokal installation via Node.js klart den bedste løsning.

* [Installering af wp-now på Node.js](https://www.npmjs.com/package/@wp-now/wp-now) 