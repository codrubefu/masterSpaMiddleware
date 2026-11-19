# masterSpaMiddleware — Functionalitati

Middleware Laravel 10 intre site-ul WooCommerce si baza de date SQL Server a aplicatiei Acord (SPA + Hotel).
Primeste comenzile de pe site, creeaza clientii, rezervarile si documentele (nota de informare, nota de plata, factura),
apoi trimite emailurile (vouchere, factura, confirmare).

Documentul e intretinut de agentul `function-documenter` (`.claude/agents/function-documenter.md`).

## Istoric modificari

- 2026-11-19 — NOU — documentatie initiala: toate functionalitatile existente.
- 2026-11-19 — UPDATE — 5. Clienti din comenzi web: `findOrCreate` scrie ' ' (un spatiu) in loc de NULL pentru adresa1, adresa2, oras, judet, tara; metoda noua `blankIfEmpty`.
- 2026-11-19 — UPDATE — 5. Clienti din comenzi web: metoda noua `applyDefaults` (campuri implicite `' '`, `conthotel = 0`, `origin = 'Romania'`) apelata din `findOrCreate` si `findOrCreateCompany`; `banca`/`iban` PJ din comanda cu `blankIfEmpty`.

- 2026-11-19 — UPDATE — 5. Clienti din comenzi web: metoda noua `syncClhead` (clhead = spaid pentru PF, PJ si `assignHotel`); `findOrCreateCompany(array)` fara `$spaid`, legatura PJ -> PF prin `clhead` nu mai exista.

## Cuprins

