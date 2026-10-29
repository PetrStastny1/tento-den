# Experiment: kvóty a penalizace obecných článků (2026-10-29)

Jedno kolo s limitem 30 minut. Výsledek: **žádná varianta nebyla lepší na obou měřítkách, výběr
zůstává beze změny.** Kód variant byl po měření odstraněn.

## Varianty
- **V – výchozí stav:** aspoň 2 české, jinak podle skóre (nudné na konci, století jen při shodě).
- **K – pevné kvóty:** 2 z Česka + 3 ze světa (chybějící doplní druhá sekce).
- **P – penalizace obecných článků:** signál = nejlepší neobecný odkaz události se sitelinks
  × 1 / (1 + log10(n)), kde n = počet událostí za rok, které na článek odkazují.
  Vzorec stanoven před měřením.
- **K+P** – obojí.

## Měřítka
1. **Zlatá sada** – 8 známých výročí identifikovaných konkrétním textem (`backend/bin/report/selection-quality.php`).
2. **Stejných 12 dní jako v `docs/kvalita.md`** – žádná „správná odpověď“ neexistuje, proto ruční
   hodnocení: sjednocení všech karet ze všech variant (91 karet) ohodnoceno **naslepo**, bez
   informace o variantě: 2 = významná nebo zajímavá událost, 1 = průměrná, 0 = drobnost nebo nuda.
   Skóre varianty = součet za jejích 60 karet. **Hodnocení provedl AI asistent – je subjektivní,
   tabulka níže slouží ke kontrole.** (Při přepisu se u tří karet 6. 12. posunuly hodnoty;
   chyba byla nalezena a opravena před závěrem, závěr neměnila.)

## Výsledky

| Varianta | Zlatá sada | 12 dní (součet z 120) | hodnocení 2 / 0 |
|---|---|---|---|
| V – výchozí stav | 2 / 8 | **73** | 22 / 9 |
| K – kvóty | 2 / 8 | 70 | 21 / 11 |
| P – penalizace | **3 / 8** (+ Gagarin) | 68 | 17 / 9 |
| K+P | **3 / 8** | 68 | 18 / 10 |

Penalizace pomohla tam, kde hlavním článkem je kulisa, ale zároveň potlačila karty, jejichž
článek je oprávněně často odkazovaný (Galileo, koupě Aljašky, atentát na Reagana, Einstein,
Mozart, stavba Berlínské zdi). Kvóty pouze vyměnily české karty za slabší světové. Podle pravidla
„ponechat jen to, co je lepší na obou“ se nemění nic. Skutečné řešení je výběr hlavního článku
(předmět, ne kulisa) ověřený na hodnoticí sadě – v plné verzi.

## Hodnocení karet

V = výchozí, K = kvóty, P = penalizace, K+P = obojí – ve kterých variantách se karta objevila.

