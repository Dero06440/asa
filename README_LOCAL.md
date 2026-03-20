# ASA Paillon - Test local et bascule Hostinger

Ce projet fonctionne maintenant en `local` et en `production` sans modifier les pages avant copie.

## Principe

- En `local` (`localhost`, `127.0.0.1`, IP interne), le site utilise :
  - la base MySQL web Hostinger
  - l'hote MySQL distant `193.203.168.87` (force IPv4)
  - OTP sans envoi SMTP
  - affichage du code OTP directement sur la page de verification
- En `production`, le site utilise automatiquement la configuration Hostinger definie dans `config.php`.

`BASE_URL` est calcule automatiquement depuis l'URL d'acces.

## Test en local avec XAMPP

1. Installer `XAMPP`.
2. Copier le dossier du projet dans `C:\xampp\htdocs\asa`.
3. Demarrer `Apache`.
4. Verifier que la base MySQL web Hostinger est accessible avec les identifiants de [`config.php`](C:/Users/bigde/GitHtml/asa/config.php).
5. Ouvrir `http://localhost/asa/login.php`.

## Connexion en local

1. Saisir un email existant dans la table `utilisateurs`.
2. Valider.
3. Sur `verify.php`, le code OTP de test s'affiche dans un encadre jaune.
4. Saisir ce code pour vous connecter.

## Mise en ligne sur Hostinger

1. Copier les fichiers du projet dans `public_html/asa/`.
2. Importer [`schema.sql`](C:/Users/bigde/GitHtml/asa/schema.sql) dans la base Hostinger.
3. Verifier les identifiants de production dans [`config.php`](C:/Users/bigde/GitHtml/asa/config.php).
4. Ouvrir `https://allodero.fr/asa/login.php`.

## Bascule local -> web

- Vous developpez en local sur `localhost`.
- Vous copiez ensuite les fichiers vers Hostinger.
- Aucune modification des pages n'est necessaire avant upload.
- Le mode `production` s'active automatiquement hors adresse locale.

## Points d'attention

- Le local utilise maintenant la base web Hostinger.
- Le local n'envoie pas d'email.
- En production, le SMTP Hostinger reste actif.
