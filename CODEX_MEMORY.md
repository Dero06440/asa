# Memo projet - ASA Paillon

Derniere mise a jour: 2026-03-18

## Objet

Application web PHP pour la gestion des arrosants de l'ASA Arrosants et Riverains du Paillon.

Fonctions principales :
- connexion par OTP email
- consultation de la liste des arrosants
- filtrage, tri, pagination
- creation / modification d'un arrosant
- administration des utilisateurs
- menu d'impression avec PDF enveloppes
- gestion d'un bareme de cotisations et d'une simulation
- import CSV / export

## Stack et structure

- PHP procedural, sans framework
- MySQL
- Bootstrap 5 via CDN
- PHPMailer dans `lib/phpmailer/`

Fichiers racine importants :
- `config.php` : detection auto local / production, `BASE_URL`, flags OTP
- `config.local.php` : secrets locaux / overrides
- `schema.sql` : schema complet + fonctions SQL + triggers
- `index.php` : liste principale
- `edit.php` : formulaire ajout / edition arrosant
- `login.php` / `verify.php` / `logout.php` : auth OTP
- `admin/index.php` : tableau de bord admin / editeur
- `admin/print.php` : menu impression
- `admin/print_envelopes.php` : PDF enveloppes
- `admin/utilisateurs.php` : CRUD utilisateurs
- `admin/tarifs.php` : gestion bareme simul
- `admin/import.php` / `admin/export.php` : import / export

Includes :
- `includes/db.php` : connexion PDO
- `includes/auth.php` : session, roles, OTP, logs
- `includes/functions.php` : helpers affichage, flash, pagination
- `includes/simple_pdf.php` : generation PDF simple sans dependance externe
- `includes/mailer.php` : envoi OTP

## Environnements

Le projet est prevu pour fonctionner sans modifier les pages entre local et production.

### Local
- host detecte via `localhost`, `127.0.0.1`, IP privees
- utilise la base MySQL distante Hostinger
- SMTP desactive par defaut
- OTP affiche dans `verify.php` pour test (`APP_DEBUG_OTP = true`)

### Production
- configuration de production chargee via `config.php` + `config.local.php`
- SMTP actif
- URL cible documentee : `https://allodero.fr/asa/login.php`

## Modele de donnees

Tables principales :
- `arrosants`
- `utilisateurs`
- `otp_codes`
- `sessions_log`
- `tarifs`
- `app_settings`

Points importants :
- `arrosants.adresse2` existe
- `arrosants.puisant` existe
- `utilisateurs.role` = `lecteur`, `editeur`, `admin`
- `arrosants.cotisation` est calculee par trigger SQL
- les puisants n'ont pas de surface et utilisent un tarif fixe
- le pourcentage de simulation est memorise dans `app_settings`
- simulation se base sur `tarifs.tarif_simul`

Fonctions SQL critiques :
- `calcul_cotisation_v2(surface, puisant)`
- `calcul_cotisation_simul_v2(surface, puisant)`

Triggers critiques :
- `before_arrosants_insert`
- `before_arrosants_update`

## Regles de droits

- `lecteur` : consultation
- `editeur` : modification arrosants + acces admin partiel + tarifs
- `admin` : gestion utilisateurs + import + tout le reste

Le controle des droits est centralise dans `includes/auth.php` avec `requireRole()` et `hasRole()`.

## Comportement fonctionnel actuel

### Liste (`index.php`)
- acces authentifie obligatoire
- filtres : texte, quartier, ville
- tri par nom, adresse, quartier, parcelles, surface, cotisation, cotisation simul
- pagination 25 lignes
- badges de synthese : nombre d'arrosants, total cotisations, total simulation
- lien `+ Ajouter` visible pour `editeur` et `admin`

### Edition (`edit.php`)
- acces `editeur+`
- creation ou modification
- champ `adresse2` pris en charge
- case `Puisant` qui neutralise la surface
- retour vers la liste preserve via `return_to`
- cotisation et cotisation simul affichees en lecture seule depuis les fonctions SQL

### Administration (`admin/index.php`)
- acces `editeur+`
- cartes de synthese
- journal recent des connexions/actions
- acces gestion utilisateurs et import reserve a `admin`

### Impression (`admin/print.php`)
- acces `editeur+`
- premiere sortie disponible : `Enveloppes`
- generation PDF serveur sans bibliotheque externe
- une enveloppe par page
- destinataires limites aux arrosants actifs avec adresse complete

### Utilisateurs (`admin/utilisateurs.php`)
- acces `admin`
- creation / edition / activation / suppression
- prevention de modification de son propre compte pour certaines actions

## Import / export / donnees

- source historique : `arrosants.xlsx`
- script local : `export_csv.py`
- fichier genere : `arrosants_import.csv`
- migrations SQL presentes :
  - `migrate_simulation_settings.sql`
  - `migrate_puisants.sql`
  - `migrate_tarifs.sql`
  - `migrate_roles.sql`
  - `migrate_drop_ref.sql`
  - `migrate_adresse2.sql`

## Deploiement

Voir :
- `README_LOCAL.md` pour l'usage local / bascule auto
- `DEPLOY.md` pour la mise en ligne Hostinger

Notes de deploiement utiles :
- upload vers `public_html/asa/`
- importer `schema.sql`
- verifier `config.php` / `config.local.php`
- PHPMailer doit etre present dans `lib/phpmailer/`

## Points d'attention pour les prochaines sessions

- Toujours verifier si les changements concernent la base distante Hostinger, meme en local.
- Ne pas casser la detection auto `BASE_URL`.
- Ne pas supposer que le SMTP fonctionne en local.
- Les calculs de cotisation sont portes par MySQL, pas par PHP.
- Les roles `lecteur` / `editeur` / `admin` structurent presque toute la navigation.
- Plusieurs fichiers affichent encore des caracteres mal encodes; prudence sur les encodages lors des edits.

## Routine de reprise recommandee

1. Lire ce fichier.
2. Relire `README_LOCAL.md` si le sujet touche l'environnement.
3. Relire `schema.sql` si le sujet touche donnees / cotisations / roles.
4. Ouvrir la page PHP directement concernee.
5. Verifier si une migration SQL est necessaire avant de coder.