| Den | Rok | Sekce | Hodn. | Varianty | Text |
|---|---|---|---|---|---|
| 21. leden | 1720 | Svět | 1 | V, K | Švédsko a Prusko podepsaly mírovou dohodu ukončující mezi nimi Severní válku. |
| 21. leden | 1861 | Svět | 1 | P, K+P | Senátor Jefferson Davis oznámil odtržení státu Mississippi od Unie. |
| 21. leden | 1896 | Česko | 1 | V, K, P, K+P | Byl založen fotbalový klub SK Slavia Praha. |
| 21. leden | 1919 | Svět | 2 | V, K, P, K+P | V Dublinu byla prohlášena nezávislost Irska. |
| 21. leden | 1933 | Česko | 1 | P, K+P | V Brně došlo k nezdařenému pokusu o obsazení vojenských kasáren. Vyšetřování ukázalo, že útočníci vedení Ladislavem Kobzinkem pocházeli z ř… |
| 21. leden | 1941 | Svět | 1 | V, K | V Bukurešti byl potlačen státní převrat Železné gardy. |
| 21. leden | 1950 | Svět | 1 | P, K+P | USA oznámily objevení 93. chemického prvku. Podle místa objevu (Berkeley v Kalifornii) byl nazván berkelium. |
| 21. leden | 1997 | Česko | 2 | V, K | Ve dnech 21.–22. 1. německý spolkový kancléř Helmut Kohl podepsal během své oficiální návštěvy České republiky spolu s předsedou české vlád… |
| 26. leden | 1790 | Svět | 2 | V, K | Ve Vídni se konala premiéra Mozartovy opery Cosi fan tutte. |
| 26. leden | 1905 | Svět | 2 | P, K+P | V jihoafrickém dole byl nalezen největší diamant v historii o váze 3 106 karátů. Později získal jméno Cullinan. |
| 26. leden | 1915 | Svět | 1 | P, K+P | Založení Národního parku Skalnaté hory v americkém státě Colorado. |
| 26. leden | 1930 | Svět | 2 | V, K, P, K+P | V Indii začal boj o nezávislost. |
| 26. leden | 1934 | Svět | 2 | V, K | V Berlíně byla uzavřena smlouva o neútočení mezi nacistickým Německem a druhou Polskou republikou. |
| 26. leden | 1943 | Česko | 1 | P, K+P | V koncentračním táboře Mauthausen bylo popraveno dalších třicet odbojářů a jejich rodinných příslušníků, spolupracovníků výsadkové skupiny … |
| 26. leden | 1993 | Česko | 2 | V, K, P, K+P | Václav Havel byl zvolen prezidentem. |
| 26. leden | 2013 | Česko | 2 | V, K | Miloš Zeman byl jako první prezident České republiky volen přímou volbou. |
| 13. únor | 1306 | Česko | 0 | V, K | Po smrti Václava II. provdal Anežku Přemyslovnu její bratr, český a polský král Václav III., za korutanského vévodu Jindřicha |
| 13. únor | 1633 | Svět | 2 | V, K | Galileo Galilei přijel do Říma před katolický soud – inkvizici, aby obhajoval svou teorii o kulatosti a otáčení Země. |
| 13. únor | 1971 | Svět | 1 | P, K+P | Jihovietnamské vojenské jednotky, podporované Spojenými státy americkými, napadly v rámci Vietnamské války Laos. |
| 13. únor | 1992 | Česko | 2 | P, K+P | Oficiální připojení ČSFR k Internetu. Jako první bylo připojeno ČVUT v Praze. |
| 13. únor | 1996 | Svět | 1 | V, K | V Nepálu začala občanská válka vedená zdejšími maoisty proti monarchistickému státnímu zřízení. |
| 13. únor | 2001 | Svět | 1 | V, K, P, K+P | Zemětřesení o síle 6,6 Richterovy stupnici postihlo El Salvador, zabilo přinejmenším 400 lidí. |
| 13. únor | 2001 | Česko | 1 | P, K+P | Český film režiséra Jana Hřebejka Musíme si pomáhat (2000) byl nominován na Oscara v kategorii Nejlepší cizojazyčný film v rámci 73. ročník… |
| 13. únor | 2004 | Svět | 1 | P, K+P | Harvard-Smithsonian Center for Astrophysics zveřejnilo výsledky zkoumání hvězdy BPM 37093, podle nichž se jedná o největšího známého zkryst… |
| 13. únor | 2020 | Česko | 0 | V, K | Martina Sáblíková se stala mistryní světa v závodě na 3 000 metrů. Byl to její poslední 21. světový titul v rámci závodů mistrovství světa. |
| 1. březen | 1 př. n. l. | Svět | 1 | V, K | V Římě se začal používat upravený Juliánský kalendář. |
| 1. březen | 1453 | Česko | 2 | P, K+P | Ve vesnici Kunvald na česko-moravských hranicích vzniklo druhé nejstarší protestantské hnutí Moravští bratři (Unitas Fratrum). |
| 1. březen | 1785 | Česko | 2 | V, K | V pražském Klementinu byla naměřena teplota −27,6 °C, vůbec nejnižší teplota během měření v období 1775–2019. |
| 1. březen | 1867 | Svět | 1 | V, K, P, K+P | Nebraska se stává 37. státem USA. |
| 1. březen | 1870 | Svět | 1 | P, K+P | Paraguayská válka: smrtí paraguayského diktátora Francisca Solano Lópeze v bitvě u Cerro Corá končí šestiletý konflikt mezi Paraguayí a ali… |
| 1. březen | 1896 | Svět | 2 | P, K+P | První italsko-etiopská válka: Etiopané, vedení císařem Menelikem, porazili italské invazní vojsko v bitvě u Adwy. |
| 1. březen | 1992 | Svět | 1 | V, K | Muslimové a Chorvati se v referendu vyjádřili pro nezávislost Bosny. |
| 1. březen | 2020 | Česko | 2 | V, K, P, K+P | V Ústí nad Labem se objevily první 3 případy onemocnění covid-19 v Česku. |
| 30. březen | 1867 | Svět | 2 | V, K | Aljašku koupili USA od Ruského impéria za 7,2 milionu dolarů (4,19 dolaru/km²). |
| 30. březen | 1926 | Česko | 1 | V, K, P | První rozhlasový přenos koncertu z pražského Radiopaláce |
| 30. březen | 1965 | Svět | 1 | P, K+P | Během války ve Vietnamu explodovala automobilová bomba před velvyslanectvím Spojených států v Saigonu. Bylo zabito 22 lidí a 183 lidí bylo … |
| 30. březen | 1968 | Česko | 1 | V, K, P, K+P | Generál Ludvík Svoboda byl zákonodárnými orgány zvolen prezidentem ČSSR. |
| 30. březen | 1981 | Svět | 2 | V, K, K+P | Prezident USA Ronald Reagan byl střelen do hrudi před hotelem ve Washingtonu atentátníkem Johnem Hinckleym mladším. |
| 30. březen | 1983 | Svět | 1 | V, K | V Řecku byl uzákoněn pětidenní pracovní týden a osmihodinový pracovní den. |
| 30. březen | 2004 | Česko | 1 | P, K+P | Do výkonu základní vojenské služby v Armádě České republiky narukovali poslední branci. Jejich propuštěním do civilu 21. 12. přestala všeob… |
| 30. březen | 2006 | Svět | 1 | P, K+P | Marcos Pontes se stal prvním brazilským astronautem. |
| 31. březen | 1462 | Česko | 1 | V, K | Když české poselstvo požádalo papeže Pia II. o potvrzení kompaktát, papež je naopak zrušil. Znamenalo to, že každý, kdo je uznával, byl pov… |
| 31. březen | 1468 | Česko | 1 | V | Uherský král Matyáš Korvín vypověděl válku českému králi Jiřímu z Poděbrad a vtrhl do Čech na křižáckou výpravu za znovunastolení katolicis… |
| 31. březen | 1901 | Česko | 2 | V, K, P, K+P | Premiéra opery Antonína Dvořáka Rusalka v Národním divadle. |
| 31. březen | 1918 | Svět | 1 | V, K, P, K+P | USA uzákonily letní čas na který přešly všechny státy kromě Arizony. |
| 31. březen | 1997 | Svět | 0 | P, K+P | Jaromír Jágr hrál svůj 500. zápas v NHL (proti Floridě) |
| 31. březen | 2005 | Svět | 1 | V, K, P, K+P | Byla objevena trpasličí planeta Makemake. |
| 31. březen | 2005 | Svět | 0 | K | Komisař NHL Gary Bettman oznámil, že celá sezóna 2004–05 se kvůli probíhající stávce ruší. |
| 31. březen | 2005 | Česko | 0 | P, K+P | Státní zástupce obžaloval katarského prince Háinida bin Abdal Saního. S ním byly obžalovány tři mladé ženy za to, že mu dodávaly nezletilé … |
| 1. srpen | 1914 | Česko | 0 | V, K, P, K+P | V Českých Budějovicích byl po necelých pěti letech ukončen provoz I. generace trolejbusové dopravy. |
| 1. srpen | 1936 | Svět | 2 | V, K | V Berlíně byly zahájeny XI. Letní olympijské hry. |
| 1. srpen | 1960 | Svět | 1 | V, K, P, K+P | Benin získal nezávislost na Francii. |
| 1. srpen | 1973 | Česko | 0 | V, K, P, K+P | V Bohumíně byl ukončen provoz tramvají. |
| 1. srpen | 2007 | Svět | 1 | P, K+P | V minnesotském Minneapolis se zhroutil 579 metrů dlouhý most do řeky Mississippi. |
| 1. srpen | 2020 | Svět | 1 | V, K, P, K+P | Ve Spojených arabských emirátech byla spuštěna Jaderná elektrárna Baráka – první jaderná elektrárna na území arabského světa. |
| 6. srpen | 1825 | Svět | 1 | V, K, P, K+P | Bolívie získala nezávislost na Španělsku. Svou zemi nazvali po velkém hrdinovi jihoamerických bojů za nezávislost, Venezuelci Simónu Bolíva… |
| 6. srpen | 1861 | Svět | 1 | V, K, P, K+P | Spojené království anektovalo nigerijský Lagos jako britskou kolonii. |
| 6. srpen | 1865 | Česko | 1 | V, K, P, K+P | Na Vltavu byl spuštěn kolesový parník Praha, postavený v pražské Rustonce, aby zahájil pravidelnou osobní dopravu z Prahy do Štěchovic. |
| 6. srpen | 1962 | Svět | 1 | V, K | Jamajka získala nezávislost na Spojeném království. |
| 6. srpen | 1997 | Svět | 1 | P, K+P | Na tichomořském ostrově Guam se zřítil jihokorejský letoun Boeing 747-300 s 254 lidmi na palubě; katastrofu přežilo 26 osob, 228 zahynulo |
| 6. srpen | 2015 | Česko | 0 | V, K, P, K+P | Výbuch metanu v Dole Darkov na Karvinsku zabil 3 horníky |
| 13. srpen | 1732 | Svět | 1 | P, K+P | Premiéra Voltairova dramatu Zaïre v Paříži |
| 13. srpen | 1775 | Česko | 2 | V, K | Vydala Marie Terezie nový robotní patent, ve kterém stanovila maximální délku roboty na tři dny v týdnu. |
| 13. srpen | 1906 | Česko | 2 | P, K+P | Velkolepá show Buffalo Bill's Wild West Show uspořádala představení v Jihlavě na své cestě po Evropě |
| 13. srpen | 1960 | Svět | 1 | V, K, P, K+P | Středoafrická republika vyhlásila nezávislost na Francii |
| 13. srpen | 1961 | Svět | 2 | V, K | Komunistická vláda v NDR zahájila stavbu Berlínské zdi. |
| 13. srpen | 2004 | Svět | 1 | V, K | V Athénách byly zahájeny XXVIII. Letní olympijské hry. |
| 13. srpen | 2009 | Česko | 0 | V, K, P, K+P | Madonna opět navštívila Prahu. Tentokrát se svou Sticky and Sweet tour. |
| 13. srpen | 2015 | Svět | 1 | P, K+P | Při útoku islamistické skupiny Boko Haram na nigerijskou vesnici Kukuwa-Gari přišlo o život nejméně 150 lidí. |
| 16. září | 1597 | Česko | 2 | V | Rudolf II. povoluje stavbu Zlatnické uličky na pražském hradě. Nejmilostivěji vyhověl žádosti "střelců při branách Pražského hradu" kteří s… |
| 16. září | 1921 | Česko | 0 | V, K | Prezident Masaryk poprvé oficiálně navštívil Brno. V uvítací delegaci na brněnském nádraží byl i Leoš Janáček. |
| 16. září | 1933 | Česko | 1 | P | Divadlo D 34, vedené E. F. Burianem, zahájilo činnost v Mozarteu v Jungmanově ulici premiérou hry Erich Kästnera Život za našich dnů. |
| 16. září | 1975 | Svět | 1 | V, K, P, K+P | Papua Nová Guinea získala nezávislost na Austrálii. |
| 16. září | 1996 | Svět | 0 | K, K+P | Odstartoval raketoplán Atlantis v rámci mise STS-79, jejímž cílem bylo setkání s ruskou orbitální stanicí Mir. |
| 16. září | 2001 | Česko | 1 | V, K, P, K+P | Historicky první český pilot v závodě F1 – Tomáš Enge. |
| 16. září | 2018 | Svět | 1 | V, K, P, K+P | Eritrea a Etiopie podepsaly v Saúdské Arábii mírovou smlouvu týkající se sporných území. |
| 16. září | 2026 | Česko | 1 | P, K+P | Čeští zoologové poblíž Červenohorského sedla poprvé v historii odchytili netopýra obrovského, největšího představitele letounovitých savců … |
| 27. září | 1529 | Svět | 2 | P, K+P | Osmanský sultán Sulejman I. zahájil 1. turecké obléhání Vídně, které trvalo do 14. října téhož roku. |
| 27. září | 1654 | Svět | 2 | P, K+P | Mauzoleum Tádž Mahal, kterou nechal Šáhdžahán postavit pro svou ženu Mumtáz Mahal, po tom co zemřela při porodu 14. dítěte, konečně dokonče… |
| 27. září | 1905 | Svět | 2 | V, K | Albert Einstein v článku Závisí setrvačnost tělesa na energii v něm obsažené o speciální teorii relativity představil nejslavnější vzorec n… |
| 27. září | 1916 | Svět | 0 | V, K, P, K+P | Řecko vyhlásilo válku Bulharsku. |
| 27. září | 1922 | Svět | 1 | V, K | Řecký král Konstantin I. Řecký abdikoval na trůn ve prospěch svého nejstaršího syna Jiřího II. |
| 27. září | 1941 | Česko | 2 | V, K, P, K+P | Zastupujícím říšským protektorem v Čechách a na Moravě byl jmenován generál SS a policie Reinhard Heydrich, později přezdívaný pražský řezn… |
| 27. září | 2017 | Česko | 0 | V, K, P, K+P | Odstupující vláda podepsala před odchodem důležité memorandum o lithiu. Ministr průmyslu Jiří Havlíček (ČSSD) podepsal memorandum o porozum… |
| 6. prosinec | 1240 | Svět | 2 | P, K+P | Mongolové vedení Bátú-chánem dobyli a vyplenili Kyjev, čímž bylo završeno mongolské podmanění Rusi. Z 5000 obyvatel přežilo pouze 2000. |
| 6. prosinec | 1768 | Svět | 2 | V, K, P, K+P | V Edinburghu byl nabídnut k prodeji první díl Encyclopædia Britannica, který stál 6 pencí. |
| 6. prosinec | 1774 | Česko | 2 | V, K, P, K+P | Marie Terezie zavedla všeobecnou vzdělávací povinnost. |
| 6. prosinec | 1917 | Svět | 2 | V, K, P, K+P | Vznik státu Finsko na základě vyhlášení nezávislosti na Rusku. |
| 6. prosinec | 1989 | Svět | 1 | V, K | V kanadském Montrealu zastřelil Marc Lépine na École polytechnique čtrnáct osob a dalších čtrnáct zranil. |
| 6. prosinec | 2003 | Česko | 0 | P, K+P | Za vichřice padla Vopařilova jedle, památný strom a nejvyšší jedle bělokorá České republiky. |
| 6. prosinec | 2017 | Česko | 1 | V, K | Prezident Miloš Zeman jmenoval Andreje Babiše premiérem ČR. |
