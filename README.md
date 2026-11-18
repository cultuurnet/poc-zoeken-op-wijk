# POC: wijk van een UiTdatabank-event

Geef een link naar een UiTdatabank-event (of enkel de UUID) en de POC toont in welke wijk en deelgemeente het event plaatsvindt. Hiervoor gebruikt de POC de geocoördinaten uit `location.geo` van het event.

## Vereisten

- Docker (met Docker Compose) en `make`. Alles draait in een `php:8.4-cli-alpine`-container.
- Geen Composer-pakketten, database of Elasticsearch nodig.

## Starten

```bash
make start            # http://127.0.0.1:8088 (andere poort: make start PORT=9000)
make down             # stoppen
make logs             # logs van de webserver volgen
make lookup EVENT=https://io-acc.uitdatabank.be/events/86ccacc0-f3cc-48b8-8d97-48b704cfe548
make geoshapes        # geodata opnieuw ophalen (REF=<sha> voor een vaste versie)
make                  # overzicht van alle commando's
```

Ontbreekt de geodata in `data/`, dan halen `make start` en `make lookup` die eerst op.

Zonder Docker kan het ook met PHP 8.1+ lokaal: `php -S 127.0.0.1:8088 -t public` en `php bin/lookup.php <url|uuid>`.

## Ondersteunde invoer

| Omgeving   | API-link (`io`)                              | Frontend-link                             |
|------------|----------------------------------------------|-------------------------------------------|
| Productie  | `https://io.uitdatabank.be/events/{uuid}`      | `https://uitdatabank.be/events/{uuid}`      |
| Acceptatie | `https://io-acc.uitdatabank.be/events/{uuid}`  | `https://acc.uitdatabank.be/events/{uuid}`  |
| Test       | `https://io-test.uitdatabank.be/events/{uuid}` | `https://test.uitdatabank.be/events/{uuid}` |

- Een losse UUID wordt **altijd op productie** opgezocht.
- Een frontend-link wordt omgezet naar de bijhorende `io`-link. Het event wordt altijd via de `io`-API opgehaald (zonder authenticatie).
- Extra padsegmenten na de UUID (bv. `/edit`) worden genegeerd. Links naar `/places/…` worden niet aanvaard.

## Werking

1. `EventReference` haalt de omgeving en de UUID uit de invoer.
2. `UitdatabankClient` haalt de JSON-LD van het event op en leest `location.geo`.
3. `AreaLocator` zoekt via point-in-polygon de wijk en de deelgemeente. Eerst wordt op bounding box gefilterd, daarna gebeurt ray casting op de (Multi)Polygonen.

Eén opzoeking duurt een paar milliseconden. Het inladen van de JSON (±7 MB) gebeurt per request, wat voor een POC volstaat. Voor productie kan je de data cachen (APCu, of een gegenereerd PHP-bestand voor opcache) of naar Elasticsearch verhuizen (`geo_shape`-veld met een `intersects`-query op een punt).

## Geodata

### Bron

