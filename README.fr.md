# PHPBrowser et BrowserShell

Un navigateur scriptable en PHP avec son interpréteur de commande !

Vous pouvez naviguer sur le web, remplir et envoyer des formulaires

Vous pouvez utiliser les méthodes du Browser depuis votre script PHP
ou en exécutant l'interpréteur avec l'entrée standard ou un fichier batch

Browser.php est le navigateur capable d'effectuer des requêtes
BrowserShell.php est un interpréteur de commande pour diriger Browser.php

## Utilisation

Utiliser ne navigaeur dans votre script PHP:

    require_once 'lib/Browser.php'

Passer les commandes depuis l'entrée standard

    php BrowserShell.php --stdin

Lire les commandes d'un fichier batch

    php BrowserShell.php --file=<fichier batch> [--config=<fichier de config>] [--pause=<duree entre les appels reseau>] [--debug=1|0]

### Méthode de l'interpréteur
(voir browser-shell.man)

    browse   : Navige vers une URL
    submit   : Valide le formulaire. Peut être suivi du nom ou de l'ID du bouton à utiliser
    write    : Enregistre le contenu récupérée dans le fichier indiqué
    print    : Affiche le contenu récupéré directement dans la consile (la fenêtre d'exécution)
    history  : Naviguer dans l'historique
    use-form : Nom du formulaire à utiliser pour la suite.
    reset    : Réinitialise le formulaire
    set      : Définir une valeur pour un élément de formulaire. Pour plusieurs valeurs, utiliser un JSON
    unset    : Supprime l'élément des données de formulaire qui seront transmise
    check    : Coche une checkbox. Equivalent de set avec "on" pour valeur
    uncheck  : Décoche une checkbox. Equivalent de unset
    set-var  : Définie une variable de script qui pourra être appelée par %var:nom-de-la-variable%
    include  : Exécuter un autre fichier de commande
    message  : Affiche un message dans la console (la fenêtre d'exécution)
    debug    : Affiche un message dans la console *si* le mode debug est activé (cf. fichier cfg section [shell])

Examples

    browse http://www.google.fr  Pour naviguer à l'URL http://www.google.fr
    
    include commandes.browser Pour lancer les commandes définies dans commandes.browser 
    
    use-form          Utilise pour la suite le premier formulaire trouvé dans la page
    use-form formName Utilise pour la suite le formulaire avec le nom "formName"
    use-form #formID  Utilise pour la suite le formulaire avec l'ID "formID"
    
    submit               Pour envoyer le formulaire
    submit submitButton  Pour envoyer le formulaire en utilisant le bouton ayant pour nom "submitButton"
    submit #submitBtnId  Pour envoyer le formulaire en utilisant le bouton ayant pour id "submitBtnId"
    
    write  nomDuFichier  Enregistre le contenu récupéré lors du dernier browse/submit dans le fichier "nomDuFichier"
    
    history goto:[label]   Retourne au label "label" en utilisant l'historique (comme un clic dans l'historique du navigateur)
    history -1             Retourne à la page précédente
    history back           Retourne à la page précédente
    history +1             Retourne à la page suivante
    history forward        Retourne à la page suivante
    history 0              Recharge la page
    history reload         Recharge la page
    history -5             Retourne 5 pages en arrière
    history +2             Retourne 2 pages en avant
    history [param] browse Applique le paramètre (goto, -N, +N, reload, etc.) en indiquant de faire un simple browse (ne pas re-soumettre le formulaire éventuel)
    
    reset                  Réinitialise le formulaire en cours
    
    set nom = valeur       Attribut "valeur" à l'élément ayant le nom "nom"
    set #id = valeur       Attribut "valeur" à l'élément ayant l'id "id"
    set nom = ["a", "b"]   Attribut la liste de valeurs "a" et "b" à l'élément nommé "nom"
    set nom = "["a", "b"]" Attribut la valeur ["a", "b"] à l'élément nommé "nom"
    
    check macheckbox       Coche la checkbox ayant pour nom "macheckbox"
    check #checkboxUD      Coche la checkbox ayant pour id "checkboxID"
    uncheck macheckbox     DéCoche la checkbox ayant pour nom "macheckbox"
    uncheck #checkboxUD    DéCoche la checkbox ayant pour id "checkboxID"
    
    unset nom              Supprime l'élément nommé "nom" du formulaire à transmettre
    unset #id              Supprime l'élément identifé par  "id" du formulaire à transmettre
    
    set-var variable = val Attribut la valeur "val" à la variable "variable". La variable pourra être utilisée en appelant %var:variable%
    
    print mon message      Affiche "mon message" suivi d'un saut de ligne
    print                  Affiche juste une ligne vide
    
    debug message de debug Affiche "message de debug" si debug = 1
    debug                  Affiche une ligne vide si debug = 1


### Labels :

Vous pouvez utiliser des labels / groupes de commandes avec:

    [label]

Vous pouvez ensuite revenir au label (comme un clic dans l'historique du navigateur) avec :

    history goto:[label]

Vous pouvez rejouer le groupe de commande avec :
    
    play label

Example :
    
    [label1]
    
    browse http://www.google.fr
    write my-file-1.htm
    
    [label2]
    
    browse http://www.php.net
    write my-file-2.htm
    play label1
    
    [end-label-2]
    
    play label2

Est équivalent de :

    [label1]
    
    browse http://www.google.fr
    write my-file-1.htm
    
    [label2]
    
    browse http://www.php.net
    write my-file-2.htm
    
    browse http://www.google.fr
    write my-file-1.htm
    
    [end-label-2]
    
    browse http://www.php.net
    write my-file-2.htm
    
    browse http://www.google.fr
    write my-file-1.htm


### Variables utilisables

    {%system:login%}
    {%system:timestamp%}
    {%system:os%}
    {%system:rand%}
    {%system:rand:min:max%}

    {%date:format%}         format est au format francais: AAAA = annee sur 4 chiffre, JJ = jour sur 2 chiffre, etc.
    {%date:php:format%}     Pour utiliser directement le format PHP comme dans la fonction date()
    {%date_interval:interval:format%} Interval est comme avec PHP : par exemple "-2 month"

    {%cfg:config-field-name%} Renvoi la valeur du champ "config-field-name" de la section [data] du fichier de configuration

    {%var:var-name%}          Renvoi la valeur de variable "var-name" definie par la commande "set-var var-name = value"

Note :
Les variables peuvent être imbriquées

    {%cfg:constant-string{%var:var-name%}%}



## Fichier de configuration

Il y a 3 sections

[browser] pour la configuration du browser :
user-agent = User-Agent
cookie-file = Fichier a utiliser pour les cookies
exit-if-error = 1 pour quitter en cas d'erreur serveur (code autre que 2xx,  1xx ou 3xx)

[shell] Pour la configuration de l'interpréteur
pause-duration = (delais en secondes entre les appels réseaux)
check-formelement-exists = 1 pour mettre les valeurs dans les champs uniquement si le champ existe dans le DOM
check-formelement-type = 1 pour cocher/décocher les checkbox uniquement s'il s'agit bien d'une checkbox
debug = 1 pour voir les messages d'exécution

[data] pour la configuration personalisée, utilisable dans le fichier batch

custom-field = custom-value

Ces champs seront utilisables dans le fichier batch avec {%cfg:field-name%}
Il est possible d'utiliser des variables, fonctions et champs de configuration dans cette section [data]

Par exemple :

    [data]
    
    file-name = /home/path/PREFIX_MYFILE_DATA-{%date:php:Y-m-d_His%}-{%system:os%}.EXT

Si la date est le 1er novembre 2017 à 15h26, et que le système est Linux, alors
{%cfg:file-name%} renvois "/home/path/PREFIX_MYFILE_DATA-2017-11-01_15:26-linux.EXT"

Les variables peuvent être imbriquées

Par exemple :

    [data]
 
    date-format = php:Y-m-d_His
    file-name = /home/path/PREFIX_MYFILE_DATA-{%date:{%cfg:date-format%}%}-{%system:os%}.EXT

## Example
    
### Fichier de configuration
 
    ; --------------------------------------------------------
    ; Exemple de fichier INI de configuration
    ;
    ; --------------------------------------------------------

[browser]
user-agent="Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36"
cookie-file=librx.cookie
exit-if-error=1


[shell]

pause-duration=1
check-formelement-exists=1
check-formelement-type=1
debug=0

[data]

recherche-1 = navigateur scriptable
recherche-2 = telecharger php pour windows
    
### Fichier de commandes

    ; --------------------------------------------------------
    ; Exemple de fichier batch
    ;
    ; --------------------------------------------------------
    
    ; Définir des variables personnalisées
    
    set-var numero-recherche = 1
    
    [init]
    
    browse http://www.google.fr

    [recherche1]

    ; Rechercher sur Google and valider avec le bouton "J'ai de la chance"

    set-var texte-a-chercher = {%cfg:recherche-{%var:numero-recherche%}%}
    
    set q = {%var:texte-a-chercher%}
    submit
    
    [telechargement]
    
    ; Sauvegarder les données du site ainsi récupéré dans le fichier
    
    write ./google_recherche{%var:numero-recherche%}.html
    
    [recommencer]
    
    set-var numero-recherche  = 2
    
    history goto:[recherche1]
    
    play recherche1
    play telechargement


ATTENTION !
Si une commande "play" est dans son propre groupe, il y a une boucle infinie !

    [mon-label]

    ...	
    
    play mon-label
    ...
    
Est une boucle infinie

Vous devez utiliser :

    [mon-label]

    ...	

    [un-autre-label]
       	
    play mon-label
    ...



## License

[GNU / LGPL v.3](https://www.gnu.org/licenses/lgpl.html)

    Copyright (C) 2017 Francois RAOULT

    Distribué sous licence LGPL version 3.0
    Vous pouvez obtenir le texte de la licence a:

       https://www.gnu.org/licenses/lgpl.html

    ---------

    Licensed under the LGPL, Version 3.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

       https://www.gnu.org/licenses/lgpl.html

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.

## Contributions

Vous pouvez faire un fork de ce dépot et contribuer par un pull-request

Toutes les contributions, grandes ou petites, majeures ou mineures, ou les fix de bugs ou autre tests
sont les bienvenues et sont appréciées



## Auteur

Francois Raoult | http://francois.raoult.name
