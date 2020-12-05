# inventory
Inventory Management System with invoices and picklists.

# Documentation
-[Install L.A.M.P. stack](https://projects.raspberrypi.org/en/projects/lamp-web-server-with-wordpress/) ( MySQL is now MariaDB ) ~ optionally install Wordpress

* Download the latest version.

* Not sure how this works?  Start with a clean ( empty ) database, then import **demo_inv.sql** and you've got some basic data to get    started! OR, try a clean database: import/load **inventory.sql** into your mysql database. This should set up the basic structure of the database system.

* Modify the includes/config.php and change the variables to match your host, database, username and passwords.

* Change all Folder permission inside uploads folder either add them to group call `www-data` if available or `777`.

* Then logging in by typing **username** and **password**:


   Administrator        | Special User           | Default User
   ---------------------| -----------------------| -------------------
   **Username** : admin | **Username** : special | **Username** : user
   **Password** : admin | **Password** : special | **Password** : user
   
****
-Additional documentation and configuration for your Inventory Management System:

-[Hackster.io](https://www.hackster.io/bitsandbots/serving-your-own-inventory-management-system-6e8b53)
-[Blog](https://coreconduit.com/2019/02/07/using-a-raspberry-pi-for-your-own-inventory-management-system/)

# [Support](https://coreconduit.com/contact/)
Contact Cory:  
****
If you find this project useful...
[Donate](https://www.paypal.com/biz/fund?id=ZDR2NTBSKK7JE)
****

Enhanced by Cory J. Potter aka CoreConduit Consulting Services 2018 - 2020

The application was initially created by Siamon Hasan, using [php](http:php.net),
[mysql](https://www.mysql.com) and [bootstrap](http://getbootstrap.com).
****