[github.com/provinciesincijfers/gebiedsniveaus](https://github.com/provinciesincijfers/gebiedsniveaus), map `data_voor_swing/shapefiles/`:

| Laag          | Bronbestand        | Codevoorbeeld                    |
|---------------|--------------------|----------------------------------|
| Wijken        | `ggw7.txt`         | `33029W05` Wervik Centrum Zuid (Wervik) |
| Deelgemeenten | `deelgemeente.txt` | `33029A` Wervik (Dg.)            |

De `.txt`-bestanden zijn GeoJSON in WGS84, hetzelfde coördinatenstelsel als de UiTdatabank-coördinaten. De `.shp`-varianten in dezelfde map staan in Belgische Lambert 72 (EPSG:31370) en zouden eerst omgerekend moeten worden. Die gebruiken we dus niet. Lagen met een jaartal in de naam (bv. `ggw72024`) zijn historisch.

### Ophalen / bijwerken

De geodata wordt **niet** als volledige repo meegecloned (die is ±560 MB, waarvan we 2 bestanden nodig hebben). In plaats daarvan:

```bash
php bin/fetch-geoshapes.php                       # laatste versie (master)
php bin/fetch-geoshapes.php --ref=<commit-sha>    # vaste, reproduceerbare versie
php bin/fetch-geoshapes.php --source=../gebiedsniveaus   # vanuit een lokale clone
```

Het script downloadt enkel de nodige GeoJSON-bestanden, controleert het coördinatenstelsel en schrijft compacte JSON met een bounding box per gebied naar `data/`. In `data/source.json` staat welke versie wanneer werd opgehaald.

De bestanden in `data/` worden **mee gecommit**. Zo hangt een deploy niet af van GitHub, is elke wijziging aan de wijkgrenzen zichtbaar in een diff, en weet je altijd welke versie in productie draait.

### Halfjaarlijkse update

Provincies in Cijfers werkt de wijkindeling **halfjaarlijks** bij: gemeenten kunnen correcties of nieuwe wijkindelingen aanleveren, en gemeentefusies of nieuwe statistische sectoren leiden tot nieuwe indelingen. De huidige data bevat al de fusiegemeenten van 2025.

Plan daarom twee keer per jaar:

1. `php bin/fetch-geoshapes.php` uitvoeren.
2. De diff van `data/` en het aantal gebieden in `data/source.json` nakijken.
3. Committen en deployen.

Let op: wijkcodes kunnen bij een update wijzigen of verdwijnen. Sla je ooit wijkcodes op bij events, dan moet je die na een update herberekenen.

## Beperkingen

- **Enkel Vlaanderen en Brussel.** Wallonië valt buiten de wijkindeling. Voor zulke events (en events buiten België) toont de POC "Geen wijk". Dat staat ook op de pagina vermeld.
- **Vereenvoudigde geometrie.** De bronbestanden zijn vereenvoudigd voor online gebruik. Een punt vlak bij een wijkgrens kan daardoor in de verkeerde wijk vallen. Voor de POC aanvaarden we dat. Is meer precisie nodig, dan kan het via de onvereenvoudigde Statbel-sectoren (`onvereenvoudigde_shapefile_statsec_Statbel`, Lambert 72) in combinatie met de koppeltabel `data_voor_swing/aggregatietabellen/statsec_ggw7.xlsx`.
- **Twee soorten wijken.** Waar een gemeente zelf een wijkindeling aanleverde, wordt die gebruikt. Elders gaat het om een automatische indeling op basis van NIS7-codes. Beide worden gebruikt.
- **Geen wijknaam in een deel van de gemeenten.** 282 van de 2823 wijken (stand november 2026) zijn automatische NIS7-wijken zonder echte naam. Hun naam is de code zelf, bv. `11056A0 (Beveren-Kruibeke-Zwijndrecht)`. Ook de bronbestanden van Provincies in Cijfers hebben geen betere naam. Het gaat vooral om Oost-Vlaamse gemeenten die (nog) geen eigen wijkindeling aanleverden: Beveren-Kruibeke-Zwijndrecht, Ninove, Erpe-Mere, Oudenaarde, Kruisem, Lede, Oosterzele, Brakel, Sint-Gillis-Waas, Haaltert, Wetteren, Denderleeuw, Aalter en andere. Voor de POC tonen we die code. Een leesbare naam zou kunnen door de namen van de statistische sectoren in de wijk samen te voegen (`data_voor_swing/gebiedsdefinities/statsec.xlsx`), of door terug te vallen op de deelgemeente. Zodra zo'n gemeente zelf een wijkindeling aanlevert, komt die er via de halfjaarlijkse update vanzelf in.
- **Events zonder coördinaten.** Als `location.geo` ontbreekt (bv. online events of een locatie zonder geocodeerbaar adres), toont de POC een foutmelding die dat uitlegt.
- **"Niet te lokaliseren"-wijken** (`…WONB`) hebben geen geometrie en worden dus nooit gevonden.

## Licentie van de geodata

De wijkindeling wordt verdeeld onder de [Vlaamse modellicentie gratis hergebruik](https://github.com/provinciesincijfers/gebiedsniveaus/blob/master/gemeente_statsec_wijken/license.md). Die laat hergebruik toe voor elk rechtmatig doel, ook commercieel. Bij elk hergebruik moet deze bronvermelding verplicht vermeld worden:

> Wijken voor omgevingsanalyse. © gemeentebesturen, verzameld door Data & Analyse van de Vlaamse provincies

De POC toont die in de footer. Verder mag je niet de indruk wekken dat het gebruik officieel goedgekeurd is door de licentiegever.

**Nog na te gaan:**
- De licentie verwijst formeel naar de map `gemeente_statsec_wijken`. De geometrie die wij gebruiken komt uit `data_voor_swing/shapefiles`. Laat bevestigen dat de licentie ook voor die bestanden geldt, en ook voor de deelgemeente-laag.
- Of het geplande gebruik buiten deze POC binnen de licentie valt.

## Structuur

```
bin/fetch-geoshapes.php   geodata ophalen en omzetten naar data/
bin/lookup.php            opzoeking via CLI
data/                     gegenereerde geodata (gecommit)
public/index.php          webpagina
src/                      EventReference, UitdatabankClient, AreaLocator, WijkLookup
```
