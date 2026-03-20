# Guide de déploiement — ASA Paillon Web

## 1. Prérequis Hostinger

- Hébergement mutualisé avec PHP 8.x et MySQL 8.x
- Accès hPanel (panneau de contrôle Hostinger)
- Une adresse email Hostinger (ex: asa@allodero.fr) pour l'envoi des OTP

---

## 2. Créer la base de données MySQL

1. Dans hPanel → **Bases de données** → **MySQL Databases**
2. Créer une BDD : ex. `u123456_asa`
3. Créer un utilisateur MySQL dédié avec mot de passe fort
4. Attribuer les droits : SELECT, INSERT, UPDATE, DELETE (pas DROP ni ALTER)
5. Noter les informations pour `config.php`

---

## 3. Installer PHPMailer

1. Télécharger : https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip
2. Extraire l'archive
3. Copier ces 3 fichiers dans `lib/phpmailer/` :
   - `src/PHPMailer.php` → `lib/phpmailer/PHPMailer.php`
   - `src/SMTP.php`      → `lib/phpmailer/SMTP.php`
   - `src/Exception.php` → `lib/phpmailer/Exception.php`

---

## 4. Configurer config.php

Éditer `config.php` et remplir :

```php
define('DB_NAME', 'u123456_asa');       // votre BDD Hostinger
define('DB_USER', 'u123456_asa');       // votre utilisateur MySQL
define('DB_PASS', 'xxx');               // votre mot de passe MySQL

define('SMTP_USER', 'asa@allodero.fr'); // votre email Hostinger
define('SMTP_PASS', 'xxx');             // mot de passe de cet email
define('SMTP_FROM', 'asa@allodero.fr');
```

---

## 5. Uploader les fichiers

Via **File Manager** Hostinger ou **FTP** (FileZilla) :

Uploader tout le dossier `web/` dans : `public_html/asa/`

Structure finale sur le serveur :
```
public_html/
└── asa/
    ├── .htaccess
    ├── config.php
    ├── index.php
    ├── login.php
    ├── verify.php
    ├── logout.php
    ├── edit.php
    ├── admin/
    ├── includes/
    ├── lib/phpmailer/    ← les 3 fichiers PHPMailer ici
    └── assets/
```

---

## 6. Importer le schéma SQL

1. hPanel → **phpMyAdmin** → sélectionner votre base
2. Onglet **Importer** → choisir `schema.sql`
3. Cliquer **Exécuter**

---

## 7. Créer l'utilisateur administrateur

Dans phpMyAdmin → onglet **SQL** :

```sql
UPDATE utilisateurs
SET email = 'votre-email-reel@exemple.com',
    nom   = 'Votre Nom'
WHERE role = 'admin';
```

---

## 8. Importer les données arrosants

### Option A — Via interface web (recommandé)
1. Générer le CSV : `python export_csv.py` (depuis votre PC)
2. Se connecter sur https://allodero.fr/asa/
3. Admin → **Importer des données**
4. Uploader `arrosants_import.csv`, séparateur `;`, cocher "Ignorer la 1ère ligne"

### Option B — Via phpMyAdmin
1. phpMyAdmin → Import → choisir `arrosants_import.csv`
2. Format : CSV, séparateur `;`, cocher "La première ligne contient les noms de colonnes"

---

## 9. Tester

1. Aller sur https://allodero.fr/asa/login.php
2. Saisir l'email admin → recevoir le code
3. Saisir le code → accès à la liste
4. Tester la modification d'un arrosant
5. Tester l'ajout d'un utilisateur en lecture seule

---

## 10. Ajouter d'autres utilisateurs

Admin → **Utilisateurs** → remplir le formulaire :
- Nom, prénom, email (= identifiant de connexion)
- Rôle : **Lecture** (consultation), **Écriture** (modification), **Admin**

L'utilisateur n'a pas besoin de mot de passe — il reçoit un code par email à chaque connexion.
