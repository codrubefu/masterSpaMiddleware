# masterSpaMiddleware

Middleware Laravel 10 (SQL Server) intre WooCommerce si baza de date Acord SPA/Hotel.
Documentatia functionalitatilor: `docs/FUNCTIONALITATI.md`.

## Documentarea functiilor noi

Documentatia **nu** se scrie automat. Dupa ce termini de implementat o functie noua sau modifici una existenta
(cod in `app/`, `routes/`, `config/`), si inainte sa inchei tura, intreaba utilizatorul cu `AskUserQuestion`:

- **Adauga ca functionalitate noua**: o sectiune noua in `docs/FUNCTIONALITATI.md`
- **Actualizeaza o functionalitate existenta**: propune sectiunea care se potriveste (ex. `3. Pachete web`)
- **Nu documenta acum**

In intrebare spune ce functii/fisiere ai atins. Daca alege una dintre primele doua optiuni, apeleaza agentul
`function-documenter` cu modul (`NOU` / `UPDATE`), sectiunea (la UPDATE) si lista fisierelor + functiilor atinse.
Daca alege "Nu documenta acum", nu mai intreba pentru aceleasi functii in aceeasi sesiune.

Nu intreba pentru: teste, refactorizari fara schimbare de comportament, fix-uri de formatare.
