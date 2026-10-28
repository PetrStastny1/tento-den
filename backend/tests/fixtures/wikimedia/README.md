# Fixtures: odpovědi Wikimedia API

Skutečné odpovědi API Wikipedie a Wikimedia stažené **28. 10. 2026** při průzkumu zdrojů
([docs/zdroje.md](../../../../docs/zdroje.md)). Slouží jako vstup pro testy parserů a importu,
aby testy nevolaly síť.

## Licence

Texty v těchto souborech pocházejí z **české Wikipedie** (autoři: přispěvatelé Wikipedie)
a jsou šířené pod licencí **[CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.cs)**.
Soubory jsou **upravené**: zkrácené na menší počet položek, z HTML stránek dnů zůstala jen
sekce „Události“ bez obrázků a z JSON odpovědí byla odstraněna pole s obrázky
(`thumbnail`, `originalimage`) a souřadnice. Licence zdrojového kódu projektu se na tyto
soubory nevztahuje.

Historie autorů je dostupná u každého zdroje (odkaz „historie“).

## Soubory

| Soubor | Zdroj (revize) | Úprava |
|---|---|---|
| `day-page-10-28.html` | [28. říjen](https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&oldid=26193496) ([historie](https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&action=history)) – Parsoid HTML z `GET /api/rest_v1/page/html/28._říjen` | jen sekce Události, bez obrázků |
| `day-page-02-29.html` | [29. únor](https://cs.wikipedia.org/w/index.php?title=29._%C3%BAnor&oldid=26121894) ([historie](https://cs.wikipedia.org/w/index.php?title=29._%C3%BAnor&action=history)) – nejchudší den (3 + 3 události) | dtto |
| `day-page-12-08.html` | [8. prosinec](https://cs.wikipedia.org/w/index.php?title=8._prosinec&oldid=25732384) ([historie](https://cs.wikipedia.org/w/index.php?title=8._prosinec&action=history)) – jediná česká událost | dtto |
| `day-page-08-15.html` | [15. srpen](https://cs.wikipedia.org/w/index.php?title=15._srpen&oldid=26176901) ([historie](https://cs.wikipedia.org/w/index.php?title=15._srpen&action=history)) – roky bez odkazu (`* 1099 –`) | dtto |
| `day-page-05-22.html` | [22. květen](https://cs.wikipedia.org/w/index.php?title=22._kv%C4%9Bten&oldid=26047078) ([historie](https://cs.wikipedia.org/w/index.php?title=22._kv%C4%9Bten&action=history)) – 70 světových událostí, podnadpisy podle století, komentář v nadpisu | dtto |
| `day-page-12-31.html` | [31. prosinec](https://cs.wikipedia.org/w/index.php?title=31._prosinec&oldid=25994743) ([historie](https://cs.wikipedia.org/w/index.php?title=31._prosinec&action=history)) – vnořené podbody pod rokem | dtto |
| `day-page-02-07.html` | [7. únor](https://cs.wikipedia.org/w/index.php?title=7._%C3%BAnor&oldid=25712499) ([historie](https://cs.wikipedia.org/w/index.php?title=7._%C3%BAnor&action=history)) – šablona `{{0}}`, rok jako odkaz `[[1984 v dopravě\|1984]]` | dtto |
| `onthisday-cs-events-10-28.json` | `GET cs.wikipedia.org/api/rest_v1/feed/onthisday/events/10/28` | 9 z 31 událostí, max. 2 stránky na událost |
| `onthisday-cs-selected-10-28.json` | `GET …/feed/onthisday/selected/10/28` (zdroj: [Wikipedie:Vybraná výročí dne/28. říjen](https://cs.wikipedia.org/wiki/Wikipedie:Vybran%C3%A1_v%C3%BDro%C4%8D%C3%AD_dne/28._%C5%99%C3%ADjen)) | max. 2 stránky na položku |
| `onthisday-cs-events-02-29.json` | `events` z `GET …/feed/onthisday/all/02/29` | jen klíč `events` |
| `page-summary-vznik-ceskoslovenska.json` | `GET …/page/summary/Vznik_Československa` ([revize 26271507](https://cs.wikipedia.org/w/index.php?title=Vznik_%C4%8Ceskoslovenska&oldid=26271507), [historie](https://cs.wikipedia.org/w/index.php?title=Vznik_%C4%8Ceskoslovenska&action=history)) | bez obrázků |
| `action-query-info-pageprops-pageviews.json` | `GET cs.wikipedia.org/w/api.php?action=query&prop=info\|pageprops\|pageviews&redirects=1&titles=…` (11 titulů) | beze změny; obsahuje `continue` |
| `pageviews-daily-svehluv-most.json` | `GET wikimedia.org/api/rest_v1/metrics/pageviews/per-article/cs.wikipedia/all-access/user/Švehlův_most/daily/20260730/20261027` | beze změny (jen čísla) |
| `rate-limit-429.txt` | tělo odpovědi HTTP 429 z `cs.wikipedia.org/w/api.php` (hlavička `retry-after: 37`) | beze změny |
