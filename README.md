# GelasFileDrop – Secure File Transfer System

## Projectbeschrijving

GelasFileDrop is een beveiligd bestandstransfersysteem waarmee gebruikers bestanden kunnen uploaden en downloaden binnen een afgeschermde omgeving. Het systeem is ontwikkeld met focus op de drie belangrijkste principes van informatiebeveiliging: vertrouwelijkheid, integriteit en authenticatie.

## Doel

Het doel van GelasFileDrop is om bestanden veilig uit te wisselen tussen gebruikers en systemen, terwijl risico's zoals datalekken, bestandsmanipulatie en ongeautoriseerde toegang worden voorkomen.

## Beveiliging

### Vertrouwelijkheid

* Alleen ingelogde gebruikers hebben toegang tot het systeem.
* Het systeem maakt gebruik van HTTPS om gegevens tijdens transport te beveiligen.
* Bestanden zijn alleen toegankelijk voor geautoriseerde gebruikers.

### Integriteit

* Bestanden worden gecontroleerd tijdens het uploadproces.
* Alleen toegestane bestandstypen worden geaccepteerd.
* Ongeldige bestanden worden geweigerd.

### Authenticatie

* Gebruikers moeten inloggen voordat zij bestanden kunnen uploaden of downloaden.
* Sessiebeheer wordt gebruikt om gebruikers geauthenticeerd te houden.

## Functionaliteiten

* Inloggen en uitloggen van gebruikers
* Sessiebeheer
* Beveiligde bestand-upload
* Downloaden van bestanden via een unieke link
* Opslaan van bestanden in een database
* Automatische generatie van downloadlinks
* Logging van uploads en downloads
* Logging van mislukte inlogpogingen
* Gebruikersvriendelijke foutmeldingen

## Database

Bestanden worden opgeslagen in een database met de volgende gegevens:

* Unieke ID
* Bestandsnaam
* Bestandstype
* Bestandsinhoud

## Beveiligingsmaatregelen

* HTTPS verplicht
* Alleen geauthenticeerde toegang
* Bestandstypecontrole
* Bestandsgroottecontrole
* Veilige databasequeries (PDO)
* Logging van belangrijke acties
* Foutafhandeling voor uploads, downloads en authenticatie

## Logging

Het systeem registreert belangrijke gebeurtenissen, waaronder:

* Uploads van bestanden
* Downloads van bestanden
* Mislukte inlogpogingen

Deze logs helpen bij monitoring, controle en probleemoplossing.

## Mogelijke verbeteringen

* Encryptie van opgeslagen bestanden
* Hashing voor integriteitscontrole
* Tijdelijke downloadlinks
* Rollen en rechtenbeheer
* Uitgebreidere monitoring en logging

## Team

Projectweek 3 Cybersecurity

Ontwikkeld door:

* Gabriel
* Keano
* Roni
* Jayden
