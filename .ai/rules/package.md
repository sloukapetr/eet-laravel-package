# Pravidla pro vývoj balíčku eet-laravel-package

Balíček: `sloukapetr/eet-laravel-package`, namespace `SloukaPetr\Eet`. Univerzální Laravel balíček pro datové rozhraní EET 2.0 (SOAP v4.1) a obnovu pokladního certifikátu přes CAEET API. Podpora PHP `^8.5`, Laravel `^13`.

Jediný zdroj pravdy o rozhraní jsou soubory stažené z https://eet.gov.cz/cs/pro-vyvojare/dokumenty-ke-stazeni a uložené v `docs/` a `tests/fixtures/`. Jmenný prostor, `SOAPAction` a názvy elementů ber jen z `EETXMLSchema.xsd` a `EETServiceSOAP.wsdl`. Co ve specifikaci není, nevymýšlej a napiš jako otázku.

## Rozhraní v kostce
- Jmenný prostor zprávy: `http://fs.gov.cz/eet/schema/v4` (prefix ve vzorech `v4`). SOAP 1.1, dokumentový styl.
- `SOAPAction`: `http://fs.gov.cz/eet/OdeslaniTrzby` (WSDL `soap:operation`).
- Operace WSDL: `OdeslaniTrzby`. Požadavek nese element `Trzba` (`Hlavicka` + `Data`), odpověď element `Odpoved`.
- Playground: `https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4` (ve WSDL je stejná adresa s portem `:443`).
- Produkce: `https://trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4`.
- CAEET base URL ber z `docs/caeetapi/caeetapi_jwt.yml` (`servers`). Tři prostředí: test `https://ca.test.caeet.gov.cz/api`, zkušební `https://ca.zkus.caeet.gov.cz/api`, produkce `https://ca.caeet.gov.cz/api`. Které patří k playgroundu EET, není v XSD. Dokud to Petr neřekne, `caeet.base_url` zůstává prázdné a aplikace ho nastaví.

## Struktura
- `src/EetServiceProvider.php` – konfigurace. Fasáda a klient přijdou až ve fázi, která je posílá.
- `src/Data/` – readonly DTO.
- `src/Support/` – formát částky, času a vzory opsané z XSD (`SchemaPatterns`).
- `src/Validation/ReceiptValidator.php` – formáty tržby. Vyhodí `ValidationException`.
- `src/Certificate.php` – načtení PKCS#12. Soukromý klíč vrací jen `@internal` `privateKey()`.
- `src/Support/LegacyPkcs12Reader.php` – převod 3DES PKCS#12 přes `openssl pkcs12 -legacy` (Symfony Process / `proc_open`).
- `src/Exceptions/` – výjimky balíčku. Do textu výjimky nikdy nedávej heslo, klíč, certifikát ani XML s `BinarySecurityToken`.

## Veřejné API
- Veřejné API: config klíče `eet.*`, DTO `Receipt`, `SendOptions`, `EetResponse`, `Warning`, `AmountFormatter`, `DateTimeFormatter`, `ReceiptValidator`, `SchemaPatterns`, `ValidationException`, `Certificate` (`fromPkcs12`, `eic`, `serialNumber`, `notBefore`, `notAfter`, `thumbprintSha256`, `isValidAt`, `assertValidAt`, `toPkcs12`), výjimky `InvalidCertificatePassword`, `InvalidCertificate`, `CertificateExpired`.
- `Certificate::privateKey()` je `@internal` pro podpis. Aplikace ho nevolá a neloguje.
- `declare(strict_types=1);` v každém souboru, `final` u tříd, které nejsou k dědění, `readonly` u DTO.
- Změna signatury je major verze. Balíček zatím není vydaný.

## Peníze a data
- Částky v API balíčku jsou `int` v haléřích. Do zprávy je převádí `AmountFormatter` podle `CastkaType` v XSD: dvě desetinná místa, tečka, bez úvodních nul, nikdy `-0.00`, otevřený interval (−100000000, 100000000).
- `dat_trzby` a čas odeslání formátuj jako UTC `Y-m-d\TH:i:s\Z`, ať je `uniqueKey()` stejný pro stejný okamžik.
- `Receipt::uniqueKey()` je `eic|id_jednotky|id_pokl|porad_cis|dat_trzby|celk_trzba`.

## Bezpečnost
- Heslo k PKCS#12, soukromý klíč a XML obsahující `BinarySecurityToken` se nesmí dostat do logu, zprávy výjimky ani do gitu.
- Testovací `.p12` z playgroundu smí být ve `tests/fixtures/playground/`. Heslo k nim je jen v `.env.testing` (git ho ignoruje). Archiv `CAEET_Playground_2026_v1.zip` obsahuje soubor s heslem, proto je v `.gitignore`.
- Balíček certifikát na disk neukládá, kromě okamžitého převodu starého PKCS#12 ve fázi B. Dočasný soubor má práva 0600 a hned se smaže.

## Co balíček nedělá
- Žádné migrace, modely, fronty, notifikace ani rozhodování, kdy evidovat. To patří do aplikace.
- Žádné odkazy na konkrétní aplikaci.
- Neposílá dávky a sám odeslání neopakuje.

## Testy
- Po každé změně `vendor/bin/pest`, Pint a PHPStan.
- Testy nevolají síť. Integrační test proti playgroundu jen s `EET_PLAYGROUND_TEST=1` a až ve fázi, která umí zprávu odeslat.
- Vzory v `tests/fixtures/*.xml` jsou oficiální zprávy i s podpisem. Test je smí číst, nesmí je logovat.

## Otevřené body (nepřidávej chování, dokud nejsou rozhodnuté)
- Kód chyby `8` je v odpovědi dočasný (`isTemporaryError()`), protože aplikace z něj dělá trvalou chybu až po opakování. Kódy `2`, `3`, `4`, `6`, `7` jsou trvalé. Neznámý kód není ani jedno.
- `uuid` v `SendOptions` musí být UUID verze 4, i když XSD `UUIDType` pouští verze 1–5.
- Mapování `EET_ENVIRONMENT` na jedno z CAEET `servers` není vybrané.