1. [Comenzi SPA](#1-comenzi-spa)
2. [Comenzi Hotel](#2-comenzi-hotel)
3. [Pachete web (nota de informare)](#3-pachete-web-nota-de-informare)
4. [Nota de plata + factura](#4-nota-de-plata--factura)
5. [Clienti din comenzi web](#5-clienti-din-comenzi-web)
6. [Emailuri si PDF-uri](#6-emailuri-si-pdf-uri)
7. [Cautare camere disponibile](#7-cautare-camere-disponibile)
8. [Import produse pe site (genprod)](#8-import-produse-pe-site-genprod)
9. [API CRUD: camere, clienti, rezervari](#9-api-crud-camere-clienti-rezervari)
10. [Securitate API](#10-securitate-api)
11. [Helpers: TVA, suma in litere, judete, tari](#11-helpers-tva-suma-in-litere-judete-tari)
12. [Infrastructura: SCOPE_IDENTITY, log salvari modele, pagini de test](#12-infrastructura-scope_identity-log-salvari-modele-pagini-de-test)
- [Anexe](#anexe)

---

## 1. Comenzi SPA

**Scop:** Salveaza o comanda SPA venita din WooCommerce: clienti (cumparator + beneficiari), pachete web sau nota de plata/factura, apoi trimite voucherele pe email.

**Endpoint(uri):** `POST /api/order/spa` (`api.secret`) -> `Api\OrderSpaController@save`

**Flux:**
1. `SpaOrderRequest` valideaza payload-ul (reguli comune `OrderRequest` + `id` obligatoriu). La eroare raspunde JSON 422.
2. `OrderSpaService::parseOrderInfo()` extrage beneficiarii din `items.*.meta_data` (`Abonat - Nume [1]`, `Abonat - Email [1]`, ...) in `items.*.clients` si genereaza numarul de voucher pentru fiecare.
3. In `DB::transaction`:
   - cumparatorul: `ClientResolver::findOrCreate()`; firma (daca `_billing_company_details = 1`): `resolveCompany()`;
   - fiecare beneficiar cu email devine client (`beneficiar_id`);
   - fiecare item e impartit: daca `PackageBillService::findPackage()` gaseste produsul in `genprod` -> **pachet**, altfel -> **altele**;
   - pachetele -> `PackageBillService::createBill()` (trzbill + trzdetbill + detaliitrz), `nrbill` pus in comanda;
   - altele + `CREATE_NP_AND_INVOICE=true` -> `trznp` + `trzfact` + linii (vezi sectiunea 4); `trzcard` pentru fiecare item.
4. Dupa commit: `OrderMailer::sendVouchers()`. O eroare la email e doar logata (comanda ramane salvata, raspuns 200).

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `OrderSpaController::save()` | Apeleaza serviciul, prinde orice exceptie | `SpaOrderRequest` -> JSON `true` / 500 `{success:false,message}` | Trimite `$request->all()` (payload complet, nu doar validat) |
| `OrderSpaService::saveOrder()` | Orchestreaza toata comanda SPA | `array $orderInfo` -> `true` | Scrierile DB sunt atomice; emailurile dupa commit |
| `OrderSpaService::parseOrderInfo()` (private) | Beneficiari din meta_data + numere voucher | `array` -> `array` | Item fara meta_data -> un beneficiar gol. Voucher = `order_id . rand(1000,9999) . index` |
| `OrderSpaService::createTrzcard()` (protected) | Insereaza un rand `trzcard` din item | `array` -> `Trzcard` | Vezi TODO |

**Tabele DB:** `client` (scriere), `trzbill`/`trzdetbill`/`detaliitrz` (pachete), `trznp`/`trzdetnp`/`trzdet`/`trzfact`/`trzdetfact` (cand e activ), `trzcard`.

**Config / env:** `orders.np_invoice.enabled` (`CREATE_NP_AND_INVOICE`, default `false`), `orders.packages.filters` (`ORDER_PACKAGE_FILTERS`).

**Teste:** `tests/Feature/OrderValidationTest.php`, `tests/Unit/Orders/OrderHelpersTest.php`.

**De stiut / TODO:**
- `createTrzcard()` face `fill()` cu cheile WooCommerce, care nu sunt `fillable` -> rand gol in `trzcard`; trebuie mapare de campuri (`app/Services/OrderSpaService.php:78`).
- Cand exista si pachete si altele, totalul NP/factura e suma `subtotal` a celorlalte; altfel `order.total`.
- Numarul de voucher contine `rand()` -> nu e garantat unic.

---

## 2. Comenzi Hotel

**Scop:** Salveaza o rezervare de hotel venita din WooCommerce: alege camera libera, creeaza `rezervarehotel`, optional nota de plata + factura, si trimite emailul.

**Endpoint(uri):** `POST /api/order` (`api.secret`) -> `Api\OrderHotelController@save`

**Flux:**
1. `HotelOrderRequest` valideaza: reguli comune + `custom_info.start_date/end_date` (end > start), `items.*.product_meta_input._hotel_id/_hotel_room_number/_hotel_room_type_long/_hotel_room_type`, `items.*.meta_data.0.value` (numele pachetului).
2. In `DB::transaction`, pentru fiecare item:
   - lista de camere din `_hotel_room_number` (separate prin virgula), fara cele deja alese in aceeasi comanda;
   - `RezervareHotelService::getRoomNumber()` -> prima camera libera; daca nu e niciuna -> exceptie, rollback complet;
   - `ClientResolver::assignHotel()` seteaza `client.hotel`;
   - `createRezervarehotel()`;
   - daca NP/factura e activ: un singur `trznp` + `trzfact` pe comanda (legat de prima rezervare), `rezervare.nrnp`, linii NP si factura per item.
3. Dupa commit: cu factura -> `OrderMailer::sendHotelInvoice()`; fara -> `sendHotelBooking()` (confirmare simpla). Erorile la email sunt doar logate.

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `OrderHotelController::save()` | Apeleaza serviciul, prinde exceptiile | `HotelOrderRequest` -> JSON `true` / 500 | |
| `OrderHotelService::saveOrder()` | Orchestreaza rezervarea | `array $orderInfo, RezervareHotelService` -> `true` | Atomic; email dupa commit |
| `OrderHotelService::createRezervarehotel()` (private) | Insereaza `rezervarehotel` | client, booking info, tip camera, nopti, pret, camera, hotel, pachet -> `Rezervarehotel` | Check-in 14:00, check-out 11:00. `single` in pachet -> 1 adult, altfel `adultmax`. `dejun` -> `tipmasa = 'MD inclus'`. Arunca daca camera nu exista |
| `RezervareHotelService::getRoomNumber()` | Camerele din lista care nu se suprapun cu rezervari existente | `array $roomsIds, checkIn, checkOut, int $hotelId` -> `array` / `null` | Suprapunere `[datas, dataf)` cu `[checkIn, checkOut)` |
| `RezervareHotelService::searchBookedRooms()` (protected) | Camerele ocupate in interval | -> `Collection` de `camera` | |
| `RezervareHotelService::getNextId()` | `max(idrezervarehotel) + $y` | `int` -> `int` | Nefolosit in fluxul de comanda |

**Tabele DB:** `rezervarehotel` (citire/scriere), `camerehotel` (citire `adultmax`, `kidmax`), `client` (`hotel`, `clhead`), plus tabelele din sectiunea 4.

**Config / env:** `orders.hotels` (idhotel -> valoare `client.hotel`), `orders.default_hotel`, `orders.idfirma` (`ORDER_IDFIRMA`), `orders.np_invoice.enabled`.

**Teste:** `tests/Feature/OrderValidationTest.php`.

**De stiut / TODO:**
- `pret = subtotal / quantity` e scris in `pretcamera`, `pretnoapte`, `prettipmasa` si `total` (aceeasi valoare).
- `nrcopii = kidmax` daca `custom_info.kids != 0`, nu numarul real de copii.

---

## 3. Pachete web (nota de informare)

**Scop:** Produsele din `genprod` cu clasa/grupa configurata (default `WEB/PACK`) se vand pe site si, la comanda, se scriu ca nota de informare/rezervare (`facturat = 0`). Receptia face apoi nota de plata in aplicatia Acord.

**Endpoint(uri):** folosit din `POST /api/order/spa`; listarea pentru site in sectiunea 8.

**Flux:**
1. `findPackage()` cauta produsul dupa nume (`genprod.art`, cu TRIM) intre pachetele configurate active; daca nu e acolo, orice produs `genprod` cu acel nume (activ si cel mai nou primul) devine pachet de sine statator.
2. `lines()` pentru fiecare pachet comandat:
   - componentele din `gencard` (fara componente -> produsul insusi, o singura linie);
   - pretul: `pret` (cel mai recent dupa `data`) > suma `gencard.val` > pretul de pe site (cu warning in log);
   - pretul se imparte proportional pe componente (`allocate()`), inmultit cu cantitatea.
3. `createBill()`: header `trzbill` (total + TVA calculat per linie cu cota fiecarui produs), apoi pentru fiecare linie un `trzdetbill` si un `detaliitrz`, legate prin `nrbill`.

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `PackageBillService::filters()` | Perechile clasa/grupa configurate | -> `array` | Din `ORDER_PACKAGE_FILTERS` |
| `PackageBillService::packagesQuery()` | Query `genprod` restrans la filtre | -> `Builder` | Fara filtre -> `1 = 0` (nimic). TRIM pe clasa/grupa |
| `PackageBillService::findPackage()` | Pachetul pentru un item WooCommerce | `array $item` -> `?Genprod` | Fallback la orice produs cu acel nume |
| `PackageBillService::packagePrice()` | Pretul pachetului din `pret` | `Genprod` -> `?float` | Are prioritate fata de suma componentelor |
| `PackageBillService::components()` | Componentele `gencard` + cota TVA | `Genprod` -> `Collection` | Arunca daca o componenta nu exista in `genprod` |
| `PackageBillService::createBill()` | Scrie trzbill + trzdetbill + detaliitrz | `Client, array $packages, $orderId` -> `Trzbill` | Trebuie rulat in tranzactia comenzii |
| `PackageBillService::lines()` (private) | Liniile unui pachet comandat | `Genprod, array $item` -> `array` | Logheaza warning cand pretul site != `pret` |
| `PackageBillService::createDetBill()` (private) | Un rand `trzdetbill` | -> `Trzdetbill` | |
| `PackageBillService::createDetaliiTrz()` (private) | Un rand `detaliitrz` | -> `Detaliitrz` | `facturat=0`, `extra=1`, `card=clasa` (ca la nota manuala) |
| `PackageBillService::allocate()` (static) | Imparte un total proportional, ultima componenta preia rotunjirea | `float $total, float[] $values` -> `float[]` | Suma valorilor = total, exact |
| `PackageBillService::vatRate()` (static) | Cota TVA a produsului (primul `tax1..tax5` > 0) | produs -> `float` (fractie) | |
| `PackageBillService::vatOf()` (static) | TVA dintr-o suma cu TVA | `float $gross, float $rate` -> `float` | |

**Tabele DB:** `genprod`, `gencard`, `pret` (citire); `trzbill`, `trzdetbill`, `detaliitrz` (scriere).

**Config / env:** `orders.packages.filters` (`ORDER_PACKAGE_FILTERS`, default `WEB:PACK`, format `CLASA:GRUPA,CLASA2:GRUPA2`), `orders.idfirma`.

**Teste:** `tests/Unit/Orders/PackageBillTest.php`.

**De stiut / TODO:**
- `nrbill` vine din IDENTITY-ul `trzbill`; de confirmat cu Acord daca notele web au nevoie de serie/contor propriu (`app/Services/Orders/PackageBillService.php:144`).
- Coloanele legacy sunt CHAR cu spatii -> toate comparatiile folosesc `RTRIM(LTRIM(...))`.

---

## 4. Nota de plata + factura

**Scop:** Creeaza nota de plata (`trznp` + `trzdetnp` + `trzdet`) si factura (`trzfact` + `trzdetfact`) pentru comenzile web. Se activeaza cu `CREATE_NP_AND_INVOICE`.

**Flux:**
1. `createNp()` — header NP cu total, TVA, `descval` (suma in litere).
2. `createInvoice()` — rezerva numarul de factura (`reserveInvoiceNo()`), creeaza `trzfact`, leaga `trznp.nrfact`.
3. Per item: `createNpLine()` (+ `trzdet`) si `createInvoiceLine()`.

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `NpInvoiceService::enabled()` | Trebuie create NP + factura? | -> `bool` | `orders.np_invoice.enabled` |
| `NpInvoiceService::reserveInvoiceNo()` | Urmatorul numar de factura din `gest.nrf` | -> `string` ex. `FA25100042` | `lockForUpdate` pana la commit -> fara duplicate concurente. Format `FA` + `yy` + `nrgest` + 5 cifre |
| `NpInvoiceService::createNp()` | Header `trznp` | `Client, float $total, $idrezervarehotel` -> `Trznp` | `modp = 'Bank Card Web'`, `tip = 'WEBSITE'` |
| `NpInvoiceService::createNpLine()` | `trzdetnp` + `trzdet` | `Client, Trznp, subtotal, cant, idrezervare, art` -> `Trzdetnp` | `tipf = 'Hotel'`, `cotatva = Vat::fraction()` |
| `NpInvoiceService::createInvoice()` | Header `trzfact` | `Client, float $total, Trznp` -> `Trzfact` | `stotalron = Vat::net`, `tva = Vat::vat` |
| `NpInvoiceService::createInvoiceLine()` | Linie `trzdetfact` | `Client, Trzfact, subtotal, cant, item, roomType, spa` -> `Trzdetfact` | Clasa/grupa din `genprod` (spa, dupa nume) sau `pret` (hotel, dupa tip camera). Arunca daca lipseste sursa |
| `NpInvoiceService::createTrzdet()` (private) | Detaliul de tranzactie al liniei NP | `Trzdetnp` -> `Trzdet` | `tip = 'NP'` |

**Tabele DB:** `gest` (`nrf`), `trznp`, `trzdetnp`, `trzdet`, `trzfact`, `trzdetfact`, `genprod`/`pret` (citire).

**Config / env:** `CREATE_NP_AND_INVOICE`, `ORDER_NR_GEST`, `ORDER_IDFIRMA`, `ORDER_VAT_RATE`.

**Teste:** `tests/Unit/Orders/OrderHelpersTest.php` (helpers).

**De stiut / TODO:**
- Valori fixe: `tipcc = 'VISA'`, `nrtrzcc = '1234****3456'`, `cnp = '000000000000'`.
- `trzdetfact.art` = `item.meta_data[0].value`, nu numele produsului.

---

## 5. Clienti din comenzi web

**Scop:** Gaseste sau creeaza clientii unei comenzi: persoana fizica (dupa email + telefon) si, optional, firma (dupa CUI).

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `ClientResolver::findOrCreate()` | Client PF dupa `email` + `mobilcontact` | `array $clientInfo` -> `Client` | Obligatorii: `last_name`, `first_name`, `phone`, `email`. Apeleaza `applyDefaults()` inainte de campurile proprii; dupa `save()` apeleaza `syncClhead()` (`clhead = spaid`). Suprascrie datele clientului existent. `judet`/`tara` din coduri. `adresa1`, `adresa2`, `oras`, `judet`, `tara` lipsa/goale (inclusiv cod negasit) se salveaza ca `' '`, nu NULL |
| `ClientResolver::applyDefaults()` (privata) | Valori implicite Acord pe orice client web (PF si PJ) | `Client` -> `void` | `reg, cp, tel, fax, banca, iban, profesie, nrvoucher, institutia, cardid, obs, pasaport, numevip, mobilvip, emailvip, sursamkt, canalmkt, idpartener = ' '`; `conthotel = 0`; `origin = 'Romania'`. Se aplica inainte de campurile specifice, deci suprascrie si clientul existent gasit la match (inclusiv `banca`/`iban`/`obs`/`tel` deja completate) |
| `ClientResolver::syncClhead()` (privata) | Seteaza `clhead = spaid` (id propriu) daca difera | `Client` -> `void` | Apelata dupa `save()` in `findOrCreate`, `findOrCreateCompany`, `assignHotel`. `spaid` e cunoscut doar dupa insert -> la clientii noi e un `UPDATE` suplimentar (al doilea `save()`); la clientii existenti cu `clhead` deja egal nu face nimic |
| `ClientResolver::blankIfEmpty()` (privata) | Inlocuieste NULL / sir gol / doar spatii cu `' '` | `$value` -> `string` | Acord asteapta un spatiu, ca la clientii creati manual. Folosita in `findOrCreate` (adresa) si in `findOrCreateCompany` (doar `banca`, `iban`) |
| `ClientResolver::findOrCreateCompany()` | Client PJ dupa `_billing_cui` | `array $clientInfo` -> `Client` | `pj = 1`; `clhead = spaid` propriu prin `syncClhead()` (nu mai pointeaza la persoana fizica). Apeleaza `applyDefaults()`. `banca` din `_billing_banca`, `iban` din `_billing_cont_iban`, cu `' '` daca lipsesc. `adresa1`, `oras`, `judet`, `tara`, `nrc` nu folosesc `blankIfEmpty` (pot ramane NULL) |
| `ClientResolver::resolveCompany()` | Firma doar daca `_billing_company_details = 1` | `array, Client` -> `?Client` | Apeleaza `findOrCreateCompany($clientInfo)`; parametrul `Client $person` nu mai e folosit |
| `ClientResolver::assignHotel()` | Seteaza `client.hotel` dupa idhotel | `Client, $hotelId` -> `Client` | Dupa `save()` apeleaza `syncClhead()` (`clhead = spaid`, self) |
| `ClientService::createOrUpdateClient()` | Creeaza client doar cu den/prenume | -> `Client` | Legacy, neapelat in fluxurile de comanda |

**Tabele DB:** `client` (citire/scriere).

**De stiut / TODO:**
- Regula: `clhead` = `spaid` pentru toti clientii creati/actualizati din comenzi web (PF, PJ, hotel). Legatura PJ -> PF prin `clhead` nu mai exista; un PJ existent care avea `clhead` = id-ul unei persoane primeste `clhead` = propriul `spaid` la prima comanda cu acea firma.
- `syncClhead()` face un `save()` in plus la clientii noi (INSERT, apoi UPDATE); cele doua `save()` sunt logate separat in canalul `model_saves`.
- `Client` foloseste `UsesScopeIdentity` (triggerul de audit strica `@@IDENTITY`, vezi sectiunea 12).
- `applyDefaults()` rescrie la fiecare comanda campurile din lista pe clientul existent (`app/Services/Orders/ClientResolver.php:37`, `:123`); datele introduse manual in Acord pe `tel`, `banca`, `iban`, `obs` etc. se pierd. La PJ, `banca`/`iban` se rescriu apoi din comanda (`:137-138`).
- Campurile de adresa PJ (`adresa1`, `oras`, `judet`, `tara`) nu trec prin `blankIfEmpty` (`:130`, `:139-141`); `Judet::getNameByCode`/`Country::getNameByCode` primesc direct valoarea din comanda.

---

## 6. Emailuri si PDF-uri

**Scop:** Genereaza PDF-uri (factura, vouchere) si trimite emailurile comenzilor. Se apeleaza doar dupa commit; erorile sunt logate, nu aruncate (altfel WooCommerce retrimite comanda).

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `OrderMailer::sendHotelInvoice()` | PDF factura + email rezervare | `order, clientInfo, invoiceNo, nrnp` -> `void` | |
| `OrderMailer::sendHotelBooking()` | Email client (BCC staff) + sumar separat la staff | `order, ?invoiceFile` -> `void` | Fara fisier -> template `emails.booking_confirmation` |
| `OrderMailer::sendVouchers()` | Un PDF voucher per beneficiar; beneficiarii cu `send` primesc al lor, cumparatorul le primeste pe toate | `order` -> `void` | Staff in copie |
| `OrderMailer::generateInvoicePdf()` (private) | PDF `invoice` in `storage/invoices/yy/mm` | -> path | Date firma din `company` |
| `OrderMailer::generateVoucherPdf()` (private) | PDF `emails.voucher_pdf` A4 landscape in `storage/vouchers/yy/mm` | -> path | |
| `OrderMailer::storageDir()` (private) | Creeaza `storage/<tip>/<yy>/<mm>` | -> path | |
| `OrderMailer::deliver()` (private) | Send sau queue, logheaza erorile | `to, OrderMail, bcc` -> `void` | `ORDER_MAIL_QUEUE=true` necesita `queue:work` |
| `OrderMail` (mailable) | Subiect + view + date + atasamente | | Proprietatile nu sunt expuse direct in Blade |
| `VoucherPreviewController::show()` | Preview PDF voucher in browser | query `first_name, last_name, voucher_no, date` -> PDF | `GET /voucher-preview` si `GET /api/spa/preview` |
| `TestEmailController::sendTestEmail()` / `sendTestEmailToCustom()` | Email de test | | `POST /api/test-email/send[/{email}]` |

**Config / env:** `orders.mail.*` (`ORDER_MAIL_QUEUE`, `CLIENT_NAME`, `ORDER_HOTEL_MAIL_SUBJECT`, `ORDER_HOTEL_MAIL_STAFF`, `ORDER_VOUCHER_MAIL_STAFF`).

**Teste:** `tests/Unit/Orders/OrderMailerTest.php`.

**De stiut / TODO:** `/api/spa/preview` este in afara grupului `api.secret` (public).

---

## 7. Cautare camere disponibile

**Scop:** Pentru un numar de adulti/copii, interval si numar de camere, intoarce combinatiile de camere libere, grupate pe tip si sortate dupa pret, paginat.

**Endpoint(uri):** `POST /api/rooms/search-combinations` (`api.secret`) -> `Api\roomSearch@searchAvailableRoomCombinations`. Formular de test: `GET/POST /room-search-test`.

**Flux:**
1. Toate camerele (`camerehotel` + `pret`).
2. Imparte adultii/copiii cat mai egal pe camere.
3. Exclude camerele cu rezervari suprapuse pe `[start, end)`.
4. Genereaza combinatiile de N camere care incap persoanele, le grupeaza pe tipuri (prima combinatie per tip), sorteaza dupa pret.
5. Intercaleaza hotel 2 / hotel 1, combinatiile mixte la final; paginare.

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `roomSearch::searchAvailableRoomCombinations()` | Citeste parametrii (`adults, kids, start_date, end_date, number_of_rooms, page, per_page`) | `Request` -> JSON | |
| `RoomSearchService::searchAvailableRoomCombinations()` | Algoritmul de mai sus | -> `['data' => [...], 'pagination' => [...]]` | |
| `RoomSearchService::distributePeoplePerRoom()` (private) | Distributie egala pe camere | -> `array` | |
| `RoomSearchService::getRoomCombinations()` (private) | Combinari de N camere care respecta capacitatea | -> `array` | Complexitate combinatorica |
| `RoomSearchService::groupRooms()` (private) | Grupeaza pe tipuri, sorteaza dupa pret | -> `array` | |

**Tabele DB:** `camerehotel`, `pret`, `rezervarehotel` (citire).

**De stiut / TODO:** rezervarile ocupate nu sunt filtrate pe `idhotel` (numerele de camera se compara intre hoteluri).

---

## 8. Import produse pe site (genprod)

**Scop:** Expune produsele `genprod` pentru importul pe site si CRUD de baza.

**Endpoint(uri)** (toate `api.secret`):
- `GET /api/genprod/spa/only` -> `GenprodController@onlySpa`
- `GET /api/genprod/packages` -> `GenprodController@packages` (inaintea `apiResource`, altfel o prinde `genprod/{id}`)
- `apiResource /api/genprod` -> `index, show, store, update, destroy`

**Functii:**

| Functie | Ce face | Intrare -> Iesire | Observatii |
|---|---|---|---|
| `GenprodController::onlySpa()` | Produsele active din filtrele de pachete, cu `pret` | -> JSON | Acelasi filtru ca la comenzi (`ORDER_PACKAGE_FILTERS`) |
| `GenprodController::packages()` | Pachetele active cu `pret` (din `packagePrice`) si `components` | -> JSON | Scoate coloana binara `imagine` |
| `GenprodController::index/show/store/update/destroy` | CRUD | | `store/update` cu `$request->all()` |
| `Genprod::pret()` | Relatie cu `pret` | | |

**Config / env:** `ORDER_PACKAGE_FILTERS`.

**Teste:** `tests/Unit/Orders/PackageBillTest.php` (query-ul de filtre).

---

## 9. API CRUD: camere, clienti, rezervari

**Scop:** Endpoint-uri REST peste tabelele `camerehotel`, `client`, `rezervarehotel` (toate `api.secret`).

| Controller | Endpoint-uri |
|---|---|
| `Api\CamerehotelController` | `apiResource camerehotel`; `GET camerehotel/grouped-by-type`, `virtual/list`, `baby-beds/list`, `hotel/{hotelId}`, `type/{type}`, `hotel/{hotelId}/floor/{floor}`, `{id}/statistics`; `POST camerehotel/search-capacity` |
| `Api\ClientController` | `apiResource clients`; `POST clients/search`; `GET clients/vip/list`, `{id}/reservations`, `{id}/statistics`, `employees/list`, `company/{companyId}`; `PATCH clients/{id}/vip-status`, `{id}/employee-status` |
| `Api\RezervarehotelController` | `apiResource reservations`; `PATCH reservations/{id}/checkin`, `checkout`, `payment`, `cancel`; `GET reservations/room/{roomNumber}`, `statistics/overview`, `arrivals/today`, `departures/today` |

**Scope-uri model:**
- `Camerehotel`: `byHotel`, `byFloor`, `minCapacity`, `totalCapacity`, `virtual`, `withBabyBed`; atribute `total_capacity`, `is_virtual`, `has_baby_bed`; relatii `rezervari`, `pret`.
- `Client`: `active`, `vip`, `byEmail`, `byPhone`, `byName`; atribute `full_name`, `is_vip`; relatie `rezervari`.
- `Rezervarehotel`: `active`, `forDateRange`, `forRoom`; relatie `client`.

**De stiut / TODO:** sectiune sumara; detaliile per endpoint se completeaza la urmatorul UPDATE pe aceste controllere.

---

## 10. Securitate API

**Functii:**

| Functie | Ce face | Observatii |
|---|---|---|
| `ApiSecretMiddleware::handle()` | Compara `X-API-Secret` (header) sau `api_secret` (body/query) cu `config('app.api_secret')` | 401 JSON daca lipseste/e gresit. Alias `api.secret` |
| `OrderRequest::authorize()` / `failedValidation()` | Autorizarea e lasata middleware-ului; validarea raspunde mereu JSON 422 | WooCommerce nu trimite `Accept: application/json` |

**Config / env:** `API_SECRET`.

**De stiut / TODO:** comparatia cu `!==`, nu `hash_equals()` (timing-safe). Rutele web `/env-config`, `/model-save-logs`, `/room-search-test` nu au autentificare.

---

## 11. Helpers: TVA, suma in litere, judete, tari

| Functie | Ce face | Observatii |
|---|---|---|
| `Vat::rate()` | Cota in procente | `ORDER_VAT_RATE`, default 11 |
| `Vat::fraction()` | Cota ca fractie (0.11) | Scrisa in coloanele `cotatva` |
| `Vat::net($gross)` | Suma fara TVA, rotunjita la 2 zecimale | |
| `Vat::vat($gross)` | TVA, astfel incat `net + vat === gross` | |
| `MoneyToWords::toWords($n)` | Suma in litere, in romana (doar partea intreaga) | Folosit la `trznp.descval` |
| `Judet::getAll()` / `getNameByCode()` / `getCodeByName()` | Judete RO, cod <-> nume | |
| `Country::getAll()` / `getNameByCode()` | Tari, cod -> nume | |

**Teste:** `tests/Unit/Orders/OrderHelpersTest.php`.

---

## 12. Infrastructura: SCOPE_IDENTITY, log salvari modele, pagini de test

| Functie | Ce face | Observatii |
|---|---|---|
| `UsesScopeIdentity::insertAndSetId()` | Insert + `SELECT SCOPE_IDENTITY()` in loc de `@@IDENTITY` | Fix pentru triggerele de audit (ex. `trg_client_audit`) care altfel dau id-ul gresit. Folosit de `Client`, `Rezervarehotel`, `Trznp`, `Trzdetnp`, `Trzfact`, `Trzbill` |
| `AppServiceProvider::boot()` | Logheaza fiecare `eloquent.saved` in canalul `model_saves` (`storage/logs/model-saves*.log`, 30 zile) | Model, tabel, cheie, controller, campuri |
| `ModelSaveLogController::index()` | Pagina `/model-save-logs` care citeste logurile de mai sus | |
| `EnvConfigController::index()` | Pagina `/env-config` cu config-ul curent (parolele ascunse) | |
| `RoomSearchTestController::showForm()` / `submitForm()` | Formular de test pentru cautarea de camere | |

**De stiut / TODO:** cand adaugi un model nou pe un tabel cu trigger si IDENTITY, foloseste `UsesScopeIdentity`.

---

## Anexe

### Anexa A — Tabele DB

| Tabel | Model | PK | Rol |
|---|---|---|---|
| `client` | `Client` | `spaid` | Clienti PF/PJ |
| `camerehotel` | `Camerehotel` | `idcamerehotel` | Camere hotel |
| `rezervarehotel` | `Rezervarehotel` | `idrezervarehotel` | Rezervari |
| `pret` | `Pret` | | Preturi (camere, produse) |
| `genprod` | `Genprod` | `idprod` | Produse/articole |
| `gencard` | `Gencard` | `idgencard` | Componentele unui pachet |
| `gest` | `Gest` | `idgest` | Gestiuni; `nrf` = contor facturi |
| `company` | `Company` | `idfirma` | Firma emitenta |
| `trzbill` | `Trzbill` | `nrbill` | Nota de informare (header) |
| `trzdetbill` | `Trzdetbill` | `idtrzf` | Linii nota de informare |
| `detaliitrz` | `Detaliitrz` | `idtrz` | Detalii tranzactie (`facturat = 0` pentru web) |
| `trznp` | `Trznp` | `nrnpint` | Nota de plata (header) |
| `trzdetnp` | `Trzdetnp` | `idtrzf` | Linii nota de plata |
| `trzdet` | `Trzdet` | `idtrzdet` | Detaliu tranzactie NP |
| `trzfact` | `Trzfact` | `nrfact` | Factura (header) |
| `trzdetfact` | `Trzdetfact` | | Linii factura |
| `trzcard` | `Trzcard` | `idtrzcard` | Carduri/abonamente |

### Anexa B — Config / env (`config/orders.php`)

| Cheie config | Env | Default | Rol |
|---|---|---|---|
| `orders.vat_rate` | `ORDER_VAT_RATE` | `11` | Cota TVA (%) |
| `orders.idfirma` | `ORDER_IDFIRMA` | `1` | Firma emitenta |
| `orders.nr_gest` | `ORDER_NR_GEST` | `1` | Gestiunea pentru numere de factura |
| `orders.np_invoice.enabled` | `CREATE_NP_AND_INVOICE` | `false` | Creeaza NP + factura |
| `orders.packages.filters` | `ORDER_PACKAGE_FILTERS` | `WEB:PACK` | Clasa/grupa pachete web |
| `orders.hotels` / `orders.default_hotel` | — | `1 => '1~Hotel Noblesse'` / `'1~Hotel Royal'` | Valoarea `client.hotel` |
| `orders.mail.queue` | `ORDER_MAIL_QUEUE` | `false` | Emailuri pe coada |
| `orders.mail.client_name` | `CLIENT_NAME` | `Mirage MedSPA Hotel` | Nume in subiectul voucherului |
| `orders.mail.hotel_subject` | `ORDER_HOTEL_MAIL_SUBJECT` | | Subiect email hotel |
| `orders.mail.hotel_staff` | `ORDER_HOTEL_MAIL_STAFF` | | Staff in copie la hotel |
| `orders.mail.voucher_staff` | `ORDER_VOUCHER_MAIL_STAFF` | | Staff in copie la vouchere |
| `app.api_secret` | `API_SECRET` | | Secretul API |
