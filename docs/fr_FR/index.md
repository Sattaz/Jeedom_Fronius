Description 
===

Le plugin Fronius permet de récupérer les informations de production photovoltaïque des onduleurs de la marque Fronius.

Il supporte les APIs de version 0 et 1.

En application courante, il permet par exemple d'allumer ou d'éteindre un équipement en fonction de la puissance réelle générée par votre installation PV depuis un scénario dans Jeedom.

Configuration du plugin 
===

La configuration du plugin est très simple.
Une fois installé, il suffit de créer un nouvel équipement et de le configurer de la manière suivantes:

![Fronius](https://sattaz.github.io/Jeedom_Fronius/pictures/Fronius_2.jpg)

Comme pour chaque plugin Jeedom, il faudra indiquer le 'Nom de l'équipement', un 'Objet parent' et une 'Catégorie'.
Ne pas oublier de cocher les cases 'Activer' et 'Visible'.

Puis viennent aussi quelques paramètres dédiés aux spécification de l'onduleur Fronius:

-   IP de l'onduleur : veuillez renseigner l'adresse IP de l'interface web de l'onduleur.

-   Port le l'onduleur : veuillez renseigner le port à utiliser pour se connecter à l'interface web de l'onduleur.

-   Puissance crête : veuillez renseigner la puissance de votre installation photovoltaïque (en watts)

-> Veuillez dès à présent appuyer sur le bouton 'Sauvegarder' afin d'enregistrer la configuration.
-> Cette action va automatiquement créer les commandes de l'équipement.

Commandes de l'équipement 
===

Comme énoncé dans le précédent chapitre, les commandes de l'équipement sont automatiquement crées dès lors que la configuration est sauvegardée.

IMPORTANT : ne pas effacer la commande 'VersionAPI' car elle est automatiquement créée et utilisée pour se connecter à l'onduleur.

![Fronius](https://sattaz.github.io/Jeedom_Fronius/pictures/Fronius_3.jpg)



Le widget 
===

Le widget arrive comme montré sur la photo ci-après et la jauge indiquant la valeur 'PV Production' est calibrée (min/max) par la puissance crête indiquée dans la configuration de l'équipement.

![Fronius](https://sattaz.github.io/Jeedom_Fronius/pictures/Fronius_1.jpg)

Libre à vous de modifier le widget afin de l'adapter à votre style de présentation.



Autres informations 
===

* Le plugin rafraîchi les données toutes les minutes.
* Vous pouvez créer plusieurs équipements pour gérer les onduleurs d'une ferme photovoltaïque.
